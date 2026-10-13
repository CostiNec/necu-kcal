<?php

namespace Tests\Feature;

use App\Services\NutritionEstimation\GroqNutritionEstimator;
use App\Services\NutritionEstimation\NutritionEstimator;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GroqNutritionEstimateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'nutrition-ai.provider' => 'groq',
            'services.groq.api_key' => 'test-groq-key',
            'services.groq.base_url' => 'https://groq.test/openai/v1',
            'services.groq.nutrition_model' => 'qwen/qwen3.8-27b',
        ]);
        Http::preventStrayRequests();
    }

    public function test_groq_is_resolved_and_returns_validated_meal_entries(): void
    {
        Http::fake(['groq.test/*' => Http::response($this->response())]);
        $estimator = app(NutritionEstimator::class);
        $this->assertInstanceOf(GroqNutritionEstimator::class, $estimator);
        $result = $estimator->estimate('Două ouă fierte', 'ro');
        $this->assertSame('Ouă fierte', $result['entries'][0]['name']);
        $this->assertSame(100.0, $result['entries'][0]['weight_grams']);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://groq.test/openai/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-groq-key')
                && $data['model'] === 'qwen/qwen3.8-27b'
                && $data['response_format']['type'] === 'json_schema'
                && $data['response_format']['json_schema']['strict'] === true
                && $data['response_format']['json_schema']['schema']['required'] === ['entries']
                && str_contains($data['messages'][0]['content'], 'locale is ro')
                && $data['messages'][1]['content'][0]['text'] === 'Două ouă fierte';
        });
    }

    public function test_meal_photos_are_encoded_with_their_mime_types(): void
    {
        Http::fake(['groq.test/*' => Http::response($this->response())]);
        $images = [UploadedFile::fake()->image('meal.jpg'), UploadedFile::fake()->image('label.png')];
        app(NutritionEstimator::class)->estimate('Eggs with a package label', 'en', $images);
        Http::assertSent(function (Request $request) use ($images): bool {
            $content = $request->data()['messages'][1]['content'];

            return count($content) === 3
                && $content[0]['text'] === 'Eggs with a package label'
                && $request->data()['response_format']['json_schema']['strict'] === true
                && $content[1]['image_url']['url'] === 'data:image/jpeg;base64,'.base64_encode($images[0]->get())
                && $content[2]['image_url']['url'] === 'data:image/png;base64,'.base64_encode($images[1]->get());
        });
    }

    public function test_ten_day_photos_fit_three_sheets_without_losing_the_last_photo(): void
    {
        Http::fake(['groq.test/*' => Http::response($this->response(day: true))]);
        $images = [];
        for ($number = 1; $number <= 10; $number++) {
            $file = UploadedFile::fake()->image("photo-$number.png", 20, 20);
            $image = imagecreatetruecolor(20, 20);
            imagefill($image, 0, 0, imagecolorallocate($image, $number * 20, 0, 0));
            imagepng($image, $file->getPathname());
            imagedestroy($image);
            $images[] = $file;
        }
        $result = app(NutritionEstimator::class)->estimateDay('Photos 1-4 breakfast, rest lunch.', 'en', $images);
        $this->assertSame('breakfast', $result['entries'][0]['meal']);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();
            $content = $data['messages'][1]['content'];
            $this->assertCount(4, $content);
            foreach (array_slice($content, 1) as $sheetIndex => $input) {
                $sheet = imagecreatefromstring(base64_decode(explode(',', $input['image_url']['url'], 2)[1]));
                $this->assertSame(1536, imagesx($sheet));
                $panels = $sheetIndex === 2 ? 2 : 4;
                for ($panel = 0; $panel < $panels; $panel++) {
                    $color = imagecolorsforindex($sheet, imagecolorat($sheet, ($panel % 2) * 768 + 384, intdiv($panel, 2) * 800 + 42));
                    $this->assertEqualsWithDelta(($sheetIndex * 4 + $panel + 1) * 20, $color['red'], 3);
                }
                imagedestroy($sheet);
            }

            return $data['max_completion_tokens'] === 8192
                && isset($data['response_format']['json_schema']['schema']['properties']['entries']['items']['properties']['meal'])
                && str_contains($content[0]['text'], 'Photos 1-4');
        });
    }

    public function test_transient_failures_are_retried_once(): void
    {
        Http::fake(['groq.test/*' => Http::sequence()->push([], 503)->push($this->response())]);
        app(NutritionEstimator::class)->estimate('Eggs', 'en');
        Http::assertSentCount(2);
    }

    public function test_long_rate_limits_do_not_block_the_request_with_retries(): void
    {
        Http::fake(['groq.test/*' => Http::response([], 429, ['Retry-After' => '60'])]);
        try {
            app(NutritionEstimator::class)->estimate('Eggs', 'en');
            $this->fail('Expected rate-limit failure.');
        } catch (RuntimeException $e) {
            $this->assertStringStartsWith('Groq request failed with HTTP 429.', $e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_invalid_nutrition_values_are_rejected(): void
    {
        $response = $this->response();
        $payload = json_decode($response['choices'][0]['message']['content'], true);
        $payload['entries'][0]['weight_grams'] = -10;
        $response['choices'][0]['message']['content'] = json_encode($payload);
        Http::fake(['groq.test/*' => Http::response($response)]);
        $this->expectException(RuntimeException::class);
        app(NutritionEstimator::class)->estimate('Eggs', 'en');
    }

    public function test_truncated_json_is_rejected_even_when_the_content_is_valid(): void
    {
        $response = $this->response();
        $response['choices'][0]['finish_reason'] = 'length';
        Http::fake(['groq.test/*' => Http::response($response)]);
        $this->expectExceptionMessage('Groq returned an incomplete estimate.');
        app(NutritionEstimator::class)->estimate('Eggs', 'en');
    }

    public function test_missing_key_fails_without_sending_a_request(): void
    {
        config(['services.groq.api_key' => '']);
        try {
            app(NutritionEstimator::class)->estimate('Eggs', 'en');
            $this->fail('Expected missing key failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('The Groq API key is not configured.', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_an_unexpected_response_shape_includes_diagnostics_and_provider_output(): void
    {
        $response = $this->response();
        $response['choices'][0]['message']['content'] = json_encode(['food' => 'private food description']);
        Http::fake(['groq.test/*' => Http::response($response)]);

        try {
            app(NutritionEstimator::class)->estimate('private user description', 'en');
            $this->fail('Expected invalid response shape failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('entries=null; count=n/a', $exception->getMessage());
            $this->assertStringContainsString('Provider response:', $exception->getMessage());
            $this->assertStringContainsString('private food description', $exception->getMessage());
            $this->assertStringNotContainsString('private user description', $exception->getMessage());
        }
    }

    public function test_an_empty_estimate_is_rejected_without_inventing_a_food(): void
    {
        $response = $this->response();
        $response['choices'][0]['message']['content'] = '{"entries":[]}';
        Http::fake(['groq.test/*' => Http::response($response)]);

        $this->expectExceptionMessage('entries=array; count=0');
        app(NutritionEstimator::class)->estimate('A photo with no identifiable food', 'en');
    }

    public function test_malformed_json_reports_a_parse_error_without_exposing_the_response(): void
    {
        $response = $this->response();
        $response['choices'][0]['message']['content'] = 'private malformed JSON';
        Http::fake(['groq.test/*' => Http::response($response)]);

        $this->expectExceptionMessage('json=Syntax error; root=null; entries=null; count=n/a');
        app(NutritionEstimator::class)->estimate('Eggs', 'en');
    }

    public function test_an_empty_response_is_retried_and_a_valid_estimate_is_returned(): void
    {
        $empty = $this->response();
        $empty['choices'][0]['message']['content'] = '{"entries":[]}';
        Http::fake(['groq.test/*' => Http::sequence()->push($empty)->push($this->response())]);

        $result = app(NutritionEstimator::class)->estimate('30g paine', 'ro');
        $this->assertCount(1, $result['entries']);
        Http::assertSentCount(2);
    }

    public function test_invalid_responses_are_retried_only_once(): void
    {
        $empty = $this->response();
        $empty['choices'][0]['message']['content'] = '{"entries":[]}';
        Http::fake(['groq.test/*' => Http::response($empty)]);
        try {
            app(NutritionEstimator::class)->estimate('30g paine', 'ro');
            $this->fail('Expected invalid estimate failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('after 2 attempt(s)', $exception->getMessage());
            $this->assertStringContainsString('Provider response:', $exception->getMessage());
        }
        Http::assertSentCount(2);
    }

    private function response(bool $day = false): array
    {
        $entry = [
            'name' => 'Ouă fierte',
            'weight_grams' => 100,
            'calories_per_100g' => 155,
            'protein_per_100g' => 12.5,
            'carbohydrates_per_100g' => 1.1,
            'fat_per_100g' => 11,
            'fibre_per_100g' => 0,
            'confidence' => 'high',
            'assumptions' => 'Two eggs.',
        ];
        if ($day) {
            $entry['meal'] = 'breakfast';
        }

        return ['choices' => [[
            'finish_reason' => 'stop',
            'message' => ['content' => json_encode(['entries' => [$entry]])],
        ]]];
    }
}

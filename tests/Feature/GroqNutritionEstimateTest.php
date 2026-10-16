<?php

namespace Tests\Feature;

use App\Services\NutritionEstimation\GroqNutritionEstimator;
use App\Services\NutritionEstimation\NutritionEstimator;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
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
                && $data['temperature'] === 0.2
                && $data['reasoning_format'] === 'hidden'
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

    #[DataProvider('estimateModes')]
    public function test_provider_json_generation_errors_are_retried_for_meals_and_full_days(bool $fullDay): void
    {
        Http::fake(['groq.test/*' => Http::sequence()
            ->push($this->jsonGenerationError(), 400)
            ->push($this->response(day: $fullDay))]);
        $images = [UploadedFile::fake()->image('chicken.jpg'), UploadedFile::fake()->image('scale.jpg')];
        $description = 'pulpe de pui dezosate fara piele cu miere, cantarite crude cu TARE';
        $estimator = app(NutritionEstimator::class);
        $result = $fullDay
            ? $estimator->estimateDay($description, 'ro', $images)
            : $estimator->estimate($description, 'ro', $images);

        $this->assertCount(1, $result['entries']);
        $this->assertSame(100.0, $result['entries'][0]['weight_grams']);
        if ($fullDay) {
            $this->assertSame('breakfast', $result['entries'][0]['meal']);
        }
        Http::assertSentCount(2);
        [$first, $second] = Http::recorded()->map(fn (array $pair): array => $pair[0]->data())->all();
        $this->assertSame($first['messages'][1], $second['messages'][1]);
        $this->assertSame($description, $second['messages'][1]['content'][0]['text']);
        $this->assertCount(3, $second['messages'][1]['content']);
        $this->assertTrue($first['response_format']['json_schema']['strict']);
        $this->assertSame(['type' => 'json_object'], $second['response_format']);
        $this->assertStringContainsString(json_encode($first['response_format']['json_schema']['schema']), $second['messages'][0]['content']);
        $this->assertStringContainsString('previous generation was rejected', $second['messages'][0]['content']);
    }

    public static function estimateModes(): array
    {
        return [[false], [true]];
    }

    public function test_a_romanian_food_list_recovers_using_json_object_mode(): void
    {
        $response = $this->response();
        $template = json_decode($response['choices'][0]['message']['content'], true)['entries'][0];
        $entries = [];
        foreach (['Parizer' => 50, 'Paine' => 70, 'Cascaval' => 30] as $name => $weight) {
            $entries[] = [...$template, 'name' => $name, 'weight_grams' => $weight];
        }
        $response['choices'][0]['message']['content'] = json_encode(['entries' => $entries]);
        $error = $this->jsonGenerationError();
        $error['error']['failed_generation'] = '{\\""';
        Http::fake(['groq.test/*' => Http::sequence()->push($error, 400)->push($response)]);

        $result = app(NutritionEstimator::class)->estimate("Parizer 50g\nPaine 70g\nCascaval 30g", 'ro');

        $this->assertSame(['Parizer', 'Paine', 'Cascaval'], array_column($result['entries'], 'name'));
        $this->assertSame([50.0, 70.0, 30.0], array_column($result['entries'], 'weight_grams'));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request['response_format'] === ['type' => 'json_object']);
    }

    #[DataProvider('textRecoveryModes')]
    public function test_repeated_json_errors_recover_the_pizza_prompt_using_validated_text(bool $fullDay, bool $fenced): void
    {
        $response = $this->response(day: $fullDay);
        $payload = json_decode($response['choices'][0]['message']['content'], true);
        $payload['entries'][0]['name'] = 'Pizza Zest';
        $payload['entries'][0]['weight_grams'] = 620;
        $output = json_encode($payload);
        $response['choices'][0]['message']['content'] = $fenced ? "```json\n".$output."\n```" : $output;
        $error = $this->jsonGenerationError();
        $error['error']['failed_generation'] = '{fant';
        Http::fake(['groq.test/*' => Http::sequence()
            ->push($error, 400)
            ->push($error, 400)
            ->push($response)]);
        $images = [UploadedFile::fake()->image('pizza.jpg')];
        $estimator = app(NutritionEstimator::class);

        $result = $fullDay
            ? $estimator->estimateDay('pizza zest - 620g', 'ro', $images)
            : $estimator->estimate('pizza zest - 620g', 'ro', $images);

        $this->assertSame('Pizza Zest', $result['entries'][0]['name']);
        $this->assertSame(620.0, $result['entries'][0]['weight_grams']);
        if ($fullDay) {
            $this->assertSame('breakfast', $result['entries'][0]['meal']);
        }
        Http::assertSentCount(3);
        [$first, $second, $third] = Http::recorded()->map(fn (array $pair): array => $pair[0]->data())->all();
        $this->assertSame('json_schema', $first['response_format']['type']);
        $this->assertSame(['type' => 'json_object'], $second['response_format']);
        $this->assertSame(['type' => 'text'], $third['response_format']);
        $this->assertSame($first['messages'][1], $third['messages'][1]);
        $this->assertSame('pizza zest - 620g', $third['messages'][1]['content'][0]['text']);
        $this->assertCount(2, $third['messages'][1]['content']);
        $this->assertSame('hidden', $third['reasoning_format']);
        $this->assertStringContainsString(json_encode($first['response_format']['json_schema']['schema']), $third['messages'][0]['content']);
    }

    public static function textRecoveryModes(): array
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

    #[DataProvider('invalidTextRecoveryOutputs')]
    public function test_text_recovery_still_rejects_invalid_estimates(string $kind): void
    {
        $response = $this->response();
        $payload = json_decode($response['choices'][0]['message']['content'], true);
        if ($kind === 'negative_weight') {
            $payload['entries'][0]['weight_grams'] = -620;
        } elseif ($kind === 'missing_nutrient') {
            unset($payload['entries'][0]['protein_per_100g']);
        } elseif ($kind === 'empty_entries') {
            $payload['entries'] = [];
        }
        $output = match ($kind) {
            'malformed' => '{fant',
            'prose' => 'Here is the estimate: '.json_encode($payload),
            default => "```json\n".json_encode($payload)."\n```",
        };
        $response['choices'][0]['message']['content'] = $output;
        Http::fake(['groq.test/*' => Http::sequence()
            ->push($this->jsonGenerationError(), 400)
            ->push($this->jsonGenerationError(), 400)
            ->push($response)]);

        try {
            app(NutritionEstimator::class)->estimate('pizza zest - 620g', 'ro');
            $this->fail('Expected invalid text recovery failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('after 3 attempt(s)', $exception->getMessage());
            $this->assertStringContainsString("Provider response:\n".$output, $exception->getMessage());
        }
        Http::assertSentCount(3);
    }

    public static function invalidTextRecoveryOutputs(): array
    {
        return [['malformed'], ['negative_weight'], ['missing_nutrient'], ['empty_entries'], ['prose']];
    }

    public function test_repeated_provider_json_errors_stop_after_text_recovery_and_keep_diagnostics(): void
    {
        Http::fake(['groq.test/*' => Http::response($this->jsonGenerationError(), 400)]);

        try {
            app(NutritionEstimator::class)->estimate('Chicken', 'en');
            $this->fail('Expected generation failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Groq request failed with HTTP 400.', $exception->getMessage());
            $this->assertStringContainsString('json_validate_failed', $exception->getMessage());
            $this->assertStringContainsString('failed_generation', $exception->getMessage());
            $this->assertStringContainsString('Generation attempt: 3; response format: text.', $exception->getMessage());
        }
        Http::assertSentCount(3);
    }

    public function test_json_recovery_stops_when_the_timeout_budget_is_exhausted(): void
    {
        config(['nutrition-ai.timeout' => 2]);
        Http::fake(['groq.test/*' => Http::response($this->jsonGenerationError(), 400)]);

        try {
            app(NutritionEstimator::class)->estimate('pizza zest - 620g', 'ro');
            $this->fail('Expected generation failure without time for recovery.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Generation attempt: 1; response format: json_schema.', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_qwen_reasoning_options_are_not_sent_to_other_models(): void
    {
        config(['services.groq.nutrition_model' => 'openai/gpt-oss-20b']);
        Http::fake(['groq.test/*' => Http::response($this->response())]);

        app(NutritionEstimator::class)->estimate('pizza zest - 620g', 'ro');

        Http::assertSent(fn (Request $request): bool => ! array_key_exists('reasoning_format', $request->data()));
    }

    public function test_other_bad_requests_are_not_retried(): void
    {
        Http::fake(['groq.test/*' => Http::response(['error' => ['code' => 'invalid_request_error']], 400)]);

        try {
            app(NutritionEstimator::class)->estimate('Chicken', 'en');
            $this->fail('Expected request failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Groq request failed with HTTP 400.', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_an_invalid_estimate_after_a_provider_json_error_is_still_rejected(): void
    {
        $invalid = $this->response();
        $payload = json_decode($invalid['choices'][0]['message']['content'], true);
        $payload['entries'][0]['weight_grams'] = -10;
        $invalid['choices'][0]['message']['content'] = json_encode($payload);
        Http::fake(['groq.test/*' => Http::sequence()
            ->push($this->jsonGenerationError(), 400)
            ->push($invalid)]);

        try {
            app(NutritionEstimator::class)->estimate('Chicken', 'en');
            $this->fail('Expected validation failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('after 2 attempt(s)', $exception->getMessage());
            $this->assertStringContainsString('invalid nutrition values', $exception->getMessage());
        }
        Http::assertSentCount(2);
    }

    private function jsonGenerationError(): array
    {
        return ['error' => [
            'message' => "Failed to generate JSON. Please adjust your prompt. See 'failed_generation' for more details.",
            'type' => 'invalid_request_error',
            'code' => 'json_validate_failed',
            'failed_generation' => '{(entries=[], comprehension=Pâte dez오sseerate dezóżerate dezourage',
        ]];
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

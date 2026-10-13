<?php

namespace App\Services\NutritionEstimation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GroqNutritionEstimator extends AbstractNutritionEstimator
{
    public function estimate(string $description, string $locale, array $images = []): array
    {
        return $this->generateEstimate($description, $locale, $images, false);
    }

    public function estimateDay(string $description, string $locale, array $images = []): array
    {
        return $this->generateEstimate($description, $locale, $images, true);
    }

    /** @param array<int, UploadedFile> $images */
    private function generateEstimate(string $description, string $locale, array $images, bool $fullDay): array
    {
        $apiKey = trim((string) config('services.groq.api_key'));

        if ($apiKey === '') {
            throw new RuntimeException('The Groq API key is not configured.');
        }

        $schema = $fullDay ? $this->daySchema() : $this->schema();
        $instructions = $fullDay ? $this->dayInstructions($locale) : $this->instructions($locale);
        $instructions .= "\nReturn only the nutrition estimate in the required JSON structure. Use foods identified in the description even when photos are unclear or unrelated. A list of foods and weights is sufficient input. Return an empty entries array only when neither text nor images identify food or drink; do not invent foods.";
        $instructions .= "\nNumbered panels in image sheets refer to the original photo order. Treat each panel as a separate photo, not a separate serving.";
        $content = [[
            'type' => 'text',
            'text' => $description !== '' ? $description : ($fullDay
                ? 'Reconstruct the photographed food-diary day.'
                : 'Estimate the photographed food.'),
        ], ...$this->imageContent($images)];
        $timeout = (int) config($fullDay ? 'nutrition-ai.full_day_timeout' : 'nutrition-ai.timeout', 120);

        $deadline = microtime(true) + $timeout;
        for ($generation = 0; $generation < 2; $generation++) {
            $response = Http::baseUrl(rtrim((string) config('services.groq.base_url'), '/'))
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(max(1, (int) (($deadline - microtime(true) - 5) / 2)))
                ->retry(
                    2,
                    fn (int $attempt, Throwable $exception): int => $exception instanceof RequestException
                        ? max(1000, (int) $exception->response->header('Retry-After') * 1000)
                        : 1000,
                    function (Throwable $exception, PendingRequest $request): bool {
                        if ($exception instanceof ConnectionException) {
                            return true;
                        }

                        return $exception instanceof RequestException
                            && ($exception->response->status() === 429 || $exception->response->serverError())
                            && (int) $exception->response->header('Retry-After') <= 5;
                    },
                    throw: false
                )
                ->post('/chat/completions', [
                    'model' => config('services.groq.nutrition_model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $instructions],
                        ['role' => 'user', 'content' => $content],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => $fullDay ? 'day_nutrition_estimate' : 'nutrition_estimate',
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                    'max_completion_tokens' => $fullDay ? 8192 : 4096,
                    'stream' => false,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Groq request failed with HTTP '.$response->status().'.'
                    ."\nProvider response:\n".$response->body());
            }

            if ($response->json('choices.0.finish_reason') !== 'stop') {
                throw new RuntimeException('Groq returned an incomplete estimate.');
            }

            $output = $response->json('choices.0.message.content');

            if (! is_string($output) || trim($output) === '') {
                throw new RuntimeException('Groq returned no estimate.');
            }

            try {
                return $fullDay ? $this->validatedDayEstimate($output) : $this->validatedEstimate($output);
            } catch (RuntimeException $exception) {
                // Keep the response and structural diagnostics available in the copyable error report.
                $decoded = json_decode($output, true);
                $jsonError = json_last_error_msg();
                $entries = is_array($decoded) ? ($decoded['entries'] ?? null) : null;
                $details = sprintf(
                    'json=%s; root=%s; entries=%s; count=%s',
                    $jsonError,
                    get_debug_type($decoded),
                    get_debug_type($entries),
                    is_array($entries) ? (string) count($entries) : 'n/a'
                );

                if ($generation === 0 && microtime(true) < $deadline - 2) {
                    $instructions .= "\nThe previous response failed validation: ".$exception->getMessage()
                        .' Return a corrected estimate for the original input with all required fields and identifiable foods.';

                    continue;
                }

                throw new RuntimeException(
                    'Groq estimate validation failed after '.($generation + 1).' attempt(s): '.$exception->getMessage().' ('.$details.').'
                        ."\nProvider response:\n".$output,
                    0,
                    $exception
                );
            }
        }

        throw new RuntimeException('Groq could not produce a valid estimate.');
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @return array<int, array<string, mixed>>
     */
    private function imageContent(array $images): array
    {
        if (count($images) <= 3) {
            return array_map(fn (UploadedFile $image): array => $this->imageInput(
                $image->get(), $image->getMimeType() ?: 'image/jpeg'
            ), $images);
        }

        if (! extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required for Groq estimates with more than three photos.');
        }

        // Up to ten original photos fit in three sheets, retaining global photo numbers.
        $content = [];
        foreach (array_chunk($images, 4) as $sheetIndex => $group) {
            $sheet = imagecreatetruecolor(1536, (int) ceil(count($group) / 2) * 800);
            $white = imagecolorallocate($sheet, 255, 255, 255);
            $black = imagecolorallocate($sheet, 0, 0, 0);
            imagefill($sheet, 0, 0, $white);

            try {
                foreach ($group as $index => $image) {
                    $source = @imagecreatefromstring($image->get());
                    if ($source === false) {
                        throw new RuntimeException('A photo could not be decoded for Groq.');
                    }
                    try {
                        $scale = min(768 / imagesx($source), 768 / imagesy($source), 1);
                        $width = (int) round(imagesx($source) * $scale);
                        $height = (int) round(imagesy($source) * $scale);
                        $x = ($index % 2) * 768;
                        $y = intdiv($index, 2) * 800;
                        imagestring($sheet, 5, $x + 8, $y + 8, 'Photo '.($sheetIndex * 4 + $index + 1), $black);
                        imagecopyresampled($sheet, $source, $x + intdiv(768 - $width, 2), $y + 32, 0, 0, $width, $height, imagesx($source), imagesy($source));
                    } finally {
                        imagedestroy($source);
                    }
                }
                ob_start();
                try {
                    imagejpeg($sheet, null, 90);
                    $jpeg = ob_get_contents();
                } finally {
                    ob_end_clean();
                }
                $content[] = $this->imageInput($jpeg, 'image/jpeg');
            } finally {
                imagedestroy($sheet);
            }
        }

        return $content;
    }

    private function imageInput(string $bytes, string $mime): array
    {
        return [
            'type' => 'image_url',
            'image_url' => ['url' => 'data:'.$mime.';base64,'.base64_encode($bytes)],
        ];
    }
}

<?php

namespace App\Services\NutritionEstimation;

use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiNutritionEstimator extends AbstractNutritionEstimator
{
    private const API_KEY_CURSOR_CACHE_KEY =
        'nutrition-ai:gemini:api-key-cursor';

    /**
     * @param  array<int, UploadedFile>  $images
     * @return array{
     *     name: string,
     *     weight_grams: float,
     *     calories_per_100g: float,
     *     protein_per_100g: float,
     *     carbohydrates_per_100g: float,
     *     fat_per_100g: float,
     *     fibre_per_100g: float,
     *     confidence: string,
     *     assumptions: string
     * }
     */
    public function estimate(
        string $description,
        string $locale,
        array $images = []
    ): array {
        return $this->generateEstimate(
            $description,
            $images,
            $this->instructions($locale),
            $this->schema(),
            2048,
            false
        );
    }

    public function estimateDay(
        string $description,
        string $locale,
        array $images = []
    ): array {
        return $this->generateEstimate(
            $description,
            $images,
            $this->dayInstructions($locale),
            $this->daySchema(),
            8192,
            true
        );
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function generateEstimate(
        string $description,
        array $images,
        string $instructions,
        array $schema,
        int $maxOutputTokens,
        bool $fullDay
    ): array {
        $apiKeys = array_values(array_unique(array_filter([
            trim((string) config('services.gemini.api_key')),
            trim((string) config('services.gemini.api_key_2')),
            trim((string) config('services.gemini.api_key_3')),
        ])));

        if ($apiKeys === []) {
            throw new RuntimeException(
                'The Gemini API key is not configured.'
            );
        }

        $model = (string) config('services.gemini.nutrition_model');
        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => $this->userParts(
                    $description,
                    $images,
                    $instructions,
                    $fullDay
                ),
            ]],
            'generationConfig' => [
                'maxOutputTokens' => $maxOutputTokens,
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $schema,
            ],
        ];
        $response = null;

        foreach ($this->orderedApiKeys($apiKeys) as $apiKey) {
            $response = Http::baseUrl(
                rtrim((string) config('services.gemini.base_url'), '/')
            )
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->acceptJson()
                ->asJson()
                ->timeout($fullDay
                    ? (int) config('nutrition-ai.full_day_timeout')
                    : (int) config('services.gemini.timeout'))
                ->retry(
                    $fullDay || count($apiKeys) > 1 ? 1 : 2,
                    250,
                    throw: false
                )
                ->post(
                    '/models/'.rawurlencode($model).':generateContent',
                    $payload
                );

            if (
                $response->successful()
                || ! $this->shouldTryAnotherKey($response)
            ) {
                break;
            }
        }

        if (! $response?->successful()) {
            if (! $response) {
                throw new RuntimeException(
                    'No Gemini API key could be selected.'
                );
            }

            $status = $response->status();
            $providerStatus = trim(
                (string) $response->json('error.status', '')
            );
            $providerMessage = trim(
                (string) $response->json('error.message', '')
            );
            $details = implode(
                ': ',
                array_filter([$providerStatus, $providerMessage])
            );

            throw new RuntimeException(
                "Gemini request failed with HTTP {$status}"
                .($details !== '' ? ": {$details}" : '.')
            );
        }

        $output = $this->outputText($response);

        return $fullDay
            ? $this->validatedDayEstimate($output)
            : $this->validatedEstimate($output);
    }

    /**
     * @param  array<int, string>  $apiKeys
     * @return array<int, string>
     */
    private function orderedApiKeys(array $apiKeys): array
    {
        Cache::add(
            self::API_KEY_CURSOR_CACHE_KEY,
            -1,
            now()->addYears(10)
        );
        $cursor = Cache::increment(self::API_KEY_CURSOR_CACHE_KEY);
        $startIndex = is_numeric($cursor)
            ? (int) $cursor % count($apiKeys)
            : 0;
        $ordered = [];

        foreach (range(0, count($apiKeys) - 1) as $offset) {
            $index = ($startIndex + $offset) % count($apiKeys);
            $ordered[] = $apiKeys[$index];
        }

        return $ordered;
    }

    private function shouldTryAnotherKey(Response $response): bool
    {
        return in_array($response->status(), [401, 403, 429], true)
            || $response->serverError();
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @return array<int, array<string, mixed>>
     */
    private function userParts(
        string $description,
        array $images,
        string $instructions,
        bool $fullDay
    ): array {
        $food = $description !== ''
            ? $description
            : ($fullDay
                ? 'Reconstruct the photographed food-diary day.'
                : 'Estimate the photographed food.');
        $parts = [[
            'text' => $instructions
                .($fullDay
                    ? "\n\nFull-day notes:\n{$food}"
                    : "\n\nFood to estimate:\n{$food}"),
        ]];

        foreach ($images as $image) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $image->getMimeType() ?: 'image/jpeg',
                    'data' => base64_encode($image->get()),
                ],
            ];
        }

        return $parts;
    }

    private function outputText(Response $response): string
    {
        if ($response->json('promptFeedback.blockReason')) {
            throw new RuntimeException('Gemini blocked the estimate.');
        }

        foreach ($response->json('candidates', []) as $candidate) {
            if (($candidate['finishReason'] ?? null) === 'MAX_TOKENS') {
                throw new RuntimeException(
                    'Gemini truncated the nutrition estimate.'
                );
            }

            foreach ($candidate['content']['parts'] ?? [] as $part) {
                if (isset($part['text']) && is_string($part['text'])) {
                    return $part['text'];
                }
            }
        }

        throw new RuntimeException('Gemini returned no estimate.');
    }
}

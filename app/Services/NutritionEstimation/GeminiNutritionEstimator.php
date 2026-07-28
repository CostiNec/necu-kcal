<?php

namespace App\Services\NutritionEstimation;

use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiNutritionEstimator extends AbstractNutritionEstimator
{
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
        $apiKey = (string) config('services.gemini.api_key');

        if ($apiKey === '') {
            throw new RuntimeException(
                'The Gemini API key is not configured.'
            );
        }

        $model = (string) config('services.gemini.nutrition_model');
        $response = Http::baseUrl(
            rtrim((string) config('services.gemini.base_url'), '/')
        )
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.gemini.timeout'))
            ->retry(2, 250, throw: false)
            ->post(
                '/models/'.rawurlencode($model).':generateContent',
                [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => $this->userParts(
                            $description,
                            $locale,
                            $images
                        ),
                    ]],
                    'generationConfig' => [
                        'maxOutputTokens' => 2048,
                        'responseMimeType' => 'application/json',
                        'responseJsonSchema' => $this->schema(),
                    ],
                ]
            );

        if (! $response->successful()) {
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

        return $this->validatedEstimate($this->outputText($response));
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @return array<int, array<string, mixed>>
     */
    private function userParts(
        string $description,
        string $locale,
        array $images
    ): array {
        $food = $description !== ''
            ? $description
            : 'Estimate the photographed food.';
        $parts = [[
            'text' => $this->instructions($locale)
                ."\n\nFood to estimate:\n{$food}",
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

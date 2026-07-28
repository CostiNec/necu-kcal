<?php

namespace App\Services\NutritionEstimation;

use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiNutritionEstimator extends AbstractNutritionEstimator
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
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException(
                'The OpenAI API key is not configured.'
            );
        }

        $response = Http::baseUrl(
            rtrim((string) config('services.openai.base_url'), '/')
        )
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.openai.timeout'))
            ->retry(2, 250, throw: false)
            ->post('/responses', [
                'model' => config('services.openai.nutrition_model'),
                'store' => false,
                'max_output_tokens' => 500,
                'input' => [
                    [
                        'role' => 'system',
                        'content' => $this->instructions($locale),
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->userContent(
                            $description,
                            $images
                        ),
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'nutrition_estimate',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenAI could not produce an estimate.'
            );
        }

        return $this->validatedEstimate($this->outputText($response));
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @return array<int, array<string, string>>
     */
    private function userContent(string $description, array $images): array
    {
        $content = [[
            'type' => 'input_text',
            'text' => $description !== ''
                ? $description
                : 'Estimate the photographed food.',
        ]];

        foreach ($images as $image) {
            $mimeType = $image->getMimeType() ?: 'image/jpeg';
            $content[] = [
                'type' => 'input_image',
                'image_url' => sprintf(
                    'data:%s;base64,%s',
                    $mimeType,
                    base64_encode($image->get())
                ),
                'detail' => 'high',
            ];
        }

        return $content;
    }

    private function outputText(Response $response): string
    {
        foreach ($response->json('output', []) as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException(
                        'OpenAI refused the estimate.'
                    );
                }

                if (($content['type'] ?? null) === 'output_text') {
                    return (string) ($content['text'] ?? '');
                }
            }
        }

        throw new RuntimeException('OpenAI returned no estimate.');
    }
}

<?php

namespace App\Services\FoodTranslations;

use Illuminate\Http\Client\Factory;
use RuntimeException;

class DeepLFoodNameTranslator implements FoodNameTranslator
{
    public function __construct(private readonly Factory $http) {}

    public function assertConfigured(): void
    {
        $key = config('food-translations.deepl.key');

        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException(
                'DEEPL_API_KEY is required to translate generic foods.'
            );
        }
    }

    public function translate(
        array $names,
        string $sourceLocale,
        string $targetLocale
    ): array {
        $this->assertConfigured();
        $key = (string) config('food-translations.deepl.key');

        if ($names === []) {
            return [];
        }

        $translations = [];

        foreach (
            $this->requestChunks($names, $sourceLocale, $targetLocale) as $chunk
        ) {
            array_push(
                $translations,
                ...$this->translateChunk(
                    $chunk,
                    $sourceLocale,
                    $targetLocale,
                    $key
                )
            );
        }

        if (
            count($translations) !== count($names)
            || in_array('', $translations, true)
        ) {
            throw new RuntimeException(
                'DeepL returned an incomplete food translation response.'
            );
        }

        return $translations;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function translateChunk(
        array $names,
        string $sourceLocale,
        string $targetLocale,
        string $key
    ): array {
        $response = $this->http
            ->withHeaders([
                'Authorization' => 'DeepL-Auth-Key '.$key,
                'Content-Type' => 'application/json',
            ])
            ->timeout((int) config(
                'food-translations.deepl.timeout',
                60
            ))
            ->retry(3, 500)
            ->post(
                (string) config('food-translations.deepl.url'),
                $this->payload($names, $sourceLocale, $targetLocale)
            )
            ->throw();

        $translations = collect($response->json('translations', []))
            ->map(fn (mixed $translation) => is_array($translation)
                ? trim((string) ($translation['text'] ?? ''))
                : '')
            ->values()
            ->all();

        if (
            count($translations) !== count($names)
            || in_array('', $translations, true)
        ) {
            throw new RuntimeException(
                'DeepL returned an incomplete food translation response.'
            );
        }

        return $translations;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, array<int, string>>
     */
    private function requestChunks(
        array $names,
        string $sourceLocale,
        string $targetLocale
    ): array {
        $maximumBytes = (int) config(
            'food-translations.deepl.max_request_bytes',
            112 * 1024
        );
        $chunks = [];
        $chunk = [];

        foreach (array_values($names) as $name) {
            $candidate = [...$chunk, $name];

            if (
                $chunk !== []
                && $this->payloadBytes(
                    $candidate,
                    $sourceLocale,
                    $targetLocale
                ) > $maximumBytes
            ) {
                $chunks[] = $chunk;
                $chunk = [$name];
            } else {
                $chunk = $candidate;
            }

            if (
                $this->payloadBytes(
                    $chunk,
                    $sourceLocale,
                    $targetLocale
                ) > $maximumBytes
            ) {
                throw new RuntimeException(
                    'A food name is too large for one DeepL request.'
                );
            }
        }

        if ($chunk !== []) {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function payload(
        array $names,
        string $sourceLocale,
        string $targetLocale
    ): array {
        return [
            'text' => array_values($names),
            'source_lang' => mb_strtoupper($sourceLocale),
            'target_lang' => mb_strtoupper($targetLocale),
            'context' => implode(' ', [
                'These are concise food and food preparation names',
                'from a nutrition database. Preserve ingredients,',
                'cuts, cooking methods, and qualifiers.',
            ]),
            'preserve_formatting' => true,
        ];
    }

    /**
     * @param  array<int, string>  $names
     */
    private function payloadBytes(
        array $names,
        string $sourceLocale,
        string $targetLocale
    ): int {
        $json = json_encode(
            $this->payload($names, $sourceLocale, $targetLocale)
        );

        if ($json === false) {
            throw new RuntimeException(
                'Unable to encode the DeepL translation request.'
            );
        }

        return strlen($json);
    }

    public function source(): string
    {
        return 'deepl';
    }
}

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
                [
                    'text' => array_values($names),
                    'source_lang' => mb_strtoupper($sourceLocale),
                    'target_lang' => mb_strtoupper($targetLocale),
                    'context' => implode(' ', [
                        'These are concise food and food preparation names',
                        'from a nutrition database. Preserve ingredients,',
                        'cuts, cooking methods, and qualifiers.',
                    ]),
                    'preserve_formatting' => true,
                ]
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

    public function source(): string
    {
        return 'deepl';
    }
}

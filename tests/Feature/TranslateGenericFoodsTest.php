<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Services\FoodTranslations\FoodNameTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class TranslateGenericFoodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_translates_missing_generic_names_and_indexes_them(): void
    {
        $food = Food::create([
            'name' => 'Chicken breast, cooked',
            'food_type' => 'generic',
            'calories' => 165,
            'is_public' => true,
        ]);
        $food->translations()->create([
            'locale' => 'en',
            'name' => 'Chicken breast, cooked',
        ]);
        config([
            'food-translations.deepl.key' => 'test-key',
            'food-translations.deepl.url' => 'https://deepl.test/v2/translate',
        ]);
        Http::fake([
            'deepl.test/*' => Http::response([
                'translations' => [[
                    'detected_source_language' => 'EN',
                    'text' => 'Piept de pui, gătit',
                ]],
            ]),
        ]);

        $status = Artisan::call('foods:translate-generics');

        $this->assertSame(0, $status, Artisan::output());
        Http::assertSent(fn (Request $request) => $request->url()
            === 'https://deepl.test/v2/translate'
            && $request->hasHeader(
                'Authorization',
                'DeepL-Auth-Key test-key'
            )
            && $request['text'] === ['Chicken breast, cooked']
            && $request['source_lang'] === 'EN'
            && $request['target_lang'] === 'RO');
        $this->assertDatabaseHas('food_translations', [
            'food_id' => $food->id,
            'locale' => 'ro',
            'name' => 'Piept de pui, gătit',
            'translation_source' => 'deepl',
            'reviewed_at' => null,
        ]);
        $this->assertStringContainsString(
            'Chicken breast, cooked',
            $food->fresh()->search_text
        );
        $this->assertStringContainsString(
            'Piept de pui, gătit',
            $food->fresh()->search_text
        );
    }

    public function test_it_never_overwrites_an_existing_translation(): void
    {
        $food = Food::create([
            'name' => 'Tomato, raw',
            'food_type' => 'generic',
            'calories' => 18,
            'is_public' => true,
        ]);
        $food->translations()->create([
            'locale' => 'en',
            'name' => 'Tomato, raw',
        ]);
        $food->translations()->create([
            'locale' => 'ro',
            'name' => 'Roșie crudă',
            'translation_source' => 'manual',
            'reviewed_at' => now(),
        ]);
        $translator = Mockery::mock(FoodNameTranslator::class);
        $translator->shouldReceive('assertConfigured')->once();
        $translator->shouldNotReceive('translate');
        $this->app->instance(FoodNameTranslator::class, $translator);

        $status = Artisan::call('foods:translate-generics');

        $this->assertSame(0, $status, Artisan::output());
        $this->assertDatabaseHas('food_translations', [
            'food_id' => $food->id,
            'locale' => 'ro',
            'name' => 'Roșie crudă',
            'translation_source' => 'manual',
        ]);
        $this->assertStringContainsString(
            'Roșie crudă',
            $food->fresh()->search_text
        );
    }

    public function test_dry_run_does_not_translate_or_write(): void
    {
        $food = Food::create([
            'name' => 'Milk, whole',
            'food_type' => 'generic',
            'calories' => 61,
            'is_public' => true,
        ]);
        $food->translations()->create([
            'locale' => 'en',
            'name' => 'Milk, whole',
        ]);
        $translator = Mockery::mock(FoodNameTranslator::class);
        $translator->shouldNotReceive('translate');
        $this->app->instance(FoodNameTranslator::class, $translator);

        $status = Artisan::call('foods:translate-generics', [
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $this->assertDatabaseMissing('food_translations', [
            'food_id' => $food->id,
            'locale' => 'ro',
        ]);
    }

    public function test_it_does_not_translate_hidden_duplicate_foods(): void
    {
        $canonical = Food::query()->forceCreate([
            'name' => 'Cucumber, raw',
            'food_type' => 'generic',
            'calories' => 16,
            'is_public' => true,
            'is_active' => true,
        ]);
        $duplicate = Food::query()->forceCreate([
            'name' => 'Cucumber, raw',
            'food_type' => 'generic',
            'calories' => 16,
            'is_public' => true,
            'is_active' => true,
            'canonical_food_id' => $canonical->id,
        ]);
        $canonical->translations()->create([
            'locale' => 'en',
            'name' => 'Cucumber, raw',
        ]);
        $duplicate->translations()->create([
            'locale' => 'en',
            'name' => 'Cucumber, raw',
        ]);
        $translator = Mockery::mock(FoodNameTranslator::class);
        $translator->shouldReceive('assertConfigured')->once();
        $translator
            ->shouldReceive('translate')
            ->once()
            ->with(['Cucumber, raw'], 'en', 'ro')
            ->andReturn(['Castravete, crud']);
        $translator->shouldReceive('source')->once()->andReturn('deepl');
        $this->app->instance(FoodNameTranslator::class, $translator);

        $status = Artisan::call('foods:translate-generics');

        $this->assertSame(0, $status, Artisan::output());
        $this->assertDatabaseHas('food_translations', [
            'food_id' => $canonical->id,
            'locale' => 'ro',
            'name' => 'Castravete, crud',
        ]);
        $this->assertDatabaseMissing('food_translations', [
            'food_id' => $duplicate->id,
            'locale' => 'ro',
        ]);
    }

    public function test_it_splits_large_batches_at_the_request_byte_limit(): void
    {
        config([
            'food-translations.deepl.key' => 'test-key',
            'food-translations.deepl.url' => 'https://deepl.test/v2/translate',
            'food-translations.deepl.max_request_bytes' => 600,
        ]);
        Http::fake(fn (Request $request) => Http::response([
            'translations' => collect($request['text'])
                ->map(fn (string $name) => ['text' => 'RO '.$name])
                ->all(),
        ]));
        $translator = app(FoodNameTranslator::class);
        $names = [
            str_repeat('A', 255),
            str_repeat('B', 255),
            str_repeat('C', 255),
        ];

        $translations = $translator->translate($names, 'en', 'ro');

        $this->assertSame([
            'RO '.$names[0],
            'RO '.$names[1],
            'RO '.$names[2],
        ], $translations);
        Http::assertSentCount(3);
    }
}

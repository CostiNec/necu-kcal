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
}

<?php

namespace App\Providers;

use App\Services\FoodTranslations\DeepLFoodNameTranslator;
use App\Services\FoodTranslations\FoodNameTranslator;
use App\Services\NutritionEstimation\GeminiNutritionEstimator;
use App\Services\NutritionEstimation\NutritionEstimator;
use App\Services\NutritionEstimation\OpenAiNutritionEstimator;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            FoodNameTranslator::class,
            fn ($app) => match (config('food-translations.provider')) {
                'deepl' => $app->make(DeepLFoodNameTranslator::class),
                default => throw new RuntimeException(
                    'Unsupported food translation provider.'
                ),
            }
        );

        $this->app->bind(
            NutritionEstimator::class,
            fn ($app) => match (config('nutrition-ai.provider')) {
                'gemini' => $app->make(GeminiNutritionEstimator::class),
                'openai' => $app->make(OpenAiNutritionEstimator::class),
                default => throw new RuntimeException(
                    'Unsupported nutrition AI provider.'
                ),
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

namespace App\Providers;

use App\Services\FoodTranslations\DeepLFoodNameTranslator;
use App\Services\FoodTranslations\FoodNameTranslator;
use Illuminate\Support\ServiceProvider;

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
                default => throw new \RuntimeException(
                    'Unsupported food translation provider.'
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

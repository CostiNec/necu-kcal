<?php

namespace App\Services\NutritionEstimation;

use Illuminate\Http\UploadedFile;

interface NutritionEstimator
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
    ): array;

    /**
     * @param  array<int, UploadedFile>  $images
     * @return array{
     *     entries: array<int, array{
     *         meal: string,
     *         name: string,
     *         weight_grams: float,
     *         calories_per_100g: float,
     *         protein_per_100g: float,
     *         carbohydrates_per_100g: float,
     *         fat_per_100g: float,
     *         fibre_per_100g: float,
     *         confidence: string,
     *         assumptions: string
     *     }>
     * }
     */
    public function estimateDay(
        string $description,
        string $locale,
        array $images = []
    ): array;
}

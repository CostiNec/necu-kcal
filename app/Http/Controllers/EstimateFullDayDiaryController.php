<?php

namespace App\Http\Controllers;

use App\Services\NutritionEstimation\NutritionEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class EstimateFullDayDiaryController extends Controller
{
    public function __invoke(
        Request $request,
        NutritionEstimator $estimator
    ): JsonResponse {
        set_time_limit((int) config('nutrition-ai.full_day_timeout', 120));

        $validator = Validator::make($request->all(), [
            'description' => ['nullable', 'string', 'min:3', 'max:2000'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:1024',
            ],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $images = $request->file('images', []);
            $images = is_array($images)
                ? $images
                : ($images ? [$images] : []);

            if (
                trim((string) $request->input('description')) === ''
                && $images === []
            ) {
                $validator->errors()->add(
                    'description',
                    __('app.ai_input_required')
                );
            }

            $totalBytes = array_sum(array_map(
                fn ($image): int => (int) ($image->getSize() ?: 0),
                $images
            ));

            if ($totalBytes > 7 * 1024 * 1024) {
                $validator->errors()->add(
                    'images',
                    __('app.ai_day_images_too_large')
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $validated = $validator->validated();
        $description = trim((string) ($validated['description'] ?? ''));

        try {
            $estimate = $estimator->estimateDay(
                $description,
                app()->currentLocale(),
                $request->file('images', [])
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => __('app.ai_estimate_unavailable'),
            ], 503);
        }

        return response()->json(['estimate' => $estimate]);
    }
}

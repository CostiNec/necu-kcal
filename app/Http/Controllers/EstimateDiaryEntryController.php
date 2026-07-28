<?php

namespace App\Http\Controllers;

use App\Services\NutritionEstimation\NutritionEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class EstimateDiaryEntryController extends Controller
{
    public function __invoke(
        Request $request,
        NutritionEstimator $estimator
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'description' => ['nullable', 'string', 'min:3', 'max:1000'],
            'images' => ['nullable', 'array', 'max:2'],
            'images.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
            ],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $images = $request->file('images', []);
            $imageCount = is_array($images)
                ? count($images)
                : ($images ? 1 : 0);

            if (
                trim((string) $request->input('description')) === ''
                && $imageCount === 0
            ) {
                $validator->errors()->add(
                    'description',
                    __('app.ai_input_required')
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
            $estimate = $estimator->estimate(
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

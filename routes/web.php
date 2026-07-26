<?php

use App\Http\Controllers\DiaryController;
use App\Http\Controllers\DiaryEntryController;
use App\Http\Controllers\FavouriteFoodController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome'))->name('home');
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('auth')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])
        ->name('onboarding.show');
    Route::put('/onboarding', [OnboardingController::class, 'update'])
        ->name('onboarding.update');

    Route::middleware('onboarded')->group(function () {
        Route::get('/today', [DiaryController::class, 'today'])->name('diary.today');
        Route::get('/diary/{date}', [DiaryController::class, 'show'])
            ->where('date', '\d{4}-\d{2}-\d{2}')
            ->name('diary.show');
        Route::put('/diary/{date}/notes', [DiaryController::class, 'updateNotes'])
            ->where('date', '\d{4}-\d{2}-\d{2}')
            ->name('diary.notes.update');

        Route::get('/foods', [FoodController::class, 'index'])->name('foods.index');
        Route::post('/foods', [FoodController::class, 'store'])->name('foods.store');
        Route::delete('/foods/{food}', [FoodController::class, 'destroy'])
            ->name('foods.destroy');
        Route::post('/foods/{food}/favourite', [FavouriteFoodController::class, 'toggle'])
            ->name('foods.favourite');

        Route::post('/diary-entries', [DiaryEntryController::class, 'store'])
            ->name('diary-entries.store');
        Route::put('/diary-entries/{diaryEntry}', [DiaryEntryController::class, 'update'])
            ->name('diary-entries.update');
        Route::delete('/diary-entries/{diaryEntry}', [DiaryEntryController::class, 'destroy'])
            ->name('diary-entries.destroy');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

        Route::get('/settings', [SettingsController::class, 'index'])
            ->name('settings.index');
        Route::put('/settings/targets', [SettingsController::class, 'updateTargets'])
            ->name('settings.targets.update');
        Route::delete('/settings/account', [SettingsController::class, 'destroyAccount'])
            ->name('settings.account.destroy');
    });
});

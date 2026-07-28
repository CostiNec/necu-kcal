<?php

use App\Http\Controllers\DiaryController;
use App\Http\Controllers\DiaryEntryController;
use App\Http\Controllers\FavouriteFoodController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\RecipeCommentController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeightLogController;
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
        Route::get('/foods/search', [FoodController::class, 'search'])
            ->name('foods.search');
        Route::post('/foods', [FoodController::class, 'store'])->name('foods.store');
        Route::delete('/foods/{food}', [FoodController::class, 'destroy'])
            ->name('foods.destroy');
        Route::post('/foods/{food}/favourite', [FavouriteFoodController::class, 'toggle'])
            ->name('foods.favourite');
        Route::delete('/foods/{food}/favourite', [FavouriteFoodController::class, 'destroy'])
            ->name('foods.favourite.destroy');

        Route::get('/recipes', [RecipeController::class, 'index'])
            ->name('recipes.index');
        Route::get('/recipes/create', [RecipeController::class, 'create'])
            ->name('recipes.create');
        Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])
            ->name('recipes.show');
        Route::get('/recipes/{recipe}/edit', [RecipeController::class, 'edit'])
            ->name('recipes.edit');
        Route::post(
            '/recipes/{recipe}/comments',
            [RecipeCommentController::class, 'store']
        )->name('recipes.comments.store');
        Route::put(
            '/recipes/{recipe}/comments/{comment}',
            [RecipeCommentController::class, 'update']
        )->name('recipes.comments.update');
        Route::post('/recipes', [RecipeController::class, 'store'])
            ->name('recipes.store');
        Route::put('/recipes/{recipe}', [RecipeController::class, 'update'])
            ->name('recipes.update');
        Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy'])
            ->name('recipes.destroy');

        Route::post('/diary-entries', [DiaryEntryController::class, 'store'])
            ->name('diary-entries.store');
        Route::post(
            '/diary-entries/quick',
            [DiaryEntryController::class, 'storeQuick']
        )->name('diary-entries.quick.store');
        Route::put('/diary-entries/{diaryEntry}', [DiaryEntryController::class, 'update'])
            ->name('diary-entries.update');
        Route::delete('/diary-entries/{diaryEntry}', [DiaryEntryController::class, 'destroy'])
            ->name('diary-entries.destroy');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user:username}', [UserController::class, 'show'])
            ->name('users.show');
        Route::post('/users/{user:username}/friend-request', [FriendshipController::class, 'store'])
            ->name('friendships.store');
        Route::put('/friendships/{friendship}/accept', [FriendshipController::class, 'accept'])
            ->name('friendships.accept');
        Route::delete('/friendships/{friendship}', [FriendshipController::class, 'destroy'])
            ->name('friendships.destroy');

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::put('/notifications/{notification}/read', [NotificationController::class, 'read'])
            ->name('notifications.read');

        Route::get('/weight', [WeightLogController::class, 'index'])
            ->name('weight.index');
        Route::post('/weight', [WeightLogController::class, 'store'])
            ->name('weight.store');
        Route::put('/weight/{weightLog}', [WeightLogController::class, 'update'])
            ->name('weight.update');
        Route::delete('/weight/{weightLog}', [WeightLogController::class, 'destroy'])
            ->name('weight.destroy');

        Route::get('/settings', [SettingsController::class, 'index'])
            ->name('settings.index');
        Route::put('/settings/targets', [SettingsController::class, 'updateTargets'])
            ->name('settings.targets.update');
        Route::delete('/settings/account', [SettingsController::class, 'destroyAccount'])
            ->name('settings.account.destroy');
    });
});

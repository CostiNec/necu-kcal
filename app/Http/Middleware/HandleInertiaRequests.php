<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only([
                    'id',
                    'name',
                    'username',
                    'email',
                    'email_verified_at',
                ]),
            ],
            'notificationSummary' => [
                'unread_count' => fn () => $request->user()
                    ? $request->user()->unreadNotifications()->count()
                    : 0,
            ],
            'locale' => App::currentLocale(),
            'availableLocales' => config('locales.supported'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'createdFoodId' => fn () => $request->session()->get('created_food_id'),
            ],
        ];
    }
}

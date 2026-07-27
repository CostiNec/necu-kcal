<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $actionableRequestIds = Friendship::query()
            ->where('status', Friendship::STATUS_PENDING)
            ->where('requested_by', '!=', $request->user()->id)
            ->where(function ($query) use ($request) {
                $query
                    ->where('user_id', $request->user()->id)
                    ->orWhere('friend_id', $request->user()->id);
            })
            ->pluck('id');
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'event' => $notification->data['event'] ?? 'unknown',
                'actor_name' => $notification->data['actor_name'] ?? '',
                'actor_username' => $notification->data['actor_username'] ?? '',
                'friendship_id' => $notification->data['friendship_id'] ?? null,
                'actionable' => $actionableRequestIds->contains(
                    $notification->data['friendship_id'] ?? null
                ),
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at?->toISOString(),
            ]);

        $request->user()->unreadNotifications->markAsRead();

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
        ]);
    }

    public function read(
        Request $request,
        DatabaseNotification $notification
    ): RedirectResponse {
        abort_unless(
            $notification->notifiable_type === $request->user()::class
                && (int) $notification->notifiable_id === $request->user()->id,
            404
        );

        $notification->markAsRead();

        return back();
    }
}

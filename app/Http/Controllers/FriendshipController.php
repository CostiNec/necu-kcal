<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FriendshipController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        abort_if($currentUser->is($user), 422);

        $friendship = DB::transaction(function () use (
            $currentUser,
            $user
        ): Friendship {
            $existing = Friendship::query()
                ->between($currentUser, $user)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'friendship' => __('app.friend_request_exists'),
                ]);
            }

            [$userId, $friendId] = Friendship::orderedIds($currentUser, $user);

            return Friendship::create([
                'user_id' => $userId,
                'friend_id' => $friendId,
                'requested_by' => $currentUser->id,
                'status' => Friendship::STATUS_PENDING,
            ]);
        });

        $user->notify(new FriendRequestReceived($currentUser, $friendship));

        return back()->with('success', __('app.friend_request_sent', [
            'username' => $user->username,
        ]));
    }

    public function accept(
        Request $request,
        Friendship $friendship
    ): RedirectResponse {
        $currentUser = $request->user();

        abort_unless(
            $friendship->status === Friendship::STATUS_PENDING
                && $friendship->requested_by !== $currentUser->id
                && in_array(
                    $currentUser->id,
                    [$friendship->user_id, $friendship->friend_id],
                    true
                ),
            403
        );

        $friendship->update([
            'status' => Friendship::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
        $requester = $friendship->requester;
        $requester->notify(new FriendRequestAccepted($currentUser));

        return back()->with('success', __('app.friend_request_accepted'));
    }

    public function destroy(
        Request $request,
        Friendship $friendship
    ): RedirectResponse {
        abort_unless(
            in_array(
                $request->user()->id,
                [$friendship->user_id, $friendship->friend_id],
                true
            ),
            403
        );

        $wasAccepted = $friendship->status === Friendship::STATUS_ACCEPTED;
        $friendship->delete();

        return back()->with(
            'success',
            __($wasAccepted ? 'app.friend_removed' : 'app.friend_request_removed')
        );
    }
}

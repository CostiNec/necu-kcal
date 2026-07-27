<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class FriendRequestAccepted extends Notification
{
    public function __construct(private User $friend) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'friend_request_accepted',
            'actor_id' => $this->friend->id,
            'actor_name' => $this->friend->name,
            'actor_username' => $this->friend->username,
        ];
    }

    public function broadcastType(): string
    {
        return 'friend_request_accepted';
    }
}

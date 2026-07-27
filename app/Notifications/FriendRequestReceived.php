<?php

namespace App\Notifications;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Notifications\Notification;

class FriendRequestReceived extends Notification
{
    public function __construct(
        private User $requester,
        private Friendship $friendship
    ) {}

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
            'event' => 'friend_request_received',
            'actor_id' => $this->requester->id,
            'actor_name' => $this->requester->name,
            'actor_username' => $this->requester->username,
            'friendship_id' => $this->friendship->id,
        ];
    }

    public function broadcastType(): string
    {
        return 'friend_request_received';
    }
}

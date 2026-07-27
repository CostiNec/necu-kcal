<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    protected $fillable = [
        'user_id',
        'friend_id',
        'requested_by',
        'status',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopeBetween(
        Builder $query,
        User|int $first,
        User|int $second
    ): Builder {
        [$userId, $friendId] = self::orderedIds($first, $second);

        return $query->where([
            'user_id' => $userId,
            'friend_id' => $friendId,
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function orderedIds(
        User|int $first,
        User|int $second
    ): array {
        $firstId = $first instanceof User ? $first->id : $first;
        $secondId = $second instanceof User ? $second->id : $second;

        return $firstId < $secondId
            ? [$firstId, $secondId]
            : [$secondId, $firstId];
    }

    public function otherUser(User|int $user): User
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->user_id === $userId ? $this->friend : $this->user;
    }
}

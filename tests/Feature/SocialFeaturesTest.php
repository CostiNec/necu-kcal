<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Friendship;
use App\Models\Recipe;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SocialFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_be_found_by_username(): void
    {
        $viewer = $this->onboardedUser(['username' => 'viewer']);
        $match = $this->onboardedUser([
            'name' => 'Alex Friend',
            'username' => 'alex_friend',
        ]);
        $this->onboardedUser(['username' => 'someone_else']);

        $this->actingAs($viewer)
            ->get('/users?search=Alex_Friend')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('users/index')
                ->where('searchResult.id', $match->id)
                ->where('searchResult.username', 'alex_friend')
                ->where('searchResult.friendship_state', 'none')
            );

        $this->actingAs($viewer)
            ->get('/users?search=alex')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('searchResult', null)
            );
    }

    public function test_users_page_lists_only_friends_until_searching(): void
    {
        $viewer = $this->onboardedUser(['username' => 'viewer']);
        $friend = $this->onboardedUser(['username' => 'friend']);
        $stranger = $this->onboardedUser(['username' => 'stranger']);
        $this->acceptedFriendship($viewer, $friend);

        $this->actingAs($viewer)
            ->get('/users')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('friends', 1)
                ->where('friends.0.id', $friend->id)
                ->where('friends.0.friendship_state', 'friends')
                ->has('requests', 0)
                ->where('searchResult', null)
            );

        $this->actingAs($viewer)
            ->get('/users?search=stranger')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('friends', 1)
                ->where('searchResult.id', $stranger->id)
                ->where('searchResult.friendship_state', 'none')
            );
    }

    public function test_friend_request_creates_notification_and_can_be_accepted(): void
    {
        $sender = $this->onboardedUser(['username' => 'sender']);
        $recipient = $this->onboardedUser(['username' => 'recipient']);

        $this->actingAs($sender)
            ->post("/users/{$recipient->username}/friend-request")
            ->assertRedirect();

        $friendship = Friendship::firstOrFail();
        $receivedNotification = new FriendRequestReceived(
            $sender,
            $friendship
        );
        $this->assertContains(
            'broadcast',
            $receivedNotification->via($recipient)
        );
        $this->assertSame(
            'friend_request_received',
            $receivedNotification->broadcastType()
        );
        $this->assertSame(Friendship::STATUS_PENDING, $friendship->status);
        $this->assertSame($sender->id, $friendship->requested_by);
        $this->assertSame(1, $recipient->notifications()->count());
        $this->assertSame(
            'friend_request_received',
            $recipient->notifications()->first()->data['event']
        );

        $this->actingAs($sender)
            ->get('/users')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('requests', 1)
                ->where('requests.0.id', $recipient->id)
                ->where('requests.0.friendship_state', 'outgoing')
            );
        $this->actingAs($recipient)
            ->get('/users?tab=requests')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.tab', 'requests')
                ->has('requests', 1)
                ->where('requests.0.id', $sender->id)
                ->where('requests.0.friendship_state', 'incoming')
            );

        $this->actingAs($recipient)
            ->put("/friendships/{$friendship->id}/accept")
            ->assertRedirect();

        $this->assertSame(
            Friendship::STATUS_ACCEPTED,
            $friendship->refresh()->status
        );
        $this->assertNotNull($friendship->accepted_at);
        $acceptedNotification = new FriendRequestAccepted($recipient);
        $this->assertContains(
            'broadcast',
            $acceptedNotification->via($sender)
        );
        $this->assertSame(
            'friend_request_accepted',
            $acceptedNotification->broadcastType()
        );
        $this->assertSame(1, $sender->notifications()->count());
        $this->assertSame(
            'friend_request_accepted',
            $sender->notifications()->first()->data['event']
        );
    }

    public function test_notifications_are_private_and_marked_read_when_viewed(): void
    {
        $sender = $this->onboardedUser([
            'name' => 'Sender Name',
            'username' => 'sender',
        ]);
        $recipient = $this->onboardedUser(['username' => 'recipient']);

        $this->actingAs($sender)
            ->post("/users/{$recipient->username}/friend-request");

        $this->actingAs($sender)
            ->get('/notifications')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('notifications', 0)
            );

        $this->actingAs($recipient)
            ->get('/notifications')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('notifications', 1)
                ->where('notifications.0.event', 'friend_request_received')
                ->where('notifications.0.actor_username', 'sender')
                ->where('notifications.0.actionable', true)
            );

        $this->assertSame(0, $recipient->unreadNotifications()->count());
    }

    public function test_only_friends_can_see_use_and_favourite_a_users_recipe(): void
    {
        $owner = $this->onboardedUser(['username' => 'chef']);
        $friend = $this->onboardedUser(['username' => 'friend']);
        $stranger = $this->onboardedUser(['username' => 'stranger']);
        [$recipe, $recipeFood] = $this->recipeFor($owner);

        $this->actingAs($stranger)
            ->get("/users/{$owner->username}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canViewRecipes', false)
                ->has('recipes', 0)
            );

        $this->actingAs($stranger)
            ->post("/foods/{$recipeFood->id}/favourite")
            ->assertForbidden();
        $this->actingAs($stranger)
            ->post('/diary-entries', $this->diaryPayload($recipeFood))
            ->assertNotFound();

        $friendship = $this->acceptedFriendship($owner, $friend);

        $this->actingAs($friend)
            ->get("/users/{$owner->username}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canViewRecipes', true)
                ->has('recipes', 1)
                ->where('recipes.0.id', $recipe->id)
                ->where('recipes.0.name', 'Friend soup')
            );

        $this->actingAs($friend)
            ->post("/foods/{$recipeFood->id}/favourite")
            ->assertRedirect();
        $this->assertDatabaseHas('food_favourites', [
            'user_id' => $friend->id,
            'food_id' => $recipeFood->id,
        ]);

        $this->actingAs($friend)
            ->post('/diary-entries', $this->diaryPayload($recipeFood))
            ->assertRedirect('/diary/2026-07-27?focus_meal=dinner');
        $this->assertDatabaseHas('diary_entries', [
            'food_id' => $recipeFood->id,
            'food_name' => 'Friend soup',
            'calories' => 150,
        ]);

        $friendship->delete();

        $this->actingAs($friend)
            ->post('/diary-entries', $this->diaryPayload($recipeFood))
            ->assertNotFound();
    }

    public function test_only_the_recipient_can_accept_a_friend_request(): void
    {
        $sender = $this->onboardedUser(['username' => 'sender']);
        $recipient = $this->onboardedUser(['username' => 'recipient']);
        $other = $this->onboardedUser(['username' => 'other']);
        [$userId, $friendId] = Friendship::orderedIds($sender, $recipient);
        $friendship = Friendship::create([
            'user_id' => $userId,
            'friend_id' => $friendId,
            'requested_by' => $sender->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        $this->actingAs($sender)
            ->put("/friendships/{$friendship->id}/accept")
            ->assertForbidden();
        $this->actingAs($other)
            ->put("/friendships/{$friendship->id}/accept")
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function onboardedUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->profile()->create([
            'timezone' => 'Europe/Bucharest',
            'unit_system' => 'metric',
            'onboarding_completed_at' => now(),
        ]);
        $user->nutritionTarget()->create([
            'calories' => 2000,
            'protein' => 120,
            'carbohydrates' => 220,
            'fat' => 65,
            'fibre' => 30,
        ]);

        return $user;
    }

    /**
     * @return array{0: Recipe, 1: Food}
     */
    private function recipeFor(User $owner): array
    {
        $food = $owner->foods()->create([
            'name' => 'Friend soup',
            'food_type' => 'recipe',
            'calories' => 150,
            'protein' => 8,
            'carbohydrates' => 18,
            'fat' => 5,
            'fibre' => 3,
            'nutrition_basis_amount' => 100,
            'nutrition_basis_unit' => 'g',
            'is_public' => false,
        ]);
        $recipe = $owner->recipes()->create([
            'food_id' => $food->id,
            'name' => 'Friend soup',
            'cooked_weight' => 500,
            'total_calories' => 750,
            'total_protein' => 40,
            'total_carbohydrates' => 90,
            'total_fat' => 25,
            'total_fibre' => 15,
        ]);

        return [$recipe, $food];
    }

    private function acceptedFriendship(User $first, User $second): Friendship
    {
        [$userId, $friendId] = Friendship::orderedIds($first, $second);

        return Friendship::create([
            'user_id' => $userId,
            'friend_id' => $friendId,
            'requested_by' => $first->id,
            'status' => Friendship::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function diaryPayload(Food $food): array
    {
        return [
            'food_id' => $food->id,
            'date' => '2026-07-27',
            'meal' => 'dinner',
            'unit' => 'g',
            'amount' => 100,
            'quantity' => 1,
        ];
    }
}

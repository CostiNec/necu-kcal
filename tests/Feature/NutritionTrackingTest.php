<?php

namespace Tests\Feature;

use App\Models\DiaryDay;
use App\Models\Food;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NutritionTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_default_profile_and_targets(): void
    {
        $response = $this->post('/register', [
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
        ]);

        $user = User::where('email', 'alex@example.com')->firstOrFail();

        $response->assertRedirect('/today');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Europe/Bucharest', $user->profile->timezone);
        $this->assertNull($user->profile->onboarding_completed_at);
        $this->assertSame(2000, $user->nutritionTarget->calories);
    }

    public function test_incomplete_users_are_sent_to_onboarding(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'timezone' => 'Europe/Bucharest',
            'unit_system' => 'metric',
        ]);
        $user->nutritionTarget()->create([
            'calories' => 2000,
            'protein' => 120,
            'carbohydrates' => 220,
            'fat' => 65,
        ]);

        $this->actingAs($user)
            ->get('/foods')
            ->assertRedirect('/onboarding');
    }

    public function test_a_diary_entry_uses_a_nutrition_snapshot(): void
    {
        $user = $this->onboardedUser();
        $food = Food::create([
            'user_id' => $user->id,
            'name' => 'Protein bowl',
            'calories' => 200,
            'protein' => 10,
            'carbohydrates' => 20,
            'fat' => 5,
            'unit_type' => 'g',
            'is_public' => false,
        ]);

        $this->actingAs($user)->post('/diary-entries', [
            'food_id' => $food->id,
            'date' => '2026-07-26',
            'meal' => 'lunch',
            'serving_name' => 'Half bowl',
            'serving_amount' => 50,
            'quantity' => 2,
        ])->assertRedirect('/diary/2026-07-26');

        $entry = DiaryDay::firstOrFail()->entries()->firstOrFail();
        $this->assertSame(200.0, $entry->calories);
        $this->assertSame(10.0, $entry->protein);

        $food->update(['calories' => 999, 'protein' => 99]);
        $entry->refresh();

        $this->assertSame(200.0, $entry->calories);
        $this->assertSame(10.0, $entry->protein);
    }

    public function test_users_cannot_change_another_users_diary(): void
    {
        $owner = $this->onboardedUser();
        $intruder = $this->onboardedUser();
        $day = $owner->diaryDays()->create(['date' => '2026-07-26']);
        $entry = $day->entries()->create([
            'meal' => 'snacks',
            'food_name' => 'Apple',
            'unit_type' => 'g',
            'serving_name' => '1 apple',
            'quantity' => 1,
            'amount' => 180,
            'calories' => 95,
            'protein' => 0.5,
            'carbohydrates' => 25,
            'fat' => 0.3,
        ]);

        $this->actingAs($intruder)
            ->delete("/diary-entries/{$entry->id}")
            ->assertForbidden();
    }

    public function test_weekly_report_contains_aggregated_values(): void
    {
        $user = $this->onboardedUser();
        $day = $user->diaryDays()->create(['date' => '2026-07-22']);
        $day->entries()->create([
            'meal' => 'breakfast',
            'food_name' => 'Oats',
            'unit_type' => 'g',
            'serving_name' => '1 bowl',
            'quantity' => 1,
            'amount' => 50,
            'calories' => 190,
            'protein' => 6.5,
            'carbohydrates' => 34,
            'fat' => 3.5,
        ]);

        $this->actingAs($user)
            ->get('/reports?week=2026-07-22')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/index')
                ->where('loggedDays', 1)
                ->where('averages.calories', 190)
            );
    }

    public function test_user_can_delete_their_account_and_private_nutrition_data(): void
    {
        $user = $this->onboardedUser();
        $food = Food::create([
            'user_id' => $user->id,
            'name' => 'Private recipe',
            'calories' => 450,
            'protein' => 25,
            'carbohydrates' => 40,
            'fat' => 20,
            'unit_type' => 'g',
            'is_public' => false,
        ]);
        $day = $user->diaryDays()->create(['date' => '2026-07-26']);
        $day->entries()->create([
            'food_id' => $food->id,
            'meal' => 'dinner',
            'food_name' => $food->name,
            'unit_type' => 'g',
            'serving_name' => '1 serving',
            'quantity' => 1,
            'amount' => 100,
            'calories' => 450,
            'protein' => 25,
            'carbohydrates' => 40,
            'fat' => 20,
        ]);

        $this->actingAs($user)
            ->delete('/settings/account', ['current_password' => 'password'])
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('foods', ['id' => $food->id]);
        $this->assertDatabaseMissing('diary_days', ['id' => $day->id]);
    }

    private function onboardedUser(): User
    {
        $user = User::factory()->create();
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
        ]);

        return $user;
    }
}

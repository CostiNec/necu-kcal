<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WeightTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_weight_and_replace_the_same_day(): void
    {
        $user = $this->onboardedUser();
        $today = CarbonImmutable::now('Europe/Bucharest')->toDateString();

        $this->actingAs($user)
            ->from('/weight')
            ->post('/weight', [
                'date' => $today,
                'weight' => '81.45',
                'note' => 'Morning',
            ])
            ->assertRedirect('/weight');

        $this->actingAs($user)
            ->from('/weight')
            ->post('/weight', [
                'date' => $today,
                'weight' => '80.95',
                'note' => 'After training',
            ])
            ->assertRedirect('/weight');

        $this->assertDatabaseCount('weight_logs', 1);
        $this->assertDatabaseHas('weight_logs', [
            'user_id' => $user->id,
            'date' => $today,
            'weight_kg' => 80.95,
            'note' => 'After training',
        ]);
    }

    public function test_weight_page_and_report_include_weight_trends(): void
    {
        $user = $this->onboardedUser();
        $user->weightLogs()->create([
            'date' => '2026-05-10',
            'weight_kg' => 80,
        ]);
        $latest = $user->weightLogs()->create([
            'date' => '2026-07-22',
            'weight_kg' => 78.5,
            'note' => 'Morning',
        ]);

        $this->actingAs($user)
            ->get('/weight')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('weight/index')
                ->where('filters.range', 'month')
                ->where('summary.current', 78.5)
                ->has('entries', 2)
                ->where('entries.0.id', $latest->id)
                ->where('pagination.next_cursor', null)
                ->where('pagination.previous_cursor', null)
            );

        $this->actingAs($user)
            ->get('/reports?range=custom&start=2026-05-10&end=2026-07-22')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/index')
                ->where('weightSummary.current', 78.5)
                ->where('weightSummary.change', -1.5)
                ->where('weightSummary.loggedDays', 2)
                ->has('weightChart', 2)
            );
    }

    public function test_weight_history_uses_cursor_pagination(): void
    {
        $user = $this->onboardedUser();
        $today = CarbonImmutable::now('Europe/Bucharest');

        foreach (range(0, 24) as $daysAgo) {
            $user->weightLogs()->create([
                'date' => $today->subDays($daysAgo)->toDateString(),
                'weight_kg' => 80 + ($daysAgo / 10),
            ]);
        }

        $firstPage = $this->actingAs($user)->get('/weight');

        $firstPage
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('entries', 20)
                ->where('pagination.previous_cursor', null)
                ->where(
                    'pagination.next_cursor',
                    fn ($cursor) => is_string($cursor) && $cursor !== ''
                )
            );

        $nextCursor = $firstPage->viewData('page')['props']['pagination']['next_cursor'];

        $secondPage = $this->actingAs($user)
            ->get('/weight?cursor='.urlencode($nextCursor));

        $secondPage
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('entries', 5)
                ->where('pagination.next_cursor', null)
                ->where('pagination.previous_cursor', fn ($cursor) => is_string($cursor))
            );
    }

    public function test_weight_trend_can_be_filtered_by_month_year_or_all_time(): void
    {
        $user = $this->onboardedUser();
        $today = CarbonImmutable::now('Europe/Bucharest');

        foreach ([
            $today->subDays(10)->toDateString(),
            $today->subMonths(6)->toDateString(),
            $today->subMonths(18)->toDateString(),
        ] as $index => $date) {
            $user->weightLogs()->create([
                'date' => $date,
                'weight_kg' => 80 + $index,
            ]);
        }

        $this->actingAs($user)
            ->get('/weight')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.range', 'month')
                ->has('trend', 1)
            );

        $this->actingAs($user)
            ->get('/weight?range=year')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.range', 'year')
                ->has('trend', 2)
            );

        $this->actingAs($user)
            ->get('/weight?range=all')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.range', 'all')
                ->has('trend', 3)
            );
    }

    public function test_user_can_update_and_delete_their_weight_entry(): void
    {
        $user = $this->onboardedUser();
        $entry = $user->weightLogs()->create([
            'date' => '2026-07-20',
            'weight_kg' => 82,
        ]);

        $this->actingAs($user)
            ->put("/weight/{$entry->id}", [
                'date' => '2026-07-21',
                'weight' => 81.6,
                'note' => 'Corrected',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('weight_logs', [
            'id' => $entry->id,
            'date' => '2026-07-21',
            'weight_kg' => 81.6,
            'note' => 'Corrected',
        ]);

        $this->actingAs($user)
            ->delete("/weight/{$entry->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('weight_logs', ['id' => $entry->id]);
    }

    public function test_users_cannot_change_another_users_weight_entries(): void
    {
        $owner = $this->onboardedUser();
        $intruder = $this->onboardedUser();
        $entry = $owner->weightLogs()->create([
            'date' => '2026-07-20',
            'weight_kg' => 82,
        ]);

        $this->actingAs($intruder)
            ->put("/weight/{$entry->id}", [
                'date' => '2026-07-20',
                'weight' => 70,
            ])
            ->assertNotFound();

        $this->actingAs($intruder)
            ->delete("/weight/{$entry->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('weight_logs', [
            'id' => $entry->id,
            'weight_kg' => 82,
        ]);
    }

    public function test_weight_validation_rejects_invalid_values_and_future_dates(): void
    {
        $user = $this->onboardedUser();

        $this->actingAs($user)
            ->post('/weight', [
                'date' => '2999-01-01',
                'weight' => 10,
                'note' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors(['date', 'weight', 'note']);

        $this->assertDatabaseCount('weight_logs', 0);
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
            'fibre' => 30,
        ]);

        return $user;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\User;
use Database\Seeders\FoodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_to_romanian_and_locale_is_shared_with_inertia(): void
    {
        $this->from('/login')
            ->post('/locale', ['locale' => 'ro'])
            ->assertRedirect('/login')
            ->assertSessionHas('locale', 'ro');

        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="ro"', false)
            ->assertInertia(fn ($page) => $page
                ->component('auth/login')
                ->where('locale', 'ro')
                ->has('availableLocales.en')
                ->has('availableLocales.ro')
            );
    }

    public function test_romanian_locale_translates_validation_messages(): void
    {
        $this->withSession(['locale' => 'ro'])
            ->post('/register', [
                'name' => '',
                'username' => '',
                'email' => '',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionHasErrors([
                'name' => 'Câmpul nume este obligatoriu.',
                'email' => 'Câmpul e-mail este obligatoriu.',
                'password' => 'Câmpul parolă este obligatoriu.',
            ]);
    }

    public function test_common_foods_can_be_searched_and_returned_in_romanian(): void
    {
        $this->seed(FoodSeeder::class);
        $user = $this->onboardedUser();

        $this->actingAs($user)
            ->withSession(['locale' => 'ro'])
            ->get('/foods?search=pui')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('foods/index')
                ->has('foods', 1)
                ->where('foods.0.name', 'Piept de pui, gătit')
            );
    }

    public function test_translated_food_search_uses_word_prefixes_not_substrings(): void
    {
        $this->seed(FoodSeeder::class);
        $user = $this->onboardedUser();

        $this->actingAs($user)
            ->withSession(['locale' => 'ro'])
            ->getJson('/foods/search?search=t')
            ->assertOk()
            ->assertJsonMissing([
                'name' => 'Piept de pui, gătit',
            ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'ro'])
            ->getJson('/foods/search?search=pie')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Piept de pui, gătit',
            ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'ro'])
            ->getJson('/foods/search?search=piept%20de')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Piept de pui, gătit',
            ]);
    }

    public function test_romanian_search_ranks_exact_then_prefix_then_other_matches(): void
    {
        $user = $this->onboardedUser();
        $foods = collect([
            'other' => ['Egg salad', 'Salată cu ouă'],
            'prefix' => ['Boiled eggs', 'Ouă fierte'],
            'exact' => ['Eggs', 'Ouă'],
        ])->map(function (array $names) {
            $food = Food::create([
                'name' => $names[0],
                'food_type' => 'generic',
                'calories' => 100,
                'is_public' => true,
            ]);
            $food->translations()->create([
                'locale' => 'ro',
                'name' => $names[1],
            ]);
            $food->forceFill([
                'search_text' => implode(' ', $names),
            ])->save();

            return $food;
        });

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'ro'])
            ->getJson('/foods/search?search='.urlencode('ouă'))
            ->assertOk()
            ->assertJsonCount(3, 'foods');

        $this->assertSame([
            $foods['exact']->id,
            $foods['prefix']->id,
            $foods['other']->id,
        ], collect($response->json('foods'))->pluck('id')->all());
    }

    public function test_search_ranking_checks_translations_outside_the_display_locale(): void
    {
        $user = $this->onboardedUser();
        $other = Food::create([
            'name' => 'Egg salad',
            'food_type' => 'generic',
            'calories' => 100,
            'is_public' => true,
        ]);
        $other->translations()->create([
            'locale' => 'ro',
            'name' => 'Salată cu ouă',
        ]);
        $other->forceFill([
            'search_text' => 'Egg salad Salată cu ouă',
        ])->save();
        $exact = Food::create([
            'name' => 'Whole eggs',
            'food_type' => 'generic',
            'calories' => 100,
            'is_public' => true,
        ]);
        $exact->translations()->create([
            'locale' => 'ro',
            'name' => 'Ouă',
        ]);
        $exact->forceFill([
            'search_text' => 'Whole eggs Ouă',
        ])->save();

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->getJson('/foods/search?search='.urlencode('ouă'))
            ->assertOk()
            ->assertJsonCount(2, 'foods');

        $this->assertSame(
            [$exact->id, $other->id],
            collect($response->json('foods'))->pluck('id')->all()
        );
        $this->assertSame('Ouă', $response->json('foods.0.name'));
        $this->assertSame('Salată cu ouă', $response->json('foods.1.name'));
    }

    public function test_food_name_is_used_when_current_locale_translation_is_missing(): void
    {
        $user = $this->onboardedUser();
        $food = Food::query()->create([
            'user_id' => null,
            'name' => 'Fallback food',
            'calories' => 100,
            'unit_type' => 'g',
            'is_public' => true,
        ]);
        $food->translations()->create([
            'locale' => 'en',
            'name' => 'English translated food',
        ]);
        $this->actingAs($user)
            ->withSession(['locale' => 'ro'])
            ->getJson('/foods/search?search=Fallback')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Fallback food',
            ]);
    }

    public function test_flash_messages_follow_the_selected_locale(): void
    {
        $user = $this->onboardedUser();

        $this->actingAs($user)
            ->withSession(['locale' => 'ro'])
            ->from('/settings')
            ->put('/settings/targets', [
                'calories' => 2200,
                'protein' => 140,
                'carbohydrates' => 230,
                'fat' => 70,
                'fibre' => 35,
                'timezone' => 'Europe/Bucharest',
            ])
            ->assertRedirect('/settings')
            ->assertSessionHas('success', 'Obiectivele au fost actualizate.');

        $this->assertDatabaseHas('nutrition_targets', [
            'user_id' => $user->id,
            'fibre' => 35,
        ]);
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

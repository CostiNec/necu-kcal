<?php

namespace Tests\Feature;

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
                ->where('foods.0.serving.name', '100 g')
            );
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
                'timezone' => 'Europe/Bucharest',
            ])
            ->assertRedirect('/settings')
            ->assertSessionHas('success', 'Obiectivele au fost actualizate.');
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

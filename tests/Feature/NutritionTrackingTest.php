<?php

namespace Tests\Feature;

use App\Models\DiaryDay;
use App\Models\Food;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertSame(30, $user->nutritionTarget->fibre);
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
            'fibre' => 4,
            'unit_type' => 'g',
            'is_public' => false,
        ]);

        $this->actingAs($user)->post('/diary-entries', [
            'food_id' => $food->id,
            'date' => '2026-07-26',
            'meal' => 'lunch',
            'unit' => 'g',
            'amount' => 50,
            'quantity' => 2,
        ])->assertRedirect('/diary/2026-07-26');

        $entry = DiaryDay::firstOrFail()->entries()->firstOrFail();
        $this->assertSame('g', $entry->unit);
        $this->assertSame(50.0, $entry->amount);
        $this->assertSame(100.0, $entry->total_grams);
        $this->assertSame(200.0, $entry->calories);
        $this->assertSame(10.0, $entry->protein);
        $this->assertSame(4.0, $entry->fibre);

        $food->update(['calories' => 999, 'protein' => 99, 'fibre' => 99]);
        $entry->refresh();

        $this->assertSame(200.0, $entry->calories);
        $this->assertSame(10.0, $entry->protein);
        $this->assertSame(4.0, $entry->fibre);
    }

    public function test_mass_units_are_normalized_to_grams_and_can_be_updated(): void
    {
        $user = $this->onboardedUser();
        $food = Food::create([
            'user_id' => $user->id,
            'name' => 'Measured food',
            'calories' => 100,
            'protein' => 10,
            'carbohydrates' => 5,
            'fat' => 2,
            'fibre' => 1,
            'is_public' => false,
        ]);

        $this->actingAs($user)->post('/diary-entries', [
            'food_id' => $food->id,
            'date' => '2026-07-26',
            'meal' => 'lunch',
            'unit' => 'kg',
            'amount' => 0.25,
            'quantity' => 2,
        ])->assertRedirect('/diary/2026-07-26');

        $entry = DiaryDay::firstOrFail()->entries()->firstOrFail();

        $this->assertSame('kg', $entry->unit);
        $this->assertSame(0.25, $entry->amount);
        $this->assertSame(2.0, $entry->quantity);
        $this->assertSame(500.0, $entry->total_grams);
        $this->assertSame(500.0, $entry->calories);

        $this->actingAs($user)->put("/diary-entries/{$entry->id}", [
            'unit' => 'oz',
            'amount' => 1,
            'quantity' => 2,
        ])->assertRedirect();

        $entry->refresh();

        $this->assertSame('oz', $entry->unit);
        $this->assertSame(1.0, $entry->amount);
        $this->assertSame(56.699, $entry->total_grams);
        $this->assertSame(56.7, $entry->calories);

        $this->actingAs($user)->post('/diary-entries', [
            'food_id' => $food->id,
            'date' => '2026-07-26',
            'meal' => 'lunch',
            'unit' => 'ml',
            'amount' => 100,
            'quantity' => 1,
        ])->assertSessionHasErrors('unit');
    }

    public function test_user_can_log_calories_without_creating_a_food(): void
    {
        $user = $this->onboardedUser();

        $this->actingAs($user)
            ->post('/diary-entries/quick', [
                'date' => '2026-07-26',
                'meal' => 'dinner',
                'calories' => 720,
            ])
            ->assertRedirect('/diary/2026-07-26');

        $entry = DiaryDay::firstOrFail()->entries()->firstOrFail();

        $this->assertNull($entry->food_id);
        $this->assertSame('Quick calorie entry', $entry->food_name);
        $this->assertSame(720.0, $entry->calories);
        $this->assertSame(0.0, $entry->protein);
        $this->assertSame(0.0, $entry->carbohydrates);
        $this->assertSame(0.0, $entry->fat);
        $this->assertSame(0.0, $entry->fibre);
        $this->assertDatabaseCount('foods', 0);
    }

    public function test_quick_calorie_entry_accepts_optional_macronutrients(): void
    {
        $user = $this->onboardedUser();

        $this->actingAs($user)
            ->post('/diary-entries/quick', [
                'date' => '2026-07-26',
                'meal' => 'lunch',
                'name' => 'Restaurant meal',
                'calories' => 720,
                'protein' => 35,
                'carbohydrates' => 80,
                'fat' => 28,
                'fibre' => 12,
            ])
            ->assertRedirect('/diary/2026-07-26');

        $entry = DiaryDay::firstOrFail()->entries()->firstOrFail();

        $this->assertSame('Restaurant meal', $entry->food_name);
        $this->assertSame(720.0, $entry->calories);
        $this->assertSame(35.0, $entry->protein);
        $this->assertSame(80.0, $entry->carbohydrates);
        $this->assertSame(28.0, $entry->fat);
        $this->assertSame(12.0, $entry->fibre);
    }

    public function test_users_cannot_change_another_users_diary(): void
    {
        $owner = $this->onboardedUser();
        $intruder = $this->onboardedUser();
        $day = $owner->diaryDays()->create(['date' => '2026-07-26']);
        $entry = $day->entries()->create([
            'meal' => 'snacks',
            'food_name' => 'Apple',
            'unit' => 'g',
            'quantity' => 1,
            'amount' => 180,
            'total_grams' => 180,
            'calories' => 95,
            'protein' => 0.5,
            'carbohydrates' => 25,
            'fat' => 0.3,
        ]);

        $this->actingAs($intruder)
            ->delete("/diary-entries/{$entry->id}")
            ->assertForbidden();
    }

    public function test_recipe_calculates_nutrition_from_cooked_weight(): void
    {
        $user = $this->onboardedUser();
        $chicken = Food::create([
            'user_id' => $user->id,
            'name' => 'Chicken',
            'calories' => 200,
            'protein' => 10,
            'carbohydrates' => 0,
            'fat' => 5,
            'fibre' => 0,
            'unit_type' => 'g',
            'is_public' => false,
        ]);
        $rice = Food::create([
            'user_id' => $user->id,
            'name' => 'Rice',
            'calories' => 100,
            'protein' => 2,
            'carbohydrates' => 20,
            'fat' => 1,
            'fibre' => 1,
            'unit_type' => 'g',
            'is_public' => false,
        ]);

        $this->actingAs($user)->post('/recipes', [
            'name' => 'Chicken rice',
            'cooked_weight' => 400,
            'ingredients' => [
                ['food_id' => $chicken->id, 'amount' => 200],
                ['food_id' => $rice->id, 'amount' => 300],
            ],
        ])->assertRedirect('/recipes');

        $recipe = Recipe::with('ingredients')->firstOrFail();
        $recipeFood = $recipe->food;

        $this->assertSame(700.0, $recipe->total_calories);
        $this->assertSame(26.0, $recipe->total_protein);
        $this->assertCount(2, $recipe->ingredients);
        $this->assertNotNull($recipeFood);
        $this->assertSame(175.0, $recipeFood->calories);
        $this->assertSame(6.5, $recipeFood->protein);
        $this->assertSame(15.0, $recipeFood->carbohydrates);
    }

    public function test_recipe_cannot_use_another_users_private_food(): void
    {
        $owner = $this->onboardedUser();
        $intruder = $this->onboardedUser();
        $privateFood = Food::create([
            'user_id' => $owner->id,
            'name' => 'Secret ingredient',
            'calories' => 100,
            'protein' => 1,
            'carbohydrates' => 1,
            'fat' => 1,
            'unit_type' => 'g',
            'is_public' => false,
        ]);

        $this->actingAs($intruder)->post('/recipes', [
            'name' => 'Invalid recipe',
            'cooked_weight' => 100,
            'ingredients' => [
                ['food_id' => $privateFood->id, 'amount' => 100],
            ],
        ])->assertSessionHasErrors('ingredients');

        $this->assertDatabaseCount('recipes', 0);
    }

    public function test_food_autocomplete_is_server_side_and_respects_visibility(): void
    {
        $user = $this->onboardedUser();
        $otherUser = $this->onboardedUser();
        Food::create([
            'name' => 'Chicken breast',
            'calories' => 165,
            'unit_type' => 'g',
            'is_public' => true,
        ]);
        Food::create([
            'user_id' => $user->id,
            'name' => 'Chicken family recipe',
            'calories' => 200,
            'unit_type' => 'g',
            'is_public' => false,
        ]);
        Food::create([
            'user_id' => $otherUser->id,
            'name' => 'Chicken secret',
            'calories' => 250,
            'unit_type' => 'g',
            'is_public' => false,
        ]);

        $this->actingAs($user)
            ->getJson('/foods/search?search=Chicken')
            ->assertOk()
            ->assertJsonCount(2, 'foods')
            ->assertJsonFragment(['name' => 'Chicken breast'])
            ->assertJsonFragment(['name' => 'Chicken family recipe'])
            ->assertJsonMissing(['name' => 'Chicken secret']);
    }

    public function test_food_page_initially_contains_only_favourites(): void
    {
        $user = $this->onboardedUser();
        $favourite = Food::create([
            'name' => 'Favourite apple',
            'calories' => 52,
            'unit_type' => 'g',
            'is_public' => true,
        ]);
        Food::create([
            'name' => 'Ordinary pear',
            'calories' => 57,
            'unit_type' => 'g',
            'is_public' => true,
        ]);
        DB::table('food_favourites')->insert([
            'user_id' => $user->id,
            'food_id' => $favourite->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/foods')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('foods/index')
                ->has('foods', 1)
                ->where('foods.0.id', $favourite->id)
                ->where('foods.0.is_favourite', true)
                ->where('pagination.next_cursor', null)
            );
    }

    public function test_food_search_uses_cursor_pagination(): void
    {
        $user = $this->onboardedUser();

        foreach (range(1, 25) as $index) {
            Food::create([
                'name' => sprintf('Searchable food %02d', $index),
                'calories' => 100 + $index,
                'unit_type' => 'g',
                'is_public' => true,
            ]);
        }

        $firstPage = $this->actingAs($user)
            ->getJson('/foods/search?search=Searchable');

        $firstPage
            ->assertOk()
            ->assertJsonCount(20, 'foods');

        $cursor = $firstPage->json('next_cursor');
        $this->assertIsString($cursor);

        $this->actingAs($user)
            ->getJson('/foods/search?'.http_build_query([
                'search' => 'Searchable',
                'cursor' => $cursor,
            ]))
            ->assertOk()
            ->assertJsonCount(5, 'foods')
            ->assertJsonPath('next_cursor', null);
    }

    public function test_recipe_index_only_contains_the_authenticated_users_recipes(): void
    {
        $user = $this->onboardedUser();
        $otherUser = $this->onboardedUser();
        $ownRecipe = $user->recipes()->create([
            'name' => 'My soup',
            'cooked_weight' => 500,
            'total_calories' => 400,
            'total_protein' => 20,
            'total_carbohydrates' => 40,
            'total_fat' => 10,
            'total_fibre' => 5,
        ]);
        $otherUser->recipes()->create([
            'name' => 'Private soup',
            'cooked_weight' => 500,
            'total_calories' => 500,
            'total_protein' => 25,
            'total_carbohydrates' => 50,
            'total_fat' => 12,
            'total_fibre' => 6,
        ]);

        $this->actingAs($user)
            ->get('/recipes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('recipes/index')
                ->has('recipes', 1)
                ->where('recipes.0.id', $ownRecipe->id)
                ->where('recipes.0.name', 'My soup')
            );
    }

    public function test_recipe_creation_form_has_its_own_page(): void
    {
        $user = $this->onboardedUser();

        $this->actingAs($user)
            ->get('/recipes/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('recipes/create')
                ->where('createdFood', null)
            );
    }

    public function test_user_can_edit_recipe_and_recalculate_generated_food(): void
    {
        $user = $this->onboardedUser();
        $chicken = Food::create([
            'user_id' => $user->id,
            'name' => 'Chicken',
            'calories' => 200,
            'protein' => 10,
            'carbohydrates' => 0,
            'fat' => 5,
            'fibre' => 0,
            'unit_type' => 'g',
            'is_public' => false,
        ]);
        $rice = Food::create([
            'user_id' => $user->id,
            'name' => 'Rice',
            'calories' => 100,
            'protein' => 2,
            'carbohydrates' => 20,
            'fat' => 1,
            'fibre' => 1,
            'unit_type' => 'g',
            'is_public' => false,
        ]);

        $this->actingAs($user)->post('/recipes', [
            'name' => 'Chicken bowl',
            'cooked_weight' => 100,
            'ingredients' => [
                ['food_id' => $chicken->id, 'amount' => 100],
            ],
        ]);

        $recipe = Recipe::with('food')->firstOrFail();
        $generatedFoodId = $recipe->food_id;

        $this->actingAs($user)
            ->get("/recipes/{$recipe->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('recipes/edit')
                ->where('recipe.id', $recipe->id)
                ->where('recipe.name', 'Chicken bowl')
                ->where('recipe.ingredients.0.food_id', $chicken->id)
                ->where('recipe.ingredients.0.amount', 100)
            );

        $this->actingAs($user)
            ->put("/recipes/{$recipe->id}", [
                'name' => 'Rice bowl',
                'cooked_weight' => 400,
                'ingredients' => [
                    ['food_id' => $rice->id, 'amount' => 400],
                ],
            ])
            ->assertRedirect('/recipes')
            ->assertSessionHas('success');

        $recipe->refresh()->load('ingredients', 'food');

        $this->assertSame('Rice bowl', $recipe->name);
        $this->assertSame(400.0, $recipe->cooked_weight);
        $this->assertSame(400.0, $recipe->total_calories);
        $this->assertCount(1, $recipe->ingredients);
        $this->assertSame($rice->id, $recipe->ingredients->first()->food_id);
        $this->assertSame($generatedFoodId, $recipe->food_id);
        $this->assertSame('Rice bowl', $recipe->food->name);
        $this->assertSame(100.0, $recipe->food->calories);
        $this->assertSame(2.0, $recipe->food->protein);
        $this->assertSame(20.0, $recipe->food->carbohydrates);
    }

    public function test_user_cannot_edit_another_users_recipe(): void
    {
        $owner = $this->onboardedUser();
        $intruder = $this->onboardedUser();
        $recipe = $owner->recipes()->create([
            'name' => 'Private recipe',
            'cooked_weight' => 100,
            'total_calories' => 100,
            'total_protein' => 1,
            'total_carbohydrates' => 1,
            'total_fat' => 1,
            'total_fibre' => 0,
        ]);

        $this->actingAs($intruder)
            ->get("/recipes/{$recipe->id}/edit")
            ->assertForbidden();

        $this->actingAs($intruder)
            ->put("/recipes/{$recipe->id}", [
                'name' => 'Changed',
                'cooked_weight' => 100,
                'ingredients' => [],
            ])
            ->assertForbidden();

        $this->assertSame('Private recipe', $recipe->fresh()->name);
    }

    public function test_deleting_recipe_removes_its_generated_food(): void
    {
        $user = $this->onboardedUser();
        $ingredient = Food::create([
            'user_id' => $user->id,
            'name' => 'Ingredient',
            'calories' => 100,
            'protein' => 1,
            'carbohydrates' => 1,
            'fat' => 1,
            'unit_type' => 'g',
            'is_public' => false,
        ]);

        $this->actingAs($user)->post('/recipes', [
            'name' => 'Temporary recipe',
            'cooked_weight' => 100,
            'ingredients' => [
                ['food_id' => $ingredient->id, 'amount' => 100],
            ],
        ]);

        $recipe = Recipe::firstOrFail();
        $recipeFoodId = $recipe->food_id;

        $this->actingAs($user)
            ->delete("/recipes/{$recipe->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseMissing('foods', ['id' => $recipeFoodId]);
        $this->assertDatabaseHas('foods', ['id' => $ingredient->id]);
    }

    public function test_weekly_report_contains_aggregated_values(): void
    {
        $user = $this->onboardedUser();
        $day = $user->diaryDays()->create(['date' => '2026-07-22']);
        $day->entries()->create([
            'meal' => 'breakfast',
            'food_name' => 'Oats',
            'unit' => 'g',
            'quantity' => 1,
            'amount' => 50,
            'total_grams' => 50,
            'calories' => 190,
            'protein' => 6.5,
            'carbohydrates' => 34,
            'fat' => 3.5,
            'fibre' => 5,
        ]);

        $this->actingAs($user)
            ->get('/reports?week=2026-07-22')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/index')
                ->where('loggedDays', 1)
                ->where('averages.calories', 190)
                ->where('averages.fibre', 5)
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
            'unit' => 'g',
            'quantity' => 1,
            'amount' => 100,
            'total_grams' => 100,
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

<?php

namespace Tests\Feature;

use App\Models\DiaryDay;
use App\Models\Food;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;
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
            'username' => 'alex',
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
        ])->assertRedirect('/diary/2026-07-26?focus_meal=lunch');

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
        ])->assertRedirect('/diary/2026-07-26?focus_meal=lunch');

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

    public function test_liquid_foods_are_logged_and_updated_by_volume(): void
    {
        $user = $this->onboardedUser();
        $milk = Food::create([
            'user_id' => $user->id,
            'name' => 'Milk',
            'calories' => 60,
            'protein' => 3.2,
            'carbohydrates' => 4.8,
            'fat' => 3.5,
            'nutrition_basis_amount' => 100,
            'nutrition_basis_unit' => 'ml',
            'is_public' => false,
        ]);

        $this->actingAs($user)->post('/diary-entries', [
            'food_id' => $milk->id,
            'date' => '2026-07-26',
            'meal' => 'breakfast',
            'unit' => 'l',
            'amount' => 0.25,
            'quantity' => 2,
        ])->assertRedirect('/diary/2026-07-26?focus_meal=breakfast');

        $entry = DiaryDay::firstOrFail()->entries()->firstOrFail();
        $this->assertNull($entry->total_grams);
        $this->assertSame(500.0, $entry->total_milliliters);
        $this->assertSame(300.0, $entry->calories);

        $this->actingAs($user)->put("/diary-entries/{$entry->id}", [
            'unit' => 'ml',
            'amount' => 200,
            'quantity' => 1,
        ])->assertRedirect();

        $entry->refresh();
        $this->assertSame(200.0, $entry->total_milliliters);
        $this->assertSame(120.0, $entry->calories);

        $this->actingAs($user)->post('/diary-entries', [
            'food_id' => $milk->id,
            'date' => '2026-07-27',
            'meal' => 'breakfast',
            'unit' => 'g',
            'amount' => 100,
            'quantity' => 1,
        ])->assertSessionHasErrors('unit');
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

    public function test_recipe_uses_each_ingredients_nutrition_basis(): void
    {
        $user = $this->onboardedUser();
        $milk = Food::create([
            'user_id' => $user->id,
            'name' => 'Milk',
            'calories' => 150,
            'protein' => 8,
            'carbohydrates' => 12,
            'fat' => 8,
            'nutrition_basis_amount' => 250,
            'nutrition_basis_unit' => 'ml',
            'is_public' => false,
        ]);

        $this->actingAs($user)->post('/recipes', [
            'name' => 'Milk pudding',
            'cooked_weight' => 600,
            'ingredients' => [
                ['food_id' => $milk->id, 'amount' => 500],
            ],
        ])->assertRedirect('/recipes');

        $recipe = Recipe::with('ingredients', 'food')->firstOrFail();
        $this->assertSame(300.0, $recipe->total_calories);
        $this->assertSame(50.0, $recipe->food->calories);
        $this->assertSame('ml', $recipe->ingredients->first()->unit);
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

    public function test_food_page_contains_the_last_100_distinct_logged_foods(): void
    {
        $user = $this->onboardedUser();
        $day = DiaryDay::create([
            'user_id' => $user->id,
            'date' => '2026-07-28',
        ]);
        $foods = collect(range(1, 102))->map(
            fn (int $index) => Food::create([
                'name' => "Recent food {$index}",
                'calories' => 100,
                'is_public' => true,
            ])
        );
        $now = now();

        foreach ($foods as $food) {
            DB::table('diary_entries')->insert([
                'diary_day_id' => $day->id,
                'food_id' => $food->id,
                'meal' => 'dinner',
                'food_name' => $food->name,
                'amount' => 100,
                'calories' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('diary_entries')->insert([
            'diary_day_id' => $day->id,
            'food_id' => null,
            'meal' => 'dinner',
            'food_name' => 'Quick calories',
            'amount' => 1,
            'calories' => 250,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('diary_entries')->insert([
            'diary_day_id' => $day->id,
            'food_id' => $foods->first()->id,
            'meal' => 'dinner',
            'food_name' => $foods->first()->name,
            'amount' => 100,
            'calories' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($user)
            ->get('/foods?date=2026-07-28&meal=dinner')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('lists.recent', 100)
                ->where('lists.recent.0.id', $foods->first()->id)
                ->where(
                    'lists.recent.99.id',
                    $foods->get(3)->id
                )
            );
    }

    public function test_recipe_food_list_contains_own_and_favourited_friend_recipes(): void
    {
        $user = $this->onboardedUser();
        $user->update(['username' => 'recipe-list-user']);
        $friend = $this->onboardedUser();
        $friend->update(['username' => 'recipe-list-friend']);
        $ownRecipe = Food::create([
            'user_id' => $user->id,
            'name' => 'My recipe',
            'food_type' => 'recipe',
            'calories' => 100,
            'is_public' => false,
        ]);
        $favouriteFriendRecipe = Food::create([
            'user_id' => $friend->id,
            'name' => 'Favourite friend recipe',
            'food_type' => 'recipe',
            'calories' => 120,
            'is_public' => false,
        ]);
        $otherFriendRecipe = Food::create([
            'user_id' => $friend->id,
            'name' => 'Other friend recipe',
            'food_type' => 'recipe',
            'calories' => 140,
            'is_public' => false,
        ]);
        DB::table('friendships')->insert([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
            'requested_by' => $user->id,
            'status' => 'accepted',
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('food_favourites')->insert([
            'user_id' => $user->id,
            'food_id' => $favouriteFriendRecipe->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/foods')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('lists.recipes', 2)
                ->where(
                    'lists.recipes.0.recipe_owner.username',
                    'recipe-list-friend'
                )
                ->where(
                    'lists.recipes',
                    fn ($recipes) => collect($recipes)
                        ->pluck('id')
                        ->sort()
                        ->values()
                        ->all() === collect([
                            $ownRecipe->id,
                            $favouriteFriendRecipe->id,
                        ])->sort()->values()->all()
                        && ! collect($recipes)
                            ->pluck('id')
                            ->contains($otherFriendRecipe->id)
                )
            );
    }

    public function test_food_page_defaults_to_todays_meal_for_the_users_timezone(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::create(
                2026,
                7,
                28,
                8,
                30,
                timezone: 'Europe/Bucharest'
            )
        );

        try {
            $user = $this->onboardedUser();

            $this->actingAs($user)
                ->get('/foods')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('context.date', null)
                    ->where('context.today', '2026-07-28')
                    ->where('context.meal', 'breakfast')
                );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_food_page_uses_the_selected_meal(): void
    {
        $user = $this->onboardedUser();

        $this->actingAs($user)
            ->get('/foods?date=2026-07-28&meal=dinner')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('context.date', '2026-07-28')
                ->where('context.meal', 'dinner')
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

    public function test_food_search_prioritizes_generic_foods_before_products(): void
    {
        $user = $this->onboardedUser();
        $product = Food::create([
            'name' => 'Milk A branded',
            'brand' => 'Example Dairy',
            'calories' => 64,
            'unit_type' => 'g',
            'food_type' => 'product',
            'is_public' => true,
        ]);
        $generic = Food::create([
            'name' => 'Milk Z whole',
            'calories' => 61,
            'unit_type' => 'g',
            'food_type' => 'generic',
            'is_public' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/foods/search?search=Milk')
            ->assertOk()
            ->assertJsonCount(2, 'foods')
            ->assertJsonPath('foods.0.id', $generic->id)
            ->assertJsonPath('foods.1.id', $product->id);
    }

    public function test_food_search_orders_each_type_by_relevance_and_stable_tiebreakers(): void
    {
        $user = $this->onboardedUser();
        $foods = collect([
            [
                'key' => 'product_exact',
                'name' => 'Bread',
                'food_type' => 'product',
                'popularity_score' => 100,
            ],
            [
                'key' => 'generic_other',
                'name' => 'Toasted rye bread',
                'food_type' => 'generic',
                'popularity_score' => 100,
            ],
            [
                'key' => 'generic_prefix_long',
                'name' => 'Bread, whole wheat',
                'food_type' => 'generic',
                'popularity_score' => 100,
            ],
            [
                'key' => 'generic_prefix_less_popular',
                'name' => 'Bread aa',
                'food_type' => 'generic',
                'popularity_score' => 5,
            ],
            [
                'key' => 'generic_prefix_popular',
                'name' => 'Bread bb',
                'food_type' => 'generic',
                'popularity_score' => 10,
            ],
            [
                'key' => 'generic_exact',
                'name' => 'Bread',
                'food_type' => 'generic',
                'popularity_score' => 0,
            ],
        ])->mapWithKeys(function (array $attributes) {
            $key = $attributes['key'];
            $popularity = $attributes['popularity_score'];
            unset($attributes['key']);
            unset($attributes['popularity_score']);

            $food = Food::create([
                ...$attributes,
                'calories' => 100,
                'is_public' => true,
            ]);
            $food->forceFill([
                'popularity_score' => $popularity,
            ])->save();

            return [$key => $food];
        });

        $response = $this->actingAs($user)
            ->getJson('/foods/search?search=Bread')
            ->assertOk()
            ->assertJsonCount(6, 'foods');

        $this->assertSame([
            $foods['generic_exact']->id,
            $foods['generic_prefix_popular']->id,
            $foods['generic_prefix_less_popular']->id,
            $foods['generic_prefix_long']->id,
            $foods['generic_other']->id,
            $foods['product_exact']->id,
        ], collect($response->json('foods'))->pluck('id')->all());
    }

    public function test_food_search_finds_an_exact_barcode(): void
    {
        $user = $this->onboardedUser();
        $product = Food::create([
            'name' => 'Unrelated product name',
            'barcode' => '5941234567890',
            'calories' => 100,
            'unit_type' => 'g',
            'food_type' => 'product',
            'is_public' => true,
        ]);
        Food::create([
            'name' => '5941234567890 is not a barcode match',
            'calories' => 50,
            'unit_type' => 'g',
            'food_type' => 'generic',
            'is_public' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/foods/search?search=5941234567890')
            ->assertOk()
            ->assertJsonCount(1, 'foods')
            ->assertJsonPath('foods.0.id', $product->id);
    }

    public function test_a_barcode_product_created_by_one_user_is_available_to_others(): void
    {
        $creator = $this->onboardedUser();
        $otherUser = $this->onboardedUser();

        $this->actingAs($creator)
            ->post('/foods', [
                'name' => 'Shared scanned yogurt',
                'brand' => 'Example Dairy',
                'barcode' => '5941234567891',
                'calories' => 72,
                'nutrition_basis_amount' => 100,
                'nutrition_basis_unit' => 'g',
                'protein' => 4,
                'carbohydrates' => 8,
                'fat' => 3,
                'fibre' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Product created and shared.');

        $product = Food::where('barcode', '5941234567891')->firstOrFail();

        $this->assertNull($product->user_id);
        $this->assertSame('product', $product->food_type);
        $this->assertSame(2, $product->search_priority);
        $this->assertTrue($product->is_public);

        $this->actingAs($otherUser)
            ->getJson('/foods/search?search=5941234567891')
            ->assertOk()
            ->assertJsonCount(1, 'foods')
            ->assertJsonPath('foods.0.id', $product->id)
            ->assertJsonPath('foods.0.is_custom', false);
    }

    public function test_a_custom_food_without_a_barcode_remains_user_owned(): void
    {
        $creator = $this->onboardedUser();

        $this->actingAs($creator)
            ->post('/foods', [
                'name' => 'My homemade soup',
                'calories' => 80,
            ])
            ->assertRedirect();

        $food = Food::where('name', 'My homemade soup')->firstOrFail();

        $this->assertSame($creator->id, $food->user_id);
        $this->assertSame('custom', $food->food_type);
        $this->assertFalse($food->is_public);
    }

    public function test_a_scanned_barcode_cannot_create_a_duplicate_product(): void
    {
        $user = $this->onboardedUser();
        Food::create([
            'name' => 'Existing shared product',
            'barcode' => '5941234567892',
            'calories' => 100,
            'food_type' => 'product',
            'is_public' => true,
        ]);

        $this->actingAs($user)
            ->post('/foods', [
                'name' => 'Duplicate product',
                'barcode' => '5941234567892',
                'calories' => 100,
            ])
            ->assertSessionHasErrors('barcode');

        $this->assertSame(
            1,
            Food::where('barcode', '5941234567892')->count()
        );
    }

    public function test_existing_encoded_food_names_are_decoded_for_display(): void
    {
        $foodId = DB::table('foods')->insertGetId([
            'name' => '&amp;quot;CRISPY&amp;quot; &amp; chips',
            'brand' => 'Brand &amp; Co',
            'calories' => 457,
            'food_type' => 'product',
            'search_priority' => 2,
            'is_public' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('food_translations')->insert([
            'food_id' => $foodId,
            'locale' => 'en',
            'name' => '&quot;CRISPY&quot; translated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $food = Food::with('translation')->findOrFail($foodId);

        $this->assertSame('"CRISPY" & chips', $food->name);
        $this->assertSame('Brand & Co', $food->brand);
        $this->assertSame('"CRISPY" translated', $food->localizedName());
    }

    public function test_recipe_index_separates_own_and_friend_recipes(): void
    {
        $user = $this->onboardedUser();
        $friend = $this->onboardedUser();
        $friend->update(['username' => 'recipe-friend']);
        $stranger = $this->onboardedUser();
        $ownRecipe = $user->recipes()->create([
            'name' => 'My soup',
            'cooked_weight' => 500,
            'total_calories' => 400,
            'total_protein' => 20,
            'total_carbohydrates' => 40,
            'total_fat' => 10,
            'total_fibre' => 5,
        ]);
        $friendRecipeFood = $friend->foods()->create([
            'name' => 'Friend soup',
            'food_type' => 'recipe',
            'calories' => 90,
            'is_public' => false,
        ]);
        $friendRecipe = $friend->recipes()->create([
            'food_id' => $friendRecipeFood->id,
            'name' => 'Friend soup',
            'cooked_weight' => 500,
            'total_calories' => 450,
            'total_protein' => 22,
            'total_carbohydrates' => 45,
            'total_fat' => 11,
            'total_fibre' => 5,
        ]);
        $newerFriendRecipeFood = $friend->foods()->create([
            'name' => 'Newer friend recipe',
            'food_type' => 'recipe',
            'calories' => 90,
            'is_public' => false,
        ]);
        $friend->recipes()->create([
            'food_id' => $newerFriendRecipeFood->id,
            'name' => 'Newer friend recipe',
            'cooked_weight' => 400,
            'total_calories' => 360,
            'total_protein' => 18,
            'total_carbohydrates' => 36,
            'total_fat' => 9,
            'total_fibre' => 4,
        ]);
        $unsavedFriendRecipeFood = $friend->foods()->create([
            'name' => 'Unsaved friend recipe',
            'food_type' => 'recipe',
            'calories' => 80,
            'is_public' => false,
        ]);
        $unsavedFriendRecipe = $friend->recipes()->create([
            'food_id' => $unsavedFriendRecipeFood->id,
            'name' => 'Unsaved friend recipe',
            'cooked_weight' => 400,
            'total_calories' => 320,
            'total_protein' => 16,
            'total_carbohydrates' => 32,
            'total_fat' => 8,
            'total_fibre' => 4,
        ]);
        $stranger->recipes()->create([
            'name' => 'Private soup',
            'cooked_weight' => 500,
            'total_calories' => 500,
            'total_protein' => 25,
            'total_carbohydrates' => 50,
            'total_fat' => 12,
            'total_fibre' => 6,
        ]);
        DB::table('friendships')->insert([
            'user_id' => min($user->id, $friend->id),
            'friend_id' => max($user->id, $friend->id),
            'requested_by' => $user->id,
            'status' => 'accepted',
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('food_favourites')->insert([
            [
                'user_id' => $user->id,
                'food_id' => $friendRecipeFood->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'food_id' => $newerFriendRecipeFood->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user)
            ->get("/recipes?tab=friends&recipe={$friendRecipe->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('recipes/index')
                ->has('recipes', 1)
                ->where('recipes.0.id', $ownRecipe->id)
                ->where('recipes.0.name', 'My soup')
                ->where('recipes.0.is_owner', true)
                ->has('friendRecipes', 2)
                ->where('friendRecipes.0.id', $friendRecipe->id)
                ->where('friendRecipes.0.name', 'Friend soup')
                ->where(
                    'friendRecipes.0.owner.username',
                    'recipe-friend'
                )
                ->where('friendRecipes.0.is_owner', false)
                ->where(
                    'friendRecipes',
                    fn ($recipes) => ! collect($recipes)
                        ->pluck('id')
                        ->contains($unsavedFriendRecipe->id)
                )
                ->where('filters.tab', 'friends')
                ->where('filters.recipe', $friendRecipe->id)
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

    public function test_report_defaults_to_the_last_seven_days(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::create(
                2026,
                7,
                22,
                12,
                timezone: 'Europe/Bucharest'
            )
        );

        try {
            $user = $this->onboardedUser();

            $this->actingAs($user)
                ->get('/reports')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('reports/index')
                    ->where('period.range', '7')
                    ->where('period.start', '2026-07-16')
                    ->where('period.end', '2026-07-22')
                    ->where('period.today', '2026-07-22')
                    ->where('period.days', 7)
                    ->has('chart', 7)
                    ->where('chart.0.date', '2026-07-16')
                    ->where('chart.6.date', '2026-07-22')
                );

            $this->actingAs($user)
                ->get('/reports?range=30')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('period.range', '30')
                    ->where('period.start', '2026-06-23')
                    ->where('period.end', '2026-07-22')
                    ->where('period.days', 30)
                    ->has('chart', 30)
                );

            $this->actingAs($user)
                ->get('/reports?range=365')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('period.range', '365')
                    ->where('period.start', '2025-07-23')
                    ->where('period.end', '2026-07-22')
                    ->where('period.days', 365)
                    ->has('chart', 13)
                );

            $this->actingAs($user)
                ->get('/reports?range=custom&start=2026-05-01&end=2026-05-15')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('period.range', 'custom')
                    ->where('period.start', '2026-05-01')
                    ->where('period.end', '2026-05-15')
                    ->where('period.days', 15)
                    ->has('chart', 15)
                );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_report_contains_aggregated_values_from_the_selected_seven_days(): void
    {
        $user = $this->onboardedUser();
        $excludedDay = $user->diaryDays()->create(['date' => '2026-07-15']);
        $excludedDay->entries()->create([
            'meal' => 'breakfast',
            'food_name' => 'Old oats',
            'unit' => 'g',
            'quantity' => 1,
            'amount' => 50,
            'total_grams' => 50,
            'calories' => 500,
            'protein' => 10,
            'carbohydrates' => 50,
            'fat' => 10,
            'fibre' => 10,
        ]);
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
            ->get('/reports?end=2026-07-22')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/index')
                ->where('period.range', '7')
                ->where('period.start', '2026-07-16')
                ->where('period.end', '2026-07-22')
                ->where('period.days', 7)
                ->has('chart', 7)
                ->where('chart.0.date', '2026-07-16')
                ->where('chart.6.date', '2026-07-22')
                ->where('loggedDays', 1)
                ->where('averages.calories', 190)
                ->where('averages.fibre', 5)
            );
    }

    public function test_report_daily_kcal_average_excludes_today(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::create(
                2026,
                7,
                22,
                12,
                timezone: 'Europe/Bucharest'
            )
        );

        try {
            $user = $this->onboardedUser();

            foreach ([
                '2026-07-21' => 1800,
                '2026-07-22' => 600,
            ] as $date => $calories) {
                $day = $user->diaryDays()->create(['date' => $date]);
                $day->entries()->create([
                    'meal' => 'breakfast',
                    'food_name' => 'Daily food',
                    'unit' => 'g',
                    'quantity' => 1,
                    'amount' => 100,
                    'total_grams' => 100,
                    'calories' => $calories,
                    'protein' => 10,
                    'carbohydrates' => 20,
                    'fat' => 5,
                    'fibre' => 3,
                ]);
            }

            $this->actingAs($user)
                ->get('/reports')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('loggedDays', 2)
                    ->where('averages.calories', 1800)
                    ->where('chart.6.date', '2026-07-22')
                    ->where('chart.6.calories', 600)
                );
        } finally {
            CarbonImmutable::setTestNow();
        }
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

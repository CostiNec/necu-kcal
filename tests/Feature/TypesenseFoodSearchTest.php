<?php

namespace Tests\Feature;

use App\Contracts\FoodSearchIndex;
use App\Models\Food;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TypesenseFoodSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_typesense_search_filters_visibility_and_preserves_hit_order(): void
    {
        $viewer = $this->onboardedUser();
        $friend = $this->onboardedUser();
        $stranger = $this->onboardedUser();
        $this->acceptedFriendship($viewer, $friend);
        $public = $this->food(['name' => 'Ou public']);
        $friendRecipe = $this->food([
            'user_id' => $friend->id,
            'name' => 'Ou friend recipe',
            'food_type' => 'recipe',
            'is_public' => false,
        ]);
        $hidden = $this->food([
            'user_id' => $stranger->id,
            'name' => 'Ou private',
            'food_type' => 'custom',
            'is_public' => false,
        ]);
        $this->enableTypesense();

        $index = Mockery::mock(FoodSearchIndex::class);
        $index->shouldReceive('search')
            ->once()
            ->with('foods', Mockery::on(function (array $parameters) use (
                $viewer,
                $friend
            ): bool {
                return $parameters['q'] === 'ou'
                    && $parameters['page'] === 1
                    && $parameters['per_page'] === 20
                    && str_contains(
                        $parameters['filter_by'],
                        "user_id:={$viewer->id}"
                    )
                    && str_contains(
                        $parameters['filter_by'],
                        "user_id:=[{$friend->id}]"
                    )
                    && $parameters['sort_by'] === implode(',', [
                        'search_priority:asc',
                        '_text_match:desc',
                        'ranking_score:desc',
                    ]);
            }))
            ->andReturn([
                'found' => 21,
                'hits' => [
                    ['document' => ['id' => (string) $friendRecipe->id]],
                    ['document' => ['id' => (string) $public->id]],
                    ['document' => ['id' => (string) $hidden->id]],
                ],
            ]);
        $this->app->instance(FoodSearchIndex::class, $index);

        $response = $this->actingAs($viewer)
            ->getJson('/foods/search?search=ou')
            ->assertOk()
            ->assertJsonCount(2, 'foods');

        $this->assertSame([
            $friendRecipe->id,
            $public->id,
        ], collect($response->json('foods'))->pluck('id')->all());
        $this->assertStringStartsWith(
            'ts_',
            $response->json('next_cursor')
        );
    }

    public function test_typesense_cursor_requests_the_next_page(): void
    {
        $viewer = $this->onboardedUser();
        $first = $this->food(['name' => 'Ou first']);
        $second = $this->food(['name' => 'Ou second']);
        $this->enableTypesense();

        $index = Mockery::mock(FoodSearchIndex::class);
        $index->shouldReceive('search')
            ->once()
            ->with('foods', Mockery::on(
                fn (array $parameters) => $parameters['page'] === 1
            ))
            ->andReturn([
                'found' => 21,
                'hits' => [
                    ['document' => ['id' => (string) $first->id]],
                ],
            ]);
        $index->shouldReceive('search')
            ->once()
            ->with('foods', Mockery::on(
                fn (array $parameters) => $parameters['page'] === 2
            ))
            ->andReturn([
                'found' => 21,
                'hits' => [
                    ['document' => ['id' => (string) $second->id]],
                ],
            ]);
        $this->app->instance(FoodSearchIndex::class, $index);

        $cursor = $this->actingAs($viewer)
            ->getJson('/foods/search?search=ou')
            ->assertOk()
            ->json('next_cursor');

        $this->actingAs($viewer)
            ->getJson('/foods/search?'.http_build_query([
                'search' => 'ou',
                'cursor' => $cursor,
            ]))
            ->assertOk()
            ->assertJsonPath('foods.0.id', $second->id)
            ->assertJsonPath('next_cursor', null);
    }

    public function test_search_returns_service_unavailable_when_typesense_is_down(): void
    {
        $viewer = $this->onboardedUser();
        $this->enableTypesense();

        $index = Mockery::mock(FoodSearchIndex::class);
        $index->shouldReceive('search')
            ->once()
            ->andThrow(new RuntimeException('Typesense unavailable'));
        $this->app->instance(FoodSearchIndex::class, $index);

        $this->actingAs($viewer)
            ->getJson('/foods/search?search=Ounce')
            ->assertServiceUnavailable();
    }

    public function test_barcode_search_uses_typesense_without_typos_or_prefixes(): void
    {
        $viewer = $this->onboardedUser();
        $food = $this->food([
            'name' => 'Barcode food',
            'barcode' => '5941234567890',
        ]);
        $this->enableTypesense();

        $index = Mockery::mock(FoodSearchIndex::class);
        $index->shouldReceive('search')
            ->once()
            ->with('foods', Mockery::on(
                fn (array $parameters) => $parameters['q']
                        === '5941234567890'
                    && $parameters['query_by'] === 'barcode'
                    && $parameters['num_typos'] === '0'
                    && $parameters['prefix'] === false
            ))
            ->andReturn([
                'found' => 1,
                'hits' => [
                    ['document' => ['id' => (string) $food->id]],
                ],
            ]);
        $this->app->instance(FoodSearchIndex::class, $index);

        $this->actingAs($viewer)
            ->getJson('/foods/search?search=5941234567890')
            ->assertOk()
            ->assertJsonPath('foods.0.id', $food->id);
    }

    public function test_favourites_only_search_is_filtered_in_typesense(): void
    {
        $viewer = $this->onboardedUser();
        $favourite = $this->food(['name' => 'Favourite oats']);
        DB::table('food_favourites')->insert([
            'user_id' => $viewer->id,
            'food_id' => $favourite->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->enableTypesense();

        $index = Mockery::mock(FoodSearchIndex::class);
        $index->shouldReceive('search')
            ->once()
            ->with('foods', Mockery::on(
                fn (array $parameters) => str_contains(
                    $parameters['filter_by'],
                    "id:=[{$favourite->id}]"
                )
            ))
            ->andReturn([
                'found' => 1,
                'hits' => [
                    ['document' => ['id' => (string) $favourite->id]],
                ],
            ]);
        $this->app->instance(FoodSearchIndex::class, $index);

        $this->actingAs($viewer)
            ->getJson('/foods/search?'.http_build_query([
                'search' => 'oats',
                'favourites_only' => true,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'foods')
            ->assertJsonPath('foods.0.id', $favourite->id);
    }

    public function test_favourites_only_search_skips_typesense_when_none_exist(): void
    {
        $viewer = $this->onboardedUser();
        $this->enableTypesense();

        $index = Mockery::mock(FoodSearchIndex::class);
        $index->shouldNotReceive('search');
        $this->app->instance(FoodSearchIndex::class, $index);

        $this->actingAs($viewer)
            ->getJson('/foods/search?'.http_build_query([
                'search' => 'oats',
                'favourites_only' => true,
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'foods')
            ->assertJsonPath('next_cursor', null);
    }

    public function test_food_search_document_is_small_and_ram_conscious(): void
    {
        $food = $this->food([
            'name' => 'Whole egg',
            'brand' => 'Example',
            'barcode' => '5941234567890',
            'is_common' => true,
            'common_priority' => 3,
        ]);
        $food->translations()->create([
            'locale' => 'ro',
            'name' => 'Ou întreg',
        ]);
        $food->aliases()->create([
            'locale' => 'ro',
            'name' => 'Ou',
        ]);

        $document = $food->fresh()->toSearchableArray();

        $this->assertTrue($food->shouldBeSearchable());
        $this->assertSame((string) $food->id, $document['id']);
        $this->assertSame(['Ou întreg'], $document['translation_names']);
        $this->assertSame(['Ou'], $document['alias_names']);
        $this->assertSame('5941234567890', $document['barcode']);
        $this->assertGreaterThan(2_000_000_000_000, $document['ranking_score']);
    }

    private function enableTypesense(): void
    {
        config([
            'food-search.driver' => 'typesense',
            'food-search.fallback_to_database' => false,
            'scout.driver' => 'null',
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function food(array $attributes): Food
    {
        return Food::create([
            'name' => 'Food',
            'calories' => 100,
            'unit_type' => 'g',
            'is_public' => true,
            ...$attributes,
        ]);
    }

    private function acceptedFriendship(User $first, User $second): void
    {
        [$userId, $friendId] = Friendship::orderedIds($first, $second);

        Friendship::create([
            'user_id' => $userId,
            'friend_id' => $friendId,
            'requested_by' => $first->id,
            'status' => Friendship::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\User;
use App\Services\FoodSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeduplicateGenericFoodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_exact_generic_duplicates_without_deleting_them(): void
    {
        $cnfSourceId = DB::table('food_sources')
            ->where('code', 'canadian_nutrient_file')
            ->value('id');
        $usdaSourceId = DB::table('food_sources')
            ->where('code', 'usda_food_data_central')
            ->value('id');
        $cnf = Food::query()->forceCreate([
            'source_id' => $cnfSourceId,
            'external_id' => 'cnf-cucumber',
            'name' => 'Cucumber, raw',
            'food_type' => 'generic',
            'calories' => 16,
            'protein' => 0.65,
            'nutrition_basis_amount' => 100,
            'nutrition_basis_unit' => 'g',
            'is_public' => true,
            'is_active' => true,
        ]);
        $usda = Food::query()->forceCreate([
            'source_id' => $usdaSourceId,
            'external_id' => 'usda-cucumber',
            'name' => 'Cucumber, raw',
            'food_type' => 'generic',
            'calories' => 16,
            'protein' => 0.62,
            'nutrition_basis_amount' => 100,
            'nutrition_basis_unit' => 'g',
            'is_public' => true,
            'is_active' => true,
        ]);
        $cnf->translations()->create([
            'locale' => 'ro',
            'name' => 'Castravete, crud',
        ]);
        $usda->translations()->create([
            'locale' => 'ro',
            'name' => 'Castravete, crud',
        ]);

        $status = Artisan::call('foods:deduplicate-generics');

        $this->assertSame(0, $status, Artisan::output());
        $this->assertSame($usda->id, $cnf->fresh()->canonical_food_id);
        $this->assertNull($usda->fresh()->canonical_food_id);
        $this->assertDatabaseHas('foods', ['id' => $cnf->id]);
        $this->assertDatabaseHas('food_aliases', [
            'food_id' => $usda->id,
            'locale' => 'ro',
            'name' => 'Castravete, crud',
            'alias_type' => 'source_duplicate',
        ]);

        $user = User::factory()->create();
        $results = app(FoodSearch::class)
            ->query($user, 'castravete')
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame($usda->id, $results->first()->id);
    }

    public function test_dry_run_reports_without_linking_duplicates(): void
    {
        Food::query()->forceCreate([
            'name' => 'Rice, cooked',
            'food_type' => 'generic',
            'calories' => 130,
            'nutrition_basis_amount' => 100,
            'nutrition_basis_unit' => 'g',
            'is_public' => true,
            'is_active' => true,
        ]);
        Food::query()->forceCreate([
            'name' => 'Rice, cooked',
            'food_type' => 'generic',
            'calories' => 129,
            'nutrition_basis_amount' => 100,
            'nutrition_basis_unit' => 'g',
            'is_public' => true,
            'is_active' => true,
        ]);

        $status = Artisan::call('foods:deduplicate-generics', [
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $this->assertSame(
            0,
            Food::query()->whereNotNull('canonical_food_id')->count()
        );
    }
}

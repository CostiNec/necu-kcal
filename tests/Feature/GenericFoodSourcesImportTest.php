<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\User;
use App\Services\FoodSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class GenericFoodSourcesImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_canadian_foods_with_macros_and_aliases(): void
    {
        $path = $this->cnfArchive();
        config([
            'generic-food-sources.sources.cnf.path' => $path,
            'generic-food-sources.progress_every' => 1,
        ]);

        $status = Artisan::call('foods:import-generic-sources', [
            '--source' => 'cnf',
            '--batch' => 1,
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $sourceId = DB::table('food_sources')
            ->where('code', 'canadian_nutrient_file')
            ->value('id');
        $food = DB::table('foods')
            ->where('source_id', $sourceId)
            ->where('external_id', '42')
            ->first();

        $this->assertNotNull($food);
        $this->assertSame('Egg, whole, raw', $food->name);
        $this->assertEquals(143, $food->calories);
        $this->assertEquals(12.6, $food->protein);
        $this->assertEquals(0.7, $food->carbohydrates);
        $this->assertEquals(9.5, $food->fat);
        $this->assertEquals(0, $food->fibre);
        $this->assertEquals(0.142, $food->sodium);
        $this->assertDatabaseHas('food_aliases', [
            'food_id' => $food->id,
            'locale' => 'en',
            'name' => 'Chicken egg',
            'source' => 'canadian_nutrient_file',
        ]);
        $this->assertDatabaseHas('food_import_runs', [
            'source_id' => $sourceId,
            'status' => 'completed',
            'inserted_count' => 1,
        ]);
    }

    public function test_common_food_curation_copies_nutrition_and_tracks_source(): void
    {
        $sourceId = DB::table('food_sources')
            ->where('code', 'fineli')
            ->value('id');
        $sourceFoodId = DB::table('foods')->insertGetId([
            'source_id' => $sourceId,
            'external_id' => 'egg-source',
            'food_type' => 'generic',
            'search_priority' => 0,
            'name' => 'Egg, whole, raw',
            'main_locale' => 'en',
            'calories' => 143,
            'nutrition_basis_amount' => 100,
            'nutrition_basis_unit' => 'g',
            'protein' => 12.6,
            'carbohydrates' => 0.7,
            'fat' => 9.5,
            'fibre' => 0,
            'is_public' => true,
            'nutrition_complete' => true,
            'is_active' => true,
            'search_text' => 'Egg whole raw',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        config(['common-foods' => [[
            'key' => 'egg',
            'en' => 'Egg',
            'ro' => 'Ou',
            'ro_aliases' => ['Ouă'],
            'candidates' => ['Egg, whole, raw'],
        ]]]);

        $status = Artisan::call('foods:curate-common', [
            '--link-exact-duplicates' => true,
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $canonical = DB::table('foods')
            ->where('external_id', 'egg')
            ->where('is_common', true)
            ->first();

        $this->assertNotNull($canonical);
        $this->assertSame($sourceFoodId, $canonical->nutrition_source_food_id);
        $this->assertSame('Egg', $canonical->name);
        $this->assertEquals(143, $canonical->calories);
        $this->assertDatabaseHas('food_translations', [
            'food_id' => $canonical->id,
            'locale' => 'ro',
            'name' => 'Ou',
            'translation_source' => 'curated_common',
        ]);
        $this->assertDatabaseHas('food_aliases', [
            'food_id' => $canonical->id,
            'locale' => 'ro',
            'name' => 'Ouă',
        ]);
    }

    public function test_aliases_are_searchable_and_common_foods_rank_first(): void
    {
        $user = User::factory()->create();
        $common = Food::create([
            'name' => 'Egg',
            'calories' => 143,
            'is_public' => true,
            'is_common' => true,
            'common_priority' => 1,
        ]);
        $variant = Food::create([
            'name' => 'Egg white omelette',
            'calories' => 120,
            'is_public' => true,
        ]);
        $common->aliases()->create([
            'locale' => 'ro',
            'name' => 'Ouă',
            'alias_type' => 'curated_synonym',
        ]);
        app()->setLocale('ro');

        $search = app(FoodSearch::class);
        $query = $search->query($user, 'Ouă')->select('foods.*');
        $ids = $search
            ->order($query, 'Ouă')
            ->get()
            ->pluck('id')
            ->all();

        $this->assertSame([$common->id], $ids);
        $this->assertNotContains($variant->id, $ids);
    }

    private function cnfArchive(): string
    {
        $directory = storage_path('framework/testing');

        if (
            ! is_dir($directory)
            && ! mkdir($directory, 0777, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                "Unable to create test directory: {$directory}"
            );
        }

        $path = $directory.'/cnf-'.Str::uuid().'.zip';
        $archive = new ZipArchive;

        if ($archive->open(
            $path,
            ZipArchive::CREATE | ZipArchive::OVERWRITE
        ) !== true) {
            throw new RuntimeException(
                "Unable to create CNF fixture: {$path}"
            );
        }

        $archive->addFromString(
            'Food_Name.csv',
            "\xEF\xBB\xBFFood_Code,Food_Description_EN,Food_Description_FR,Alternate_Description_EN,Alternate_Description_FR,Food_Source_Code,USDA_NDB_Code,CNF_Food_Group_Code,Comment_EN,Comment_FR,ScientificName,Food_Last_Updated_Date\n"
            ."42,\"Egg, whole, raw\",Oeuf,Chicken egg,,0,,1,,,Gallus gallus,2026-01-01\n"
        );
        $archive->addFromString(
            'Nutrient_Amount.csv',
            "\xEF\xBB\xBFFood_Code,Nutrient_Code,Nutrient_Amount,STD_Error,Observations,Nutrient_Source_Code,Nutrient_Last_Updated_Date\n"
            ."42,203,12.6,0,1,1,2026-01-01\n"
            ."42,204,9.5,0,1,1,2026-01-01\n"
            ."42,205,0.7,0,1,1,2026-01-01\n"
            ."42,208,143,0,1,1,2026-01-01\n"
            ."42,269,0.4,0,1,1,2026-01-01\n"
            ."42,291,0,0,1,1,2026-01-01\n"
            ."42,307,142,0,1,1,2026-01-01\n"
        );
        $archive->close();
        $this->beforeApplicationDestroyed(
            static fn () => is_file($path) && unlink($path)
        );

        return $path;
    }
}

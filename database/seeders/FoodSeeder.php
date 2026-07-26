<?php

namespace Database\Seeders;

use App\Models\Food;
use Illuminate\Database\Seeder;

class FoodSeeder extends Seeder
{
    public function run(): void
    {
        $foods = [
            ['chicken_breast', 'Chicken breast, cooked', 165, 31, 0, 3.6, 0, '100_g', '100 g', 100, 'g'],
            ['whole_egg', 'Whole egg', 143, 12.6, 0.7, 9.5, 0, 'large_egg', '1 large egg', 50, 'g'],
            ['greek_yogurt', 'Greek yogurt 2%', 73, 9.9, 3.9, 2, 0, 'cup', '1 cup', 200, 'g'],
            ['rolled_oats', 'Oats, rolled', 379, 13.2, 67.7, 6.5, 10.1, 'bowl', '1 bowl', 50, 'g'],
            ['white_rice', 'White rice, cooked', 130, 2.7, 28.2, 0.3, 0.4, 'cup', '1 cup', 158, 'g'],
            ['boiled_potato', 'Potato, boiled', 87, 1.9, 20.1, 0.1, 1.8, 'medium_piece', '1 medium', 170, 'g'],
            ['banana', 'Banana', 89, 1.1, 22.8, 0.3, 2.6, 'medium_piece', '1 medium', 118, 'g'],
            ['apple', 'Apple', 52, 0.3, 13.8, 0.2, 2.4, 'medium_piece', '1 medium', 182, 'g'],
            ['avocado', 'Avocado', 160, 2, 8.5, 14.7, 6.7, 'half_avocado', '1/2 avocado', 100, 'g'],
            ['almonds', 'Almonds', 579, 21.2, 21.6, 49.9, 12.5, 'handful', '1 handful', 28, 'g'],
            ['whole_milk', 'Whole milk', 61, 3.2, 4.8, 3.3, 0, 'glass', '1 glass', 250, 'ml'],
            ['baked_salmon', 'Salmon, baked', 206, 22.1, 0, 12.4, 0, 'fillet', '1 fillet', 150, 'g'],
        ];

        foreach ($foods as $item) {
            [
                $key,
                $name,
                $calories,
                $protein,
                $carbohydrates,
                $fat,
                $fibre,
                $servingKey,
                $servingName,
                $servingAmount,
                $unitType,
            ] = $item;

            $food = Food::updateOrCreate(
                ['translation_key' => "foods.{$key}"],
                [
                    'user_id' => null,
                    'name' => $name,
                    'brand' => null,
                    'calories' => $calories,
                    'protein' => $protein,
                    'carbohydrates' => $carbohydrates,
                    'fat' => $fat,
                    'fibre' => $fibre,
                    'unit_type' => $unitType,
                    'is_public' => true,
                ]
            );

            $food->servings()->updateOrCreate(
                ['translation_key' => "servings.{$servingKey}"],
                [
                    'name' => $servingName,
                    'amount' => $servingAmount,
                    'is_default' => true,
                ]
            );
        }
    }
}

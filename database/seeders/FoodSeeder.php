<?php

namespace Database\Seeders;

use App\Models\Food;
use Illuminate\Database\Seeder;

class FoodSeeder extends Seeder
{
    public function run(): void
    {
        $foods = [
            ['Chicken breast, cooked', 'Piept de pui, gătit', 165, 31, 0, 3.6, 0],
            ['Whole egg', 'Ou întreg', 143, 12.6, 0.7, 9.5, 0],
            ['Greek yogurt 2%', 'Iaurt grecesc 2%', 73, 9.9, 3.9, 2, 0],
            ['Oats, rolled', 'Fulgi de ovăz', 379, 13.2, 67.7, 6.5, 10.1],
            ['White rice, cooked', 'Orez alb, fiert', 130, 2.7, 28.2, 0.3, 0.4],
            ['Potato, boiled', 'Cartof fiert', 87, 1.9, 20.1, 0.1, 1.8],
            ['Banana', 'Banană', 89, 1.1, 22.8, 0.3, 2.6],
            ['Apple', 'Măr', 52, 0.3, 13.8, 0.2, 2.4],
            ['Avocado', 'Avocado', 160, 2, 8.5, 14.7, 6.7],
            ['Almonds', 'Migdale', 579, 21.2, 21.6, 49.9, 12.5],
            ['Salmon, baked', 'Somon la cuptor', 206, 22.1, 0, 12.4, 0],
        ];

        foreach ($foods as $item) {
            [
                $name,
                $romanianName,
                $calories,
                $protein,
                $carbohydrates,
                $fat,
                $fibre,
            ] = $item;

            $food = Food::updateOrCreate(
                [
                    'user_id' => null,
                    'name' => $name,
                ],
                [
                    'brand' => null,
                    'calories' => $calories,
                    'protein' => $protein,
                    'carbohydrates' => $carbohydrates,
                    'fat' => $fat,
                    'fibre' => $fibre,
                    'is_public' => true,
                ]
            );

            $food->translations()->updateOrCreate(
                ['locale' => 'en'],
                ['name' => $name]
            );
            $food->translations()->updateOrCreate(
                ['locale' => 'ro'],
                ['name' => $romanianName]
            );
        }
    }
}

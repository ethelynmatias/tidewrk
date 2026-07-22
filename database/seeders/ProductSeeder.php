<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /** How many random products to generate. */
    private const COUNT = 30;

    /**
     * Seed the products table with randomly generated data.
     */
    public function run(): void
    {
        $faker = fake();
        $now = now();

        $units = ['boxes', 'bottles', 'jars', 'bags', 'pkgs', 'cans', 'cartons'];

        $products = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            $products[] = [
                'ProductName'     => ucfirst($faker->unique()->words(2, true)),
                'SupplierID'      => $faker->numberBetween(1, 10),
                'CategoryId'      => $faker->numberBetween(1, 8),
                'QuantityPerUnit' => $faker->numberBetween(6, 48) . ' - ' . $faker->numberBetween(1, 24) . ' ' . $faker->randomElement($units),
                'UnitPrice'       => $faker->randomFloat(2, 5, 100),
                'UnitStock'       => $faker->numberBetween(0, 150),
                'UnitsOnOrder'    => $faker->numberBetween(0, 80),
                'ReorderLevel'    => $faker->randomElement([0, 5, 10, 25]),
                'Discontinued'    => $faker->boolean(20), // ~20% discontinued
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::table('products')->insert($products);
    }
}

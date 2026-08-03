<?php

namespace Database\Factories;

use App\Models\Bread;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class BreadFactory extends Factory
{
    protected $model = Bread::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'sku' => strtoupper('BRD-'.$this->faker->unique()->numerify('###')),
            'unit' => 'pcs',
            'selling_price' => $this->faker->randomFloat(2, 20, 100),
            'cost_price' => $this->faker->randomFloat(2, 10, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
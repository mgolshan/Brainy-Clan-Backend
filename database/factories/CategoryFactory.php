<?php

namespace Database\Factories;

use App\Modules\Content\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'parent_id' => null,
            'slug' => str($name)->slug()->append('-'.fake()->unique()->numberBetween(1, 99999))->toString(),
            'name' => ['en' => $name],
            'icon' => fake()->randomElement(Category::ICONS),
            'sort' => 0,
            'min_age' => null,
            'max_age' => null,
            'is_active' => true,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }
}

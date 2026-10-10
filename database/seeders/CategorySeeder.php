<?php

namespace Database\Seeders;

use App\Modules\Content\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The 8 launch categories and their sub-scopes (database/seeders/data/categories.php).
 * Matched on slug, so re-running updates names, icons, ages and order.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, array<string, mixed>> $tree */
        $tree = require __DIR__.'/data/categories.php';

        $this->seedLevel($tree, null);
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     */
    private function seedLevel(array $nodes, ?Category $parent): void
    {
        $sort = 0;

        foreach ($nodes as $slug => $node) {
            $category = Category::query()->updateOrCreate(['slug' => $slug], [
                'parent_id' => $parent?->id,
                'name' => $node['name'],
                'icon' => $node['icon'] ?? $parent?->icon,
                'sort' => ($sort += 10),
                'min_age' => $node['min_age'] ?? null,
                'max_age' => $node['max_age'] ?? null,
                'is_active' => true,
            ]);

            if (isset($node['children']) && is_array($node['children'])) {
                $this->seedLevel($node['children'], $category);
            }
        }
    }
}

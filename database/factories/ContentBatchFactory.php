<?php

namespace Database\Factories;

use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A queued AI batch of 5 multiple-choice items (difficulty 2–4, ages 8–12).
 *
 * @extends Factory<ContentBatch>
 */
class ContentBatchFactory extends Factory
{
    protected $model = ContentBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'type_key' => 'multiple_choice',
            'locale' => 'en',
            'variant' => null,
            'min_difficulty' => 2,
            'max_difficulty' => 4,
            'min_age' => 8,
            'max_age' => 12,
            'requested_count' => 5,
            'note' => null,
            'item_count' => 0,
            'status' => ContentBatch::STATUS_QUEUED,
            'created_by' => User::factory()->staff(),
        ];
    }

    public function inReview(int $items = 0): static
    {
        return $this->state(fn () => ['status' => ContentBatch::STATUS_REVIEW, 'item_count' => $items, 'model' => 'claude-sonnet-5-5']);
    }

    public function wordPuzzle(string $variant): static
    {
        return $this->state(fn () => ['type_key' => 'word_puzzle', 'variant' => $variant]);
    }

    public function wisdomCard(): static
    {
        return $this->state(fn () => ['type_key' => 'wisdom_card']);
    }
}

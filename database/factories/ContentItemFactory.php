<?php

namespace Database\Factories;

use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A published multiple-choice item ("a" is correct) by default.
 *
 * @extends Factory<ContentItem>
 */
class ContentItemFactory extends Factory
{
    protected $model = ContentItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'type_key' => 'multiple_choice',
            'locale' => 'en',
            'difficulty' => 2,
            'min_age' => null,
            'max_age' => null,
            'payload' => [
                'question' => fake()->sentence().'?',
                'choices' => [
                    ['id' => 'a', 'text' => 'Right'],
                    ['id' => 'b', 'text' => 'Wrong 1'],
                    ['id' => 'c', 'text' => 'Wrong 2'],
                    ['id' => 'd', 'text' => 'Wrong 3'],
                ],
                'image_url' => null,
            ],
            'answer' => ['choice_id' => 'a', 'explanation' => 'Because.'],
            'status' => ContentItem::STATUS_PUBLISHED,
            'source' => 'manual',
            'published_at' => now(),
        ];
    }

    public function wisdomCard(): static
    {
        return $this->state(fn () => [
            'type_key' => 'wisdom_card',
            'payload' => [
                'card' => ['text' => 'Little strokes fell great oaks.', 'author' => 'Benjamin Franklin', 'source' => "Poor Richard's Almanack"],
                'question' => 'What is the main idea?',
                'choices' => [
                    ['id' => 'a', 'text' => 'Small efforts add up'],
                    ['id' => 'b', 'text' => 'Trees are weak'],
                    ['id' => 'c', 'text' => 'Only big efforts count'],
                ],
            ],
            'answer' => ['choice_id' => 'a', 'explanation' => null],
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ContentItem::STATUS_DRAFT, 'published_at' => null]);
    }

    public function difficulty(int $difficulty): static
    {
        return $this->state(fn () => ['difficulty' => $difficulty]);
    }
}

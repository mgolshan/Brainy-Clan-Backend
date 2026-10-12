<?php

namespace App\Modules\Content\Actions;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Policy\Models\AgeBand;

/**
 * What a member of a given age band would see for an item: the exact client payload,
 * plus whether the item would be served to that band (age range and difficulty range).
 */
class PreviewContentItem
{
    public function __construct(private readonly ChallengeTypeRegistry $registry) {}

    /**
     * @return array{type: string, payload: array<string, mixed>, correct_response: array<string, mixed>, explanation: string|null, points: int, served: bool, reasons: list<string>, ui_theme: string}
     */
    public function handle(ContentItem $item, AgeBand $band, ?int $age = null): array
    {
        $handler = $this->registry->get($item->type_key);
        $age ??= $band->min_age;
        $reasons = [];

        if (! $item->allowsAge($age)) {
            $reasons[] = "Item age range ({$item->min_age}–".($item->max_age ?? '∞').") excludes age {$age}.";
        }

        $min = (int) $band->rule('difficulty.min', 1);
        $max = (int) $band->rule('difficulty.max', 10);

        if ($item->difficulty < $min || $item->difficulty > $max) {
            $reasons[] = "Difficulty {$item->difficulty} is outside the band's range {$min}–{$max} (used only as a fallback).";
        }

        if ($item->status !== ContentItem::STATUS_PUBLISHED) {
            $reasons[] = "Status is {$item->status}; only published items are served.";
        }

        if ($item->category_id !== null && ! $item->category->allowsAge($age)) {
            $reasons[] = 'The category is hidden for this age.';
        }

        return [
            'type' => $item->type_key,
            'payload' => $handler->forClient($item),
            'correct_response' => $handler->correctResponse($item),
            'explanation' => $handler->explanation($item),
            'points' => $handler->points($item),
            'served' => $reasons === [],
            'reasons' => $reasons,
            'ui_theme' => (string) $band->rule('ui_theme', 'modern'),
        ];
    }
}

<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Contracts\ChallengeType;
use App\Modules\Content\Models\ContentItem;

/**
 * Shared logic for types answered by picking one of several choices.
 * Answer JSON: { "choice_id": "a", "explanation": "..." | null }.
 */
abstract class ChoiceChallenge implements ChallengeType
{
    protected const int MIN_CHOICES = 2;

    protected const int MAX_CHOICES = 6;

    public function validateResponse(ContentItem $item, array $response): array
    {
        $given = $response['choice_id'] ?? null;
        $choiceIds = array_column((array) ($item->payload['choices'] ?? []), 'id');

        if (! is_string($given) || ! in_array($given, $choiceIds, true)) {
            return ['response.choice_id' => ['Pick one of the choices of this item.']];
        }

        return [];
    }

    public function check(ContentItem $item, array $response): bool
    {
        $given = $response['choice_id'] ?? null;

        return is_string($given) && $given === ($item->answer['choice_id'] ?? null);
    }

    public function correctResponse(ContentItem $item): array
    {
        return ['choice_id' => (string) ($item->answer['choice_id'] ?? '')];
    }

    public function explanation(ContentItem $item): ?string
    {
        $explanation = $item->answer['explanation'] ?? null;

        return is_string($explanation) && $explanation !== '' ? $explanation : null;
    }

    public function points(ContentItem $item): int
    {
        return 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answer
     * @return array<string, list<string>>
     */
    protected function validateChoices(array $payload, array $answer): array
    {
        $errors = [];
        $choices = $payload['choices'] ?? null;

        if (! is_array($choices) || count($choices) < static::MIN_CHOICES || count($choices) > static::MAX_CHOICES) {
            $errors['payload.choices'][] = 'Between '.static::MIN_CHOICES.' and '.static::MAX_CHOICES.' choices are required.';

            return $errors;
        }

        $ids = [];

        foreach (array_values($choices) as $index => $choice) {
            if (! is_array($choice) || ! is_string($choice['id'] ?? null) || $choice['id'] === '' || ! is_string($choice['text'] ?? null) || trim($choice['text']) === '') {
                $errors["payload.choices.{$index}"][] = 'Each choice needs a non-empty "id" and "text".';

                continue;
            }

            $ids[] = $choice['id'];
        }

        if (count($ids) !== count(array_unique($ids))) {
            $errors['payload.choices'][] = 'Choice ids must be unique.';
        }

        if (! is_string($answer['choice_id'] ?? null) || ! in_array($answer['choice_id'], $ids, true)) {
            $errors['answer.choice_id'][] = 'The answer must be the id of one of the choices.';
        }

        if (isset($answer['explanation']) && ! is_string($answer['explanation'])) {
            $errors['answer.explanation'][] = 'The explanation must be text.';
        }

        return $errors;
    }

    /**
     * @return list<array{id: string, text: string}>
     */
    protected function clientChoices(ContentItem $item): array
    {
        return array_values(array_map(
            fn (array $choice) => ['id' => (string) $choice['id'], 'text' => (string) $choice['text']],
            (array) ($item->payload['choices'] ?? []),
        ));
    }

    /**
     * Reads choice_a … choice_f and correct (a-f) from a CSV row.
     *
     * @param  array<string, mixed>  $row
     * @return array{choices: list<array{id: string, text: string}>, answer: array{choice_id: string, explanation: string|null}}
     */
    protected function choicesFromRow(array $row): array
    {
        $choices = [];

        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $id) {
            $text = trim((string) ($row["choice_{$id}"] ?? ''));

            if ($text !== '') {
                $choices[] = ['id' => $id, 'text' => $text];
            }
        }

        $explanation = trim((string) ($row['explanation'] ?? ''));

        return [
            'choices' => $choices,
            'answer' => [
                'choice_id' => strtolower(trim((string) ($row['correct'] ?? ''))),
                'explanation' => $explanation !== '' ? $explanation : null,
            ],
        ];
    }
}

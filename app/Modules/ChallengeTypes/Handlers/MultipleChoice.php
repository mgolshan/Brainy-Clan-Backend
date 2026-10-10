<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\Content\Models\ContentItem;

/**
 * payload:  { "question": "Capital of Peru?", "choices": [{ "id": "a", "text": "Lima" }, …], "image_url": null }
 * response: { "choice_id": "a" }
 */
class MultipleChoice extends ChoiceChallenge
{
    public function key(): string
    {
        return 'multiple_choice';
    }

    public function name(): string
    {
        return 'Multiple choice';
    }

    public function validate(array $payload, array $answer): array
    {
        $errors = [];

        if (! is_string($payload['question'] ?? null) || trim($payload['question']) === '') {
            $errors['payload.question'][] = 'A question is required.';
        }

        if (isset($payload['image_url']) && (! is_string($payload['image_url']) || filter_var($payload['image_url'], FILTER_VALIDATE_URL) === false)) {
            $errors['payload.image_url'][] = 'The image URL must be a valid URL or null.';
        }

        return array_merge_recursive($errors, $this->validateChoices($payload, $answer));
    }

    public function forClient(ContentItem $item): array
    {
        $image = $item->payload['image_url'] ?? null;

        return [
            'question' => (string) ($item->payload['question'] ?? ''),
            'choices' => $this->clientChoices($item),
            'image_url' => is_string($image) && $image !== '' ? $image : null,
        ];
    }

    public function fromFlatRow(array $row): array
    {
        $parsed = $this->choicesFromRow($row);
        $image = trim((string) ($row['image_url'] ?? ''));

        return [
            'payload' => [
                'question' => trim((string) ($row['question'] ?? '')),
                'choices' => $parsed['choices'],
                'image_url' => $image !== '' ? $image : null,
            ],
            'answer' => $parsed['answer'],
        ];
    }

    public function flatColumns(): array
    {
        return ['question', 'choice_a', 'choice_b', 'choice_c', 'choice_d', 'correct', 'explanation', 'image_url'];
    }
}

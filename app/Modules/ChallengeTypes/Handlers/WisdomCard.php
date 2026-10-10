<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\Content\Models\ContentItem;

/**
 * A short quote or original card followed by a comprehension question.
 *
 * payload:  { "card": { "text": "...", "author": "Seneca", "source": "Letters to Lucilius" },
 *             "question": "What is the main idea?", "choices": [{ "id": "a", "text": "..." }, …] }
 * response: { "choice_id": "b" }
 */
class WisdomCard extends ChoiceChallenge
{
    public function key(): string
    {
        return 'wisdom_card';
    }

    public function name(): string
    {
        return 'Wisdom card';
    }

    public function validate(array $payload, array $answer): array
    {
        $errors = [];
        $card = $payload['card'] ?? null;

        if (! is_array($card) || ! is_string($card['text'] ?? null) || trim($card['text']) === '') {
            $errors['payload.card.text'][] = 'The card needs a text.';
        }

        foreach (['author', 'source'] as $field) {
            if (is_array($card) && isset($card[$field]) && ! is_string($card[$field])) {
                $errors["payload.card.{$field}"][] = "The card {$field} must be text or null.";
            }
        }

        if (! is_string($payload['question'] ?? null) || trim($payload['question']) === '') {
            $errors['payload.question'][] = 'A question is required.';
        }

        return array_merge_recursive($errors, $this->validateChoices($payload, $answer));
    }

    public function forClient(ContentItem $item): array
    {
        $card = (array) ($item->payload['card'] ?? []);

        return [
            'card' => [
                'text' => (string) ($card['text'] ?? ''),
                'author' => isset($card['author']) && $card['author'] !== '' ? (string) $card['author'] : null,
                'source' => isset($card['source']) && $card['source'] !== '' ? (string) $card['source'] : null,
            ],
            'question' => (string) ($item->payload['question'] ?? ''),
            'choices' => $this->clientChoices($item),
        ];
    }

    public function fromFlatRow(array $row): array
    {
        $parsed = $this->choicesFromRow($row);
        $author = trim((string) ($row['card_author'] ?? ''));
        $source = trim((string) ($row['card_source'] ?? ''));
        $question = trim((string) ($row['question'] ?? ''));

        return [
            'payload' => [
                'card' => [
                    'text' => trim((string) ($row['card_text'] ?? '')),
                    'author' => $author !== '' ? $author : null,
                    'source' => $source !== '' ? $source : null,
                ],
                'question' => $question !== '' ? $question : 'What is the main idea?',
                'choices' => $parsed['choices'],
            ],
            'answer' => $parsed['answer'],
        ];
    }

    public function flatColumns(): array
    {
        return ['card_text', 'card_author', 'card_source', 'question', 'choice_a', 'choice_b', 'choice_c', 'correct', 'explanation'];
    }
}

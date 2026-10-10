<?php

namespace App\Modules\ChallengeTypes\Contracts;

use App\Modules\Content\Models\ContentItem;

/**
 * A challenge type plugin. Adding a new game = one class implementing this interface
 * (registered in config/challenge_types.php) + one React Native component. No schema change.
 */
interface ChallengeType
{
    /** The key sent to the app as `type` (e.g. "multiple_choice"). */
    public function key(): string;

    /** Human-readable name for the admin. */
    public function name(): string;

    /**
     * Validate an item's payload and answer. Returns field errors (empty array = valid),
     * keyed like "payload.question" or "answer.choice_id".
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answer
     * @return array<string, list<string>>
     */
    public function validate(array $payload, array $answer): array;

    /**
     * The payload as the app receives it: never contains the answer.
     *
     * @return array<string, mixed>
     */
    public function forClient(ContentItem $item): array;

    /**
     * Server-side check of a member's response.
     *
     * @param  array<string, mixed>  $response
     */
    public function check(ContentItem $item, array $response): bool;

    /**
     * What the app may show after answering.
     *
     * @return array<string, mixed>
     */
    public function correctResponse(ContentItem $item): array;

    public function explanation(ContentItem $item): ?string;

    /** Points for an answer (1 per correct answer for the launch types). */
    public function score(ContentItem $item, bool $isCorrect): int;

    /**
     * Build payload + answer from a flat CSV row (column name => value) for the importer.
     *
     * @param  array<string, mixed>  $row
     * @return array{payload: array<string, mixed>, answer: array<string, mixed>}
     */
    public function fromFlatRow(array $row): array;

    /**
     * CSV columns this type reads in fromFlatRow(), for the importer's help text.
     *
     * @return list<string>
     */
    public function flatColumns(): array;
}

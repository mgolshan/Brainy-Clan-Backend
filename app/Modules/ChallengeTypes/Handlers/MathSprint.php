<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Contracts\ChallengeType;
use App\Modules\ChallengeTypes\Contracts\GeneratesItems;
use App\Modules\ChallengeTypes\Generators\MathSprintGenerator;
use App\Modules\ChallengeTypes\Support\Rational;
use App\Modules\Content\Models\ContentItem;

/**
 * A quick math question with a typed answer, generated per session (times tables, mental math,
 * fractions), age-scaled through the difficulty.
 *
 * payload:  { "question": "7 × 8", "answer_format": "integer" | "decimal" | "fraction", "time_limit_ms": 10000 | null }
 * answer:   { "value": "56", "allow_decimal": false, "reduced_only": true (optional) }
 * response: { "value": "56" }
 *
 * Equivalent answers count: "3/4" = "6/8" = "0.75" (decimals for a fraction answer only when
 * `allow_decimal`), "1 1/4" = "5/4", "56" = "56.0". "Simplify …" questions set `reduced_only`:
 * then only the fully reduced fraction counts ("7/8", not "14/16" or "0.875"). Generator options: `{ "mode": "times_tables" | "mental" | "fractions" }`.
 */
class MathSprint implements ChallengeType, GeneratesItems
{
    public const array FORMATS = ['integer', 'decimal', 'fraction'];

    public function __construct(private readonly MathSprintGenerator $generator) {}

    public function key(): string
    {
        return 'math_sprint';
    }

    public function name(): string
    {
        return 'Math sprint';
    }

    public function validate(array $payload, array $answer): array
    {
        $errors = [];

        if (! is_string($payload['question'] ?? null) || trim($payload['question']) === '') {
            $errors['payload.question'][] = 'A question is required.';
        }

        if (! in_array($payload['answer_format'] ?? null, self::FORMATS, true)) {
            $errors['payload.answer_format'][] = 'The answer format must be integer, decimal or fraction.';
        }

        $limit = $payload['time_limit_ms'] ?? null;

        if ($limit !== null && (! is_int($limit) || $limit < 1000 || $limit > 600000)) {
            $errors['payload.time_limit_ms'][] = 'The time limit must be null or 1000–600000 ms.';
        }

        if (! is_string($answer['value'] ?? null) || Rational::parse($answer['value']) === null) {
            $errors['answer.value'][] = 'The answer must be a number ("56", "0.75" or "3/4").';
        }

        if (isset($answer['allow_decimal']) && ! is_bool($answer['allow_decimal'])) {
            $errors['answer.allow_decimal'][] = 'allow_decimal must be true or false.';
        }

        if (isset($answer['reduced_only']) && ! is_bool($answer['reduced_only'])) {
            $errors['answer.reduced_only'][] = 'reduced_only must be true or false.';
        }

        return $errors;
    }

    public function forClient(ContentItem $item): array
    {
        $limit = $item->payload['time_limit_ms'] ?? null;

        return [
            'question' => (string) ($item->payload['question'] ?? ''),
            'answer_format' => (string) ($item->payload['answer_format'] ?? 'integer'),
            'time_limit_ms' => is_int($limit) ? $limit : null,
        ];
    }

    public function validateResponse(ContentItem $item, array $response): array
    {
        $value = $response['value'] ?? null;

        if ((! is_string($value) && ! is_int($value)) || Rational::parse((string) $value) === null) {
            return ['response.value' => ['Type a number, e.g. "56", "0.75" or "3/4".']];
        }

        return [];
    }

    public function check(ContentItem $item, array $response): bool
    {
        $value = $response['value'] ?? null;
        $given = is_string($value) || is_int($value) ? Rational::parse((string) $value) : null;
        $expected = Rational::parse((string) ($item->answer['value'] ?? ''));

        if ($given === null || $expected === null || ! $given['value']->equals($expected['value'])) {
            return false;
        }

        $decimalForFraction = $given['notation'] === 'decimal'
            && ($item->payload['answer_format'] ?? null) === 'fraction'
            && ($item->answer['allow_decimal'] ?? false) !== true;

        if (($item->answer['reduced_only'] ?? false) === true && ($given['notation'] === 'decimal' || ! $given['reduced'])) {
            return false;
        }

        return ! $decimalForFraction;
    }

    public function correctResponse(ContentItem $item): array
    {
        return ['value' => (string) ($item->answer['value'] ?? '')];
    }

    public function explanation(ContentItem $item): ?string
    {
        return null;
    }

    public function points(ContentItem $item): int
    {
        return 1;
    }

    public function generate(string $seed, int $difficulty, array $options): array
    {
        $question = $this->generator->generate($seed, $this->modeOption($options), $difficulty);
        $value = $question['value'];
        $canonical = $question['answer_format'] === 'decimal' ? $value->toDecimalString() : $value->toFractionString();

        return [
            'payload' => [
                'question' => $question['question'],
                'answer_format' => $question['answer_format'],
                'time_limit_ms' => $question['time_limit_ms'],
            ],
            'answer' => [
                'value' => $canonical,
                'allow_decimal' => ! $question['reduced_only'] && ($question['answer_format'] !== 'fraction' || $value->isTerminatingDecimal()),
                ...($question['reduced_only'] ? ['reduced_only' => true] : []),
            ],
        ];
    }

    public function pointsFor(array $options, int $difficulty): int
    {
        return 1;
    }

    public function validateOptions(array $options): array
    {
        return in_array($options['mode'] ?? null, MathSprintGenerator::MODES, true)
            ? []
            : ['options.mode' => ['The mode must be one of: '.implode(', ', MathSprintGenerator::MODES).'.']];
    }

    public function fromFlatRow(array $row): array
    {
        $format = trim((string) ($row['answer_format'] ?? ''));
        $limit = trim((string) ($row['time_limit_ms'] ?? ''));

        return [
            'payload' => [
                'question' => trim((string) ($row['question'] ?? '')),
                'answer_format' => $format !== '' ? $format : 'integer',
                'time_limit_ms' => $limit !== '' ? (int) $limit : null,
            ],
            'answer' => [
                'value' => trim((string) ($row['answer'] ?? '')),
                'allow_decimal' => in_array(strtolower(trim((string) ($row['allow_decimal'] ?? ''))), ['1', 'true', 'yes'], true),
            ],
        ];
    }

    public function flatColumns(): array
    {
        return ['question', 'answer', 'answer_format', 'time_limit_ms', 'allow_decimal'];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function modeOption(array $options): string
    {
        $mode = $options['mode'] ?? null;

        return in_array($mode, MathSprintGenerator::MODES, true) ? $mode : 'times_tables';
    }
}

<?php

namespace App\Modules\Content\Generation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Laravel AI SDK agent that writes one batch of quiz items as structured output
 * ({"items": [...]}, item schema per generation type). Provider and model come from
 * config/content_generator.php; tests replace it with `ContentBatchAgent::fake([...])`.
 */
class ContentBatchAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private readonly string $generationType,
        private readonly string $systemPrompt,
        private readonly GenerationTypes $types,
    ) {}

    /** Whether a provider API key is set (otherwise the admin page shows "not configured"). */
    public static function isConfigured(): bool
    {
        if (self::isFaked()) {
            return true;
        }

        $provider = (string) config('content_generator.provider', 'anthropic');

        return filled(config("ai.providers.{$provider}.key"));
    }

    public function instructions(): string
    {
        return $this->systemPrompt;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items($schema->object($this->types->itemSchema($this->generationType, $schema)))
                ->required(),
        ];
    }

    public function provider(): string
    {
        return (string) config('content_generator.provider', 'anthropic');
    }

    public function model(): string
    {
        return (string) config('content_generator.model', 'claude-sonnet-5-5');
    }

    public function timeout(): int
    {
        return (int) config('content_generator.timeout', 300);
    }

    public function maxTokens(): int
    {
        return (int) config('content_generator.max_tokens', 20000);
    }
}

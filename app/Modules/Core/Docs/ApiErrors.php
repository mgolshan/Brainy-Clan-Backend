<?php

namespace App\Modules\Core\Docs;

use Attribute;

/**
 * Documents the domain error codes an endpoint can answer with (docs/API.md "Error codes").
 * Read by `ErrorResponsesExtension`, which adds them to the Scramble docs in the contract shape
 * `{ message, code, errors? }`. Codes implied by middleware (`unauthenticated`, `no_family`,
 * `forbidden` for admin routes, `not_found` for path ids, `rate_limited`, `app_update_required`)
 * and `validation_failed` for Form Requests are added automatically.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiErrors
{
    /** @var list<string> */
    public array $codes;

    public function __construct(string ...$codes)
    {
        $this->codes = array_values($codes);
    }
}

<?php

namespace App\Modules\Content\Actions;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Content\Models\ContentItem;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a content item after the challenge type plugin validated its
 * payload and answer. Used by the admin, the CSV importer and seeders. Translations of one
 * item share a `translation_group`; `cross_locale` = false keeps an English item from members
 * of other locales (see ContentItem::whereAvailableInLocale).
 */
class SaveContentItem
{
    public function __construct(private readonly ChallengeTypeRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, ?ContentItem $item = null): ContentItem
    {
        $item ??= new ContentItem;
        $item->fill($data);

        if (blank($item->translation_group)) {
            $item->translation_group = null;
        }

        $this->assertValid($item);

        if ($item->status === ContentItem::STATUS_PUBLISHED && $item->published_at === null) {
            $item->published_at = now();
        }

        $item->save();

        return $item;
    }

    /**
     * @throws ValidationException
     */
    public function assertValid(ContentItem $item): void
    {
        if (! $this->registry->has($item->type_key)) {
            throw ValidationException::withMessages(['type_key' => "Unknown challenge type [{$item->type_key}]."]);
        }

        if ($item->difficulty < 1 || $item->difficulty > 10) {
            throw ValidationException::withMessages(['difficulty' => 'Difficulty must be between 1 and 10.']);
        }

        $errors = $this->registry->get($item->type_key)->validate((array) $item->payload, (array) $item->answer);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

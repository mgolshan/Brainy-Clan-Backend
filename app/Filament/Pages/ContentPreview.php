<?php

namespace App\Filament\Pages;

use App\Modules\Content\Actions\PreviewContentItem;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Policy\Models\AgeBand;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Shows an item exactly as a member of the chosen age band would receive it: the client payload
 * (no answer), a rough rendering, the answer the app gets after answering, and whether the item
 * would be served to that band at all.
 */
class ContentPreview extends Page
{
    protected string $view = 'filament.pages.content-preview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Content preview';

    #[Url]
    public ?int $item = null;

    #[Url]
    public string $band = 'child';

    #[Url]
    public ?int $age = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $bands = AgeBand::query()->whereNull('country_code')->orderBy('min_age')->get();
        $band = $bands->firstWhere('code', $this->band) ?? $bands->first();
        $item = $this->item !== null ? ContentItem::query()->with('category')->find($this->item) : null;

        return [
            'bands' => $bands,
            'record' => $item,
            'preview' => $item !== null && $band !== null
                ? app(PreviewContentItem::class)->handle($item, $band, $this->age ?? $band->min_age + 1)
                : null,
            'json' => fn (mixed $value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    public function randomItem(): void
    {
        $this->item = ContentItem::query()->published()->inRandomOrder()->value('id');
    }
}

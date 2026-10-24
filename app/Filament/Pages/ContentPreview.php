<?php

namespace App\Filament\Pages;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\ChallengeTypes\Contracts\GeneratesItems;
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
 * would be served to that band at all. Generated types (Sudoku, Math Sprint) can be previewed
 * as a sample: pick a generator, a difficulty and a seed.
 */
class ContentPreview extends Page
{
    /** Generator samples: key => [type, options, label]. */
    public const array SAMPLES = [
        'sudoku-4' => ['sudoku', ['size' => 4], 'Sudoku 4×4'],
        'sudoku-6' => ['sudoku', ['size' => 6], 'Sudoku 6×6'],
        'sudoku-9' => ['sudoku', ['size' => 9], 'Sudoku 9×9'],
        'math-times_tables' => ['math_sprint', ['mode' => 'times_tables'], 'Math: times tables'],
        'math-mental' => ['math_sprint', ['mode' => 'mental'], 'Math: mental math'],
        'math-fractions' => ['math_sprint', ['mode' => 'fractions'], 'Math: fractions'],
        'memory_match' => ['memory_match', [], 'Brain games: memory match'],
        'pattern_sequence' => ['pattern_sequence', [], 'Brain games: pattern sequence'],
        'nonogram' => ['nonogram', [], 'Brain games: nonogram'],
        'spot_difference' => ['spot_difference', [], 'Brain games: spot the difference'],
    ];

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

    #[Url]
    public ?string $sample = null;

    #[Url]
    public int $difficulty = 3;

    #[Url]
    public string $seed = 'preview';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $bands = AgeBand::query()->whereNull('country_code')->orderBy('min_age')->get();
        $band = $bands->firstWhere('code', $this->band) ?? $bands->first();
        $item = $this->sample !== null && $this->sample !== ''
            ? $this->sampleItem()
            : ($this->item !== null ? ContentItem::query()->with('category')->find($this->item) : null);

        return [
            'bands' => $bands,
            'samples' => self::SAMPLES,
            'record' => $item,
            'preview' => $item !== null && $band !== null
                ? app(PreviewContentItem::class)->handle($item, $band, $this->age ?? $band->min_age + 1)
                : null,
            'json' => fn (mixed $value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    public function randomItem(): void
    {
        $this->sample = null;
        $this->item = ContentItem::query()->published()->inRandomOrder()->value('id');
    }

    /**
     * An unsaved item built by the generator, like a session would build it.
     */
    private function sampleItem(): ?ContentItem
    {
        [$type, $options] = self::SAMPLES[$this->sample] ?? [null, []];
        $registry = app(ChallengeTypeRegistry::class);
        $handler = $type !== null && $registry->has($type) ? $registry->get($type) : null;

        if (! $handler instanceof GeneratesItems) {
            return null;
        }

        $difficulty = max(1, min(10, $this->difficulty));
        $built = $handler->generate($this->seed, $difficulty, $options);

        return (new ContentItem)->forceFill([
            'type_key' => $type,
            'difficulty' => $difficulty,
            'payload' => $built['payload'],
            'answer' => $built['answer'],
            'status' => ContentItem::STATUS_PUBLISHED,
            'source' => 'generated',
        ]);
    }
}

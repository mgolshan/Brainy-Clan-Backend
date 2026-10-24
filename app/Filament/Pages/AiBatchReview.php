<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Support\StaffAccess;
use App\Modules\Content\Actions\PreviewContentItem;
use App\Modules\Content\Actions\ReviewContentBatchItems;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Models\AgeBand;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/**
 * Review one AI batch: every draft rendered as the app shows it (shared phone preview), then
 * approve (publish via ChangeContentStatus, reviewed_by set), edit (content item form) or reject
 * (archive), one by one or in bulk. The batch turns `done` when no drafts are left.
 */
class AiBatchReview extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.ai-batch-review';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'ai-batch-review';

    /**
     * The AI generator costs money and publishes content: admins only (403 and hidden for `support`).
     */
    public static function canAccess(): bool
    {
        return StaffAccess::isAdmin();
    }

    #[Url]
    public ?int $batch = null;

    public ?ContentBatch $record = null;

    private ?AgeBand $band = null;

    public function mount(): void
    {
        $this->record = ContentBatch::query()->with(['category', 'creator'])->find($this->batch);

        abort_if($this->record === null, 404);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Review batch #'.$this->batch;
    }

    public function categoryPath(): string
    {
        $categoryId = $this->record?->category_id;

        return $categoryId !== null ? implode(' → ', app(CategoryTree::class)->pathNames($categoryId)) : '—';
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(ContentItem $item): array
    {
        $this->band ??= $this->reviewBand();

        return app(PreviewContentItem::class)->handle($item, $this->band, $this->record->min_age ?? $this->band->min_age + 1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ContentItem::query()
                ->with(['category'])
                ->where('content_batch_id', $this->batch))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('id')->label('#'),
                ViewColumn::make('preview')
                    ->view('filament.tables.columns.content-phone')
                    ->state(fn (ContentItem $record) => $this->preview($record))
                    ->width('380px'),
                TextColumn::make('explanation')->label('Explanation')->wrap()
                    ->state(fn (ContentItem $record) => $record->answer['explanation'] ?? null)->placeholder('—'),
                TextColumn::make('difficulty'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    ContentItem::STATUS_PUBLISHED => 'success',
                    ContentItem::STATUS_ARCHIVED => 'gray',
                    default => 'info',
                }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    ContentItem::STATUS_DRAFT => 'To review (draft)',
                    ContentItem::STATUS_PUBLISHED => 'Approved (published)',
                    ContentItem::STATUS_ARCHIVED => 'Rejected (archived)',
                ])->default(ContentItem::STATUS_DRAFT),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (ContentItem $record) => $record->status === ContentItem::STATUS_DRAFT)
                    ->action(fn (ContentItem $record) => $this->decide([$record], ReviewContentBatchItems::APPROVE)),
                Action::make('edit')
                    ->icon(Heroicon::OutlinedPencilSquare)->color('gray')
                    ->url(fn (ContentItem $record) => ContentItemResource::getUrl('edit', ['record' => $record])),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (ContentItem $record) => $record->status === ContentItem::STATUS_DRAFT)
                    ->action(fn (ContentItem $record) => $this->decide([$record], ReviewContentBatchItems::REJECT)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')->label('Approve selected')
                        ->icon(Heroicon::OutlinedCheckCircle)->color('success')->requiresConfirmation()
                        ->action(fn (Collection $records) => $this->decide($records, ReviewContentBatchItems::APPROVE))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject_selected')->label('Reject selected')
                        ->icon(Heroicon::OutlinedXCircle)->color('danger')->requiresConfirmation()
                        ->action(fn (Collection $records) => $this->decide($records, ReviewContentBatchItems::REJECT))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('All batches')->color('gray')->icon(Heroicon::OutlinedArrowLeft)
                ->url(AiBatchGenerator::getUrl()),
            Action::make('approve_all')->label('Approve all drafts')
                ->icon(Heroicon::OutlinedCheckBadge)->color('success')->requiresConfirmation()
                ->modalDescription('Publishes every remaining draft of this batch. Review them first.')
                ->action(fn () => $this->decide(
                    ContentItem::query()->where('content_batch_id', $this->batch)->where('status', ContentItem::STATUS_DRAFT)->get(),
                    ReviewContentBatchItems::APPROVE,
                )),
        ];
    }

    /**
     * @param  iterable<mixed>  $items  table records (only ContentItems are used)
     */
    private function decide(iterable $items, string $decision): void
    {
        $reviewer = Auth::user();

        if ($this->record === null || ! $reviewer instanceof User) {
            return;
        }

        $contentItems = collect($items)->filter(fn ($item) => $item instanceof ContentItem)->values()->all();
        $result = app(ReviewContentBatchItems::class)->handle($this->record, $contentItems, $decision, $reviewer);
        $verb = $decision === ReviewContentBatchItems::APPROVE ? 'approved' : 'rejected';
        $notification = Notification::make()->title("{$result['changed']} item(s) {$verb}");

        if ($result['skipped'] !== []) {
            $notification->body('Skipped (invalid payload, edit them first): #'.implode(', #', $result['skipped']))->warning();
        } else {
            $notification->success();
        }

        $notification->send();
        $this->record->refresh();
    }

    /**
     * The global age band containing the batch's minimum age (or the youngest band).
     */
    private function reviewBand(): AgeBand
    {
        $bands = AgeBand::query()->whereNull('country_code')->orderBy('min_age')->get();
        $age = $this->record?->min_age;

        return ($age !== null ? $bands->last(fn (AgeBand $band) => $band->min_age <= $age) : null)
            ?? $bands->first()
            ?? new AgeBand(['code' => 'adult', 'name' => 'Adult', 'min_age' => 18, 'policy' => []]);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AiBatchGenerator;
use App\Filament\Pages\AiBatchReview;
use App\Filament\Support\StaffAccess;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Support\CategoryTree;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * AI batches whose items wait for a reviewer, oldest first (one query; categories come from the
 * cached category tree).
 */
class BatchesAwaitingReview extends TableWidget
{
    public const int LIMIT = 8;

    protected static ?int $sort = 8;

    /**
     * AI generator workflow: admins only (the `support` role has no access to the AI pages).
     */
    public static function canView(): bool
    {
        return StaffAccess::isAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('AI batches awaiting review')
            ->query(fn (): Builder => ContentBatch::query()
                ->where('status', ContentBatch::STATUS_REVIEW)
                ->withCount('draftItems')
                ->oldest('id')
                ->limit(self::LIMIT))
            ->paginated(false)
            ->poll('60s')
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('category_id')->label('Category')->wrap()
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? implode(' → ', app(CategoryTree::class)->pathNames($state)) : '—'),
                TextColumn::make('type_key')->label('Type')->badge()->state(fn (ContentBatch $record): string => $record->typeLabel()),
                TextColumn::make('locale'),
                TextColumn::make('draft_items_count')->label('To review')->numeric(),
                TextColumn::make('created_at')->label('Created')->since()->dateTimeTooltip(),
            ])
            ->recordUrl(fn (ContentBatch $record): string => AiBatchReview::getUrl(['batch' => $record->id]))
            ->headerActions([
                Action::make('allBatches')->label('AI generator')->link()->url(AiBatchGenerator::getUrl()),
            ])
            ->emptyStateHeading('Nothing to review')
            ->emptyStateDescription('Generated batches show up here until every item is approved or rejected.');
    }
}

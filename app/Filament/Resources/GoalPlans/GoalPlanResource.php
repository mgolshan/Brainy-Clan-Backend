<?php

namespace App\Filament\Resources\GoalPlans;

use App\Filament\Resources\GoalPlans\Pages\ManageGoalPlans;
use App\Filament\Resources\GoalPlans\Pages\ViewGoalPlan;
use App\Filament\Resources\GoalPlans\RelationManagers\PeriodsRelationManager;
use App\Filament\Support\ReadOnlyForSupport;
use App\Filament\Support\StaffAccess;
use App\Modules\Goals\Actions\SaveGoalPlan;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Goals\Models\GoalPlanMember;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Read-mostly support view of family goal plans. Families manage their own plans in the app;
 * admins can view periods/results (deleted plans too, via the filter) and pause a plan.
 */
class GoalPlanResource extends Resource
{
    use ReadOnlyForSupport;

    protected static ?string $model = GoalPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Families';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('family.name')->label('Family'),
            TextEntry::make('name'),
            IconEntry::make('is_active')->boolean()->label('Active'),
            TextEntry::make('mode')->badge(),
            TextEntry::make('period_type')->label('Period')->badge(),
            TextEntry::make('target_points')->label('Target')->numeric(),
            TextEntry::make('reward_text')->label('Reward')->placeholder('—'),
            TextEntry::make('starts_on')->date(),
            TextEntry::make('ends_on')->date()->placeholder('open-ended'),
            TextEntry::make('categories.slug')->label('Scopes')->badge(),
            TextEntry::make('members')->label('Members')->listWithLineBreaks()
                ->state(fn (GoalPlan $record): array => $record->planMembers
                    ->map(fn (GoalPlanMember $m) => $m->member->nickname.($m->target_override !== null ? " (target {$m->target_override})" : ''))
                    ->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['family', 'categories']))
            ->columns([
                TextColumn::make('family.name')->label('Family')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('mode')->badge(),
                TextColumn::make('period_type')->label('Period')->badge(),
                TextColumn::make('target_points')->label('Target')->numeric()->sortable(),
                TextColumn::make('reward_text')->label('Reward')->limit(30)->toggleable(),
                TextColumn::make('categories.slug')->label('Scopes')->badge()->toggleable(),
                TextColumn::make('plan_members_count')->counts('planMembers')->label('Members'),
                TextColumn::make('periods_count')->counts('periods')->label('Periods'),
                TextColumn::make('starts_on')->date()->sortable(),
                TextColumn::make('ends_on')->date()->placeholder('open-ended'),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('deleted_at')->label('Deleted')->dateTime()->placeholder('—')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                TrashedFilter::make()->label('Deleted by the family'),
                SelectFilter::make('mode')->options(array_combine(GoalPlan::MODES, GoalPlan::MODES)),
                SelectFilter::make('period_type')->label('Period')->options(array_combine(GoalPlan::PERIOD_TYPES, GoalPlan::PERIOD_TYPES)),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('deactivate')
                    ->icon(Heroicon::OutlinedPause)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (GoalPlan $record): bool => $record->is_active && ! $record->trashed())
                    ->authorize(fn (): bool => StaffAccess::isAdmin())
                    ->action(fn (GoalPlan $record) => app(SaveGoalPlan::class)->deactivate($record)),
            ]);
    }

    /**
     * Support can open plans a family deleted (soft delete) through the "Deleted" filter.
     *
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            PeriodsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGoalPlans::route('/'),
            'view' => ViewGoalPlan::route('/{record}'),
        ];
    }
}

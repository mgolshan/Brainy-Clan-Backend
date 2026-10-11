<?php

namespace App\Filament\Resources\GoalPlans;

use App\Filament\Resources\GoalPlans\Pages\ManageGoalPlans;
use App\Modules\Goals\Actions\SaveGoalPlan;
use App\Modules\Goals\Models\GoalPlan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-mostly support view of family goal plans. Families manage their own plans in the app;
 * admins can only deactivate (through the same Action the API uses).
 */
class GoalPlanResource extends Resource
{
    protected static ?string $model = GoalPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Families';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
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
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                SelectFilter::make('mode')->options(array_combine(GoalPlan::MODES, GoalPlan::MODES)),
                SelectFilter::make('period_type')->label('Period')->options(array_combine(GoalPlan::PERIOD_TYPES, GoalPlan::PERIOD_TYPES)),
            ])
            ->recordActions([
                Action::make('deactivate')
                    ->icon(Heroicon::OutlinedPause)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (GoalPlan $record): bool => $record->is_active)
                    ->action(fn (GoalPlan $record) => app(SaveGoalPlan::class)->deactivate($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGoalPlans::route('/'),
        ];
    }
}

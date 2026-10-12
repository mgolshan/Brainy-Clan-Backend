<?php

namespace App\Filament\Resources\GoalPlans\RelationManagers;

use App\Modules\Goals\Models\GoalPeriod;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Periods of a goal plan with their results (support view). Closed periods show the stored
 * result; open periods are computed live in the app, so nothing is queried per row here.
 */
class PeriodsRelationManager extends RelationManager
{
    protected static string $relationship = 'periods';

    protected static ?string $title = 'Periods and results';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('starts_at')
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')->dateTime()->sortable(),
                TextColumn::make('ends_at')->label('Ends (exclusive)')->dateTime(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => $state === GoalPeriod::STATUS_OPEN ? 'info' : 'gray'),
                TextColumn::make('target_points')->label('Target')->numeric(),
                TextColumn::make('team_total')->label('Team total')->placeholder('open')
                    ->state(fn (GoalPeriod $record): ?int => isset($record->result['team_total']) ? (int) $record->result['team_total'] : null),
                IconColumn::make('reached')->boolean()
                    ->state(fn (GoalPeriod $record): ?bool => isset($record->result['reached']) ? (bool) $record->result['reached'] : null),
                TextColumn::make('winners')->placeholder('—')->listWithLineBreaks()
                    ->state(fn (GoalPeriod $record): array => self::winnerNames($record)),
                TextColumn::make('scores')->label('Points')->listWithLineBreaks()->placeholder('—')->toggleable()
                    ->state(fn (GoalPeriod $record): array => array_map(
                        fn (array $entry) => "#{$entry['rank']} {$entry['nickname']}: {$entry['points']}/{$entry['target']}",
                        (array) ($record->result['entries'] ?? []),
                    )),
                TextColumn::make('closed_at')->dateTime()->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([GoalPeriod::STATUS_OPEN => 'open', GoalPeriod::STATUS_CLOSED => 'closed']),
            ]);
    }

    /**
     * Nicknames of the winners, from the stored result (no queries).
     *
     * @return list<string>
     */
    private static function winnerNames(GoalPeriod $record): array
    {
        $winners = (array) ($record->result['winner_member_ids'] ?? []);

        return array_values(array_map(
            fn (array $entry) => (string) $entry['nickname'],
            array_filter((array) ($record->result['entries'] ?? []), fn (array $entry) => in_array($entry['member_id'], $winners, true)),
        ));
    }
}

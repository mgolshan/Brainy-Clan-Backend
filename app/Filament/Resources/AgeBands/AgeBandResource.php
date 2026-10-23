<?php

namespace App\Filament\Resources\AgeBands;

use App\Filament\Resources\AgeBands\Pages\CreateAgeBand;
use App\Filament\Resources\AgeBands\Pages\EditAgeBand;
use App\Filament\Resources\AgeBands\Pages\ListAgeBands;
use App\Modules\Policy\Models\AgeBand;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Age bands and what each band allows. Changes apply immediately (no release):
 * the PolicyResolver cache is flushed when a band is saved.
 */
class AgeBandResource extends Resource
{
    protected static ?string $model = AgeBand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Policy';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Band')->columns(5)->schema([
                TextInput::make('code')->required()->maxLength(30)->helperText('child, teen, adult, senior …'),
                TextInput::make('name')->required()->maxLength(60),
                TextInput::make('min_age')->required()->numeric()->minValue(0)->maxValue(120),
                TextInput::make('max_age')->numeric()->minValue(0)->maxValue(120)->helperText('Empty = no upper limit'),
                TextInput::make('country_code')->length(2)->helperText('Empty = every country'),
            ]),
            Section::make('Rules')->columns(3)->schema([
                Select::make('policy.ui_theme')->label('Default UI theme')->options(AgeBand::THEME_LABELS)->required(),
                Toggle::make('policy.is_child')->label('Child (under the digital-consent threshold)'),
                Toggle::make('policy.collect_email')->label('May self-register with email / password'),
                Toggle::make('policy.email_login_allowed')->label('Admins may give an email login')
                    ->helperText('A family admin can add an email + password sign-in for members of this band.'),
                Toggle::make('policy.ads_allowed')->label('Rewarded ads allowed (free tier)'),
                Toggle::make('policy.purchases_allowed')->label('In-app purchases allowed'),
                Toggle::make('policy.redemption_allowed')->label('Redemption round allowed')->default(true),
            ]),
            Section::make('Ad requests (AdMob)')->columns(3)
                ->description('How the app must request rewarded ads for this band (sent to the app as policy.ad_request).')
                ->schema([
                    Toggle::make('policy.ad_request.non_personalized')->label('Non-personalized ads only')->default(true),
                    Toggle::make('policy.ad_request.under_age_of_consent')->label('Tag for under age of consent'),
                    Select::make('policy.ad_request.max_ad_content_rating')->label('Max ad content rating')
                        ->options(array_combine(AgeBand::AD_CONTENT_RATINGS, AgeBand::AD_CONTENT_RATINGS))->default('G')->required(),
                ]),
            Section::make('Difficulty (1–10)')->columns(3)->schema([
                TextInput::make('policy.difficulty.min')->label('Min')->numeric()->minValue(1)->maxValue(10)->required(),
                TextInput::make('policy.difficulty.max')->label('Max')->numeric()->minValue(1)->maxValue(10)->required(),
                TextInput::make('policy.difficulty.default')->label('Default')->numeric()->minValue(1)->maxValue(10)->required(),
            ]),
            Section::make('Accessibility defaults')->columns(4)->schema([
                TextInput::make('policy.accessibility_defaults.text_scale')->label('Text scale')->numeric()->minValue(0.8)->maxValue(1.6)->step(0.1)->default(1.0),
                Toggle::make('policy.accessibility_defaults.reduced_motion')->label('Reduced motion'),
                Toggle::make('policy.accessibility_defaults.calm_timers')->label('Calm timers'),
                Toggle::make('policy.accessibility_defaults.sound_effects')->label('Sound effects')
                    ->helperText('Default for members who have not chosen (app sound effects on or off).'),
            ]),
            Section::make('Data rules')->schema([
                Grid::make(3)->schema([
                    Toggle::make('policy.data_rules.analytics')->label('Analytics'),
                    Toggle::make('policy.data_rules.personalized_ads')->label('Personalized ads'),
                    Toggle::make('policy.data_rules.real_name')->label('Real name'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('min_age')
            ->columns([
                TextColumn::make('code')->searchable()->badge(),
                TextColumn::make('name'),
                TextColumn::make('min_age')->label('Ages')->formatStateUsing(fn (AgeBand $record) => $record->min_age.'–'.($record->max_age ?? '∞')),
                TextColumn::make('country_code')->placeholder('All'),
                TextColumn::make('policy.ui_theme')->label('Theme')->formatStateUsing(fn (?string $state): string => AgeBand::THEME_LABELS[(string) $state] ?? (string) $state),
                IconColumn::make('policy.is_child')->label('Child')->boolean(),
                IconColumn::make('policy.ads_allowed')->label('Ads')->boolean(),
                IconColumn::make('policy.collect_email')->label('Self sign-up')->boolean(),
                IconColumn::make('policy.email_login_allowed')->label('Email login')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgeBands::route('/'),
            'create' => CreateAgeBand::route('/create'),
            'edit' => EditAgeBand::route('/{record}/edit'),
        ];
    }
}

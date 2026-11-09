<?php

namespace App\Filament\Resources\SupportMessages;

use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Resources\SupportMessages\Pages\ListSupportMessages;
use App\Filament\Resources\SupportMessages\Pages\ViewSupportMessage;
use App\Filament\Support\ReadOnlyForSupport;
use App\Filament\Support\StaffAccess;
use App\Modules\Support\Actions\ChangeSupportMessageStatus;
use App\Modules\Support\Actions\ReplyToSupportMessage;
use App\Modules\Support\Actions\SupportInboxStats;
use App\Modules\Support\Models\SupportMessage;
use App\Modules\Support\Models\SupportMessageReply;
use App\Modules\Support\Support\SupportText;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * The "Support inbox" (docs/PLAN.md "Support channel without a public mailto"): messages from
 * family admins in the app and from the website's contact form. Both staff roles read and answer
 * them (Reply, Mark open, Close); only admins delete. Viewing a `new` message marks it `open`.
 * Everything people typed is escaped (`e()`); nothing is rendered as HTML or Markdown.
 *
 * The list eager-loads the user (reply address of app messages) and the family, and counts the
 * replies, so it runs a fixed number of queries.
 */
class SupportMessageResource extends Resource
{
    use ReadOnlyForSupport;

    protected static ?string $model = SupportMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Support inbox';

    protected static ?string $modelLabel = 'support message';

    public const array STATUS_LABELS = [
        SupportMessage::STATUS_NEW => 'New (unread)',
        SupportMessage::STATUS_OPEN => 'Open',
        SupportMessage::STATUS_ANSWERED => 'Answered',
        SupportMessage::STATUS_CLOSED => 'Closed',
    ];

    public const array SOURCE_LABELS = [
        SupportMessage::SOURCE_APP => 'App',
        SupportMessage::SOURCE_WEB => 'Website',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Unread messages, for the navigation badge (one COUNT query).
     */
    public static function getNavigationBadge(): ?string
    {
        $new = app(SupportInboxStats::class)->newCount();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'New support messages';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Message')->columns(3)->schema([
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextEntry::make('topic')->formatStateUsing(fn (string $state): string => SupportMessage::topicLabel($state)),
                TextEntry::make('source')->badge()->formatStateUsing(fn (string $state): string => self::SOURCE_LABELS[$state] ?? $state),
                TextEntry::make('name')->label('From')->placeholder('—'),
                TextEntry::make('reply_to')->label('Reply goes to')->copyable()
                    ->state(fn (SupportMessage $record): ?string => $record->replyEmail())
                    ->placeholder('No email address — cannot reply'),
                TextEntry::make('locale')->label('Language')->formatStateUsing(fn (string $state): string => $state === 'fr' ? 'French' : 'English'),
                TextEntry::make('body')->label('Message')->columnSpanFull()
                    ->state(fn (SupportMessage $record): string => $record->message)
                    ->formatStateUsing(fn (string $state): HtmlString => self::plainText($state)),
            ]),
            Section::make('Details')->columns(3)->collapsible()->schema([
                TextEntry::make('family_id')->label('Family')->placeholder('—')
                    ->formatStateUsing(fn (?int $state, SupportMessage $record): string => '#'.$state.($record->family !== null ? ' ('.$record->family->tier.' plan)' : ''))
                    ->url(fn (SupportMessage $record): ?string => $record->family_id !== null ? FamilyResource::getUrl('view', ['record' => $record->family_id]) : null),
                TextEntry::make('app_version')->label('App version')->placeholder('—'),
                TextEntry::make('platform')->placeholder('—'),
                TextEntry::make('device')->placeholder('—'),
                TextEntry::make('created_at')->label('Received')->dateTime(),
                TextEntry::make('last_activity_at')->label('Last activity')->dateTime()->placeholder('—'),
                TextEntry::make('answered_at')->label('Answered')->dateTime()->placeholder('—'),
                TextEntry::make('closed_at')->label('Closed')->dateTime()->placeholder('—'),
            ]),
            Section::make('Replies')->schema([
                TextEntry::make('history')->hiddenLabel()
                    ->state(fn (SupportMessage $record): string => $record->replies->isEmpty() ? '' : 'replies')
                    ->formatStateUsing(fn (SupportMessage $record): HtmlString => self::history($record))
                    ->placeholder('No reply yet.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user:id,email', 'family:id,tier'])->withCount('replies'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('topic')->formatStateUsing(fn (string $state): string => SupportMessage::topicLabel($state)),
                TextColumn::make('name')->label('From')->placeholder('—')->searchable(['name', 'email'])
                    ->description(fn (SupportMessage $record): string => (string) $record->replyEmail()),
                TextColumn::make('excerpt')->label('Message')->wrap()
                    ->state(fn (SupportMessage $record): string => mb_strimwidth($record->message, 0, 90, '…'))
                    ->searchable(['message']),
                TextColumn::make('source')->badge()->formatStateUsing(fn (string $state): string => self::SOURCE_LABELS[$state] ?? $state),
                TextColumn::make('replies_count')->label('Replies')->numeric(),
                TextColumn::make('created_at')->label('Received')->since()->dateTimeTooltip()->sortable(),
                TextColumn::make('last_activity_at')->label('Last activity')->since()->dateTimeTooltip()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUS_LABELS),
                SelectFilter::make('source')->options(self::SOURCE_LABELS),
                SelectFilter::make('topic')->options(array_map(fn (array $labels): string => $labels['en'], SupportMessage::TOPIC_LABELS)),
            ])
            ->recordActions([ViewAction::make()]);
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->with(['user:id,email', 'family:id,tier', 'replies.staff:id,display_name']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportMessages::route('/'),
            'view' => ViewSupportMessage::route('/{record}'),
        ];
    }

    /**
     * Header actions of the view page: Reply, Mark open, Close (both staff roles), Delete (admins).
     *
     * @return list<Action>
     */
    public static function messageActions(): array
    {
        return [
            Action::make('reply')
                ->label('Reply')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->authorize(fn (): bool => StaffAccess::isStaff())
                ->visible(fn (SupportMessage $record): bool => ($record->replyEmail() ?? '') !== '')
                ->modalHeading('Reply by email')
                ->modalDescription(fn (SupportMessage $record): string => 'Sent to '.$record->replyEmail().' in '.($record->locale === 'fr' ? 'French' : 'English').' (the mail frame is translated; write your text in that language). Replies go back to the support inbox. Plain text only.')
                ->modalSubmitActionLabel('Send reply')
                ->schema([
                    Textarea::make('body')->label('Your reply')->required()->rows(10)->maxLength(SupportText::REPLY_MAX_LENGTH),
                ])
                ->action(function (SupportMessage $record, array $data): void {
                    $staff = StaffAccess::user();

                    if ($staff === null) {
                        return;
                    }

                    try {
                        app(ReplyToSupportMessage::class)->handle($record, $staff, (string) $data['body']);
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    }

                    $record->load('replies.staff:id,display_name');
                    Notification::make()->title('Reply sent to '.$record->replyEmail())->success()->send();
                }),
            Action::make('markOpen')
                ->label('Mark open')
                ->icon(Heroicon::OutlinedEnvelopeOpen)
                ->color('gray')
                ->authorize(fn (): bool => StaffAccess::isStaff())
                ->visible(fn (SupportMessage $record): bool => in_array($record->status, [SupportMessage::STATUS_ANSWERED, SupportMessage::STATUS_CLOSED], true))
                ->action(function (SupportMessage $record): void {
                    app(ChangeSupportMessageStatus::class)->handle($record, SupportMessage::STATUS_OPEN);
                    Notification::make()->title('Marked open')->success()->send();
                }),
            Action::make('close')
                ->label('Close')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('gray')
                ->authorize(fn (): bool => StaffAccess::isStaff())
                ->visible(fn (SupportMessage $record): bool => $record->status !== SupportMessage::STATUS_CLOSED)
                ->action(function (SupportMessage $record): void {
                    app(ChangeSupportMessageStatus::class)->handle($record, SupportMessage::STATUS_CLOSED);
                    Notification::make()->title('Closed')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }

    /**
     * Plain text as escaped HTML that keeps the line breaks.
     */
    public static function plainText(string $text): HtmlString
    {
        return new HtmlString('<div style="white-space: pre-wrap; overflow-wrap: anywhere;">'.e($text).'</div>');
    }

    /**
     * Every reply, oldest first: who, when, and the escaped text.
     */
    private static function history(SupportMessage $record): HtmlString
    {
        $html = '';

        foreach ($record->replies as $reply) {
            /** @var SupportMessageReply $reply */
            $html .= '<div style="margin-bottom: 1rem;"><p style="font-size: 0.85em; opacity: .75; margin: 0 0 .25rem;">'
                .e(($reply->staff->display_name ?? 'Former staff').' · '.($reply->sent_at?->toDayDateTimeString() ?? '—').' UTC')
                .'</p>'.self::plainText($reply->body)->toHtml().'</div>';
        }

        return new HtmlString($html);
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            SupportMessage::STATUS_NEW => 'warning',
            SupportMessage::STATUS_OPEN => 'info',
            SupportMessage::STATUS_ANSWERED => 'success',
            default => 'gray',
        };
    }
}

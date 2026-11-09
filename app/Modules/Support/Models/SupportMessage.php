<?php

namespace App\Modules\Support\Models;

use App\Modules\Family\Models\Family;
use App\Modules\Identity\Models\User;
use App\Modules\Support\Support\SupportText;
use Database\Factories\SupportMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A message to the support team (docs/API.md "Support messages"): from the app (`source` app, a
 * family admin; the reply goes to the user's account email) or from the website's /support form
 * (`source` web, `name` + `email` typed by the sender). `message` is plain text normalized by
 * {@see SupportText}; it is never rendered as HTML or Markdown anywhere (Blade escapes it, mails
 * use the `user-text` mail component).
 *
 * Status: new (unread) → open (seen by staff) → answered (a reply was sent) → closed. The API shows
 * `new` as `open`.
 *
 * @property int $id
 * @property string $source
 * @property int|null $user_id
 * @property int|null $family_id
 * @property string|null $name
 * @property string|null $email
 * @property string $locale
 * @property string $topic
 * @property string $message
 * @property string $status
 * @property string|null $app_version
 * @property string|null $platform
 * @property string|null $device
 * @property string|null $client_id
 * @property Carbon|null $answered_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Family|null $family
 * @property-read Collection<int, SupportMessageReply> $replies
 */
#[Fillable(['source', 'user_id', 'family_id', 'name', 'email', 'locale', 'topic', 'message', 'status', 'app_version', 'platform', 'device', 'client_id', 'answered_at', 'closed_at', 'last_activity_at'])]
#[UseFactory(SupportMessageFactory::class)]
class SupportMessage extends Model
{
    /** @use HasFactory<SupportMessageFactory> */
    use HasFactory;

    public const string SOURCE_APP = 'app';

    public const string SOURCE_WEB = 'web';

    public const array SOURCES = [self::SOURCE_APP, self::SOURCE_WEB];

    public const string STATUS_NEW = 'new';

    public const string STATUS_OPEN = 'open';

    public const string STATUS_ANSWERED = 'answered';

    public const string STATUS_CLOSED = 'closed';

    public const array STATUSES = [self::STATUS_NEW, self::STATUS_OPEN, self::STATUS_ANSWERED, self::STATUS_CLOSED];

    /** Topics (docs/API.md "Support messages"); the website form uses the same list. */
    public const array TOPICS = ['account', 'billing', 'bug', 'content', 'privacy', 'idea', 'other'];

    public const array PLATFORMS = ['ios', 'android', 'web'];

    /** Topic labels for the website form, the inbox and the mails. */
    public const array TOPIC_LABELS = [
        'account' => ['en' => 'My account or family', 'fr' => 'Mon compte ou ma famille'],
        'billing' => ['en' => 'Plan and billing', 'fr' => 'Forfait et facturation'],
        'bug' => ['en' => 'Something doesn’t work', 'fr' => 'Quelque chose ne fonctionne pas'],
        'content' => ['en' => 'A question or answer looks wrong', 'fr' => 'Une question ou une réponse semble erronée'],
        'privacy' => ['en' => 'Privacy and my data', 'fr' => 'Confidentialité et mes données'],
        'idea' => ['en' => 'An idea or a suggestion', 'fr' => 'Une idée ou une suggestion'],
        'other' => ['en' => 'Something else', 'fr' => 'Autre chose'],
    ];

    /**
     * Never serialize the text by accident (e.g. a model dumped into a log or exception context).
     *
     * @var list<string>
     */
    protected $hidden = ['message'];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * Staff replies, oldest first (deleted with the message, FK cascade).
     *
     * @return HasMany<SupportMessageReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(SupportMessageReply::class)->orderBy('id');
    }

    /**
     * The status the API shows: `new` (not seen by staff yet) is `open` for the sender.
     */
    public function apiStatus(): string
    {
        return $this->status === self::STATUS_NEW ? self::STATUS_OPEN : $this->status;
    }

    /**
     * Where a reply goes: the account's current email for app messages (the address the app
     * showed before sending), else the address typed on the website form.
     */
    public function replyEmail(): ?string
    {
        if ($this->source === self::SOURCE_APP && $this->user_id !== null) {
            return $this->user->email ?? $this->email;
        }

        return $this->email;
    }

    public static function topicLabel(string $topic, string $locale = 'en'): string
    {
        return self::TOPIC_LABELS[$topic][$locale] ?? self::TOPIC_LABELS[$topic]['en'] ?? $topic;
    }
}

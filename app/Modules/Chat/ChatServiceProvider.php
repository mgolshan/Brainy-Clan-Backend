<?php

namespace App\Modules\Chat;

use App\Modules\Chat\Console\PruneChatMessagesCommand;
use App\Modules\Chat\Listeners\DeleteFamilyChat;
use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Family\Events\FamilyDeleted;
use App\Modules\Identity\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Family chat (docs/API.md "Family chat", 2026-10-23): one plain-text group chat per family.
 * `chat-post` limiter: 20 messages per minute per member; `chat-report`: 10 reports per hour per
 * member. `chat:prune` runs hourly (retention). The `family_chat` / `family_chat_report` pushes live
 * in the Notifications module (listen to `MessagePosted` / `MessageReported`).
 */
class ChatServiceProvider extends ModuleServiceProvider
{
    public const int POSTS_PER_MINUTE = 20;

    public const int REPORTS_PER_HOUR = 10;

    protected array $listen = [
        FamilyDeleted::class => [DeleteFamilyChat::class],
    ];

    public function boot(): void
    {
        RateLimiter::for('chat-post', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(self::POSTS_PER_MINUTE)->by($user instanceof User ? 'chat-post|'.$user->id : 'chat-post-ip|'.$request->ip());
        });

        RateLimiter::for('chat-report', function (Request $request) {
            $user = $request->user();

            return Limit::perHour(self::REPORTS_PER_HOUR)->by($user instanceof User ? 'chat-report|'.$user->id : 'chat-report-ip|'.$request->ip());
        });

        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([PruneChatMessagesCommand::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('chat:prune')->hourly()->withoutOverlapping()->onOneServer();
            });
        }
    }
}

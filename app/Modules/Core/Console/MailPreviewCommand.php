<?php

namespace App\Modules\Core\Console;

use App\Modules\Billing\Mail\WebCheckoutMail;
use App\Modules\Core\Mail\OpsAlertMail;
use App\Modules\Core\Support\Website;
use App\Modules\Family\Mail\AccountDeletionConfirmation;
use App\Modules\Family\Mail\ChildDataExport;
use App\Modules\Identity\Mail\PasswordChanged;
use App\Modules\Identity\Mail\PasswordResetLink;
use App\Modules\Identity\Mail\SignInAttemptsNotice;
use App\Modules\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\Notifications\LongWaitDetected;
use RuntimeException;
use Throwable;

/**
 * Sends one sample of every email the app sends to one address, with made-up data (no real user,
 * family or token), so the shared template can be checked in a mail inbox such as the Mailtrap
 * sandbox (README "Emails"). Sent synchronously (`sendNow`), so SMTP errors show up here instead
 * of in Horizon. Refused in production.
 */
class MailPreviewCommand extends Command
{
    protected $signature = 'mail:preview
        {email : Address that receives the samples (use a sandbox inbox such as Mailtrap)}
        {--locale=en : en or fr (staff alerts are English only and are sent with en)}
        {--pause=2 : Seconds to wait between two mails (sandbox inboxes rate-limit)}';

    protected $description = 'Send a sample of every email type to one address (local/staging only)';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('mail:preview is disabled in production: it sends sample mails with made-up links.');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $locale = (string) $this->option('locale');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Give a valid email address.');

            return self::FAILURE;
        }

        if (! in_array($locale, User::LOCALES, true)) {
            $this->error('--locale must be one of: '.implode(', ', User::LOCALES).'.');

            return self::FAILURE;
        }

        $this->info('Mailer: '.config('mail.default').' ('.config('mail.mailers.'.config('mail.default').'.host', '-').'), from '.config('mail.from.address'));

        $failures = 0;
        $first = true;

        foreach ($this->samples($locale) as $label => $send) {
            if (! $first) {
                $this->pause();
            }

            $first = false;

            try {
                $send($email);
                $this->line("  <info>sent</info>  {$label}");
            } catch (Throwable $exception) {
                $failures++;
                $this->line("  <error>failed</error> {$label}: ".$exception->getMessage());
            }
        }

        if ($failures > 0) {
            $this->error("{$failures} mail(s) failed.");

            return self::FAILURE;
        }

        $this->info('Done. Check the inbox of '.$email.'.');

        return self::SUCCESS;
    }

    /**
     * Label => sender. Every mailable of the app appears here; tests check none is missing.
     *
     * @return array<string, callable(string): void>
     */
    public function samples(string $locale): array
    {
        $lang = $locale === 'en' ? [] : ['lang' => $locale];
        $mail = fn (Mailable $mailable): callable => fn (string $to) => Mail::to($to)->sendNow($mailable);

        $samples = [
            'Password reset link' => $mail(new PasswordResetLink(
                Website::url(route('password.reset', ['token' => Str::random(64), ...$lang], false)),
                $locale,
                60,
            )),
            'Password changed' => $mail(new PasswordChanged($locale)),
            'Sign-in attempts notice' => $mail(new SignInAttemptsNotice($locale, 15)),
            'Account deletion confirmation' => $mail(new AccountDeletionConfirmation(
                Website::route('account.delete.confirm', ['token' => Str::random(64), ...$lang]),
                $locale,
            )),
            'Web checkout link' => $mail(new WebCheckoutMail(
                $locale,
                'annual',
                Website::route('website.subscribe.checkout', ['token' => Str::random(64), ...$lang]),
                null,
                null,
            )),
            'Web checkout refused (store plan active)' => $mail(new WebCheckoutMail($locale, 'annual', null, 'store_plan_active', 'app_store')),
            'Child data export' => $mail($this->sampleChildExport($locale)),
        ];

        if ($locale !== 'en') {
            return $samples;
        }

        return $samples + [
            'Ops alert (failed job)' => $mail(new OpsAlertMail(
                '[Brainy Clan '.app()->environment().'] Queued job failed: SampleJob (preview)',
                implode("\n", [
                    'A queued job failed and was moved to the failed jobs (tries = 1).',
                    '',
                    'Job: App\\Modules\\Notifications\\Jobs\\SampleJob',
                    'Queue: redis / default',
                    'Exception: RuntimeException',
                    'Time (UTC): '.now()->utc()->toDateTimeString(),
                    '',
                    'This is a sample sent by php artisan mail:preview.',
                ]),
            )),
            'Horizon long wait (Horizon notification)' => function (string $to): void {
                if (! Horizon::$email) {
                    throw new RuntimeException('skipped: no ops alert address (OPS_ALERT_EMAIL) configured');
                }

                Notification::route('mail', $to)->notifyNow(new LongWaitDetected('redis', 'default', 75));
            },
        ];
    }

    /**
     * A child export mail with a made-up profile and a small sample JSON instead of a real export.
     */
    private function sampleChildExport(string $locale): ChildDataExport
    {
        $child = new User;

        return new class($child, $locale === 'fr' ? 'Petit Renard' : 'Little Fox', $locale) extends ChildDataExport
        {
            /**
             * @return array<int, Attachment>
             */
            public function attachments(): array
            {
                $json = (string) json_encode(['data' => ['sample' => true, 'note' => 'mail:preview sample, not a real export']], JSON_PRETTY_PRINT);

                return [Attachment::fromData(fn (): string => $json, 'brainy-clan-export-sample.json')->withMime('application/json')];
            }
        };
    }

    private function pause(): void
    {
        $seconds = max(0, (int) $this->option('pause'));

        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}

<?php

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Lockout\LoginLockout;
use Illuminate\Console\Command;

/**
 * Support tool: lifts the sign-in lockout of an email (per email and every email + IP pair),
 * optionally also of an IP address (per IP and sign-in / invite codes).
 */
class UnlockSignInCommand extends Command
{
    protected $signature = 'auth:unlock {email : The account email} {--ip= : Also unlock this IP address}';

    protected $description = 'Lift the sign-in lockout of an email address (and optionally an IP)';

    public function handle(LoginLockout $lockout): int
    {
        $email = (string) $this->argument('email');

        if (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            $this->error('That is not an email address.');

            return self::FAILURE;
        }

        $lockout->clearEmail($email);
        $this->info('Sign-in lockout cleared for that email.');

        $ip = $this->option('ip');

        if (is_string($ip) && $ip !== '') {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $this->error('That is not an IP address.');

                return self::FAILURE;
            }

            $lockout->clearIp($ip);
            $this->info('Sign-in lockout cleared for that IP.');
        }

        return self::SUCCESS;
    }
}

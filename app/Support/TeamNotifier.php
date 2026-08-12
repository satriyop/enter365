<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Sends internal team notifications to the configured team/company email.
 */
final class TeamNotifier
{
    public static function mail(Notification $notification, string $configKey): void
    {
        $email = config("accounting.notifications.{$configKey}.team_email")
            ?: config('accounting.notifications.team_email')
            ?: config('accounting.company.email');

        if (! is_string($email) || $email === '') {
            Log::info('Team notification skipped: no team email configured', [
                'notification' => $notification::class,
                'config_key' => $configKey,
            ]);

            return;
        }

        NotificationFacade::route('mail', $email)->notify($notification);
    }
}

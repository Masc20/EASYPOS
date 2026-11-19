<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class SecurityLogger
{
    /**
     * Write an event to the dedicated security log channel without ever throwing.
     *
     * @param  array<string, mixed>  $context
     */
    public static function log(string $level, string $event, array $context = []): void
    {
        try {
            $logger = Log::channel('security');

            match (strtolower(trim($level))) {
                'emergency' => $logger->emergency($event, $context),
                'alert' => $logger->alert($event, $context),
                'critical' => $logger->critical($event, $context),
                'error' => $logger->error($event, $context),
                'notice' => $logger->notice($event, $context),
                'info' => $logger->info($event, $context),
                'debug' => $logger->debug($event, $context),
                default => $logger->warning($event, $context),
            };
        } catch (Throwable) {
            // Never fail POS, auth, or security flows due to log channel/permission errors
        }
    }
}

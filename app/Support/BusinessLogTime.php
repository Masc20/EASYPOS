<?php

namespace App\Support;

use DateTimeZone;
use Illuminate\Log\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;

class BusinessLogTime
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        $monolog->setTimezone(new DateTimeZone(config('app.business_timezone')));

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface && $handler->getFormatter() instanceof LineFormatter) {
                $handler->getFormatter()->setDateFormat('Y-m-d H:i:s P');
            }
        }
    }
}

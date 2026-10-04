<?php

namespace App\Logging;

use DateTimeZone;
use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;

class UseParisTimezone
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        if ($monolog instanceof MonologLogger) {
            $monolog->setTimezone(new DateTimeZone('Europe/Paris'));
        }
    }
}

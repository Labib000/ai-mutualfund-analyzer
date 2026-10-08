<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Shared hosting has no supervisor, so cron runs `schedule:run` every minute
 * and this entry drains the database queue. --max-time stays under a minute
 * and below the queue's retry_after (90s). The overlap lock expires after
 * 5 minutes so a killed worker cannot block the queue for long.
 *
 * Keep this entry last: scheduled tasks run in order, and the worker can
 * hold the minute for up to --max-time seconds.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);

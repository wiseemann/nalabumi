<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('nalabumi:fetch-news')->everyThirtyMinutes();
Schedule::command('nalabumi:recalculate-urgency')->dailyAt('01:00');

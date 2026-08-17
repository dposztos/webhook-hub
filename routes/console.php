<?php

use Illuminate\Support\Facades\Schedule;

// Enforce retention rules. By default there is nothing to do: endpoints keep
// everything until you set a limit.
Schedule::command('webhook:prune')->dailyAt('03:30');

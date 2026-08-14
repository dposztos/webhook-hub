<?php

use Illuminate\Support\Facades\Schedule;

// Megőrzési szabályok érvényesítése. Alapból nincs mit tenni:
// az endpointok addig őriznek mindent, amíg nem állítasz be korlátot.
Schedule::command('webhook:prune')->dailyAt('03:30');

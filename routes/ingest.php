<?php

use App\Http\Controllers\IngestController;
use Illuminate\Support\Facades\Route;

// Every incoming webhook: /u/<group>/<subgroup>/<endpoint>/<secret>[/<anything>]
Route::any('/u/{path}', IngestController::class)->where('path', '.*');

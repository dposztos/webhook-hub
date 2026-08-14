<?php

use App\Http\Controllers\IngestController;
use Illuminate\Support\Facades\Route;

// Minden beérkező webhook: /u/<csoport>/<alcsoport>/<endpoint>/<titok>[/<bármi>]
Route::any('/u/{path}', IngestController::class)->where('path', '.*');

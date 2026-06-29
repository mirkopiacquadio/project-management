<?php

use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Middleware\EnsureGestionaleToken;
use Illuminate\Support\Facades\Route;

/*
| API esposte al gestionale esterno (omnianextsrl).
| Prefisso /api e gruppo middleware "api" applicati automaticamente da bootstrap/app.php
| (nessuna sessione/web). Protette dal token statico EnsureGestionaleToken.
*/
Route::middleware(EnsureGestionaleToken::class)->group(function () {
    Route::get('/projects', [ProjectApiController::class, 'index']);
    Route::get('/projects/{project}', [ProjectApiController::class, 'show']);
});

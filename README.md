# Readiness Controller

this class is to be used in laravel projects as a drop in readiness controller for k8s environments.
it checks the connection for redis and sql to be working and active and send 503 if they are not, thus triggering the proper readiness-failed mechanisms of k8s.
So this is a hardening thing.

## install 

to use this do the usual

```
composer install meinestadt/ms-laravel-readiness
```

## configure

to be used in your application, you also need to setup the necessary route(s)

```
<?php

use Illuminate\Support\Facades\Route;
use Meinestadt\MsLaravelReadiness\ReadinessController;
use App\Http\Middleware\AuthenticateApiCalls;

/*
 * other routes here
 * ...
 * ...
 */

# excluding session based middleware
# bonus simplified liveness route, to be used for startup and liveness checks
Route::withoutMiddleware([
    AuthenticateApiCalls::class,
    \App\Http\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \App\Http\Middleware\VerifyCsrfToken::class,
])->group(function () {
    Route::get('/health/liveness', fn () => response()->json(['status' => 'ok']))->name('liveness');
    Route::get('/health/readiness', ReadinessController::class)->name('readiness');
});

```


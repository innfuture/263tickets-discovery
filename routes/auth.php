<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Laravel\WorkOS\Http\Requests\AuthKitAuthenticationRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLoginRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLogoutRequest;

Route::middleware(['guest'])->group(function () {
    Route::get('login', fn (AuthKitLoginRequest $request) => $request->redirect())->name('login');

    Route::get('authenticate', function (AuthKitAuthenticationRequest $request) {
        $request->authenticate();

        $user = auth()->user();
        $currentOrg = $user->currentOrganization ?? $user->personalOrganization();

        if ($currentOrg && ! $user->current_organization_id) {
            $user->switchOrganization($currentOrg);
        }

        if ($currentOrg) {
            URL::defaults(['current_organization' => $currentOrg->slug]);
        }

        return redirect()->intended(route('dashboard'));
    });
});

Route::post('logout', fn (AuthKitLogoutRequest $request) => $request->logout())
    ->middleware(['auth'])->name('logout');

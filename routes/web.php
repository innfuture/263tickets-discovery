<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', ValidateSessionWithWorkOS::class, EnsureTeamMembership::class])
    ->group(function () {
        Route::inertia('dashboard', 'dashboard')->name('dashboard');

        Route::get('events', [EventController::class, 'index'])->name('events.index');
        Route::post('events', [EventController::class, 'store'])->name('events.store');
        Route::get('events/{event:slug}/edit', [EventController::class, 'edit'])->name('events.edit');
        Route::patch('events/{event:slug}', [EventController::class, 'update'])->name('events.update');
        Route::patch('events/{event:slug}/seo', [EventController::class, 'updateSeo'])->name('events.seo.update');
        Route::post('events/{event:slug}/media', [EventController::class, 'storeMedia'])->name('events.media.store');
        Route::delete('events/{event:slug}/media/{media}', [EventController::class, 'destroyMedia'])->name('events.media.destroy');
        Route::get('events/{event:slug}', [EventController::class, 'show'])->name('events.show');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

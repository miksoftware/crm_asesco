<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Settings\Users\Index as UsersIndex;
use App\Livewire\Settings\Users\Create as UsersCreate;
use App\Livewire\Settings\Users\Edit as UsersEdit;
use App\Livewire\Settings\Roles\Index as RolesIndex;
use App\Livewire\Settings\Roles\Create as RolesCreate;
use App\Livewire\Settings\Roles\Edit as RolesEdit;
use App\Livewire\Channels\Index as ChannelsIndex;
use App\Livewire\Channels\Create as ChannelsCreate;
use App\Livewire\Channels\Edit as ChannelsEdit;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/', Login::class)->name('login');
    Route::get('/login', Login::class);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Channels (WhatsApp)
    Route::get('/canales', ChannelsIndex::class)->name('channels.index');
    Route::get('/canales/crear', ChannelsCreate::class)->name('channels.create');
    Route::get('/canales/{channel}/editar', ChannelsEdit::class)->name('channels.edit');

    // Settings - Users
    Route::get('/configuracion/usuarios', UsersIndex::class)->name('settings.users.index');
    Route::get('/configuracion/usuarios/crear', UsersCreate::class)->name('settings.users.create');
    Route::get('/configuracion/usuarios/{user}/editar', UsersEdit::class)->name('settings.users.edit');

    // Settings - Roles
    Route::get('/configuracion/roles', RolesIndex::class)->name('settings.roles.index');
    Route::get('/configuracion/roles/crear', RolesCreate::class)->name('settings.roles.create');
    Route::get('/configuracion/roles/{role}/editar', RolesEdit::class)->name('settings.roles.edit');
});

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
use App\Livewire\Chat\Index as ChatIndex;
use App\Livewire\Help\TechnicalManual;
use App\Livewire\Campaigns\Index as CampaignsIndex;
use App\Livewire\Campaigns\Create as CampaignsCreate;
use App\Livewire\Campaigns\Results as CampaignsResults;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/', Login::class)->name('login');
    Route::get('/login', Login::class);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard')->middleware('permission:dashboard.ver');

    // Channels (WhatsApp)
    Route::get('/canales', ChannelsIndex::class)->name('channels.index')->middleware('permission:canales.ver');
    Route::get('/canales/crear', ChannelsCreate::class)->name('channels.create')->middleware('permission:canales.crear');
    Route::get('/canales/{channel}/editar', ChannelsEdit::class)->name('channels.edit')->middleware('permission:canales.editar');

    // Chat (WhatsApp)
    Route::get('/chat', ChatIndex::class)->name('chat.index')->middleware('permission:chats.ver');

    // Campaigns (Bulk Messaging)
    Route::get('/campanas', CampaignsIndex::class)->name('campaigns.index')->middleware('permission:campanas.ver');
    Route::get('/campanas/crear', CampaignsCreate::class)->name('campaigns.create')->middleware('permission:campanas.crear');
    Route::get('/campanas/{campaign}/resultados', CampaignsResults::class)->name('campaigns.results')->middleware('permission:campanas.ver');

    // Settings - Users
    Route::get('/configuracion/usuarios', UsersIndex::class)->name('settings.users.index')->middleware('permission:usuarios.ver');
    Route::get('/configuracion/usuarios/crear', UsersCreate::class)->name('settings.users.create')->middleware('permission:usuarios.crear');
    Route::get('/configuracion/usuarios/{user}/editar', UsersEdit::class)->name('settings.users.edit')->middleware('permission:usuarios.editar');

    // Settings - Roles
    Route::get('/configuracion/roles', RolesIndex::class)->name('settings.roles.index')->middleware('permission:roles.ver');
    Route::get('/configuracion/roles/crear', RolesCreate::class)->name('settings.roles.create')->middleware('permission:roles.crear');
    Route::get('/configuracion/roles/{role}/editar', RolesEdit::class)->name('settings.roles.edit')->middleware('permission:roles.editar');

    // Help
    Route::get('/ayuda/manual-tecnico', TechnicalManual::class)->name('help.technical-manual');
});

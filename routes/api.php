<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

/**
 * Evolution API Webhook Endpoint
 * 
 * This endpoint receives webhooks from Evolution API for:
 * - New incoming messages (messages.upsert)
 * - Message status updates (messages.update)
 * - Connection status changes (connection.update)
 * 
 * Requirements: 9.1
 * 
 * Note: This route is public (no authentication) as Evolution API
 * sends webhooks without authentication headers. Security is handled
 * by validating the instance name exists in our database.
 */
Route::post('/webhook/evolution', [WebhookController::class, 'handleEvolutionWebhook'])
    ->name('webhook.evolution');

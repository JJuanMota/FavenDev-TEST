<?php

use App\Http\Controllers\IssuerWebhookController;
use App\Http\Controllers\RedeemController;
use Illuminate\Support\Facades\Route;

Route::post('/redeem', RedeemController::class);
Route::post('/webhook/issuer-platform', IssuerWebhookController::class);

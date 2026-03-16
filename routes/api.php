<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillController;
use App\Http\Controllers\NimblePaymentController;

Route::get('/fetch-bill', [BillController::class, 'fetchBill']);
Route::post('/create-payment-link', [NimblePaymentController::class, 'createPaymentLink']);
Route::post('/update-payment-link', [PaymentController::class, 'updatePaymentLink']);
Route::post('/payment-link-action', [PaymentController::class, 'paymentLinkAction']);
Route::post('/nimbbl-webhook', [PaymentController::class, 'paymentWebhook']);

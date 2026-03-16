<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\BillController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', function () {
    return view('home');
});

Route::get('/checkout', [PaymentController::class, 'checkoutPage']);
Route::post('/create-order', [PaymentController::class, 'createOrder']);
Route::post('/payment-callback', [PaymentController::class, 'paymentCallback']);
Route::get('/order/{order_id}', [PaymentController::class, 'getOrder']);
Route::post('/payment/verify', [PaymentController::class, 'verifyPayment']);
Route::post('/fetch-bill', [BillController::class, 'fetchBill']);
Route::get('/bill', function () {
    return view('bill', ['billData' => ['success' => true]]);
});

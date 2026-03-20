<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function checkoutPage()
    {
        return view('checkout');
    }

    public function createOrder(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric',
                'user_name' => 'required|string',
            ]);

            $invoiceId = 'INV_'.time();
            $amount = (float) $request->input('amount');
            $userName = $request->input('user_name');
              // $wallet = Wallet::where('user_id', $user->id)->first();

            // if (!$wallet) {
            //     throw new \Exception('Wallet not found');
            // }

            // // ✅ Check Balance
            // if ($wallet->balance < $amount) {
            //     throw new \Exception('Insufficient wallet balance');
            // }

            $accessKey = env('NIMBBL_ACCESS_KEY');
            $accessSecret = env('NIMBBL_ACCESS_SECRET');
            $baseUrl = env('NIMBBL_BASE_URL', 'https://api.nimbbl.tech');

            // STEP 1 Generate Token
            $tokenUrl = $baseUrl.'/api/v3/generate-token';
            $tokenPayload = [
                'access_key' => $accessKey,
                'access_secret' => $accessSecret,
            ];

            $this->customLog($tokenUrl, 'POST', [], $tokenPayload, 'REQUEST', $invoiceId);

            $tokenResponse = Http::post($tokenUrl, $tokenPayload);

            $this->customLog($tokenUrl, 'POST', $tokenResponse->headers(), $tokenResponse->json(), 'RESPONSE', $invoiceId);

            if ($tokenResponse->failed()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token generation failed',
                    'nimbbl_response' => $tokenResponse->body(),
                ], 500);
            }

            $token = $tokenResponse['token'];

            // STEP 2 Create Order
            $orderUrl = $baseUrl.'/api/v3/create-order';

            $payload = [
                'amount_before_tax' => $amount,
                'tax' => 0,
                'total_amount' => $amount,
                'currency' => 'INR',
                'invoice_id' => $invoiceId,

                'user' => [
                    'email' => 'test@gmail.com',
                    'first_name' => $userName,
                    'last_name' => 'Kumar',
                    'mobile_number' => '9876543210',
                    'country_code' => '+91',
                ],

                'callback_url' => url('/payment-callback'),
            ];

            $this->customLog($orderUrl, 'POST', ['Authorization' => 'Bearer '.$token], $payload, 'REQUEST', $invoiceId);

            $response = Http::withToken($token)
                ->acceptJson()
                ->post($orderUrl, $payload);

            $this->customLog($orderUrl, 'POST', $response->headers(), $response->json(), 'RESPONSE', $invoiceId);

            // Show real API response for debugging
            if ($response->failed()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order creation failed',
                    'nimbbl_response' => $response->body(),
                ], 500);
            }

            $data = $response->json();

            $orderId = $data['order_id'] ?? null;

            Transaction::create([
                'invoice_id' => $invoiceId,
                'order_id' => $orderId,
                'consumer_number' => $request->consumer_number ?? null,
                'amount' => $amount,
                'currency' => 'INR',
                'status' => 'INITIATED',
                'raw_response' => json_encode($data),
            ]);

            return response()->json([
                'status' => true,
                'token' => $data['token'] ?? null, // This is the access token for the checkout JS from the order
                'order_id' => $data['order_id'] ?? null,
                'full_response' => $data,
            ]);

        } catch (\Exception $e) {

            // ✅ Log error to payment.text
            $this->customLog(url('/create-order-error'), 'EXCEPTION', [], [
                'message' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500), // Limit trace size
            ], 'ERROR', $invoiceId ?? 'unknown');

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while creating order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        try {
            $invoiceId = $request->payload['order']['invoice_id'] ?? 'unknown';

            $this->customLog(url('/verify-payment'), 'POST', $request->headers->all(), $request->all(), 'VERIFY_PAYMENT_REQUEST', $invoiceId);

            $secretKey = env('NIMBBL_ACCESS_SECRET');

            $transaction = $request->payload['transaction'] ?? null;
            $order = $request->payload['order'] ?? null;

            if (! $transaction || ! $order) {
                return response()->json(['success' => false, 'message' => 'Invalid payload'], 400);
            }

            $invoiceId = $order['invoice_id'];
            $transactionId = $transaction['transaction_id'];
            $amount = number_format($transaction['transaction_amount'], 2, '.', '');
            $currency = $transaction['transaction_currency'];
            $status = $transaction['status'];
            $type = $transaction['transaction_type'];
            $receivedSignature = $transaction['signature'] ?? '';

            $this->customLog(url('/verify-payment'), 'DEBUG', [], [
                'extracted_fields' => [
                    'invoice_id' => $invoiceId,
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => $status,
                    'type' => $type,
                    'received_signature' => $receivedSignature,
                ],
            ], 'VERIFY_PAYMENT_EXTRACTED_DATA', $invoiceId);

            $signatureData = $invoiceId.'|'.$transactionId.'|'.$amount.'|'.$currency.'|'.$status.'|'.$type;

            $generatedSignature = hash_hmac('sha256', $signatureData, $secretKey);

            if ($generatedSignature === $receivedSignature) {

                $this->customLog(url('/verify-payment'), 'POST', [], [
                    'status' => 'success',
                    'message' => 'Signature Matched',
                    'comparison' => [
                        'received_from_nimbbl' => $receivedSignature,
                        'generated_by_server' => $generatedSignature,
                    ],
                    'raw_string_hashed' => $signatureData,
                    'invoice_id' => $invoiceId,
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => $status,
                    'type' => $type,
                ], 'VERIFY_PAYMENT_SIGNATURE_MATCH', $invoiceId);

                $txnResponse = $this->transactionEnquiry($transactionId, $invoiceId);

                $this->customLog(url('/transaction-enquiry'), 'POST', [], $txnResponse, 'TRANSACTION_ENQUIRY_RESPONSE', $invoiceId);

                // It returns an array 'transaction' containing the transaction objects.
                $paymentStatus = $txnResponse['transaction'][0]['payment_status'] ?? null;

                if ($paymentStatus === 'succeeded') {

                    $this->customLog(url('/verify-payment'), 'SUCCESS', [], [
                        'message' => 'Payment verified and confirmed Successfully',
                        'transaction_id' => $transactionId,
                        'status' => $paymentStatus,
                    ], 'VERIFY_PAYMENT_RESPONSE', $invoiceId);

                    Transaction::where('invoice_id', $invoiceId)
                        ->update([
                            'transaction_id' => $transactionId,
                            'status' => 'SUCCESS',
                            'payment_type' => $type,
                            'signature' => $receivedSignature,
                        ]);

                    $wallet = Wallet::where('user_id', 1)->first();

                    if ($wallet) {
                        $wallet->balance = $wallet->balance - $amount;
                        $wallet->save();

                        WalletTransaction::create([
                            'user_id' => 1,
                            'transaction_id' => $transactionId,
                            'amount' => $amount,
                            'type' => 'DEBIT',
                        ]);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment paid successfully',
                    ]);
                }

                $this->customLog(url('/verify-payment'), 'FAILURE', [], [
                    'message' => 'Payment not confirmed from transaction enquiry',
                    'nimbbl_status' => $paymentStatus,
                ], 'VERIFY_PAYMENT_RESPONSE', $invoiceId);

                Transaction::where('invoice_id', $invoiceId)
                    ->update([
                        'transaction_id' => $transactionId,
                        'status' => 'FAILED',
                    ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment not confirmed from transaction enquiry',
                    'nimbbl_status' => $paymentStatus,
                ]);
            }

            $this->customLog(url('/verify-payment'), 'ERROR', [], [
                'status' => 'failure',
                'message' => 'Signature Mismatch',
                'comparison' => [
                    'received_from_nimbbl' => $receivedSignature,
                    'generated_by_server' => $generatedSignature,
                ],
                'raw_string_hashed' => $signatureData,
                'fields' => [
                    'invoice_id' => $invoiceId,
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => $status,
                    'type' => $type,
                ],
            ], 'VERIFY_PAYMENT_RESPONSE', $invoiceId);

            Transaction::where('invoice_id', $invoiceId)
                ->update([
                    'transaction_id' => $transactionId,
                    'status' => 'SIGNATURE_FAILED',
                ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
            ]);

        } catch (\Exception $e) {

            // ✅ Log error to payment.text
            $this->customLog(url('/verify-payment-error'), 'EXCEPTION', [], [
                'message' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ], 'ERROR', $invoiceId ?? 'unknown');

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong in payment verification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function transactionEnquiry($transactionId, $invoiceId = null)
    {
        try {

            $accessKey = env('NIMBBL_ACCESS_KEY');
            $accessSecret = env('NIMBBL_ACCESS_SECRET');
            $baseUrl = env('NIMBBL_BASE_URL', 'https://api.nimbbl.tech');

            // STEP 1 Generate Token for Enquiry
            $tokenUrl = $baseUrl.'/api/v3/generate-token';
            $tokenPayload = [
                'access_key' => $accessKey,
                'access_secret' => $accessSecret,
            ];

            $this->customLog($tokenUrl, 'POST', [], $tokenPayload, 'ENQUIRY_TOKEN_REQUEST', $invoiceId);

            $tokenResponse = Http::post($tokenUrl, $tokenPayload);

            $this->customLog($tokenUrl, 'POST', $tokenResponse->headers(), $tokenResponse->json(), 'ENQUIRY_TOKEN_RESPONSE', $invoiceId);

            if ($tokenResponse->failed()) {
                return ['error' => 'Token generation failed for enquiry'];
            }

            $token = $tokenResponse['token'];

            // STEP 2 Perform Transaction Enquiry
            $enquiryUrl = $baseUrl.'/api/v3/transaction-enquiry';
            $enquiryPayload = [
                'transaction_id' => $transactionId,
            ];

            $this->customLog($enquiryUrl, 'POST', ['Authorization' => 'Bearer '.$token], $enquiryPayload, 'TRANSACTION_ENQUIRY_REQUEST', $invoiceId);

            $response = Http::withToken($token)
                ->acceptJson()
                ->post($enquiryUrl, $enquiryPayload);

            $this->customLog($enquiryUrl, 'POST', $response->headers(), $response->json(), 'TRANSACTION_ENQUIRY_RESPONSE', $invoiceId);

            return $response->json();

        } catch (\Exception $e) {

            // ✅ Log error to payment.text
            $this->customLog(url('/transaction-enquiry-error'), 'EXCEPTION', [], [
                'transaction_id' => $transactionId,
                'message' => $e->getMessage(),
            ], 'ERROR', $invoiceId);

            return [
                'error' => 'Something went wrong during transaction enquiry',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getOrder($order_id)
    {
        $accessKey = env('NIMBBL_ACCESS_KEY');
        $accessSecret = env('NIMBBL_ACCESS_SECRET');
        $baseUrl = env('NIMBBL_BASE_URL', 'https://api.nimbbl.tech');

        // STEP 1 Generate Token
        $tokenResponse = Http::post($baseUrl.'/api/v3/generate-token', [
            'access_key' => $accessKey,
            'access_secret' => $accessSecret,
        ]);

        if ($tokenResponse->failed()) {
            return 'Token generation failed';
        }

        $token = $tokenResponse['token'];

        // STEP 2 Get Order Details
        $orderResponse = Http::withToken($token)
            ->get($baseUrl.'/api/v3/order', [
                'order_id' => $order_id,
            ]);

        $this->customLog($baseUrl.'/api/v3/order', 'GET', $orderResponse->headers(), $orderResponse->json(), 'RESPONSE');

        if ($orderResponse->failed()) {
            return 'Order fetch failed';
        }

        $orderData = $orderResponse->json();

        return view('order-details', compact('orderData'));
    }

    public function paymentCallback(Request $request)
    {
        $invoiceId = $request->order_invoice_id ?? ($request->invoice_id ?? 'unknown');
        $this->customLog(url('/payment-callback'), 'POST', $request->headers->all(), $request->all(), 'CALLBACK_REQUEST', $invoiceId);

        return response()->json([
            'status' => 'Payment response received',
            'data' => $request->all(),
        ]);
    }

    /**
     * Custom logging to storage/logs/payment.text
     */
    private function customLog($url, $method, $headers, $body, $type = 'REQUEST', $identifier = null)
    {
        $importantHeaders = ['content-type', 'accept', 'authorization', 'access-key', 'nimbbl-token'];
        $filteredHeaders = array_intersect_key(
            array_change_key_case($headers, CASE_LOWER),
            array_flip($importantHeaders)
        );

        if (isset($filteredHeaders['authorization'])) {
            $filteredHeaders['authorization'] = 'Bearer '.substr($filteredHeaders['authorization'], 7, 10).'...';
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] " . ($identifier ? "[{$identifier}] " : "") . "{$type}\n";
        $logMessage .= "URL     : {$url}\n";
        $logMessage .= "METHOD  : {$method}\n";
        $logMessage .= 'HEADERS : '.json_encode($filteredHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $logMessage .= 'BODY    : '.json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $logMessage .= "--------------------------------------------------------------------------------\n";

        file_put_contents(storage_path('logs/payment.text'), $logMessage, FILE_APPEND);
    }
}

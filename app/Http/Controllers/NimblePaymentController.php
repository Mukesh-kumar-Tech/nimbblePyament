<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class NimblePaymentController extends Controller
{
    public function createPaymentLink(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'first_name' => 'required|string',
                'last_name' => 'required|string',
                'mobile_number' => 'required|string',
                'amount' => 'required|numeric',
            ]);

            if ($validator->fails()) {

                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $invoiceId = 'INV_'.rand(1000, 9999);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'access-key' => env('NIMBBL_ACCESS_KEY'),
                'access-secret' => env('NIMBBL_ACCESS_SECRET'),
            ])->post(env('NIMBBL_BASE_URL').'/api/v3/payment-link', [

                'user' => [
                    'email' => $request->email,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'country_code' => '+91',
                    'mobile_number' => $request->mobile_number,
                ],

                'total_amount' => $request->amount,
                'currency' => 'INR',
                'invoice_id' => 'INV_'.time(),
                'expires_at' => now()->addDay()->format('Y-m-d H:i:s'),

                'send_sms' => true,
                'send_email' => true,

                'description' => 'Payment for order',

                'callback_url' => 'https://yourdomain.com/payment-response',
            ]);

            if ($response->successful()) {

                $data = $response->json();

                return response()->json([
                    'status' => true,
                    'message' => 'Payment link created successfully',
                    'data' => $data,
                ], 200);
            }

            return response()->json([
                'status' => false,
                'message' => 'Failed to create payment link',
                'error' => $response->json(),
            ], 400);

        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updatePaymentLink(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $accessKey = env('NIMBBL_ACCESS_KEY');
        $accessSecret = env('NIMBBL_ACCESS_SECRET');

        $response = Http::withBasicAuth($accessKey, $accessSecret)
            ->put('https://api.nimbbl.tech/api/v3/payment_links', [

                'invoice_id' => $request->invoice_id,

                'user' => [
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                ],

                'total_amount' => $request->amount,
                'currency' => 'INR',
                'expires_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'status' => true,
            'data' => $response->json(),
        ]);
    }

    public function paymentLinkAction(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|string',
            'action' => 'required|string', // send or cancel
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $accessKey = env('NIMBBL_ACCESS_KEY');
        $accessSecret = env('NIMBBL_ACCESS_SECRET');

        $response = Http::withBasicAuth($accessKey, $accessSecret)
            ->post('https://api.nimbbl.tech/api/v3/payment_links/actions', [

                'invoice_id' => $request->invoice_id,
                'action' => $request->action,

            ]);

        return response()->json([
            'status' => true,
            'data' => $response->json(),
        ]);
    }

    public function paymentWebhook(Request $request)
    {
        $this->customLog(url('/nimbbl-webhook'), 'POST', $request->headers->all(), $request->all(), 'WEBHOOK_RECEIVED');

        $secretKey = env('NIMBBL_ACCESS_SECRET');

        // Verify signature before processing
        $isVerified = $this->verifyNimbblSignature($request->all(), $secretKey, url('/nimbbl-webhook'));

        if (!$isVerified) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $data = $request->all();
        $actualPayload = $data['payload'] ?? $data;
        $transaction = $actualPayload['transaction'] ?? null;
        
        $payment_status = $transaction['status'] ?? null;
        $order_id = $actualPayload['order']['order_id'] ?? null;

        if ($payment_status == 'success') {
            // Update database securely
            // Order::where('order_id', $order_id)->update(['status' => 'paid']);

            return response()->json([
                'message' => 'Payment successful',
            ]);
        } else {
            return response()->json([
                'message' => 'Payment failed or pending',
            ]);
        }
    }

    private function verifyNimbblSignature($payload, $secret, $url)
    {
        $transaction = $payload['payload']['transaction'] ?? ($payload['transaction'] ?? null);
        $order = $payload['payload']['order'] ?? ($payload['order'] ?? null);

        if (!$transaction || !$order) {
            return false;
        }

        $invoiceId = $order['invoice_id'] ?? '';
        $transactionId = $transaction['transaction_id'] ?? '';
        $amount = number_format($transaction['transaction_amount'] ?? 0, 2, '.', '');
        $currency = $transaction['transaction_currency'] ?? '';
        $status = $transaction['status'] ?? '';
        $type = $transaction['transaction_type'] ?? '';
        $receivedSignature = $transaction['signature'] ?? '';

        $this->customLog($url, 'DEBUG', [], [
            'extracted_fields' => [
                'invoice_id' => $invoiceId,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'type' => $type,
                'received_signature' => $receivedSignature
            ]
        ], 'WEBHOOK_EXTRACTED_DATA');

        $signatureData = $invoiceId . '|' . $transactionId . '|' . $amount . '|' . $currency . '|' . $status . '|' . $type;
        $generatedSignature = hash_hmac('sha256', $signatureData, $secret);

        $this->customLog($url, 'VERIFY', [], [
            'status' => ($receivedSignature === $generatedSignature) ? 'success' : 'failure',
            'message' => ($receivedSignature === $generatedSignature) ? 'Signature Matched' : 'Signature Mismatch',
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
                'type' => $type
            ]
        ], 'SIGNATURE_VERIFICATION');

        return $receivedSignature === $generatedSignature;
    }

    private function customLog($url, $method, $headers, $body, $type = 'REQUEST')
    {
        $importantHeaders = ['content-type', 'accept', 'authorization', 'access-key', 'nimbbl-token'];
        $filteredHeaders = array_intersect_key(
            array_change_key_case($headers, CASE_LOWER),
            array_flip($importantHeaders)
        );

        if (isset($filteredHeaders['authorization'])) {
            $filteredHeaders['authorization'] = 'Bearer ' . substr($filteredHeaders['authorization'], 7, 10) . '...';
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$type}\n";
        $logMessage .= "URL     : {$url}\n";
        $logMessage .= "METHOD  : {$method}\n";
        $logMessage .= 'HEADERS : ' . json_encode($filteredHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $logMessage .= 'BODY    : ' . json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $logMessage .= "--------------------------------------------------------------------------------\n";

        file_put_contents(storage_path('logs/custom_payment.txt'), $logMessage, FILE_APPEND);
    }
}

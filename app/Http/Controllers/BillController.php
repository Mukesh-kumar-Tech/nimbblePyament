<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillController extends Controller
{
    public function fetchBill(Request $request)
    {
        $request->validate([
            'consumer_number' => 'required|string',
        ]);

        $apiUrl = config('services.mobikwik.url');

 

        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-MClient' => '14',
                'Accept' => 'application/json',
            ])->post($apiUrl, $payload);

            $responseData = $response->json();

            // Log request & response
            Log::info('Mobikwik API Request', [
                'url' => $apiUrl,
                'payload' => $payload,
            ]);

            Log::info('Mobikwik API Response', [
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            // Check API success
            if ($response->successful() && ! empty($responseData['success'])) {
                return view('bill', [
                    'billData' => $responseData,
                ]);
            }

            return view('bill', [
                'billData' => [
                    'success' => false,
                    'message' => $responseData['message']['text'] ?? 'Invalid request',
                ],
            ]);

        } catch (\Exception $e) {

            Log::error('Mobikwik API Exception', [
                'error' => $e->getMessage(),
            ]);

            return view('bill', [
                'billData' => [
                    'success' => false,
                    'message' => 'API connection failed',
                ],
            ]);
        }
    }
}

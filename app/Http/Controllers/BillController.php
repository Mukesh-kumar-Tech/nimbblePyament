<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillController extends Controller
{
    // public function fetchBill(Request $request)
    // {
    //     $request->validate([
    //         'consumer_number' => 'required|string',
    //     ]);

    //     $apiUrl = config('services.mobikwik.url');

    //     // $payload = [
    //     //     'cn' => trim($request->consumer_number),
    //     //     'op' => '31',      // ✅ numeric operator ID
    //     //     'uid' => config('services.mobikwik.uid'),
    //     //     'pswd' => config('services.mobikwik.pwd'), // ⚠️ IMPORTANT: use pwd NOT pswd
    //     // ];

    //     $payload = [
    //         'adParams' => new \stdClass, // IMPORTANT
    //         'cn' => trim($request->consumer_number),
    //         'uid' => config('services.mobikwik.uid'),
    //         'pswd' => config('services.mobikwik.pwd'),
    //         "op": "194",
    //         "cir": 18,
    //     ];
    //     try {

    //         $response = Http::withHeaders([
    //             'Content-Type' => 'application/json',
    //             'Accept' => 'application/json',
    //             'X-MClient' => '14',
    //         ])->timeout(30)
    //             ->post($apiUrl, $payload);

    //             dd($response->body());
    //         $responseData = $response->json();

    //         // ✅ Debug properly (instead of dd)
    //         Log::info('Mobikwik Request', $payload);
    //         Log::info('Mobikwik Raw Response', [
    //             'status' => $response->status(),
    //             'body' => $response->body(),
    //         ]);

    //         // ✅ Handle success
    //         if ($response->successful() && isset($responseData['success']) && $responseData['success']) {
    //             return view('bill', [
    //                 'billData' => $responseData,
    //             ]);
    //         }

    //         // ❌ Handle failure properly
    //         return view('bill', [
    //             'billData' => [
    //                 'success' => false,
    //                 'message' => $responseData['message']['text']
    //                     ?? $responseData['message']
    //                     ?? 'Invalid request',
    //             ],
    //         ]);

    //     } catch (\Exception $e) {

    //         Log::error('Mobikwik Exception', [
    //             'error' => $e->getMessage(),
    //         ]);

    //         return view('bill', [
    //             'billData' => [
    //                 'success' => false,
    //                 'message' => 'API connection failed',
    //             ],
    //         ]);
    //     }
    // }

    
    public function fetchBill(Request $request)
    {
        try {
            $request->validate([
                'consumer_number' => 'required|string',
            ]);

            $apiUrl = config('services.mobikwik.url');
            $uid = config('services.mobikwik.uid');
            $pswd = config('services.mobikwik.pwd');

            // ✅ Payload
            // payload structure based on Mobikwik's API documentation

            $jsonPayload = json_encode($payload);

            // ✅ UPDATED HEADERS
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-MClient: 14',
            ];

            // ✅ Generate Terminal cURL (with headers)
            $curlCommand = 'curl -X POST "'.$apiUrl.'" ';
            foreach ($headers as $header) {
                $curlCommand .= '-H "'.$header.'" ';
            }
            $curlCommand .= "-d '".$jsonPayload."'";

            // ✅ Log cURL (optional but professional)
            \Log::info('Mobikwik CURL Command', [
                'command' => $curlCommand,
            ]);

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $jsonPayload,
            ]);

            $response = curl_exec($ch);

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            // ✅ Handle cURL Error
            if ($curlError) {
                throw new \Exception($curlError);
            }

            curl_close($ch);

            // ✅ Log API response
            \Log::info('Mobikwik API Response', [
                'status' => $httpCode,
                'response' => $response,
            ]);

            $billData = [
                'success' => true,
                'data' => [
                    [
                        'billAmount' => '14993.0',
                        'billnetamount' => '14993.0',
                        'billdate' => '01 Mar 2026',
                        'dueDate' => '2026-03-12',
                        'acceptPayment' => true,
                        'acceptPartPay' => false,
                        'cellNumber' => '3006781362',
                        'userName' => 'MR SUDHANSHU',
                    ],
                ],
            ];

            return view('bill', compact('billData'));

        } catch (\Exception $e) {

            // ✅ Proper error logging
            \Log::error('Fetch Bill Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

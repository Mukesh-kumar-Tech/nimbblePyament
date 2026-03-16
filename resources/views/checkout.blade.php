<!DOCTYPE html>
<html>
<head>
    <title>Nimbbl Checkout</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body>

    <button id="createOrderBtn">Create Order</button>
    <button id="payBtn">Payment</button>

<script type="module">

import Checkout from "https://cdn.jsdelivr.net/npm/nimbbl_sonic@latest";

let orderInfo = {
    token: null,
    order_id: null
};

/**
 * Helper to log requests in the specific format requested
 */
function logRequest(url, method, headers, body) {
    const now = new Date();
    const timestamp = now.getFullYear() + '-' + 
                      String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                      String(now.getDate()).padStart(2, '0') + ' ' + 
                      String(now.getHours()).padStart(2, '0') + ':' + 
                      String(now.getMinutes()).padStart(2, '0') + ':' + 
                      String(now.getSeconds()).padStart(2, '0');

    console.log(`[${timestamp}] REQUEST`);
    console.log(`URL     : ${url}`);
    console.log(`METHOD  : ${method}`);
    console.log(`HEADERS : ${JSON.stringify(headers, null, 4)}`);
    console.log(`BODY    : ${JSON.stringify(body, null, 4)}`);
    console.log('--------------------------------------------------------------------------------');
}

// Method 1: Create Order
document.getElementById("createOrderBtn").onclick = async function(){

    const url = '/create-order';
    const method = 'POST';
    const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    };
    const body = {}; // No body needed for this specific simple POST

    logRequest(url, method, headers, body);

    try {
        const response = await fetch(url, {
            method: method,
            headers: headers,
            body: JSON.stringify(body)
        });

        const data = await response.json();

        if (data.status) {
            orderInfo.token = data.token;
            orderInfo.order_id = data.order_id;
            
            alert("Order created successfully! You can now proceed to payment.");
            console.log("Response Data:", data);
        } else {
            alert("Order creation failed: " + data.message);
        }
    } catch (error) {
        console.error("Error creating order:", error);
        alert("An error occurred while creating the order.");
    }

};

// Method 2: Payment

document.getElementById("payBtn").onclick = function () {

    const checkout = new Checkout({
        token: orderInfo.token,
        //token: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJ1cm46bmltYmJsIiwiaWF0IjoxNzczMzk3ODQ0LCJleHAiOjE3NzMzOTkwNDQsInR5cGUiOiJvcmRlciIsInN1Yl9tZXJjaGFudF9pZCI6NTgwOTksIm9yZGVyX2lkIjoib19OV2xyUERMbVhWRDZna0dwIn0.uDS-FdJfgyUFPU2G158A8oPtlDS6lIe4nPSMeea9mVc"
        });

    checkout.open({
        order_id: orderInfo.order_id,
        
        //order_id: "o_NWlrPDLmXVD6gkGp",

        callback_handler: async function (response) {

            console.log("Nimbbl Response:", response);

            // STEP 1: store response payload in another variable
            const paymentData = {
                payload: response.payload
            };

            console.log("Payment Data:", paymentData);

            // STEP 2: send this variable to backend
            const verifyResponse = await fetch("/payment/verify", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute("content")
                },
                body: JSON.stringify(paymentData)
            });

            const result = await verifyResponse.json();

            console.log("Verify Result:", result);

            if (result.success) {
                alert("Payment Verified Successfully");
            } else {
                alert("Payment Verification Failed");
            }
        }
    });
};





// document.getElementById("payBtn").onclick = function(){

//     // if (!orderInfo.token || !orderInfo.order_id) {
//     //     alert("Please create an order first.");
//     //     return;
//     // }

//     logRequest('Nimbbl SDK Open', 'SDK_CALL', {}, { order_id: orderInfo.order_id, token: 'REDACTED' });

//     const checkout = new Checkout({
//         token: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJ1cm46bmltYmJsIiwiaWF0IjoxNzczMzc4MDA4LCJleHAiOjE3NzMzNzkyMDgsInR5cGUiOiJvcmRlciIsInN1Yl9tZXJjaGFudF9pZCI6NTgwOTksIm9yZGVyX2lkIjoib18xNzkweTVMUjN2Vk1rcTJtIn0.2lr0vDlZhpizKbHO6ctWHBY1mNMi3cUlPbbEf_lmTmk"
//     });

//     checkout.open({
//         order_id: "o_1790y5LR3vVMkq2m",
//         callback_handler: async function(response){
            
//             const callbackUrl = '/payment-callback';
//             const callbackHeaders = {
//                 'Content-Type': 'application/json',
//                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
//             };
            
//             logRequest(callbackUrl, 'POST', callbackHeaders, response);

//             await fetch(callbackUrl, {
//                 method: 'POST',
//                 headers: callbackHeaders,
//                 body: JSON.stringify(response)
//             });

//             alert("Payment completed");
//         }
//     });

// };

</script>

</body>
</html>

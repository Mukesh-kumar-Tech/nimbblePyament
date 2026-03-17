<!DOCTYPE html>
<html>
<head>
    <title>Bill Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: #f5f7fa;">

<div class="container mt-5">

    <div class="row justify-content-center">
        <div class="col-md-6">

            <h3 class="text-center mb-4">⚡ Electricity Bill</h3>

            @if(isset($billData['success']) && $billData['success'])

                @php
                    $bill = $billData['data'][0] ?? null;
                @endphp

                <div class="card shadow-lg border-0 rounded-4">
                    
                    <div class="card-header bg-success text-white text-center rounded-top-4">
                        <h5 class="mb-0">Bill Details</h5>
                    </div>

                    <div class="card-body">

                        <div class="mb-3">
                            <strong>👤 Customer Name:</strong>
                            <div class="text-muted">{{ $bill['userName'] ?? '-' }}</div>
                        </div>

                        <div class="mb-3">
                            <strong>📱 Consumer Number:</strong>
                            <div class="text-muted">{{ $bill['cellNumber'] ?? '-' }}</div>
                        </div>

                        <div class="mb-3">
                            <strong>📅 Bill Date:</strong>
                            <div class="text-muted">{{ $bill['billdate'] ?? '-' }}</div>
                        </div>

                        <div class="mb-3">
                            <strong>⏳ Due Date:</strong>
                            <div class="text-danger fw-bold">{{ $bill['dueDate'] ?? '-' }}</div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center">
                            <h4>Total Amount</h4>
                            <h3 class="text-success">₹ {{ $bill['billAmount'] ?? '0' }}</h3>
                        </div>

                        <div class="text-center mt-4">
                            <form id="payBillForm" method="POST">
                                @csrf

                                <input type="hidden" id="bill_amount" name="amount" value="{{ $bill['billAmount'] ?? '' }}">
                                <input type="hidden" name="consumer_number" value="{{ $bill['cellNumber'] ?? '' }}">
                                <input type="hidden" id="consumer_name" name="name" value="{{ $bill['userName'] ?? '' }}">

                                <button type="submit" id="payBillBtn" class="btn btn-success w-100 py-2">
                                    💳 Pay Bill
                                </button>
                            </form>
                        </div>

                    </div>
                </div>

            @else

                <div class="alert alert-danger text-center shadow">
                    ❌ {{ $billData['message'] ?? 'Something went wrong' }}
                </div>

            @endif

        </div>
    </div>

</div>

</body>
</html>

<script type="module">
import Checkout from "https://cdn.jsdelivr.net/npm/nimbbl_sonic@latest";

document.getElementById('payBillForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    let btn = document.getElementById('payBillBtn');
    btn.innerText = 'Processing...';
    btn.disabled = true;

    const csrfToken = document.querySelector('input[name="_token"]').value;
    const amount = document.getElementById('bill_amount').value;
    const userName = document.getElementById('consumer_name').value;

    const body = {
        amount: amount,
        user_name: userName
    };

    try {
        const response = await fetch('/create-order', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(body)
        });

        const data = await response.json();

        if (data.status) {
            const checkout = new Checkout({ token: data.token });
            checkout.open({
                order_id: data.order_id,
                callback_handler: async function (paymentResponse) {
                    const paymentData = { payload: paymentResponse.payload };
                    const verifyResponse = await fetch("/payment/verify", {
                        method: "POST",
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(paymentData)
                    });
                    const result = await verifyResponse.json();
                    if (result.success) {
                        alert(result.message);
                        window.location.href = '/home';
                    } else {
                        alert(result.message);
                        btn.innerText = 'Pay Bill';
                        btn.disabled = false;
                    }
                }
            });
            setTimeout(() => {
                btn.innerText = 'Pay Bill';
                btn.disabled = false;
            }, 2000);
        } else {
            alert("Order creation failed: " + data.message);
            btn.innerText = 'Pay Bill';
            btn.disabled = false;
        }
    } catch (error) {
        console.error("Error creating order:", error);
        alert("An error occurred while creating the order.");
        btn.innerText = 'Pay Bill';
        btn.disabled = false;
    }
});
</script>

    </div>
</div> 
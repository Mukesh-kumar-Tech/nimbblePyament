<div class="container" style="padding: 20px; font-family: sans-serif;">
    <div class="card" style="border: 1px solid #ddd; padding: 20px; border-radius: 8px; background: #f9f9f9;">
        <h2 style="color: #2c3e50;">Bill Details</h2>
        <hr>

        <div style="margin-bottom: 10px;">
            <strong>Consumer Name:</strong> XXXXX
        </div>

        <div style="margin-bottom: 10px;">
            <strong>Bill Amount:</strong> 
            <span style="color: #e74c3c; font-weight: bold;">₹550.0</span>
        </div>

        <div style="margin-bottom: 10px;">
            <strong>Due Date:</strong> 11-Mar-2024
        </div>

        <div style="margin-bottom: 10px;">
            <strong>Bill Date:</strong> 04-Mar-2024
        </div>

        <div style="margin-bottom: 10px;">
            <strong>Net Amount:</strong> ₹550.0
        </div>


        <!-- Pay Bill Button -->
        <form id="payBillForm" style="margin-top:20px;">
            @csrf
            <input type="hidden" id="bill_amount" value="550.0">
            <input type="hidden" id="consumer_name" value="XXXXX">
            
            <button type="submit" id="payBillBtn"
                style="padding: 10px 20px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Pay Bill
            </button>

            <a href="/home"
               style="margin-left:10px; padding: 10px 15px; background: #3498db; color: white; text-decoration: none; border-radius: 4px;">
               Back to Home
            </a>

        </form>

<script type="module">
import Checkout from "https://cdn.jsdelivr.net/npm/nimbbl_sonic@latest";

document.getElementById('payBillForm').addEventListener('submit', async function(e) {
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
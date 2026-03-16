<!DOCTYPE html>
<html>
<head>
<title>Order Details</title>
</head>

<body>

<h2>Order Details</h2>

<p><strong>Order ID:</strong> {{ $orderData['order_id'] }}</p>

<p><strong>Invoice ID:</strong> {{ $orderData['invoice_id'] }}</p>

<p><strong>Status:</strong> {{ $orderData['status'] }}</p>

<p><strong>Total Amount:</strong> {{ $orderData['total_amount'] }}</p>

<h3>User Details</h3>

<p>Name: {{ $orderData['user']['first_name'] }} {{ $orderData['user']['last_name'] }}</p>

<p>Email: {{ $orderData['user']['email'] }}</p>

<p>Mobile: {{ $orderData['user']['mobile_number'] }}</p>

</body>
</html>
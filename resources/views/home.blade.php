<form method="POST" action="/fetch-bill">
    @csrf
    <input type="text" name="consumer_number" placeholder="Enter Consumer Number">
    <button type="submit">Fetch Bill</button>
</form>
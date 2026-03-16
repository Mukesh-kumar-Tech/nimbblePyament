<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = [
        'invoice_id',
        'order_id',
        'transaction_id',
        'consumer_number',
        'amount',
        'currency',
        'status',
        'payment_type',
        'signature',
        'raw_response'
    ];
}

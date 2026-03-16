<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NimblePayment extends Model
{
    /** @use HasFactory<\Database\Factories\NimblePaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'mobile_number',
        'amount',
        'invoice_id',
        'description',
        'currency',
    ];
}

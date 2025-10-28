<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionMitraItem extends Model
{
    protected $fillable = ['transaction_mitra_id', 'product_id', 'product_name', 'price', 'quantity', 'subtotal'];

    public function transaction()
    {
        return $this->belongsTo(TransactionMitra::class, 'transaction_mitra_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionMitra extends Model
{
    protected $fillable = ['transaction_code', 'mitra_id', 'mitra_name', 'total_price', 'transaction_date', 'status'];

    public function items()
    {
        return $this->hasMany(TransactionMitraItem::class);
    }
}

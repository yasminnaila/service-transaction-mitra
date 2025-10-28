<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TransactionMitra;
use App\Models\TransactionMitraItem;

class TransactionMitraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $transaction = TransactionMitra::create([
            'transaction_code' => 'TRXM-' . strtoupper(\Str::random(8)),
            'mitra_id' => 1,
            'mitra_name' => 'Mitra Satu',
            'total_price' => 200000,
            'transaction_date' => now(),
            'status' => 'pending',
        ]);

        $transaction->items()->createMany([
            [
                'product_id' => 10,
                'product_name' => 'Pupuk A',
                'price' => 100000,
                'quantity' => 1,
                'subtotal' => 100000,
            ],
            [
                'product_id' => 12,
                'product_name' => 'Bibit Jagung',
                'price' => 50000,
                'quantity' => 2,
                'subtotal' => 100000,
            ]
        ]);
    }
}

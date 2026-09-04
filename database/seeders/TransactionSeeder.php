<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transactions;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Transactions::create([
            'client_id' => 1,
            'type' => 'deposit',
            'amount' => 10000,
            'instrument' => null,
            'quantity' => null,
            'price' => null,
        ]);

        Transactions::create([
            'client_id' => 1,
            'type' => 'buy',
            'amount' => 2000,
            'instrument' => 'AAPL',
            'quantity' => 10,
            'price' => 200,
        ]);

        Transactions::create([
            'client_id' => 1,
            'type' => 'buy',
            'amount' => 1500,
            'instrument' => 'TSLA',
            'quantity' => 5,
            'price' => 300,
        ]);

        Transactions::create([
            'client_id' => 1,
            'type' => 'sell',
            'amount' => 750,
            'instrument' => 'AAPL',
            'quantity' => 3,
            'price' => 250,
        ]);

        Transactions::create([
            'client_id' => 1,
            'type' => 'withdrawal',
            'amount' => 1000,
            'instrument' => null,
            'quantity' => null,
            'price' => null,
        ]);

        // Transactions for client 2

        Transactions::create([
            'client_id' => 2,
            'type' => 'deposit',
            'amount' => 20000,
            'instrument' => null,
            'quantity' => null,
            'price' => null,
        ]);

        Transactions::create([
            'client_id' => 2,
            'type' => 'buy',
            'amount' => 5000,
            'instrument' => 'MSFT',
            'quantity' => 10,
            'price' => 500,
        ]);
    }
}

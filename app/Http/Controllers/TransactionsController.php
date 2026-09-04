<?php

namespace App\Http\Controllers;

use App\Models\Transactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Client;

class TransactionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $transactions = Transactions::with('client')
            ->latest()
            ->get();

        return response()->json($transactions);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        

        $validated = $request->validate([
            'client_id' => [
                'required',
                'integer',
                'exists:clients,id',
            ],

            'type' => [
                'required',
                'in:deposit,withdrawal,buy,sell',
            ],

            'amount' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'instrument' => [
                'nullable',
                'string',
                'max:20',
            ],

            'quantity' => [
                'nullable',
                'integer',
                'gt:0',
            ],

            'price' => [
                'nullable',
                'numeric',
                'gt:0',
            ],
        ]);
        

        return DB::transaction(function () use ($validated) {
            

            /*
             * Lock the client row.
             */
            $client = Client::where('id', $validated['client_id'])
                ->lockForUpdate()
                ->first();


            /*
             * DEPOSIT
             */
            if ($validated['type'] === 'deposit') {

                if (
                    !isset($validated['amount']) ||
                    isset($validated['instrument']) ||
                    isset($validated['quantity']) ||
                    isset($validated['price'])
                ) {
                    throw ValidationException::withMessages([
                        'transaction' => 'A deposit requires only an amount.',
                    ]);
                }

                $transaction = Transactions::create([
                    'client_id' => $client->id,
                    'type' => 'deposit',
                    'amount' => $validated['amount'],
                    'instrument' => null,
                    'quantity' => null,
                    'price' => null,
                ]);

                return response()->json([
                    'message' => 'Deposit created successfully.',
                    'transaction' => $transaction,
                ], 201);
            }


            /*
             * Calculate current cash balance.
             */
            $cashBalance = Transactions::where('client_id', $client->id)
                ->selectRaw("
                    COALESCE(SUM(
                        CASE
                            WHEN type IN ('deposit', 'sell') THEN amount
                            WHEN type IN ('withdrawal', 'buy') THEN -amount
                            ELSE 0
                        END
                    ), 0) as balance
                ")
                ->value('balance');


            /*
             * WITHDRAWAL
             */
            if ($validated['type'] === 'withdrawal') {

                if (
                    !isset($validated['amount']) ||
                    isset($validated['instrument']) ||
                    isset($validated['quantity']) ||
                    isset($validated['price'])
                ) {
                    throw ValidationException::withMessages([
                        'transaction' => 'A withdrawal requires only an amount.',
                    ]);
                }

                if ($validated['amount'] > $cashBalance) {
                    throw ValidationException::withMessages([
                        'amount' => "Insufficient funds. Available cash: {$cashBalance}.",
                    ]);
                }

                $transaction = Transactions::create([
                    'client_id' => $client->id,
                    'type' => 'withdrawal',
                    'amount' => $validated['amount'],
                    'instrument' => null,
                    'quantity' => null,
                    'price' => null,
                ]);

                return response()->json([
                    'message' => 'Withdrawal created successfully.',
                    'transaction' => $transaction,
                ], 201);
            }


            /*
             * BUY / SELL require instrument, quantity and price.
             */
            if (
                empty($validated['instrument']) ||
                !isset($validated['quantity']) ||
                !isset($validated['price'])
            ) {
                throw ValidationException::withMessages([
                    'transaction' => 'Buy and sell transactions require instrument, quantity and price.',
                ]);
            }


            /*
             * Calculate amount from quantity × price.
             *
             */
            $amount = round(
                $validated['quantity'] * $validated['price'],
                2
            );


            /*
             * BUY
             */
            if ($validated['type'] === 'buy') {

                if ($amount > $cashBalance) {
                    throw ValidationException::withMessages([
                        'amount' => "Insufficient funds. Required: {$amount}. Available: {$cashBalance}.",
                    ]);
                }

                $transaction = Transactions::create([
                    'client_id' => $client->id,
                    'type' => 'buy',
                    'amount' => $amount,
                    'instrument' => $validated['instrument'],
                    'quantity' => $validated['quantity'],
                    'price' => $validated['price'],
                ]);

                return response()->json([
                    'message' => 'Buy transaction created successfully.',
                    'transaction' => $transaction,
                ], 201);
            }


            /*
             * SELL
             */

            $ownedQuantity = Transactions::where('client_id', $client->id)
                ->where('instrument', $validated['instrument'])
                ->selectRaw("
                    COALESCE(SUM(
                        CASE
                            WHEN type = 'buy' THEN quantity
                            WHEN type = 'sell' THEN -quantity
                            ELSE 0
                        END
                    ), 0) as quantity
                ")
                ->value('quantity');

            
            if ($validated['quantity'] > $ownedQuantity) {
            
                throw ValidationException::withMessages([
                    'quantity' =>
                        "Insufficient holdings. You own {$ownedQuantity} {$validated['instrument']} shares."
                ]);
            }


            $transaction = Transactions::create([
                'client_id' => $client->id,
                'type' => 'sell',
                'amount' => $amount,
                'instrument' => $validated['instrument'],
                'quantity' => $validated['quantity'],
                'price' => $validated['price'],
            ]);


            return response()->json([
                'message' => 'Sell transaction created successfully.',
                'transaction' => $transaction,
            ], 201);
        });
    }
    /**
     * Show one client's current account state.
     */
    public function clientSummary(Client $client)
    {
        $cashBalance = Transactions::where('client_id', $client->id)
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN type IN ('deposit', 'sell') THEN amount
                        WHEN type IN ('withdrawal', 'buy') THEN -amount
                        ELSE 0
                    END
                ), 0) as balance
            ")
            ->value('balance');


        $holdings = Transactions::where('client_id', $client->id)
            ->whereNotNull('instrument')
            ->select(
                'instrument',
                DB::raw("
                    SUM(
                        CASE
                            WHEN type = 'buy' THEN quantity
                            WHEN type = 'sell' THEN -quantity
                            ELSE 0
                        END
                    ) as quantity
                ")
            )
            ->groupBy('instrument')
            ->havingRaw('quantity > 0')
            ->get();


        return response()->json([
            'client' => $client,
            'cash_balance' => $cashBalance,
            'holdings' => $holdings,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Transactions $transactions)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transactions $transactions)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transactions $transactions)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transactions $transactions)
    {
        //
    }
}

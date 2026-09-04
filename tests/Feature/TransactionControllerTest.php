<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Transactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Deposit
    |--------------------------------------------------------------------------
    */

    public function test_client_can_make_a_deposit(): void
    {
        $client = Client::create([
            'name' => 'John Smith',
        ]);

        $response = $this->postJson('/transactions', [
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 10000,
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Deposit created successfully.',
            ]);

        $this->assertDatabaseHas('transactions', [
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 10000,
        ]);
    }

    public function test_deposit_amount_must_be_positive(): void
    {
        $client = Client::create([
            'name' => 'John Smith',
        ]);

        $response = $this->postJson('/transactions', [
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 0,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('transactions', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Withdrawal
    |--------------------------------------------------------------------------
    */

    public function test_client_can_withdraw_money(): void
    {
        $client = Client::create([
            'name' => 'John Smith',
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 10000,
        ]);

        $response = $this->postJson('/transactions', [
            'client_id' => $client->id,
            'type' => 'withdrawal',
            'amount' => 3000,
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Withdrawal created successfully.',
            ]);

        $this->assertDatabaseHas('transactions', [
            'client_id' => $client->id,
            'type' => 'withdrawal',
            'amount' => 3000,
        ]);
    }

    public function test_client_cannot_withdraw_more_money_than_available(): void
    {
        $client = Client::create([
            'name' => 'John Smith',
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 10000,
        ]);

        $response = $this->postJson('/transactions', [
            'client_id' => $client->id,
            'type' => 'withdrawal',
            'amount' => 15000,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('transactions', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Buy
    |--------------------------------------------------------------------------
    */

    public function test_client_can_buy_shares(): void
    {
        $client = Client::create([
            'name' => 'John Smith',
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 10000,
        ]);

        $response = $this->postJson('/transactions', [
            'client_id' => $client->id,
            'type' => 'buy',
            'instrument' => 'AAPL',
            'quantity' => 10,
            'price' => 200,
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Buy transaction created successfully.',
            ]);

        $this->assertDatabaseHas('transactions', [
            'client_id' => $client->id,
            'type' => 'buy',
            'instrument' => 'AAPL',
            'quantity' => 10,
            'price' => 200,
            'amount' => 2000,
        ]);
    }

    public function test_buy_amount_is_calculated_by_server(): void
    {
        $client = Client::create([
            'name' => 'John Smith',
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 10000,
        ]);

        $response = $this->postJson('/transactions', [
            'client_id' => $client->id,
            'type' => 'buy',
            'instrument' => 'AAPL',
            'quantity' => 10,
            'price' => 200,

            // Намерно погрешен amount
            'amount' => 1,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('transactions', [
            'client_id' => $client->id,
            'type' => 'buy',
            'amount' => 2000,
        ]);

        $this->assertDatabaseMissing('transactions', [
            'client_id' => $client->id,
            'type' => 'buy',
            'amount' => 1,
        ]);
    }

    public function test_client_cannot_buy_more_than_available_cash(): void
    {
        $client = Client::create([
            'name' => 'John Smith',
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 1000,
        ]);

        $response = $this->postJson('/transactions', [
            'client_id' => $client->id,
            'type' => 'buy',
            'instrument' => 'AAPL',
            'quantity' => 10,
            'price' => 200,
        ]);

        $response->assertStatus(422);

        // Само deposit постои.
        $this->assertDatabaseCount('transactions', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Sell
    |--------------------------------------------------------------------------
    */

    public function test_client_can_sell_shares(): void
    {
        $client = Client::create([
            'name' => 'John Smith',
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 10000,
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'buy',
            'amount' => 2000,
            'instrument' => 'AAPL',
            'quantity' => 10,
            'price' => 200,
        ]);

        $response = $this->postJson('/transactions', [
            'client_id' => $client->id,
            'type' => 'sell',
            'instrument' => 'AAPL',
            'quantity' => 3,
            'price' => 250,
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Sell transaction created successfully.',
            ]);

        $this->assertDatabaseHas('transactions', [
            'client_id' => $client->id,
            'type' => 'sell',
            'instrument' => 'AAPL',
            'quantity' => 3,
            'price' => 250,
            'amount' => 750,
        ]);
    }

    public function test_client_cannot_sell_more_shares_than_owned(): void
    {
        $client = Client::create([
            'name' => 'John Smith',
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 10000,
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'buy',
            'amount' => 2000,
            'instrument' => 'AAPL',
            'quantity' => 10,
            'price' => 200,
        ]);

        $response = $this->postJson('/transactions', [
            'client_id' => $client->id,
            'type' => 'sell',
            'instrument' => 'AAPL',
            'quantity' => 11,
            'price' => 250,
        ]);

        $response->assertStatus(422);

        // Deposit + buy = 2 transactions.
        $this->assertDatabaseCount('transactions', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Client isolation
    |--------------------------------------------------------------------------
    */

    public function test_one_client_cannot_use_another_clients_money(): void
    {
        $client1 = Client::create([
            'name' => 'John Smith',
        ]);

        $client2 = Client::create([
            'name' => 'Mark Johnson',
        ]);

        Transactions::create([
            'client_id' => $client1->id,
            'type' => 'deposit',
            'amount' => 10000,
        ]);

        // Client 2 нема пари.
        $response = $this->postJson('/transactions', [
            'client_id' => $client2->id,
            'type' => 'buy',
            'instrument' => 'AAPL',
            'quantity' => 10,
            'price' => 500,
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('transactions', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Summary
    |--------------------------------------------------------------------------
    */

    public function test_client_summary_returns_correct_cash_balance_and_holdings(): void
    {
        $client = Client::create([
            'name' => 'John Smith',
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 10000,
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'buy',
            'amount' => 2000,
            'instrument' => 'AAPL',
            'quantity' => 10,
            'price' => 200,
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'sell',
            'amount' => 750,
            'instrument' => 'AAPL',
            'quantity' => 3,
            'price' => 250,
        ]);

        Transactions::create([
            'client_id' => $client->id,
            'type' => 'withdrawal',
            'amount' => 1000,
        ]);

        $response = $this->getJson(
            "/clients/{$client->id}/summary"
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('cash_balance', 7750)
            ->assertJsonPath('holdings.0.instrument', 'AAPL')
            ->assertJsonPath('holdings.0.quantity', 7);
    }
}
<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_be_created(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 'John Smith',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Client created successfully',
            ]);

        $this->assertDatabaseHas('clients', [
            'name' => 'John Smith',
        ]);
    }

    public function test_client_name_is_required(): void
    {
        $response = $this->postJson('/api/clients', []);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_client_name_must_be_a_string(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 12345,
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('clients', 0);
    }


    public function test_duplicate_client_name_is_not_allowed(): void
    {
        Client::create([
            'name' => 'John Smith',
        ]);

        $response = $this->postJson('/api/clients', [
            'name' => 'John Smith',
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('clients', 1);
    }
}
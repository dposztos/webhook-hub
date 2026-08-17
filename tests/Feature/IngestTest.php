<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Group;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngestTest extends TestCase
{
    use RefreshDatabase;

    private function endpoint(): Endpoint
    {
        $customers = Group::create(['name' => 'Customers', 'slug' => 'customers']);
        $abc = Group::create(['name' => 'ACME Ltd.', 'slug' => 'acme', 'parent_id' => $customers->id]);

        return Endpoint::create(['name' => 'Orders', 'slug' => 'orders', 'group_id' => $abc->id]);
    }

    public function test_stores_an_incoming_request_on_the_group_path(): void
    {
        $endpoint = $this->endpoint();
        $payload = ['event' => 'order.paid', 'order' => ['id' => 'ORD-1', 'total' => 24990]];

        $response = $this->postJson("/u/customers/acme/orders/{$endpoint->secret}", $payload);

        $response->assertOk();

        $message = Message::firstOrFail();
        $this->assertSame('POST', $message->method);
        $this->assertEquals($payload, $message->body_json);
        $this->assertSame(1, $endpoint->fresh()->messages_count);
    }

    public function test_rossz_titokra_404_a_valasz_es_nem_tarol(): void
    {
        $this->endpoint();

        $this->post('/u/customers/acme/orders/wrongSecret', ['a' => 1])->assertNotFound();

        $this->assertSame(0, Message::count());
    }

    public function test_kikapcsolt_endpoint_nem_fogad(): void
    {
        $endpoint = $this->endpoint();
        $endpoint->update(['enabled' => false]);

        $this->post("/u/customers/acme/orders/{$endpoint->secret}")->assertNotFound();
    }

    public function test_trailing_status_code_overrides_the_response_and_the_path_suffix_is_stored(): void
    {
        $endpoint = $this->endpoint();

        $this->post("/u/customers/acme/orders/{$endpoint->secret}/callback/418")
            ->assertStatus(418);

        $this->assertSame('callback/418', Message::firstOrFail()->path_suffix);
    }

    public function test_form_kodolt_testet_is_strukturaltan_tarol(): void
    {
        $endpoint = $this->endpoint();

        $this->post("/u/customers/acme/orders/{$endpoint->secret}", ['event' => 'ping', 'db' => '3']);

        // jsonb does not preserve key order, hence assertEquals.
        $this->assertEquals(['event' => 'ping', 'db' => '3'], Message::firstOrFail()->body_json);
    }

    public function test_deleting_a_message_restores_the_counter(): void
    {
        $endpoint = $this->endpoint();
        $user = \App\Models\User::factory()->create();

        $this->post("/u/customers/acme/orders/{$endpoint->secret}", ['a' => 1]);
        $this->post("/u/customers/acme/orders/{$endpoint->secret}", ['a' => 2]);
        $this->assertSame(2, $endpoint->fresh()->messages_count);

        $uuid = Message::orderByDesc('id')->value('uuid');
        $this->actingAs($user)->deleteJson("/api/messages/{$uuid}")->assertOk();

        $this->assertSame(1, $endpoint->fresh()->messages_count);
        $this->assertSame(1, Message::count());
    }
}

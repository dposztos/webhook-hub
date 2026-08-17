<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnreadTest extends TestCase
{
    use RefreshDatabase;

    private Endpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $group = Group::create(['name' => 'Customers', 'slug' => 'customers']);
        $this->endpoint = Endpoint::create(['name' => 'Hook', 'slug' => 'hook', 'group_id' => $group->id]);
    }

    private function send(array $payload = ['a' => 1]): void
    {
        $this->postJson("/u/customers/hook/{$this->endpoint->secret}", $payload);
    }

    public function test_an_incoming_message_starts_unread(): void
    {
        $this->send();

        $this->assertNull(Message::firstOrFail()->read_at);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/messages')
            ->assertOk()
            ->assertJsonPath('data.0.read', false);
    }

    public function test_opening_a_message_marks_it_read(): void
    {
        $this->send();
        $uuid = Message::firstOrFail()->uuid;

        $this->actingAs(User::factory()->create())
            ->getJson("/api/messages/{$uuid}")
            ->assertOk();

        $this->assertNotNull(Message::firstOrFail()->read_at);
    }

    public function test_message_can_be_marked_unread_again(): void
    {
        $this->send();
        $uuid = Message::firstOrFail()->uuid;
        $user = User::factory()->create();

        $this->actingAs($user)->getJson("/api/messages/{$uuid}");
        $this->actingAs($user)->postJson("/api/messages/{$uuid}/unread")->assertOk();

        $this->assertNull(Message::firstOrFail()->read_at);
    }

    public function test_the_tree_sums_unread_counts_onto_the_group(): void
    {
        $this->send();
        $this->send();

        $tree = $this->actingAs(User::factory()->create())->getJson('/api/tree')->assertOk();

        $tree->assertJsonPath('groups.0.unread_count', 2);
        $tree->assertJsonPath('groups.0.endpoints.0.unread_count', 2);
    }

    public function test_mark_all_read_for_an_endpoint(): void
    {
        $this->send();
        $this->send();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/messages/read-all', ['endpoint_id' => $this->endpoint->id])
            ->assertOk()
            ->assertJson(['marked' => 2]);

        $this->assertSame(0, Message::unread()->count());
    }

    public function test_filtering_to_unread_only(): void
    {
        $this->send(['elso' => true]);
        $first = Message::firstOrFail();
        $user = User::factory()->create();
        $this->actingAs($user)->getJson("/api/messages/{$first->uuid}");

        $this->send(['masodik' => true]);

        $this->actingAs($user)
            ->getJson('/api/messages?only=unread')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.read', false);
    }
}

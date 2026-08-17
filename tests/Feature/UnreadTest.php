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

        $group = Group::create(['name' => 'Ügyfelek', 'slug' => 'ugyfelek']);
        $this->endpoint = Endpoint::create(['name' => 'Hook', 'slug' => 'hook', 'group_id' => $group->id]);
    }

    private function send(array $payload = ['a' => 1]): void
    {
        $this->postJson("/u/ugyfelek/hook/{$this->endpoint->secret}", $payload);
    }

    public function test_a_beerkezo_uzenet_olvasatlan(): void
    {
        $this->send();

        $this->assertNull(Message::firstOrFail()->read_at);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/messages')
            ->assertOk()
            ->assertJsonPath('data.0.read', false);
    }

    public function test_a_megnyitas_olvasottnak_jeloli(): void
    {
        $this->send();
        $uuid = Message::firstOrFail()->uuid;

        $this->actingAs(User::factory()->create())
            ->getJson("/api/messages/{$uuid}")
            ->assertOk();

        $this->assertNotNull(Message::firstOrFail()->read_at);
    }

    public function test_visszatehető_olvasatlanra(): void
    {
        $this->send();
        $uuid = Message::firstOrFail()->uuid;
        $user = User::factory()->create();

        $this->actingAs($user)->getJson("/api/messages/{$uuid}");
        $this->actingAs($user)->postJson("/api/messages/{$uuid}/unread")->assertOk();

        $this->assertNull(Message::firstOrFail()->read_at);
    }

    public function test_a_fa_az_olvasatlanokat_szamolja_a_csoportra_is(): void
    {
        $this->send();
        $this->send();

        $tree = $this->actingAs(User::factory()->create())->getJson('/api/tree')->assertOk();

        $tree->assertJsonPath('groups.0.unread_count', 2);
        $tree->assertJsonPath('groups.0.endpoints.0.unread_count', 2);
    }

    public function test_mind_olvasottnak_jelolese_endpointra(): void
    {
        $this->send();
        $this->send();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/messages/read-all', ['endpoint_id' => $this->endpoint->id])
            ->assertOk()
            ->assertJson(['marked' => 2]);

        $this->assertSame(0, Message::unread()->count());
    }

    public function test_szures_csak_az_olvasatlanokra(): void
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

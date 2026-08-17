<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreeMoveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function move(array $payload)
    {
        return $this->postJson('/api/tree/move', $payload);
    }

    public function test_moving_an_endpoint_to_another_group_changes_its_url(): void
    {
        $a = Group::create(['name' => 'A', 'slug' => 'a']);
        $b = Group::create(['name' => 'B', 'slug' => 'b']);
        $endpoint = Endpoint::create(['name' => 'Hook', 'slug' => 'hook', 'group_id' => $a->id]);

        $this->assertStringContainsString('/u/a/hook/', $endpoint->url());

        $this->move(['type' => 'endpoint', 'id' => $endpoint->id, 'parent_id' => $b->id])->assertOk();

        $this->assertStringContainsString('/u/b/hook/', $endpoint->fresh()->url());
    }

    public function test_moving_a_group_updates_the_urls_of_its_children(): void
    {
        $customers = Group::create(['name' => 'Customers', 'slug' => 'customers']);
        $archive = Group::create(['name' => 'Archive', 'slug' => 'archive']);
        $abc = Group::create(['name' => 'ABC', 'slug' => 'abc', 'parent_id' => $customers->id]);
        $endpoint = Endpoint::create(['name' => 'Order', 'slug' => 'order', 'group_id' => $abc->id]);

        $this->move(['type' => 'group', 'id' => $abc->id, 'parent_id' => $archive->id])->assertOk();

        $this->assertStringContainsString('/u/archive/abc/order/', $endpoint->fresh()->url());
    }

    public function test_a_name_clash_yields_a_numbered_slug(): void
    {
        $a = Group::create(['name' => 'A', 'slug' => 'a']);
        $b = Group::create(['name' => 'B', 'slug' => 'b']);
        Endpoint::create(['name' => 'Hook', 'slug' => 'hook', 'group_id' => $b->id]);
        $moved = Endpoint::create(['name' => 'Hook', 'slug' => 'hook', 'group_id' => $a->id]);

        $response = $this->move(['type' => 'endpoint', 'id' => $moved->id, 'parent_id' => $b->id]);

        $response->assertOk()->assertJson(['slug_changed' => true, 'slug' => 'hook-2']);
        $this->assertSame('hook-2', $moved->fresh()->slug);
    }

    public function test_a_group_cannot_be_moved_under_its_own_descendant(): void
    {
        $parent = Group::create(['name' => 'Parent', 'slug' => 'parent']);
        $child = Group::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);

        $this->move(['type' => 'group', 'id' => $parent->id, 'parent_id' => $child->id])
            ->assertStatus(422);

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_reordering_among_siblings(): void
    {
        $group = Group::create(['name' => 'G', 'slug' => 'g']);
        $first = Endpoint::create(['name' => 'First', 'slug' => 'first', 'group_id' => $group->id, 'position' => 0]);
        $second = Endpoint::create(['name' => 'Second', 'slug' => 'second', 'group_id' => $group->id, 'position' => 1]);
        $third = Endpoint::create(['name' => 'Third', 'slug' => 'third', 'group_id' => $group->id, 'position' => 2]);

        // Put the third one before the first
        $this->move(['type' => 'endpoint', 'id' => $third->id, 'parent_id' => $group->id, 'position' => 0])->assertOk();

        $order = Endpoint::where('group_id', $group->id)->orderBy('position')->pluck('slug')->all();
        $this->assertSame(['third', 'first', 'second'], $order);
    }

    public function test_promoting_to_the_root(): void
    {
        $group = Group::create(['name' => 'G', 'slug' => 'g']);
        $endpoint = Endpoint::create(['name' => 'Hook', 'slug' => 'hook', 'group_id' => $group->id]);

        $this->move(['type' => 'endpoint', 'id' => $endpoint->id, 'parent_id' => null])->assertOk();

        $this->assertNull($endpoint->fresh()->group_id);
        $this->assertStringContainsString('/u/hook/', $endpoint->fresh()->url());
    }
}

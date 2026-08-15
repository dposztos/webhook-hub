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

    public function test_endpoint_athelyezese_masik_csoportba_megvaltoztatja_az_url_t(): void
    {
        $a = Group::create(['name' => 'A', 'slug' => 'a']);
        $b = Group::create(['name' => 'B', 'slug' => 'b']);
        $endpoint = Endpoint::create(['name' => 'Hook', 'slug' => 'hook', 'group_id' => $a->id]);

        $this->assertStringContainsString('/u/a/hook/', $endpoint->url());

        $this->move(['type' => 'endpoint', 'id' => $endpoint->id, 'parent_id' => $b->id])->assertOk();

        $this->assertStringContainsString('/u/b/hook/', $endpoint->fresh()->url());
    }

    public function test_csoport_athelyezesekor_a_gyerekek_url_je_is_kovet(): void
    {
        $customers = Group::create(['name' => 'Ügyfelek', 'slug' => 'ugyfelek']);
        $archive = Group::create(['name' => 'Archív', 'slug' => 'archiv']);
        $abc = Group::create(['name' => 'ABC', 'slug' => 'abc', 'parent_id' => $customers->id]);
        $endpoint = Endpoint::create(['name' => 'Rendelés', 'slug' => 'rendeles', 'group_id' => $abc->id]);

        $this->move(['type' => 'group', 'id' => $abc->id, 'parent_id' => $archive->id])->assertOk();

        $this->assertStringContainsString('/u/archiv/abc/rendeles/', $endpoint->fresh()->url());
    }

    public function test_nevutkozes_eseten_sorszamozott_slugot_kap(): void
    {
        $a = Group::create(['name' => 'A', 'slug' => 'a']);
        $b = Group::create(['name' => 'B', 'slug' => 'b']);
        Endpoint::create(['name' => 'Hook', 'slug' => 'hook', 'group_id' => $b->id]);
        $moved = Endpoint::create(['name' => 'Hook', 'slug' => 'hook', 'group_id' => $a->id]);

        $response = $this->move(['type' => 'endpoint', 'id' => $moved->id, 'parent_id' => $b->id]);

        $response->assertOk()->assertJson(['slug_changed' => true, 'slug' => 'hook-2']);
        $this->assertSame('hook-2', $moved->fresh()->slug);
    }

    public function test_csoport_nem_kerulhet_sajat_leszarmazottja_ala(): void
    {
        $parent = Group::create(['name' => 'Szülő', 'slug' => 'szulo']);
        $child = Group::create(['name' => 'Gyerek', 'slug' => 'gyerek', 'parent_id' => $parent->id]);

        $this->move(['type' => 'group', 'id' => $parent->id, 'parent_id' => $child->id])
            ->assertStatus(422);

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_sorrend_valtoztatasa_a_testverek_kozott(): void
    {
        $group = Group::create(['name' => 'G', 'slug' => 'g']);
        $first = Endpoint::create(['name' => 'Első', 'slug' => 'elso', 'group_id' => $group->id, 'position' => 0]);
        $second = Endpoint::create(['name' => 'Második', 'slug' => 'masodik', 'group_id' => $group->id, 'position' => 1]);
        $third = Endpoint::create(['name' => 'Harmadik', 'slug' => 'harmadik', 'group_id' => $group->id, 'position' => 2]);

        // A harmadikat az első elé
        $this->move(['type' => 'endpoint', 'id' => $third->id, 'parent_id' => $group->id, 'position' => 0])->assertOk();

        $order = Endpoint::where('group_id', $group->id)->orderBy('position')->pluck('slug')->all();
        $this->assertSame(['harmadik', 'elso', 'masodik'], $order);
    }

    public function test_gyokerbe_emeles(): void
    {
        $group = Group::create(['name' => 'G', 'slug' => 'g']);
        $endpoint = Endpoint::create(['name' => 'Hook', 'slug' => 'hook', 'group_id' => $group->id]);

        $this->move(['type' => 'endpoint', 'id' => $endpoint->id, 'parent_id' => null])->assertOk();

        $this->assertNull($endpoint->fresh()->group_id);
        $this->assertStringContainsString('/u/hook/', $endpoint->fresh()->url());
    }
}

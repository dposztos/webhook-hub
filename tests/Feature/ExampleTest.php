<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_felulet_bejelentkezest_kovetel(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_bejelentkezve_betolt_a_felulet(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk();
    }
}

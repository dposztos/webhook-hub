<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ui_requires_authentication(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_the_ui_loads_once_signed_in(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk();
    }
}

<?php

namespace Tests\Feature\Web;

use App\Livewire\LifetimeMap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LifetimeMapOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_display_options(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(LifetimeMap::class)
            ->assertSee('Display options')
            ->assertSee('data-opt-single-color', false)
            ->assertSee('data-opt-weight', false)
            ->assertSee('data-color-preset="#e82127"', false);
    }
}

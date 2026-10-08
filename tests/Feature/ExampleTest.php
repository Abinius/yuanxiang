<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders(): void
    {
        Tenant::create(['slug' => 'guangcai', 'name' => '光彩云村庄', 'status' => 'active']);

        $this->get('/')->assertOk();
    }
}

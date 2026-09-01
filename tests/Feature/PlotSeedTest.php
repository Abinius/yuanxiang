<?php

namespace Tests\Feature;

use App\Models\Plot;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlotSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_plot_seeder_generates_expected_units(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);

        $this->assertSame(360, Plot::count());
        $this->assertSame(50, Plot::where('type', 'plot')->count());
        $this->assertSame(10, Plot::where('type', 'group')->count());
        $this->assertSame(300, Plot::where('type', 'plant')->count());

        // 每个拼团田下 30 株
        $group = Plot::where('type', 'group')->first();
        $this->assertSame(30, Plot::where('parent_plot_id', $group->id)->count());

        // 代码正确且唯一
        $this->assertTrue(Plot::where('code', 'FD-01')->exists());
        $this->assertTrue(Plot::where('code', 'PT-10')->exists());
        $this->assertTrue(Plot::where('code', 'Z-10-30')->exists());
        $this->assertSame(360, Plot::count()); // unique(tenant_id, code) 未冲突
    }
}

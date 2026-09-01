<?php

namespace Tests\Feature;

use App\Models\Plot;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO 分享：公开页输出 OG/description、robots.txt、sitemap.xml。
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function plot(): Plot
    {
        return Plot::where('tenant_id', $this->tenant()->id)->where('type', 'plot')->first();
    }

    public function test_adopt_page_outputs_og_and_page_description(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        $this->get("/t/{$t->slug}/adopt/{$this->plot()->id}")
            ->assertOk()
            ->assertSee('og:title', false)
            ->assertSee('og:description', false)
            ->assertSee('宁夏红寺堡枸杞认养');
    }

    public function test_trace_page_outputs_trace_description(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        $this->get("/t/{$t->slug}/trace/{$this->plot()->id}")
            ->assertOk()
            ->assertSee('og:description', false)
            ->assertSee('溯源时间线');
    }

    public function test_robots_txt(): void
    {
        $this->seed([BaseSeeder::class]);
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /my', false)
            ->assertSee('Disallow: /admin', false);
    }

    public function test_sitemap_xml(): void
    {
        $this->seed([BaseSeeder::class]);
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/adopt', false);
    }
}

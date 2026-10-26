<?php

namespace Tests\Feature;

use App\Jobs\ExecuteExtraction;
use App\Models\Extraction;
use App\ScrapingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExtractionTest extends TestCase
{
    use RefreshDatabase;

    private function input(ScrapingProvider $provider, string $mode = 'demo'): array
    {
        return ['name' => 'Mi extracción', 'mode' => $mode, 'url' => 'https://books.toscrape.com/',
            'format' => 'markdown', 'prompt' => 'Extraé productos y precios', 'url_parameter' => 'originUrl', 'input_parameters_json' => '{}'];
    }

    public function test_dashboard_and_three_modules_render_without_exposing_keys(): void
    {
        config(['integrations.providers.firecrawl.api_key' => 'secret-never-render']);
        $this->get('/')->assertOk()->assertSee('Firecrawl')->assertSee('Browse AI')->assertSee('ScrapeGraphAI')->assertDontSee('secret-never-render');
        foreach (ScrapingProvider::cases() as $provider) {
            $this->get(route('modules.show', $provider))->assertOk()->assertSee($provider->label())->assertDontSee('secret-never-render');
        }
        $this->get('/modules/unknown')->assertNotFound();
    }

    public function test_each_module_persists_its_provider_and_queues_the_selected_mode(): void
    {
        Queue::fake();
        foreach (ScrapingProvider::cases() as $provider) {
            $response = $this->post(route('extractions.store', $provider), [...$this->input($provider), 'provider' => 'injected', 'status' => 'completed', 'records' => ['fake']]);
            $extraction = Extraction::latest('id')->firstOrFail();
            $response->assertSessionHasNoErrors()->assertRedirect(route('extractions.show', [$provider, $extraction]));
            $this->assertSame($provider, $extraction->provider);
            $this->assertSame('demo', $extraction->mode);
            $this->assertSame('queued', $extraction->status);
            $this->assertNull($extraction->records);
            Queue::assertPushed(ExecuteExtraction::class, fn ($job) => $job->extractionId === $extraction->id);
        }
        $this->assertDatabaseCount('extractions', 3);
    }

    public function test_live_mode_requires_credentials_and_browse_robot(): void
    {
        Queue::fake();
        foreach (ScrapingProvider::cases() as $provider) {
            config(['integrations.providers.'.$provider->value.'.api_key' => '']);
            $this->postJson(route('extractions.store', $provider), $this->input($provider, 'live'))
                ->assertUnprocessable()->assertJsonValidationErrors('mode');
        }
        config(['integrations.providers.browse-ai.api_key' => 'test-key']);
        $this->postJson(route('extractions.store', ScrapingProvider::BrowseAi), $this->input(ScrapingProvider::BrowseAi, 'live'))
            ->assertUnprocessable()->assertJsonValidationErrors('robot_id');
        $this->assertDatabaseCount('extractions', 0);
        Queue::assertNothingPushed();
    }

    public function test_public_url_validation_rejects_local_targets_and_credentials(): void
    {
        Queue::fake();
        foreach (['http://127.0.0.1', 'http://10.0.0.1', 'http://localhost', 'http://2130706433', 'https://user:pass@example.com', 'http://example.com:3306', 'file:///etc/passwd'] as $url) {
            $this->postJson(route('extractions.store', ScrapingProvider::Firecrawl), [...$this->input(ScrapingProvider::Firecrawl), 'url' => $url])
                ->assertUnprocessable()->assertJsonValidationErrors('url');
        }
        $this->assertDatabaseCount('extractions', 0);
        Queue::assertNothingPushed();
    }

    public function test_prompt_schema_and_robot_parameters_are_validated(): void
    {
        Queue::fake();
        $this->postJson(route('extractions.store', ScrapingProvider::ScrapeGraphAi), [...$this->input(ScrapingProvider::ScrapeGraphAi), 'prompt' => '', 'schema_json' => '{ broken'])
            ->assertUnprocessable()->assertJsonValidationErrors(['prompt', 'schema_json']);
        $this->postJson(route('extractions.store', ScrapingProvider::BrowseAi), [...$this->input(ScrapingProvider::BrowseAi), 'input_parameters_json' => '[]', 'robot_id' => '../robots'])
            ->assertUnprocessable()->assertJsonValidationErrors(['input_parameters_json', 'robot_id']);
        $this->postJson(route('extractions.store', ScrapingProvider::Firecrawl), [...$this->input(ScrapingProvider::Firecrawl), 'format' => 'json', 'prompt' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('prompt');
    }

    public function test_history_and_detail_are_scoped_to_the_provider(): void
    {
        $fire = Extraction::factory()->create(['name' => 'Solo Firecrawl']);
        Extraction::factory()->create(['name' => 'Solo Browse', 'provider' => ScrapingProvider::BrowseAi]);
        $this->get(route('modules.show', ScrapingProvider::Firecrawl))->assertSee('Solo Firecrawl')->assertDontSee('Solo Browse');
        foreach (['extractions.show', 'extractions.status'] as $route) {
            $this->get(route($route, [ScrapingProvider::BrowseAi, $fire]))->assertNotFound();
        }
        $this->get(route('extractions.export', [ScrapingProvider::BrowseAi, $fire, 'json']))->assertNotFound();
    }

    public function test_exports_label_demo_preserve_json_and_escape_html_and_csv_formulas(): void
    {
        $extraction = Extraction::factory()->create(['status' => 'completed', 'records' => [
            ['value' => '=1+1', 'nested' => ['a' => 2]], ['value' => '<script>alert(1)</script>', 'other' => 'última'],
        ], 'provider_payload' => ['simulated' => true]]);
        $this->get(route('extractions.show', [$extraction->provider, $extraction]))->assertOk()
            ->assertSee('DATOS SIMULADOS')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $response = $this->get(route('extractions.export', [$extraction->provider, $extraction, 'json']))->assertOk();
        $json = json_decode($response->streamedContent(), true);
        $this->assertTrue($json['simulated']);
        $this->assertSame('demo', $json['mode']);
        $this->assertSame('=1+1', $json['records'][0]['value']);
        $csv = $this->get(route('extractions.export', [$extraction->provider, $extraction, 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString('_mode,_provider', $csv);
        $this->assertStringContainsString('other', $csv);
        $this->get(route('extractions.export', [$extraction->provider, $extraction, 'xml']))->assertNotFound();
    }

    public function test_pending_exports_are_unavailable_and_status_is_read_only(): void
    {
        Queue::fake();
        $extraction = Extraction::factory()->create();
        $this->get(route('extractions.export', [$extraction->provider, $extraction, 'json']))->assertNotFound();
        $this->get(route('extractions.status', [$extraction->provider, $extraction]))
            ->assertExactJson(['status' => 'queued', 'label' => 'En cola', 'active' => true]);
        $this->assertSame('queued', $extraction->fresh()->status);
        Queue::assertNothingPushed();
    }
}

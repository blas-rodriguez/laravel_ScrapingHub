<?php

namespace Tests\Feature;

use App\Models\Extraction;
use App\ScrapingProvider;
use App\Services\Scraping\BrowseAiDriver;
use App\Services\Scraping\FirecrawlDriver;
use App\Services\Scraping\ScrapeGraphAiDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ProviderAdaptersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (ScrapingProvider::cases() as $provider) {
            config(['integrations.providers.'.$provider->value.'.api_key' => 'test-'.$provider->value.'-key']);
        }
    }

    public function test_firecrawl_v2_markdown_contract(): void
    {
        Http::fake(['https://api.firecrawl.dev/v2/scrape' => Http::response(['success' => true, 'data' => ['markdown' => '# Catálogo', 'metadata' => ['title' => 'Libros'], 'links' => []]])]);
        $run = Extraction::factory()->create(['mode' => 'live']);
        $result = app(FirecrawlDriver::class)->start($run);
        $this->assertTrue($result->complete);
        $this->assertSame('# Catálogo', $result->records[0]['markdown']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.firecrawl.dev/v2/scrape'
            && $request->hasHeader('Authorization', 'Bearer test-firecrawl-key')
            && $request['url'] === $run->url && $request['formats'] === ['markdown', 'links']);
        Http::assertSentCount(1);
    }

    public function test_firecrawl_json_format_includes_prompt_and_schema(): void
    {
        Http::fake(['https://api.firecrawl.dev/v2/scrape' => Http::response(['success' => true, 'data' => ['json' => ['products' => [['name' => 'A'], ['name' => 'B']]]]])]);
        $schema = ['type' => 'object', 'properties' => ['products' => ['type' => 'array']]];
        $run = Extraction::factory()->create(['mode' => 'live', 'options' => ['format' => 'json', 'prompt' => 'Get products', 'schema' => $schema]]);
        $result = app(FirecrawlDriver::class)->start($run);
        $this->assertCount(2, $result->records);
        Http::assertSent(fn (Request $request) => $request['formats'][0] === ['type' => 'json', 'prompt' => 'Get products', 'schema' => $schema]);
    }

    public function test_browse_submits_once_and_gets_results_from_task_endpoint(): void
    {
        Http::fake([
            'https://api.browse.ai/v2/robots/robot-123/tasks' => Http::response(['result' => ['id' => 'task-456', 'status' => 'running']]),
            'https://api.browse.ai/v2/robots/robot-123/tasks/task-456' => Http::response(['result' => ['id' => 'task-456', 'status' => 'successful', 'capturedTexts' => ['title' => 'Store'], 'capturedLists' => ['products' => [['name' => 'A']]]]]),
        ]);
        $run = Extraction::factory()->create(['provider' => ScrapingProvider::BrowseAi, 'mode' => 'live', 'options' => [
            'robot_id' => 'robot-123', 'url_parameter' => 'originUrl', 'input_parameters' => ['originUrl' => 'https://ignored.example', 'limit' => 5],
        ]]);
        $driver = app(BrowseAiDriver::class);
        $pending = $driver->start($run);
        $this->assertFalse($pending->complete);
        $run->update(['remote_id' => $pending->remoteId]);
        $complete = $driver->poll($run);
        $this->assertTrue($complete->complete);
        $this->assertSame('A', $complete->records[0]['name']);
        $this->assertSame('products', $complete->records[0]['_list']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['inputParameters']['originUrl'] === $run->url
            && $request['inputParameters']['limit'] === 5 && $request->hasHeader('Authorization', 'Bearer test-browse-ai-key'));
        Http::assertSentCount(2);
    }

    public function test_scrapegraph_uses_current_v2_extract_endpoint_and_header(): void
    {
        Http::fake(['https://v2-api.scrapegraphai.com/api/extract' => Http::response(['id' => 'request-123', 'json' => ['items' => [['title' => 'A']]], 'usage' => ['promptTokens' => 30]])]);
        $run = Extraction::factory()->create(['provider' => ScrapingProvider::ScrapeGraphAi, 'mode' => 'live', 'options' => ['prompt' => 'Extract titles']]);
        $result = app(ScrapeGraphAiDriver::class)->start($run);
        $this->assertTrue($result->complete);
        $this->assertSame('request-123', $result->remoteId);
        $this->assertSame('A', $result->records[0]['title']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://v2-api.scrapegraphai.com/api/extract'
            && $request->hasHeader('SGAI-APIKEY', 'test-scrapegraphai-key') && ! $request->hasHeader('Authorization')
            && $request['prompt'] === 'Extract titles' && $request['url'] === $run->url);
    }

    public function test_browse_supports_custom_lists_inside_captured_texts(): void
    {
        Http::fake(['*' => Http::response(['result' => ['id' => 'task', 'status' => 'successful',
            'capturedTexts' => ['title' => 'Store', 'custom_catalog' => [['name' => 'A'], ['name' => 'B']]],
        ]])]);
        $run = Extraction::factory()->create(['provider' => ScrapingProvider::BrowseAi, 'mode' => 'live',
            'options' => ['robot_id' => 'robot', 'url_parameter' => 'originUrl']]);
        $result = app(BrowseAiDriver::class)->start($run);
        $this->assertTrue($result->complete);
        $this->assertCount(2, $result->records);
        $this->assertSame('custom_catalog', $result->records[0]['_list']);
    }

    public function test_http_errors_are_sanitized_and_post_is_not_retried(): void
    {
        $run = Extraction::factory()->create(['mode' => 'live']);
        $sequence = Http::sequence();
        foreach ([401, 402, 429, 503] as $status) {
            $sequence->push(['error' => 'secret-server-body test-firecrawl-key'], $status);
        }
        Http::fake(['*' => $sequence]);
        foreach ([401, 402, 429, 503] as $status) {
            try {
                app(FirecrawlDriver::class)->start($run);
                $this->fail('Expected a provider error.');
            } catch (RuntimeException $exception) {
                $this->assertSame($status, $exception->getCode());
                $this->assertStringNotContainsString('test-firecrawl-key', $exception->getMessage());
                $this->assertStringNotContainsString('secret-server-body', $exception->getMessage());
            }
        }
        Http::assertSentCount(4);
    }

    public function test_success_payload_redacts_credentials_and_echoed_api_keys(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['markdown' => 'echo test-firecrawl-key', 'api_key' => 'server-secret', 'metadata' => ['Authorization' => 'server-auth']]])]);
        $result = app(FirecrawlDriver::class)->start(Extraction::factory()->create(['mode' => 'live']));
        $this->assertSame('echo [REDACTED]', $result->records[0]['markdown']);
        $this->assertSame('[REDACTED]', $result->payload['data']['api_key']);
        $this->assertSame('[REDACTED]', $result->payload['data']['metadata']['Authorization']);
    }

    public function test_malformed_json_and_missing_provider_contract_are_rejected(): void
    {
        $run = Extraction::factory()->create(['mode' => 'live']);
        Http::fake(['*' => Http::sequence()->push('not json', 200)->push(['success' => true, 'data' => []])]);
        foreach ([1, 2] as $attempt) {
            try {
                app(FirecrawlDriver::class)->start($run);
                $this->fail('Invalid provider data must fail.');
            } catch (RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_polling_identifier_cannot_inject_an_api_path(): void
    {
        Http::fake();
        $run = Extraction::factory()->create(['provider' => ScrapingProvider::BrowseAi, 'mode' => 'live', 'remote_id' => '../robots', 'options' => ['robot_id' => 'robot-123']]);
        try {
            app(BrowseAiDriver::class)->poll($run);
            $this->fail('Invalid remote id must fail.');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }
    }
}

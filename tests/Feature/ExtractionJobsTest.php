<?php

namespace Tests\Feature;

use App\Jobs\ExecuteExtraction;
use App\Jobs\PollExtraction;
use App\Models\Extraction;
use App\ScrapingProvider;
use App\Services\Scraping\ExtractionLifecycle;
use App\Services\Scraping\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ExtractionJobsTest extends TestCase
{
    use RefreshDatabase;

    private function start(Extraction $run): void
    {
        (new ExecuteExtraction($run->id))->handle(app(ProviderRegistry::class), app(ExtractionLifecycle::class));
    }

    private function poll(Extraction $run): void
    {
        (new PollExtraction($run->id))->handle(app(ProviderRegistry::class), app(ExtractionLifecycle::class));
    }

    public function test_three_demo_modules_complete_without_any_http_calls(): void
    {
        Http::fake();
        Queue::fake();
        foreach (ScrapingProvider::cases() as $provider) {
            $run = Extraction::factory()->create(['provider' => $provider]);
            $this->start($run);
            if ($provider === ScrapingProvider::BrowseAi) {
                $this->assertSame('waiting', $run->fresh()->status);
                $this->poll($run);
            }
            $run->refresh();
            $this->assertSame('completed', $run->status);
            $this->assertTrue($run->provider_payload['simulated']);
            $this->assertNotEmpty($run->records);
            $this->assertNotNull($run->duration_ms);
            $this->assertNotNull($run->finished_at);
        }
        Http::assertNothingSent();
    }

    public function test_duplicate_job_does_not_submit_a_paid_request_twice(): void
    {
        config(['integrations.providers.firecrawl.api_key' => 'test-key']);
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['markdown' => '# Real response']])]);
        Queue::fake();
        $run = Extraction::factory()->create(['mode' => 'live']);
        $this->start($run);
        $this->start($run);
        $this->assertSame('completed', $run->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_real_browse_task_is_submitted_once_then_polled_to_completion(): void
    {
        config(['integrations.providers.browse-ai.api_key' => 'test-key']);
        Http::fake([
            'https://api.browse.ai/v2/robots/robot/tasks' => Http::response(['result' => ['id' => 'task', 'status' => 'running']]),
            'https://api.browse.ai/v2/robots/robot/tasks/task' => Http::sequence()
                ->push(['result' => ['id' => 'task', 'status' => 'running']])
                ->push(['result' => ['id' => 'task', 'status' => 'successful', 'capturedTexts' => ['title' => 'Result']]]),
        ]);
        Queue::fake();
        $run = Extraction::factory()->create(['provider' => ScrapingProvider::BrowseAi, 'mode' => 'live', 'options' => ['robot_id' => 'robot', 'url_parameter' => 'originUrl']]);
        $this->start($run);
        $this->assertSame('waiting', $run->fresh()->status);
        $this->assertSame('task', $run->fresh()->remote_id);
        $this->poll($run);
        $this->assertSame('waiting', $run->fresh()->status);
        $this->poll($run);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame('Result', $run->fresh()->records[0]['title']);
        $this->poll($run);
        Http::assertSentCount(3);
        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->method() === 'POST'));
        Queue::assertPushed(PollExtraction::class, 2);
    }

    public function test_real_api_failure_never_falls_back_to_demo(): void
    {
        config(['integrations.providers.firecrawl.api_key' => 'secret-test-key']);
        Http::fake(['*' => Http::response(['error' => 'secret-test-key'], 401)]);
        Queue::fake();
        $run = Extraction::factory()->create(['mode' => 'live']);
        $this->start($run);
        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertSame('live', $run->mode);
        $this->assertNull($run->records);
        $this->assertNull($run->provider_payload);
        $this->assertStringContainsString('HTTP 401', $run->error);
        $this->assertStringNotContainsString('secret-test-key', $run->error);
        Queue::assertNothingPushed();
        Http::assertSentCount(1);
    }

    public function test_transient_get_failure_schedules_another_poll_without_resubmitting(): void
    {
        config(['integrations.providers.browse-ai.api_key' => 'test-key']);
        Http::fake(['*' => Http::response([], 429)]);
        Queue::fake();
        $run = Extraction::factory()->create(['provider' => ScrapingProvider::BrowseAi, 'mode' => 'live', 'status' => 'waiting',
            'started_at' => now(), 'remote_id' => 'task', 'options' => ['robot_id' => 'robot', 'url_parameter' => 'originUrl']]);
        $this->poll($run);
        $this->assertSame('waiting', $run->fresh()->status);
        Queue::assertPushed(PollExtraction::class, 1);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET');
        Http::assertSentCount(1);
    }

    public function test_polling_has_a_limit_and_preserves_the_remote_task_id(): void
    {
        Http::fake();
        Queue::fake();
        $run = Extraction::factory()->create(['provider' => ScrapingProvider::BrowseAi, 'mode' => 'live', 'status' => 'waiting',
            'started_at' => now(), 'remote_id' => 'task', 'poll_count' => 30]);
        $this->poll($run);
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame('task', $run->fresh()->remote_id);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_failure_callback_cannot_overwrite_a_completed_extraction(): void
    {
        $run = Extraction::factory()->create(['status' => 'completed', 'records' => [['title' => 'Keep']]]);
        (new ExecuteExtraction($run->id))->failed(new RuntimeException('Late error'));
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame('Keep', $run->fresh()->records[0]['title']);
    }

    public function test_missing_key_is_an_explicit_failure_and_not_a_demo(): void
    {
        config(['integrations.providers.firecrawl.api_key' => '']);
        Http::fake();
        Queue::fake();
        $run = Extraction::factory()->create(['mode' => 'live']);
        $this->start($run);
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertNull($run->fresh()->records);
        Http::assertNothingSent();
    }
}

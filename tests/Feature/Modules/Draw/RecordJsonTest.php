<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RecordJsonTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE_KEYS = ['title', 'status', 'payload', 'commitment', 'beacon_source', 'created', 'discarded', 'discarded_reason'];

    private const REVEAL_KEYS = ['server_seed', 'beacon_value', 'final_seed', 'assignment', 'executed'];

    #[Test]
    public function committed_draw_hides_secrets(): void
    {
        $draw = Draw::factory()->create();

        $response = $this->getJson("draw/record/{$draw->slug}/json")->assertOk();

        $this->assertSame(self::BASE_KEYS, array_keys($response->json()));
        $this->assertStringNotContainsString($draw->server_seed, $response->getContent());
    }

    #[Test]
    public function executed_direct_draw_reveals(): void
    {
        $draw = Draw::factory()->executed()->create();

        $data = $this->getJson("draw/record/{$draw->slug}/json")->assertOk()->json();

        $this->assertSame([...self::BASE_KEYS, ...self::REVEAL_KEYS], array_keys($data));
        $this->assertSame($draw->server_seed, $data['server_seed']);
        $this->assertSame($draw->beacon_value, $data['beacon_value']);
        $this->assertSame($draw->final_seed, $data['final_seed']);
        $expected = (new DrawService)->execute($draw->payloadArray(), $draw->server_seed, $draw->beacon_value)['assignment'];
        $this->assertSame($expected, $data['assignment']);
        $this->assertSame($draw->title, $data['title']);
        $this->assertSame($draw->commitment, $data['commitment']);
    }

    #[Test]
    public function picking_token_draw_stays_hidden(): void
    {
        $draw = Draw::factory()->token()->executed()->create();
        $this->assertSame('picking', $draw->status);

        $data = $this->getJson("draw/record/{$draw->slug}/json")->assertOk()->json();

        $this->assertArrayNotHasKey('server_seed', $data);
        $this->assertSame(self::BASE_KEYS, array_keys($data));
    }

    #[Test]
    public function closed_token_draw_reveals(): void
    {
        $draw = Draw::factory()->token()->executed()->closed()->create();

        $data = $this->getJson("draw/record/{$draw->slug}/json")->assertOk()->json();

        $this->assertSame($draw->server_seed, $data['server_seed']);
        $this->assertArrayHasKey('assignment', $data);
    }

    #[Test]
    public function json_keeps_raw_datetime_strings(): void
    {
        $draw = Draw::factory()->executed()->create([
            'created' => '2026-03-01 10:00:00',
            'executed' => '2026-03-01 11:00:00',
        ]);

        $data = $this->getJson("draw/record/{$draw->slug}/json")->json();

        $this->assertSame('2026-03-01 10:00:00', $data['created']);
        $this->assertSame('2026-03-01 11:00:00', $data['executed']);
    }

    #[Test]
    public function json_passes_list_shaped_assignment_through(): void
    {
        $draw = Draw::factory()->withPayload(['entries' => [1, 2, 3], 'slots' => ['0', '1', '2'], 'rule' => 'r'])->executed()->create();

        $response = $this->getJson("draw/record/{$draw->slug}/json")->assertOk();

        $this->assertStringContainsString('"assignment":[', $response->getContent());
        $this->assertSame(json_decode($draw->result, true), $response->json('assignment'));
    }

    #[Test]
    public function json_keeps_int_and_string_entries(): void
    {
        $draw = Draw::factory()->withPayload(['entries' => [101, 'Q1'], 'slots' => ['A2', 'B2'], 'rule' => 'r'])->create();

        $data = json_decode($this->getJson("draw/record/{$draw->slug}/json")->getContent(), true);

        $this->assertSame([101, 'Q1'], $data['payload']['entries']);
    }

    #[Test]
    public function unknown_and_malformed_slugs_are_404(): void
    {
        $this->getJson('draw/record/zzzz9999/json')->assertNotFound()->assertExactJson(['error' => 'not found']);
        $this->getJson('draw/record/abc/json')->assertNotFound();
        $this->getJson('draw/record/a.bcd/json')->assertNotFound();
    }

    #[Test]
    public function json_sends_no_cache_headers(): void
    {
        $draw = Draw::factory()->create();

        foreach ([$draw->slug, 'zzzz9999'] as $slug) {
            $response = $this->getJson("draw/record/{$slug}/json");
            $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
            $this->assertTrue($response->headers->hasCacheControlDirective('no-cache'));
            $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
            $this->assertSame(0, $response->baseResponse->getMaxAge());
            $this->assertSame('no-cache', $response->headers->get('Pragma'));
        }
    }

    #[Test]
    public function endpoint_output_passes_the_python_verifier(): void
    {
        $python = (new ExecutableFinder)->find('python3');
        if ($python === null) {
            $this->markTestSkipped('python3 not found');
        }
        $draw = Draw::factory()->executed()->create();
        $file = tempnam(sys_get_temp_dir(), 'rec');
        file_put_contents($file, $this->getJson("draw/record/{$draw->slug}/json")->getContent());

        $process = new Process([$python, base_path('tools/verify_draw.py'), $file]);
        $process->run();
        @unlink($file);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
    }
}

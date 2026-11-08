<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Services\Notifications\Transport\FakeTransport;
use App\Services\Notifications\Transport\LogTransport;
use App\Services\Notifications\Transport\ProviderTransport;
use App\Services\Notifications\Transport\TemplateRef;
use App\Services\Notifications\Transport\TransportFactory;
use App\Services\Notifications\Transport\TransportResult;
use App\Services\Notifications\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransportTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT = 'ACtest0000000000000000000000001234';

    private const TOKEN = 'token-test-secret';

    private const TO = '+436600000000';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'whatsapp.driver' => 'live',
            'whatsapp.account_sid' => self::ACCOUNT,
            'whatsapp.auth_token' => self::TOKEN,
            'whatsapp.messaging_service_sid' => 'MGtest0000000000000000000000000001',
            'whatsapp.sender' => '+43 660 0000001',
            'whatsapp.sandbox_sender' => '+43 660 0000002',
            'whatsapp.api_base' => 'https://api.example.test',
            'whatsapp.api_messages_path' => '/v1/Accounts/{account}/Messages.json',
            'whatsapp.api_account_path' => '/v1/Accounts/{account}.json',
            'whatsapp.api_service_path' => '/v1/Services/{service}',
            'whatsapp.webhook_base_url' => 'https://hooks.example.test/',
        ]);
    }

    /** Http::fake() stubs accumulate (first match wins), so start from a fresh factory when re-faking. */
    private function freshHttp(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }

    private function transport(): ProviderTransport
    {
        return app(ProviderTransport::class);
    }

    private function template(): TemplateRef
    {
        return new TemplateRef('HXtest0001', 'player_search_opened', 'de', 'Freitext');
    }

    private function send(): TransportResult
    {
        return $this->transport()->send(self::TO, $this->template(), ['1' => 'Sa, 14.11.', '2' => '18:00'], null);
    }

    #[Test]
    public function live_sends_form_fields_basic_auth_and_whatsapp_prefix(): void
    {
        Http::fake(['*' => Http::response(['sid' => 'SMtest1'], 201)]);

        $result = $this->send();

        $this->assertTrue($result->accepted);
        $this->assertSame('SMtest1', $result->sid);
        Http::assertSent(function (Request $r) {
            $data = $r->data();

            return $r->url() === 'https://api.example.test/v1/Accounts/'.self::ACCOUNT.'/Messages.json'
                && $r->method() === 'POST'
                && $data['To'] === 'whatsapp:+436600000000'
                && $data['ContentSid'] === 'HXtest0001'
                && $data['ContentVariables'] === '{"1":"Sa, 14.11.","2":"18:00"}'
                && $data['MessagingServiceSid'] === 'MGtest0000000000000000000000000001'
                && $data['StatusCallback'] === 'https://hooks.example.test/webhooks/whatsapp/status'
                && ! isset($data['Body'], $data['From'])
                && $r->header('Authorization') === ['Basic '.base64_encode(self::ACCOUNT.':'.self::TOKEN)];
        });
    }

    #[Test]
    public function status_callback_falls_back_to_app_url_and_from_to_sender_without_service(): void
    {
        config(['whatsapp.webhook_base_url' => '', 'whatsapp.messaging_service_sid' => '', 'app.url' => 'https://app.example.test']);
        Http::fake(['*' => Http::response(['sid' => 'SMtest2'], 201)]);

        $this->send();

        Http::assertSent(fn (Request $r) => $r->data()['StatusCallback'] === 'https://app.example.test/webhooks/whatsapp/status'
            && $r->data()['From'] === 'whatsapp:+43 660 0000001'
            && ! isset($r->data()['MessagingServiceSid']));
    }

    #[Test]
    public function sandbox_sends_body_and_sandbox_sender_instead_of_content_sid(): void
    {
        config(['whatsapp.sandbox' => '1']);
        Http::fake(['*' => Http::response(['sid' => 'SMtest3'], 201)]);

        $this->assertTrue($this->send()->accepted);

        Http::assertSent(function (Request $r) {
            $data = $r->data();

            return $data['Body'] === 'Freitext'
                && $data['From'] === 'whatsapp:+43 660 0000002'
                && ! isset($data['ContentSid'], $data['ContentVariables'], $data['MessagingServiceSid']);
        });
    }

    #[Test]
    public function error_map_marks_permanent_and_retryable(): void
    {
        $cases = [
            'invalid number' => [Http::response(['code' => 21211, 'message' => 'x'], 400), false, false, '21211'],
            'outside window' => [Http::response(['code' => 63016], 400), false, false, '63016'],
            'unknown 4xx' => [Http::response(['code' => 99999], 400), false, false, '99999'],
            'http 503' => [Http::response('', 503), true, false, 'http_503'],
            'http 429' => [Http::response('', 429), true, false, 'http_429'],
            'timeout' => [fn () => throw new ConnectionException('cURL error 28'), true, false, 'timeout'],
            'http 401' => [Http::response('', 401), false, true, 'http_401'],
            'http 403' => [Http::response('', 403), false, true, 'http_403'],
        ];

        foreach ($cases as $name => [$fake, $retryable, $auth, $code]) {
            $this->freshHttp();
            Http::fake(['*' => $fake]);
            $result = $this->send();

            $this->assertFalse($result->accepted, $name);
            $this->assertSame($retryable, $result->retryable, $name);
            $this->assertSame($auth, $result->authFailure, $name);
            $this->assertSame($code, $result->errorCode, $name);
        }
    }

    #[Test]
    public function missing_config_fails_without_a_request(): void
    {
        config(['whatsapp.api_base' => '']);

        $result = $this->send();

        $this->assertFalse($result->accepted);
        $this->assertSame('config_missing', $result->errorCode);
        Http::assertNothingSent();
    }

    #[Test]
    public function no_secret_in_logs_or_exception_messages(): void
    {
        Log::spy();
        Http::fake(['*' => fn () => throw new ConnectionException('cURL error for '.self::ACCOUNT.' with '.self::TOKEN)]);

        $result = $this->send();
        $this->freshHttp();
        Http::fake(['*' => Http::response(['code' => 21211, 'message' => 'bad '.self::TOKEN], 400)]);
        $result2 = $this->send();
        $probe = $this->transport()->probe();

        $captured = [];
        Log::shouldHaveReceived('warning')->atLeast()->once()->withArgs(function ($message, $context = []) use (&$captured) {
            $captured[] = $message.json_encode($context, JSON_UNESCAPED_UNICODE);

            return true;
        });
        $all = implode('|', $captured).json_encode([$result, $result2, $probe]);

        $this->assertStringNotContainsString(self::TOKEN, $all);
        $this->assertStringNotContainsString(self::ACCOUNT, $all);
        $this->assertStringNotContainsString('+436600000000', $all);
        $this->assertStringContainsString('…1234', $all);
    }

    #[Test]
    public function probe_returns_status_only(): void
    {
        Http::fake([
            'api.example.test/v1/Accounts/*' => Http::response(['friendly_name' => 'Fantasiefeld-ABC', 'status' => 'active'], 200),
            'api.example.test/v1/Services/*' => Http::response(['friendly_name' => 'Fantasiefeld-XYZ'], 200),
        ]);

        $ok = $this->transport()->probe();

        $this->assertTrue($ok->ok);
        $this->assertStringNotContainsString('Fantasiefeld', json_encode($ok));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === 'https://api.example.test/v1/Services/MGtest0000000000000000000000000001');

        $this->freshHttp();
        Http::fake([
            'api.example.test/v1/Accounts/*' => Http::response('', 200),
            'api.example.test/v1/Services/*' => Http::response(['friendly_name' => 'Fantasiefeld-XYZ'], 404),
        ]);
        $bad = $this->transport()->probe();
        $this->assertFalse($bad->ok);
        $this->assertSame('http_404', $bad->code);
        $this->assertStringNotContainsString('Fantasiefeld', json_encode($bad));
    }

    #[Test]
    public function probe_skips_service_path_without_messaging_service_sid(): void
    {
        config(['whatsapp.messaging_service_sid' => '']);
        Http::fake(['*' => Http::response([], 200)]);

        $this->assertTrue($this->transport()->probe()->ok);

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/v1/Services/'));
    }

    #[Test]
    public function button_suffix_becomes_the_next_variable_and_empty_vars_encode_as_object(): void
    {
        Http::fake(['*' => Http::response(['sid' => 'SMtest4'], 201)]);
        $vars = ['1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd', '5' => 'e'];

        $this->transport()->send(self::TO, $this->template(), $vars, 'suffix-1');
        $this->transport()->send(self::TO, $this->template(), [], null);

        $sent = [];
        Http::assertSent(function (Request $r) use (&$sent) {
            $sent[] = $r->data()['ContentVariables'];

            return true;
        });
        $this->assertSame('{"1":"a","2":"b","3":"c","4":"d","5":"e","6":"suffix-1"}', $sent[0]);
        $this->assertSame('{}', $sent[1]);
    }

    #[Test]
    public function log_transport_writes_no_number_and_no_text(): void
    {
        Log::spy();
        $template = new TemplateRef(null, 'player_search_opened', 'de', 'Geheimer Freitext');

        $result = (new LogTransport)->send(self::TO, $template, ['1' => 'Variablenwert'], 'abc');

        $this->assertTrue($result->accepted);
        $this->assertStringStartsWith('log-', (string) $result->sid);
        $captured = '';
        Log::shouldHaveReceived('info')->once()->withArgs(function ($message, $context = []) use (&$captured) {
            $captured = $message.json_encode($context, JSON_UNESCAPED_UNICODE);

            return true;
        });
        $this->assertStringContainsString('player_search_opened', $captured);
        $this->assertStringContainsString('…00', $captured);
        $this->assertStringNotContainsString('6600000000', $captured);
        $this->assertStringNotContainsString('Geheimer', $captured);
        $this->assertStringNotContainsString('Variablenwert', $captured);
        Http::assertNothingSent();
    }

    #[Test]
    public function fake_transport_records_calls_and_result_is_steerable(): void
    {
        $fake = new FakeTransport;
        $this->assertTrue($fake->send(self::TO, $this->template(), ['1' => 'a'], null)->accepted);

        $fake->willReturn(TransportResult::failed('21211'));
        $result = $fake->send(self::TO, $this->template(), [], 'x');

        $this->assertFalse($result->accepted);
        $this->assertCount(2, $fake->sent());
        $this->assertSame('x', $fake->sent()[1]['buttonSuffix']);
    }

    #[Test]
    public function factory_picks_transport_by_effective_driver(): void
    {
        $factory = fn () => app(TransportFactory::class)->make();

        config(['whatsapp.driver' => 'log']);
        $this->assertInstanceOf(LogTransport::class, $factory());
        config(['whatsapp.driver' => 'fake']);
        $this->assertInstanceOf(FakeTransport::class, $factory());
        $this->assertSame($factory(), $factory());
        config(['whatsapp.driver' => 'live']);
        $this->assertInstanceOf(ProviderTransport::class, $factory());
    }

    #[Test]
    public function staging_database_downgrades_live_to_log_unless_sandbox_or_flag(): void
    {
        $settings = fn () => app(WhatsAppSettings::class);
        $connection = DB::connection();
        $original = $connection->getDatabaseName();

        try {
            $this->assertSame('live', $settings()->effectiveDriver());

            $connection->setDatabaseName('x_staging');
            $this->assertSame('log', $settings()->effectiveDriver());
            $this->assertInstanceOf(LogTransport::class, app(TransportFactory::class)->make());

            config(['whatsapp.sandbox' => '1']);
            $this->assertSame('live', $settings()->effectiveDriver());

            config(['whatsapp.sandbox' => '', 'whatsapp.allow_live_on_staging' => '1']);
            $this->assertSame('live', $settings()->effectiveDriver());

            config(['whatsapp.allow_live_on_staging' => '', 'whatsapp.driver' => 'fake']);
            $this->assertSame('fake', $settings()->effectiveDriver());
        } finally {
            $connection->setDatabaseName($original);
        }
    }
}

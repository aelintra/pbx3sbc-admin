<?php

namespace Tests\Feature;

use App\Services\OpenSIPSMIService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenSIPSMIServiceReloadTest extends TestCase
{
    public function test_dr_reload_returns_false_on_mi_http_failure(): void
    {
        Http::fake([
            '*' => Http::response('Service Unavailable', 503),
        ]);

        $service = app(OpenSIPSMIService::class);

        $this->assertFalse($service->drReload());
    }

    public function test_dr_reload_returns_true_on_mi_success(): void
    {
        Http::fake([
            '*' => Http::response(['jsonrpc' => '2.0', 'result' => 'OK', 'id' => 1], 200),
        ]);

        $service = app(OpenSIPSMIService::class);

        $this->assertTrue($service->drReload());
    }

    public function test_domain_reload_returns_false_when_mi_unreachable(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $service = app(OpenSIPSMIService::class);

        $this->assertFalse($service->domainReload());
    }

    public function test_reg_reload_returns_false_on_json_rpc_error_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'error' => ['code' => -32000, 'message' => 'reg_reload not found'],
                'id' => 1,
            ], 200),
        ]);

        $service = app(OpenSIPSMIService::class);

        $this->assertFalse($service->regReload());
    }

    public function test_dispatcher_reload_returns_true_when_first_command_succeeds(): void
    {
        Http::fake([
            '*' => Http::response(['jsonrpc' => '2.0', 'result' => 'OK', 'id' => 1], 200),
        ]);

        $service = app(OpenSIPSMIService::class);

        $this->assertTrue($service->dispatcherReload());
    }

    public function test_dispatcher_reload_returns_false_when_mi_unreachable(): void
    {
        Http::fake([
            '*' => Http::response('Service Unavailable', 503),
        ]);

        $service = app(OpenSIPSMIService::class);

        $this->assertFalse($service->dispatcherReload());
    }
}

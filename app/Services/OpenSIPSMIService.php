<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenSIPSMIService
{
    protected string $miUrl;

    public function __construct()
    {
        $this->miUrl = config('opensips.mi_url', 'http://127.0.0.1:8888/mi');
    }

    /**
     * Call OpenSIPS MI command
     */
    public function call(string $command, array $params = []): array
    {
        try {
            $response = Http::timeout(5)->post($this->miUrl, [
                'jsonrpc' => '2.0',
                'method' => $command,
                'params' => $params,
                'id' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['error'])) {
                    Log::error('OpenSIPS MI error response', [
                        'command' => $command,
                        'error' => $data['error'],
                    ]);
                    throw new \Exception("OpenSIPS MI error: " . ($data['error']['message'] ?? 'Unknown error'));
                }
                return $data;
            }

            Log::error('OpenSIPS MI call failed', [
                'command' => $command,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception("OpenSIPS MI call failed: {$response->status()}");

        } catch (\Exception $e) {
            Log::error('OpenSIPS MI exception', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reload domain module
     */
    public function domainReload(): void
    {
        try {
            $this->call('domain_reload');
            Log::info('OpenSIPS domain module reloaded successfully');
        } catch (\Exception $e) {
            // Log but don't throw - MI might not be available
            Log::warning('OpenSIPS domain reload failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reload dispatcher module
     */
    public function dispatcherReload(): void
    {
        try {
            $this->call('dispatcher_reload');
            Log::info('OpenSIPS dispatcher module reloaded successfully');
        } catch (\Exception $e) {
            // Log but don't throw - MI might not be available
            Log::warning('OpenSIPS dispatcher reload failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Set dispatcher destination state
     */
    public function dispatcherSetState(int $setid, string $destination, int $state): void
    {
        $this->call('dispatcher_set_state', [
            'setid' => $setid,
            'destination' => $destination,
            'state' => $state,
        ]);
    }
}

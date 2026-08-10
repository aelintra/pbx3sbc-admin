<?php

namespace Tests\Feature;

use App\Services\WhitelistSyncService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class WhitelistSyncServiceTest extends TestCase
{
    private const SECRET = 'super-secret-db-password';

    private ?string $fakeScriptPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeScriptPath = tempnam(sys_get_temp_dir(), 'fake-sync-script');
        file_put_contents($this->fakeScriptPath, "#!/bin/bash\nexit 0\n");
        chmod($this->fakeScriptPath, 0755);

        putenv('FAIL2BAN_SYNC_SCRIPT_PATH='.$this->fakeScriptPath);

        config([
            'database.connections.mysql.database' => 'opensips',
            'database.connections.mysql.username' => 'opensips',
            'database.connections.mysql.password' => self::SECRET,
        ]);
    }

    protected function tearDown(): void
    {
        putenv('FAIL2BAN_SYNC_SCRIPT_PATH');
        if ($this->fakeScriptPath !== null && file_exists($this->fakeScriptPath)) {
            unlink($this->fakeScriptPath);
        }

        parent::tearDown();
    }

    public function test_sync_never_puts_db_password_on_process_argv(): void
    {
        Process::fake();

        $service = new WhitelistSyncService;
        $service->sync();

        Process::assertRan(function ($process) {
            foreach ($process->command as $arg) {
                if (is_string($arg) && str_contains($arg, self::SECRET)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_sync_calls_sudo_script_with_db_name_and_a_credentials_file_path(): void
    {
        Process::fake();

        $service = new WhitelistSyncService;
        $service->sync();

        Process::assertRan(function ($process) {
            $command = $process->command;

            return is_array($command)
                && $command[0] === 'sudo'
                && $command[1] === $this->fakeScriptPath
                && $command[2] === 'opensips'
                && is_string($command[3])
                && str_contains($command[3], sys_get_temp_dir());
        });
    }

    public function test_sync_writes_a_private_defaults_extra_file_containing_the_password(): void
    {
        $capturedPath = null;

        Process::fake(function ($process) use (&$capturedPath) {
            $capturedPath = $process->command[3] ?? null;

            // Assert on the file's existence/permissions/content before the
            // service's `finally` block deletes it.
            if ($capturedPath !== null) {
                $this->assertFileExists($capturedPath);
                $this->assertSame('0600', substr(sprintf('%o', fileperms($capturedPath)), -4));

                $contents = file_get_contents($capturedPath);
                $this->assertStringContainsString('[client]', $contents);
                $this->assertStringContainsString('password='.self::SECRET, $contents);
            }

            return Process::result();
        });

        $service = new WhitelistSyncService;
        $service->sync();

        $this->assertNotNull($capturedPath);
        // The service must clean up the credentials file afterwards.
        $this->assertFileDoesNotExist($capturedPath);
    }
}

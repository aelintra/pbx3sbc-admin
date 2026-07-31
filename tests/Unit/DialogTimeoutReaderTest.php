<?php

namespace Tests\Unit;

use App\Services\DialogTimeoutReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DialogTimeoutReaderTest extends TestCase
{
    #[Test]
    public function reads_default_timeout_from_cfg(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'opensips-cfg-');
        file_put_contents($path, <<<'CFG'
modparam("dialog", "db_mode", 2)
modparam("dialog", "db_update_period", 10)
modparam("dialog", "default_timeout", 14400)
CFG);
        config(['opensips.cfg_path' => $path]);

        $info = app(DialogTimeoutReader::class)->read();
        @unlink($path);

        $this->assertSame(14400, $info['seconds']);
        $this->assertSame('4h', $info['human']);
        $this->assertSame('cfg', $info['source']);
        $this->assertNull($info['error']);
    }

    #[Test]
    public function falls_back_to_opensips_default_when_modparam_omitted(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'opensips-cfg-');
        file_put_contents($path, 'modparam("dialog", "db_mode", 2)'."\n");
        config(['opensips.cfg_path' => $path]);

        $info = app(DialogTimeoutReader::class)->read();
        @unlink($path);

        $this->assertSame(DialogTimeoutReader::OPENSIPS_DEFAULT_SECONDS, $info['seconds']);
        $this->assertSame('12h', $info['human']);
        $this->assertSame('opensips_default', $info['source']);
    }

    #[Test]
    public function reports_unreadable_cfg(): void
    {
        config(['opensips.cfg_path' => '/no/such/opensips.cfg']);

        $info = app(DialogTimeoutReader::class)->read();

        $this->assertSame('unreadable', $info['source']);
        $this->assertNotNull($info['error']);
        $this->assertSame(DialogTimeoutReader::OPENSIPS_DEFAULT_SECONDS, $info['seconds']);
    }
}

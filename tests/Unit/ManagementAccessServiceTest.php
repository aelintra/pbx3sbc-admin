<?php

namespace Tests\Unit;

use App\Services\ManagementAccessService;
use PHPUnit\Framework\TestCase;

class ManagementAccessServiceTest extends TestCase
{
    private string $tmp;

    private ManagementAccessService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/mgmt-access-'.uniqid('', true).'.json';
        $this->svc = new ManagementAccessService($this->tmp, '/nonexistent/apply.sh');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmp)) {
            @unlink($this->tmp);
        }
        parent::tearDown();
    }

    public function test_default_lockdown_off(): void
    {
        $state = $this->svc->get();
        $this->assertFalse($state['lockdown']);
        $this->assertSame([], $state['allows']);
    }

    public function test_put_and_get_roundtrip(): void
    {
        $this->svc->put([
            'lockdown' => true,
            'allows' => [
                ['cidr' => '203.0.113.10/32', 'comment' => 'desk'],
                ['cidr' => '198.51.100.0/24', 'comment' => 'office'],
            ],
        ]);
        $state = $this->svc->get();
        $this->assertTrue($state['lockdown']);
        $this->assertCount(2, $state['allows']);
        $this->assertSame('203.0.113.10/32', $state['allows'][0]['cidr']);
    }

    public function test_rejects_invalid_cidr(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->put([
            'lockdown' => false,
            'allows' => [['cidr' => 'not-an-ip', 'comment' => 'x']],
        ]);
    }

    public function test_ip_covered_by_allows(): void
    {
        $allows = [
            ['cidr' => '203.0.113.0/24', 'comment' => ''],
        ];
        $this->assertTrue($this->svc->ipCoveredByAllows('203.0.113.50', $allows));
        $this->assertFalse($this->svc->ipCoveredByAllows('198.51.100.1', $allows));
    }

    public function test_apply_refuses_empty_lockdown(): void
    {
        $result = $this->svc->apply(
            ['lockdown' => true, 'allows' => []],
            '', // no client IP to auto-add
        );
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('empty', $result['message']);
    }

    public function test_apply_auto_adds_client_when_missing(): void
    {
        $result = $this->svc->apply(
            [
                'lockdown' => true,
                'allows' => [['cidr' => '198.51.100.0/24', 'comment' => 'other']],
            ],
            '203.0.113.10',
        );
        // Script missing locally — after auto-add, put succeeds then apply script fails.
        // Assert allow list gained the client before script step via get after put path:
        // When script missing we revert lockdown but keep allows including auto-add.
        $this->assertNotEmpty($result['message']);
        $state = $this->svc->get();
        $cidrs = array_column($state['allows'], 'cidr');
        $this->assertContains('203.0.113.10/32', $cidrs);
    }
}

<?php

namespace Tests\Unit;

use App\Services\DrRulePrefixOverlap;
use PHPUnit\Framework\TestCase;

class DrRulePrefixOverlapTest extends TestCase
{
    public function test_normalize_trims(): void
    {
        $this->assertSame('01924', DrRulePrefixOverlap::normalize(' 01924 '));
        $this->assertSame('', DrRulePrefixOverlap::normalize(null));
    }

    public function test_classify_finds_parent_and_child(): void
    {
        $result = DrRulePrefixOverlap::classify('01924918076', [
            ['ruleid' => 1, 'prefix' => '01924', 'description' => 'area'],
            ['ruleid' => 2, 'prefix' => '019249180761', 'description' => 'extension'],
            ['ruleid' => 3, 'prefix' => '020', 'description' => 'london'],
        ]);

        $this->assertCount(1, $result['parents']);
        $this->assertSame(1, $result['parents'][0]['ruleid']);
        $this->assertCount(1, $result['children']);
        $this->assertSame(2, $result['children'][0]['ruleid']);
    }

    public function test_classify_empty_has_no_nesting(): void
    {
        $result = DrRulePrefixOverlap::classify('', [
            ['ruleid' => 1, 'prefix' => '01924'],
        ]);

        $this->assertSame([], $result['parents']);
        $this->assertSame([], $result['children']);
    }

    public function test_classify_skips_identical_prefix(): void
    {
        $result = DrRulePrefixOverlap::classify('01924', [
            ['ruleid' => 1, 'prefix' => '01924'],
        ]);

        $this->assertSame([], $result['parents']);
        $this->assertSame([], $result['children']);
    }

    public function test_format_classify_hint(): void
    {
        $classified = DrRulePrefixOverlap::classify('01924918076', [
            ['ruleid' => 5, 'prefix' => '01924', 'description' => 'Yorkshire'],
        ]);

        $hint = DrRulePrefixOverlap::formatClassifyHint($classified);

        $this->assertNotNull($hint);
        $this->assertStringContainsString('Nested under rule 5', $hint);
        $this->assertStringContainsString('01924', $hint);
    }
}

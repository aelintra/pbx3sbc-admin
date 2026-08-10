<?php

namespace Tests\Unit;

use App\Models\DrRule;
use App\Policies\DrRulePolicy;
use PHPUnit\Framework\TestCase;

class DrRulePolicyTest extends TestCase
{
    private function fleetOwnedRule(): DrRule
    {
        return new DrRule([
            'groupid' => '1',
            'prefix' => '441924918076',
            'attrs' => 'fleet=did;tenant=9wvvnb;e164_key=441924918076',
        ]);
    }

    private function standaloneRule(): DrRule
    {
        return new DrRule([
            'groupid' => '1',
            'prefix' => '441924918076',
            'attrs' => 'carrier=magrathea',
        ]);
    }

    public function test_fleet_owned_rule_cannot_be_edited(): void
    {
        $policy = new DrRulePolicy;

        $this->assertFalse($policy->update(null, $this->fleetOwnedRule()));
    }

    public function test_fleet_owned_rule_cannot_be_deleted(): void
    {
        $policy = new DrRulePolicy;

        $this->assertFalse($policy->delete(null, $this->fleetOwnedRule()));
    }

    public function test_standalone_rule_stays_editable_and_deletable(): void
    {
        $policy = new DrRulePolicy;
        $rule = $this->standaloneRule();

        $this->assertTrue($policy->update(null, $rule));
        $this->assertTrue($policy->delete(null, $rule));
    }

    public function test_rule_with_no_attrs_stays_editable_and_deletable(): void
    {
        $policy = new DrRulePolicy;
        $rule = new DrRule(['groupid' => '0', 'prefix' => '', 'attrs' => null]);

        $this->assertTrue($policy->update(null, $rule));
        $this->assertTrue($policy->delete(null, $rule));
    }

    public function test_viewing_and_creating_always_allowed(): void
    {
        $policy = new DrRulePolicy;

        $this->assertTrue($policy->viewAny(null));
        $this->assertTrue($policy->create(null));
        $this->assertTrue($policy->view(null, $this->fleetOwnedRule()));
    }
}

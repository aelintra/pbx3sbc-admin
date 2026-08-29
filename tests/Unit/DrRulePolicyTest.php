<?php

namespace Tests\Unit;

use App\Models\DrRule;
use App\Policies\DrRulePolicy;
use App\Support\FleetMode;
use Tests\TestCase;

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

    private function standaloneInboundRule(): DrRule
    {
        return new DrRule([
            'groupid' => '1',
            'prefix' => '441924918076',
            'attrs' => 'carrier=magrathea',
        ]);
    }

    private function outboundRule(): DrRule
    {
        return new DrRule([
            'groupid' => '0',
            'prefix' => '',
            'attrs' => null,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['fleet.service_token' => '']);
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

    public function test_standalone_inbound_editable_when_not_fleet_joined(): void
    {
        $policy = new DrRulePolicy;
        $rule = $this->standaloneInboundRule();

        $this->assertTrue($policy->update(null, $rule));
        $this->assertTrue($policy->delete(null, $rule));
    }

    public function test_standalone_inbound_locked_when_fleet_joined(): void
    {
        config(['fleet.service_token' => 'fleet-token']);
        $this->assertTrue(FleetMode::joined());

        $policy = new DrRulePolicy;
        $rule = $this->standaloneInboundRule();

        $this->assertTrue(DrRulePolicy::inboundLockedOnFleet($rule));
        $this->assertFalse($policy->update(null, $rule));
        $this->assertFalse($policy->delete(null, $rule));
    }

    public function test_outbound_stays_editable_when_fleet_joined(): void
    {
        config(['fleet.service_token' => 'fleet-token']);

        $policy = new DrRulePolicy;
        $rule = $this->outboundRule();

        $this->assertFalse(DrRulePolicy::inboundLockedOnFleet($rule));
        $this->assertTrue($policy->update(null, $rule));
        $this->assertTrue($policy->delete(null, $rule));
    }

    public function test_rule_with_no_attrs_stays_editable_and_deletable(): void
    {
        $policy = new DrRulePolicy;
        $rule = $this->outboundRule();

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

    public function test_direction_options_omit_inbound_when_fleet_joined(): void
    {
        config(['fleet.service_token' => '']);
        $this->assertArrayHasKey('1', \App\Filament\Resources\DrRuleResource::directionOptions());

        config(['fleet.service_token' => 'fleet-token']);
        $opts = \App\Filament\Resources\DrRuleResource::directionOptions();
        $this->assertArrayHasKey('0', $opts);
        $this->assertArrayNotHasKey('1', $opts);
    }
}

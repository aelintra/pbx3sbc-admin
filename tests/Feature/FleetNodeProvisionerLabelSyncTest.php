<?php

namespace Tests\Feature;

use App\Models\Dispatcher;
use App\Models\DrGateway;
use App\Services\FleetNodeProvisioner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FleetNodeProvisionerLabelSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('dr_gateways', function (Blueprint $table) {
            $table->increments('id');
            $table->string('gwid', 64);
            $table->integer('type')->default(0);
            $table->string('address', 128);
            $table->integer('strip')->default(0);
            $table->string('pri_prefix', 64)->nullable();
            $table->string('attrs', 255)->nullable();
            $table->integer('probe_mode')->default(0);
            $table->integer('state')->default(0);
            $table->string('socket', 128)->nullable();
            $table->string('description', 255)->nullable();
        });

        Schema::create('dispatcher', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('setid');
            $table->string('destination', 128);
            $table->string('socket', 128)->nullable();
            $table->integer('state')->default(0);
            $table->integer('probe_mode')->default(0);
            $table->integer('weight')->default(1);
            $table->integer('priority')->default(0);
            $table->string('attrs', 255)->nullable();
            $table->string('description', 255)->nullable();
        });
    }

    public function test_sync_node_description_updates_peer_and_dispatcher(): void
    {
        $attrs = FleetNodeProvisioner::fleetPeerAttrs('kid123', 3);
        DrGateway::query()->create([
            'gwid' => '10',
            'type' => 0,
            'address' => 'sip:192.168.1.31:5060',
            'strip' => 0,
            'attrs' => $attrs,
            'probe_mode' => 0,
            'state' => 0,
            'description' => 'old name',
        ]);

        Dispatcher::query()->create([
            'setid' => 3,
            'destination' => 'sip:192.168.1.31:5060',
            'state' => 0,
            'probe_mode' => 0,
            'weight' => 1,
            'priority' => 0,
            'attrs' => FleetNodeProvisioner::fleetDispatcherAttrs('kid123', '192.168.1.31'),
            'description' => 'old name',
        ]);

        $result = FleetNodeProvisioner::syncNodeDescription('kid123', 'Lab Home', 3);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['peer_updated']);
        $this->assertSame(1, $result['dispatcher_updated']);
        $this->assertSame('Lab Home', DrGateway::query()->where('gwid', '10')->value('description'));
        $this->assertSame('Lab Home', Dispatcher::query()->where('setid', 3)->value('description'));
    }

    public function test_sync_node_description_fails_when_nothing_to_update(): void
    {
        $result = FleetNodeProvisioner::syncNodeDescription('missing', 'Lab Home', 99);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OpensipsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data (optional - comment out if you want to preserve existing data)
        DB::table('dispatcher')->truncate();
        DB::table('domain')->truncate();

        // Seed domain table
        $domains = [
            [
                'domain' => 'example.com',
                'setid' => 1,
                'attrs' => null,
                'accept_subdomain' => 0,
            ],
            [
                'domain' => 'test.local',
                'setid' => 2,
                'attrs' => null,
                'accept_subdomain' => 1,
            ],
            [
                'domain' => 'demo.example',
                'setid' => 1,
                'attrs' => null,
                'accept_subdomain' => 0,
            ],
        ];

        foreach ($domains as $domain) {
            DB::table('domain')->insert([
                'domain' => $domain['domain'],
                'setid' => $domain['setid'],
                'attrs' => $domain['attrs'],
                'accept_subdomain' => $domain['accept_subdomain'],
                'last_modified' => now(),
            ]);
        }

        // Seed dispatcher table
        $dispatchers = [
            // Dispatchers for setid 1 (example.com, demo.example)
            [
                'setid' => 1,
                'destination' => 'sip:10.0.1.10:5060',
                'socket' => null,
                'state' => 0, // Active
                'probe_mode' => 0,
                'weight' => 1,
                'priority' => 0,
                'attrs' => null,
                'description' => 'Primary server for example.com',
            ],
            [
                'setid' => 1,
                'destination' => 'sip:10.0.1.11:5060',
                'socket' => null,
                'state' => 0, // Active
                'probe_mode' => 0,
                'weight' => 1,
                'priority' => 1,
                'attrs' => null,
                'description' => 'Secondary server for example.com',
            ],
            // Dispatchers for setid 2 (test.local)
            [
                'setid' => 2,
                'destination' => 'sip:192.168.1.100:5060',
                'socket' => null,
                'state' => 0, // Active
                'probe_mode' => 0,
                'weight' => 1,
                'priority' => 0,
                'attrs' => null,
                'description' => 'Test server',
            ],
            [
                'setid' => 2,
                'destination' => 'sip:192.168.1.101:5060',
                'socket' => null,
                'state' => 1, // Inactive (for testing)
                'probe_mode' => 0,
                'weight' => 1,
                'priority' => 1,
                'attrs' => null,
                'description' => 'Backup test server (inactive)',
            ],
        ];

        foreach ($dispatchers as $dispatcher) {
            DB::table('dispatcher')->insert($dispatcher);
        }
    }
}

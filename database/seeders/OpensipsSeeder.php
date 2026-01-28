<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * OpensipsSeeder - Example data seeder
 * 
 * NOTE: This seeder is for EXAMPLE/DEMONSTRATION purposes only.
 * 
 * IMPORTANT: The OpenSIPS database tables (domain, dispatcher, endpoint_locations) 
 * must be created by the pbx3sbc repository's init-database.sh script BEFORE 
 * running this seeder. This seeder only populates example data into existing tables.
 * 
 * To set up OpenSIPS database tables, use:
 *   cd pbx3sbc
 *   sudo ./scripts/init-database.sh
 */
class OpensipsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * WARNING: This will truncate domain and dispatcher tables!
     * Only use this for development/testing with example data.
     */
    public function run(): void
    {
        // Clear existing data (optional - comment out if you want to preserve existing data)
        DB::table('dispatcher')->truncate();
        DB::table('domain')->truncate();

        // Seed domain table - single example entry
        DB::table('domain')->insert([
            'domain' => 'example.com',
            'setid' => 15,
            'attrs' => 'setid=15', // Automatically populate attrs from setid
            'accept_subdomain' => 0,
            'last_modified' => now(),
        ]);

        // Seed dispatcher table - single example entry (linked to domain via setid 15)
        DB::table('dispatcher')->insert([
            'setid' => 15,
            'destination' => 'sip:10.0.1.10:5060',
            'socket' => null,
            'state' => 0, // Active
            'probe_mode' => 0,
            'weight' => 1,
            'priority' => 0,
            'attrs' => null,
            'description' => 'Example dispatcher destination',
        ]);
    }
}

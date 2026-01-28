<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fail2ban_whitelist', function (Blueprint $table) {
            $table->id();
            $table->string('ip_or_cidr', 45)->unique()->comment('IP address or CIDR range (e.g., 192.168.1.100 or 192.168.1.0/24)');
            $table->string('comment', 255)->nullable()->comment('Description/comment for this whitelist entry');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who created this entry');
            $table->timestamps();
            
            $table->index('ip_or_cidr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fail2ban_whitelist');
    }
};

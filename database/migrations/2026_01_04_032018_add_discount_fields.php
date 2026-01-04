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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('discount_type')->default('fixed')->after('discount');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('discount', 12, 2)->default(0)->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('discount_type');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};

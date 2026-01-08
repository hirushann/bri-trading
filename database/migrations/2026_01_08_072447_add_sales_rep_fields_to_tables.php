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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('from_sales_rep_stock')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['sales_rep_id']);
            $table->dropColumn('sales_rep_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['sales_rep_id']);
            $table->dropColumn(['sales_rep_id', 'from_sales_rep_stock']);
        });
    }
};

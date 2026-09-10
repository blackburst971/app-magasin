<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_forms', function (Blueprint $table) {
            // 'inactive' | 'optional' | 'required_before' | 'required_after'
            $table->string('status')->default('optional')->after('description');
        });

        // Migrer les données existantes
        DB::table('checkout_forms')->get()->each(function ($row) {
            $status = 'optional';
            if (!$row->is_active) {
                $status = 'inactive';
            } elseif ($row->is_required_for_reservation) {
                $status = 'required_before';
            }
            DB::table('checkout_forms')->where('id', $row->id)->update(['status' => $status]);
        });

        Schema::table('checkout_forms', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'is_required_for_reservation']);
        });
    }

    public function down(): void
    {
        Schema::table('checkout_forms', function (Blueprint $table) {
            $table->boolean('is_active')->default(false);
            $table->boolean('is_required_for_reservation')->default(false);
        });

        DB::table('checkout_forms')->get()->each(function ($row) {
            DB::table('checkout_forms')->where('id', $row->id)->update([
                'is_active' => $row->status !== 'inactive' ? 1 : 0,
                'is_required_for_reservation' => $row->status === 'required_before' ? 1 : 0,
            ]);
        });

        Schema::table('checkout_forms', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};

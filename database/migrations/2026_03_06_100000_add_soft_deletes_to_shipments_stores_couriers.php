<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('couriers', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('couriers', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('stop_id')->constrained('stops')->cascadeOnDelete();
            $table->integer('suggested_order')->default(0);
            $table->timestamps();

            $table->unique(['route_id', 'stop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
    }
};

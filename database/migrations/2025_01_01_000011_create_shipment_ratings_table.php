<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('courier_id')->constrained('couriers');
            $table->tinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_ratings');
    }
};

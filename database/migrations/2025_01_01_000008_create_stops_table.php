<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->enum('type', ['pickup', 'delivery']);
            $table->integer('suggested_order')->default(0);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->string('photo_s3_key')->nullable();
            $table->decimal('amount_collected', 10, 2)->nullable();
            $table->string('fail_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stops');
    }
};

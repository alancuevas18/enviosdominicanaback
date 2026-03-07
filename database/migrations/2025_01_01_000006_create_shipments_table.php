<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('courier_id')->nullable()->constrained('couriers')->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->text('address');
            $table->string('maps_url')->nullable();
            $table->string('sector')->nullable();
            $table->decimal('amount_to_collect', 10, 2)->default(0.00);
            $table->enum('payment_method', ['cash', 'transfer', 'card'])->nullable();
            $table->enum('payer', ['store', 'customer'])->default('store');
            $table->enum('status', ['pending', 'assigned', 'picked_up', 'in_route', 'delivered', 'not_delivered'])->default('pending');
            $table->text('notes')->nullable();
            $table->text('custom_notification_message')->nullable();
            $table->enum('weight_size', ['small', 'medium', 'large'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};

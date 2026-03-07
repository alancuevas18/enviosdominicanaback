<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches');
            $table->date('date');
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->timestamps();

            $table->unique(['courier_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};

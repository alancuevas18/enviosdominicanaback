<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_access_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('business_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone');
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('address')->nullable();
            $table->string('rnc')->nullable();
            $table->text('description')->nullable();
            $table->enum('volume_estimate', ['1-10', '10-50', '50-100', '100+'])->nullable();
            $table->enum('status', ['pending', 'in_review', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_access_requests');
    }
};

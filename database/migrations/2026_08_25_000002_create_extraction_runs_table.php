<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extraction_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('documents_found')->default(0);
            $table->unsignedInteger('documents_processed')->default(0);
            $table->unsignedInteger('documents_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['extraction_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_runs');
    }
};

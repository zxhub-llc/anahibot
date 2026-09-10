<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extraction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('extraction_run_id')->nullable()->constrained()->nullOnDelete();

            $table->string('company_ruc');
            $table->string('access_key');
            $table->string('type');
            $table->string('direction');
            $table->string('status')->default('downloaded');
            $table->boolean('is_voided')->default(false);

            $table->string('issuer_ruc')->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('receiver_ruc')->nullable();
            $table->string('receiver_name')->nullable();
            $table->date('issued_at')->nullable();
            $table->decimal('subtotal', 14, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->decimal('total', 14, 2)->nullable();
            $table->json('normalized_metadata')->nullable();
            $table->text('processing_error')->nullable();

            $table->timestamps();

            // Idempotencia: un mismo documento fiscal (por clave de acceso) no se duplica dentro del Team.
            $table->unique(['team_id', 'access_key']);
            $table->index(['team_id', 'direction', 'type']);
            $table->index(['team_id', 'is_voided']);
            $table->index(['team_id', 'company_ruc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
    }
};



<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sri_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('company_ruc');
            $table->text('username');
            $table->text('password');
            $table->string('environment')->default('production');
            $table->timestamp('last_authenticated_at')->nullable();
            $table->text('last_authentication_error')->nullable();
            $table->timestamps();

            $table->unique('company_ruc');
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sri_credentials');
    }
};
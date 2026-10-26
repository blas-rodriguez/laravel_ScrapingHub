<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('extractions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('provider', 30)->index();
            $table->string('mode', 10);
            $table->text('url');
            $table->json('options');
            $table->string('status', 20)->default('queued')->index();
            $table->string('remote_id', 150)->nullable();
            $table->json('records')->nullable();
            $table->json('provider_payload')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('poll_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extractions');
    }
};

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
        Schema::create('workflow_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('title');
            $table->text('description');
            $table->json('depends_on');
            $table->string('status')->default('pending');
            $table->text('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_tasks');
    }
};

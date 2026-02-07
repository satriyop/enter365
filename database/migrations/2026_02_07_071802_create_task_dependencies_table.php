<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('dependency_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('dependency_type')->default('finish_to_start');
            $table->timestamp('created_at')->nullable();

            $table->unique(['task_id', 'dependency_id']);
            $table->index('dependency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};

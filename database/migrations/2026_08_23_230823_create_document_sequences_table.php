<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backing store for DocumentNumbers.
     *
     * Numbers used to be derived by string-sorting the target table and parsing
     * the last four characters back out, which collided permanently once a
     * prefix reached 10,000 documents. One counter row per prefix, claimed with
     * an atomic UPDATE, removes both the parse and the first-of-month race.
     *
     * Rows are seeded lazily on first use of a prefix (prefixes are month-based,
     * so they cannot be enumerated ahead of time).
     */
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix')->unique();
            $table->unsignedBigInteger('next_value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};

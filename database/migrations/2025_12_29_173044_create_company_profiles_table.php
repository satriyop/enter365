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
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('employees_count')->nullable(); // e.g., "10-50", "50-100"

            // Branding
            $table->string('logo_path')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('primary_color', 7)->default('#FF7A3D'); // hex color
            $table->string('secondary_color', 7)->nullable();

            // JSON Content Arrays
            $table->json('services')->nullable(); // [{title, description, icon?}]
            $table->json('portfolio')->nullable(); // [{title, description, image_path, year?}]
            $table->json('team')->nullable(); // [{name, role, photo_path?, bio?}]
            $table->json('certifications')->nullable(); // [{name, issuer?, year?, image_path?}]
            $table->json('social_links')->nullable(); // {instagram?, linkedin?, facebook?, youtube?}

            // Contact
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();

            // Custom Domain
            $table->string('custom_domain')->nullable()->unique();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};

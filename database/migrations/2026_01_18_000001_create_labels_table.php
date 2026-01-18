<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->default('#6b7280'); // hex color
            $table->boolean('is_system')->default(false); // system labels can't be deleted
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Pivot table for contact labels
        Schema::create('contact_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('label_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['contact_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_label');
        Schema::dropIfExists('labels');
    }
};

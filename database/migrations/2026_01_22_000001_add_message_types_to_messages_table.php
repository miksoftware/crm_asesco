<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the type enum to include new message types
        DB::statement("ALTER TABLE messages MODIFY COLUMN type ENUM('text', 'image', 'document', 'audio', 'video', 'contact', 'location', 'sticker', 'deleted', 'other') DEFAULT 'text'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE messages MODIFY COLUMN type ENUM('text', 'image', 'document', 'audio', 'video') DEFAULT 'text'");
    }
};

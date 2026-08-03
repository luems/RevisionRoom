<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->float('position_x')->nullable()->after('timestamp_seconds'); // normalized percentage 0.00 to 100.00
            $table->float('position_y')->nullable()->after('position_x'); // normalized percentage 0.00 to 100.00
            $table->foreignId('draft_item_id')->nullable()->after('draft_id')->constrained('draft_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['draft_item_id']);
            $table->dropColumn(['position_x', 'position_y', 'draft_item_id']);
        });
    }
};

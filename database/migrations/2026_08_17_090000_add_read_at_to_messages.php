<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable();
        });

        // Existing messages count as read, so rolling this out does not mark
        // the entire history unread.
        DB::table('messages')->update(['read_at' => DB::raw('created_at')]);

        // Counting unread messages per endpoint is a frequent query.
        DB::statement('CREATE INDEX messages_unread_idx ON messages (endpoint_id) WHERE read_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS messages_unread_idx');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};

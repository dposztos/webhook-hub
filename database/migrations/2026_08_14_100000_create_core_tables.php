<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 64);
            $table->text('description')->nullable();
            $table->string('color', 16)->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });

        Schema::create('endpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 64);
            $table->string('secret', 32);
            $table->text('description')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('enabled')->default(true);

            // The response returned to the caller
            $table->smallInteger('response_status')->default(200);
            $table->text('response_body')->nullable();
            $table->string('response_content_type')->default('text/plain');
            $table->integer('response_delay_ms')->default(0);
            $table->boolean('cors')->default(true);

            // Retention: null = forever
            $table->integer('retention_days')->nullable();
            $table->integer('max_messages')->nullable();

            $table->bigInteger('messages_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'slug']);
            $table->index('secret');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('endpoint_id')->constrained('endpoints')->cascadeOnDelete();
            $table->string('method', 10);
            $table->text('url');
            $table->string('path_suffix')->nullable();
            $table->jsonb('query')->default('{}');
            $table->jsonb('headers')->default('{}');
            $table->text('body')->nullable();
            $table->jsonb('body_json')->nullable();
            $table->jsonb('files')->default('[]');
            $table->string('content_type')->nullable();
            $table->integer('size')->default(0);
            $table->boolean('truncated')->default(false);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->smallInteger('response_status')->nullable();

            // State of a rule run
            $table->timestamp('processed_at')->nullable();
            $table->jsonb('matched_rules')->default('[]');
            $table->smallInteger('actions_ok')->default(0);
            $table->smallInteger('actions_failed')->default(0);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['endpoint_id', 'id']);
            $table->index('created_at');
        });

        // Fast search inside the JSON body
        DB::statement('CREATE INDEX messages_body_json_gin ON messages USING gin (body_json jsonb_path_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('endpoints');
        Schema::dropIfExists('groups');
    }
};

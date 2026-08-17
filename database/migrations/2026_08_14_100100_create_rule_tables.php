<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('priority')->default(100);

            // Exactly one of these is set: a rule attached to a group is
            // inherited by every endpoint below it.
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->foreignId('endpoint_id')->nullable()->constrained('endpoints')->cascadeOnDelete();

            $table->jsonb('conditions')->default('{"type":"group","op":"and","children":[]}');
            $table->boolean('stop_processing')->default(false);

            $table->bigInteger('match_count')->default(0);
            $table->timestamp('last_matched_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'enabled']);
            $table->index(['endpoint_id', 'enabled']);
        });

        Schema::create('rule_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('rules')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('name')->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('position')->default(0);
            $table->jsonb('config')->default('{}');
            $table->timestamps();
        });

        Schema::create('action_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('rules')->nullOnDelete();
            $table->foreignId('rule_action_id')->nullable()->constrained('rule_actions')->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 16); // success | failed | skipped
            $table->text('summary')->nullable();
            $table->text('error')->nullable();
            $table->jsonb('detail')->default('{}');
            $table->integer('duration_ms')->default(0);
            $table->integer('attempt')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['message_id', 'id']);
            $table->index(['rule_id', 'id']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_runs');
        Schema::dropIfExists('rule_actions');
        Schema::dropIfExists('rules');
    }
};

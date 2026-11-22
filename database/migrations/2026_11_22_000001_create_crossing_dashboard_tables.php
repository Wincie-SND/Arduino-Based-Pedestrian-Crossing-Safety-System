<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crossing_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('incident_code', 40);
            $table->string('incident_type');
            $table->string('status', 32)->default('needs_review');
            $table->string('evidence_path')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['team_id', 'incident_code']);
            $table->index(['team_id', 'occurred_at']);
        });

        Schema::create('crossing_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 40);
            $table->string('status', 24)->default('unknown');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'name']);
        });

        Schema::create('crossing_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_code', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 24)->default('open');
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->unique(['team_id', 'reference_code']);
            $table->index(['team_id', 'reported_at']);
        });

        Schema::create('crossing_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('notification_code', 40);
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('level', 24)->default('info');
            $table->timestamp('occurred_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'notification_code']);
            $table->index(['team_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crossing_notifications');
        Schema::dropIfExists('crossing_issues');
        Schema::dropIfExists('crossing_devices');
        Schema::dropIfExists('crossing_incidents');
    }
};

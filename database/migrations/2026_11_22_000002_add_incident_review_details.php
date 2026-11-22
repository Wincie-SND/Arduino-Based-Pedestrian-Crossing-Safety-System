<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crossing_incidents', function (Blueprint $table) {
            $table->string('detection_method')->nullable();
            $table->string('pedestrian_status')->nullable();
            $table->string('vehicle_status')->nullable();
            $table->string('device_status')->nullable();
            $table->string('alert_transmission_status')->nullable();
            $table->string('evidence_source')->nullable();
            $table->text('event_description')->nullable();
            $table->foreignId('assigned_enforcer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
        });

        Schema::create('crossing_incident_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crossing_incident_id')->constrained('crossing_incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_code', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['crossing_incident_id', 'event_code']);
            $table->index(['crossing_incident_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crossing_incident_events');

        Schema::table('crossing_incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_enforcer_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'detection_method',
                'pedestrian_status',
                'vehicle_status',
                'device_status',
                'alert_transmission_status',
                'evidence_source',
                'event_description',
                'reviewed_at',
                'review_remarks',
            ]);
        });
    }
};

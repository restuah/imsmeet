<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('password')->nullable();
            $table->enum('status', ['scheduled', 'active', 'ended'])->default('scheduled');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('max_participants')->default(50);
            $table->boolean('is_recording_enabled')->default(false);
            $table->boolean('is_chat_enabled')->default(true);
            $table->boolean('is_whiteboard_enabled')->default(true);
            $table->boolean('waiting_room_enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('host_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};

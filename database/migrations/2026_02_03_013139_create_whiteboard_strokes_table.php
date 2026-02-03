<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whiteboard_strokes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stroke_id')->unique();
            $table->enum('tool', ['pen', 'eraser', 'line', 'rectangle', 'circle', 'text', 'clear'])->default('pen');
            $table->json('points');
            $table->string('color')->default('#000000');
            $table->integer('stroke_width')->default(2);
            $table->text('text_content')->nullable();
            $table->integer('font_size')->nullable();
            $table->timestamps();

            $table->index(['meeting_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whiteboard_strokes');
    }
};

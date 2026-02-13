<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\MeetingParticipantController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\WhiteboardController;
use App\Http\Controllers\Api\RecordingController;
use App\Http\Controllers\Api\SignalingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);

    // Meetings
    Route::apiResource('meetings', MeetingController::class);
    Route::post('/meetings/{meeting}/start', [MeetingController::class, 'start']);
    Route::post('/meetings/{meeting}/end', [MeetingController::class, 'end']);
    Route::post('/meetings/{meeting}/join', [MeetingController::class, 'join']);
    Route::post('/meetings/{meeting}/leave', [MeetingController::class, 'leave']);
    Route::get('/meetings/join/{uuid}', [MeetingController::class, 'joinByUuid']);

    // Participants
    Route::get('/meetings/{meeting}/participants', [MeetingParticipantController::class, 'index']);
    Route::post('/meetings/{meeting}/participants/{participant}/mute', [MeetingParticipantController::class, 'mute']);
    Route::post('/meetings/{meeting}/participants/{participant}/unmute', [MeetingParticipantController::class, 'unmute']);
    Route::post('/meetings/{meeting}/participants/{participant}/kick', [MeetingParticipantController::class, 'kick']);
    Route::post('/meetings/{meeting}/participants/{participant}/promote', [MeetingParticipantController::class, 'promote']);
    Route::post('/meetings/{meeting}/participants/{participant}/demote', [MeetingParticipantController::class, 'demote']);
    Route::post('/meetings/{meeting}/mute-all', [MeetingParticipantController::class, 'muteAll']);

    // Media controls
    Route::post('/meetings/{meeting}/toggle-video', [MeetingParticipantController::class, 'toggleVideo']);
    Route::post('/meetings/{meeting}/toggle-audio', [MeetingParticipantController::class, 'toggleAudio']);
    Route::post('/meetings/{meeting}/screen-share/start', [MeetingParticipantController::class, 'startScreenShare']);
    Route::post('/meetings/{meeting}/screen-share/stop', [MeetingParticipantController::class, 'stopScreenShare']);
    Route::post('/meetings/{meeting}/raise-hand', [MeetingParticipantController::class, 'raiseHand']);
    Route::post('/meetings/{meeting}/lower-hand', [MeetingParticipantController::class, 'lowerHand']);

    // Signaling for WebRTC
    Route::post('/meetings/{meeting}/signal', [SignalingController::class, 'signal']);
    Route::post('/meetings/{meeting}/ice-candidate', [SignalingController::class, 'iceCandidate']);
    Route::get('/meetings/{meeting}/ice-servers', [MeetingController::class, 'getIceServers']);

    // Chat
    Route::get('/meetings/{meeting}/messages', [ChatController::class, 'index']);
    Route::post('/meetings/{meeting}/messages', [ChatController::class, 'store']);

    // Whiteboard
    Route::get('/meetings/{meeting}/whiteboard', [WhiteboardController::class, 'index']);
    Route::post('/meetings/{meeting}/whiteboard', [WhiteboardController::class, 'store']);
    Route::delete('/meetings/{meeting}/whiteboard/{stroke}', [WhiteboardController::class, 'destroy']);
    Route::post('/meetings/{meeting}/whiteboard/clear', [WhiteboardController::class, 'clear']);

    // Recordings
    Route::get('/meetings/{meeting}/recordings', [RecordingController::class, 'index']);
    Route::post('/meetings/{meeting}/recordings/start', [RecordingController::class, 'start']);
    Route::post('/meetings/{meeting}/recordings/{recording}/stop', [RecordingController::class, 'stop']);
    Route::post('/recordings/{recording}/upload', [RecordingController::class, 'upload']);
    Route::get('/recordings/{recording}/download', [RecordingController::class, 'download']);
    Route::delete('/recordings/{recording}', [RecordingController::class, 'destroy']);

    // Admin routes
    Route::middleware('role:admin|superadmin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
        Route::post('/users/{user}/role', [AdminController::class, 'assignRole']);
        Route::get('/meetings', [AdminController::class, 'meetings']);
        Route::get('/statistics', [AdminController::class, 'statistics']);
    });

    // Superadmin routes
    Route::middleware('role:superadmin')->prefix('superadmin')->group(function () {
        Route::get('/roles', [AdminController::class, 'roles']);
        Route::post('/roles', [AdminController::class, 'createRole']);
        Route::get('/permissions', [AdminController::class, 'permissions']);
        Route::post('/roles/{role}/permissions', [AdminController::class, 'assignPermissions']);
        Route::get('/system-settings', [AdminController::class, 'systemSettings']);
        Route::put('/system-settings', [AdminController::class, 'updateSystemSettings']);
    });

    // User management
    Route::get('/users/search', [UserController::class, 'search']);
});

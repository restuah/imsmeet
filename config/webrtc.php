<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TURN Server Configuration
    |--------------------------------------------------------------------------
    */
    'turn_server' => env('TURN_SERVER', 'domainanda.com'),
    'turn_username' => env('TURN_USERNAME', 'username'),
    'turn_password' => env('TURN_PASSWORD', 'pasword'),
    'turn_secret' => env('TURN_SECRET', ''),
    
    /*
    |--------------------------------------------------------------------------
    | WebRTC Settings
    |--------------------------------------------------------------------------
    */
    'max_participants' => env('WEBRTC_MAX_PARTICIPANTS', 50),
    'default_video_bitrate' => env('WEBRTC_DEFAULT_VIDEO_BITRATE', 1000000),
    'default_audio_bitrate' => env('WEBRTC_DEFAULT_AUDIO_BITRATE', 64000),
];
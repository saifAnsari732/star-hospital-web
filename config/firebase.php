<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Star Hospital Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Auth, Cloud Firestore, Cloud Messaging (FCM),
    | and Cloud Storage used across web portal and mobile app synchronization.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID', 'star-f3312'),
    'api_key' => env('FIREBASE_API_KEY', 'AIzaSyDBIo4nBnE7tJGmROpqz2ZhZCjKehW5Z_I'),
    'auth_domain' => env('FIREBASE_AUTH_DOMAIN', 'star-f3312.firebaseapp.com'),
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', 'star-f3312.firebasestorage.app'),
    'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID', '374748056859'),
    'app_id' => env('FIREBASE_APP_ID', '1:374748056859:web:46ff4749ad354ed875d7be'),
    'credentials_file' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),
];

<?php
/**
 * Firebase Firestore Configuration
 * 
 * Project: amuletsiam
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

// Set default timezone to Asia/Bangkok (+07:00 Thailand)
date_default_timezone_set('Asia/Bangkok');

// --- FIREBASE CONFIGURATION ---
// กรุณาใส่ API Key ของคุณที่นี่
define('FIREBASE_API_KEY', 'AIzaSyDtNfbfvspe5LeQHBi8Vtl8sjEC4Aitq9g');

define('FIREBASE_PROJECT_ID', 'amuletsiam');
define('FIREBASE_AUTH_DOMAIN', 'amuletsiam.firebaseapp.com');
define('FIREBASE_STORAGE_BUCKET', 'amuletsiam.firebasestorage.app');
define('FIREBASE_MESSAGING_SENDER_ID', '880058490397');
define('FIREBASE_APP_ID', '1:880058490397:web:af3d20e6fc2e9055f1b0f4');

// Firestore REST API Base URL
define('FIRESTORE_REST_ENDPOINT', 'https://firestore.googleapis.com/v1/projects/' . FIREBASE_PROJECT_ID . '/databases/(default)/documents/');

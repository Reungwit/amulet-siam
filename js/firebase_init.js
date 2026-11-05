/**
 * Firebase Client SDK Initialization & Real-Time Listener Module
 * 
 * Provides centralized Firebase App & Firestore instances for the frontend.
 * 
 * @package AmuletPlatform
 */

import { initializeApp } from "https://www.gstatic.com/firebasejs/13.2.0/firebase-app.js";
import { 
    getFirestore, 
    collection, 
    doc, 
    onSnapshot, 
    query, 
    orderBy, 
    limit,
    addDoc,
    serverTimestamp 
} from "https://www.gstatic.com/firebasejs/13.2.0/firebase-firestore.js";

// Global Firebase Configuration populated from PHP constants
const firebaseConfig = {
    apiKey: window.FIREBASE_CONFIG?.apiKey || "YOUR_FIREBASE_API_KEY_HERE",
    authDomain: "amuletsiam.firebaseapp.com",
    projectId: "amuletsiam",
    storageBucket: "amuletsiam.firebasestorage.app",
    messagingSenderId: "880058490397",
    appId: "1:880058490397:web:af3d20e6fc2e9055f1b0f4"
};

// Initialize Firebase App & Firestore
const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

// Expose to window for application-wide access
window.firebaseApp = app;
window.firestoreDb = db;
window.FirestoreSDK = {
    doc,
    collection,
    onSnapshot,
    query,
    orderBy,
    limit,
    addDoc,
    serverTimestamp
};

console.log('[FIREBASE] Client SDK initialized successfully for project: amuletsiam');


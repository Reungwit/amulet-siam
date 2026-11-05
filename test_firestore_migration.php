<?php
/**
 * Automated Verification Test for Firebase Firestore Migration
 * 
 * Verifies:
 * 1. Firebase Configuration Constants (Project ID, Auth Domain, Storage Bucket, App ID)
 * 2. Firestore Document Encoding & Decoding (Map, Array, Primitive types)
 * 3. Firestore Document Path Parsing
 * 4. Amulet Repository Adapter Functionality
 * 5. Frontend Client SDK script presence
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/firebase_config.php';
require_once __DIR__ . '/includes/firestore_client.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/repository.php';

function run_test(string $name, callable $fn): void {
    echo "========================================\n";
    echo "TEST: {$name}\n";
    try {
        $result = $fn();
        if ($result === true) {
            echo "STATUS: \033[32m[PASSED]\033[0m\n";
        } else {
            echo "STATUS: \033[31m[FAILED]\033[0m: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
            exit(1);
        }
    } catch (Throwable $e) {
        echo "STATUS: \033[31m[EXCEPTION]\033[0m: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// 1. Verify Configuration Constants
run_test("1. Firebase Configuration Constants Verification", function() {
    if (FIREBASE_PROJECT_ID !== 'amuletsiam') {
        return "Project ID mismatch: " . FIREBASE_PROJECT_ID;
    }
    if (FIREBASE_AUTH_DOMAIN !== 'amuletsiam.firebaseapp.com') {
        return "Auth domain mismatch: " . FIREBASE_AUTH_DOMAIN;
    }
    if (FIREBASE_STORAGE_BUCKET !== 'amuletsiam.firebasestorage.app') {
        return "Storage bucket mismatch: " . FIREBASE_STORAGE_BUCKET;
    }
    if (FIREBASE_APP_ID !== '1:880058490397:web:af3d20e6fc2e9055f1b0f4') {
        return "App ID mismatch: " . FIREBASE_APP_ID;
    }
    return true;
});

// 2. Verify Firestore Encoder & Decoder for Nested Radar Chart Stats
run_test("2. Firestore Type Encoding & Decoding (6-Point Radar Stats)", function() {
    $original_data = [
        'title' => 'พระสมเด็จวัดระฆัง',
        'price' => 250000.50,
        'is_available' => true,
        'views' => 120,
        'stats' => [
            'protection' => 10,
            'popularity' => 9,
            'charm' => 8,
            'evasion' => 9,
            'invincibility' => 10,
            'wealth' => 9,
        ],
        'tags' => ['พระยอดนิยม', 'พิมพ์ใหญ่']
    ];

    $encoded = [];
    foreach ($original_data as $k => $v) {
        $encoded[$k] = FirestoreClient::encodeValue($v);
    }

    // Verify Firestore Type mapping
    if (!isset($encoded['price']['doubleValue'])) return "Double encoding failed";
    if (!isset($encoded['is_available']['booleanValue'])) return "Boolean encoding failed";
    if (!isset($encoded['views']['integerValue'])) return "Integer encoding failed";
    if (!isset($encoded['stats']['mapValue']['fields']['protection']['integerValue'])) return "Map encoding failed";
    if (!isset($encoded['tags']['arrayValue']['values'][0]['stringValue'])) return "Array encoding failed";

    // Decode and verify exact fidelity
    $decoded = [];
    foreach ($encoded as $k => $v) {
        $decoded[$k] = FirestoreClient::decodeValue($v);
    }

    if ($decoded['title'] !== $original_data['title']) return "Title decoded mismatch";
    if ($decoded['price'] !== $original_data['price']) return "Price decoded mismatch";
    if ($decoded['stats']['protection'] !== 10) return "Protection stat decoded mismatch";
    if ($decoded['stats']['wealth'] !== 9) return "Wealth stat decoded mismatch";
    if ($decoded['tags'][0] !== 'พระยอดนิยม') return "Tag array decoded mismatch";

    return true;
});

// 3. Verify Firestore Document Parser
run_test("3. Firestore Document Parser", function() {
    $raw_doc = [
        'name' => 'projects/amuletsiam/databases/(default)/documents/amulets/doc_123',
        'fields' => [
            'title' => ['stringValue' => 'เหรียญหลวงปู่เอี่ยม'],
            'price' => ['doubleValue' => 85000.00],
        ],
        'createTime' => '2026-11-05T10:00:00Z',
    ];

    $parsed = FirestoreClient::parseDocument($raw_doc);
    if ($parsed['id'] !== 'doc_123') return "Document ID extraction failed";
    if ($parsed['title'] !== 'เหรียญหลวงปู่เอี่ยม') return "Title parse failed";
    if ($parsed['price'] !== 85000.00) return "Price parse failed";

    return true;
});

// 4. Verify Amulet Repository Interface
run_test("4. Amulet Repository Interface Integration", function() {
    $repo = get_amulet_repository();
    $categories = $repo->getCategories();
    if (empty($categories)) return "Categories empty from repository";

    $amulets = $repo->getAvailableAmulets();
    if (empty($amulets)) return "Amulets empty from repository";

    $first_amulet = $repo->getAmuletById($amulets[0]['id']);
    if (!$first_amulet) return "Failed to retrieve amulet by ID";
    if (!isset($first_amulet['stats']['protection'])) return "Spiritual stats missing on retrieved amulet";

    return true;
});

// 5. Verify Frontend Client SDK Assets
run_test("5. Frontend Firebase Client SDK Assets Check", function() {
    $init_file = __DIR__ . '/js/firebase_init.js';
    if (!file_exists($init_file)) return "Missing js/firebase_init.js";

    $content = file_get_contents($init_file);
    if (!str_contains($content, 'amuletsiam')) return "Project ID missing in js/firebase_init.js";
    if (!str_contains($content, 'getFirestore')) return "Firestore import missing in js/firebase_init.js";

    $footer_file = __DIR__ . '/includes/footer.php';
    $footer_content = file_get_contents($footer_file);
    if (!str_contains($footer_content, 'FIREBASE_CONFIG')) return "FIREBASE_CONFIG missing in includes/footer.php";
    if (!str_contains($footer_content, 'js/firebase_init.js')) return "Script tag missing in includes/footer.php";

    return true;
});

echo "\n=========================================================\n";
echo "🎉 ALL AUTOMATED VERIFICATION TESTS PASSED SUCCESSFULLY!\n";
echo "=========================================================\n";


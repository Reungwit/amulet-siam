<?php
/**
 * Database Seeder Script
 * 
 * Populates initial categories and default administrator account.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db_connect.php';

try {
    $pdo = get_db_connection();
    echo "Running database seeder...\n";

    // 1. Seed Categories
    $categories = [
        ['name' => 'พระสมเด็จ', 'slug' => 'somdej', 'description' => 'จักรพรรดิแห่งพระเครื่อง เมตตามหานิยม แคล้วคลาด'],
        ['name' => 'พระเหรียญคณาจารย์', 'slug' => 'coins', 'description' => 'เหรียญพระเกจิอาจารย์ยอดนิยม มหาอุตม์ โชคลาภ'],
        ['name' => 'พระกรุยอดนิยม', 'slug' => 'kru', 'description' => 'พระกรุโบราณ ทรงคุณค่าทางประวัติศาสตร์และพุทธคุณ'],
        ['name' => 'เครื่องรางของขลัง', 'slug' => 'talismans', 'description' => 'เบี้ยแก้ ตะกรุด มีดหมอ ป้องกันภัย มหาเสน่ห์'],
        ['name' => 'พระกริ่งและรูปหล่อ', 'slug' => 'kring', 'description' => 'พระพุทธรูปจำลอง พระกริ่ง สัมฤทธิ์ผลแห่งชีวิต'],
    ];

    $cat_stmt = $pdo->prepare("INSERT INTO categories (name, slug, description) 
        VALUES (:name, :slug, :description)
        ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)");

    foreach ($categories as $cat) {
        $cat_stmt->execute($cat);
    }
    echo "✓ Categories seeded successfully.\n";

    // 2. Seed Default Admin User
    $admin_user = 'admin';
    $admin_email = 'admin@amulet.local';
    $admin_pass = 'admin123456';
    $password_hash = password_hash($admin_pass, PASSWORD_BCRYPT);

    $user_check = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
    $user_check->execute(['username' => $admin_user, 'email' => $admin_email]);
    $existing = $user_check->fetch();

    if (!$existing) {
        $insert_user = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role)
            VALUES (:username, :email, :password_hash, :full_name, 'admin')");
        $insert_user->execute([
            'username' => $admin_user,
            'email' => $admin_email,
            'password_hash' => $password_hash,
            'full_name' => 'ผู้ดูแลระบบสูงสุด',
        ]);
        echo "✓ Default admin created (username: admin / password: {$admin_pass})\n";
    } else {
        echo "✓ Admin user already exists.\n";
    }

    echo "Seeding completed successfully!\n";
} catch (Exception $e) {
    echo "Seeding error: " . $e->getMessage() . "\n";
    exit(1);
}


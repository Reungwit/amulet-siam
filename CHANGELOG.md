# CHANGELOG

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added - 2026-11-05
- **100% Pure Cloud Firestore Cutover Completed**:
  - Eliminated all hard dependencies on MySQL/phpMyAdmin across all 21 application endpoints.
  - Re-architected all modules to read/write natively to Google Cloud Firestore (`amuletsiam`):
    - **Authentication**: `register.php`, `login.php`, `profile.php` directly query and persist users in Firestore with bcrypt hashing.
    - **E-Commerce & Orders**: `index.php`, `amulet.php`, `cart.php`, `checkout.php`, `my_orders.php` operate 100% on Firestore collections (`amulets`, `categories`, `orders`).
    - **Real-Time Auctions & Bidding**: `auctions.php`, `auction_room.php`, `api/place_bid.php`, `api/auction_status.php` read and persist live bids, room status, and +3 min anti-snipe logic directly in Firestore.
    - **Admin CMS**: `admin/index.php`, `admin/amulets.php`, `admin/amulet_form.php`, `admin/amulet_delete.php`, `admin/categories.php`, `admin/category_delete.php`, `admin/orders.php`, `admin/auctions.php` fully powered by Firestore REST client.
  - Added safe fallback in `includes/db_connect.php` to prevent fatal PDO errors when MySQL is not available (Cloud container environment).
  - Created and executed comprehensive automated test suite `test_pure_firestore.php` verifying zero MySQL dependency and 100% Firestore operations.
- Prepared production deployment package for **Render (Free Cloud PHP Hosting)**:
  - `Dockerfile`: Production PHP 8.2 Apache container with `gd`, `pdo`, `curl`, and rewrite modules.
  - `render.yaml`: Blueprint configuration for one-click deployment on Render.
  - `.dockerignore` & `.gitignore`: Clean deployment artifacts.
  - Initialized Git repository and created baseline deployment commit.
- Successfully migrated all data from MySQL to live **Google Cloud Firebase Firestore**:
  - `categories`: 5 categories imported.
  - `users`: 5 accounts migrated with bcrypt hashes.
  - `amulets`: 5 amulets with nested 6-point spiritual radar stats maps imported.
  - `auctions` & `bids`: 3 live auction rooms and bidding histories imported.
  - `orders`: Orders with item breakdown arrays imported.
- Integrated `AmuletRepository` adapter into `index.php` and `amulet.php` to fetch live data directly from Firestore collections.
- Completed Automated Verification Test Suite for Firestore Migration (`test_firestore_migration.php`) with 100% test pass rate.
- Implemented Unified Database Adapter (`includes/repository.php`) bridging Firestore collections and MySQL relational data.
- Configured project credentials for `amuletsiam` across PHP backend and JavaScript frontend:
  - `includes/firebase_config.php`: Configured `FIREBASE_PROJECT_ID = 'amuletsiam'`, authDomain, storageBucket, and appId.
  - `js/firebase_init.js`: Created Firebase v13.2.0 client module initializing `initializeApp` and `getFirestore`.
  - `includes/footer.php`: Injected global `window.FIREBASE_CONFIG` and imported module script.
- Designed and scaffolded Firebase Firestore migration architecture:
  - `includes/firebase_config.php`: Central configuration for Firebase Project ID, Web API Key, and Firestore REST endpoints.
  - `includes/firestore_client.php`: Lightweight, zero-dependency Native PHP Firestore REST Client supporting CRUD and automatic Type Encoding/Decoding.
  - `migrate_mysql_to_firestore.php`: Automated CLI migration tool to export all data from MySQL tables (`users`, `categories`, `amulets`, `amulet_stats`, `auctions`, `bids`, `orders`) into Firestore NoSQL collections with nested 6-point radar stats.
- Configured application-wide Timezone to `Asia/Bangkok` (+07:00 Thailand) in `includes/db_connect.php` (`date_default_timezone_set('Asia/Bangkok')` and MySQL session `time_zone = '+07:00'`).
- Fixed real-time countdown timer timezone parsing in `auctions.php`, `auction_room.php`, and `js/auction.js` to prevent room timer discrepancies.
- Updated active auctions to current Thai local time (+24h).
- Implemented Real-Time Auction Module:
  - `api/place_bid.php`: AJAX POST bidding endpoint with Anti-Snipe (+3 minutes on final 60s bids) and minimum bid increment validation.
  - `api/auction_status.php`: Polling endpoint returning remaining seconds, live price, and latest masked bid history.
  - `auctions.php`: Live auction room catalog with dynamic cards and countdown timers.
  - `auction_room.php`: Interactive bidding room embedding HTML5 Canvas Radar Chart, big countdown clock, AJAX bidding form, and live bid history.
  - `js/auction.js`: Full vanilla JavaScript auction controller.
  - `admin/auctions.php`: Admin CMS to create, monitor, and close auction listings.
- Built Comprehensive Automated Test Suite (`test_suite.php`) covering Schema, Auth, Upload permissions, 6-Point Radar Chart stats, and Anti-Snipe bidding logic with 100% pass rate.
- Fixed file upload permissions (`chmod 777`) for `/images/uploads/` and `/images/uploads/slips/`.
- Enhanced file upload error diagnostics and size handling (up to 20MB) in `admin/amulet_form.php` and `checkout.php`.
- Implemented User Profile and Account Management (`profile.php`) with full name, phone, address, and password change support.
- Added Profile navigation link in `includes/header.php`.
- Created comprehensive architecture and implementation plan for Real-Time Auction System (`auction_plan.md`).

### Added - 2026-11-04
- Implemented full Shopping Cart system (`cart.php`) supporting adding items, removing items, total calculation, and session cart persistence.
- Implemented Checkout & Payment Slip Upload (`checkout.php`) with image upload to `images/uploads/slips/`, shipping address input, and transaction creation in `orders` and `order_items`.
- Implemented Customer Order History & Tracking (`my_orders.php`).
- Implemented Admin Category CRUD (`admin/categories.php`, `admin/category_delete.php`) allowing full category creation, editing, and deletion.
- Implemented Admin Order & Slip Verification CMS (`admin/orders.php`) with image modal, status updating (`pending_verification` -> `preparing` -> `shipped` with tracking number / `cancelled`), and atomic inventory availability management.
- Implemented Frontend Catalog (`index.php`) with POST-based filtering and search (title, monk, temple, category).
- Implemented Amulet Detail View (`amulet.php`) supporting both POST and GET routing with full sacred provenance metadata and status breakdowns.
- Created HTML5 Canvas 6-point Radar Chart engine (`js/radar.js`) with Retina-sharp DPR scaling, concentric hexagon webs, glowing gold vertices, and entrance animation.
- Enhanced `css/style.css` with responsive 2-column detail layouts, filter bars, and status pills.
- Connected live database `amulet_db` via XAMPP MySQL.
- Initialized database seeder (`seed.php`) with categories and default administrator account.
- Implemented Authentication module (`register.php`, `login.php`, `logout.php`).
- Implemented Admin Guard (`includes/admin_guard.php`).
- Implemented Admin Dashboard (`admin/index.php`).
- Implemented full Amulet CRUD (`admin/amulets.php`, `admin/amulet_form.php`, `admin/amulet_delete.php`) with image upload handling and 6-point spiritual status attributes (1-10) stored in `amulet_stats`.
- Initialized project architecture for Thai Amulet Marketplace & Real-time Auction platform.
- Documented implementation plan in `TODOS.md` and initial system structure in `project_info.txt`.
- Set up directory skeleton: `/css`, `/js`, `/images`, `/includes`, `/admin`.
- Implemented `/css/style.css` featuring luxury dark red (`#5e030f`) and gold (`#E9D793`) palette.
- Created `/includes/db_connect.php` with robust PDO configuration and query helpers.
- Created `/includes/header.php` and `/includes/footer.php` with navigation and session awareness.
- Created `/js/main.js` and `/js/auction.js` utility scripts.
- Created `schema.sql` database migration script covering all 4 modules.
- Created initial landing page `index.php`.


# Thai Amulet Marketplace & Real-Time Auction

## Phase 8: 100% Pure Firestore Migration & Render Optimization
### Done ✓
- [x] Full codebase audit to locate legacy MySQL queries
- [x] Refactor Auth module (register, login, profile) to 100% Firestore
- [x] Refactor E-Commerce & Orders module (index, amulet, cart, checkout, my_orders) to 100% Firestore
- [x] Refactor Real-Time Auction & APIs (auctions, auction_room, api/place_bid, api/auction_status) to 100% Firestore
- [x] Refactor Admin CMS (dashboard, amulets, categories, orders, auctions) to 100% Firestore
- [x] Safe MySQL disconnect handling in includes/db_connect.php
- [x] Automated Verification Test Suite in test_pure_firestore.php (100% PASSED)

## Phase 7: Cloud Deployment (Render.com)
### Done ✓
- [x] Create production Dockerfile with PHP 8.2 Apache & extensions
- [x] Create render.yaml Infrastructure as Code blueprint
- [x] Configure .gitignore and .dockerignore
- [x] Initialize Git repository and commit full codebase

## Phase 6: Firebase Firestore Migration
### Done ✓
- [x] Configure Firebase credentials in includes/firebase_config.php
- [x] Initialize Firebase App & Firestore Client SDK in js/firebase_init.js
- [x] Implement Native PHP Firestore REST Client in includes/firestore_client.php
- [x] Build Automated MySQL to Firestore Migration script in migrate_mysql_to_firestore.php
- [x] Implement Database Access Adapter with Firestore mapping & fallback in includes/repository.php
- [x] Complete Automated Verification Test Suite in test_firestore_migration.php (100% PASSED)

## Phase 1: Project Setup, Custom CSS, and DB Connection
### Done ✓
- [x] Initialize directory structure & documentation
- [x] Create custom CSS theme variables & responsive styles in css/style.css
- [x] Create PDO database connection in includes/db_connect.php
- [x] Create layout templates includes/header.php & includes/footer.php
- [x] Create database schema in schema.sql
- [x] Create starter JS files js/main.js & js/auction.js
- [x] Create landing page index.php

## Phase 2: Authentication & Admin CMS (20 - 40%)
### Done ✓
- [x] Database seeder for admin and categories
- [x] User registration, login, logout & session management
- [x] Admin guard & Dashboard UI in admin/index.php
- [x] Amulet CRUD (List, Add, Edit, Delete) with image upload
- [x] 6 Spiritual status inputs (1-10) in Amulet form & save to amulet_stats

## Phase 3: Frontend Catalog & Radar Chart Graphic (40 - 60%)
### Done ✓
- [x] Catalog page with POST search & category filtering
- [x] Amulet detail view with sacred provenance & action triggers
- [x] HTML5 Canvas 6-point Radar Chart with animation in js/radar.js
- [x] Enhanced responsive theme styles in css/style.css

## Phase 4: Shopping Cart, Orders & Category Management
### Done ✓
- [x] Shopping cart system in cart.php (add, remove, clear, calculate)
- [x] Checkout page with bank slip upload & order creation in checkout.php
- [x] Customer order history & tracking in my_orders.php
- [x] Complete Category CRUD in admin/categories.php & admin/category_delete.php
- [x] Admin Order & Slip verification management in admin/orders.php

## Phase 5: Real-time Auction System (ระบบประมูลพระ)
### Done ✓
- [x] Implement AJAX bidding endpoint api/place_bid.php with anti-snipe logic
- [x] Implement live auction polling api/auction_status.php
- [x] Build auction catalog auctions.php & bidding room auction_room.php
- [x] Enhance vanilla JS auction.js with countdown & live polling
- [x] Build Admin Auction CMS admin/auctions.php
- [x] Comprehensive Automated Test Suite in test_suite.php (100% PASSED)

## User Account & Upload Fixes
### Done ✓
- [x] Fix folder write permissions for images/uploads/ and images/uploads/slips/
- [x] Enhance file upload error handling and increase limit to 20MB
- [x] Create user profile page profile.php and update navigation header
- [x] Set global timezone to Asia/Bangkok (+07:00 TH) across PHP, MySQL, and JS countdowns
### Todo
- [ ] Auction countdown timer with vanilla JS ~1d #auction @dev 2026-11-06
- [ ] Live bidding endpoint (Fetch API) with minimum bid validation ~1d #auction @dev 2026-11-06
- [ ] Anti-snipe extension (+3 mins if bid placed within last 1 min) ~1d #auction @dev 2026-11-06
- [ ] Real-time bid history table ~0.5d #auction @dev 2026-11-06

## Phase 4: Amulet Status Graphic (HTML5 Canvas Radar Chart)
### Todo
- [ ] 6-point Hexagonal Radar Chart canvas component (#E9D793 on #5e030f) ~1d #canvas @dev 2026-11-07
- [ ] Dynamic attribute mapping (Protection, Popularity, Charm, Evasion, Invincibility, Wealth) ~0.5d #canvas @dev 2026-11-07

## Phase 5: Authentication & Admin Panel
### Todo
- [ ] User registration, login, session management ~1d #auth @dev 2026-11-08
- [ ] Admin CMS for amulet CRUD & 1-10 status assignment ~1d #admin @dev 2026-11-08
- [ ] Admin slip verification & auction scheduling ~1d #admin @dev 2026-11-08

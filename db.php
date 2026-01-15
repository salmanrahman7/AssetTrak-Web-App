<?php
// db.php - Connects to MySQL and sets up constants.
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'asset_trak_web_app_db');

// System Constants
define('MIN_PASS_LENGTH', 8);
define('EXPIRING_SOON_DAYS', 30);

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_EDITOR', 'editor');
define('ROLE_STAFF', 'staff');

// User Statuses
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');

// Asset Approval Statuses
define('ASSET_PENDING', 'pending');
define('ASSET_APPROVED', 'approved');
define('ASSET_REJECTED', 'rejected');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$upload_dir = 'uploads/';

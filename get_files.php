<?php
// get_files.php [Used by the "View Files" modal to fetch data asynchronously (if you plan to re-implement AJAX later) or you can rely on the data already loaded in the dashboard table. (Note: Since I implemented the "No AJAX" solution in the dashboard code above where file data is loaded with the page, this file isn't strictly necessary for the current logic, but good to have for future scalability).]
require_once 'db.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) { 
    echo json_encode([]); 
    exit; 
}

if (isset($_GET['asset_id'])) {
    header('Content-Type: application/json');
    $aid = (int)$_GET['asset_id'];
    $stmt = $conn->prepare("SELECT file_path, file_type, original_name FROM asset_files WHERE asset_meta_id = ?");
    $stmt->bind_param("i", $aid);
    $stmt->execute();
    $res = $stmt->get_result();
    $files = [];
    while($row = $res->fetch_assoc()) { $files[] = $row; }
    echo json_encode($files);
}
?>
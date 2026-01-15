<?php
// functions.php Handles flash messages and basic auth checks.

function set_flash_message($type, $msg) {
    $_SESSION['flash_message'] = ['type' => $type, 'text' => $msg];
}

function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        $bgClass = $msg['type'] === 'success' ? 'bg-green-600' : 'bg-red-600';
        $icon = $msg['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        echo "
        <div id='flash-msg' class='fixed top-5 right-5 z-[100] px-6 py-4 rounded shadow-lg text-white $bgClass'>
            <div class='flex items-center'>
                <i class='fas $icon mr-3'></i>
                <span>" . htmlspecialchars($msg['text']) . "</span>
                <button onclick=\"document.getElementById('flash-msg').remove()\" class='ml-4 text-white hover:text-gray-200'><i class='fas fa-times'></i></button>
            </div>
        </div>";
    }
}

function check_auth() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_status'] !== 'approved') {
        header("Location: login.php");
        exit;
    }
}

function check_admin() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        header("Location: dashboard.php"); // Redirect non-admins back to dashboard
        exit;
    }
}
?>
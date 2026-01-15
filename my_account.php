<?php
// my_account.php
require_once 'db.php';
require_once 'functions.php';
check_auth();

// Fetch current user data for display
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_pass = $_POST['new_password'];
    if (!empty($new_pass) && strlen($new_pass) >= MIN_PASS_LENGTH) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $_SESSION['user_id']);
        $stmt->execute();
        set_flash_message('success', 'Password updated successfully.');
    } else {
        set_flash_message('error', 'Password must be at least '.MIN_PASS_LENGTH.' chars.');
    }
    header("Location: my_account.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Account - AssetTrak</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }</style>
</head>
<body class="text-gray-800">
    <?php display_flash_message(); ?>
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>
        <div class="flex-1 ml-64 p-8">
            <div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-2">My Profile</h2>
                <div class="mb-6">
                    <div class="grid grid-cols-2 gap-4 mb-2"><span class="text-gray-500 font-medium">Full Name:</span><span class="text-gray-800"><?php echo htmlspecialchars($current_user['full_name']); ?></span></div>
                    <div class="grid grid-cols-2 gap-4 mb-2"><span class="text-gray-500 font-medium">Email:</span><span class="text-gray-800"><?php echo htmlspecialchars($current_user['email']); ?></span></div>
                    <div class="grid grid-cols-2 gap-4"><span class="text-gray-500 font-medium">Role:</span><span class="uppercase text-xs font-bold bg-blue-100 text-blue-800 px-2 py-1 rounded w-max"><?php echo htmlspecialchars($current_user['role']); ?></span></div>
                </div>
                <h3 class="text-lg font-bold mb-4 text-gray-700 mt-8">Change Password</h3>
                <form method="POST" action="my_account.php">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">New Password</label>
                        <input type="password" name="new_password" class="mt-1 block w-full border border-gray-300 rounded p-2" required>
                    </div>
                    <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-900 transition">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
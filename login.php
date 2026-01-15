<?php
// login.php The default entry point.
require_once 'db.php';
require_once 'functions.php';

if (isset($_SESSION['user_id']) && $_SESSION['user_status'] === STATUS_APPROVED) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, full_name, password_hash, role, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password_hash'])) {
            if ($user['status'] === STATUS_APPROVED) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_status'] = $user['status'];
                header("Location: dashboard.php");
                exit;
            } else {
                set_flash_message('error', 'Account pending approval.');
            }
        } else {
            set_flash_message('error', 'Invalid email or password.');
        }
    } else {
        set_flash_message('error', 'Invalid email or password.');
    }
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - AssetTrak</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }</style>
</head>
<body class="text-gray-800">
    <?php display_flash_message(); ?>
    <div class="flex items-center justify-center min-h-screen bg-gray-100">
        <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-md border border-gray-200">
            <h2 class="text-2xl font-bold text-center mb-6 text-blue-600">Login to AssetTrak</h2>
            <form method="POST" action="login.php">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                    <input type="password" name="password" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition">Sign In</button>
            </form>
            <p class="text-center mt-4 text-sm">
                Don't have an account? <a href="register.php" class="text-blue-500 hover:underline">Register here</a>
            </p>
        </div>
    </div>
</body>
</html>
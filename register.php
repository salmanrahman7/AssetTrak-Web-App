<?php
// register.php
require_once 'db.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        set_flash_message('error', 'Passwords do not match.');
    } elseif (strlen($password) < MIN_PASS_LENGTH) {
        set_flash_message('error', 'Password must be at least ' . MIN_PASS_LENGTH . ' characters.');
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            set_flash_message('error', 'Email already registered.');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $full_name, $email, $hash);
            if ($stmt->execute()) {
                set_flash_message('success', 'Registration successful! Please wait for Admin approval.');
            } else {
                set_flash_message('error', 'Registration failed.');
            }
        }
    }
    header("Location: register.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - AssetTrak</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }</style>
</head>
<body class="text-gray-800">
    <?php display_flash_message(); ?>
    <div class="flex items-center justify-center min-h-screen bg-gray-100">
        <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-md border border-gray-200">
            <h2 class="text-2xl font-bold text-center mb-6 text-green-600">Create Account</h2>
            <form method="POST" action="register.php">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Full Name</label>
                    <input type="text" name="full_name" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                    <input type="password" name="password" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-green-500" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-green-500" required>
                </div>
                <button type="submit" class="w-full bg-green-600 text-white font-bold py-2 px-4 rounded hover:bg-green-700 transition">Register</button>
            </form>
            <p class="text-center mt-4 text-sm">
                Already have an account? <a href="login.php" class="text-blue-500 hover:underline">Login here</a>
            </p>
        </div>
    </div>
</body>
</html>
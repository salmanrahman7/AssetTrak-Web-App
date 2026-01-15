<?php
// user_management.php Admin only page.
require_once 'db.php';
require_once 'functions.php';
check_auth();

// Restrict access to Admins only
if ($_SESSION['user_role'] !== ROLE_ADMIN) {
    header("Location: dashboard.php");
    exit;
}

// --- POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Handle Approval, Rejection, and Deletion
    if (isset($_POST['action']) && in_array($_POST['action'], ['approve_user', 'reject_user', 'delete_user'])) {
        $target_user_id = (int)$_POST['user_id'];

        // Security: Prevent admin from deleting themselves
        if ($target_user_id === $_SESSION['user_id'] && $_POST['action'] === 'delete_user') {
            set_flash_message('error', 'You cannot delete your own admin account.');
        } else {
            if ($_POST['action'] === 'approve_user') {
                // THE FIX: Capture the role from the form select field
                $selected_role = $_POST['role'] ?? ROLE_STAFF;

                $stmt = $conn->prepare("UPDATE users SET status = ?, role = ? WHERE id = ?");
                $approved_status = STATUS_APPROVED;
                $stmt->bind_param("ssi", $approved_status, $selected_role, $target_user_id);

                if ($stmt->execute()) {
                    set_flash_message('success', "User approved as " . ucfirst($selected_role) . ".");
                } else {
                    set_flash_message('error', "Failed to approve user.");
                }
            } else {
                // Rejection or Deletion
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $target_user_id);
                $stmt->execute();
                $msg = ($_POST['action'] === 'reject_user') ? 'Registration rejected.' : 'User deleted successfully.';
                set_flash_message('success', $msg);
            }
        }
        header("Location: user_management.php");
        exit;
    }
}

// --- DATA FETCHING ---
// Fetch Pending Users
$pending_res = $conn->query("SELECT id, full_name, email, created_at FROM users WHERE status = '" . STATUS_PENDING . "' ORDER BY created_at DESC");

// Fetch Approved Users
$approved_res = $conn->query("SELECT id, full_name, email, role, created_at FROM users WHERE status = '" . STATUS_APPROVED . "' ORDER BY full_name ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Management - AssetTrak</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50 flex min-h-screen">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 ml-64 p-8">
        <?php display_flash_message(); ?>

        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-800">User Management</h2>
            <p class="text-sm text-gray-500">Manage access requests and active accounts.</p>
        </div>

        <div class="mb-12">
            <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-clock text-yellow-500 mr-2"></i> Pending Requests
                <span class="ml-2 bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full"><?php echo $pending_res->num_rows; ?></span>
            </h3>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">User Info</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Requested On</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Assign Role & Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($pending_res->num_rows === 0): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-gray-400">No pending requests at this time.</td>
                            </tr>
                        <?php endif; ?>

                        <?php while ($user = $pending_res->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <form method="POST" action="user_management.php" class="flex items-center gap-3">
                                        <input type="hidden" name="action" value="approve_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">

                                        <select name="role" required class="text-sm border border-gray-300 rounded p-1.5 focus:ring-2 focus:ring-blue-500 outline-none">
                                            <option value="<?php echo ROLE_STAFF; ?>">Staff</option>
                                            <option value="<?php echo ROLE_EDITOR; ?>">Editor</option>
                                            <option value="<?php echo ROLE_ADMIN; ?>">Admin</option>
                                        </select>

                                        <button type="submit" class="bg-green-600 text-white text-xs font-bold px-3 py-1.5 rounded hover:bg-green-700">Approve</button>

                                        <button type="button" onclick="openRejectModal(<?php echo $user['id']; ?>)" class="text-red-500 hover:text-red-700 text-xs font-semibold">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-users text-blue-500 mr-2"></i> Active Accounts
            </h3>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php while ($user = $approved_res->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase 
                                    <?php echo $user['role'] === ROLE_ADMIN ? 'bg-purple-100 text-purple-700' : ($user['role'] === ROLE_EDITOR ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'); ?>">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                        <button onclick="openDeleteUserModal(<?php echo $user['id']; ?>)" class="text-gray-400 hover:text-red-600 transition">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-[10px] text-gray-400 italic">You</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="deleteUserModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-2xl p-6 w-96 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <i class="fas fa-user-times text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900">Remove User?</h3>
            <p class="text-sm text-gray-500 mt-2">This action is permanent and cannot be undone.</p>
            <div class="mt-6 flex justify-center gap-3">
                <button onclick="closeModals()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold">Cancel</button>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold">Delete User</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openDeleteUserModal(id) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteUserModal').classList.remove('hidden');
        }

        function openRejectModal(id) {
            // Reusing delete modal logic for rejection for simplicity
            document.getElementById('deleteUserId').value = id;
            const modal = document.getElementById('deleteUserModal');
            modal.querySelector('h3').innerText = "Reject Registration?";
            modal.querySelector('input[name="action"]').value = "reject_user";
            modal.querySelector('button[type="submit"]').innerText = "Confirm Rejection";
            modal.classList.remove('hidden');
        }

        function closeModals() {
            document.getElementById('deleteUserModal').classList.add('hidden');
        }
    </script>
</body>

</html>
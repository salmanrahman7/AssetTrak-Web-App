<?php
// sidebar.php - Role-based navigation and badge logic.
require_once 'db.php';

$pending_user_count = 0;
$pending_asset_count = 0;

// Badge for Admin: Pending Users
if ($_SESSION['user_role'] === ROLE_ADMIN) {
    $res = $conn->query("SELECT COUNT(*) as c FROM users WHERE status = '" . STATUS_PENDING . "'");
    $pending_user_count = $res->fetch_assoc()['c'];
}

// Badge for Editor: Pending Assets
if ($_SESSION['user_role'] === ROLE_EDITOR) {
    $res = $conn->query("SELECT COUNT(*) as c FROM assets_meta WHERE approval_status = '" . ASSET_PENDING . "'");
    $pending_asset_count = $res->fetch_assoc()['c'];
}

// Badge for Expired Licenses (Visible to Admin & Editor)
$expired_license_count = 0;
if ($_SESSION['user_role'] === ROLE_ADMIN || $_SESSION['user_role'] === ROLE_EDITOR) {
    // Count assets that are licensed AND expired AND currently approved
    $res = $conn->query("SELECT COUNT(*) as c FROM assets_meta 
                         WHERE is_licensed = 1 
                         AND license_expiry_date < CURDATE() 
                         AND approval_status = '" . ASSET_APPROVED . "'");
    $expired_license_count = $res->fetch_assoc()['c'];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="w-64 bg-slate-900 text-white flex flex-col fixed h-full shadow-xl z-10">
    <div class="p-6 border-b border-slate-700">
        <h1 class="text-2xl font-bold text-blue-400"><i class="fas fa-layer-group"></i> AssetTrak</h1>
    </div>

    <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
        <a href="dashboard.php" class="flex items-center justify-between px-4 py-3 rounded hover:bg-slate-800 transition <?php echo $current_page === 'dashboard.php' ? 'bg-blue-600' : 'text-slate-300'; ?>">
            <div class="flex items-center">
                <i class="fas fa-home w-6"></i> Dashboard
            </div>

            <?php if ($expired_license_count > 0): ?>
                <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                    <?php echo $expired_license_count; ?>
                </span>
            <?php endif; ?>
        </a>

        <?php if ($_SESSION['user_role'] === ROLE_STAFF): ?>
            <a href="upload_activity.php" class="flex items-center px-4 py-3 rounded hover:bg-slate-800 transition <?php echo $current_page === 'upload_activity.php' ? 'bg-blue-600' : 'text-slate-300'; ?>">
                <i class="fas fa-history w-6"></i> Upload Activity
            </a>
        <?php endif; ?>

        <?php if ($_SESSION['user_role'] === ROLE_EDITOR): ?>
            <a href="asset_approval.php" class="flex items-center justify-between px-4 py-3 rounded hover:bg-slate-800 transition <?php echo $current_page === 'asset_approval.php' ? 'bg-blue-600' : 'text-slate-300'; ?>">
                <div class="flex items-center"><i class="fas fa-check-double w-6"></i> Asset Approval</div>
                <?php if ($pending_asset_count > 0): ?>
                    <span class="bg-yellow-500 text-slate-900 text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo $pending_asset_count; ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <a href="my_account.php" class="flex items-center px-4 py-3 rounded hover:bg-slate-800 transition <?php echo $current_page === 'my_account.php' ? 'bg-blue-600' : 'text-slate-300'; ?>">
            <i class="fas fa-user w-6"></i> My Account
        </a>

        <?php if ($_SESSION['user_role'] === ROLE_ADMIN): ?>
            <a href="user_management.php" class="flex items-center justify-between px-4 py-3 rounded hover:bg-slate-800 transition <?php echo $current_page === 'user_management.php' ? 'bg-blue-600' : 'text-slate-300'; ?>">
                <div class="flex items-center"><i class="fas fa-users-cog w-6"></i> User Management</div>
                <?php if ($pending_user_count > 0): ?>
                    <span class="bg-yellow-500 text-slate-900 text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo $pending_user_count; ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
    </nav>

    <div class="p-4 border-t border-slate-700 bg-slate-950">
        <div class="flex items-center mb-4 text-sm">
            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center font-bold mr-3"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></div>
            <div>
                <p class="font-semibold truncate w-32"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <p class="text-[10px] text-blue-400 uppercase"><?php echo $_SESSION['user_role']; ?></p>
            </div>
        </div>
        <a href="logout.php" class="block text-center w-full py-2 bg-slate-800 hover:bg-red-600 rounded text-xs transition">Logout</a>
    </div>
</aside>
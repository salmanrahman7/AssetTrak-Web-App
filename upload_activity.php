<?php
require_once 'db.php';
require_once 'functions.php';
check_auth();

$user_id = $_SESSION['user_id'];
$sql = "SELECT am.*, 
        (SELECT CONCAT(file_path, '::', file_type, '::', original_name) FROM asset_files WHERE asset_meta_id = am.id LIMIT 1) as main_file
        FROM assets_meta am 
        WHERE am.user_id = $user_id 
        ORDER BY am.uploaded_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Upload Activity - AssetTrak</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50 flex">
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 ml-64 p-8">
        <h2 class="text-2xl font-bold mb-6 text-gray-800">My Upload Activity</h2>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Preview</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Title</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Editor Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="w-12 h-12 bg-gray-100 rounded"></div>
                            </td>
                            <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($row['title']); ?></td>
                            <td class="px-6 py-4">
                                <?php
                                $status = $row['approval_status'];
                                $color = ($status === 'approved' ? 'bg-green-100 text-green-700' : ($status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'));
                                ?>
                                <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase <?php echo $color; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500 italic">
                                <?php echo $row['rejection_reason'] ? htmlspecialchars($row['rejection_reason']) : 'No notes.'; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
<?php
// dashboard.php The main app area. Includes Sidebar, Upload Logic, and Asset Table.
require_once 'db.php';
require_once 'functions.php';
check_auth();

// Define permissions
$is_admin = ($_SESSION['user_role'] === ROLE_ADMIN);
$is_editor = ($_SESSION['user_role'] === ROLE_EDITOR);

// --- POST REQUESTS (Actions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // UPLOAD LOGIC
    if (isset($_POST['action']) && $_POST['action'] === 'upload') {
        $title = trim($_POST['title']);
        $desc = trim($_POST['description']);
        $tags = trim($_POST['tags']);
        $is_licensed = isset($_POST['is_licensed']) ? (int)$_POST['is_licensed'] : 0;
        $expiry_date = ($is_licensed === 1 && !empty($_POST['expiry_date'])) ? $_POST['expiry_date'] : null;

        // If the uploader is Admin or Editor, auto-approve the asset. Staff uploads go to pending.
        $initial_status = ($is_admin || $is_editor) ? ASSET_APPROVED : ASSET_PENDING;

        $conn->begin_transaction();
        try {
            $stmt_meta = $conn->prepare("INSERT INTO assets_meta (user_id, title, description, tags, is_licensed, license_expiry_date, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_meta->bind_param("isssiss", $_SESSION['user_id'], $title, $desc, $tags, $is_licensed, $expiry_date, $initial_status);
            $stmt_meta->execute();
            $asset_meta_id = $conn->insert_id;
            $stmt_meta->close();

            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_count = count($_FILES['asset_files']['name']);
            $uploaded_count = 0;
            $stmt_file = $conn->prepare("INSERT INTO asset_files (asset_meta_id, file_path, file_type, original_name) VALUES (?, ?, ?, ?)");

            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['asset_files']['error'][$i] === 0) {
                    $original_name = basename($_FILES['asset_files']['name'][$i]);
                    $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    $new_filename = uniqid('asset_', true) . '.' . $file_ext;
                    $target_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['asset_files']['tmp_name'][$i], $target_path)) {
                        $stmt_file->bind_param("isss", $asset_meta_id, $new_filename, $file_ext, $original_name);
                        $stmt_file->execute();
                        $uploaded_count++;
                    }
                }
            }
            $stmt_file->close();

            if ($uploaded_count > 0) {
                $conn->commit();
                $msg = "Asset entry created with $uploaded_count file(s).";
                if ($initial_status === ASSET_PENDING) $msg .= " Status: Pending Approval.";
                set_flash_message('success', $msg);
            } else {
                throw new Exception("No files were uploaded successfully.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            set_flash_message('error', 'Upload failed: ' . $e->getMessage());
        }
        header("Location: dashboard.php");
        exit;
    }

    // UPDATE EXPIRY (Admin or Editor)
    if (($is_admin || $is_editor) && isset($_POST['action']) && $_POST['action'] === 'update_expiry') {
        $asset_id = (int)$_POST['asset_id'];
        $new_date = $_POST['new_expiry_date'];
        if ($asset_id && $new_date) {
            $stmt = $conn->prepare("UPDATE assets_meta SET license_expiry_date = ? WHERE id = ? AND is_licensed = 1");
            $stmt->bind_param("si", $new_date, $asset_id);
            $stmt->execute();
            set_flash_message('success', 'Expiry date updated.');
        }
        header("Location: dashboard.php");
        exit;
    }

    // DELETE ASSET (Admin OR Editor) - THIS IS THE FIX
    if (($is_admin || $is_editor) && isset($_POST['action']) && $_POST['action'] === 'delete_asset') {
        $asset_id = (int)$_POST['asset_id'];
        $stmt = $conn->prepare("SELECT file_path FROM asset_files WHERE asset_meta_id = ?");
        $stmt->bind_param("i", $asset_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $file_full_path = $upload_dir . $row['file_path'];
            if (file_exists($file_full_path)) unlink($file_full_path);
        }
        $stmt->close();

        $del_stmt = $conn->prepare("DELETE FROM assets_meta WHERE id = ?");
        $del_stmt->bind_param("i", $asset_id);
        $del_stmt->execute();
        set_flash_message('success', 'Asset deleted.');
        header("Location: dashboard.php");
        exit;
    }
}

// --- DATA FETCHING ---
$assets_data = [];
$expired_stats = ['count' => 0, 'items' => []];
$expiring_soon_stats = ['count' => 0, 'items' => []];
$search_query = "";
// Filter to show only APPROVED assets in the main dashboard
$search_sql = " WHERE am.approval_status = '" . ASSET_APPROVED . "'";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $conn->real_escape_string(trim($_GET['search']));
    $search_sql .= " AND (am.title LIKE '%$search_query%' OR am.tags LIKE '%$search_query%')";
}

$sql = "SELECT am.*, u.full_name as uploader_name,
        GROUP_CONCAT(CONCAT(af.file_path, '::', af.file_type, '::', af.original_name) SEPARATOR '||') as all_files,
        (SELECT file_path FROM asset_files WHERE asset_meta_id = am.id LIMIT 1) as preview_path,
        (SELECT file_type FROM asset_files WHERE asset_meta_id = am.id LIMIT 1) as preview_type
        FROM assets_meta am 
        JOIN users u ON am.user_id = u.id 
        LEFT JOIN asset_files af ON am.id = af.asset_meta_id
        $search_sql
        GROUP BY am.id
        ORDER BY am.uploaded_at DESC";

$result = $conn->query($sql);
$today_dt = new DateTime();
$soon_dt = new DateTime("+" . EXPIRING_SOON_DAYS . " days");

while ($row = $result->fetch_assoc()) {
    $status_data = ['label' => 'Owned', 'color' => 'text-green-600 bg-green-100', 'icon' => 'fa-check-circle'];

    if ($row['is_licensed'] == 1 && !empty($row['license_expiry_date'])) {
        $expiry_dt = new DateTime($row['license_expiry_date']);
        $diff = $today_dt->diff($expiry_dt);
        if ($expiry_dt < $today_dt) {
            $days_ago = $diff->days;
            $status_data = ['label' => "Expired ($days_ago days ago)", 'color' => 'text-red-700 bg-red-100', 'icon' => 'fa-times-circle'];
            $expired_stats['count']++;
            $expired_stats['items'][] = $row;
        } elseif ($expiry_dt <= $soon_dt) {
            $days_left = $diff->days;
            $status_data = ['label' => "Expiring soon ($days_left days left)", 'color' => 'text-yellow-800 bg-yellow-100', 'icon' => 'fa-exclamation-triangle'];
            $expiring_soon_stats['count']++;
            $expiring_soon_stats['items'][] = $row;
        } else {
            $status_data = ['label' => 'Active Licensed', 'color' => 'text-blue-600 bg-blue-100', 'icon' => 'fa-shield-alt'];
        }
    }
    $row['status_data'] = $status_data;

    $files_arr = [];
    if (!empty($row['all_files'])) {
        $raw_files = explode('||', $row['all_files']);
        foreach ($raw_files as $rf) {
            $parts = explode('::', $rf);
            if (count($parts) === 3) $files_arr[] = ['path' => $parts[0], 'type' => $parts[1], 'name' => $parts[2]];
        }
    }
    $row['processed_files'] = $files_arr;
    $assets_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - AssetTrak</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
</head>

<body class="text-gray-800">
    <?php display_flash_message(); ?>
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 ml-64 p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <?php if ($expired_stats['count'] > 0): ?>
                    <div class="bg-red-100 border-l-4 border-red-600 p-4 rounded shadow-sm flex items-start">
                        <div class="flex-shrink-0"><i class="fas fa-times-circle text-red-600 text-2xl mt-1"></i></div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-lg font-bold text-red-800 mb-1"><?php echo $expired_stats['count']; ?> Assets Expired</h3>
                            <ul class="text-sm text-red-700 max-h-24 overflow-y-auto pl-4 list-disc">
                                <?php foreach ($expired_stats['items'] as $item): ?>
                                    <li><span class="font-semibold"><?php echo htmlspecialchars($item['title']); ?></span> (Expired on <?php echo $item['license_expiry_date']; ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($expiring_soon_stats['count'] > 0): ?>
                    <div class="bg-yellow-100 border-l-4 border-yellow-600 p-4 rounded shadow-sm flex items-start">
                        <div class="flex-shrink-0"><i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mt-1"></i></div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-lg font-bold text-yellow-800 mb-1"><?php echo $expiring_soon_stats['count']; ?> Assets Expiring Soon</h3>
                            <p class="text-sm text-yellow-800 mb-2">Within next <?php echo EXPIRING_SOON_DAYS; ?> days.</p>
                            <ul class="text-sm text-yellow-700 max-h-24 overflow-y-auto pl-4 list-disc">
                                <?php foreach ($expiring_soon_stats['items'] as $item): ?>
                                    <li><span class="font-semibold"><?php echo htmlspecialchars($item['title']); ?></span> (Expires <?php echo $item['license_expiry_date']; ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h2 class="text-xl font-bold mb-4 text-gray-700"><i class="fas fa-cloud-upload-alt mr-2"></i> Upload New Asset Entry</h2>
                <form method="POST" enctype="multipart/form-data" action="dashboard.php">
                    <input type="hidden" name="action" value="upload">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Asset Title</label>
                            <input type="text" name="title" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tags</label>
                            <input type="text" name="tags" placeholder="e.g. politics, 2025" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                        </div>
                    </div>
                    <div class="mb-4 p-4 bg-gray-50 rounded border border-gray-200">
                        <label class="block text-sm font-bold text-gray-700 mb-3">Licensing Status</label>
                        <div class="flex items-center gap-6 mb-4">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="is_licensed" value="0" class="form-radio text-blue-600 h-4 w-4" checked onclick="toggleExpiryField(false)">
                                <span class="ml-2 text-gray-700">Non-licensed Asset</span>
                            </label>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="is_licensed" value="1" class="form-radio text-blue-600 h-4 w-4" onclick="toggleExpiryField(true)">
                                <span class="ml-2 text-gray-700 font-medium">Licensed Asset</span>
                            </label>
                        </div>
                        <div id="expiry-date-container" class="hidden md:w-1/2">
                            <label class="block text-sm font-medium text-red-600 mb-1">License Expiry Date</label>
                            <input type="date" name="expiry_date" id="expiry_date_input" class="block w-full border border-red-300 rounded-md shadow-sm p-2 text-red-700">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Files</label>
                        <input type="file" name="asset_files[]" id="files" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.mkv,.mov,.avi" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition cursor-pointer" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2"></textarea>
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 font-medium transition shadow-sm flex items-center">
                        <i class="fas fa-upload mr-2"></i> Upload Assets
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center bg-gray-50">
                    <h2 class="text-xl font-bold text-gray-700 mb-2 md:mb-0">Asset Repository</h2>
                    <form method="GET" action="dashboard.php" class="flex w-full md:w-auto">
                        <div class="flex rounded-md shadow-sm w-full">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search title/tags..." class="flex-1 border border-gray-300 rounded-l-md px-4 py-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <?php if (!empty($search_query)): ?>
                                <a href="dashboard.php" class="bg-gray-200 text-gray-600 px-3 py-2 hover:bg-gray-300 border-t border-b border-gray-300 flex items-center text-sm font-medium">Clear</a>
                            <?php endif; ?>
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-r-md hover:bg-blue-700"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-xs uppercase font-bold tracking-wider">
                                <th class="p-4 border-b w-24">Preview</th>
                                <th class="p-4 border-b">Title & Tags</th>
                                <th class="p-4 border-b w-1/4">Description</th>
                                <th class="p-4 border-b">Status</th>
                                <th class="p-4 border-b">Expiry Date</th>
                                <th class="p-4 border-b">Uploaded By</th>
                                <th class="p-4 border-b text-center w-48">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-200">
                            <?php if (empty($assets_data)): ?>
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-gray-500 italic">No assets found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assets_data as $asset): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="p-4 align-top">
                                            <div class="flex flex-col items-center">
                                                <?php
                                                $file_path = $upload_dir . ($asset['preview_path'] ?? '');
                                                $ext = $asset['preview_type'] ?? 'N/A';
                                                if (!empty($asset['preview_path']) && in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                    echo '<img src="' . $file_path . '" class="h-12 w-12 object-cover rounded border shadow-sm bg-white">';
                                                } elseif ($ext === 'pdf') echo '<i class="fas fa-file-pdf text-red-500 text-4xl"></i>';
                                                elseif (in_array($ext, ['mp4', 'mkv', 'mov', 'avi'])) echo '<i class="fas fa-file-video text-purple-500 text-4xl"></i>';
                                                else echo '<i class="fas fa-file text-gray-400 text-4xl"></i>';
                                                ?>
                                                <span class="text-[10px] uppercase font-bold text-gray-500 mt-1"><?php echo strtoupper($ext); ?></span>
                                            </div>
                                        </td>
                                        <td class="p-4 align-top">
                                            <div class="font-bold text-gray-900 text-base mb-2 break-words"><?php echo htmlspecialchars($asset['title']); ?></div>
                                            <div class="flex flex-wrap gap-1">
                                                <?php if (!empty($asset['tags'])): ?>
                                                    <?php foreach (explode(',', $asset['tags']) as $tag): ?>
                                                        <span class="inline-flex items-center bg-blue-50 text-blue-700 text-xs font-medium px-2 py-0.5 rounded-full"><i class="fas fa-tag mr-1 text-[10px]"></i> <?php echo trim(htmlspecialchars($tag)); ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="p-4 align-top">
                                            <div class="text-gray-600 text-sm line-clamp-3" title="<?php echo htmlspecialchars($asset['description']); ?>">
                                                <?php echo nl2br(htmlspecialchars($asset['description'])) ?: '<span class="text-gray-400 italic">No description</span>'; ?>
                                            </div>
                                        </td>
                                        <td class="p-4 align-top whitespace-nowrap">
                                            <?php $st = $asset['status_data']; ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $st['color']; ?>"><i class="fas <?php echo $st['icon']; ?> mr-1.5"></i> <?php echo $st['label']; ?></span>
                                        </td>
                                        <td class="p-4 align-top whitespace-nowrap font-medium text-gray-700">
                                            <?php if ($asset['is_licensed'] == 1): ?>
                                                <?php echo date('M d, Y', strtotime($asset['license_expiry_date'])); ?>
                                                <?php if ($is_admin || $is_editor): ?>
                                                    <button onclick="openUpdateModal(<?php echo $asset['id']; ?>, '<?php echo $asset['license_expiry_date']; ?>')" class="ml-2 text-blue-500 hover:text-blue-700 bg-blue-50 p-1 rounded-full transition shadow-sm" title="Edit Expiry Date">
                                                        <i class="fas fa-edit text-sm"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 align-top text-gray-600 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-[10px] font-bold text-gray-600 mr-2"><?php echo strtoupper(substr($asset['uploader_name'] ?? 'U', 0, 1)); ?></div>
                                                <?php echo htmlspecialchars($asset['uploader_name'] ?? 'Unknown'); ?>
                                            </div>
                                        </td>
                                        <td class="p-4 align-top">
                                            <div class="space-y-2">
                                                <?php if (!empty($asset['processed_files'])): foreach ($asset['processed_files'] as $file): ?>
                                                        <div class="flex items-center justify-between bg-gray-50 p-1.5 rounded border border-gray-200 text-xs">
                                                            <div class="truncate w-24 mr-1 text-gray-600" title="<?php echo htmlspecialchars($file['name']); ?>"><i class="fas fa-file mr-1"></i><?php echo htmlspecialchars($file['name']); ?></div>
                                                            <div class="flex space-x-1">
                                                                <?php if (in_array($file['type'], ['mp4', 'mkv', 'mov', 'avi'])): ?>
                                                                    <button onclick="openVideoModal('<?php echo $upload_dir . $file['path']; ?>', '<?php echo htmlspecialchars($file['name']); ?>')" class="bg-blue-100 text-blue-600 p-1 rounded hover:bg-blue-200" title="Play Video">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <a href="<?php echo $upload_dir . $file['path']; ?>" target="_blank" class="bg-blue-100 text-blue-600 p-1 rounded hover:bg-blue-200"><i class="fas fa-eye"></i></a>
                                                                <?php endif; ?>
                                                                <a href="<?php echo $upload_dir . $file['path']; ?>" download="<?php echo htmlspecialchars($file['name']); ?>" class="bg-green-100 text-green-600 p-1 rounded hover:bg-green-200"><i class="fas fa-download"></i></a>
                                                            </div>
                                                        </div>
                                                <?php endforeach;
                                                endif; ?>
                                                
                                                <?php if ($is_admin || $is_editor): ?>
                                                    <button onclick="openDeleteModal(<?php echo $asset['id']; ?>)" class="w-full mt-2 text-xs bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 py-1 rounded flex items-center justify-center transition"><i class="fas fa-trash-alt mr-1"></i> Delete Entry</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="videoModal" class="fixed inset-0 bg-black bg-opacity-80 hidden z-[70] flex items-center justify-center backdrop-blur-md">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl overflow-hidden relative">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 id="videoModalTitle" class="text-lg font-bold text-gray-800 truncate pr-8">Video Preview</h3>
                <button onclick="closeVideoModal()" class="text-gray-500 hover:text-red-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="bg-black flex items-center justify-center" style="aspect-ratio: 16/9;">
                <video id="mainVideoPlayer" controls class="max-h-full max-w-full">
                    Your browser does not support the video tag.
                </video>
            </div>
            <div class="p-4 bg-gray-50 text-right">
                <button onclick="closeVideoModal()" class="px-6 py-2 bg-gray-800 text-white rounded hover:bg-gray-700 transition font-medium">Close Player</button>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[60] flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-2xl p-6 w-96 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4"><i class="fas fa-trash-alt text-red-600 text-xl"></i></div>
            <h3 class="text-lg font-medium text-gray-900">Are you sure?</h3>
            <p class="text-sm text-gray-500 mt-2">Delete this asset entry and all files?</p>
            <div class="mt-6 flex justify-center gap-3">
                <button onclick="document.getElementById('deleteModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded">Cancel</button>
                <form method="POST" action="dashboard.php">
                    <input type="hidden" name="action" value="delete_asset">
                    <input type="hidden" name="asset_id" id="deleteAssetId">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>

    <div id="updateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[60] flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-2xl p-6 w-96">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Update Expiry Date</h3>
                <button onclick="document.getElementById('updateModal').classList.add('hidden')" class="text-gray-400"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="dashboard.php">
                <input type="hidden" name="action" value="update_expiry">
                <input type="hidden" name="asset_id" id="updateAssetId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Expiry Date</label>
                    <input type="date" name="new_expiry_date" id="updateDateInput" class="w-full border border-gray-300 rounded p-2" required>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('updateModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Video Modal Controls
        function openVideoModal(src, filename) {
            const modal = document.getElementById('videoModal');
            const video = document.getElementById('mainVideoPlayer');
            const title = document.getElementById('videoModalTitle');
            
            title.textContent = filename;
            video.src = src;
            modal.classList.remove('hidden');
            video.play();
        }

        function closeVideoModal() {
            const modal = document.getElementById('videoModal');
            const video = document.getElementById('mainVideoPlayer');
            
            video.pause();
            video.src = ""; // Clear src to stop loading/buffering
            modal.classList.add('hidden');
        }

        function openDeleteModal(id) {
            document.getElementById('deleteAssetId').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function openUpdateModal(id, date) {
            document.getElementById('updateAssetId').value = id;
            document.getElementById('updateDateInput').value = date;
            document.getElementById('updateModal').classList.remove('hidden');
        }

        function toggleExpiryField(isLicensed) {
            const container = document.getElementById('expiry-date-container');
            const input = document.getElementById('expiry_date_input');
            if (isLicensed) {
                container.classList.remove('hidden');
                input.setAttribute('required', 'required');
            } else {
                container.classList.add('hidden');
                input.removeAttribute('required');
                input.value = '';
            }
        }
    </script>
</body>

</html>
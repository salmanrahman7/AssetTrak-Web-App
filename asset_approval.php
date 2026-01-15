<?php
require_once 'db.php';
require_once 'functions.php';
check_auth();

// Only allow Editor and Admin
if ($_SESSION['user_role'] !== ROLE_EDITOR && $_SESSION['user_role'] !== ROLE_ADMIN) {
    header("Location: dashboard.php");
    exit;
}

// Handle Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_approval') {
    $asset_id = (int)$_POST['asset_id'];
    $status = $_POST['status']; // 'approved' or 'rejected'
    $reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';

    $stmt = $conn->prepare("UPDATE assets_meta SET approval_status = ?, rejection_reason = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $reason, $asset_id);

    if ($stmt->execute()) {
        set_flash_message('success', 'Asset status updated successfully.');
    } else {
        set_flash_message('error', 'Database error: ' . $conn->error);
    }
    header("Location: asset_approval.php");
    exit;
}

// --- QUERY: Fetch assets, file counts, and file details grouped together ---
$sql = "SELECT 
            am.*, 
            u.full_name as uploader_name, 
            GROUP_CONCAT(CONCAT(af.file_path, ':::', af.file_type, ':::', af.original_name) SEPARATOR '|||') as batch_files_data,
            COUNT(af.id) as file_count
        FROM assets_meta am
        LEFT JOIN users u ON am.user_id = u.id
        LEFT JOIN asset_files af ON am.id = af.asset_meta_id
        WHERE am.approval_status = '" . ASSET_PENDING . "'
        GROUP BY am.id
        ORDER BY am.uploaded_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Asset Approval - AssetTrak</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50 text-gray-800 font-sans antialiased flex">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 ml-64 p-8">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-3xl font-bold text-gray-800">Pending Approvals</h2>
                <p class="text-gray-500 mt-1">Review and manage asset requests</p>
            </div>
            <?php display_flash_message(); ?>
        </header>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 border-b border-gray-200 text-xs uppercase">
                        <th class="px-6 py-4 font-semibold tracking-wider">Asset Details</th>
                        <th class="px-6 py-4 font-semibold tracking-wider">Uploaded By</th>
                        <th class="px-6 py-4 font-semibold tracking-wider">Date</th>
                        <th class="px-6 py-4 font-semibold tracking-wider w-80">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 align-top">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-blue-100 rounded text-blue-600">
                                            <i class="fas fa-file-archive text-xl"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-800 text-base mb-1"><?php echo htmlspecialchars($row['title']); ?></p>
                                            <p class="text-gray-500 text-xs mb-2 line-clamp-2"><?php echo htmlspecialchars($row['description']); ?></p>

                                            <div class="flex flex-wrap gap-2 items-center">
                                                <span class="bg-blue-100 text-blue-700 text-[10px] px-2 py-0.5 rounded border border-blue-200 font-semibold">
                                                    <?php echo $row['file_count']; ?> Files
                                                </span>

                                                <?php if ($row['is_licensed'] == 1): ?>
                                                    <span class="bg-orange-100 text-orange-700 text-[10px] px-2 py-0.5 rounded border border-orange-200 font-bold flex items-center gap-1">
                                                        <i class="far fa-calendar-times"></i> Expires: <?php echo htmlspecialchars($row['license_expiry_date']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-green-100 text-green-700 text-[10px] px-2 py-0.5 rounded border border-green-200 font-bold flex items-center gap-1">
                                                        <i class="fas fa-check-circle"></i> Owned
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($row['tags'])): ?>
                                                    <?php foreach (explode(',', $row['tags']) as $tag): ?>
                                                        <span class="bg-gray-200 text-gray-600 text-[10px] px-2 py-0.5 rounded border border-gray-300">#<?php echo htmlspecialchars(trim($tag)); ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-gray-700 align-top">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs font-bold text-gray-600">
                                            <?php echo strtoupper(substr($row['uploader_name'], 0, 1)); ?>
                                        </div>
                                        <?php echo htmlspecialchars($row['uploader_name']); ?>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-gray-500 text-xs align-top whitespace-nowrap">
                                    <i class="far fa-clock mr-1"></i>
                                    <?php echo date('M j, Y', strtotime($row['uploaded_at'])); ?><br>
                                    <span class="ml-4"><?php echo date('g:i A', strtotime($row['uploaded_at'])); ?></span>
                                </td>

                                <td class="px-6 py-4 align-top">
                                    <div class="flex flex-col gap-3">
                                        <button type="button"
                                            onclick="openPreviewModal(this)"
                                            data-files="<?php echo htmlspecialchars($row['batch_files_data']); ?>"
                                            class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-2 rounded transition flex items-center justify-center gap-2 shadow-sm">
                                            <i class="fas fa-eye"></i> View Files (<?php echo $row['file_count']; ?>)
                                        </button>

                                        <form method="POST" class="bg-gray-50 p-3 rounded border border-gray-200">
                                            <input type="hidden" name="action" value="process_approval">
                                            <input type="hidden" name="asset_id" value="<?php echo $row['id']; ?>">

                                            <select name="status" onchange="toggleReasonBox(this)" class="w-full bg-white text-gray-700 text-xs border border-gray-300 rounded p-2 mb-2 focus:ring-1 focus:ring-blue-500 outline-none">
                                                <option value="" disabled selected>Select Action...</option>
                                                <option value="<?php echo ASSET_APPROVED; ?>">Approve Asset</option>
                                                <option value="<?php echo ASSET_REJECTED; ?>">Reject Asset</option>
                                            </select>

                                            <div class="hidden reason-box mb-2">
                                                <textarea name="rejection_reason" rows="2" placeholder="Write feedback for staff..." class="w-full text-xs border border-red-200 bg-red-50 text-red-800 rounded p-2 focus:outline-none focus:border-red-400 placeholder-red-300"></textarea>
                                            </div>

                                            <button type="submit" class="w-full bg-gray-800 text-white text-xs font-bold py-2 rounded hover:bg-gray-700 transition">
                                                Confirm Status
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-clipboard-check text-4xl mb-3 text-gray-300"></i>
                                    <p>No pending approvals found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="previewModal" class="fixed inset-0 bg-black/90 hidden z-50 overflow-y-auto backdrop-blur-sm">
        <div class="min-h-screen px-4 flex items-center justify-center">
            <div class="bg-slate-900 rounded-xl shadow-2xl w-full max-w-5xl my-8 border border-slate-700 relative">
                <button onclick="closePreviewModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition bg-slate-800 w-8 h-8 rounded-full flex items-center justify-center z-10">
                    <i class="fas fa-times"></i>
                </button>

                <div class="p-6 border-b border-slate-700">
                    <h3 class="text-xl font-bold text-white flex items-center gap-2">
                        <i class="fas fa-folder-open text-blue-500"></i> Batch Content Preview
                    </h3>
                </div>

                <div id="previewContent" class="p-6 space-y-8 max-h-[75vh] overflow-y-auto custom-scrollbar text-white">
                </div>

                <div class="bg-slate-950 px-6 py-4 flex justify-end rounded-b-xl border-t border-slate-800">
                    <button onclick="closePreviewModal()" class="bg-slate-800 text-white px-6 py-2 rounded hover:bg-slate-700 transition text-sm font-semibold border border-slate-700">
                        Close Preview
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle Rejection Reason Box
        function toggleReasonBox(select) {
            const form = select.closest('form');
            const box = form.querySelector('.reason-box');
            const textarea = box.querySelector('textarea');

            if (select.value === '<?php echo ASSET_REJECTED; ?>') {
                box.classList.remove('hidden');
                textarea.setAttribute('required', 'required');
            } else {
                box.classList.add('hidden');
                textarea.removeAttribute('required');
                textarea.value = '';
            }
        }

        // Preview Modal Functions
        function openPreviewModal(btn) {
            const rawData = btn.getAttribute('data-files');
            const container = document.getElementById('previewContent');
            const modal = document.getElementById('previewModal');

            container.innerHTML = ''; // Clear previous content

            if (!rawData) {
                container.innerHTML = '<p class="text-slate-400 text-center py-10">No files attached to this asset.</p>';
                modal.classList.remove('hidden');
                return;
            }

            // Data format: path:::type:::name ||| path:::type:::name
            const files = rawData.split('|||');

            files.forEach(fileStr => {
                const parts = fileStr.split(':::');
                if (parts.length < 3) return;

                const path = "uploads/" + parts[0];
                const type = parts[1]; // extension or mime type
                const name = parts[2];

                // Create Wrapper
                const wrapper = document.createElement('div');
                wrapper.className = "bg-slate-800/50 p-6 rounded-lg border border-slate-700";

                const header = document.createElement('div');
                header.className = "flex items-center gap-3 mb-4 border-b border-slate-700/50 pb-3";
                header.innerHTML = `<i class="fas fa-file text-blue-400"></i> <h4 class="text-white font-semibold text-sm">${name}</h4>`;
                wrapper.appendChild(header);

                let content = '';
                const ext = type.toLowerCase();

                // Video
                if (['mp4', 'mkv', 'mov', 'avi', 'webm'].includes(ext) || type.includes('video')) {
                    content = `
                        <div class="relative rounded-lg overflow-hidden bg-black shadow-lg">
                            <video controls class="w-full max-h-[500px]">
                                <source src="${path}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>`;
                }
                // PDF
                else if (ext === 'pdf' || type.includes('pdf')) {
                    content = `<iframe src="${path}" class="w-full h-[600px] rounded border border-slate-600 bg-white"></iframe>`;
                }
                // Images
                else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext) || type.includes('image')) {
                    content = `<img src="${path}" class="max-w-full h-auto max-h-[600px] rounded mx-auto shadow-lg" alt="${name}">`;
                }
                // Others
                else {
                    content = `
                        <div class="text-center py-8 bg-slate-900 rounded border border-dashed border-slate-700">
                            <i class="fas fa-download text-4xl text-slate-600 mb-3"></i>
                            <p class="text-slate-400 text-sm mb-4">Preview not available for this file type.</p>
                            <a href="${path}" target="_blank" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded text-sm transition inline-flex items-center gap-2">
                                <i class="fas fa-download"></i> Download File
                            </a>
                        </div>`;
                }

                const contentDiv = document.createElement('div');
                contentDiv.innerHTML = content;
                wrapper.appendChild(contentDiv);
                container.appendChild(wrapper);
            });

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closePreviewModal() {
            const modal = document.getElementById('previewModal');
            const container = document.getElementById('previewContent');

            modal.classList.add('hidden');
            container.innerHTML = '';
            document.body.style.overflow = 'auto';
        }

        // Close modal on click outside
        document.getElementById('previewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreviewModal();
            }
        });
    </script>
</body>

</html>
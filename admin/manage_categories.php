<?php
require_once '../config.php';
 
if (!isAdminLoggedIn()) {
    redirect('login.php');
}
 
$conn = getConnection();
 
//  Handle Add Category 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name        = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $icon        = sanitize($_POST['icon']);
 
    $stmt = $conn->prepare("INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $description, $icon);
    if ($stmt->execute()) {
        setSuccessMessage("Category added successfully!");
    } else {
        setErrorMessage("Failed to add category!");
    }
    $stmt->close();
    redirect('manage_categories.php');
}
 
//  Handle Edit Category 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id          = intval($_POST['id']);
    $name        = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $icon        = sanitize($_POST['icon']);
 
    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, icon = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $description, $icon, $id);
    if ($stmt->execute()) {
        setSuccessMessage("Category updated successfully!");
    } else {
        setErrorMessage("Failed to update category!");
    }
    $stmt->close();
    redirect('manage_categories.php');
}
 
//  Handle Delete Category 
if (isset($_GET['delete'])) {
    $id    = intval($_GET['delete']);
    $check = $conn->query("SELECT COUNT(*) as count FROM packages WHERE category_id = $id");
    $result = $check->fetch_assoc();
    if ($result['count'] > 0) {
        setErrorMessage("Cannot delete category! It has " . $result['count'] . " packages associated with it.");
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            setSuccessMessage("Category deleted successfully!");
        } else {
            setErrorMessage("Failed to delete category!");
        }
        $stmt->close();
    }
    redirect('manage_categories.php');
}
 
//  Handle Add Tag 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_tag') {
    $tag = trim($conn->real_escape_string($_POST['tag_name']));
    if ($tag) {
        $conn->query("INSERT IGNORE INTO tags (tag_name) VALUES ('$tag')");
        setSuccessMessage("Tag '$tag' added successfully!");
    }
    redirect('manage_categories.php');
}
 
//  Handle Delete Tag 
if (isset($_GET['delete_tag'])) {
    $tid = intval($_GET['delete_tag']);
    $conn->query("DELETE FROM tags WHERE id = $tid");
    setSuccessMessage("Tag deleted successfully!");
    redirect('manage_categories.php');
}
                                           
//  Fetch Data 
$categories = $conn->query("
    SELECT c.*, COUNT(p.id) as package_count
    FROM categories c
    LEFT JOIN packages p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.name
");
 
$tags = $conn->query("SELECT * FROM tags ORDER BY tag_name")->fetch_all(MYSQLI_ASSOC);
 
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - TMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <style>
        /*  MODALS  */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover { color: #000; }
 
        /*  ICON GRID  */
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-top: 15px;
            max-height: 300px;
            overflow-y: auto;
        }
        .icon-option {
            padding: 15px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .icon-option:hover { border-color: #667eea; background: #f0f4ff; }
        .icon-option.selected { border-color: #667eea; background: #667eea; color: white; }
        .icon-option i { font-size: 24px; }
 
        /*  TAGS SECTION  */
        .tags-section {
            margin-top: 35px;
        }
        .tags-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .tags-section-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tags-section-header h2 i { color: #667eea; }
 
        .tags-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 20px;
            align-items: start;
        }
 
        /* Add Tag Card */
        .add-tag-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .add-tag-card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 20px;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .add-tag-card-body { padding: 20px; }
        .add-tag-card-body label {
            font-weight: 600;
            font-size: 14px;
            color: #444;
            display: block;
            margin-bottom: 8px;
        }
        .add-tag-card-body input[type="text"] {
            width: 100%;
            border: 1px solid #dde0e8;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border 0.2s;
        }
        .add-tag-card-body input[type="text"]:focus { border-color: #667eea; }
        .add-tag-hint { font-size: 12px; color: #999; margin-top: 5px; }
        .btn-add-tag {
            width: 100%;
            margin-top: 14px;
            padding: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-add-tag:hover { opacity: 0.9; }
 
        /* Tags Table Card */
        .tags-table-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .tags-table-header {
            background: #2d3748;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 15px;
            font-weight: 600;
        }
        .tags-table-header .left { display: flex; align-items: center; gap: 8px; }
        .tags-count { background: white; color: #2d3748; font-weight: 700; border-radius: 20px; padding: 2px 12px; font-size: 13px; }
        .tags-tbl { width: 100%; border-collapse: collapse; }
        .tags-tbl thead th { background: #3d4f63; color: #c8d6e5; padding: 11px 16px; font-size: 13px; font-weight: 600; text-align: left; border: none; }
        .tags-tbl tbody tr { border-bottom: 1px solid #f0f2f5; }
        .tags-tbl tbody tr:hover { background: #f8f9ff; }
        .tags-tbl tbody td { padding: 11px 16px; font-size: 14px; color: #333; border: none; vertical-align: middle; }
        .tag-pill {
            background: #4a5568;
            color: white;
            border-radius: 20px;
            padding: 4px 14px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-tag-delete {
            background: #e53e3e;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 5px 12px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-tag-delete:hover { background: #c53030; color: white; }
 
        /* Responsive */
        @media (max-width: 768px) {
            .tags-layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
 
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
 
        <div class="content">
 
            <!-- SECTION 1: CATEGORIES -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h1><i class="fas fa-list"></i> Manage Categories</h1>
                <button onclick="openAddModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
            </div>
 
            <?php
            $messages = getMessages();
            if (isset($messages['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $messages['success'] ?>
                </div>
            <?php endif; ?>
            <?php if (isset($messages['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $messages['error'] ?>
                </div>
            <?php endif; ?>
 
            <!-- Categories Table -->
            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Icon</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Packages</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($categories->num_rows > 0): ?>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= $cat['id'] ?></strong></td>
                                <td>
                                    <i class="fas <?= $cat['icon'] ?>" style="font-size:32px; color:#667eea;"></i>
                                </td>
                                <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                <td><?= htmlspecialchars($cat['description']) ?></td>
                                <td>
                                    <span class="badge" style="background:#667eea; color:white; padding:5px 12px;">
                                        <?= $cat['package_count'] ?> packages
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($cat['created_at'])) ?></td>
                                <td class="action-buttons">
                                    <button onclick="openEditModal(
                                        <?= $cat['id'] ?>,
                                        '<?= htmlspecialchars(addslashes($cat['name'])) ?>',
                                        '<?= htmlspecialchars(addslashes($cat['description'])) ?>',
                                        '<?= $cat['icon'] ?>'
                                    )" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?= $cat['id'] ?>"
                                       onclick="return confirm('Are you sure? This will fail if packages are using this category.')"
                                       class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:50px; color:#999;">
                                    <i class="fas fa-folder-open" style="font-size:48px; display:block; margin-bottom:15px;"></i>
                                    <strong>No categories found</strong>
                                    <p style="margin-top:10px;">Click "Add New Category" to create your first category</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
 
            <!-- SECTION 2: DESTINATION / ACTIVITY TAGS  -->
            <div class="tags-section">
                <!-- <div class="tags-section-header">
                    <h2><i class="fas fa-tags"></i> Destination / Activity Tags</h2>
                    <small style="color:#999; font-size:13px;">
                        Tags are used by the Cosine Similarity recommendation engine
                    </small>
                </div> -->
 
                <div class="tags-layout">
 
                    <!-- Add Tag Form -->
                    <div class="add-tag-card">
                        <div class="add-tag-card-header">
                            <i class="fas fa-plus-circle"></i> Add New Tag
                        </div>
                        <div class="add-tag-card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_tag">
                                <label>Tag Name</label>
                                <input type="text" name="tag_name"
                                       placeholder="e.g. Beach, Adventure, Trekking..."
                                       required>
                                <!-- <div class="add-tag-hint">
                                    Tags help match tourist preferences with travel packages.
                                </div> -->
                                <button type="submit" class="btn-add-tag">
                                    <i class="fas fa-plus"></i> Add Tag
                                </button>
                            </form>
                        </div>
                    </div>
 
                    <!-- Tags Table -->
                    <div class="tags-table-card">
                        <div class="tags-table-header">
                            <span class="left">
                                <i class="fas fa-list-ul"></i> All Destination / Activity Tags
                            </span>
                            <span class="tags-count"><?= count($tags) ?> Tags</span>
                        </div>
                        <table class="tags-tbl">
                            <thead>
                                <tr>
                                    <th width="55">#</th>
                                    <th>Tag Name</th>
                                    <th style="text-align:right; width:120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tags)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; padding:40px; color:#999;">
                                            <i class="fas fa-tag" style="font-size:30px; display:block; margin-bottom:10px;"></i>
                                            No tags yet. Add your first tag!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tags as $i => $tag): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td>
                                                <span class="tag-pill">
                                                    <i class="fas fa-tag"></i>
                                                    <?= htmlspecialchars($tag['tag_name']) ?>
                                                </span>
                                            </td>
                                            <td style="text-align:right;">
                                                <a href="?delete_tag=<?= $tag['id'] ?>"
                                                   onclick="return confirm('Delete tag: <?= htmlspecialchars($tag['tag_name']) ?>?')"
                                                   class="btn-tag-delete">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
 
                </div><!-- end tags-layout -->
            </div><!-- end tags-section -->
 
        </div><!-- end content -->
    </div><!-- end main-content -->
 
    <!-- Add Category Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>Add New Category</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Icon *</label>
                    <input type="text" id="add_icon" name="icon" class="form-control" value="fa-box" required readonly>
                    <small>Click an icon below to select</small>
                    <div class="icon-grid">
                        <div class="icon-option selected" onclick="selectIcon(this, 'fa-box', 'add')"><i class="fas fa-box"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-mountain', 'add')"><i class="fas fa-mountain"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-umbrella-beach', 'add')"><i class="fas fa-umbrella-beach"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-landmark', 'add')"><i class="fas fa-landmark"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-spa', 'add')"><i class="fas fa-spa"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-paw', 'add')"><i class="fas fa-paw"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-heart', 'add')"><i class="fas fa-heart"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-people-roof', 'add')"><i class="fas fa-people-roof"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-plane', 'add')"><i class="fas fa-plane"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-hiking', 'add')"><i class="fas fa-hiking"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-camera', 'add')"><i class="fas fa-camera"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-map', 'add')"><i class="fas fa-map"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-compass', 'add')"><i class="fas fa-compass"></i></div>
                    </div>
                </div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Category
                    </button>
                    <button type="button" onclick="closeAddModal()" class="btn" style="background:#666; color:white;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
 
    <!-- Edit Category Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Category</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="id">
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Icon *</label>
                    <input type="text" id="edit_icon" name="icon" class="form-control" required readonly>
                    <small>Click an icon below to select</small>
                    <div class="icon-grid" id="edit_icon_grid">
                        <div class="icon-option" onclick="selectIcon(this, 'fa-box', 'edit')"><i class="fas fa-box"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-mountain', 'edit')"><i class="fas fa-mountain"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-umbrella-beach', 'edit')"><i class="fas fa-umbrella-beach"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-landmark', 'edit')"><i class="fas fa-landmark"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-spa', 'edit')"><i class="fas fa-spa"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-paw', 'edit')"><i class="fas fa-paw"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-heart', 'edit')"><i class="fas fa-heart"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-plane', 'edit')"><i class="fas fa-plane"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-hiking', 'edit')"><i class="fas fa-hiking"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-camera', 'edit')"><i class="fas fa-camera"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-map', 'edit')"><i class="fas fa-map"></i></div>
                        <div class="icon-option" onclick="selectIcon(this, 'fa-compass', 'edit')"><i class="fas fa-compass"></i></div>
                    </div>
                </div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Category
                    </button>
                    <button type="button" onclick="closeEditModal()" class="btn" style="background:#666; color:white;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
 
    <script>
        // Category Modals 
        function openAddModal()  { document.getElementById('addModal').style.display  = 'block'; }
        function closeAddModal() { document.getElementById('addModal').style.display  = 'none';  }
        function openEditModal(id, name, description, icon) {
            document.getElementById('edit_id').value          = id;
            document.getElementById('edit_name').value        = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_icon').value        = icon;
 
            // Highlight current icon
            document.querySelectorAll('#edit_icon_grid .icon-option').forEach(function(el) {
                el.classList.remove('selected');
                if (el.getAttribute('onclick') && el.getAttribute('onclick').includes(icon)) {
                    el.classList.add('selected');
                }
            });
            document.getElementById('editModal').style.display = 'block';
        }
        function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
 
        // Icon Selection 
        function selectIcon(element, iconClass, type) {
            var modal = type === 'add'
                ? document.getElementById('addModal')
                : document.getElementById('editModal');
            modal.querySelectorAll('.icon-option').forEach(function(el) {
                el.classList.remove('selected');
            });
            element.classList.add('selected');
            document.getElementById(type + '_icon').value = iconClass;
        }
 
        // Close modal on outside click 
        window.onclick = function(event) {
            var addModal  = document.getElementById('addModal');
            var editModal = document.getElementById('editModal');
            if (event.target == addModal)  closeAddModal();
            if (event.target == editModal) closeEditModal();
        }
    </script>
</body>
</html>
 
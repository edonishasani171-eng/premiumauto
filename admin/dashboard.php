<?php
require_once 'auth_guard.php';

$success = '';
$error   = '';
if (!empty($_SESSION['car_success'])) {
    $success = $_SESSION['car_success'];
    unset($_SESSION['car_success']);
}
$show_delete_toast = false;
if (!empty($_SESSION['delete_success'])) {
    unset($_SESSION['delete_success']);
    // handled by toast in JS
    $show_delete_toast = true;
}
$show_sold_toast = false;
if (!empty($_SESSION['sold_success'])) {
    unset($_SESSION['sold_success']);
    $show_sold_toast = true;
}
// ── Handle DELETE ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $del_id = (int)$_POST['delete_id'];
    $stmt   = $conn->prepare("SELECT image_path FROM cars WHERE id = ?");
    $stmt->bind_param('i', $del_id);
    $stmt->execute();
    $img = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($img) {
        $del_stmt = $conn->prepare("DELETE FROM cars WHERE id = ?");
        $del_stmt->bind_param('i', $del_id);
        if ($del_stmt->execute()) {
            $img_file = '../' . $img['image_path'];
            if (file_exists($img_file)) unlink($img_file);
            $_SESSION['delete_success'] = true;
        } else {
            $error = 'Failed to delete car.';
        }
        $del_stmt->close();
    }
    header('Location: dashboard.php?section=manage');
    exit;
}

// ── Handle ADD ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $make  = trim($_POST['make']  ?? '');
    $model = trim($_POST['model'] ?? '');
    $year  = (int)($_POST['year'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $mileage = trim($_POST['mileage'] ?? '');
    $accidents = isset($_POST['accidents']) ? (int)$_POST['accidents'] : 0;
    $fuel = trim($_POST['fuel'] ?? '');
    $transmission = trim($_POST['transmission'] ?? '');
    $parts_arr = $_POST['parts_changed'] ?? [];
    $parts_changed = implode(', ', $parts_arr);
    $options_arr = $_POST['car_options'] ?? [];
    $car_options = implode(', ', $options_arr);

    if (!$make || !$model || !$year || !$price) {
        $error = 'Please fill in all required fields.';
    } elseif (empty($_FILES['images']['name'][0])) {
    $error = 'Please upload at least one car image.';
} else {
    $allowed    = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $upload_dir = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $uploaded_paths = [];
    $files = $_FILES['images'];
    $total = count($files['name']);

    for ($i = 0; $i < min($total, 10); $i++) {
        if ($files['error'][$i] !== 0) continue;
        $file_type = mime_content_type($files['tmp_name'][$i]);
        if (!in_array($file_type, $allowed)) continue;
        if ($files['size'][$i] > 5 * 1024 * 1024) continue;

        $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $filename = uniqid('car_', true) . '.' . strtolower($ext);
        $dest     = $upload_dir . $filename;

        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $uploaded_paths[] = 'admin/uploads/' . $filename;
        }
    }

    if (empty($uploaded_paths)) {
        $error = 'Failed to upload images. Check folder permissions.';
    } else {
        // Use first image as main image_path for backwards compatibility
        $image_path = $uploaded_paths[0];
        $engine = $_POST['engine'] ?? '';
        $hp = (int)($_POST['hp'] ?? 0);

        $stmt = $conn->prepare(
            "INSERT INTO cars (make, model, year, price, fuel, transmission, engine, hp, description, image_path, mileage, accidents, parts_changed, car_options) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            'ssidsssissssss',
            $make,
            $model,
            $year,
            $price,
            $fuel,
            $transmission,
            $engine,
            $hp,
            $desc,
            $image_path,
            $mileage,
            $accidents,
            $parts_changed,
            $car_options
        );
        if ($stmt->execute()) {
            $car_id = $conn->insert_id;

            // Save all images to car_images table
            $img_stmt = $conn->prepare("INSERT INTO car_images (car_id, image_path, is_primary, sort_order) VALUES (?,?,?,?)");
            foreach ($uploaded_paths as $idx => $path) {
                $is_primary = ($idx === 0) ? 1 : 0;
                $img_stmt->bind_param('isii', $car_id, $path, $is_primary, $idx);
                $img_stmt->execute();
            }
            $img_stmt->close();

            $_SESSION['car_success'] = "$year $make $model";
            header('Location: dashboard.php?section=add-car');
            exit;
        } else {
            $error = 'Database error: ' . $conn->error;
        }
        $stmt->close();
    }
}
}

// ── Stats ────────────────────────────────────────────────────────
$total_cars  = $conn->query("SELECT COUNT(*) as c FROM cars")->fetch_assoc()['c'];
$total_value = $conn->query("SELECT SUM(price) as s FROM cars")->fetch_assoc()['s'] ?? 0;
$newest      = $conn->query("SELECT make, model, year FROM cars ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$avg_price   = $total_cars > 0 ? $total_value / $total_cars : 0;

// ── Cars list ────────────────────────────────────────────────────
$search     = trim($_GET['s'] ?? '');
$page       = max(1, (int)($_GET['p'] ?? 1));
$per_page   = 8;
$offset     = ($page - 1) * $per_page;

if ($search) {
    $like = "%$search%";
    $count_q = $conn->prepare("SELECT COUNT(*) as c FROM cars WHERE make LIKE ? OR model LIKE ? OR year LIKE ?");
    $count_q->bind_param('sss', $like, $like, $like);
    $count_q->execute();
    $total_filtered = $count_q->get_result()->fetch_assoc()['c'];
    $count_q->close();

    $cars_q = $conn->prepare("SELECT * FROM cars WHERE make LIKE ? OR model LIKE ? OR year LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $cars_q->bind_param('sssii', $like, $like, $like, $per_page, $offset);
} else {
    $total_filtered = $total_cars;
    $cars_q = $conn->prepare("SELECT * FROM cars ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $cars_q->bind_param('ii', $per_page, $offset);
}
$cars_q->execute();
$cars_result = $cars_q->get_result();
$cars_q->close();

$total_pages = ceil($total_filtered / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Premium Auto</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ── Layout ───────────────────────────── */
        body { background: #0a0a0a; }

        .dash-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* ── Sidebar ──────────────────────────── */
        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: #0e0e0e;
            border-right: 1px solid #222;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 200;
        }

        .sidebar-logo {
            padding: 1.8rem 1.5rem 1.2rem;
            border-bottom: 1px solid #1e1e1e;
        }

        .sidebar-logo span {
            font-family: 'Syne', sans-serif !important;
            color: var(--gold);
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .sidebar-logo small {
            display: block;
            color: #555;
            font-size: 0.7rem;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1.5rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.75rem 1.5rem;
            color: #888;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }

        .nav-item:hover, .nav-item.active {
            color: var(--gold);
            background: rgba(212,175,55,0.06);
        }

        .nav-item.active {
            border-left: 3px solid var(--gold);
        }

        .nav-section {
            padding: 1rem 1.5rem 0.4rem;
            font-size: 0.65rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #444;
        }

        .sidebar-footer {
            padding: 1.2rem 1.5rem;
            border-top: 1px solid #1e1e1e;
        }

        .admin-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .admin-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: rgba(212,175,55,0.15);
            border: 1px solid var(--gold);
            display: flex; align-items: center; justify-content: center;
            color: var(--gold);
            font-weight: 700;
            font-size: 0.85rem;
        }

        .admin-name { font-size: 0.85rem; color: #ccc; }
        .admin-role { font-size: 0.7rem; color: #555; }

        .btn-logout {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 0.6rem 0.8rem;
            background: rgba(231,76,60,0.1);
            border: 1px solid rgba(231,76,60,0.3);
            color: #e74c3c;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-logout:hover { background: rgba(231,76,60,0.2); }
        .btn-logout svg   { width: 16px; height: 16px; }

        /* ── Main content ─────────────────────── */
        .main-content {
            margin-left: 240px;
            flex: 1;
            padding: 2rem 2.5rem;
            max-width: calc(100vw - 240px);
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        /* Update this block to target both the title and its span */
        .page-title, 
        .page-title span {
            font-family: 'Syne', sans-serif !important;
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text-main);
            line-height: 1.2 !important;      /* Gives vertical space for descenders */
            padding-bottom: 6px;
        }

        /* Keep this below it just to handle the gold color swap */
        .page-title span { 
            color: var(--gold); 
        }

        .topbar-time {
            font-size: 0.8rem;
            color: #555;
        }

        /* ── Stats cards ──────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: #111;
            border: 1px solid #1e1e1e;
            border-radius: 10px;
            padding: 1.4rem 1.5rem;
            transition: border-color 0.3s;
        }

        .stat-card:hover { border-color: #333; }

        .stat-icon {
            width: 38px; height: 38px;
            border-radius: 8px;
            background: rgba(212,175,55,0.1);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1rem;
        }

        .stat-icon svg { width: 20px; height: 20px; stroke: var(--gold); }

        .stat-label {
            font-size: 0.75rem;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.4rem;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
        }

        .stat-sub {
            font-size: 0.75rem;
            color: #555;
            margin-top: 0.4rem;
        }

        .sold-row td {
            opacity: 0.6;
        }

        .sold-row .car-thumb {
            filter: grayscale(50%);
        }

        /* ── Section panels ───────────────────── */
        .panel {
            background: #111;
            border: 1px solid #1e1e1e;
            border-radius: 10px;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #1a1a1a;
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-title svg { width: 18px; height: 18px; stroke: var(--gold); }

        .panel-body { padding: 1.5rem; }

        /* ── Add car form ─────────────────────── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.2rem;
        }

        .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .form-group.full { grid-column: 1 / -1; }

        .form-group label {
            font-size: 0.75rem;
            color: var(--gold);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .form-group input,
        .form-group textarea {
            padding: 0.75rem 1rem;
            background: #0a0a0a;
            border: 1px solid #2a2a2a;
            border-radius: 6px;
            color: var(--text-main);
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212,175,55,0.08);
        }

        .form-group textarea { resize: vertical; min-height: 90px; }

        .image-upload-area {
            border: 2px dashed #2a2a2a;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
        }

        .image-upload-area:hover {
            border-color: var(--gold);
            background: rgba(212,175,55,0.02);
        }

        .image-upload-area.has-file { border-color: var(--gold); }

        .image-upload-area input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer;
            width: 100%; height: 100%;
        }

        .upload-icon svg { width: 36px; height: 36px; stroke: #444; margin-bottom: 0.5rem; }
        .upload-label  { color: #666; font-size: 0.85rem; }
        .upload-label strong { color: var(--gold); }

        #preview-img {
            max-width: 100%; max-height: 150px;
            border-radius: 6px;
            display: none;
            margin: 0.8rem auto 0;
        }

        .form-actions { margin-top: 1.5rem; display: flex; gap: 1rem; }

        .btn-submit {
            padding: 0.8rem 2rem;
            background: var(--gold);
            color: #000;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: background 0.2s, transform 0.1s;
            font-family: 'Inter', sans-serif;
        }

        .btn-submit:hover  { background: var(--gold-hover); }
        .btn-submit:active { transform: scale(0.98); }

        .btn-reset {
            padding: 0.8rem 1.5rem;
            background: transparent;
            color: #666;
            border: 1px solid #2a2a2a;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-reset:hover { border-color: #555; color: #aaa; }

        /* ── Manage cars table ────────────────── */
        .table-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-wrap { position: relative; flex: 1; max-width: 320px; }

        .search-wrap svg {
            position: absolute;
            left: 10px; top: 50%;
            transform: translateY(-50%);
            width: 16px; height: 16px;
            stroke: #555;
            pointer-events: none;
        }

        .table-search {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 2.2rem;
            background: #0a0a0a;
            border: 1px solid #2a2a2a;
            border-radius: 6px;
            color: var(--text-main);
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
        }

        .table-search:focus { outline: none; border-color: var(--gold); }

        .cars-count { font-size: 0.8rem; color: #555; white-space: nowrap; }

        .cars-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cars-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-size: 0.7rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #555;
            border-bottom: 1px solid #1a1a1a;
        }

        .cars-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #151515;
            font-size: 0.875rem;
            color: var(--text-main);
            vertical-align: middle;
        }

        .cars-table tr:last-child td { border-bottom: none; }

        .cars-table tr:hover td { background: rgba(255,255,255,0.02); }

        .car-thumb {
            width: 70px; height: 48px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #222;
        }

        .car-name-cell strong { display: block; font-weight: 600; }
        .car-name-cell span   { font-size: 0.75rem; color: #555; }

        .price-cell { color: var(--gold); font-weight: 600; }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            background: rgba(212,175,55,0.1);
            color: var(--gold);
            border: 1px solid rgba(212,175,55,0.2);
        }

        .btn-edit {
            padding: 0.4rem 0.8rem;
            background: transparent;
            border: 1px solid #333;
            color: #aaa;
            border-radius: 5px;
            font-size: 0.78rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            display: inline-flex; align-items: center; gap: 4px;
        }

        .btn-edit:hover { border-color: var(--gold); color: var(--gold); }

        .btn-del {
            padding: 0.4rem 0.8rem;
            background: transparent;
            border: 1px solid rgba(231,76,60,0.3);
            color: #e74c3c;
            border-radius: 5px;
            font-size: 0.78rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            display: inline-flex; align-items: center; gap: 4px;
        }

        .btn-del:hover { background: rgba(231,76,60,0.1); }

        .action-cell { display: flex; gap: 6px; align-items: center; }

        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 1.2rem;
            justify-content: flex-end;
        }

        .page-btn {
            padding: 0.45rem 0.9rem;
            background: #0a0a0a;
            border: 1px solid #2a2a2a;
            color: #888;
            border-radius: 5px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .page-btn:hover, .page-btn.active {
            border-color: var(--gold);
            color: var(--gold);
        }

        /* Alerts */
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.85rem 1.2rem;
            border-radius: 7px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.4s ease-out;
        }

        .alert svg { width: 18px; height: 18px; flex-shrink: 0; }

        .alert-success {
            background: rgba(46,204,113,0.1);
            border: 1px solid rgba(46,204,113,0.3);
            color: #2ecc71;
        }

        .alert-error {
            background: rgba(231,76,60,0.1);
            border: 1px solid rgba(231,76,60,0.3);
            color: #e74c3c;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #444;
        }

        .empty-state svg { width: 48px; height: 48px; stroke: #2a2a2a; margin-bottom: 1rem; }

        /* Loading bar */
        .loading-bar {
            position: fixed; top: 0; left: 0;
            height: 3px; background: var(--gold);
            width: 0%; transition: width 0.3s;
            z-index: 9999;
        }

        /* Delete modal */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 500;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open { display: flex; }

        .modal-box {
            background: #111;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 2rem;
            width: 100%;
            max-width: 380px;
            text-align: center;
        }

        .modal-icon svg { width: 44px; height: 44px; stroke: #e74c3c; margin-bottom: 1rem; }

        .modal-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; }

        .modal-text { color: #777; font-size: 0.875rem; margin-bottom: 1.5rem; }

        .modal-actions { display: flex; gap: 0.75rem; justify-content: center; }

        .btn-cancel {
            padding: 0.65rem 1.5rem;
            background: transparent;
            border: 1px solid #333;
            color: #888;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }

        .btn-confirm-del {
            padding: 0.65rem 1.5rem;
            background: #e74c3c;
            border: none;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
        }

        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(8px); }
            to   { opacity:1; transform:translateY(0); }
        }

        @media (max-width: 900px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; padding: 1.5rem; }
            .form-grid { grid-template-columns: 1fr; }
        }
        select option:checked {
            background: var(--gold);
            color: #000;
        }
        select:focus {
            outline: none;
            border-color: var(--gold) !important;
            box-shadow: 0 0 0 3px rgba(212,175,55,0.08);
        }
        /* ── Full screen success toast ── */
        .car-toast {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: toastFadeIn 0.4s ease-out;
        }

        .car-toast-inner {
            background: #111;
            border: 1px solid rgba(46,204,113,0.3);
            border-radius: 16px;
            padding: 2.5rem 3rem;
            text-align: center;
            max-width: 420px;
            width: 90%;
            animation: toastSlideUp 0.4s ease-out;
        }

        .car-toast-check {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: rgba(46,204,113,0.12);
            border: 2px solid #2ecc71;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem;
            animation: toastPop 0.5s 0.2s ease-out both;
        }

        .car-toast-check svg {
            width: 30px; height: 30px;
            stroke: #2ecc71;
        }

        .car-toast-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.5rem;
        }

        .car-toast-sub {
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .car-toast-bar {
            height: 3px;
            background: #1e1e1e;
            border-radius: 10px;
            overflow: hidden;
        }

        .car-toast-bar-fill {
            height: 100%;
            background: #2ecc71;
            border-radius: 10px;
            animation: toastProgress 3s linear forwards;
        }

        @keyframes toastFadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes toastSlideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes toastPop {
            0%   { transform: scale(0.5); opacity: 0; }
            70%  { transform: scale(1.1); }
            100% { transform: scale(1);   opacity: 1; }
        }

        @keyframes toastProgress {
            from { width: 100%; }
            to   { width: 0%; }
        }
        select.fuel-select option,
        select.transmission-select option {
            color: var(--text-main);
        }
        .stat-card {
            opacity: 0;
            transform: translateY(20px);
            animation: statFadeIn 0.6s ease-out forwards;
        }
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.15s; }
        .stat-card:nth-child(3) { animation-delay: 0.25s; }
        .stat-card:nth-child(4) { animation-delay: 0.35s; }

        @keyframes statFadeIn {
            to { opacity: 1; transform: translateY(0); }
        }
        .nav-item {
            opacity: 0;
            transform: translateX(-15px);
            animation: navSlideIn 0.45s ease-out forwards;
        }
        @keyframes navSlideIn {
            to { opacity: 1; transform: translateX(0); }
        }
        .dashboard-car-row {
            opacity: 0;
            transform: translateX(-15px);
            animation: rowSlideIn 0.45s ease-out forwards;
        }
        @keyframes rowSlideIn {
            to { opacity: 1; transform: translateX(0); }
        }
        .panel {
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }
        .panel:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            border-color: #2a2a2a;
        }
        .sold-pulse {
            color: var(--gold);
            font-weight: 600;
            animation: soldPulse 2s ease-in-out infinite;
        }
        @keyframes soldPulse {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.5; }
        }
        .logo-drop {
            display: inline-block;
            opacity: 0;
            transform: translateY(-30px);
            animation: dropDown 0.6s ease-out 0.1s forwards;
        }

        .logo-rise {
            display: block;
            opacity: 0;
            transform: translateY(30px);
            animation: riseUp 0.6s ease-out 0.35s forwards;
        }

        @keyframes dropDown {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes riseUp {
            to { opacity: 1; transform: translateY(0); }
        }
        .title-slide-left {
            display: inline-block;
            opacity: 0;
            transform: translateX(-40px);
            animation: slideFromLeft 0.6s ease-out 0.1s forwards;
        }

        .title-slide-right {
            display: inline-block;
            opacity: 0;
            transform: translateX(40px);
            animation: slideFromRight 0.6s ease-out 0.25s forwards;
        }

        @keyframes slideFromLeft {
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes slideFromRight {
            to { opacity: 1; transform: translateX(0); }
        }
        .slash-static {
            color: var(--gold);
        }
        .chip-dropdown {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            background: #0e0e0e;
            border: 1px solid transparent;
            border-radius: 6px;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            padding: 0 0.5rem;
            margin-top: 0;
            transition: max-height 0.35s ease, opacity 0.3s ease, padding 0.35s ease, margin-top 0.35s ease, border-color 0.3s ease;
        }

        .chip-dropdown.open {
            max-height: 500px;
            opacity: 1;
            padding: 0.5rem;
            margin-top: 4px;
            border-color: #2a2a2a;
        }
    </style>
</head>
<body>

<div class="loading-bar" id="loading-bar"></div>

<!-- Delete Confirm Modal -->
<div class="modal-overlay" id="del-modal">
    <div class="modal-box">
        <div class="modal-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        </div>
        <div class="modal-title">Delete this car?</div>
        <div class="modal-text" id="del-modal-name">This action cannot be undone.</div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal()">No, keep it</button>
            <form method="POST" action="dashboard.php" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="delete_id" id="del-id-input" value="">
                <button type="submit" class="btn-confirm-del">Yes, delete</button>
            </form>
        </div>
    </div>
</div>

<!-- Delete success toast -->
<?php if (!empty($show_delete_toast)): ?>
<div class="car-toast del-toast" id="del-toast">
    <div class="car-toast-inner" style="border-color: rgba(231,76,60,0.3);">
        <div class="car-toast-check" style="background:rgba(231,76,60,0.12); border-color:#e74c3c;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="#e74c3c"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
        </div>
        <div class="car-toast-title">Car Deleted</div>
        <div class="car-toast-sub">The vehicle has been removed from your inventory.</div>
        <div class="car-toast-bar"><div class="car-toast-bar-fill" style="background:#e74c3c;"></div></div>
    </div>
</div>
<?php endif; ?>
<?php if (!empty($show_sold_toast)): ?>
<div class="car-toast" id="sold-toast">
    <div class="car-toast-inner" style="border-color: rgba(212,175,55,0.3);">
        <div class="car-toast-check" style="background:rgba(212,175,55,0.12); border-color:var(--gold);">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="var(--gold)" style="width:30px;height:30px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div class="car-toast-title" style="color:var(--gold);">Car Marked as Sold!</div>
        <div class="car-toast-sub">The vehicle has been labeled as sold and will be automatically removed in 2 hours.</div>
        <div class="car-toast-bar"><div class="car-toast-bar-fill" style="background:var(--gold);"></div></div>
    </div>
</div>
<?php endif; ?>

<div class="dash-wrapper">

    <!-- ── Sidebar ── -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <span class="logo-drop">Premium Auto</span>
            <small class="logo-rise">Dealer Dashboard</small>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">Menu</div>
            <a class="nav-item active" href="#overview" onclick="showSection('overview',this)">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                Overview
            </a>
            <a class="nav-item" href="#add-car" onclick="showSection('add-car',this)">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Add Car
            </a>
            <a class="nav-item" href="#manage" onclick="showSection('manage',this)">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                Manage Cars
            </a>
            <div class="nav-section">Showroom</div>
            <a class="nav-item" href="../index.php" target="_blank">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                View Live Site
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="admin-badge">
                <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_username'], 0, 1)) ?></div>
                <div>
                    <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_username']) ?></div>
                    <div class="admin-role">Dealer Admin</div>
                </div>
            </div>
            <a href="logout.php" class="btn-logout" id="logout-btn">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                Sign Out
            </a>
        </div>
    </aside>

    <!-- ── Main ── -->
    <main class="main-content">

        <div class="topbar">
            <div class="page-title"><span class="title-slide-left">Dashboard</span> <span class="slash-static">/</span> <span id="section-label" class="title-slide-right">Overview</span></div>
            <div class="topbar-time" id="clock"></div>
        </div>

        <?php if ($success): ?>
        <div class="car-toast" id="car-toast">
            <div class="car-toast-inner">
                <div class="car-toast-check">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </div>
                <div class="car-toast-title">Car Added!</div>
                <div class="car-toast-sub"><?= htmlspecialchars($success) ?> has been added to your inventory.</div>
                <div class="car-toast-bar"><div class="car-toast-bar-fill"></div></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- ── OVERVIEW SECTION ── -->
        <section id="sec-overview">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                    </div>
                    <div class="stat-label">Total Cars</div>
                    <div class="stat-value" data-count="<?= $total_cars ?>">0</div>
                    <div class="stat-sub">In inventory</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="stat-label">Total Value</div>
                    <div class="stat-value" data-count="<?= (int)$total_value ?>" data-prefix="$">$0</div>
                    <div class="stat-sub">Inventory worth</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z"/></svg>
                    </div>
                    <div class="stat-label">Avg. Price</div>
                    <div class="stat-value" data-count="<?= (int)$avg_price ?>" data-prefix="$">$0</div>
                    <div class="stat-sub">Per vehicle</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                    </div>
                    <div class="stat-label">Latest Added</div>
                    <div class="stat-value" style="font-size:1rem; padding-top:4px;">
                        <?= $newest ? htmlspecialchars($newest['year'].' '.$newest['make']) : '—' ?>
                    </div>
                    <div class="stat-sub"><?= $newest ? htmlspecialchars($newest['model']) : 'No cars yet' ?></div>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                        Quick Actions
                    </div>
                </div>
                <div class="panel-body" style="display:flex; gap:1rem; flex-wrap:wrap;">
                    <button class="btn-submit" onclick="showSection('add-car', document.querySelector('[href=\'#add-car\']'))">
                        + Add New Car
                    </button>
                    <button class="btn-reset" style="border-color:#2a2a2a;" onclick="showSection('manage', document.querySelector('[href=\'#manage\']'))">
                        Manage Inventory
                    </button>
                    <a href="../index.php" target="_blank" class="btn-reset" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                        View Showroom ↗
                    </a>
                </div>
            </div>
        </section>

        <!-- ── ADD CAR SECTION ── -->
        <section id="sec-add-car" style="display:none;">
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Add New Car
            </div>
        </div>
        <div class="panel-body">
            <form method="POST" action="dashboard.php#add-car" enctype="multipart/form-data" id="add-form">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label>Make *</label>
                        <input type="text" name="make" placeholder="e.g. BMW" value="<?= htmlspecialchars($_POST['make'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Model *</label>
                        <input type="text" name="model" placeholder="e.g. M5" value="<?= htmlspecialchars($_POST['model'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Year *</label>
                        <input type="number" name="year" placeholder="e.g. 2024" min="1900" max="2030" value="<?= htmlspecialchars($_POST['year'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Price (USD) *</label>
                        <input type="number" name="price" placeholder="e.g. 85000" step="0.01" min="0" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Fuel Type *</label>
                        <select name="fuel" class="fuel-select" required style="width:100%; padding:0.75rem 2.5rem 0.75rem 1rem; background:#0a0a0a; border:1px solid #2a2a2a; border-radius:6px; color:var(--text-main); font-size:0.9rem; font-family:'Inter',sans-serif; cursor:pointer; appearance:none; -webkit-appearance:none; background-image:url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23666\' stroke-width=\'2\'%3E%3Cpath d=\'M6 9l6 6 6-6\'/%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 14px center;">
                        <option value="Petrol" <?= (($_POST['fuel'] ?? 'Petrol') === 'Petrol') ? 'selected' : '' ?>>Petrol</option>
                        <option value="Diesel" <?= (($_POST['fuel'] ?? '') === 'Diesel') ? 'selected' : '' ?>>Diesel</option>
                        <option value="Electric" <?= (($_POST['fuel'] ?? '') === 'Electric') ? 'selected' : '' ?>>Electric</option>
                    </select>
                    </div>

                    <div class="form-group">
                        <label>Transmission *</label>
                        <select name="transmission" class="transmission-select" required style="width:100%; padding:0.75rem 2.5rem 0.75rem 1rem; background:#0a0a0a; border:1px solid #2a2a2a; border-radius:6px; color:var(--text-main); font-size:0.9rem; font-family:'Inter',sans-serif; cursor:pointer; appearance:none; -webkit-appearance:none; background-image:url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23666\' stroke-width=\'2\'%3E%3Cpath d=\'M6 9l6 6 6-6\'/%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 14px center;">
                        <option value="Automatic" <?= (($_POST['transmission'] ?? 'Automatic') === 'Automatic') ? 'selected' : '' ?>>Automatic</option>
                        <option value="Manual" <?= (($_POST['transmission'] ?? '') === 'Manual') ? 'selected' : '' ?>>Manual</option>
                    </select>
                    </div>
                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description" placeholder="Describe the car — features, condition, mileage..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group full">
                        <label>Car Options / Features</label>
                        <div id="options-box" style="
                            background:#0a0a0a;
                            border:1px solid #2a2a2a;
                            border-radius:6px;
                            padding:0.6rem 0.75rem;
                            cursor:pointer;
                            display:flex;
                            align-items:center;
                            justify-content:space-between;
                            transition:border-color 0.2s;
                            user-select:none;
                        " onclick="toggleOptionsDropdown()">
                            <span id="options-placeholder" style="color:#555; font-size:0.9rem;">Click to select options...</span>
                            <span style="color:#555; font-size:0.75rem;">▼</span>
                        </div>

                        <div id="options-chips" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;"></div>

                        <div id="options-dropdown" class="chip-dropdown">
                            <?php
                            $car_option_list = [
                                'Heated Seats','Ventilated Seats','Leather Seats','Sunroof','Panoramic Roof',
                                'Navigation System','Bluetooth','360 Camera','Parking Sensors','Cruise Control',
                                'Adaptive Cruise Control','Keyless Entry','Push Button Start','Premium Sound System',
                                'Apple CarPlay','Android Auto','Blind Spot Monitor','Lane Departure Warning',
                                'Remote Start','Power Liftgate','Third Row Seating','All Wheel Drive',
                                'Heated Steering Wheel','Tow Package','Alloy Wheels'
                            ];
                            foreach ($car_option_list as $opt): ?>
                                <span class="option-item" data-value="<?= $opt ?>" onclick="toggleOption(this)" style="
                                    display:inline-block;
                                    padding:0.3rem 0.75rem;
                                    background:#1a1a1a;
                                    border:1px solid #2a2a2a;
                                    border-radius:20px;
                                    font-size:0.8rem;
                                    color:#888;
                                    cursor:pointer;
                                    transition:all 0.15s;
                                "><?= $opt ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div id="options-hidden-inputs"></div>
                        <span style="font-size:0.75rem; color:#555; margin-top:4px; display:block;">Click an option to select it, click again to remove</span>
                    </div>

                    <div class="form-group">
                        <label>Mileage *</label>
                        <div style="position:relative; display:flex; align-items:center;">
                            <input type="text" name="mileage" id="mileage-input" placeholder="e.g. 110.120"
                                   inputmode="numeric"
                                   value="<?= htmlspecialchars($_POST['mileage'] ?? '') ?>"
                                   style="width:100%; padding-right: 40px;" required>
                            <span style="position:absolute; right:12px; color:#555; font-size:0.8rem; pointer-events:none;">mi</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="engine">Engine *</label>
                        <input type="text" name="engine" id="engine" placeholder="e.g. 2.0L Turbo" value="<?= htmlspecialchars($_POST['engine'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Accidents *</label>
                        <select name="accidents" style="width:100%; padding:0.75rem 2.5rem 0.75rem 1rem; background:#0a0a0a; border:1px solid #2a2a2a; border-radius:6px; color:var(--text-main); font-size:0.9rem; font-family:'Inter',sans-serif; cursor:pointer; appearance:none; -webkit-appearance:none; background-image:url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23666\' stroke-width=\'2\'%3E%3Cpath d=\'M6 9l6 6 6-6\'/%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 14px center;">
                            <option value="0" <?= (($_POST['accidents'] ?? '0') == '0') ? 'selected' : '' ?>>No accidents</option>
                            <option value="1" <?= (($_POST['accidents'] ?? '') == '1') ? 'selected' : '' ?>>Yes — has accident history</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="hp">Horsepower (HP) *</label>
                        <input type="number" name="hp" id="hp" placeholder="e.g. 250" value="<?= htmlspecialchars($_POST['hp'] ?? '') ?>" required>
                    </div>

                    <div class="form-group full">
                        <label>Parts Changed</label>
                        <div id="parts-box" style="
                            background:#0a0a0a;
                            border:1px solid #2a2a2a;
                            border-radius:6px;
                            padding:0.6rem 0.75rem;
                            cursor:pointer;
                            display:flex;
                            align-items:center;
                            justify-content:space-between;
                            transition:border-color 0.2s;
                            user-select:none;
                        " onclick="togglePartsDropdown()">
                            <span id="parts-placeholder" style="color:#555; font-size:0.9rem;">Click to select parts...</span>
                            <span style="color:#555; font-size:0.75rem;">▼</span>
                        </div>

                        <div id="parts-chips" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;"></div>

                        <div id="parts-dropdown" class="chip-dropdown">
                            <?php
                            $part_options = [
                                'Front bumper','Rear bumper','Hood','Trunk lid',
                                'Front left door','Front right door','Rear left door','Rear right door',
                                'Left fender','Right fender','Windshield','Rear window',
                                'Left headlight','Right headlight','Left taillight','Right taillight',
                                'Engine','Gearbox','Airbags','Catalytic converter'
                            ];
                            foreach ($part_options as $part): ?>
                                <span class="part-option" data-value="<?= $part ?>" onclick="togglePart(this)" style="
                                    display:inline-block;
                                    padding:0.3rem 0.75rem;
                                    background:#1a1a1a;
                                    border:1px solid #2a2a2a;
                                    border-radius:20px;
                                    font-size:0.8rem;
                                    color:#888;
                                    cursor:pointer;
                                    transition:all 0.15s;
                                "><?= $part ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div id="parts-hidden-inputs"></div>
                        <span style="font-size:0.75rem; color:#555; margin-top:4px; display:block;">Click a part to select it, click again to remove</span>
                    </div>

                    <div class="form-group full">
                        <label>Car Images * <span style="color:#555; font-weight:400; text-transform:none; font-size:0.75rem;">— first image will be the main photo. Max 10 images, 5MB each.</span></label>
                        <div class="image-upload-area" id="upload-area">
                            <input type="file" name="images[]" id="image-input" accept="image/*" multiple required>
                            <div class="upload-icon">
                                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                            </div>
                            <div class="upload-label"><strong>Click to upload</strong> or drag and drop</div>
                            <div style="font-size:0.75rem; color:#444; margin-top:4px;">Select multiple images at once</div>
                        </div>
                        <div id="preview-grid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:10px; margin-top:12px;"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-submit" id="add-btn">Add Car to Inventory</button>
                    <button type="reset" class="btn-reset" onclick="resetPreview()">Clear Form</button>
                </div>
            </form>
        </div>
    </div>
</section>

        <!-- ── MANAGE SECTION ── -->
        <section id="sec-manage" style="display:none;">
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                        Manage Cars
                    </div>
                    <span class="badge"><?= $total_filtered ?> vehicles</span>
                </div>
                <div class="panel-body">
                    <div class="table-toolbar" style="display: flex; justify-content: space-between; align-items: center; gap: 15px; margin-bottom: 20px;">
    
                        <div class="search-box-wrap" style="flex: 1; max-width: 320px; position: relative;">
                            <div class="search-wrap" style="width: 100%; display: flex; align-items: center; background: #111; border: 1px solid #2a2a2a; border-radius: 6px; padding: 0 10px;">
                                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px; color: #555; margin-right: 8px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 10.607z"/>
                                </svg>
                                <input type="text" id="dashboard-search-input" class="table-search" 
                                    placeholder="Search by make, model, or year..." autocomplete="off"
                                    style="background: transparent; border: none; color: #fff; padding: 0.65rem 0 0.65rem 30px; width: 100%; outline: none; font-size: 0.9rem;">
                            </div>

                            <div class="search-dropdown" id="dashboard-search-dropdown" 
                                style="position: absolute; top: 100%; left: 0; right: 0; background: #111; border: 1px solid #2a2a2a; border-radius: 6px; max-height: 250px; overflow-y: auto; z-index: 999; display: none; margin-top: 5px; box-shadow: 0 8px 16px rgba(0,0,0,0.5);">
                            </div>
                            
                            <button class="btn-clear-search" id="dashboard-clear-btn" onclick="clearDashboardFilters()" 
                                    style="display: none; position: absolute; right: -85px; top: 50%; transform: translateY(-50%); background: #1a1a1a; border: 1px solid #2a2a2a; color: #fff; padding: 0.5rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem; white-space: nowrap;">
                                ✕ Clear
                            </button>
                        </div>

                        <div class="cars-count" id="dashboard-cars-counter" style="color: #888; font-size: 0.9rem;">
                            Showing <?= $cars_result->num_rows ?> of <?= $cars_result->num_rows ?>
                        </div>
                    </div>

                    <?php if ($cars_result->num_rows > 0): ?>
                    <table class="cars-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Vehicle</th>
                                <th>Year</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($car = $cars_result->fetch_assoc()): ?>
                            <tr class="dashboard-car-row" <?= ($car['sold'] ?? 0) ? 'sold-row' : '' ?> data-title="<?= htmlspecialchars(strtolower(($car['year'] ?? '') . ' ' . ($car['make'] ?? '') . ' ' . ($car['model'] ?? ''))) ?>">
                                
                                <td>
                                    <img src="../<?= htmlspecialchars($car['image_path']) ?>"
                                        alt="<?= htmlspecialchars($car['make'].' '.$car['model']) ?>"
                                        class="car-thumb">
                                </td>
                                <td class="car-name-cell">
                                    <strong><?= htmlspecialchars($car['make'].' '.$car['model']) ?></strong>
                                    <span>ID #<?= $car['id'] ?> <?= ($car['sold'] ?? 0) ? '· <span class="sold-pulse">SOLD</span>' : '' ?></span>
                                </td>
                                <td><span class="badge"><?= $car['year'] ?></span></td>
                                <td class="price-cell">$<?= number_format($car['price'], 2) ?></td>
                                <td>
                                    <div class="action-cell">
                                        <a href="edit.php?id=<?= $car['id'] ?>" class="btn-edit">
                                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg>
                                            Edit
                                        </a>
                                        <button class="btn-del"
                                            onclick="confirmDelete(<?= $car['id'] ?>, '<?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model'], ENT_QUOTES) ?>')">
                                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?p=<?= $i ?>&s=<?= urlencode($search) ?>&section=manage"
                               class="page-btn <?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>

                    <?php else: ?>
                    <div class="empty-state">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                        <p><?= $search ? 'No cars match your search.' : 'No cars in inventory yet.' ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    </main>
</div>

<script>
// ── Section navigation ──────────────────────────────
const sections = ['overview', 'add-car', 'manage'];

function showSection(id, el) {
    sections.forEach(s => {
        const sec = document.getElementById('sec-' + s);
        if (sec) sec.style.display = 'none';
    });
    
    const targetSec = document.getElementById('sec-' + id);
    if (targetSec) targetSec.style.display = 'block';

    if (id === 'manage') {
        document.querySelectorAll('.dashboard-car-row').forEach((row, i) => {
            row.style.animation = 'none';
            row.offsetHeight; // force reflow to restart animation
            row.style.animation = '';
            row.style.animationDelay = (i * 0.05) + 's';
        });
    }
    
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    if (el) el.classList.add('active');
    
    const label = document.getElementById('section-label');
    if (label) {
        label.textContent = (id === 'add-car' ? 'Add Car' : id.charAt(0).toUpperCase() + id.slice(1));
        label.style.animation = 'none';
        label.offsetHeight; // force reflow to restart animation
        label.style.animation = '';
    }
    if (window.event && window.event.type === 'click') {
        window.event.preventDefault();
    }
}

// Auto-show section from URL param
const urlParams   = new URLSearchParams(window.location.search);
const initSection = urlParams.get('section') || 'overview';
const navLink     = document.querySelector(`[href='#${initSection}']`);
showSection(initSection, navLink);

// ── Clock ───────────────────────────────────────────
function updateClock() {
    const clockEl = document.getElementById('clock');
    if (!clockEl) return;
    const now = new Date();
    clockEl.textContent =
        now.toLocaleDateString('en-US', {weekday:'short', month:'short', day:'numeric'}) +
        ' · ' + now.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
}
updateClock();
setInterval(updateClock, 1000);

// ── Image preview ───────────────────────────────────
let allSelectedFiles = [];
const imgInput = document.getElementById('image-input');
if (imgInput) {
    imgInput.addEventListener('change', function() {
        const newFiles = Array.from(this.files);
        newFiles.forEach(newFile => {
            const exists = allSelectedFiles.some(f => f.name === newFile.name && f.size === newFile.size);
            if (!exists) allSelectedFiles.push(newFile);
        });
        allSelectedFiles = allSelectedFiles.slice(0, 10);
        const dt = new DataTransfer();
        allSelectedFiles.forEach(f => dt.items.add(f));
        this.files = dt.files;
        renderAddPreviews();
    });
}

function renderAddPreviews() {
    const grid = document.getElementById('preview-grid');
    const area = document.getElementById('upload-area');
    if (!grid || !area) return;
    grid.innerHTML = '';

    if (allSelectedFiles.length > 0) {
        area.classList.add('has-file');
        allSelectedFiles.forEach((file, idx) => {
            const reader = new FileReader();
            reader.onload = e => {
                const wrap = document.createElement('div');
                wrap.style.cssText = 'position:relative; border-radius:6px; overflow:hidden; border:1px solid #2a2a2a; aspect-ratio:4/3;';
                wrap.innerHTML = `
                    <img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;">
                    ${idx === 0 ? '<span style="position:absolute;top:5px;left:5px;background:var(--gold);color:#000;font-size:0.65rem;font-weight:700;padding:2px 7px;border-radius:10px;">MAIN</span>' : ''}
                    <button type="button" onclick="removeAddFile(${idx})" style="position:absolute;top:5px;right:5px;background:rgba(0,0,0,0.7);border:none;color:#fff;width:22px;height:22px;border-radius:50%;cursor:pointer;font-size:0.75rem;display:flex;align-items:center;justify-content:center;">✕</button>
                `;
                grid.appendChild(wrap);
            };
            reader.readAsDataURL(file);
        });
    } else {
        area.classList.remove('has-file');
    }
}

function removeAddFile(idx) {
    allSelectedFiles.splice(idx, 1);
    const dt = new DataTransfer();
    allSelectedFiles.forEach(f => dt.items.add(f));
    const inputEl = document.getElementById('image-input');
    if (inputEl) inputEl.files = dt.files;
    renderAddPreviews();
}

function resetPreview() {
    allSelectedFiles = [];
    const dt = new DataTransfer();
    const inputEl = document.getElementById('image-input');
    if (inputEl) inputEl.files = dt.files;
    const grid = document.getElementById('preview-grid');
    if (grid) grid.innerHTML = '';
    const area = document.getElementById('upload-area');
    if (area) area.classList.remove('has-file');
}

// ── Delete modal ────────────────────────────────────
function confirmDelete(id, name) {
    const modalName = document.getElementById('del-modal-name');
    const idInput = document.getElementById('del-id-input');
    const modal = document.getElementById('del-modal');
    if (modalName) modalName.textContent = 'Are you sure you want to remove "' + name + '" from the system? This cannot be undone.';
    if (idInput) idInput.value = id;
    if (modal) modal.classList.add('open');
}

function closeModal() {
    const modal = document.getElementById('del-modal');
    if (modal) modal.classList.remove('open');
}

const delModal = document.getElementById('del-modal');
if (delModal) {
    delModal.addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
}

// ── Loading bar on form submit / logout ─────────────
// Locate your addForm submit listener and append this line inside:
const addForm = document.getElementById('add-form');
if (addForm) {
    addForm.addEventListener('submit', function() {
        // Strip out formatting dots from mileage input right before hitting backend
        const mInput = document.getElementById('mileage-input');
        if (mInput) {
            mInput.value = mInput.value.replace(/\./g, ''); 
        }

        const bar = document.getElementById('loading-bar');
        const btn = document.getElementById('add-btn');
        if (btn) { btn.textContent = 'Uploading...'; btn.disabled = true; }
        let w = 0;
        setInterval(() => { w = Math.min(w + Math.random()*12, 85); if (bar) bar.style.width = w+'%'; }, 200);
    });
}

const logoutBtn = document.getElementById('logout-btn');
if (logoutBtn) {
    logoutBtn.addEventListener('click', function() {
        const bar = document.getElementById('loading-bar');
        let w = 0;
        setInterval(() => { w = Math.min(w + 20, 90); if (bar) bar.style.width = w+'%'; }, 100);
    });
}

// ── Drag & drop ─────────────────────────────────────
const uploadArea = document.getElementById('upload-area');
if (uploadArea) {
    uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.style.borderColor = 'var(--gold)'; });
    uploadArea.addEventListener('dragleave', () => { uploadArea.style.borderColor = ''; });
    uploadArea.addEventListener('drop', e => {
        e.preventDefault();
        uploadArea.style.borderColor = '';
        const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'));
        if (files.length > 0) {
            files.forEach(newFile => {
                const exists = allSelectedFiles.some(f => f.name === newFile.name && f.size === newFile.size);
                if (!exists) allSelectedFiles.push(newFile);
            });
            allSelectedFiles = allSelectedFiles.slice(0, 10);
            const dt2 = new DataTransfer();
            allSelectedFiles.forEach(f => dt2.items.add(f));
            document.getElementById('image-input').files = dt2.files;
            renderAddPreviews();
        }
    });
}

// Mileage auto-format
const mileageInput = document.getElementById('mileage-input');
if (mileageInput) {
    mileageInput.addEventListener('input', function() {
        let raw = this.value.replace(/\./g, '').replace(/\D/g, '');
        if (raw.length > 3) { raw = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
        this.value = raw;
    });
}

// ── Parts chip selector ─────────────────────────────
let selectedParts = [];

function togglePartsDropdown() {
    const dd = document.getElementById('parts-dropdown');
    const box = document.getElementById('parts-box');
    if (!dd || !box) return;

    const isOpen = dd.classList.contains('open');
    dd.classList.toggle('open', !isOpen);
    box.style.borderColor = !isOpen ? 'var(--gold)' : '#2a2a2a';
}

function togglePart(el) {
    const val = el.dataset.value;
    const idx = selectedParts.indexOf(val);
    if (idx === -1) {
        selectedParts.push(val);
        el.style.background = 'rgba(212,175,55,0.15)';
        el.style.borderColor = 'var(--gold)';
        el.style.color = 'var(--gold)';
    } else {
        selectedParts.splice(idx, 1);
        el.style.background = '#1a1a1a';
        el.style.borderColor = '#2a2a2a';
        el.style.color = '#888';
    }
    renderChips();
    updateHiddenInputs();
    updatePlaceholder();
}

function renderChips() {
    const container = document.getElementById('parts-chips');
    if (!container) return;
    container.innerHTML = '';
    selectedParts.forEach(part => {
        const chip = document.createElement('span');
        chip.style.cssText = `display:inline-flex; align-items:center; gap:5px; padding:0.3rem 0.75rem; background:rgba(212,175,55,0.12); border:1px solid rgba(212,175,55,0.3); border-radius:20px; font-size:0.78rem; color:var(--gold);`;
        chip.innerHTML = `${part} <span onclick="removeChip('${part}')" style="cursor:pointer; font-size:0.9rem; opacity:0.7; line-height:1;">✕</span>`;
        container.appendChild(chip);
    });
}

function removeChip(val) {
    selectedParts = selectedParts.filter(p => p !== val);
    document.querySelectorAll('.part-option').forEach(el => {
        if (el.dataset.value === val) {
            el.style.background = '#1a1a1a';
            el.style.borderColor = '#2a2a2a';
            el.style.color = '#888';
        }
    });
    renderChips();
    updateHiddenInputs();
    updatePlaceholder();
}

function updateHiddenInputs() {
    const container = document.getElementById('parts-hidden-inputs');
    if (!container) return;
    container.innerHTML = '';
    selectedParts.forEach(part => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'parts_changed[]';
        input.value = part;
        container.appendChild(input);
    });
}

function updatePlaceholder() {
    const ph = document.getElementById('parts-placeholder');
    if (!ph) return;
    ph.textContent = selectedParts.length > 0 ? selectedParts.length + ' part(s) selected' : 'Click to select parts...';
    ph.style.color = selectedParts.length > 0 ? '#ccc' : '#555';
}

document.addEventListener('click', function(e) {
    const box = document.getElementById('parts-box');
    const dd  = document.getElementById('parts-dropdown');
    if (box && dd && !box.contains(e.target) && !dd.contains(e.target)) {
        dd.classList.remove('open');
        box.style.borderColor = '#2a2a2a';
    }

    const obox = document.getElementById('options-box');
    const odd  = document.getElementById('options-dropdown');
    if (obox && odd && !obox.contains(e.target) && !odd.contains(e.target)) {
        odd.classList.remove('open');
        obox.style.borderColor = '#2a2a2a';
    }
});

// ── Auto-close toasts (CLEAN SINGLE DECLARATIONS) ────────────────────────
const toastElement = document.getElementById('car-toast');
if (toastElement) {
    setTimeout(() => {
        toastElement.style.transition = 'opacity 0.4s';
        toastElement.style.opacity = '0';
        setTimeout(() => toastElement.remove(), 400);
    }, 3000);
}

const deleteToastElement = document.getElementById('del-toast');
if (deleteToastElement) {
    setTimeout(() => {
        deleteToastElement.style.transition = 'opacity 0.4s';
        deleteToastElement.style.opacity = '0';
        setTimeout(() => deleteToastElement.remove(), 400);
    }, 3000);
}

// ── Live Client-Side Dashboard Search Engine Engine ─────────────────────
(function() {
    const rows = document.querySelectorAll('.dashboard-car-row');
    const brandGroups = {};

    rows.forEach(row => {
        const fullText = row.dataset.title || '';
        const parts = fullText.split(' ');
        if (parts.length >= 2) {
            const brand = parts[1].toUpperCase();
            if (!brandGroups[brand]) brandGroups[brand] = [];
            brandGroups[brand].push(fullText);
        }
    });

    const dInput    = document.getElementById('dashboard-search-input');
    const dDropdown = document.getElementById('dashboard-search-dropdown');
    const dCounter  = document.getElementById('dashboard-cars-counter');

    if (!dInput || !dDropdown) return;

    dInput.addEventListener('focus', () => {
        renderDashDropdown(brandGroups, Object.keys(brandGroups));
        dDropdown.style.display = 'block';
    });

    dInput.addEventListener('input', () => {
        const query = dInput.value.toLowerCase().trim();
        const matchedBrands = Object.keys(brandGroups).filter(brand => 
            brand.toLowerCase().includes(query) || 
            brandGroups[brand].some(titleText => titleText.includes(query))
        );

        renderDashDropdown(brandGroups, matchedBrands);
        dDropdown.style.display = 'block';
        filterDashboardRows(query);
    });

    function renderDashDropdown(groups, brandsList) {
        if (brandsList.length === 0) {
            dDropdown.innerHTML = '<div style="padding: 1rem; color: #666; font-size: 0.85rem; text-align: center;">No matching brands found.</div>';
            return;
        }

        dDropdown.innerHTML = '';
        brandsList.sort().forEach(brand => {
            const dropdownRow = document.createElement('div');
            dropdownRow.style.cssText = `padding: 0.8rem 1.2rem; font-size: 0.9rem; font-weight: 600; color: #d4af37; border-bottom: 1px solid #1a1a1a; cursor: pointer; display: flex; align-items: center; justify-content: space-between; background: #111;`;

            const cleanBrandName = brand.toLowerCase().trim();
            const logoPath = '../logos/' + cleanBrandName + '.png';
            
            dropdownRow.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px; pointer-events: none;">
                    <img src="${logoPath}" style="width: 24px; height: 24px; object-fit: contain;"
                         onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\\'http://www.w3.org/2000/svg\\' viewBox=\\'0 0 24 24\\' fill=\\'%23d4af37\\'><path d=\\'M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5 0 .67 1.5 1.5-.67 1.5-1.5zm-11-4.5l1.39-4h10.22l1.4 4H6.5z\\'/></svg>';">
                    <span style="text-transform: capitalize; color: #d4af37;">${cleanBrandName}</span>
                </div>
                <span style="color: #666; font-size: 0.8rem; font-weight: 400; pointer-events: none;">${groups[brand].length} listing(s)</span>
            `;

            dropdownRow.addEventListener('click', (e) => {
                e.stopPropagation();
                dInput.value = cleanBrandName;
                dDropdown.style.display = 'none';
                filterDashboardRows(cleanBrandName);
            });

            dDropdown.appendChild(dropdownRow);
        });
    }

    function filterDashboardRows(term) {
        let matchedCount = 0;
        rows.forEach(row => {
            const titleText = row.dataset.title || '';
            const isVisible = !term || titleText.includes(term);
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) matchedCount++;
        });

        const clearBtn = document.getElementById('dashboard-clear-btn');
        if (clearBtn) { clearBtn.style.display = term ? 'block' : 'none'; }
        if (dCounter) { dCounter.textContent = `Showing ${matchedCount} of ${rows.length}`; }
    }

    window.clearDashboardFilters = function() {
        if (dInput) dInput.value = '';
        dDropdown.style.display = 'none';
        filterDashboardRows('');
    };

    document.addEventListener('click', e => {
        if (!e.target.closest('.search-box-wrap')) {
            dDropdown.style.display = 'none';
        }
    });
})();
const soldToast = document.getElementById('sold-toast');
if (soldToast) {
    setTimeout(() => {
        soldToast.style.transition = 'opacity 0.4s';
        soldToast.style.opacity = '0';
        setTimeout(() => soldToast.remove(), 400);
    }, 3000);
    soldToast.addEventListener('click', () => {
        soldToast.style.transition = 'opacity 0.3s';
        soldToast.style.opacity = '0';
        setTimeout(() => soldToast.remove(), 300);
    });
}
// ── Count-up animation for stat cards ───────────────
function animateCount(el) {
    const target = parseInt(el.dataset.count, 10);
    const prefix = el.dataset.prefix || '';
    const duration = 1200;
    const start = performance.now();

    function update(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = Math.floor(eased * target);
        el.textContent = prefix + current.toLocaleString('en-US');
        if (progress < 1) requestAnimationFrame(update);
        else el.textContent = prefix + target.toLocaleString('en-US');
    }
    requestAnimationFrame(update);
}
document.querySelectorAll('.stat-value[data-count]').forEach(animateCount);
// ── Staggered sidebar nav animation ─────────────────
document.querySelectorAll('.nav-item').forEach((item, i) => {
    item.style.animationDelay = (i * 0.06) + 's';
});
// ── Car Options chip selector ───────────────────────
let selectedOptions = [];

function toggleOptionsDropdown() {
    const dd = document.getElementById('options-dropdown');
    const box = document.getElementById('options-box');
    if (!dd || !box) return;

    const isOpen = dd.classList.contains('open');
    dd.classList.toggle('open', !isOpen);
    box.style.borderColor = !isOpen ? 'var(--gold)' : '#2a2a2a';
}

function toggleOption(el) {
    const val = el.dataset.value;
    const idx = selectedOptions.indexOf(val);
    if (idx === -1) {
        selectedOptions.push(val);
        el.style.background = 'rgba(212,175,55,0.15)';
        el.style.borderColor = 'var(--gold)';
        el.style.color = 'var(--gold)';
    } else {
        selectedOptions.splice(idx, 1);
        el.style.background = '#1a1a1a';
        el.style.borderColor = '#2a2a2a';
        el.style.color = '#888';
    }
    renderOptionChips();
    updateOptionsHiddenInputs();
    updateOptionsPlaceholder();
}

function renderOptionChips() {
    const container = document.getElementById('options-chips');
    if (!container) return;
    container.innerHTML = '';
    selectedOptions.forEach(opt => {
        const chip = document.createElement('span');
        chip.style.cssText = `display:inline-flex; align-items:center; gap:5px; padding:0.3rem 0.75rem; background:rgba(212,175,55,0.12); border:1px solid rgba(212,175,55,0.3); border-radius:20px; font-size:0.78rem; color:var(--gold);`;
        chip.innerHTML = `${opt} <span onclick="removeOptionChip('${opt}')" style="cursor:pointer; font-size:0.9rem; opacity:0.7; line-height:1;">✕</span>`;
        container.appendChild(chip);
    });
}

function removeOptionChip(val) {
    selectedOptions = selectedOptions.filter(o => o !== val);
    document.querySelectorAll('.option-item').forEach(el => {
        if (el.dataset.value === val) {
            el.style.background = '#1a1a1a';
            el.style.borderColor = '#2a2a2a';
            el.style.color = '#888';
        }
    });
    renderOptionChips();
    updateOptionsHiddenInputs();
    updateOptionsPlaceholder();
}

function updateOptionsHiddenInputs() {
    const container = document.getElementById('options-hidden-inputs');
    if (!container) return;
    container.innerHTML = '';
    selectedOptions.forEach(opt => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'car_options[]';
        input.value = opt;
        container.appendChild(input);
    });
}

function updateOptionsPlaceholder() {
    const ph = document.getElementById('options-placeholder');
    if (!ph) return;
    ph.textContent = selectedOptions.length > 0 ? selectedOptions.length + ' option(s) selected' : 'Click to select options...';
    ph.style.color = selectedOptions.length > 0 ? '#ccc' : '#555';
}
</script>
</body>
</html>

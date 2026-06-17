<?php
require_once 'auth_guard.php';

// Handle single image delete via AJAX
if (isset($_GET['delete_image'])) {
    $img_id = (int)$_GET['delete_image'];
    $car_id = (int)$_GET['car_id'];

    $stmt = $conn->prepare("SELECT image_path, is_primary FROM car_images WHERE id = ? AND car_id = ?");
    $stmt->bind_param('ii', $img_id, $car_id);
    $stmt->execute();
    $img = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($img) {
        $del = $conn->prepare("DELETE FROM car_images WHERE id = ?");
        $del->bind_param('i', $img_id);
        $del->execute();
        $del->close();

        $file = '../' . $img['image_path'];
        if (file_exists($file)) unlink($file);

        // If deleted was primary, promote next image
        if ($img['is_primary']) {
            $next = $conn->prepare("SELECT id, image_path FROM car_images WHERE car_id = ? ORDER BY sort_order ASC LIMIT 1");
            $next->bind_param('i', $car_id);
            $next->execute();
            $new_primary = $next->get_result()->fetch_assoc();
            $next->close();
            if ($new_primary) {
                $upd = $conn->prepare("UPDATE car_images SET is_primary=1 WHERE id=?");
                $upd->bind_param('i', $new_primary['id']);
                $upd->execute();
                $upd->close();
                $upd2 = $conn->prepare("UPDATE cars SET image_path=? WHERE id=?");
                $upd2->bind_param('si', $new_primary['image_path'], $car_id);
                $upd2->execute();
                $upd2->close();
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false]);
    }
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: dashboard.php?section=manage'); exit; }

$stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$car = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$car) { header('Location: dashboard.php?section=manage'); exit; }

$success = '';
$error   = '';
$show_edit_toast = false;
if (!empty($_SESSION['edit_success'])) {
    $show_edit_toast = $_SESSION['edit_success'];
    unset($_SESSION['edit_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $make    = trim($_POST['make'] ?? '');
    $model   = trim($_POST['model'] ?? '');
    $year    = (int)($_POST['year'] ?? 0);
    $price   = (float)($_POST['price'] ?? 0);
    $desc    = trim($_POST['description'] ?? '');
    
    // BACKEND INTEGRATION: Collect Engine and HP
    $engine  = trim($_POST['engine'] ?? '');
    $hp = (int)($_POST['hp'] ?? 0);

    // BACKEND INTEGRATION: Collect Mileage, Accidents, and Parts Changed
    $mileage   = trim($_POST['mileage'] ?? '');
    $accidents = (int)($_POST['accidents'] ?? 0);
    $fuel         = trim($_POST['fuel'] ?? '');
    $transmission = trim($_POST['transmission'] ?? '');
    // Combine parts array back to a safe comma-separated string for DB storage
    $parts_arr = $_POST['parts_changed'] ?? [];
    $parts_changed = !empty($parts_arr) ? implode(', ', $parts_arr) : '';
    $options_arr = $_POST['car_options'] ?? [];
    $car_options = !empty($options_arr) ? implode(', ', $options_arr) : '';

    if (!$make || !$model || !$year || !$price || !$engine || !$hp) {
        $error = 'Please fill in all required fields.';
    } else {
        $image_path = $car['image_path'];

        // New image uploaded?
        if (!empty($_FILES['images']['name'][0])) {
            $allowed    = ['image/jpeg','image/png','image/webp','image/gif'];
            $upload_dir = __DIR__ . '/uploads/';
            $files      = $_FILES['images'];
            $total      = count($files['name']);

            for ($i = 0; $i < min($total, 10); $i++) {
                if ($files['error'][$i] !== 0) continue;
                $file_type = mime_content_type($files['tmp_name'][$i]);
                if (!in_array($file_type, $allowed)) continue;
                $file_size = $files['size'][$i];
                if ($file_size > 5 * 1024 * 1024) continue;

                $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $filename = uniqid('car_', true) . '.' . strtolower($ext);
                $dest     = $upload_dir . $filename;

                if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    $new_path = 'admin/uploads/' . $filename;

                    // Check if this car has any images yet
                    $chk = $conn->prepare("SELECT COUNT(*) as c FROM car_images WHERE car_id = ?");
                    $chk->bind_param('i', $id);
                    $chk->execute();
                    $has_images = $chk->get_result()->fetch_assoc()['c'];
                    $chk->close();

                    $is_primary = ($has_images == 0 && $i == 0) ? 1 : 0;

                    // Get current max sort_order
                    $ord_q = $conn->query("SELECT MAX(sort_order) as m FROM car_images WHERE car_id = $id");
                    $max_ord = ($ord_q->fetch_assoc()['m'] ?? -1) + 1;

                    $ins = $conn->prepare("INSERT INTO car_images (car_id, image_path, is_primary, sort_order) VALUES (?,?,?,?)");
                    $ins->bind_param('isii', $id, $new_path, $is_primary, $max_ord);
                    $ins->execute();
                    $ins->close();

                    if ($is_primary) {
                        $image_path = $new_path;
                    }
                }
            }
        }

        if (!$error) {
            $upd = $conn->prepare(
                "UPDATE cars SET make=?, model=?, year=?, price=?, fuel=?, transmission=?, description=?, engine=?, hp=?, mileage=?, accidents=?, parts_changed=?, car_options=?, image_path=? WHERE id=?"
            );

            $upd->bind_param('ssisssssisssssi', $make, $model, $year, $price, $fuel, $transmission, $desc, $engine, $hp, $mileage, $accidents, $parts_changed, $car_options, $image_path, $id);
            
            if ($upd->execute()) {
            $_SESSION['edit_success'] = $car['year'].' '.$make.' '.$model;
            header('Location: edit.php?id='.$id);
            exit;
                
                // Force engine to remain a string representation so trailing zeros (.0) do not get stripped
                $engine_string = (strpos($engine, '.') === false) ? $engine : sprintf("%.1f", (float)$engine);
                
                // Sync local object array state representation securely
                $car = array_merge($car, compact('make','model','year','price','desc'), [
                    'engine' => $engine_string,
                    'hp' => $hp,
                    'mileage' => $mileage,
                    'accidents' => $accidents,
                    'parts_changed' => $parts_changed,
                    'image_path' => $image_path
                ]);
            } else {
                $error = 'Database error: ' . $conn->error;
            }
            $upd->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Car | Premium Auto</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #0a0a0a; }

        .edit-wrapper {
            max-width: 780px;
            margin: 3rem auto;
            padding: 0 1.5rem;
        }

        .back-nav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #666;
            text-decoration: none;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            transition: color 0.2s;
        }
        .back-nav:hover { color: var(--gold); }

        .edit-panel {
            background: #111;
            border: 1px solid #222;
            border-radius: 10px;
            overflow: hidden;
        }

        .edit-header {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .edit-title {
            color: var(--gold);
            font-size: 1rem;
            font-weight: 600;
        }

        .car-id-badge {
            background: rgba(212,175,55,0.1);
            border: 1px solid rgba(212,175,55,0.2);
            color: var(--gold);
            font-size: 0.75rem;
            padding: 0.25rem 0.7rem;
            border-radius: 20px;
        }

        .edit-body { padding: 1.5rem; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .form-group.full { grid-column: 1 / -1; }

        .form-group label {
            font-size: 0.75rem;
            color: var(--gold);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
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
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212,175,55,0.08);
        }

        .form-group textarea { min-height: 90px; resize: vertical; }

        .file-input-wrap {
            padding: 0.75rem 1rem;
            background: #0a0a0a;
            border: 1px dashed #2a2a2a;
            border-radius: 6px;
            color: #666;
            font-size: 0.85rem;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .file-input-wrap:hover { border-color: var(--gold); }
        .file-input-wrap input { display: none; }

        .form-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-save {
            padding: 0.8rem 2rem;
            background: var(--gold);
            color: #000;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: background 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-save:hover { background: var(--gold-hover); }

        .btn-cancel-edit {
            padding: 0.8rem 1.5rem;
            background: transparent;
            color: #666;
            border: 1px solid #2a2a2a;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            font-family: 'Inter', sans-serif;
            display: inline-flex; align-items: center;
        }

        .btn-cancel-edit:hover { border-color: #555; color: #aaa; }
        
        .btn-sold {
            padding: 0.8rem 1.5rem;
            background: transparent;
            color: #2ecc71;
            border: 1px solid rgba(46,204,113,0.4);
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .btn-sold:hover {
            background: rgba(46,204,113,0.1);
            border-color: #2ecc71;
        }

        .btn-sold:disabled {
            background: rgba(212,175,55,0.1);
            border-color: var(--gold);
            color: var(--gold);
            cursor: not-allowed;
            opacity: 0.7;
        }

        .alert {
            display: flex; align-items: center; gap: 10px;
            padding: 0.85rem 1.2rem;
            border-radius: 7px; font-size: 0.875rem;
            margin-bottom: 1.5rem;
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

        .loading-bar {
            position: fixed; top: 0; left: 0;
            height: 3px; background: var(--gold);
            width: 0%; transition: width 0.3s; z-index: 9999;
        }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
        }
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

        .car-toast-check svg { width: 30px; height: 30px; stroke: #2ecc71; }

        .car-toast-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.5rem;
        }

        .car-toast-sub { color: #888; font-size: 0.9rem; margin-bottom: 1.5rem; }

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
            from { opacity: 0; } to { opacity: 1; }
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
            from { width: 100%; } to { width: 0%; }
        }
        .swal2-custom-confirm-btn {
            color: #000000 !important;
            font-weight: 700 !important;
            font-family: 'Inter', sans-serif !important;
        }
        .swal2-popup {
            border: 1px solid rgba(212, 175, 55, 0.2) !important;
            border-radius: 12px !important;
        }
    </style>
</head>
<body>
<div class="loading-bar" id="loading-bar"></div>

<div class="edit-wrapper">
    <a href="dashboard.php?section=manage" class="back-nav">
        ← Back to dashboard
    </a>

    <?php if ($show_edit_toast): ?>
    <div class="car-toast" id="edit-toast">
        <div class="car-toast-inner">
            <div class="car-toast-check">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            </div>
            <div class="car-toast-title">Changes Saved!</div>
            <div class="car-toast-sub"><?= htmlspecialchars($show_edit_toast) ?> has been updated successfully.</div>
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

    <div class="edit-panel">
        <div class="edit-header">
            <div class="edit-title">Edit Car</div>
            <span class="car-id-badge">ID #<?= $car['id'] ?></span>
        </div>
        <div class="edit-body">
            <form method="POST" enctype="multipart/form-data" id="edit-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Make *</label>
                        <input type="text" name="make" value="<?= htmlspecialchars($car['make']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Model *</label>
                        <input type="text" name="model" value="<?= htmlspecialchars($car['model']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Year *</label>
                        <input type="number" name="year" value="<?= $car['year'] ?>" min="1900" max="2030" required>
                    </div>
                    <div class="form-group">
                        <label>Price (USD) *</label>
                        <input type="number" name="price" value="<?= $car['price'] ?>" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Fuel Type *</label>
                        <select name="fuel" required style="width:100%; padding:0.75rem 2.5rem 0.75rem 1rem; background:#0a0a0a; border:1px solid #2a2a2a; border-radius:6px; color:var(--text-main); font-size:0.9rem; font-family:'Inter',sans-serif; cursor:pointer; appearance:none; -webkit-appearance:none; background-image:url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23666\' stroke-width=\'2\'%3E%3Cpath d=\'M6 9l6 6 6-6\'/%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 14px center;">
                            <option value="Petrol" <?= (($car['fuel'] ?? 'Petrol') === 'Petrol') ? 'selected' : '' ?>>Petrol</option>
                            <option value="Diesel" <?= (($car['fuel'] ?? '') === 'Diesel') ? 'selected' : '' ?>>Diesel</option>
                            <option value="Electric" <?= (($car['fuel'] ?? '') === 'Electric') ? 'selected' : '' ?>>Electric</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Transmission *</label>
                        <select name="transmission" required style="width:100%; padding:0.75rem 2.5rem 0.75rem 1rem; background:#0a0a0a; border:1px solid #2a2a2a; border-radius:6px; color:var(--text-main); font-size:0.9rem; font-family:'Inter',sans-serif; cursor:pointer; appearance:none; -webkit-appearance:none; background-image:url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23666\' stroke-width=\'2\'%3E%3Cpath d=\'M6 9l6 6 6-6\'/%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 14px center;">
                            <option value="Automatic" <?= (($car['transmission'] ?? 'Automatic') === 'Automatic') ? 'selected' : '' ?>>Automatic</option>
                            <option value="Manual" <?= (($car['transmission'] ?? '') === 'Manual') ? 'selected' : '' ?>>Manual</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Engine *</label>
                        <input type="text" name="engine" id="engine" placeholder="e.g. 2.0" value="<?= htmlspecialchars($car['engine'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Horsepower (HP) *</label>
                        <input type="number" name="hp" id="hp" value="<?= (int)($car['hp'] ?? 0) ?>" required>
                    </div>

                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description"><?= htmlspecialchars($car['description']) ?></textarea>
                    </div>

                    <div class="form-group full" style="position: relative;">
                        <label>Car Options / Features</label>
                        <div id="options-box" style="
                            background:#0a0a0a;
                            border:1px solid #2a2a2a;
                            border-radius:6px;
                            padding:0.75rem 1rem;
                            cursor:pointer;
                            display:flex;
                            align-items:center;
                            justify-content:space-between;
                            transition:border-color 0.2s;
                            user-select:none;
                        " onclick="toggleOptionsDropdown(event)">
                            <span id="options-placeholder" style="color:#555; font-size:0.9rem;">Click to select options...</span>
                            <span style="color:#555; font-size:0.75rem;">▼</span>
                        </div>

                        <div id="options-dropdown" style="
                            display:none;
                            position:absolute;
                            top:100%;
                            left:0;
                            right:0;
                            z-index:100;
                            background:#0e0e0e;
                            border:1px solid #2a2a2a;
                            border-radius:6px;
                            margin-top:4px;
                            padding:0.75rem;
                            flex-wrap:wrap;
                            gap:8px;
                            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
                        ">
                            <?php
                            $car_option_list = [
                                'Heated Seats','Ventilated Seats','Leather Seats','Sunroof','Panoramic Roof',
                                'Navigation System','Bluetooth','Backup Camera','Parking Sensors','Cruise Control',
                                'Adaptive Cruise Control','Keyless Entry','Push Button Start','Premium Sound System',
                                'Apple CarPlay','Android Auto','Blind Spot Monitor','Lane Departure Warning',
                                'Remote Start','Power Liftgate','Third Row Seating','All Wheel Drive',
                                'Heated Steering Wheel','Tow Package','Alloy Wheels'
                            ];

                            $saved_options = !empty($car['car_options']) ? explode(',', $car['car_options']) : [];
                            $saved_options = array_map('trim', $saved_options);

                            foreach ($car_option_list as $opt):
                                $opt_selected = in_array($opt, $saved_options);
                            ?>
                                <span class="option-item" data-value="<?= $opt ?>" onclick="toggleOption(this)" data-selected="<?= $opt_selected ? 'true' : 'false' ?>" style="
                                    display:inline-block;
                                    padding:0.4rem 0.8rem;
                                    background: <?= $opt_selected ? 'rgba(212,175,55,0.15)' : '#1a1a1a' ?>;
                                    border:1px solid <?= $opt_selected ? 'var(--gold)' : '#2a2a2a' ?>;
                                    color: <?= $opt_selected ? 'var(--gold)' : '#888' ?>;
                                    border-radius:20px;
                                    font-size:0.8rem;
                                    cursor:pointer;
                                    transition:all 0.15s;
                                    user-select:none;
                                "><?= $opt ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div id="options-chips" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;"></div>
                        <div id="options-hidden-inputs"></div>
                        <span style="font-size:0.75rem; color:#555; margin-top:4px; display:block;">Click an option to select it, click again to remove</span>
                    </div>

                    <div class="form-group">
                        <label>Mileage</label>
                        <div style="position:relative; display:flex; align-items:center;">
                            <input type="text" name="mileage" id="mileage-input" placeholder="e.g. 110.120"
                                   inputmode="numeric"
                                   value="<?= htmlspecialchars($car['mileage'] ?? '') ?>"
                                   style="width:100%;">
                            <span style="position:absolute; right:12px; color:#555; font-size:0.8rem; pointer-events:none;">mi</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Accidents</label>
                        <select name="accidents" style="width:100%; padding:0.75rem 2.5rem 0.75rem 1rem; background:#0a0a0a; border:1px solid #2a2a2a; border-radius:6px; color:var(--text-main); font-size:0.9rem; font-family:'Inter',sans-serif; cursor:pointer; appearance:none; -webkit-appearance:none; background-image:url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23666\' stroke-width=\'2\'%3E%3Cpath d=\'M6 9l6 6 6-6\'/%3E%3C/svg%3E'); background-repeat:no-repeat; background-position:right 14px center;">
                            <option value="0" <?= (($car['accidents'] ?? 0) == 0) ? 'selected' : '' ?>>No accidents</option>
                            <option value="1" <?= (($car['accidents'] ?? 0) == 1) ? 'selected' : '' ?>>Yes — has accident history</option>
                        </select>
                    </div>

                    <div class="form-group full" style="position: relative;">
                        <label>Parts Changed</label>
                        <div id="parts-box" style="
                            background:#0a0a0a;
                            border:1px solid #2a2a2a;
                            border-radius:6px;
                            padding:0.75rem 1rem;
                            cursor:pointer;
                            display:flex;
                            align-items:center;
                            justify-content:space-between;
                            transition:border-color 0.2s;
                            user-select:none;
                        " onclick="togglePartsDropdown(event)">
                            <span id="parts-placeholder" style="color:#555; font-size:0.9rem;">Click to select parts...</span>
                            <span style="color:#555; font-size:0.75rem;">▼</span>
                        </div>

                        <div id="parts-dropdown" style="
                            display:none;
                            position:absolute;
                            top:100%;
                            left:0;
                            right:0;
                            z-index:100;
                            background:#0e0e0e;
                            border:1px solid #2a2a2a;
                            border-radius:6px;
                            margin-top:4px;
                            padding:0.75rem;
                            flex-wrap:wrap;
                            gap:8px;
                            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
                            ">
                            <?php
                            $part_options = [
                                'Front bumper','Rear bumper','Hood','Trunk lid',
                                'Front left door','Front right door','Rear left door','Rear right door',
                                'Left fender','Right fender','Windshield','Rear window',
                                'Left headlight','Right headlight','Left taillight','Right taillight',
                                'Engine','Gearbox','Airbags','Catalytic converter'
                            ];

                            $saved_parts = !empty($car['parts_changed']) ? explode(',', $car['parts_changed']) : [];
                            $saved_parts = array_map('trim', $saved_parts);

                            foreach ($part_options as $part):
                                $is_selected = in_array($part, $saved_parts);
                            ?>
                                <span class="part-option" data-value="<?= $part ?>" onclick="togglePart(this)" data-selected="<?= $is_selected ? 'true' : 'false' ?>" style="
                                    display:inline-block;
                                    padding:0.4rem 0.8rem;
                                    background: <?= $is_selected ? 'rgba(212,175,55,0.15)' : '#1a1a1a' ?>;
                                    border:1px solid <?= $is_selected ? 'var(--gold)' : '#2a2a2a' ?>;
                                    color: <?= $is_selected ? 'var(--gold)' : '#888' ?>;
                                    border-radius:20px;
                                    font-size:0.8rem;
                                    cursor:pointer;
                                    transition:all 0.15s;
                                    user-select:none;
                                "><?= $part ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div id="parts-chips" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;"></div>
                        <div id="parts-hidden-inputs"></div>
                        <span style="font-size:0.75rem; color:#555; margin-top:4px; display:block;">Click a part to select it, click again to remove</span>
                    </div>


                    <?php
                    $existing_images = [];
                    $img_q = $conn->prepare("SELECT * FROM car_images WHERE car_id = ? ORDER BY sort_order ASC");
                    $img_q->bind_param('i', $id);
                    $img_q->execute();
                    $img_res = $img_q->get_result();
                    while ($img_row = $img_res->fetch_assoc()) $existing_images[] = $img_row;
                    $img_q->close();
                    ?>
                    <div class="form-group full">
                        <label>Car Images</label>

                        <?php if (!empty($existing_images)): ?>
                        <div style="margin-bottom:12px;">
                            <div style="font-size:0.75rem; color:#555; margin-bottom:8px;">Current images — click the ✕ to delete one</div>
                            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:10px;">
                                <?php foreach ($existing_images as $img): ?>
                                <div style="position:relative; border-radius:6px; overflow:hidden; border:1px solid #2a2a2a; aspect-ratio:4/3;" id="img-wrap-<?= $img['id'] ?>">
                                    <img src="../<?= htmlspecialchars($img['image_path']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                    <?php if ($img['is_primary']): ?>
                                        <span style="position:absolute;top:5px;left:5px;background:var(--gold);color:#000;font-size:0.65rem;font-weight:700;padding:2px 7px;border-radius:10px;">MAIN</span>
                                    <?php endif; ?>
                                    <button type="button" onclick="deleteImage(<?= $img['id'] ?>, <?= $id ?>)" style="position:absolute;top:5px;right:5px;background:rgba(0,0,0,0.7);border:none;color:#fff;width:22px;height:22px;border-radius:50%;cursor:pointer;font-size:0.75rem;display:flex;align-items:center;justify-content:center;">✕</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <label class="new-upload-label">Add more images (optional)</label>
                        <label class="file-input-wrap" id="file-label" style="cursor:pointer; display:block;">
                            <input type="file" name="images[]" id="new-image-input" accept="image/*" multiple style="display:none;">
                            <span id="file-name-label">Click to choose images...</span>
                        </label>
                        <div id="new-preview-grid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:10px; margin-top:10px;"></div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save" id="save-btn">Save Changes</button>
                    <a href="dashboard.php?section=manage" class="btn-cancel-edit">Cancel</a>
                    <button type="button" class="btn-sold" id="sold-btn" onclick="confirmSold()"
                        <?= ($car['sold'] ?? 0) ? 'disabled' : '' ?>>
                        <?= ($car['sold'] ?? 0) ? '✓ Sold' : '🏷️ Mark as Sold' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Sold Confirm Modal -->
<div id="sold-modal" style="
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.85);
    z-index:9999;
    align-items:center;
    justify-content:center;
    animation: toastFadeIn 0.3s ease-out;
">
    <div style="
        background:#111;
        border:1px solid rgba(212,175,55,0.3);
        border-radius:16px;
        padding:2.5rem 3rem;
        text-align:center;
        max-width:420px;
        width:90%;
        animation: toastSlideUp 0.3s ease-out;
    ">
        <div style="
            width:64px; height:64px;
            border-radius:50%;
            background:rgba(212,175,55,0.12);
            border:2px solid var(--gold);
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 auto 1.2rem;
            animation: toastPop 0.5s 0.1s ease-out both;
        ">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--gold)" style="width:30px;height:30px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
        </div>
        <div style="font-size:1.5rem; font-weight:700; color:#fff; margin-bottom:0.5rem;">Mark as Sold?</div>
        <div style="color:#888; font-size:0.9rem; margin-bottom:2rem;">
            Are you sure you want to mark this car as sold?<br>
            It will appear as <span style="color:var(--gold); font-weight:600;">SOLD</span> on the website and will be automatically removed after 2 hours.
        </div>
        <div style="display:flex; gap:0.75rem; justify-content:center;">
            <button onclick="closeSoldModal()" style="
                padding:0.65rem 1.5rem;
                background:transparent;
                border:1px solid #333;
                color:#888;
                border-radius:6px;
                cursor:pointer;
                font-family:'Inter',sans-serif;
                font-size:0.9rem;
            ">No, keep it</button>
            <a id="sold-confirm-btn" href="#" style="
                padding:0.65rem 1.5rem;
                background:var(--gold);
                border:none;
                color:#000;
                border-radius:6px;
                cursor:pointer;
                font-weight:700;
                font-family:'Inter',sans-serif;
                font-size:0.9rem;
                text-decoration:none;
                display:inline-flex;
                align-items:center;
            ">Yes, mark as sold</a>
        </div>
    </div>
<script>
let selectedParts = [];
let selectedOptions = [];

document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.part-option').forEach(el => {
        if (el.getAttribute('data-selected') === 'true') {
            selectedParts.push(el.dataset.value);
        }
    });
    // Render chips and generate hidden form elements right away on load
    renderChips();
    updateHiddenInputs();
    updatePlaceholder();

    document.querySelectorAll('.option-item').forEach(el => {
        if (el.getAttribute('data-selected') === 'true') {
            selectedOptions.push(el.dataset.value);
        }
    });
    renderOptionChips();
    updateOptionsHiddenInputs();
    updateOptionsPlaceholder();
    
    const mileageInput = document.getElementById('mileage-input');
    if(mileageInput && mileageInput.value) {
        formatMileage(mileageInput);
    }
});

function togglePartsDropdown(e) {
    if(e) e.stopPropagation(); 
    const dd = document.getElementById('parts-dropdown');
    const box = document.getElementById('parts-box');
    const isOpen = dd.style.display === 'flex';
    
    dd.style.display = isOpen ? 'none' : 'flex';
    box.style.borderColor = isOpen ? '#2a2a2a' : 'var(--gold)';
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
    container.innerHTML = '';
    selectedParts.forEach(part => {
        const chip = document.createElement('span');
        chip.style.cssText = `
            display:inline-flex; align-items:center; gap:5px;
            padding:0.3rem 0.75rem;
            background:rgba(212,175,55,0.12);
            border:1px solid rgba(212,175,55,0.3);
            border-radius:20px;
            font-size:0.78rem;
            color:var(--gold);
        `;
        chip.innerHTML = `${part} <span onclick="removeChip('${part}', event)" style="cursor:pointer; font-size:0.9rem; opacity:0.7; line-height:1;">✕</span>`;
        container.appendChild(chip);
    });
}    

function removeChip(val, e) {
    if(e) e.stopPropagation(); 
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
    ph.textContent = selectedParts.length > 0
        ? selectedParts.length + ' part(s) selected'
        : 'Click to select parts...';
    ph.style.color = selectedParts.length > 0 ? '#ccc' : '#555';
}
function toggleOptionsDropdown(e) {
    if(e) e.stopPropagation();
    const dd = document.getElementById('options-dropdown');
    const box = document.getElementById('options-box');
    const isOpen = dd.style.display === 'flex';

    dd.style.display = isOpen ? 'none' : 'flex';
    box.style.borderColor = isOpen ? '#2a2a2a' : 'var(--gold)';
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
    container.innerHTML = '';
    selectedOptions.forEach(opt => {
        const chip = document.createElement('span');
        chip.style.cssText = `
            display:inline-flex; align-items:center; gap:5px;
            padding:0.3rem 0.75rem;
            background:rgba(212,175,55,0.12);
            border:1px solid rgba(212,175,55,0.3);
            border-radius:20px;
            font-size:0.78rem;
            color:var(--gold);
        `;
        chip.innerHTML = `${opt} <span onclick="removeOptionChip('${opt}', event)" style="cursor:pointer; font-size:0.9rem; opacity:0.7; line-height:1;">✕</span>`;
        container.appendChild(chip);
    });
}

function removeOptionChip(val, e) {
    if(e) e.stopPropagation();
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
    ph.textContent = selectedOptions.length > 0
        ? selectedOptions.length + ' option(s) selected'
        : 'Click to select options...';
    ph.style.color = selectedOptions.length > 0 ? '#ccc' : '#555';
}

document.addEventListener('click', function(e) {
    const box = document.getElementById('parts-box');
    const dd  = document.getElementById('parts-dropdown');
    if (box && dd && !box.contains(e.target) && !dd.contains(e.target)) {
        dd.style.display = 'none';
        box.style.borderColor = '#2a2a2a';
    }

    const obox = document.getElementById('options-box');
    const odd  = document.getElementById('options-dropdown');
    if (obox && odd && !obox.contains(e.target) && !odd.contains(e.target)) {
        odd.style.display = 'none';
        obox.style.borderColor = '#2a2a2a';
    }
});

function formatMileage(input) {
    let raw = input.value.replace(/\./g, '').replace(/\D/g, '');
    if (raw.length > 3) {
        raw = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    input.value = raw;
}

const mileageInput = document.getElementById('mileage-input');
if(mileageInput) {
    mileageInput.addEventListener('input', function() {
        formatMileage(this);
    });
}

document.getElementById('new-image-input').addEventListener('change', function() {
    const grid = document.getElementById('new-preview-grid');
    grid.innerHTML = '';
    const label = document.getElementById('file-name-label');
    label.textContent = this.files.length + ' image(s) selected';
    Array.from(this.files).slice(0, 10).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'position:relative; border-radius:6px; overflow:hidden; border:1px solid #2a2a2a; aspect-ratio:4/3;';
            wrap.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;">`;
            grid.appendChild(wrap);
        };
        reader.readAsDataURL(file);
    });
});

function deleteImage(imgId, carId) {
    Swal.fire({
        title: 'Delete this image?',
        text: "This image will be permanently removed.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel',
        confirmButtonColor: 'var(--gold)',
        cancelButtonColor: '#222',
        background: '#111',
        color: '#fff',
        iconColor: 'var(--gold)',
        customClass: {
            popup: 'swal2-custom-dark-popup',
            confirmButton: 'swal2-custom-confirm-btn'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Proceed with your existing AJAX delete logic if confirmed
            fetch(`edit.php?delete_image=${imgId}&car_id=${carId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('img-wrap-' + imgId).remove();
                        
                        // Optional: Show a subtle success toast
                        Swal.fire({
                            title: 'Deleted!',
                            text: 'The image has been removed.',
                            icon: 'success',
                            background: '#111',
                            color: '#fff',
                            confirmButtonColor: 'var(--gold)',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to delete image.',
                            icon: 'error',
                            background: '#111',
                            color: '#fff',
                            confirmButtonColor: 'var(--gold)'
                        });
                    }
                });
        }
    });
}

document.getElementById('edit-form').addEventListener('submit', function() {
    const bar = document.getElementById('loading-bar');
    const btn = document.getElementById('save-btn');
    btn.textContent = 'Saving...';
    btn.disabled = true;
    let w = 0;
    setInterval(() => { w = Math.min(w + 15, 85); bar.style.width = w + '%'; }, 200);
});
// ── Auto-close edit toast ───────────────────────────
const editToast = document.getElementById('edit-toast');
if (editToast) {
    setTimeout(() => {
        editToast.style.transition = 'opacity 0.4s';
        editToast.style.opacity = '0';
        setTimeout(() => editToast.remove(), 400);
    }, 3000);
    editToast.addEventListener('click', () => {
        editToast.style.transition = 'opacity 0.3s';
        editToast.style.opacity = '0';
        setTimeout(() => editToast.remove(), 300);
    });
}
function confirmSold() {
    const modal = document.getElementById('sold-modal');
    modal.style.display = 'flex';
    document.getElementById('sold-confirm-btn').href = 'sold.php?id=<?= $car['id'] ?>';
}

function closeSoldModal() {
    document.getElementById('sold-modal').style.display = 'none';
}

// Close on background click
document.getElementById('sold-modal').addEventListener('click', function(e) {
    if (e.target === this) closeSoldModal();
});
</script>
</body>
</html>
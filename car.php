<?php
require_once 'db_connect.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$car = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$car) { header('Location: index.php'); exit; }

// Fetch all images
$img_q = $conn->prepare("SELECT image_path FROM car_images WHERE car_id = ? ORDER BY sort_order ASC");
$img_q->bind_param('i', $id);
$img_q->execute();
$img_res = $img_q->get_result();
$images = [];
while ($row = $img_res->fetch_assoc()) $images[] = $row['image_path'];
$img_q->close();
if (empty($images)) $images[] = $car['image_path'];

$title = htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']);
$price = number_format($car['price'], 2);
$mileage = htmlspecialchars(trim($car['mileage'] ?? ''));
$accident = (int)($car['accidents'] ?? 0);
$desc = htmlspecialchars($car['description'] ?? '');
$fuel = htmlspecialchars($car['fuel'] ?? '');
$transmission = htmlspecialchars($car['transmission'] ?? '');
$parts = $car['parts_changed'] ?? '';
if (isset($car['engine']) && trim($car['engine']) !== '') {
    $engine_val = trim($car['engine']);
    // If it's a numeric string (like '3' or '3.0'), format it explicitly to 1 decimal place
    if (is_numeric($engine_val)) {
        $engine = number_format((float)$engine_val, 1);
    } else {
        $engine = htmlspecialchars($engine_val);
    }
} else {
    $engine = '';
}
$hp = (int)($car['hp'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> | Premium Auto</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Orbitron:wght@800&family=Syne:wght@800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .car-page {
            max-width: 1100px;
            margin: 7rem auto 3rem;
            padding: 0 2rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #666;
            text-decoration: none;
            font-size: 0.875rem;
            margin-bottom: 2rem;
            transition: color 0.2s;
        }
        .back-link span {
            position: relative;
            padding-bottom: 2px;
        }
        .back-link span::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--gold);
            transform: scaleX(0);
            transform-origin: bottom left;
            transition: transform 0.3s ease;
        }
        .back-link:hover { 
            color: var(--gold); 
        }
        .back-link:hover span::after {
            transform: scaleX(1);
        }

        .car-layout {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 2.5rem;
            align-items: start;
        }

        /* ── Gallery ── */
        .gallery { position: sticky; top: 110px; }

        .main-img-wrap {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: #111;
            aspect-ratio: 4/3;
            margin-bottom: 10px;
        }

        .main-img-wrap img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: opacity 0.35s;
        }

        .gallery-arrow {
            position: absolute;
            top: 50%; transform: translateY(-50%);
            background: rgba(0,0,0,0.6);
            border: none; color: #fff;
            width: 38px; height: 38px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.1rem;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
            z-index: 5;
        }

        .gallery-arrow:hover { background: rgba(212,175,55,0.85); color: #000; }
        .gallery-arrow.prev  { left: 12px; }
        .gallery-arrow.next  { right: 12px; }

        .img-count-badge {
            position: absolute;
            bottom: 12px; right: 12px;
            background: rgba(0,0,0,0.65);
            color: #fff;
            font-size: 0.75rem;
            padding: 3px 10px;
            border-radius: 12px;
        }

        .thumbs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .thumb {
            width: 72px; height: 52px;
            border-radius: 6px;
            overflow: hidden;
            border: 2px solid transparent;
            cursor: pointer;
            transition: border-color 0.2s;
            flex-shrink: 0;
        }

        .thumb.active { border-color: var(--gold); }
        .thumb img { width: 100%; height: 100%; object-fit: cover; }

        /* ── Info panel ── */
        .info-panel {
            background: #111;
            border: 1px solid #1e1e1e;
            border-radius: 12px;
            padding: 2rem;
        }

        .car-page-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.4rem;
            line-height: 1.2;
        }

        .car-page-price {
            font-size: 2rem;
            color: var(--gold);
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .divider {
            border: none;
            border-top: 1px solid #1e1e1e;
            margin: 1.25rem 0;
        }

        /* Status badges */
        .status-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0.35rem 0.9rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-clean    { background: rgba(46,204,113,0.12); border: 1px solid rgba(46,204,113,0.3); color: #2ecc71; }
        .badge-accident { background: rgba(231,76,60,0.12);  border: 1px solid rgba(231,76,60,0.3);  color: #e74c3c; }
        .badge-dot      { width: 7px; height: 7px; border-radius: 50%; }
        .dot-green      { background: #2ecc71; }
        .dot-red        { background: #e74c3c; }

        /* Spec rows */
        .specs { display: flex; flex-direction: column; gap: 0; }

        .spec-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.7rem 0;
            border-bottom: 1px solid #161616;
            font-size: 0.875rem;
        }

        .spec-row:last-child { border-bottom: none; }

        .spec-label {
            color: #555;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .spec-label svg { width: 15px; height: 15px; stroke: #444; }
        .spec-value { color: var(--text-main); font-weight: 500; }

        /* Description */
        .section-title {
            font-size: 0.7rem;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 0.6rem;
        }

        .car-page-desc {
            color: #888;
            font-size: 0.875rem;
            line-height: 1.7;
        }

        /* Parts changed */
        .parts-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .part-chip {
            padding: 0.3rem 0.75rem;
            background: rgba(212,175,55,0.08);
            border: 1px solid rgba(212,175,55,0.2);
            border-radius: 20px;
            font-size: 0.78rem;
            color: #c4a030;
        }

        /* Contact CTA */
        .cta-box {
            background: rgba(212,175,55,0.06);
            border: 1px solid rgba(212,175,55,0.15);
            border-radius: 10px;
            padding: 1.2rem;
            text-align: center;
            margin-top: 1.5rem;
        }

        .cta-box p {
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 0.75rem;
        }

        .btn-contact {
            display: block;
            width: 100%;
            padding: 0.85rem;
            background: var(--gold);
            color: #000;
            border: none;
            border-radius: 7px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-contact:hover { background: var(--gold-hover); }

        @media (max-width: 900px) {
            .car-layout { grid-template-columns: 1fr; }
            .gallery { position: static; }
        }
        .main-img-wrap {
            cursor: zoom-in;
        }

        .lightbox {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.92);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .lightbox.open {
            display: flex;
        }

        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            border-radius: 6px;
            transition: opacity 0.2s;
        }

        .lightbox-close {
            position: absolute;
            top: 20px; right: 30px;
            background: none;
            border: none;
            color: #fff;
            font-size: 2.5rem;
            cursor: pointer;
            line-height: 1;
            z-index: 10000;
        }

        .lightbox-close:hover { color: var(--gold); }

        .lightbox-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.1);
            border: none;
            color: #fff;
            width: 50px; height: 50px;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            z-index: 10000;
        }

        .lightbox-arrow:hover { background: rgba(212,175,55,0.85); color: #000; }
        .lightbox-prev { left: 24px; }
        .lightbox-next { right: 24px; }

        .lightbox-counter {
            position: absolute;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.6);
            color: #fff;
            font-size: 0.85rem;
            padding: 5px 14px;
            border-radius: 14px;
        }

        @media (max-width: 600px) {
            .lightbox-arrow { width: 40px; height: 40px; font-size: 1.2rem; }
            .lightbox-close { font-size: 2rem; top: 12px; right: 16px; }
        }
        .btn-view-options {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 1rem;
            padding: 0.85rem;
            background: transparent;
            border: 1px solid rgba(212,175,55,0.3);
            color: var(--gold);
            border-radius: 7px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s, border-color 0.2s;
        }

        .btn-view-options:hover {
            background: rgba(212,175,55,0.08);
            border-color: var(--gold);
        }

        .btn-view-options svg { width: 18px; height: 18px; }
    </style>
</head>
<body>

<header>
    <div class="logo"><a href="index.php">Premium Auto</a></div>
    <nav class="nav-links">
        <a href="index.php">Home</a>
    </nav>
</header>

<div class="car-page">
    <a href="index.php" class="back-link">← <span>Back to all cars</span></a>

    <div class="car-layout">

        <!-- ── Gallery ── -->
        <div class="gallery">
            <div class="main-img-wrap">
                <img src="<?= htmlspecialchars($images[0]) ?>" alt="<?= $title ?>" id="main-photo">
                <?php if (count($images) > 1): ?>
                    <button class="gallery-arrow prev" onclick="changePhoto(-1)">&#8249;</button>
                    <button class="gallery-arrow next" onclick="changePhoto(1)">&#8250;</button>
                    <div class="img-count-badge">
                        <span id="photo-cur">1</span>/<?= count($images) ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($images) > 1): ?>
            <div class="thumbs" id="thumbs">
                <?php foreach ($images as $i => $img): ?>
                    <div class="thumb <?= $i === 0 ? 'active' : '' ?>" onclick="setPhoto(<?= $i ?>)">
                        <img src="<?= htmlspecialchars($img) ?>" alt="Photo <?= $i+1 ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Info panel ── -->
        <div class="info-panel">
            <div class="car-page-title"><?= $title ?></div>
            <div class="car-page-price">$<?= $price ?></div>

            <!-- Accident status badge -->
            <div class="status-row">
                <?php if ($accident): ?>
                    <span class="status-badge badge-accident">
                        <span class="badge-dot dot-red"></span> Has accident history
                    </span>
                <?php else: ?>
                    <span class="status-badge badge-clean">
                        <span class="badge-dot dot-green"></span> No accidents
                    </span>
                <?php endif; ?>
            </div>

            <!-- Specs -->
            <div class="specs">
                <div class="spec-row">
                    <span class="spec-label">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                        Year
                    </span>
                    <span class="spec-value"><?= (int)$car['year'] ?></span>
                </div>
                <div class="spec-row">
                    <span class="spec-label">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                        Make
                    </span>
                    <span class="spec-value"><?= htmlspecialchars($car['make']) ?></span>
                </div>
                <div class="spec-row">
                    <span class="spec-label">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                        Model
                    </span>
                    <span class="spec-value"><?= htmlspecialchars($car['model']) ?></span>
                </div>
                <?php if ($mileage): ?>
                <div class="spec-row">
                    <span class="spec-label">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Mileage
                    </span>
                    <span class="spec-value"><?= $mileage ?> mi</span>
                </div>
                <?php endif; ?>

                <?php if ($fuel): ?>
                <div class="spec-row">
                    <span class="spec-label">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                        Fuel
                    </span>
                    <span class="spec-value"><?= $fuel ?></span>
                </div>
                <?php endif; ?>

                <?php if ($transmission): ?>
                <div class="spec-row">
                    <span class="spec-label">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Transmission
                    </span>
                    <span class="spec-value"><?= $transmission ?></span>
                </div>
                <?php endif; ?>

                <?php if ($engine): ?>
                <div class="spec-row">
                    <span class="spec-label">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A1.5 1.5 0 0019.5 21l2.25-2.25a1.5 1.5 0 000-2.12l-5.83-5.83m-4.5 4.5V13.5M11.42 15.17l2.12-2.12m-2.12 2.12a5.25 5.25 0 01-7.42-7.42l4.24 4.24a1.5 1.5 0 002.12 0l1.41-1.42a1.5 1.5 0 000-2.12L5.34 2.12a5.25 5.25 0 017.42 7.42l-1.34 1.34m1.34-1.34l2.12-2.12" /></svg>
                        Engine
                    </span>
                    <span class="spec-value"><?= $engine ?></span>
                </div>
                <?php endif; ?>

                <?php if ($hp): ?>
                <div class="spec-row">
                    <span class="spec-label">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.25 8.25 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a4.5 4.5 0 016.362-4.386z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        Horsepower
                    </span>
                    <span class="spec-value"><?= $hp ?> hp</span>
                </div>
                <?php endif; ?>

                <div class="spec-row">
                    <span class="spec-label">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                        Photos
                    </span>
                    <span class="spec-value"><?= count($images) ?></span>
                </div>
                </div>

                <?php if (!empty($car['car_options'])): ?>
                <a href="options.php?id=<?= $id ?>" class="btn-view-options">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    View Options & Features
                </a>
                <?php endif; ?>

            <?php if ($desc): ?>
            <hr class="divider">
            <div class="section-title">Description</div>
            <p class="car-page-desc"><?= $desc ?></p>
            <?php endif; ?>

            <?php if ($parts): ?>
            <hr class="divider">
            <div class="section-title">Parts Changed</div>
            <div class="parts-grid">
                <?php foreach (explode(', ', $parts) as $part): ?>
                    <span class="part-chip"><?= htmlspecialchars(trim($part)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="cta-box">
                <p>Interested in this vehicle? Contact us for more information.</p>
                <a href="tel:+1234567890" class="btn-contact">📞 Call Dealer</a>
            </div>
        </div>

    </div>
    <!-- Fullscreen Image Lightbox -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox(event)">
        <button class="lightbox-close" onclick="closeLightbox(event)">&times;</button>
        <button class="lightbox-arrow lightbox-prev" onclick="lightboxNav(-1, event)">&#8249;</button>
        <img id="lightbox-img" src="" alt="">
        <button class="lightbox-arrow lightbox-next" onclick="lightboxNav(1, event)">&#8250;</button>
        <div class="lightbox-counter"><span id="lightbox-cur">1</span>/<?= count($images) ?></div>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date("Y"); ?> Premium Auto Dealership. All rights reserved.</p>
</footer>

<script>
const images = <?= json_encode($images) ?>;
let currentIdx = 0;

function setPhoto(idx) {
    currentIdx = idx;
    document.getElementById('main-photo').style.opacity = '0';
    setTimeout(() => {
        document.getElementById('main-photo').src = images[idx];
        document.getElementById('main-photo').style.opacity = '1';
    }, 150);

    const counter = document.getElementById('photo-cur');
    if (counter) counter.textContent = idx + 1;

    document.querySelectorAll('.thumb').forEach((t, i) => {
        t.classList.toggle('active', i === idx);
    });
}

function changePhoto(dir) {
    setPhoto((currentIdx + dir + images.length) % images.length);
}

// Keyboard arrow navigation
document.addEventListener('keydown', e => {
    if (document.getElementById('lightbox').classList.contains('open')) return;
    if (e.key === 'ArrowLeft')  changePhoto(-1);
    if (e.key === 'ArrowRight') changePhoto(1);
});
let lightboxIdx = 0;

document.getElementById('main-photo').addEventListener('click', () => {
    openLightbox(currentIdx);
});

function openLightbox(idx) {
    lightboxIdx = idx;
    document.getElementById('lightbox-img').src = images[idx];
    document.getElementById('lightbox-cur').textContent = idx + 1;
    document.getElementById('lightbox').classList.add('open');
}

function closeLightbox(e) {
    if (e.target.id === 'lightbox' || e.target.classList.contains('lightbox-close')) {
        document.getElementById('lightbox').classList.remove('open');
    }
}

function lightboxNav(dir, e) {
    e.stopPropagation();
    lightboxIdx = (lightboxIdx + dir + images.length) % images.length;
    document.getElementById('lightbox-img').src = images[lightboxIdx];
    document.getElementById('lightbox-cur').textContent = lightboxIdx + 1;
}

document.addEventListener('keydown', e => {
    if (!document.getElementById('lightbox').classList.contains('open')) return;
    if (e.key === 'Escape') document.getElementById('lightbox').classList.remove('open');
    if (e.key === 'ArrowLeft') lightboxNav(-1, {stopPropagation:()=>{}});
    if (e.key === 'ArrowRight') lightboxNav(1, {stopPropagation:()=>{}});
});
</script>
</body>
</html>
<?php
require_once 'db_connect.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $conn->prepare("SELECT make, model, year, car_options, image_path FROM cars WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$car = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$car) { header('Location: index.php'); exit; }

$title = htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']);
$options = !empty($car['car_options']) ? array_map('trim', explode(',', $car['car_options'])) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> — Options & Features | Premium Auto</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .options-page {
            max-width: 800px;
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

        .options-header {
            margin-bottom: 2rem;
        }

        .options-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.4rem;
        }

        .options-header p {
            color: #555;
            font-size: 0.9rem;
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }

        .option-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #111;
            border: 1px solid #1e1e1e;
            border-radius: 10px;
            padding: 1rem 1.2rem;
            transition: border-color 0.2s, transform 0.2s;
        }

        .option-card:hover {
            border-color: rgba(212,175,55,0.3);
            transform: translateY(-2px);
        }

        .option-icon {
            width: 36px; height: 36px;
            border-radius: 8px;
            background: rgba(212,175,55,0.1);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .option-icon svg {
            width: 18px; height: 18px;
            stroke: var(--gold);
        }

        .option-name {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-main);
        }

        .empty-options {
            text-align: center;
            color: #444;
            padding: 3rem;
        }
    </style>
</head>
<body>

<header>
    <div class="logo"><a href="index.php">Premium Auto</a></div>
    <nav class="nav-links">
        <a href="index.php">Home</a>
    </nav>
</header>

<div class="options-page">
    <a href="car.php?id=<?= $id ?>" class="back-link">← <span>Back to <?= $title ?></span></a>

    <div class="options-header">
        <h1><?= $title ?></h1>
        <p>Options & Features included with this vehicle</p>
    </div>

    <?php if (!empty($options)): ?>
    <div class="options-grid">
        <?php foreach ($options as $opt): ?>
            <div class="option-card">
                <div class="option-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </div>
                <div class="option-name"><?= htmlspecialchars($opt) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-options">No additional options listed for this vehicle.</div>
    <?php endif; ?>
</div>

<footer>
    <p>&copy; <?php echo date("Y"); ?> Premium Auto Dealership. All rights reserved.</p>
</footer>

</body>
</html>
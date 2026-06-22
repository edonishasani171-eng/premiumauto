<?php
require_once 'db_connect.php';
// Auto-delete cars sold more than 2 hours ago
$conn->query("DELETE FROM cars WHERE sold = 1 AND sold_at IS NOT NULL AND sold_at <= NOW() - INTERVAL 2 HOUR");

$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM car_images WHERE car_id = c.id) as img_count
        FROM cars c ORDER BY c.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Auto | Luxury Car Dealership</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ── Image slider ── */
        .car-img-container { position: relative; }

        .slider-btn {
            position: absolute;
            top: 50%; transform: translateY(-50%);
            background: rgba(0,0,0,0.55);
            border: none; color: #fff;
            width: 28px; height: 28px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 0.85rem;
            display: flex; align-items: center; justify-content: center;
            z-index: 10;
            transition: background 0.2s;
            opacity: 0;
        }

        .car-card:hover .slider-btn { opacity: 1; }
        .slider-btn:hover { background: rgba(212,175,55,0.8); color: #000; }
        .slider-btn.prev { left: 8px; }
        .slider-btn.next { right: 8px; }

        .img-dots {
            position: absolute;
            bottom: 8px; left: 50%;
            transform: translateX(-50%);
            display: flex; gap: 5px;
        }

        .img-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: rgba(255,255,255,0.4);
            cursor: pointer;
            transition: background 0.2s;
        }

        .img-dot.active { background: var(--gold); }

        .img-counter {
            position: absolute;
            top: 8px; right: 8px;
            background: rgba(0,0,0,0.6);
            color: #fff;
            font-size: 0.65rem;
            padding: 2px 7px;
            border-radius: 10px;
        }

        /* ── Badges above image ── */
        .car-badges {
            position: absolute;
            top: 8px; left: 8px;
            display: flex; gap: 5px;
            z-index: 5;
        }

        .badge-item {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge-accident {
            background: rgba(231,76,60,0.85);
            color: #fff;
        }

        .badge-clean {
            background: rgba(46,204,113,0.85);
            color: #fff;
        }

        .badge-sold {
            background: var(--gold);
            color: #fff;
            font-size: 0.75rem;
            padding: 3px 10px;
        }

        /* ── Car info pills ── */
        .car-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 0.6rem 0 0.8rem;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0.25rem 0.65rem;
            background: rgba(255,255,255,0.04);
            border: 1px solid #2a2a2a;
            border-radius: 20px;
            font-size: 0.75rem;
            color: #aaa;
        }

        .meta-pill svg { width: 12px; height: 12px; stroke: #666; flex-shrink: 0; }

        /* ── Parts changed ── */
        .parts-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 0.5rem;
        }

        .part-tag {
            padding: 0.15rem 0.55rem;
            background: rgba(212,175,55,0.08);
            border: 1px solid rgba(212,175,55,0.2);
            border-radius: 10px;
            font-size: 0.68rem;
            color: #a88a2a;
        }

        .parts-label {
            font-size: 0.72rem;
            color: #555;
            margin-top: 0.6rem;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── View details button ── */
        .btn-view {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.55rem 1.2rem;
            background: var(--gold);
            color: #000;
            border-radius: 5px;
            font-weight: 700;
            font-size: 0.82rem;
            text-decoration: none;
            transition: background 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-view:hover { background: var(--gold-hover); }

        .car-desc { margin-top: 0; }
        /* ── Advanced search ── */
        .search-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            animation: fadeInUp 1s ease-out 0.6s backwards;
        }

        .search-row {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            width: 100%;
            max-width: 800px;
        }

        .search-select {
            padding: 0.8rem 1.2rem;
            border-radius: 30px;
            border: 1px solid var(--gold);
            background-color: var(--bg-card);
            color: var(--text-main);
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            outline: none;
            cursor: pointer;
            transition: box-shadow 0.3s, opacity 0.3s;
            min-width: 160px;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23d4af37' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
        }

        .search-select:focus {
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
        }

        .search-select option {
            background: #151515;
            color: var(--text-main);
        }

        .search-select.hidden {
            display: none;
        }

        .search-input {
            padding: 0.8rem 1.5rem;
            border-radius: 30px;
            border: 1px solid var(--gold);
            background-color: var(--bg-card);
            color: var(--text-main);
            width: 100%;
            max-width: 500px;
            outline: none;
            transition: box-shadow 0.3s;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
        }

        .search-input:focus {
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
        }

        /* ── Search dropdown ── */
        .search-box-wrap {
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }

        .search-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            left: 0; right: 0;
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 14px;
            overflow: hidden;
            z-index: 999;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
            max-height: 420px;
            overflow-y: auto;
        }

        .search-dropdown.open { display: block; }

        .dropdown-section-label {
            padding: 0.6rem 1rem 0.3rem;
            font-size: 0.65rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #444;
            border-bottom: 1px solid #1a1a1a;
        }

        .dropdown-car-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.7rem 1rem;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
            border-bottom: 1px solid #161616;
        }

        .dropdown-car-item:last-child { border-bottom: none; }

        .dropdown-car-item:hover {
            background: rgba(212,175,55,0.06);
        }

        .dropdown-car-item.highlighted {
            background: rgba(212,175,55,0.1);
        }

        .drop-thumb {
            width: 56px; height: 40px;
            border-radius: 6px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid #2a2a2a;
        }

        .drop-info { flex: 1; min-width: 0; }

        .drop-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .drop-meta {
            font-size: 0.72rem;
            color: #555;
            margin-top: 2px;
        }

        .drop-price {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--gold);
            white-space: nowrap;
        }

        .drop-empty {
            padding: 1.5rem;
            text-align: center;
            color: #444;
            font-size: 0.875rem;
        }

        .drop-accident-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .dot-red   { background: #e74c3c; }
        .dot-green { background: #2ecc71; }

        /* scrollbar */
        .search-dropdown::-webkit-scrollbar { width: 4px; }
        .search-dropdown::-webkit-scrollbar-track { background: #111; }
        .search-dropdown::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 2px; }

        .btn-clear-search {
            padding: 0.8rem 1.4rem;
            border-radius: 30px;
            border: 1px solid #444;
            background: transparent;
            color: #888;
            font-size: 0.85rem;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            display: none;
        }

        .btn-clear-search.visible { display: block; }
        .btn-clear-search:hover { border-color: var(--gold); color: var(--gold); }
        /* ── Year filter pills ── */
        .year-filter-wrap {
            display: none;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 10px;
            max-width: 500px;
            width: 100%;
        }

        .year-filter-wrap.visible { display: flex; }

        .year-pill {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            border: 1px solid #333;
            background: transparent;
            color: #888;
            font-size: 0.8rem;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .year-pill:hover { border-color: var(--gold); color: var(--gold); }

        .year-pill.active {
            background: rgba(212,175,55,0.15);
            border-color: var(--gold);
            color: var(--gold);
            font-weight: 600;
        }

        .filter-status {
            width: 100%;
            text-align: center;
            font-size: 0.78rem;
            color: #555;
            margin-top: 4px;
        }
        .hero h1 {
            animation: heroFadeUp 0.9s ease-out 0.1s backwards;
        }
        .hero p {
            animation: heroFadeUp 0.9s ease-out 0.35s backwards;
        }
        @keyframes heroFadeUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .shimmer-text {
            font-family: 'Syne', sans-serif;
            background: linear-gradient(90deg, var(--gold) 20%, #fff7da 50%, var(--gold) 80%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
            animation: shimmer 3.5s linear infinite;
        }
        @keyframes shimmer {
            to { background-position: -200% center; }
        }
        .scroll-indicator {
            position: absolute;
            bottom: 28px; left: 50%;
            transform: translateX(-50%);
            color: var(--gold);
            width: 48px; height: 48px;
            z-index: 3;
            animation: bounceDown 2s ease-in-out infinite;
            opacity: 0.85;
            transition: opacity 0.2s;
            background: rgba(212,175,55,0.1);
            border: 1px solid rgba(212,175,55,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
        }

        .scroll-indicator svg {
            width: 24px;
            height: 24px;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .scroll-indicator:hover {
            opacity: 1;
            background: rgba(212,175,55,0.18);
            box-shadow: 0 0 18px rgba(212,175,55,0.3);
        }

        .scroll-indicator:hover svg {
            transform: translateY(4px);
        }

        .scroll-indicator:active {
            animation: clickPop 0.4s ease-out forwards;
        }

        @keyframes clickPop {
            0%   { transform: translateX(-50%) scale(1); }
            30%  { transform: translateX(-50%) scale(0.85); }
            60%  { transform: translateX(-50%) scale(1.15); }
            100% { transform: translateX(-50%) scale(1); }
        }

        @keyframes bounceDown {
            0%, 100% { transform: translate(-50%, 0); }
            50%      { transform: translate(-50%, 10px); }
        }
        .car-card {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out, box-shadow 0.3s, border-color 0.3s;
        }
        .car-card.in-view {
            opacity: 1;
            transform: translateY(0);
        }
        .car-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 45px rgba(212,175,55,0.15);
            border-color: rgba(212,175,55,0.3);
        }
        .car-img-container { overflow: hidden; }
        .car-image {
            transition: transform 0.5s ease;
        }
        .car-card:hover .car-image {
            transform: scale(1.08);
        }
        .badge-clean, .badge-accident {
            animation: badgePulse 2.5s ease-in-out infinite;
        }
        @keyframes badgePulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); }
            50%      { box-shadow: 0 0 0 5px rgba(255,255,255,0.08); }
        }
        .slide-title {
            opacity: 0;
            transform: translateX(-120px);
            transition: opacity 0.8s ease-out, transform 0.8s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .slide-title.in-view {
            opacity: 1;
            transform: translateX(0);
        }
        .slide-subtitle {
            opacity: 0;
            transform: translateX(120px);
            transition: opacity 0.8s ease-out 0.15s, transform 0.8s cubic-bezier(0.25, 0.8, 0.25, 1) 0.15s;
        }
        .slide-subtitle.in-view {
            opacity: 1;
            transform: translateX(0);
        }
        #scroll-top-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 44px;
            height: 44px;
            background: var(--gold);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s, transform 0.3s, background 0.2s, box-shadow 0.3s, width 0.3s, border-radius 0.3s;
            z-index: 999;
            overflow: hidden;
        }

        #scroll-top-btn svg {
            width: 20px;
            height: 20px;
            stroke: #000;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        #scroll-top-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at center, rgba(255,255,255,0.25) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        #scroll-top-btn:hover {
            background: var(--gold-hover);
            box-shadow: 0 0 0 6px rgba(212,175,55,0.2), 0 8px 25px rgba(212,175,55,0.4);
            transform: translateY(-4px) scale(1.12);
        }

        #scroll-top-btn:hover svg {
            transform: translateY(-3px);
        }

        #scroll-top-btn:hover::before {
            opacity: 1;
        }

        #scroll-top-btn:active {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 0 0 3px rgba(212,175,55,0.2), 0 4px 12px rgba(212,175,55,0.3);
        }

        #scroll-top-btn.visible {
            opacity: 1;
            transform: translateY(0);
        }

        #scroll-top-btn.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ── Responsive Header Layout & Mobile Pull-Down ── */
        .navbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 15px 20px;
            position: relative;
            box-sizing: border-box;
        }

        /* Base styles for the 2-line menu button - hidden on desktop */
        .nav-toggle-btn {
            display: none;
            background: transparent;
            border: none;
            width: 30px;
            height: 18px; /* Lowered height so the spans stay closely tied and don't push the layout box down */
            cursor: pointer;
            flex-direction: column;
            justify-content: space-between; /* Evenly spaces the 2 lines within the 18px box */
            padding: 0;
            z-index: 2100;
        }

        .nav-toggle-btn span {
            display: block;
            width: 100%;
            height: 3px;
            background-color: var(--gold, #d4af37);
            border-radius: 2px;
            transition: all 0.3s ease-in-out;
        }

        /* ── Mobile and Tablet Specific Styling (768px and smaller) ── */
        @media (max-width: 768px) {
            .nav-toggle-btn {
                display: flex; /* Show the 2 lines button on small screens */
            }

            /* 2-Line Toggle Animation into X */
            .nav-toggle-btn.menu-is-open span:nth-child(1) {
                transform: translateY(7.5px) rotate(45deg);
            }
            .nav-toggle-btn.menu-is-open span:nth-child(2) {
                transform: translateY(-7.5px) rotate(-45deg);
            }

            /* Turn nav-links into a clean pull-down menu */
            header .nav-links {
                position: absolute;
                top: 100%; 
                left: 0;
                width: 100%;
                background: rgba(15, 15, 15, 0.98); 
                border-bottom: 2px solid var(--gold, #d4af37);
                flex-direction: column;
                align-items: center;
                gap: 0;
                padding: 0;
                
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.4s cubic-bezier(0.25, 1, 0.5, 1), padding 0.4s ease;
            }

            /* Active class triggered by JavaScript to drop down smoothly */
            header .nav-links.open {
                max-height: 220px; /* Slides open to full view */
                padding: 15px 0;
            }

            header .nav-links a {
                width: 100%;
                text-align: center;
                padding: 14px 0;
                font-size: 1.1rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.02);
            }
            header .nav-links a:last-child {
                border-bottom: none;
            }
        }
    </style>
</head>
<body>

<header class="header-transparent">
    <div class="navbar-container">
        <div class="logo"><a href="index.php">Premium Auto</a></div>
        
        <button class="nav-toggle-btn" onclick="toggleMobileMenu()">
            <span></span>
            <span></span>
        </button>

        <nav class="nav-links">
            <a href="#inventory-section" class="nav-item">CARS</a>
            <a href="index.php" class="nav-item">Location</a>
            <a href="admin/login.php" class="nav-item">Login</a>
        </nav>
    </div>
</header>

<section class="hero" style="position:relative; overflow:hidden; margin: top 7.5rem;">

    <!-- Background video -->
    <div class="video-container" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; overflow: hidden;">
    <video id="video-primary" autoplay muted playsinline
           style="
               position:absolute;
               top:0; left:0;
               width:100%; height:100%;
               object-fit:cover;
               object-position: center 40%;
               transition: opacity 0.8s ease-in-out;
               opacity: 0.7;
               z-index: 2;
           "
           src="videos/hero1.mp4">
    </video>

    <video id="video-secondary" muted playsinline
           style="
               position:absolute;
               top:0; left:0;
               width:100%; height:100%;
               object-fit:cover;
               object-position: center 40%;
               transition: opacity 0.8s ease-in-out;
               opacity: 0;
               z-index: 1;
           ">
    </video>
</div>

    <!-- Dark gradient overlay -->
    <div style="
        position:absolute;
        inset:0;
        background: linear-gradient(180deg, rgba(0,0,0,0.2) 0%, rgba(10,10,10,0.75) 100%);
        z-index:1;
    "></div>

    <!-- All content sits above video -->
    <div style="position:relative; z-index:2; padding-top: 7rem;">

    <h1>Find Your Dream Car</h1>
    <p>Explore our exclusive collection of premium vehicles, handpicked for excellence and luxury.</p>

    <div class="search-wrapper">
        <div class="search-row" style="flex-direction:column; align-items:center;">
            <div class="search-box-wrap">
                <input type="text" id="search-input" class="search-input"
                       placeholder="Search by make, model, or year..."
                       autocomplete="off">
                <div class="search-dropdown" id="search-dropdown"></div>
            </div>

            <!-- Permanent filter row -->
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:center; margin-top:6px;">
                <select id="year-select" onchange="selectYearFilter()" style="
                    padding: 0.65rem 1.2rem;
                    border-radius: 30px;
                    border: 1px solid var(--gold);
                    background: var(--bg-card);
                    color: var(--text-main);
                    font-size: 0.85rem;
                    font-family: 'Inter', sans-serif;
                    outline: none;
                    cursor: pointer;
                    appearance: none;
                    -webkit-appearance: none;
                    background-image: url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23d4af37\' stroke-width=\'2\'%3E%3Cpath d=\'M6 9l6 6 6-6\'/%3E%3C/svg%3E');
                    background-repeat: no-repeat;
                    background-position: right 14px center;
                    padding-right: 36px;
                    min-width: 150px;
                ">
                    <option value="">All Years</option>
                    <?php for ($y = 2026; $y >= 2000; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>

                <button class="btn-clear-search" id="clear-btn" onclick="clearFilters()">✕ Clear</button>
                
                <select id="fuel-select" onchange="selectFuelFilter()" style="
                    padding: 0.65rem 1.2rem;
                    border-radius: 30px;
                    border: 1px solid var(--gold);
                    background: var(--bg-card);
                    color: var(--text-main);
                    font-size: 0.85rem;
                    font-family: 'Inter', sans-serif;
                    outline: none;
                    cursor: pointer;
                    appearance: none;
                    -webkit-appearance: none;
                    background-image: url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23d4af37\' stroke-width=\'2\'%3E%3Cpath d=\'M6 9l6 6 6-6\'/%3E%3C/svg%3E');
                    background-repeat: no-repeat;
                    background-position: right 14px center;
                    padding-right: 36px;
                    min-width: 150px;
                    ">
                    <option value="">All Fuel Types</option>
                    <option value="Petrol">Petrol</option>
                    <option value="Diesel">Diesel</option>
                    <option value="Electric">Electric</option>
                </select>
            </div>

            <div id="filter-status" style="font-size:0.75rem; color:#555; margin-top:6px; min-height:18px;" ></div>
        </div>
    </div>
   </div>

    <a href="#inventory-section" class="scroll-indicator" aria-label="Scroll down">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
    </a>
</div>
</section>

<main class="cars-container" id="inventory-section">
    <div style="text-align:center; margin-bottom:2rem;">
        <h2 class="slide-title" style="font-family: 'Syne', sans-serif; font-size: 2.4rem; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; color: var(--text-main);">
            HERE IS SOME OF OUR <span class="shimmer-text">CARS</span>
        </h2>
        <p class="slide-subtitle" style="color:#555; font-size:1rem; margin-top:0.4rem;">Browse our current inventory — click any car to see full details</p>
    </div>
    <div class="cars-grid">
        <?php
        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $car_id   = (int)$row['id'];
                $make     = htmlspecialchars($row['make']);
                $model    = htmlspecialchars($row['model']);
                $year     = (int)$row['year'];
                $title    = "$year $make $model";
                $price    = number_format($row['price'], 2);
                $desc     = htmlspecialchars($row['description']);
                // Inside your while/foreach loop in index.php where you read each car row:
                // Read the mileage data directly from the row string
                $mileage = htmlspecialchars(trim($row['mileage'] ?? ''));

                $accident = (int)($row['accidents'] ?? 0);
                $sold = (int)($row['sold'] ?? 0);
                $fuel_type = htmlspecialchars($row['fuel'] ?? '');
                $parts    = htmlspecialchars($row['parts_changed'] ?? '');

                // Fetch all images for this car
                $img_q = $conn->prepare("SELECT image_path FROM car_images WHERE car_id = ? ORDER BY sort_order ASC");
                $img_q->bind_param('i', $car_id);
                $img_q->execute();
                $img_res = $img_q->get_result();
                $images = [];
                while ($img_row = $img_res->fetch_assoc()) {
                    $images[] = htmlspecialchars($img_row['image_path']);
                }
                $img_q->close();

                // Fallback to main image if car_images table is empty for this car
                if (empty($images)) {
                    $images[] = htmlspecialchars($row['image_path']);
                }

                $img_count = count($images);
                $imgs_json = json_encode($images);
        ?>
       <div class="car-card" data-make="<?= $make ?>" data-model="<?= $model ?>" data-year="<?= $year ?>" data-sold="<?= $sold ?>" data-fuel="<?= $fuel_type ?>"
        <?php if (!$sold): ?>
            onclick="window.location.href='car.php?id=<?= $car_id ?>'" style="cursor: pointer;"
        <?php else: ?>
            style="cursor: default; pointer-events: none; filter: grayscale(60%) brightness(0.6);"
        <?php endif; ?>>

            <!-- Image slider -->
            <div class="car-img-container">
                <!-- Badges over image -->
                <div class="car-badges">
                    <?php if ($sold): ?>
                        <span class="badge-item badge-sold">SOLD</span>
                    <?php elseif ($accident): ?>
                        <span class="badge-item badge-accident">Has Accident</span>
                    <?php else: ?>
                        <span class="badge-item badge-clean">Clean</span>
                    <?php endif; ?>
                </div>

                <?php if ($img_count > 1): ?>
                    <button class="slider-btn prev" onclick="slideImg(this, -1)">&#8249;</button>
                    <button class="slider-btn next" onclick="slideImg(this, 1)">&#8250;</button>
                    <span class="img-counter">
                        <span class="cur-idx">1</span>/<?= $img_count ?>
                    </span>
                    <div class="img-dots">
                        <?php for ($d = 0; $d < $img_count; $d++): ?>
                            <span class="img-dot <?= $d === 0 ? 'active' : '' ?>"></span>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

                <img src="<?= $images[0] ?>"
                     alt="<?= $title ?>"
                     class="car-image"
                     loading="lazy"
                     data-images="<?= htmlspecialchars($imgs_json) ?>"
                     data-idx="0">
            </div>

            <!-- Car details -->
            <div class="car-details">
                <h2 class="car-title"><?= $title ?></h2>
                <div class="car-price">$<?= $price ?></div>

                <!-- Info pills -->
                <div class="car-meta">
                    <?php if ($mileage): ?>
                    <span class="meta-pill">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?= $mileage ?> mi
                    </span>
                    <?php endif; ?>
                    <span class="meta-pill">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <?= $accident ? 'Accident history' : 'No accidents' ?>
                    </span>
                    <?php if ($img_count > 1): ?>
                    <span class="meta-pill">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                        <?= $img_count ?> photos
                    </span>
                    <?php endif; ?>
                </div>

                <?php if ($desc): ?>
                    <p class="car-desc"><?= $desc ?></p>
                <?php endif; ?>

                <?php if ($parts): ?>
                    <div class="parts-label">Parts changed</div>
                    <div class="parts-list">
                        <?php foreach (explode(', ', $parts) as $part): ?>
                            <span class="part-tag"><?= htmlspecialchars(trim($part)) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
            endwhile;
        else:
        ?>
            <div class="no-cars">No cars available at the moment. Please check back later!</div>
        <?php endif; ?>
    </div>
</main>

<footer>
    <p>&copy; <?php echo date("Y"); ?> Premium Auto Dealership. All rights reserved.</p>
    <button id="scroll-top-btn" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Back to top">
    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
    </svg>
</button>
</footer>

<script src="js/main.js"></script>
<script>
function slideImg(btn, dir) {
    const container = btn.closest('.car-img-container');
    const img       = container.querySelector('.car-image');
    const images    = JSON.parse(img.dataset.images);
    let idx         = parseInt(img.dataset.idx);

    idx = (idx + dir + images.length) % images.length;
    img.src          = images[idx];
    img.dataset.idx  = idx;

    // Update counter
    const counter = container.querySelector('.cur-idx');
    if (counter) counter.textContent = idx + 1;

    // Update dots
    container.querySelectorAll('.img-dot').forEach((dot, i) => {
        dot.classList.toggle('active', i === idx);
    });
}

// Dot click
document.querySelectorAll('.img-dot').forEach((dot, dotIdx) => {
    dot.addEventListener('click', () => {
        const container = dot.closest('.car-img-container');
        const img       = container.querySelector('.car-image');
        const images    = JSON.parse(img.dataset.images);

        img.src         = images[dotIdx];
        img.dataset.idx = dotIdx;

        const counter = container.querySelector('.cur-idx');
        if (counter) counter.textContent = dotIdx + 1;

        container.querySelectorAll('.img-dot').forEach((d, i) => {
            d.classList.toggle('active', i === dotIdx);
        });
    });
});
// ── Brand → Model data from PHP ─────────────────────
<?php
$all_cars_q = $conn->query("SELECT DISTINCT make, model, year FROM cars ORDER BY make, model, year DESC");
$car_data = [];
while ($r = $all_cars_q->fetch_assoc()) {
    $make  = $r['make'];
    $model = $r['model'];
    $year  = $r['year'];
    if (!isset($car_data[$make])) $car_data[$make] = [];
    if (!isset($car_data[$make][$model])) $car_data[$make][$model] = [];
    $car_data[$make][$model][] = $year;
}
$drop_q   = $conn->query("SELECT id, make, model, year, price, image_path, mileage, accidents FROM cars ORDER BY make ASC");
$drop_arr = [];
while ($r = $drop_q->fetch_assoc()) {
    $mileage_data = $r['mileage'] ?? '';
    
    // Maintain dots if present, format if it's a raw integer row
    if (strpos($mileage_data, '.') !== false) {
        $r['mileage'] = $mileage_data;
    } else {
        $raw_m = (int)$mileage_data;
        $r['mileage'] = $raw_m > 0 ? number_format($raw_m, 0, ',', '.') : '';
    }
    
    $drop_arr[] = $r;
}
?>
const carData = <?= json_encode($car_data) ?>;
const allCars  = <?= json_encode($drop_arr) ?>;

const searchInput    = document.getElementById('search-input');
const searchDropdown = document.getElementById('search-dropdown');
let ddHighlight = -1;

searchInput.addEventListener('focus', () => {
    renderDropdown(allCars);
    searchDropdown.classList.add('open');
});

searchInput.addEventListener('input', () => {
    const q = searchInput.value.toLowerCase().trim();

    const filtered = q
        ? allCars.filter(c =>
            c.make.toLowerCase().includes(q) ||
            c.model.toLowerCase().includes(q) ||
            String(c.year).includes(q))
        : allCars;

    renderDropdown(filtered);
    searchDropdown.classList.add('open');
    ddHighlight = -1;

    filterCars();
    toggleClearBtn();
});

// Keyboard navigation in dropdown
searchInput.addEventListener('keydown', e => {
    // Select either active search items OR the brand shortcut rows
    const items = searchDropdown.querySelectorAll('.dropdown-car-item, .dropdown-brand-shortcut');
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        ddHighlight = Math.min(ddHighlight + 1, items.length - 1);
        updateHighlight(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        ddHighlight = Math.max(ddHighlight - 1, -1);
        updateHighlight(items);
    } else if (e.key === 'Enter' && ddHighlight >= 0) {
        e.preventDefault();
        items[ddHighlight]?.click();
    } else if (e.key === 'Escape') {
        searchDropdown.classList.remove('remove');
    }
});

function updateHighlight(items) {
    items.forEach((item, i) => item.classList.toggle('highlighted', i === ddHighlight));
    if (ddHighlight >= 0) items[ddHighlight]?.scrollIntoView({ block: 'nearest' });
}

function renderDropdown(cars) {
    if (cars.length === 0) {
        searchDropdown.innerHTML = '<div class="drop-empty">No brands match your search.</div>';
        return;
    }

    searchDropdown.innerHTML = '';

    // Group the filtered cars by brand
    const brands = {};
    cars.forEach(car => {
        if (!brands[car.make]) brands[car.make] = [];
        brands[car.make].push(car);
    });

    // Render ONLY the brand rows with the corrected logo path
    Object.keys(brands).sort().forEach(brand => {
        const header = document.createElement('div');
        header.className = 'dropdown-brand-shortcut';
        header.style.cssText = `
            padding: 0.8rem 1.2rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gold, #d4af37);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border-bottom: 1px solid #1a1a1a;
            background: transparent;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 0.2s, color 0.2s;
        `;

        // CORRECTED PATH: Looking directly inside the "logos" folder
        const logoFileName = brand.toLowerCase().trim() + '.png';
        const logoPath = 'logos/' + logoFileName; 

        header.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <img src="${logoPath}" 
                     alt="${brand}" 
                     style="width: 24px; height: 24px; object-fit: contain; flex-shrink: 0;"
                     onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%23d4af37\'><path d=\'M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5 0 .67 1.5 1.5-.67 1.5-1.5zm-11-4.5l1.39-4h10.22l1.4 4H6.5z\'/></svg>';">
                <span>${brand}</span>
            </div>
            <span style="color: #666; font-size: 0.8rem; font-weight: 400;">${brands[brand].length} available</span>
        `;
        
        // Hover layout styling
        header.addEventListener('mouseenter', () => {
            header.style.background = 'rgba(212, 175, 55, 0.08)';
        });
        header.addEventListener('mouseleave', () => {
            header.style.background = 'transparent';
        });
        
        // Clicking copies the brand name to the input and filters the page
        header.addEventListener('click', (e) => {
            e.stopPropagation();
            searchInput.value = brand;
            searchDropdown.classList.remove('open');
            filterCars();
            toggleClearBtn();
        });
        
        searchDropdown.appendChild(header);
    });
}
function selectYearFilter() {
    activeYear = document.getElementById('year-select').value;
    filterCars();
    updateFilterStatus();
    toggleClearBtn();
}
function selectFuelFilter() {
    activeFuel = document.getElementById('fuel-select').value;
    filterCars();
    updateFilterStatus();
    toggleClearBtn();
}

function updateFilterStatus() {
    const status = document.getElementById('filter-status');
    const brand  = searchInput.value.trim();
    const year   = activeYear;
    const fuel   = activeFuel;

    if (!brand && !year && !fuel) { status.textContent = ''; return; }

    const count = document.querySelectorAll('.car-card:not([style*="display: none"]):not([style*="display:none"])').length;

    let msg = `Showing ${count} vehicle${count !== 1 ? 's' : ''}`;
    if (brand) msg += ` · Brand: ${brand}`;
    if (year)  msg += ` · Year: ${year}`;
    if (fuel)  msg += ` · Fuel: ${fuel}`;
    status.textContent = msg;
}

function appendCarItem(car) {
    const title    = `${car.year} ${car.make} ${car.model}`;
    const price    = '$' + Number(car.price).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    const mileage  = car.mileage ? `${car.mileage} mi · ` : '';
    const accident = car.accidents == 1;

    const item     = document.createElement('a');
    item.href      = `car.php?id=${car.id}`;
    item.className = 'dropdown-car-item';
    item.innerHTML = `
        <img src="${car.image_path}" alt="${title}" class="drop-thumb">
        <div class="drop-info">
            <div class="drop-title">${title}</div>
            <div class="drop-meta">${mileage}${accident ? '⚠ Has accident' : '✓ Clean'}</div>
        </div>
        <div class="drop-price">${price}</div>
    `;
    searchDropdown.appendChild(item);
}

// Close dropdown when clicking outside
document.addEventListener('click', e => {
    const wrap = document.querySelector('.search-box-wrap');
    if (wrap && !wrap.contains(e.target)) {
        searchDropdown.classList.remove('open');
        ddHighlight = -1;
    }
});
let activeYear = '';
let activeFuel = '';

function filterCars() {
    const text    = searchInput.value.toLowerCase().trim();
    const year    = document.getElementById('year-select')?.value || '';
    const fuel    = document.getElementById('fuel-select')?.value || '';
    const cards   = document.querySelectorAll('.car-card');
    let   visible = 0;

    cards.forEach(card => {
        const cardMake  = (card.dataset.make  || '').toLowerCase();
        const cardModel = (card.dataset.model || '').toLowerCase();
        const cardYear  = (card.dataset.year  || '');
        const cardFuel  = (card.dataset.fuel  || '');
        const cardTitle = (card.querySelector('.car-title')?.innerText || '').toLowerCase();

        const matchText = !text || cardTitle.includes(text) || cardMake.includes(text) || cardModel.includes(text) || cardYear.includes(text);
        const matchYear = !year || cardYear === year;
        const matchFuel = !fuel || cardFuel === fuel;

        const show = matchText && matchYear && matchFuel;
        card.style.display = show ? 'block' : 'none';
        if (show) visible++;
    });

    let empty = document.getElementById('no-search-results');
    if (visible === 0) {
        if (!empty) {
            empty = document.createElement('div');
            empty.id = 'no-search-results';
            empty.className = 'no-cars';
            empty.innerText = 'No cars match your search.';
            document.querySelector('.cars-grid').appendChild(empty);
        }
        empty.style.display = 'block';
    } else if (empty) {
        empty.style.display = 'none';
    }

    updateFilterStatus();
    toggleClearBtn();
}

function clearFilters() {
    searchInput.value = '';
    activeYear = '';
    activeFuel = '';
    document.getElementById('year-select').value = '';
    document.getElementById('fuel-select').value = '';
    searchDropdown.classList.remove('open');
    document.getElementById('filter-status').textContent = '';
    document.querySelectorAll('.car-card').forEach(c => c.style.display = 'block');
    const empty = document.getElementById('no-search-results');
    if (empty) empty.style.display = 'none';
    toggleClearBtn();
}

function toggleClearBtn() {
    const btn = document.getElementById('clear-btn');
    if(btn) btn.classList.toggle('visible', !!searchInput.value);
}
const header = document.querySelector('header');

    function checkScroll() {
        // If window is at the very top (0 pixels scrolled), make background disappear
        if (window.scrollY === 0) {
            header.classList.add('header-transparent');
        } else {
            // The moment you scroll down even 1px, pop the background back up smoothly
            header.classList.remove('header-transparent');
        }
    }

    // Run on initial page load
    checkScroll();

    // Run every time the user scrolls
    window.addEventListener('scroll', checkScroll);
// ── Scroll-reveal animation for car cards ───────────
const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const delay = (entry.target.dataset.revealIndex || 0) * 90;
            entry.target.style.transitionDelay = delay + 'ms';
            entry.target.classList.add('in-view');
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.15 });

document.querySelectorAll('.car-card').forEach((card, i) => {
    card.dataset.revealIndex = i % 3;
    revealObserver.observe(card);
});    
// ── Slide-in animation for "HERE IS SOME OF OUR CARS" ───
const titleObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('in-view');
            titleObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.3 });

document.querySelectorAll('.slide-title, .slide-subtitle').forEach(el => titleObserver.observe(el));

// ── Scroll to top button ─────────────────────────────
const scrollBtn = document.getElementById('scroll-top-btn');
window.addEventListener('scroll', () => {
    scrollBtn.classList.toggle('visible', window.scrollY > 400);
});
// Your playlist array
const videoPlaylist = [
    "videos/hero1.mp4",
    "videos/hero2.mp4"
];

let currentIndex = 0;

let activePlayer = document.getElementById('video-primary');
let nextPlayer = document.getElementById('video-secondary');

function setupVideoTransition(currentPlayer, bufferPlayer) {
    // CRUCIAL: Remove any old listeners so they don't fire early on the wrong player
    currentPlayer.onended = null;
    
    currentPlayer.onended = function() {
        // Calculate the next index
        currentIndex = (currentIndex + 1) % videoPlaylist.length;
        
        // Start playing the buffer video that was preloading
        bufferPlayer.play().then(() => {
            // Bring buffer player to the front and make it visible
            bufferPlayer.style.zIndex = "2";
            bufferPlayer.style.opacity = "0.7";
            
            // Push old player to back and hide it
            currentPlayer.style.zIndex = "1";
            currentPlayer.style.opacity = "0";
            
            // Wait for the opacity cross-fade to finish, then prepare the next video
            setTimeout(() => {
                const futureIndex = (currentIndex + 1) % videoPlaylist.length;
                currentPlayer.src = videoPlaylist[futureIndex];
                currentPlayer.load();
            }, 800);
            
            // Swap player roles for the next iteration
            activePlayer = bufferPlayer;
            nextPlayer = currentPlayer;
            
            // Recursively setup the listener for the newly active player
            setupVideoTransition(activePlayer, nextPlayer);
        }).catch(err => {
            console.log("Playback error:", err);
        });
    };
}

// Initial preloading of video 2
nextPlayer.src = videoPlaylist[(currentIndex + 1) % videoPlaylist.length];
nextPlayer.load();

// Run the engine
setupVideoTransition(activePlayer, nextPlayer);

// Function to handle opening/closing the mobile pull-down menu
function toggleMobileMenu() {
    const navLinks = document.querySelector('header .nav-links');
    const toggleBtn = document.querySelector('.nav-toggle-btn');

    // Smoothly expand/collapse menu height
    navLinks.classList.toggle('open');
    
    // Animate the 2 custom lines into an X icon
    toggleBtn.classList.toggle('menu-is-open');
}

// Auto-closes the navigation list when a menu item is clicked
document.querySelectorAll('header .nav-links a').forEach(link => {
    link.addEventListener('click', () => {
        const navLinks = document.querySelector('header .nav-links');
        const toggleBtn = document.querySelector('.nav-toggle-btn');
        
        navLinks.classList.remove('open');
        toggleBtn.classList.remove('menu-is-open');
    });
});
</script>
</body>
</html>
<?php
// ============================================================
// DATABASE CONFIGURATION & FETCHING
// ============================================================
require_once __DIR__ . '/../config/db.php';

$pdo = db();

$programs = [];
$loadError = null;

try {
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.program_name,
            p.program_type,
            p.description,
            p.start_date,
            p.end_date,
            p.status,
            p.partner_id,
            p.country_id,
            c.country_name,
            pr.institution_name
        FROM programs p
        LEFT JOIN countries c
            ON p.country_id = c.id
        LEFT JOIN partners pr
            ON p.partner_id = pr.id
        WHERE p.status = 'Active'
        ORDER BY p.id DESC
    ");

    $stmt->execute();
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log('browse_program.php query failed: ' . $ex->getMessage());
    $loadError = 'We ran into a problem loading programs. Please try again shortly.';
}

// Counts for sidebar / hero stats
$typeCounts = ['exchange' => 0, 'internship' => 0, 'research' => 0, 'training' => 0];
$countrySet = [];
$institutionSet = [];
foreach ($programs as $p) {
    $t = strtolower($p['program_type'] ?? '');
    if (isset($typeCounts[$t])) {
        $typeCounts[$t]++;
    }
    if (!empty($p['country_name'])) {
        $countrySet[$p['country_name']] = true;
    }
    if (!empty($p['institution_name'])) {
        $institutionSet[$p['institution_name']] = true;
    }
}
$totalPrograms = count($programs);
$totalCountries = count($countrySet);
$totalInstitutions = count($institutionSet);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>PSU Internalization — Student Programs Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* ===== DESIGN TOKENS ===== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --blue:        #1a4a8a;
      --blue-light:  #2a63b8;
      --blue-dark:   #133566;
      --blue-deep:   #0f2a52;
      --gold:        #e8b830;
      --gold-light:  #f2cc5e;
      --gold-dark:   #c99a1e;
      --bg:          #f0f2f5;
      --bg-alt:      #e8ebf0;
      --white:       #ffffff;
      --navy:        #1a2a44;
      --gray-700:    #3a4a5e;
      --gray-500:    #6b7a8f;
      --gray-400:    #94a3b8;
      --gray-300:    #cbd5e1;
      --gray-200:    #e2e8f0;
      --radius-sm:   8px;
      --radius:      12px;
      --radius-lg:   16px;
      --radius-xl:   24px;
      --shadow-sm:   0 1px 3px rgba(26,42,68,0.06);
      --shadow:      0 2px 12px rgba(26,42,68,0.08);
      --shadow-md:   0 4px 20px rgba(26,42,68,0.10);
      --shadow-lg:   0 8px 32px rgba(26,42,68,0.12);
      --shadow-xl:   0 24px 64px rgba(26,42,68,0.18);
      --font-body:    'Inter', sans-serif;
      --font-heading: 'Plus Jakarta Sans', sans-serif;
    }

    body {
      font-family: var(--font-body);
      background: var(--bg);
      color: var(--navy);
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
      scroll-behavior: smooth;
    }

    /* ===== NAVIGATION ===== */
    nav {
      background: var(--blue);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 48px;
      height: 72px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 20px rgba(26,74,138,0.25);
    }
    .nav-logo {
      font-family: var(--font-heading);
      color: var(--white);
      font-size: 1.35rem;
      font-weight: 800;
      letter-spacing: -0.5px;
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }
    .nav-logo::before {
      content: 'PSU';
      background: var(--gold);
      color: var(--blue-dark);
      font-size: 0.7rem;
      font-weight: 800;
      padding: 3px 8px;
      border-radius: 6px;
      letter-spacing: 0.5px;
    }
    .nav-logo span { color: var(--gold); font-weight: 700; }
    .nav-links { display: flex; align-items: center; gap: 32px; list-style: none; }
    .nav-links a {
      color: rgba(255,255,255,0.78);
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 500;
      transition: color 0.2s;
      position: relative;
    }
    .nav-links a:hover { color: var(--white); }
    .nav-links a:not(.nav-cta):not(.nav-cta-outline)::after {
      content: '';
      position: absolute;
      bottom: -4px;
      left: 0;
      width: 0;
      height: 2px;
      background: var(--gold);
      transition: width 0.25s;
      border-radius: 2px;
    }
    .nav-links a:not(.nav-cta):not(.nav-cta-outline):hover::after { width: 100%; }

    .nav-auth-group {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-left: 8px;
    }
    .nav-cta {
      background: var(--gold);
      color: var(--blue-dark) !important;
      padding: 10px 24px;
      border-radius: var(--radius-sm);
      font-weight: 700 !important;
      font-size: 0.9rem !important;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
      box-shadow: 0 2px 8px rgba(232,184,48,0.35);
      white-space: nowrap;
    }
    .nav-cta:hover {
      background: var(--gold-light) !important;
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(232,184,48,0.45);
    }
    .nav-cta-outline {
      background: transparent;
      color: var(--white) !important;
      padding: 9px 22px;
      border-radius: var(--radius-sm);
      font-weight: 600 !important;
      font-size: 0.9rem !important;
      border: 1.5px solid rgba(255,255,255,0.45);
      transition: background 0.2s, border-color 0.2s, transform 0.15s;
      white-space: nowrap;
    }
    .nav-cta-outline:hover {
      background: rgba(255,255,255,0.10);
      border-color: rgba(255,255,255,0.85);
      transform: translateY(-1px);
    }

    /* ===== HERO ===== */
    .hero {
      background: linear-gradient(135deg, var(--blue) 0%, var(--blue-light) 60%, var(--blue-dark) 100%);
      padding: 88px 40px 96px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      top: -80px;
      right: -60px;
      width: 320px;
      height: 320px;
      background: var(--gold);
      border-radius: 50%;
      opacity: 0.15;
      pointer-events: none;
    }
    .hero::after {
      content: '';
      position: absolute;
      bottom: -100px;
      left: -80px;
      width: 280px;
      height: 280px;
      background: var(--gold);
      border-radius: 50%;
      opacity: 0.12;
      pointer-events: none;
    }
    .hero-inner { position: relative; max-width: 720px; margin: 0 auto; z-index: 1; }
    .hero-eyebrow {
      display: inline-block;
      background: rgba(232,184,48,0.18);
      color: var(--gold);
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 1.8px;
      text-transform: uppercase;
      padding: 6px 16px;
      border-radius: 24px;
      margin-bottom: 24px;
      border: 1px solid rgba(232,184,48,0.25);
    }
    .hero h1 {
      font-family: var(--font-heading);
      font-size: clamp(2.2rem, 5vw, 3.2rem);
      color: var(--white);
      line-height: 1.15;
      margin-bottom: 18px;
      font-weight: 800;
      letter-spacing: -0.5px;
    }
    .hero h1 em { color: var(--gold); font-style: normal; }
    .hero p {
      color: rgba(255,255,255,0.72);
      font-size: 1.05rem;
      line-height: 1.65;
      margin-bottom: 40px;
      font-weight: 400;
    }

    /* ===== PART 1: SEARCH BAR ===== */
    .search-bar {
      display: flex;
      background: var(--white);
      border-radius: var(--radius-xl);
      overflow: hidden;
      box-shadow: 0 8px 32px rgba(10,30,60,0.25);
      max-width: 640px;
      margin: 0 auto;
    }
    .search-bar input {
      flex: 1;
      border: none;
      outline: none;
      padding: 18px 24px;
      font-family: var(--font-body);
      font-size: 0.95rem;
      color: var(--navy);
      background: transparent;
    }
    .search-bar input::placeholder { color: var(--gray-400); }
    .search-bar select {
      border: none;
      border-left: 1px solid var(--gray-200);
      outline: none;
      padding: 18px 16px;
      font-family: var(--font-body);
      font-size: 0.88rem;
      color: var(--gray-500);
      background: var(--white);
      cursor: pointer;
      font-weight: 500;
    }
    .search-bar button {
      background: var(--gold);
      color: var(--blue-dark);
      border: none;
      padding: 18px 32px;
      font-family: var(--font-body);
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s;
      white-space: nowrap;
    }
    .search-bar button:hover { background: var(--gold-light); }

    /* ===== STATS STRIP ===== */
    .stats-strip {
      display: flex;
      justify-content: center;
      gap: 48px;
      margin-top: 48px;
    }
    .stat {
      color: rgba(255,255,255,0.6);
      font-size: 0.85rem;
      text-align: center;
      font-weight: 500;
    }
    .stat strong {
      display: block;
      color: var(--white);
      font-size: 1.5rem;
      font-weight: 800;
      font-family: var(--font-heading);
      margin-bottom: 4px;
    }
    .stat strong::after {
      content: '';
      display: block;
      width: 24px;
      height: 3px;
      background: var(--gold);
      border-radius: 2px;
      margin: 6px auto 0;
    }

    /* ===== SECTION HEADERS ===== */
    .section-header {
      text-align: center;
      max-width: 640px;
      margin: 0 auto 56px;
    }
    .section-eyebrow {
      display: inline-block;
      background: rgba(26,74,138,0.08);
      color: var(--blue);
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 1.8px;
      text-transform: uppercase;
      padding: 6px 16px;
      border-radius: 24px;
      margin-bottom: 16px;
      border: 1px solid rgba(26,74,138,0.12);
    }
    .section-header h2 {
      font-family: var(--font-heading);
      font-size: clamp(1.6rem, 3vw, 2.2rem);
      font-weight: 800;
      color: var(--navy);
      line-height: 1.2;
      letter-spacing: -0.5px;
      margin-bottom: 14px;
    }
    .section-header h2 em { color: var(--blue); font-style: normal; }
    .section-header p {
      color: var(--gray-500);
      font-size: 1rem;
      line-height: 1.7;
    }

    /* ===== PART 5: PARTNER INSTITUTIONS SHOWCASE ===== */
    .institutions-section {
      max-width: 1120px;
      margin: 0 auto;
      padding: 80px 24px 40px;
      scroll-margin-top: 80px; /* Offsets the sticky navbar */
    }
    .institutions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 20px;
    }
    .institution-card {
      background: var(--white);
      border-radius: var(--radius);
      padding: 24px;
      box-shadow: var(--shadow);
      border: 1.5px solid var(--gray-200);
      cursor: pointer;
      transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s;
      display: flex;
      align-items: center;
      gap: 16px;
      position: relative;
    }
    .institution-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-md);
      border-color: var(--gold);
    }
    .institution-logo {
      width: 52px;
      height: 52px;
      border-radius: var(--radius-sm);
      background: linear-gradient(135deg, var(--blue) 0%, var(--blue-light) 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-heading);
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--white);
      flex-shrink: 0;
      box-shadow: 0 4px 12px rgba(26,74,138,0.2);
    }
    .institution-info { flex: 1; min-width: 0; }
    .institution-name {
      font-family: var(--font-heading);
      font-size: 0.92rem;
      font-weight: 700;
      color: var(--navy);
      line-height: 1.3;
      margin-bottom: 4px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .institution-meta {
      font-size: 0.78rem;
      color: var(--gray-500);
      font-weight: 500;
    }
    .institution-count {
      background: var(--bg);
      color: var(--blue);
      font-size: 0.72rem;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 20px;
      border: 1px solid var(--gray-200);
      flex-shrink: 0;
    }

    /* ===== HOW IT WORKS SECTION ===== */
    .how-section {
      max-width: 1120px;
      margin: 0 auto;
      padding: 60px 24px 80px;
      scroll-margin-top: 80px; /* Offsets the sticky navbar */
    }
    .how-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 24px;
      counter-reset: step;
    }
    .how-step {
      background: var(--white);
      border-radius: var(--radius);
      padding: 36px 28px 28px;
      box-shadow: var(--shadow);
      border: 1px solid var(--gray-200);
      position: relative;
      counter-increment: step;
      transition: transform 0.25s, box-shadow 0.25s;
    }
    .how-step:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-md);
    }
    .how-step::before {
      content: counter(step, decimal-leading-zero);
      position: absolute;
      top: -18px;
      left: 28px;
      background: var(--gold);
      color: var(--blue-dark);
      font-family: var(--font-heading);
      font-size: 0.95rem;
      font-weight: 800;
      width: 44px;
      height: 44px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(232,184,48,0.4);
      border: 3px solid var(--bg);
    }
    .how-step h3 {
      font-family: var(--font-heading);
      font-size: 1rem;
      font-weight: 700;
      color: var(--navy);
      margin-bottom: 8px;
      margin-top: 8px;
    }
    .how-step p {
      font-size: 0.87rem;
      color: var(--gray-500);
      line-height: 1.65;
    }

    /* ===== MAIN LAYOUT ===== */
    .page-body {
      max-width: 1120px;
      margin: 0 auto;
      padding: 56px 24px;
      display: grid;
      grid-template-columns: 256px 1fr;
      gap: 36px;
      align-items: start;
      scroll-margin-top: 80px;
    }

    /* ===== SIDEBAR FILTERS ===== */
    .sidebar {
      display: flex;
      flex-direction: column;
      gap: 20px;
      position: sticky;
      top: 96px;
    }
    .filter-card {
      background: var(--white);
      border-radius: var(--radius);
      padding: 24px;
      box-shadow: var(--shadow);
      border: 1px solid var(--gray-200);
    }
    .filter-card h3 {
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 1.2px;
      text-transform: uppercase;
      color: var(--blue);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .filter-card h3::before {
      content: '';
      width: 6px;
      height: 6px;
      background: var(--gold);
      border-radius: 50%;
      flex-shrink: 0;
    }
    .filter-group { display: flex; flex-direction: column; gap: 12px; }
    .filter-group label {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.9rem;
      cursor: pointer;
      color: var(--gray-700);
      transition: color 0.2s;
      font-weight: 500;
    }
    .filter-group label:hover { color: var(--blue); }
    .filter-group input[type=checkbox] {
      accent-color: var(--blue);
      width: 16px;
      height: 16px;
      cursor: pointer;
      border-radius: 4px;
    }
    .badge-count {
      margin-left: auto;
      background: var(--bg);
      color: var(--blue);
      font-size: 0.72rem;
      font-weight: 700;
      padding: 2px 10px;
      border-radius: 20px;
      border: 1px solid var(--gray-200);
    }
    .filter-card select {
      width: 100%;
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-sm);
      padding: 10px 14px;
      font-family: var(--font-body);
      font-size: 0.9rem;
      color: var(--navy);
      outline: none;
      cursor: pointer;
      background: var(--white);
      transition: border-color 0.2s;
      font-weight: 500;
    }
    .filter-card select:focus { border-color: var(--blue); }
    .clear-btn {
      width: 100%;
      background: none;
      border: 1.5px solid var(--gray-300);
      border-radius: var(--radius-sm);
      padding: 10px;
      font-family: var(--font-body);
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--gray-500);
      cursor: pointer;
      transition: border-color 0.2s, color 0.2s, background 0.2s;
    }
    .clear-btn:hover {
      border-color: var(--blue);
      color: var(--blue);
      background: rgba(26,74,138,0.04);
    }

    /* ===== LISTINGS HEADER ===== */
    .listings-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .listings-header h2 {
      font-family: var(--font-heading);
      font-size: 1.2rem;
      font-weight: 800;
      color: var(--navy);
      letter-spacing: -0.3px;
    }
    .listings-header span {
      color: var(--gray-500);
      font-size: 0.88rem;
      font-weight: 500;
    }
    .sort-select {
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-sm);
      padding: 9px 14px;
      font-family: var(--font-body);
      font-size: 0.85rem;
      color: var(--navy);
      outline: none;
      cursor: pointer;
      background: var(--white);
      transition: border-color 0.2s;
      font-weight: 500;
    }
    .sort-select:focus { border-color: var(--blue); }

    /* ===== PART 2: PROGRAM CARD ===== */
    .program-card {
      background: var(--white);
      border-radius: var(--radius);
      padding: 24px 28px;
      box-shadow: var(--shadow);
      margin-bottom: 16px;
      display: flex;
      gap: 22px;
      cursor: pointer;
      border: 2px solid transparent;
      transition: border-color 0.25s, box-shadow 0.25s, transform 0.2s;
      text-decoration: none;
      color: inherit;
      position: relative;
    }
    .program-card:hover {
      border-color: var(--blue);
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
    }
    .program-card.featured {
      border-color: var(--gold);
      background: linear-gradient(to right, #fffdf5, var(--white));
    }
    .card-logo {
      width: 60px;
      height: 60px;
      border-radius: var(--radius-sm);
      background: linear-gradient(135deg, var(--blue) 0%, var(--blue-light) 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      flex-shrink: 0;
      color: var(--white);
      box-shadow: 0 4px 12px rgba(26,74,138,0.2);
    }
    .card-body { flex: 1; min-width: 0; }
    .card-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 8px;
    }
    .card-title {
      font-family: var(--font-heading);
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--navy);
      line-height: 1.3;
    }
    .card-institution {
      font-size: 0.88rem;
      color: var(--blue);
      margin-bottom: 10px;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .card-institution:hover {
      color: var(--blue-dark);
      text-decoration: underline;
    }
    .card-desc {
      font-size: 0.87rem;
      color: var(--gray-500);
      line-height: 1.6;
      margin-bottom: 14px;
    }
    .card-tags { display: flex; flex-wrap: wrap; gap: 8px; }
    .tag {
      font-size: 0.75rem;
      font-weight: 600;
      padding: 5px 12px;
      border-radius: 6px;
      background: var(--bg);
      color: var(--blue);
      letter-spacing: 0.2px;
    }
    .tag.type-exchange { background: #e8f5e9; color: #2e7d32; }
    .tag.type-internship { background: #fff3e0; color: #bf6000; }
    .tag.type-research { background: #f3e5f5; color: #7b1fa2; }
    .tag.type-training { background: #e3f2fd; color: #1565c0; }
    .card-deadline {
      font-size: 0.78rem;
      color: var(--gray-500);
      white-space: nowrap;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .card-deadline.urgent { color: #c0392b; font-weight: 700; }
    .card-deadline.urgent::before {
      content: '';
      width: 6px;
      height: 6px;
      background: #c0392b;
      border-radius: 50%;
      animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.4; }
    }
    .featured-badge {
      background: var(--gold);
      color: var(--blue-dark);
      font-size: 0.7rem;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 6px;
      letter-spacing: 0.6px;
      text-transform: uppercase;
    }

    /* ===== PART 3: PROGRAM DETAIL MODAL ===== */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15,42,82,0.55);
      backdrop-filter: blur(4px);
      z-index: 200;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .modal-overlay.open { display: flex; }
    .modal {
      background: var(--white);
      border-radius: var(--radius-lg);
      max-width: 680px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: var(--shadow-xl);
      animation: modalIn 0.25s ease-out;
    }
    @keyframes modalIn {
      from { opacity: 0; transform: translateY(16px) scale(0.98); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .modal-header {
      padding: 28px 28px 20px;
      border-bottom: 1px solid var(--gray-200);
      display: flex;
      gap: 18px;
      align-items: flex-start;
      background: linear-gradient(to bottom, rgba(26,74,138,0.03), transparent);
    }
    .modal-logo {
      width: 64px;
      height: 64px;
      background: linear-gradient(135deg, var(--blue) 0%, var(--blue-light) 100%);
      border-radius: var(--radius-sm);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
      flex-shrink: 0;
      color: var(--white);
      box-shadow: 0 4px 16px rgba(26,74,138,0.25);
    }
    .modal-title {
      font-family: var(--font-heading);
      font-size: 1.3rem;
      font-weight: 800;
      color: var(--navy);
      margin-bottom: 4px;
      line-height: 1.3;
    }
    .modal-institution {
      color: var(--blue);
      font-weight: 600;
      margin-bottom: 10px;
      font-size: 0.92rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .modal-institution:hover {
      color: var(--blue-dark);
      text-decoration: underline;
    }
    .modal-close {
      margin-left: auto;
      background: var(--bg);
      border: none;
      font-size: 1.2rem;
      cursor: pointer;
      color: var(--gray-500);
      line-height: 1;
      padding: 8px;
      border-radius: var(--radius-sm);
      transition: background 0.2s, color 0.2s;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .modal-close:hover { background: var(--gray-200); color: var(--navy); }
    .modal-body { padding: 24px 28px 28px; }
    .modal-section { margin-bottom: 24px; }
    .modal-section h4 {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      color: var(--blue);
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .modal-section h4::before {
      content: '';
      width: 6px;
      height: 6px;
      background: var(--gold);
      border-radius: 50%;
      flex-shrink: 0;
    }
    .modal-section p {
      font-size: 0.92rem;
      line-height: 1.7;
      color: var(--gray-700);
    }
    .modal-meta {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .meta-item {
      background: var(--bg);
      border-radius: var(--radius-sm);
      padding: 14px 16px;
      border: 1px solid var(--gray-200);
    }
    .meta-item .label {
      font-size: 0.75rem;
      color: var(--gray-500);
      margin-bottom: 4px;
      font-weight: 500;
    }
    .meta-item .value {
      font-size: 0.92rem;
      font-weight: 700;
      color: var(--navy);
    }
    .requirements-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .requirements-list li {
      font-size: 0.9rem;
      color: var(--gray-700);
      padding-left: 24px;
      position: relative;
      line-height: 1.5;
    }
    .requirements-list li::before {
      content: '✓';
      position: absolute;
      left: 0;
      color: var(--blue);
      font-weight: 800;
      background: rgba(26,74,138,0.1);
      width: 18px;
      height: 18px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.7rem;
      top: 2px;
    }
    .apply-btn {
      display: block;
      width: 100%;
      background: var(--blue);
      color: var(--white);
      border: none;
      border-radius: var(--radius-sm);
      padding: 16px;
      font-family: var(--font-body);
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
      margin-top: 8px;
      text-align: center;
      text-decoration: none;
      box-shadow: 0 4px 16px rgba(26,74,138,0.3);
    }
    .apply-btn:hover {
      background: var(--blue-dark);
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(26,74,138,0.4);
    }

    /* ===== PART 5: PARTNER PROFILE PANEL ===== */
    .profile-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15,42,82,0.55);
      backdrop-filter: blur(4px);
      z-index: 300;
      align-items: flex-start;
      justify-content: center;
      padding: 48px 24px;
      overflow-y: auto;
    }
    .profile-overlay.open { display: flex; }
    .profile-panel {
      background: var(--white);
      border-radius: var(--radius-lg);
      max-width: 820px;
      width: 100%;
      box-shadow: var(--shadow-xl);
      animation: modalIn 0.25s ease-out;
      overflow: hidden;
    }
    .profile-banner {
      background: linear-gradient(135deg, var(--blue) 0%, var(--blue-light) 60%, var(--blue-dark) 100%);
      padding: 40px 36px 32px;
      position: relative;
      overflow: hidden;
    }
    .profile-banner::before {
      content: '';
      position: absolute;
      top: -60px;
      right: -40px;
      width: 200px;
      height: 200px;
      background: var(--gold);
      border-radius: 50%;
      opacity: 0.15;
    }
    .profile-banner-inner {
      display: flex;
      gap: 24px;
      align-items: center;
      position: relative;
      z-index: 1;
    }
    .profile-big-logo {
      width: 88px;
      height: 88px;
      border-radius: var(--radius);
      background: var(--gold);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-heading);
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--blue-dark);
      flex-shrink: 0;
      box-shadow: 0 8px 24px rgba(0,0,0,0.25);
      border: 3px solid rgba(255,255,255,0.3);
    }
    .profile-banner-text { flex: 1; min-width: 0; }
    .profile-name {
      font-family: var(--font-heading);
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--white);
      line-height: 1.2;
      margin-bottom: 6px;
      letter-spacing: -0.3px;
    }
    .profile-location {
      color: rgba(255,255,255,0.75);
      font-size: 0.92rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .profile-close {
      position: absolute;
      top: 20px;
      right: 20px;
      background: rgba(255,255,255,0.15);
      border: none;
      color: var(--white);
      width: 36px;
      height: 36px;
      border-radius: var(--radius-sm);
      cursor: pointer;
      font-size: 1.1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s;
      z-index: 2;
    }
    .profile-close:hover { background: rgba(255,255,255,0.25); }
    .profile-stats {
      display: flex;
      gap: 0;
      border-bottom: 1px solid var(--gray-200);
    }
    .profile-stat {
      flex: 1;
      padding: 20px 24px;
      text-align: center;
      border-right: 1px solid var(--gray-200);
    }
    .profile-stat:last-child { border-right: none; }
    .profile-stat strong {
      display: block;
      font-family: var(--font-heading);
      font-size: 1.5rem;
      font-weight: 800;
      color: var(--blue);
      margin-bottom: 2px;
    }
    .profile-stat span {
      font-size: 0.78rem;
      color: var(--gray-500);
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .profile-body { padding: 32px 36px 36px; }
    .profile-about {
      margin-bottom: 32px;
    }
    .profile-about h4 {
      font-family: var(--font-heading);
      font-size: 1rem;
      font-weight: 700;
      color: var(--navy);
      margin-bottom: 10px;
    }
    .profile-about p {
      font-size: 0.92rem;
      line-height: 1.7;
      color: var(--gray-700);
    }
    .profile-programs-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
    }
    .profile-programs-header h4 {
      font-family: var(--font-heading);
      font-size: 1rem;
      font-weight: 700;
      color: var(--navy);
    }
    .profile-programs-header span {
      font-size: 0.82rem;
      color: var(--gray-500);
      font-weight: 500;
    }
    .profile-program-item {
      display: flex;
      gap: 16px;
      padding: 16px;
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-sm);
      margin-bottom: 12px;
      cursor: pointer;
      transition: border-color 0.2s, background 0.2s;
      align-items: center;
    }
    .profile-program-item:hover {
      border-color: var(--blue);
      background: rgba(26,74,138,0.03);
    }
    .profile-program-emoji {
      width: 44px;
      height: 44px;
      border-radius: var(--radius-sm);
      background: var(--bg);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      flex-shrink: 0;
    }
    .profile-program-info { flex: 1; min-width: 0; }
    .profile-program-title {
      font-size: 0.92rem;
      font-weight: 700;
      color: var(--navy);
      margin-bottom: 3px;
      line-height: 1.3;
    }
    .profile-program-meta {
      font-size: 0.78rem;
      color: var(--gray-500);
      font-weight: 500;
    }
    .profile-program-arrow {
      color: var(--gray-400);
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .profile-empty {
      text-align: center;
      padding: 32px;
      color: var(--gray-500);
      font-size: 0.9rem;
      background: var(--bg);
      border-radius: var(--radius-sm);
    }

    /* ===== PAGINATION ===== */
    .pagination {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-top: 36px;
    }
    .page-btn {
      width: 40px;
      height: 40px;
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-sm);
      background: var(--white);
      cursor: pointer;
      font-family: var(--font-body);
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--navy);
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .page-btn.active {
      background: var(--blue);
      color: var(--white);
      border-color: var(--blue);
      box-shadow: 0 2px 8px rgba(26,74,138,0.3);
    }
    .page-btn:hover:not(.active) {
      border-color: var(--blue);
      color: var(--blue);
      background: rgba(26,74,138,0.04);
    }

    /* ===== EMPTY STATE ===== */
    .programs-empty {
      background: var(--white);
      border-radius: var(--radius);
      padding: 64px 32px;
      text-align: center;
      box-shadow: var(--shadow);
      border: 1px solid var(--gray-200);
    }
    .programs-empty p {
      color: var(--gray-500);
      font-size: 1rem;
      line-height: 1.6;
    }

    /* ===== FOOTER ===== */
    footer {
      background: var(--blue-deep);
      color: rgba(255,255,255,0.5);
      padding: 64px 24px 32px;
      font-size: 0.85rem;
      margin-top: 64px;
      position: relative;
      overflow: hidden;
    }
    footer::before {
      content: '';
      position: absolute;
      top: -60px;
      right: -60px;
      width: 180px;
      height: 180px;
      background: var(--gold);
      border-radius: 50%;
      opacity: 0.06;
    }
    .footer-inner {
      max-width: 1120px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }
    .footer-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 40px;
      padding-bottom: 48px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .footer-brand h4 {
      font-family: var(--font-heading);
      color: var(--white);
      font-size: 1.15rem;
      font-weight: 800;
      margin-bottom: 12px;
    }
    .footer-brand h4 span { color: var(--gold); }
    .footer-brand p {
      color: rgba(255,255,255,0.5);
      font-size: 0.85rem;
      line-height: 1.7;
      max-width: 280px;
    }
    .footer-col h5 {
      color: var(--white);
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 16px;
    }
    .footer-col ul {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .footer-col a {
      color: rgba(255,255,255,0.5);
      text-decoration: none;
      font-size: 0.85rem;
      transition: color 0.2s;
    }
    .footer-col a:hover { color: var(--gold); }
    .footer-bottom {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-top: 28px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .footer-bottom p {
      color: rgba(255,255,255,0.4);
      font-size: 0.8rem;
    }
    .footer-socials {
      display: flex;
      gap: 12px;
    }
    .footer-socials a {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: rgba(255,255,255,0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      color: rgba(255,255,255,0.6);
      text-decoration: none;
      font-size: 0.85rem;
      transition: background 0.2s, color 0.2s;
    }
    .footer-socials a:hover {
      background: var(--gold);
      color: var(--blue-dark);
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 900px) {
      .page-body { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      nav { padding: 0 24px; }
      .nav-links { display: none; }
      .hero { padding: 64px 24px 72px; }
      .stats-strip { gap: 32px; }
      .footer-grid { grid-template-columns: 1fr 1fr; }
      .profile-banner-inner { flex-direction: column; text-align: center; }
      .profile-stats { flex-wrap: wrap; }
      .profile-stat { flex: 1 1 50%; border-bottom: 1px solid var(--gray-200); }
    }
    @media (max-width: 600px) {
      .hero { padding: 48px 20px 56px; }
      .hero h1 { font-size: 1.8rem; }
      .search-bar { flex-direction: column; border-radius: var(--radius); }
      .search-bar input { padding: 14px 18px; }
      .search-bar select { border-left: none; border-top: 1px solid var(--gray-200); padding: 12px 18px; }
      .search-bar button { padding: 14px; }
      .stats-strip { flex-direction: column; gap: 20px; margin-top: 32px; }
      .program-card { flex-direction: column; gap: 14px; padding: 20px; }
      .card-logo { width: 48px; height: 48px; font-size: 1.2rem; }
      .card-top { flex-direction: column; gap: 8px; }
      .modal-meta { grid-template-columns: 1fr; }
      .modal-header { padding: 20px; }
      .modal-body { padding: 20px; }
      .page-body { padding: 32px 16px; }
      .footer-grid { grid-template-columns: 1fr; gap: 32px; }
      .footer-bottom { flex-direction: column; text-align: center; }
      .profile-body { padding: 24px; }
      .profile-banner { padding: 32px 24px 24px; }
      .profile-name { font-size: 1.3rem; }
      .profile-big-logo { width: 72px; height: 72px; font-size: 1.5rem; }
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav>
  <!-- Anchor link to top -->
  <a href="#" class="nav-logo" style="text-decoration:none;"><span>Internalization</span></a>
  
  <ul class="nav-links">
    <!-- Scrolls down to the program cards -->
    <li><a href="#programList">Browse Programs</a></li>
    
    <!-- Scrolls down to the Partner Institutions grid -->
    <li><a href="#institutionsGrid">Partner Institutions</a></li>
    
    <!-- Scrolls down to the How It Works section -->
    <li><a href="#how-section">How It Works</a></li>
    
    <li class="nav-auth-group">
      <a href="register.php" class="nav-cta-outline">Create Account</a>
      <a href="user_login.php" class="nav-cta">Log in Now</a>
    </li>
  </ul>
</nav>

<!-- PART 1: HERO + SEARCH & FILTERING -->
<section class="hero">
  <div class="hero-inner">
    <div class="hero-eyebrow">Student Mobility &amp; Internship Portal</div>
    <h1>Find your next program, <em>abroad</em>.</h1>
    <p>Discover internships, exchange programs, and research opportunities from partner institutions around the world — open to PSU students.</p>
    <div class="search-bar">
      <input type="text" placeholder="Search by program name, country, or field…" id="searchInput"/>
      <select id="typeFilter">
        <option value="">All Types</option>
        <option value="exchange">Exchange</option>
        <option value="internship">Internship</option>
        <option value="research">Research</option>
        <option value="training">Training</option>
      </select>
      <button onclick="applySearch()">Search Programs</button>
    </div>
    <div class="stats-strip">
      <div class="stat"><strong><?= $totalPrograms ?></strong> Active Programs</div>
      <div class="stat"><strong><?= $totalCountries ?></strong> Partner Countries</div>
      <div class="stat"><strong><?= $totalInstitutions ?></strong> Partner Institutions</div>
    </div>
  </div>
</section>

<!-- PART 5: PARTNER INSTITUTIONS SHOWCASE -->
<section class="institutions-section" id="institutionsGrid">
  <div class="section-header">
    <div class="section-eyebrow">Our Partners</div>
    <h2>Explore partner <em>institutions</em></h2>
    <p>Click any institution to view their profile and all programs they offer.</p>
  </div>
  <div class="institutions-grid" id="institutionsGridContainer"></div>
</section>

<!-- HOW IT WORKS SECTION -->
<section class="how-section" id="how-section">
  <div class="section-header">
    <div class="section-eyebrow">Simple Process</div>
    <h2>How it <em>works</em></h2>
    <p>Four straightforward steps from discovery to departure.</p>
  </div>
  <div class="how-grid">
    <div class="how-step">
      <h3>Browse Programs</h3>
      <p>Explore active programs filtered by type, country, duration, and funding options.</p>
    </div>
    <div class="how-step">
      <h3>Submit Application</h3>
      <p>Complete a single online application with all required documents and recommendations.</p>
    </div>
    <div class="how-step">
      <h3>Get Approved</h3>
      <p>Receive confirmation from PSU International Affairs and your host institution.</p>
    </div>
    <div class="how-step">
      <h3>Travel &amp; Study</h3>
      <p>Attend pre-departure orientation, then begin your adventure abroad with full support.</p>
    </div>
  </div>
</section>

<!-- PARTS 2 & 3: PROGRAM LISTINGS + DETAIL MODAL -->
<div class="page-body" id="programList">
  <aside class="sidebar">
    <div class="filter-card">
      <h3>Program Type</h3>
      <div class="filter-group">
        <label><input type="checkbox" data-filter="type" value="exchange" onchange="filterCards()"> Student Exchange <span class="badge-count"><?= $typeCounts['exchange'] ?></span></label>
        <label><input type="checkbox" data-filter="type" value="internship" onchange="filterCards()"> Internship <span class="badge-count"><?= $typeCounts['internship'] ?></span></label>
        <label><input type="checkbox" data-filter="type" value="research" onchange="filterCards()"> Research <span class="badge-count"><?= $typeCounts['research'] ?></span></label>
        <label><input type="checkbox" data-filter="type" value="training" onchange="filterCards()"> Training <span class="badge-count"><?= $typeCounts['training'] ?></span></label>
      </div>
    </div>

    <div class="filter-card">
      <h3>Country / Region</h3>
      <select id="countryFilter" onchange="filterCards()">
        <option value="">All Countries</option>
        <?php foreach (array_keys($countrySet) as $countryName): ?>
          <option><?= htmlspecialchars($countryName) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-card">
      <h3>Duration</h3>
      <div class="filter-group">
        <label><input type="checkbox" onchange="filterCards()"> 1 – 3 months</label>
        <label><input type="checkbox" onchange="filterCards()"> 4 – 6 months</label>
        <label><input type="checkbox" onchange="filterCards()"> 1 semester</label>
        <label><input type="checkbox" onchange="filterCards()"> 1 academic year</label>
      </div>
    </div>

    <div class="filter-card">
      <h3>Funding</h3>
      <div class="filter-group">
        <label><input type="checkbox" data-filter="funding" value="full scholarship" onchange="filterCards()"> With Scholarship</label>
        <label><input type="checkbox" data-filter="funding" value="partial" onchange="filterCards()"> Partially Funded</label>
        <label><input type="checkbox" data-filter="funding" value="self-funded" onchange="filterCards()"> Self-funded</label>
      </div>
    </div>

    <button class="clear-btn" onclick="clearFilters()">Clear all filters</button>
  </aside>

  <main>
    <div class="listings-header">
      <div>
        <h2>Available Programs</h2>
        <span id="resultCount">Showing <?= $totalPrograms ?> of <?= $totalPrograms ?> programs</span>
      </div>
      <select class="sort-select" id="sortSelect">
        <option value="deadline">Sort: Deadline (Soonest)</option>
        <option value="slots">Sort: Most Slots</option>
        <option value="az">Sort: A → Z</option>
      </select>
    </div>

    <?php if ($loadError): ?>
      <div class="programs-empty">
        <p><?= htmlspecialchars($loadError) ?></p>
      </div>
    <?php elseif ($totalPrograms === 0): ?>
      <div class="programs-empty">
        <p>No programs are available right now. Please check back soon.</p>
      </div>
    <?php else: ?>
      <div id="programListContainer"></div>
      <div class="pagination" id="paginationBar"></div>
    <?php endif; ?>
  </main>
</div>

<!-- PART 3: PROGRAM DETAIL MODAL -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
  <div class="modal" id="modalBox">
    <div class="modal-header">
      <div class="modal-logo" id="modalLogo"></div>
      <div>
        <div class="modal-title" id="modalTitle"></div>
        <a class="modal-institution" id="modalInstitution" onclick="openInstitutionFromModal(event)"></a>
        <div class="card-tags" id="modalTags"></div>
      </div>
      <button class="modal-close" onclick="closeModalDirect()">✕</button>
    </div>
    <div class="modal-body">
      <div class="modal-section">
        <h4>About this Program</h4>
        <p id="modalDesc"></p>
      </div>
      <div class="modal-section">
        <h4>Program Details</h4>
        <div class="modal-meta" id="modalMeta"></div>
      </div>
      <div class="modal-section">
        <h4>Requirements</h4>
        <ul class="requirements-list" id="modalReqs"></ul>
      </div>
      <!-- Default href, but JS will update it dynamically to include the program_id -->
      <a href="register.php" id="applyLink" class="apply-btn">Apply for this Program</a>
    </div>
  </div>
</div>

<!-- PART 5: PARTNER INSTITUTION PROFILE PANEL -->
<div class="profile-overlay" id="profileOverlay" onclick="closeProfile(event)">
  <div class="profile-panel" id="profilePanel">
    <div class="profile-banner">
      <button class="profile-close" onclick="closeProfileDirect()">✕</button>
      <div class="profile-banner-inner">
        <div class="profile-big-logo" id="profileLogo"></div>
        <div class="profile-banner-text">
          <div class="profile-name" id="profileName"></div>
          <div class="profile-location" id="profileLocation"></div>
        </div>
      </div>
    </div>
    <div class="profile-stats">
      <div class="profile-stat">
        <strong id="profileStatPrograms">0</strong>
        <span>Active Programs</span>
      </div>
      <div class="profile-stat">
        <strong id="profileStatTypes">0</strong>
        <span>Program Types</span>
      </div>
      <div class="profile-stat">
        <strong id="profileStatCountry">—</strong>
        <span>Location</span>
      </div>
    </div>
    <div class="profile-body">
      <div class="profile-about">
        <h4>About this Institution</h4>
        <p id="profileAbout"></p>
      </div>
      <div class="profile-programs-header">
        <h4>Programs at this Institution</h4>
        <span id="profileProgramCount"></span>
      </div>
      <div id="profileProgramsList"></div>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer>
  <div class="footer-inner">
    <div class="footer-grid">
      <div class="footer-brand">
        <h4>PSU <span>Internalization</span></h4>
        <p>The official student mobility portal of Pangasinan State University's Office of International Affairs.</p>
      </div>
      <div class="footer-col">
        <h5>Explore</h5>
        <ul>
          <li><a href="#programList">Browse Programs</a></li>
          <li><a href="#institutionsGrid">Partner Institutions</a></li>
          <li><a href="#how-section">How It Works</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Support</h5>
        <ul>
          <li><a href="#">Contact Us</a></li>
          <li><a href="#">FAQ</a></li>
          <li><a href="#">Student Handbook</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Connect</h5>
        <ul>
          <li><a href="#">Facebook</a></li>
          <li><a href="#">Instagram</a></li>
          <li><a href="#">LinkedIn</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> Pangasinan State University · Office of International Affairs. All rights reserved.</p>
      <div class="footer-socials">
        <a href="#" title="Facebook">f</a>
        <a href="#" title="Instagram">ig</a>
        <a href="#" title="LinkedIn">in</a>
      </div>
    </div>
  </div>
</footer>

<?php if (!$loadError && $totalPrograms > 0): ?>
<script>
  const rawPrograms = <?= json_encode($programs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

  const TYPE_EMOJI = {
    exchange: '🔄',
    internship: '💼',
    research: '🔬',
    training: '📚'
  };

  function formatDate(dateStr) {
    if (!dateStr) return 'TBA';
    const d = new Date(dateStr);
    if (isNaN(d)) return 'TBA';
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function computeDuration(start, end) {
    if (!start || !end) return 'Flexible';
    const s = new Date(start), e = new Date(end);
    if (isNaN(s) || isNaN(e)) return 'Flexible';
    const months = Math.max(1, Math.round((e - s) / (1000 * 60 * 60 * 24 * 30)));
    if (months >= 10) return '1 academic year';
    if (months >= 5) return '1 semester';
    if (months >= 4) return '4 – 6 months';
    return '1 – 3 months';
  }

  function isUrgent(endDate) {
    if (!endDate) return false;
    const days = (new Date(endDate) - new Date()) / (1000 * 60 * 60 * 24);
    return days >= 0 && days <= 14;
  }

  const programs = rawPrograms.map(p => {
    const type = (p.program_type || '').toLowerCase();
    return {
      id: p.id,
      title: p.program_name,
      type: type,
      desc: p.description || 'No description available.',
      institution: p.institution_name || 'Partner Institution',
      country: p.country_name || 'Unknown',
      startDate: p.start_date,
      endDate: p.end_date,
      deadline: formatDate(p.end_date),
      duration: computeDuration(p.start_date, p.end_date),
      emoji: TYPE_EMOJI[type] || '🎓',
      featured: false,
      urgent: isUrgent(p.end_date)
    };
  });

  // Build institution groups
  const institutions = {};
  programs.forEach(p => {
    if (!institutions[p.institution]) {
      institutions[p.institution] = {
        name: p.institution,
        country: p.country,
        programs: [],
        types: new Set()
      };
    }
    institutions[p.institution].programs.push(p);
    institutions[p.institution].types.add(p.type);
  });
  const institutionList = Object.values(institutions);

  const PER_PAGE = 6;
  let currentPage = 1;
  let filteredPrograms = [...programs];

  function initials(name) {
    return name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
  }

  // ===== PART 5: RENDER INSTITUTIONS GRID =====
  function renderInstitutions() {
    const grid = document.getElementById('institutionsGridContainer');
    grid.innerHTML = '';
    institutionList.forEach(inst => {
      const card = document.createElement('div');
      card.className = 'institution-card';
      card.onclick = () => openProfile(inst.name);
      card.innerHTML = `
        <div class="institution-logo">${initials(inst.name)}</div>
        <div class="institution-info">
          <div class="institution-name">${inst.name}</div>
          <div class="institution-meta">${inst.country} · ${inst.types.size} type${inst.types.size > 1 ? 's' : ''}</div>
        </div>
        <span class="institution-count">${inst.programs.length}</span>
      `;
      grid.appendChild(card);
    });
  }

  // ===== PART 5: OPEN INSTITUTION PROFILE =====
  function openProfile(name) {
    const inst = institutions[name];
    if (!inst) return;

    document.getElementById('profileLogo').textContent = initials(inst.name);
    document.getElementById('profileName').textContent = inst.name;
    document.getElementById('profileLocation').innerHTML = `📍 ${inst.country}`;
    document.getElementById('profileStatPrograms').textContent = inst.programs.length;
    document.getElementById('profileStatTypes').textContent = inst.types.size;
    document.getElementById('profileStatCountry').textContent = inst.country;
    document.getElementById('profileAbout').textContent =
      `${inst.name} is a partner institution of Pangasinan State University located in ${inst.country}. ` +
      `Through this partnership, PSU students have access to ${inst.programs.length} active program${inst.programs.length > 1 ? 's' : ''} ` +
      `spanning ${inst.types.size} type${inst.types.size > 1 ? 's' : ''} of mobility opportunity.`;

    document.getElementById('profileProgramCount').textContent =
      `${inst.programs.length} program${inst.programs.length > 1 ? 's' : ''} available`;

    const list = document.getElementById('profileProgramsList');
    list.innerHTML = '';
    inst.programs.forEach(p => {
      const item = document.createElement('div');
      item.className = 'profile-program-item';
      item.onclick = () => {
        closeProfileDirect();
        openModal(p);
      };
      item.innerHTML = `
        <div class="profile-program-emoji">${p.emoji}</div>
        <div class="profile-program-info">
          <div class="profile-program-title">${p.title}</div>
          <div class="profile-program-meta">${capitalize(p.type)} · ⏱ ${p.duration} · Deadline: ${p.deadline}</div>
        </div>
        <div class="profile-program-arrow">›</div>
      `;
      list.appendChild(item);
    });

    document.getElementById('profileOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeProfile(e) {
    if (e.target === document.getElementById('profileOverlay')) closeProfileDirect();
  }
  function closeProfileDirect() {
    document.getElementById('profileOverlay').classList.remove('open');
    if (!document.getElementById('modalOverlay').classList.contains('open')) {
      document.body.style.overflow = '';
    }
  }

  // ===== PART 2: RENDER PROGRAM CARDS =====
  function renderPrograms(list, page) {
    currentPage = page || 1;
    filteredPrograms = list;
    const total = list.length;
    const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
    const start = (currentPage - 1) * PER_PAGE;
    const pageItems = list.slice(start, start + PER_PAGE);

    const container = document.getElementById('programListContainer');
    container.innerHTML = '';

    if (total === 0) {
      container.innerHTML = '<div class="programs-empty"><p>No programs match your filters. Try adjusting your search or clearing filters.</p></div>';
      renderPagination(0, 1);
      document.getElementById('resultCount').textContent = 'No programs found';
      return;
    }

    pageItems.forEach(p => {
      const desc = p.desc.length > 130 ? p.desc.substring(0, 130) + '...' : p.desc;

      const card = document.createElement('div');
      card.className = 'program-card' + (p.featured ? ' featured' : '');
      card.onclick = () => openModal(p);

      card.innerHTML = `
        <div class="card-logo">${p.emoji}</div>
        <div class="card-body">
          <div class="card-top">
            <div>
              <div class="card-title">${p.title}</div>
              <a class="card-institution" onclick="event.stopPropagation(); openProfile('${p.institution.replace(/'/g, "\\'")}')">
                🏛 ${p.institution} · ${p.country}
              </a>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0;">
              ${p.featured ? '<span class="featured-badge">Featured</span>' : ''}
              <span class="card-deadline${p.urgent ? ' urgent' : ''}">
                Deadline: ${p.deadline}
              </span>
            </div>
          </div>
          <div class="card-desc">${desc}</div>
          <div class="card-tags">
            <span class="tag type-${p.type}">${capitalize(p.type)}</span>
            <span class="tag">⏱ ${p.duration}</span>
          </div>
        </div>
      `;

      container.appendChild(card);
    });

    document.getElementById('resultCount').textContent =
      `Showing ${start + 1}–${Math.min(start + PER_PAGE, total)} of ${total} programs`;

    renderPagination(totalPages, currentPage);
  }

  function renderPagination(totalPages, active) {
    const pg = document.getElementById('paginationBar');
    pg.innerHTML = '';
    if (totalPages <= 1) return;

    const btn = (label, page, disabled, isActive) => {
      const b = document.createElement('button');
      b.className = 'page-btn' + (isActive ? ' active' : '');
      b.textContent = label;
      b.disabled = disabled;
      if (!disabled && !isActive) b.onclick = () => renderPrograms(filteredPrograms, page);
      return b;
    };

    pg.appendChild(btn('‹', active - 1, active === 1, false));

    let pages = [];
    if (totalPages <= 7) {
      pages = Array.from({ length: totalPages }, (_, i) => i + 1);
    } else {
      pages = [1];
      if (active > 3) pages.push('…');
      for (let i = Math.max(2, active - 1); i <= Math.min(totalPages - 1, active + 1); i++) pages.push(i);
      if (active < totalPages - 2) pages.push('…');
      pages.push(totalPages);
    }

    pages.forEach(p => {
      if (p === '…') {
        const s = document.createElement('span');
        s.textContent = '…';
        s.style.cssText = 'padding:0 8px;color:var(--gray-400);line-height:40px;font-weight:600;';
        pg.appendChild(s);
      } else {
        pg.appendChild(btn(p, p, false, p === active));
      }
    });

    pg.appendChild(btn('›', active + 1, active === totalPages, false));
  }

  // ===== PART 3: PROGRAM DETAIL MODAL =====
  let activeProgramForModal = null;

  function openModal(p) {
    activeProgramForModal = p;
    document.getElementById('modalLogo').textContent = p.emoji;
    document.getElementById('modalTitle').textContent = p.title;
    document.getElementById('modalInstitution').innerHTML =
      `🏛 ${p.institution} · ${p.country} <span style="font-size:0.8rem;">↗</span>`;
    document.getElementById('modalTags').innerHTML = `
      <span class="tag type-${p.type}">${capitalize(p.type)}</span>
      <span class="tag">⏱ ${p.duration}</span>`;
    document.getElementById('modalDesc').textContent = p.desc;
    document.getElementById('modalMeta').innerHTML = `
      <div class="meta-item"><div class="label">Duration</div><div class="value">${p.duration}</div></div>
      <div class="meta-item"><div class="label">Application Deadline</div><div class="value">${p.deadline}</div></div>
      <div class="meta-item"><div class="label">Country</div><div class="value">${p.country}</div></div>
      <div class="meta-item"><div class="label">Program Type</div><div class="value">${capitalize(p.type)}</div></div>`;

    document.getElementById('modalReqs').innerHTML = `
      <li>Must be a currently enrolled PSU student</li>
      <li>Minimum GPA of 2.5 (or equivalent)</li>
      <li>Completed application form</li>
      <li>Recommendation letter from a faculty member</li>
      <li>Valid passport (for international programs)</li>`;

    // FIX: Changed to point to register.php, passing the program_id as a URL parameter
    document.getElementById('applyLink').href = 'register.php?program_id=' + p.id;
    document.getElementById('modalOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function openInstitutionFromModal(e) {
    e.preventDefault();
    if (!activeProgramForModal) return;
    const instName = activeProgramForModal.institution;
    closeModalDirect();
    openProfile(instName);
  }

  function closeModal(e) {
    if (e.target === document.getElementById('modalOverlay')) closeModalDirect();
  }
  function closeModalDirect() {
    document.getElementById('modalOverlay').classList.remove('open');
    if (!document.getElementById('profileOverlay').classList.contains('open')) {
      document.body.style.overflow = '';
    }
  }

  // ===== PART 1: SEARCH & FILTER LOGIC =====
  function applySearch() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const type = document.getElementById('typeFilter').value;
    const checkedTypes = [...document.querySelectorAll('.filter-group input[type=checkbox][data-filter=type]:checked')].map(c => c.value);
    const countryVal = document.getElementById('countryFilter').value;

    const filtered = programs.filter(p => {
      const matchQ = !q || p.title.toLowerCase().includes(q) || p.country.toLowerCase().includes(q) || p.institution.toLowerCase().includes(q);
      const matchType = (!type || p.type === type) && (checkedTypes.length === 0 || checkedTypes.includes(p.type));
      const matchCountry = !countryVal || p.country === countryVal;
      return matchQ && matchType && matchCountry;
    });
    renderPrograms(filtered, 1);
  }

  function filterCards() { applySearch(); }

  function clearFilters() {
    document.querySelectorAll('.filter-group input[type=checkbox]').forEach(cb => cb.checked = false);
    document.getElementById('searchInput').value = '';
    document.getElementById('typeFilter').value = '';
    document.getElementById('countryFilter').value = '';
    renderPrograms(programs, 1);
  }

  function capitalize(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
  }

  document.getElementById('searchInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') applySearch();
  });

  document.getElementById('sortSelect').addEventListener('change', function () {
    const val = this.value;
    const sorted = [...filteredPrograms];
    sorted.sort((a, b) => {
      const da = a.endDate ? new Date(a.endDate) : new Date(8640000000000000);
      const db = b.endDate ? new Date(b.endDate) : new Date(8640000000000000);
      return da - db;
    });
    if (val === 'az') sorted.sort((a, b) => a.title.localeCompare(b.title));
    renderPrograms(sorted, 1);
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeModalDirect();
      closeProfileDirect();
    }
  });

  // ===== INIT =====
  renderInstitutions();
  renderPrograms(programs, 1);
</script>
<?php endif; ?>
</body>
</html>
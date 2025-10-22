<?php
// Add this at the top of index.php after the session_start() if it exists
require_once 'includes/db.php';

// Get home content from database
$stmt = $pdo->prepare("SELECT * FROM home_content WHERE id = 1");
$stmt->execute();
$home_content = $stmt->fetch(PDO::FETCH_ASSOC);

// If no content exists, use default values
if (!$home_content) {
  $home_content = [
    'title' => 'Bro. Dr. Dan Owusu Asiamah',
    'description' => 'Ghanaian missionary and preacher with the Churches of Christ; Lead Preacher, Takoradi Church of Christ. Founder of Outreach Africa Vocational Institute (OAVI) and Director of Studies, Takoradi Bible College.',
    'image_path' => './images/a1.jpg',
    'bullet_points' => '["2,000+ vocational graduates since 2008 (OAVI)", "Finalist — MTN Heroes of Change (2019)", "500+ recorded sermons; TV/radio ministry in Ghana & abroad"]',
    'button1_text' => 'Listen to Sermons',
    'button1_link' => '#sermons',
    'button2_text' => 'Upcoming Events',
    'button2_link' => '#events'
  ];
}

// Decode bullet points
$bullet_points = json_decode($home_content['bullet_points'], true);
if (!is_array($bullet_points)) {
  $bullet_points = [
    '2,000+ vocational graduates since 2008 (OAVI)',
    'Finalist — MTN Heroes of Change (2019)',
    '500+ recorded sermons; TV/radio ministry in Ghana & abroad'
  ];
}

// Handle contact form submission
$contact_alert = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
  // Simple sanitization
  $c_name     = trim($_POST['name'] ?? '');
  $c_email    = trim($_POST['email'] ?? '');
  $c_phone    = trim($_POST['phone'] ?? '');
  $c_location = trim($_POST['location'] ?? '');
  $c_type     = trim($_POST['type'] ?? '');
  $c_message  = trim($_POST['message'] ?? '');
  $c_first    = isset($_POST['first_timer']) ? 1 : 0;

  if ($c_name === '' || $c_email === '' || $c_type === '' || $c_message === '') {
    $contact_alert = ['type'=>'danger', 'text'=>'Please fill in all required fields.'];
  } else {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO contact_messages (name, email, phone, location, message_type, message_text, is_first_timer)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      ");
      $ok = $stmt->execute([$c_name, $c_email, $c_phone, $c_location, $c_type, $c_message, $c_first]);
      if ($ok) {
        $contact_alert = ['type'=>'success', 'text'=>'Thank you! Your message has been received.'];
        // clear form fields after success
        $_POST = [];
      } else {
        $contact_alert = ['type'=>'danger', 'text'=>'Sorry, something went wrong while sending your message.'];
      }
    } catch (Exception $e) {
      $contact_alert = ['type'=>'danger', 'text'=>'Error: '.$e->getMessage()];
    }
  }
}

// ---- Retreat booking form handler ----
$retreat_alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retreat_submit'])) {
  // Simple sanitization
  $r_name     = trim($_POST['r_name'] ?? '');
  $r_email    = trim($_POST['r_email'] ?? '');   // optional
  $r_phone    = trim($_POST['r_phone'] ?? '');   // REQUIRED
  $r_checkin  = trim($_POST['r_checkin'] ?? '');
  $r_checkout = trim($_POST['r_checkout'] ?? '');
  $r_guests   = intval($_POST['r_guests'] ?? 1);
  $r_purpose  = trim($_POST['r_purpose'] ?? '');
  $r_notes    = trim($_POST['r_notes'] ?? '');

  // ✅ Require phone (not email)
  if ($r_name === '' || $r_phone === '' || $r_checkin === '' || $r_checkout === '' || $r_purpose === '') {
    $retreat_alert = ['type'=>'danger', 'text'=>'Please fill in all required fields (Name, Phone, Check-in, Check-out, Purpose).'];
  } else {
    try {
      // Create table if it doesn't exist (email nullable, phone required)
      $pdo->exec("
        CREATE TABLE IF NOT EXISTS retreat_bookings (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(200) NOT NULL,
          email VARCHAR(200) NULL,
          phone VARCHAR(50) NOT NULL,
          checkin DATE NOT NULL,
          checkout DATE NOT NULL,
          guests INT NOT NULL DEFAULT 1,
          purpose VARCHAR(100) NOT NULL,
          notes TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
      ");

      // If table already existed with NOT NULL email, make it nullable and phone required
      // (safe to run; will error only if already correct — ignore errors silently)
      try { $pdo->exec("ALTER TABLE retreat_bookings MODIFY email VARCHAR(200) NULL"); } catch (\Throwable $e) {}
      try { $pdo->exec("ALTER TABLE retreat_bookings MODIFY phone VARCHAR(50) NOT NULL"); } catch (\Throwable $e) {}

      $stmt = $pdo->prepare("
        INSERT INTO retreat_bookings (name, email, phone, checkin, checkout, guests, purpose, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $ok = $stmt->execute([
        $r_name, ($r_email !== '' ? $r_email : null), $r_phone,
        $r_checkin, $r_checkout, $r_guests, $r_purpose, $r_notes
      ]);

      if ($ok) {
        $retreat_alert = ['type'=>'success', 'text'=>'Thank you! Your retreat booking request has been received. We will contact you to confirm availability.'];
        $_POST = [];
      } else {
        $retreat_alert = ['type'=>'danger', 'text'=>'Sorry, something went wrong while submitting your booking request.'];
      }
    } catch (Exception $e) {
      $retreat_alert = ['type'=>'danger', 'text'=>'Error: ' . $e->getMessage()];
    }
  }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bro. Dr. Dan Owusu Asiamah — Ministry</title>
  <meta name="description" content="Official ministry website of Bro. Dr. Dan Owusu Asiamah — sermons, events, posts, and contact.">
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Boxicons for icons -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- AOS Animation Library -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

  <!-- PNG fallbacks (optional) -->
  <link rel="icon" type="image/png" sizes="32x32" href="./images/book-heart.png" >
  <link rel="icon" type="image/png" sizes="16x16" href="./images/book-heart.png" >
  
  <style>
    :root{
      --brand: #3C91E6;
      --brand-dark: #2B6CB0;
      --accent: #3C91E6;
      --accent-light: #5BA3EE;
      --orange: #FD7238;
      --orange-light: #FF8A5B;
      --ink: #1A202C;
      --ink-light: #2D3748;
      --soft: #F7FAFC;
      --soft-dark: #EDF2F7;
      --white: #FFFFFF;
      --success: #48BB78;
      --warning: #ED8936;
      --danger: #F56565;
      --gradient-primary: linear-gradient(135deg, #2B6CB0 0%, #3C91E6 100%);
      --gradient-hover: linear-gradient(135deg, #FD7238 0%, #FF8A5B 100%);
      --gradient-hero: linear-gradient(135deg, rgba(60,145,230,0.9) 0%, rgba(43,108,176,0.8) 100%);
      --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      --shadow-medium: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      --shadow-large: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
      --shadow-glow: 0 0 40px rgba(60, 145, 230, 0.15);
    }

    /* ============ GLOBAL ============ */
    * {
      box-sizing: border-box;
    }
    
    html {
      scroll-behavior: smooth;
      font-size: 16px;
    }
    
    body {
      font-family: 'Inter', sans-serif;
      color: var(--ink);
      line-height: 1.6;
      overflow-x: hidden;
    }

    h1, h2, h3, h4, h5, h6 {
      font-family: 'Playfair Display', serif;
      font-weight: 600;
      line-height: 1.3;
    }

    .text-gradient {
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .btn-gradient {
      background: var(--gradient-primary);
      border: none;
      color: white;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .btn-gradient::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0.1) 100%);
      transition: left 0.6s ease;
    }

    .btn-gradient:hover::before {
      left: 100%;
    }

    .btn-gradient:hover {
      background: var(--gradient-hover);
      transform: translateY(-2px);
      box-shadow: var(--shadow-large);
      color: white;
    }

    /* ============ NAVBAR ============ */

    /* Admin icon link (subtle) */
    .navbar .nav-link.admin-link i { font-size: 1.25rem; opacity: .45; transition: .2s ease; }
    .navbar .nav-link.admin-link:hover i { opacity: 1; transform: translateY(-1px); color: var(--brand); }

    .navbar {
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      backdrop-filter: blur(10px);
      background: rgba(255, 255, 255, 0.95) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .navbar.scrolled {
      box-shadow: var(--shadow-medium);
      background: rgba(255, 255, 255, 0.98) !important;
    }

    .navbar-brand {
      font-family: 'Playfair Display', serif;
      font-weight: 700;
      font-size: 1.5rem;
      transition: transform 0.3s ease;
    }

    .navbar-brand:hover {
      transform: scale(1.05);
    }

    .nav-link {
      position: relative;
      font-weight: 500;
      transition: all 0.3s ease;
      margin: 0 0.5rem;
      padding: 0.7rem 1rem !important;
    }

    .nav-link::before {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      width: 0;
      height: 2px;
      background: var(--gradient-primary);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      transform: translateX(-50%);
    }

    .nav-link:hover::before,
    .nav-link.active::before {
      width: 80%;
    }

    .nav-link:hover {
      color: var(--brand) !important;
      transform: translateY(-1px);
    }

    /* ============ HERO SECTION ============ */
    .hero-hero {
      min-height: 100vh;
      display: flex;
      align-items: center;
      position: relative;
      overflow: hidden;
      color: white;
      background: 
        var(--gradient-hero),
        var(--hero-url) center/cover no-repeat fixed;
    }

    .hero-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: 
        radial-gradient(circle at 30% 20%, rgba(60, 145, 230, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 70% 80%, rgba(43, 108, 176, 0.3) 0%, transparent 50%);
      animation: heroFloat 8s ease-in-out infinite;
    }

    .hero-hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.01);
      backdrop-filter: blur(0.10px);
    }

    @keyframes heroFloat {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-10px) rotate(1deg); }
    }

    .hero-content {
      position: relative;
      z-index: 2;
      max-width: 900px;
    }

    .hero-title {
      font-size: clamp(2.5rem, 5vw, 4rem);
      font-weight: 700;
      margin-bottom: 1.5rem;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
      animation: slideInUp 1s ease-out;
    }

    .hero-description {
      font-size: 1.25rem;
      line-height: 1.7;
      margin-bottom: 2rem;
      text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
      animation: slideInUp 1s ease-out 0.2s both;
    }

    .hero-badges {
      animation: slideInUp 1s ease-out 0.4s both;
    }

    .hero-badges li {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      margin-bottom: 0.5rem;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      padding: 0.75rem 1rem;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      transition: all 0.3s ease;
    }

    .hero-badges li:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateX(10px);
    }

    .hero-buttons {
      animation: slideInUp 1s ease-out 0.6s both;
      gap: 1rem;
    }

    .btn-cta {
      padding: 1rem 2rem;
      font-weight: 600;
      border-radius: 50px;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }

    .btn-cta-primary {
      background: var(--gradient-primary);
      border: none;
      color: white;
      box-shadow: var(--shadow-glow);
    }

    .btn-cta-primary:hover {
      background: var(--gradient-hover);
      color: white;
    }

    .btn-cta-outline {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 2px solid rgba(255, 255, 255, 0.3);
      color: white;
    }

    .btn-cta-outline:hover {
      background: var(--gradient-hover);
      border-color: transparent;
      color: white;
    }

    .btn-cta:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-large);
    }

    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* ============ SECTION STYLING ============ */
    .section-muted {
      background: linear-gradient(135deg, var(--soft) 0%, var(--soft-dark) 100%);
      position: relative;
    }

    .section-muted::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, var(--brand), transparent);
    }

    .section-title {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 1rem;
      position: relative;
    }

    .section-title::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 0;
      width: 60px;
      height: 4px;
      background: var(--gradient-primary);
      border-radius: 2px;
    }

    /* ============ CARDS ============ */
    .card {
      border: none;
      border-radius: 20px;
      box-shadow: var(--shadow-soft);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      overflow: hidden;
      background: white;
      position: relative;
    }

    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--gradient-primary);
      transform: scaleX(0);
      transition: transform 0.3s ease;
    }

    .card:hover::before {
      transform: scaleX(1);
    }

    .card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: var(--shadow-large);
    }

    .card-img-top {
      transition: transform 0.4s ease;
    }

    .card:hover .card-img-top {
      transform: scale(1.05);
    }

    /* Button hovers in cards */
    .card .btn-gradient:hover,
    .card .btn-outline-primary:hover {
      background: var(--gradient-hover);
      border-color: transparent;
      color: white;
    }

    .btn-outline-primary {
      transition: all 0.3s ease;
    }

    .btn-outline-primary:hover {
      background: var(--gradient-hover);
      border-color: transparent;
      color: white;
      transform: translateY(-2px);
    }

    /* ============ BADGES ============ */
    .badge-soft {
      background: rgba(60, 145, 230, 0.1);
      color: var(--brand);
      border: 1px solid rgba(60, 145, 230, 0.2);
      font-weight: 500;
      padding: 0.5rem 1rem;
    }

    .badge-revival {
      background: linear-gradient(135deg, var(--success) 0%, #38A169 100%);
      color: white;
    }

    .badge-conference {
      background: linear-gradient(135deg, #3182CE 0%, #2B6CB0 100%);
      color: white;
    }

    .badge-youth {
      background: linear-gradient(135deg, var(--warning) 0%, #D69E2E 100%);
      color: white;
    }

    /* ============ SERMON MEDIA ============ */
    .sermon-media-fixed {
      width: 100%;
      height: 220px;
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, #1A202C 0%, #2D3748 100%);
      border-radius: 16px;
    }

    .sermon-media-fixed img,
    .sermon-media-fixed video {
      width: 100%;
      height: 100%;
      object-fit: contain;
      background: #000; 
      transition: transform 0.4s ease;
    }

    .sermon-media-fixed:hover img,
    .sermon-media-fixed:hover video {
      transform: scale(1.05);
    }

    .sermon-media-fixed iframe {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      border: none;
      border-radius: 16px;
    }

    /* Play button overlay */
    .sermon-media-fixed::after {
      content: '\ec15';
      font-family: 'boxicons';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      font-size: 3rem;
      color: white;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(10px);
      border-radius: 50%;
      width: 80px;
      height: 80px;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: all 0.3s ease;
      pointer-events: none;
    }

    .sermon-media-fixed:hover::after {
      opacity: 1;
    }

    /* Prevent any emoji or icon overlay on sermon videos or thumbnails */
    .sermon-media-fixed::before,
    .sermon-media-fixed::after,
    .card.loading::before,
    .card.loading::after,
    video::before,
    video::after,
    img::before,
    img::after {
      content: none !important;
      display: none !important;
    }

    /* Optional: also ensure clean display */
    .sermon-media-fixed {
      position: relative;
      overflow: hidden;
    }


    /* ============ CONTACT FORM ============ */
    .contact-form {
      background: white;
      border-radius: 24px;
      padding: 3rem;
      box-shadow: var(--shadow-large);
      position: relative;
      overflow: hidden;
    }

    .contact-form::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 6px;
      background: var(--gradient-primary);
    }

    .form-control,
    .form-select {
      border: 2px solid #E2E8F0;
      border-radius: 12px;
      padding: 1rem 1.5rem;
      transition: all 0.3s ease;
      background: #FAFAFA;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 0.2rem rgba(60, 145, 230, 0.15);
      background: white;
    }

    .form-label {
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 0.75rem;
    }

    /* Contact form button hover */
    .contact-form .btn-gradient:hover {
      background: var(--gradient-hover);
    }

    /* ============ ANIMATIONS ============ */
    .floating {
      animation: floating 3s ease-in-out infinite;
    }

    @keyframes floating {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }

    .pulse-glow {
      animation: pulseGlow 2s infinite;
    }

    @keyframes pulseGlow {
      0%, 100% { box-shadow: 0 0 20px rgba(60, 145, 230, 0.3); }
      50% { box-shadow: 0 0 30px rgba(60, 145, 230, 0.6); }
    }

    /* ============ BACK TO TOP ============ */
    #toTop {
      position: fixed;
      right: 2rem;
      bottom: 2rem;
      z-index: 1000;
      display: none;
      border-radius: 50%;
      width: 60px;
      height: 60px;
      background: var(--gradient-primary);
      border: none;
      color: white;
      box-shadow: var(--shadow-glow);
      transition: all 0.3s ease;
    }

    #toTop:hover {
      background: var(--gradient-hover);
      transform: translateY(-5px) scale(1.1);
      box-shadow: var(--shadow-large);
    }

    .retreat-image-card {
      background: #fff;
      border-radius: 24px;
      box-shadow: var(--shadow-large);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .retreat-image-wrap {
      position: relative;
      width: 100%;
      padding-top: 58%; /* responsive aspect ratio */
      background: linear-gradient(135deg, #1A202C 0%, #2D3748 100%);
    }

    .retreat-image-wrap img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }


    /* ============ FOOTER ============ */
    footer {
      background: linear-gradient(135deg, var(--ink) 0%, var(--ink-light) 100%);
      color: white;
      position: relative;
    }

    footer::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: var(--gradient-primary);
    }

    footer a {
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      transition: all 0.3s ease;
    }

    footer a:hover {
      color: var(--accent-light);
      transform: translateY(-2px);
    }

    .social-links i {
      font-size: 1.5rem;
      transition: all 0.3s ease;
    }

    .social-links i:hover {
      color: var(--orange-light);
      transform: scale(1.2);
    }

    /* ============ RESPONSIVE ============ */
    @media (max-width: 768px) {
      .hero-hero {
        padding: 2rem 1rem;
      }

      .hero-title {
        font-size: 2.5rem;
      }
      
      .hero-description {
        font-size: 1.1rem;
      }

      .hero-buttons {
        padding: 0 1rem;
      }

      .btn-cta {
        width: 100%;
        margin-bottom: 0.75rem;
        padding: 0.75rem 1.5rem;
        font-size: 0.9rem;
      }
      
      .contact-form {
        padding: 2rem;
        margin: 0;
      }
      
      /* Make contact section full width on mobile */
      #contact .col-lg-8 {
        max-width: 100%;
        padding-left: 0;
        padding-right: 0;
      }
    }

    /* ============ UTILITIES ============ */
    .text-shadow {
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
    }

    .backdrop-blur {
      backdrop-filter: blur(10px);
    }

    .glass {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* Loading animation */
    .loading {
      opacity: 0;
      transform: translateY(30px);
      transition: all 0.6s ease;
    }

    .loading.loaded {
      opacity: 1;
      transform: translateY(0);
    }
  </style>
</head>
<body>
  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="#home">
        <span class="text-gradient">Bro. Dr. Dan O. Asiamah</span>
      </a>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
        <i class="bx bx-menu fs-3"></i>
      </button>
      <div id="nav" class="collapse navbar-collapse">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="#events">Events</a></li>
          <li class="nav-item"><a class="nav-link" href="#sermons">Sermons</a></li>
          <li class="nav-item"><a class="nav-link" href="#posts">Posts</a></li>
          <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
          <li class="nav-item"><a class="nav-link" href="#retreat">Retreat</a></li>
           <!-- Add this admin icon link -->
          <li class="nav-item">
            <a class="nav-link admin-link px-2" href="admin/login.php" title="Admin" aria-label="Admin login">
              <i class="bx bxs-lock-alt" aria-hidden="true"></i>
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- HERO SECTION -->
  <header id="home" class="hero-hero" style="--hero-url: url('<?php echo htmlspecialchars($home_content['image_path']); ?>');">
    <div class="container">
      <div class="row">
        <div class="col-lg-10">
          <div class="hero-content">
            <h1 class="hero-title">
              <?php echo htmlspecialchars($home_content['title']); ?>
            </h1>

            <p class="hero-description">
              <?php echo htmlspecialchars($home_content['description']); ?>
            </p>

            <ul class="list-unstyled hero-badges mb-4">
              <?php foreach ($bullet_points as $point): ?>
                <li>
                  <i class="bx bxs-check-circle fs-5"></i>
                  <span><?php echo htmlspecialchars($point); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>

            <div class="d-flex flex-wrap hero-buttons">
              <a href="<?php echo htmlspecialchars($home_content['button1_link']); ?>" 
                 class="btn btn-cta btn-cta-primary pulse-glow">
                <i class="bx bxs-microphone me-2"></i><?php echo htmlspecialchars($home_content['button1_text']); ?>
              </a>
              <a href="<?php echo htmlspecialchars($home_content['button2_link']); ?>" 
                 class="btn btn-cta btn-cta-outline">
                <i class="bx bxs-calendar me-2"></i><?php echo htmlspecialchars($home_content['button2_text']); ?>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- EVENTS SECTION -->
  <section id="events" class="py-5 section-muted">
    <div class="container">
      <div class="row mb-5">
        <div class="col-lg-8 mx-auto text-center" data-aos="fade-up">
          <h2 class="section-title text-gradient">Upcoming Events</h2>
          <p class="lead text-muted">Join us for powerful revivals, conferences, and ministry gatherings across Ghana and beyond.</p>
        </div>
      </div>

      <div class="row g-4">
        <?php
        require_once 'includes/db.php';
        $stmt = $pdo->prepare("SELECT * FROM events WHERE status IN ('Upcoming', 'Ongoing') ORDER BY event_date ASC, event_time ASC");
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($events) > 0) {
          $delay = 0;
          foreach ($events as $event) {
            $badge_class = match($event['event_type']) {
              'Revival' => 'badge-revival',
              'Conference' => 'badge-conference', 
              'Youth' => 'badge-youth',
              default => 'bg-secondary'
            };

            $event_date = date('d M Y', strtotime($event['event_date']));
            $event_time = !empty($event['event_time']) ? date('g:i A', strtotime($event['event_time'])) : '';

            echo '<div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="' . $delay . '">';
              echo '<div class="card h-100 loading">';
                echo '<div class="card-body d-flex flex-column">';
                  echo '<div class="d-flex justify-content-between align-items-start mb-3">';
                    echo '<span class="badge rounded-pill ' . $badge_class . ' px-3 py-2">' . htmlspecialchars($event['event_type']) . '</span>';
                    echo '<div class="text-end small text-muted">';
                      echo '<div class="fw-bold">' . $event_date . '</div>';
                      if (!empty($event_time)) echo '<div>' . $event_time . '</div>';
                    echo '</div>';
                  echo '</div>';
                  
                  echo '<h5 class="card-title text-shadow">' . htmlspecialchars($event['title']) . '</h5>';
                  echo '<div class="d-flex align-items-center mb-3 text-muted">';
                    echo '<i class="bx bxs-map-pin text-primary me-2"></i>';
                    echo '<span>' . htmlspecialchars($event['location']) . '</span>';
                  echo '</div>';
                  echo '<p class="card-text text-secondary flex-grow-1">' . htmlspecialchars($event['description']) . '</p>';

                  if ($event['status'] == 'Completed') {
                    echo '<button class="btn btn-outline-secondary disabled">Event Completed</button>';
                  } else {
                    echo '<a class="btn btn-gradient" href="' . htmlspecialchars($event['action_link']) . '">';
                    echo '<i class="bx bx-right-arrow-alt me-1"></i>' . htmlspecialchars($event['action_text']);
                    echo '</a>';
                  }
                echo '</div>';
              echo '</div>';
            echo '</div>';
            $delay += 100;
          }
        } else {
          echo '<div class="col-12" data-aos="fade-up">';
            echo '<div class="text-center py-5 glass rounded-4">';
              echo '<i class="bx bxs-calendar-event display-1 text-muted mb-3"></i>';
              echo '<h4 class="text-muted">No Upcoming Events</h4>';
              echo '<p class="text-muted">Check back soon for new ministry events and revivals!</p>';
            echo '</div>';
          echo '</div>';
        }
        ?>

        <!-- Past Events Section -->
        <?php
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM events WHERE status = 'Completed'");
        $stmt->execute();
        $past_events_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($past_events_count > 0) {
          echo '<div class="col-12 text-center mt-5" data-aos="fade-up">';
            echo '<button class="btn btn-outline-primary btn-lg" type="button" data-bs-toggle="collapse" data-bs-target="#pastEvents">';
              echo '<i class="bx bx-history me-2"></i>View Past Events (' . intval($past_events_count) . ')';
            echo '</button>';
          echo '</div>';

          echo '<div class="collapse mt-4" id="pastEvents">';
            echo '<div class="row g-4">';
              $stmt = $pdo->prepare("SELECT * FROM events WHERE status = 'Completed' ORDER BY event_date DESC LIMIT 6");
              $stmt->execute();
              $past_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

              foreach ($past_events as $event) {
                $event_date = date('d M Y', strtotime($event['event_date']));
                $event_time = !empty($event['event_time']) ? date('g:i A', strtotime($event['event_time'])) : '';

                echo '<div class="col-md-6 col-lg-4">';
                  echo '<div class="card h-100 opacity-75">';
                    echo '<div class="card-body">';
                      echo '<div class="d-flex justify-content-between mb-3">';
                        echo '<span class="badge bg-secondary rounded-pill">' . htmlspecialchars($event['event_type']) . '</span>';
                        echo '<span class="small text-muted">' . $event_date . '</span>';
                      echo '</div>';
                      echo '<h6 class="card-title">' . htmlspecialchars($event['title']) . '</h6>';
                      echo '<p class="small text-muted mb-2"><i class="bx bx-map-pin me-1"></i>' . htmlspecialchars($event['location']) . '</p>';
                      echo '<p class="small text-secondary">' . htmlspecialchars($event['description']) . '</p>';
                    echo '</div>';
                  echo '</div>';
                echo '</div>';
              }
            echo '</div>';
          echo '</div>';
        }
        ?>
      </div>
    </div>
  </section>

  <!-- SERMONS SECTION -->
  <section id="sermons" class="py-5">
    <div class="container">
      <div class="row mb-5">
        <div class="col-lg-8 mx-auto text-center" data-aos="fade-up">
          <h2 class="section-title text-gradient">Sermons & Teachings</h2>
          <p class="lead text-muted">Watch powerful messages that transform lives and strengthen faith communities.</p>
        </div>
      </div>

      <div class="row g-4">
        <?php
        require_once 'includes/db.php';
        $stmt = $pdo->prepare('SELECT * FROM sermon_videos ORDER BY display_order, created_at DESC LIMIT 9');
        $stmt->execute();
        $video_sermons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($video_sermons) > 0) {
          $delay = 0;
          foreach ($video_sermons as $video) {
            echo '<div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="' . $delay . '">';
              echo '<div class="card h-100 loading">';

                // Media section
                echo '<div class="sermon-media-fixed">';
                  if (!empty($video['thumbnail_path'])) {
                    echo '<img src="' . htmlspecialchars($video['thumbnail_path']) . '" alt="' . htmlspecialchars($video['title']) . '">';
                  }
                echo '</div>';

                echo '<div class="card-body d-flex flex-column">';
                  echo '<h5 class="card-title text-shadow">' . htmlspecialchars($video['title']) . '</h5>';

                  if (!empty($video['description'])) {
                    echo '<p class="card-text text-secondary mb-3">' . htmlspecialchars($video['description']) . '</p>';
                  }

                  // Video player section
                  if (!empty($video['short_video_file'])) {
                    $poster = !empty($video['thumbnail_path']) ? ' poster="' . htmlspecialchars($video['thumbnail_path']) . '"' : '';
                    echo '<div class="sermon-media-fixed mb-3">';
                      echo '<video controls preload="metadata" class="rounded"' . $poster . '>';
                        echo '<source src="' . htmlspecialchars($video['short_video_file']) . '" type="video/mp4">';
                        echo 'Your browser does not support the video tag.';
                      echo '</video>';
                    echo '</div>';
                  } else if (!empty($video['youtube_video_id'])) {
                    echo '<div class="sermon-media-fixed mb-3">';
                      echo '<iframe src="https://www.youtube.com/embed/' . htmlspecialchars($video['youtube_video_id']) . '" title="' . htmlspecialchars($video['title']) . '" allowfullscreen loading="lazy"></iframe>';
                    echo '</div>';
                  }

                  // Action buttons
                  echo '<div class="mt-auto">';
                    if (!empty($video['full_video_url'])) {
                      echo '<a class="btn btn-gradient w-100" target="_blank" rel="noopener" href="' . htmlspecialchars($video['full_video_url']) . '">';
                        echo '<i class="bx bx-play-circle me-2"></i>Watch Full Sermon';
                      echo '</a>';
                    }
                  echo '</div>';

                echo '</div>';
              echo '</div>';
            echo '</div>';
            $delay += 150;
          }
        } else {
          echo '<div class="col-12" data-aos="fade-up">';
            echo '<div class="text-center py-5 glass rounded-4">';
              echo '<i class="bx bxs-video-recording display-1 text-muted mb-3"></i>';
              echo '<h4 class="text-muted">No Sermons Available</h4>';
              echo '<p class="text-muted">New sermon videos will be uploaded soon. Stay tuned!</p>';
            echo '</div>';
          echo '</div>';
        }
        ?>
      </div>
    </div>
  </section>

  <!-- POSTS SECTION -->
  <section id="posts" class="py-5 section-muted">
    <div class="container">
      <div class="row mb-5">
        <div class="col-lg-8 mx-auto text-center" data-aos="fade-up">
          <h2 class="section-title text-gradient">Ministry Posts & Updates</h2>
          <p class="lead text-muted">Read the latest articles, testimonies, and ministry updates from the field.</p>
        </div>
      </div>

      <div class="row g-4">
        <?php
        $stmt = $pdo->prepare("SELECT id, title, excerpt, image_path, slug, created_at FROM posts WHERE status='Published' ORDER BY display_order, created_at DESC LIMIT 9");
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($posts) > 0) {
          $delay = 0;
          foreach ($posts as $p) {
            $date = date('d M Y', strtotime($p['created_at']));
            $link = !empty($p['slug']) ? 'post.php?slug='.urlencode($p['slug']) : 'post.php?id='.$p['id'];
            
            echo '<div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="' . $delay . '">';
              echo '<article class="card h-100 loading">';
                if (!empty($p['image_path'])) {
                  echo '<div class="position-relative overflow-hidden">';
                    echo '<img class="card-img-top" style="height: 200px; object-fit: cover;" src="'.htmlspecialchars($p['image_path']).'" alt="'.htmlspecialchars($p['title']).'">';
                    echo '<div class="position-absolute top-0 start-0 m-3">';
                      echo '<span class="badge glass text-white px-3 py-2">' . $date . '</span>';
                    echo '</div>';
                  echo '</div>';
                }
                
                echo '<div class="card-body d-flex flex-column">';
                  if (empty($p['image_path'])) {
                    echo '<div class="mb-2"><span class="badge bg-primary rounded-pill">' . $date . '</span></div>';
                  }
                  echo '<h5 class="card-title text-shadow">' . htmlspecialchars($p['title']) . '</h5>';
                  
                  if (!empty($p['excerpt'])) {
                    echo '<p class="card-text text-secondary flex-grow-1">' . htmlspecialchars($p['excerpt']) . '</p>';
                  }
                  
                  echo '<div class="mt-auto pt-3">';
                    echo '<a class="btn btn-outline-primary" href="'.htmlspecialchars($link).'">';
                      echo '<i class="bx bx-book-open me-2"></i>Read Full Article';
                    echo '</a>';
                  echo '</div>';
                echo '</div>';
              echo '</article>';
            echo '</div>';
            $delay += 100;
          }
        } else {
          echo '<div class="col-12" data-aos="fade-up">';
            echo '<div class="text-center py-5 glass rounded-4">';
              echo '<i class="bx bxs-news display-1 text-muted mb-3"></i>';
              echo '<h4 class="text-muted">No Posts Available</h4>';
              echo '<p class="text-muted">New ministry updates and articles will be published soon!</p>';
            echo '</div>';
          echo '</div>';
        }
        ?>
      </div>
    </div>
  </section>

  <!-- CONTACT SECTION -->
  <section id="contact" class="py-5">
    <div class="container-fluid px-md-5">
      <div class="row mb-5">
        <div class="col-lg-8 mx-auto text-center px-3" data-aos="fade-up">
          <h2 class="section-title text-gradient">Get in Touch</h2>
          <p class="lead text-muted">Connect with us for revival requests, prayer needs, or ministry inquiries. We'd love to hear from you!</p>
        </div>
      </div>

      <?php if (!empty($contact_alert)): ?>
        <div class="row mb-4" data-aos="fade-up">
          <div class="col-lg-8 mx-auto px-3">
            <div class="alert alert-<?php echo $contact_alert['type']==='success'?'success':'danger'; ?> alert-dismissible fade show glass" role="alert">
              <i class="bx <?php echo $contact_alert['type']==='success'?'bxs-check-circle':'bxs-error-circle'; ?> me-2"></i>
              <?php echo htmlspecialchars($contact_alert['text']); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="row">
        <div class="col-lg-8 mx-auto px-3" data-aos="fade-up" data-aos-delay="200">
          <div class="contact-form">
            <form method="post" action="#contact" novalidate>
              <input type="hidden" name="contact_submit" value="1">
              
              <div class="row g-4">
                <div class="col-md-6">
                  <label class="form-label">
                    <i class="bx bx-user me-2 text-primary"></i>Full Name <span class="text-danger">*</span>
                  </label>
                  <input type="text" class="form-control" name="name" required 
                         value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                         placeholder="Enter your full name">
                </div>
                
                <div class="col-md-6">
                  <label class="form-label">
                    <i class="bx bx-envelope me-2 text-primary"></i>Email Address <span class="text-danger">*</span>
                  </label>
                  <input type="email" class="form-control" name="email" required 
                         value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                         placeholder="your.email@example.com">
                </div>
                
                <div class="col-md-6">
                  <label class="form-label">
                    <i class="bx bx-phone me-2 text-primary"></i>Phone Number
                  </label>
                  <input type="tel" class="form-control" name="phone" 
                         value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                         placeholder="+233 XX XXX XXXX">
                </div>
                
                <div class="col-md-6">
                  <label class="form-label">
                    <i class="bx bx-map-pin me-2 text-primary"></i>Location
                  </label>
                  <input type="text" class="form-control" name="location" 
                         value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                         placeholder="City, Region, Country">
                </div>
                
                <div class="col-md-8">
                  <label class="form-label">
                    <i class="bx bx-category me-2 text-primary"></i>Message Type <span class="text-danger">*</span>
                  </label>
                  <select class="form-select" name="type" required>
                    <option value="">Choose message type...</option>
                    <?php
                      $types = [
                        'First-time visitor' => 'First-time Visitor 👋',
                        'Revival request' => 'Revival Request ⛪',
                        'Invitation to preach' => 'Invitation to Preach 📖',
                        'General enquiry' => 'General Enquiry 💬'
                      ];
                      $sel = $_POST['type'] ?? '';
                      foreach ($types as $value => $display) {
                        $selected = ($sel === $value) ? 'selected' : '';
                        echo '<option value="'.htmlspecialchars($value).'" '.$selected.'>'.htmlspecialchars($display).'</option>';
                      }
                    ?>
                  </select>
                </div>
                
                <div class="col-md-4 d-flex align-items-end">
                  <div class="form-check glass p-3 rounded-3 w-100">
                    <input class="form-check-input" type="checkbox" id="firstTimer" name="first_timer" 
                           <?php echo !empty($_POST['first_timer'])?'checked':''; ?>>
                    <label class="form-check-label fw-bold" for="firstTimer">
                      <i class="bx bx-star me-1"></i>First-time visitor
                    </label>
                  </div>
                </div>
                
                <div class="col-12">
                  <label class="form-label">
                    <i class="bx bx-message-detail me-2 text-primary"></i>Your Message <span class="text-danger">*</span>
                  </label>
                  <textarea class="form-control" rows="6" name="message" required 
                            placeholder="Share your message, prayer request, or inquiry with us..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>
                
                <div class="col-12 text-center">
                  <button type="submit" class="btn btn-gradient btn-lg px-5 pulse-glow">
                    <i class="bx bx-send me-2"></i>Send Message
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- RETREAT BOOKING SECTION -->
<section id="retreat" class="py-5 section-muted">
  <div class="container">
    <div class="row mb-5">
      <div class="col-lg-8 mx-auto text-center" data-aos="fade-up">
        <h2 class="section-title text-gradient">Retreat Center Booking</h2>
        <p class="lead text-muted">Book our serene retreat center for personal renewal, group prayer, and ministry gatherings.</p>
      </div>
    </div>

    <?php if (!empty($retreat_alert)): ?>
      <div class="row mb-4" data-aos="fade-up">
        <div class="col-lg-10 mx-auto">
          <div class="alert alert-<?php echo $retreat_alert['type']==='success'?'success':'danger'; ?> alert-dismissible fade show glass" role="alert">
            <i class="bx <?php echo $retreat_alert['type']==='success'?'bxs-check-circle':'bxs-error-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($retreat_alert['text']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="row align-items-stretch g-4">
      <!-- Image / Info side -->
      <div class="col-lg-6" data-aos="fade-right">
        <div class="retreat-image-card h-100">
          <div class="retreat-image-wrap">
            <!-- Replace with your actual image path -->
            <img src="./images/retreat-center.jpg" alt="Retreat Center" />
          </div>
          <div class="p-4">
            <h5 class="mb-2">A Quiet Place to Pray & Reflect</h5>
            <p class="text-secondary mb-3">
              Our retreat center offers peaceful grounds, simple lodging, and spaces for prayer, study, and fellowship.
            </p>
            <ul class="list-unstyled small text-muted mb-0">
              <li class="mb-2"><i class="bx bxs-check-circle text-success me-2"></i> Comfortable rooms & meeting hall</li>
              <li class="mb-2"><i class="bx bxs-check-circle text-success me-2"></i> Chapel & outdoor prayer areas</li>
              <li class="mb-2"><i class="bx bxs-check-circle text-success me-2"></i> Groups and individual retreats</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Form side -->
      <div class="col-lg-6" data-aos="fade-left">
        <div class="contact-form h-100">
          <form method="post" action="#retreat" novalidate>
            <input type="hidden" name="retreat_submit" value="1">

            <div class="row g-4">
              <div class="col-md-6">
                <label class="form-label">
                  <i class="bx bx-user me-2 text-primary"></i>Full Name <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control" name="r_name" required
                       value="<?php echo htmlspecialchars($_POST['r_name'] ?? ''); ?>"
                       placeholder="Your full name">
              </div>

              <div class="col-md-6">
                <label class="form-label">
                  <i class="bx bx-envelope me-2 text-primary"></i>Email
                  <!-- no required mark -->
                </label>
                <input type="email" class="form-control" name="r_email"
                      value="<?php echo htmlspecialchars($_POST['r_email'] ?? ''); ?>"
                      placeholder="your@email.com">
              </div>

              <div class="col-md-6">
                <label class="form-label">
                  <i class="bx bx-phone me-2 text-primary"></i>Phone <span class="text-danger">*</span>
                </label>
                <input type="tel" class="form-control" name="r_phone" required
                      value="<?php echo htmlspecialchars($_POST['r_phone'] ?? ''); ?>"
                      placeholder="+233 XX XXX XXXX"
                      pattern="^[0-9+\-\s()]{7,}$" title="Enter a valid phone number">
              </div>

              <div class="col-md-3">
                <label class="form-label">
                  <i class="bx bx-calendar me-2 text-primary"></i>Check-in <span class="text-danger">*</span>
                </label>
                <input type="date" class="form-control" name="r_checkin" required
                       value="<?php echo htmlspecialchars($_POST['r_checkin'] ?? ''); ?>">
              </div>

              <div class="col-md-3">
                <label class="form-label">
                  <i class="bx bx-calendar me-2 text-primary"></i>Check-out <span class="text-danger">*</span>
                </label>
                <input type="date" class="form-control" name="r_checkout" required
                       value="<?php echo htmlspecialchars($_POST['r_checkout'] ?? ''); ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">
                  <i class="bx bx-group me-2 text-primary"></i>Guests
                </label>
                <input type="number" class="form-control" name="r_guests" min="1" max="200"
                       value="<?php echo htmlspecialchars($_POST['r_guests'] ?? '1'); ?>">
              </div>

              <div class="col-md-8">
                <label class="form-label">
                  <i class="bx bx-category me-2 text-primary"></i>Purpose <span class="text-danger">*</span>
                </label>
                <select class="form-select" name="r_purpose" required>
                  <option value="">Select purpose...</option>
                  <?php
                    $purposes = [
                      'Personal retreat' => 'Personal Retreat',
                      'Group retreat'    => 'Group / Ministry Retreat',
                      'Leaders meeting'  => 'Leaders Meeting',
                      'Prayer & fasting' => 'Prayer & Fasting',
                      'Other'            => 'Other'
                    ];
                    $selp = $_POST['r_purpose'] ?? '';
                    foreach ($purposes as $val => $label) {
                      $selected = ($selp === $val) ? 'selected' : '';
                      echo '<option value="'.htmlspecialchars($val).'" '.$selected.'>'.htmlspecialchars($label).'</option>';
                    }
                  ?>
                </select>
              </div>

              <div class="col-12">
                <label class="form-label">
                  <i class="bx bx-message-detail me-2 text-primary"></i>Notes / Special Requests
                </label>
                <textarea class="form-control" rows="5" name="r_notes"
                          placeholder="Any special needs, schedule, or details..."><?php echo htmlspecialchars($_POST['r_notes'] ?? ''); ?></textarea>
              </div>

              <div class="col-12 text-center">
                <button type="submit" class="btn btn-gradient btn-lg px-5">
                  <i class="bx bx-send me-2"></i>Request Booking
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</section>


  <!-- FOOTER -->
  <footer class="py-5">
    <div class="container">
      <div class="row gy-4 align-items-center">
        <div class="col-md-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bx bxs-book-heart fs-2 text-primary floating"></i>
            <span class="fs-5 fw-bold">Bro. Dr. Dan O. Asiamah</span>
          </div>
          <p class="small text-light opacity-75 mb-0">
            © 2025 All rights reserved. Spreading God's word across Ghana and beyond.
          </p>
        </div>
        
        <div class="col-md-4 text-center">
          <div class="d-flex justify-content-center gap-4 small">
            <a href="#home">Home</a>
            <a href="#events">Events</a>
            <a href="#sermons">Sermons</a>
            <a href="#posts">Posts</a>
            <a href="#contact">Contact</a>
          </div>
        </div>
        
        <div class="col-md-4 text-md-end">
          <div class="social-links d-flex justify-content-md-end justify-content-center gap-3">
          
            <a href="https://web.facebook.com/DanOwusuAsiamah/?_rdc=1&_rdr#"
              class="text-decoration-none"
              title="Facebook"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Facebook (opens in new tab)">
              <i class="bx bxl-facebook-circle"></i>
            </a>


            <a href="https://www.youtube.com/c/TakoradiCentralChurchofChrist" 
              class="text-decoration-none" title="YouTube" target="_blank"
              rel="noopener noreferrer">
              <i class="bx bxl-youtube"></i>
            </a>
            <a href="#" class="text-decoration-none" title="TikTok">
              <i class="bx bxl-tiktok"></i>
            </a>

             <!-- 🎵 Spotify link -->
            <a href="https://open.spotify.com/show/yourSpotifyLinkHere"
              class="text-decoration-none"
              title="Spotify"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Spotify (opens in new tab)">
              <i class="bx bxl-spotify"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <!-- BACK TO TOP -->
  <button id="toTop" class="floating" aria-label="Back to top" title="Back to top">
    <i class="bx bx-chevron-up fs-4"></i>
  </button>

  <!-- SCRIPTS -->
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- AOS Animation Library -->
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

  <script>
    // Initialize AOS animations
    AOS.init({
      duration: 800,
      easing: 'ease-out-quart',
      once: true,
      offset: 50
    });

    // Navbar effects and back to top
    const nav = document.querySelector('.navbar');
    const toTop = document.getElementById('toTop');
    
    const handleScroll = () => {
      const scrollY = window.scrollY || document.documentElement.scrollTop;
      
      // Navbar effects
      nav.classList.toggle('scrolled', scrollY > 50);
      
      // Back to top visibility
      toTop.style.display = scrollY > 600 ? 'flex' : 'none';
    };

    window.addEventListener('scroll', handleScroll);
    handleScroll(); // Initial call

    // Active navigation highlighting
    const sections = [...document.querySelectorAll('section[id], header[id]')];
    const navLinks = [...document.querySelectorAll('.navbar .nav-link')];
    
    const updateActiveNav = () => {
      const scrollY = window.scrollY + 150;
      let currentSection = sections[0]?.id || 'home';
      
      sections.forEach(section => {
        if (scrollY >= section.offsetTop) {
          currentSection = section.id;
        }
      });
      
      navLinks.forEach(link => {
        const href = link.getAttribute('href') || '';
        const targetId = href.startsWith('#') ? href.substring(1) : '';
        link.classList.toggle('active', targetId === currentSection);
      });
    };

    window.addEventListener('scroll', updateActiveNav);
    window.addEventListener('load', updateActiveNav);

    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          const navHeight = nav.offsetHeight;
          const targetPosition = target.offsetTop - navHeight - 20;
          
          window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
          });
        }
      });
    });

    // Back to top functionality
    toTop?.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Loading animation for cards
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const cardObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('loaded');
        }
      });
    }, observerOptions);

    document.querySelectorAll('.loading').forEach(card => {
      cardObserver.observe(card);
    });

    // Enhanced form interactions
    const formControls = document.querySelectorAll('.form-control, .form-select');
    
    formControls.forEach(control => {
      // Focus effects
      control.addEventListener('focus', function() {
        this.parentElement.style.transform = 'scale(1.02)';
        this.parentElement.style.transition = 'transform 0.2s ease';
      });
      
      control.addEventListener('blur', function() {
        this.parentElement.style.transform = 'scale(1)';
      });
    });

    // Enhanced hover effects for cards
    const cards = document.querySelectorAll('.card');
    
    cards.forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-8px) scale(1.02)';
        this.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
      });
      
      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
      });
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
      setTimeout(() => {
        const closeButton = alert.querySelector('.btn-close');
        if (closeButton) closeButton.click();
      }, 5000);
    });

    // Lazy loading for images and videos
    const lazyElements = document.querySelectorAll('img, video, iframe');
    
    const lazyObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const element = entry.target;
          
          // Add loading animation
          element.style.opacity = '0';
          element.style.transform = 'scale(0.9)';
          element.style.transition = 'all 0.6s ease';
          
          setTimeout(() => {
            element.style.opacity = '1';
            element.style.transform = 'scale(1)';
          }, 100);
          
          lazyObserver.unobserve(element);
        }
      });
    });

    lazyElements.forEach(element => {
      lazyObserver.observe(element);
    });

    // Dynamic typing effect for hero title (optional enhancement)
    const heroTitle = document.querySelector('.hero-title');
    if (heroTitle) {
      const text = heroTitle.textContent;
      heroTitle.textContent = '';
      
      let i = 0;
      const typeWriter = () => {
        if (i < text.length) {
          heroTitle.textContent += text.charAt(i);
          i++;
          setTimeout(typeWriter, 50);
        }
      };
      
      // Start typing effect after page load
      setTimeout(typeWriter, 1000);
    }

    // Parallax effect for hero section
    const hero = document.querySelector('.hero-hero');
    if (hero) {
      window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.5;
        hero.style.transform = `translate3d(0, ${rate}px, 0)`;
      });
    }

    // Add entrance animations on page load
    window.addEventListener('load', () => {
      document.body.classList.add('loaded');
      
      // Trigger loading animations for visible elements
      document.querySelectorAll('.loading').forEach((element, index) => {
        setTimeout(() => {
          element.classList.add('loaded');
        }, index * 100);
      });
    });

    console.log('🙏 Ministry website loaded successfully! May God bless this digital ministry.');
  </script>
</body>
</html>
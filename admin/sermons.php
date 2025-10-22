<?php
/**
 * sermons.php (Admin) — VIDEO ONLY
 * Theming aligned with dashboard/home/events. Backend logic unchanged.
 */
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header('Location: login.php');
  exit();
}
require_once '../includes/db.php';

$message = '';
$message_type = '';

// Helpers (unchanged)
function ensure_dir($dir) {
  if (!is_dir($dir)) { mkdir($dir, 0775, true); }
}
function safe_unlink($path) {
  if ($path && file_exists($path)) { @unlink($path); }
}
function upload_error_message($code) {
  switch ($code) {
    case UPLOAD_ERR_OK: return 'OK';
    case UPLOAD_ERR_INI_SIZE: return 'The uploaded file exceeds the server limit (upload_max_filesize).';
    case UPLOAD_ERR_FORM_SIZE: return 'The uploaded file exceeds the form limit (MAX_FILE_SIZE).';
    case UPLOAD_ERR_PARTIAL: return 'The file was only partially uploaded.';
    case UPLOAD_ERR_NO_FILE: return 'No file was uploaded.';
    case UPLOAD_ERR_NO_TMP_DIR: return 'Missing a temporary folder on the server.';
    case UPLOAD_ERR_CANT_WRITE: return 'Failed to write file to disk (permissions).';
    case UPLOAD_ERR_EXTENSION: return 'A PHP extension stopped the file upload.';
    default: return 'Unknown upload error.';
  }
}

$thumb_dir = '../uploads/thumbnails/sermons/';
$video_dir = '../uploads/videos/shorts/';
ensure_dir($thumb_dir);
ensure_dir($video_dir);

/* -----------------------------
   Add new VIDEO sermon (unchanged)
------------------------------*/
if (isset($_POST['add_video'])) {
  $title          = trim($_POST['title'] ?? '');
  $description    = trim($_POST['description'] ?? '');
  $youtube_id     = trim($_POST['youtube_id'] ?? ''); // optional
  $full_video_url = trim($_POST['full_video_url'] ?? ''); // optional

  if ($title === '') { $message = 'Title is required.'; $message_type = 'error'; }

  // Thumbnail (required)
  $thumbnail_path_rel = null;
  if ($message_type !== 'error') {
    if (!isset($_FILES['thumbnail']) || empty($_FILES['thumbnail']['name'])) {
      $message = 'Please choose a thumbnail image.'; $message_type = 'error';
    } elseif ($_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) {
      $message = upload_error_message($_FILES['thumbnail']['error']); $message_type = 'error';
    } else {
      $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
      $allowed_img = ['jpg','jpeg','png','webp'];
      if (!in_array($ext, $allowed_img)) { $message = 'Thumbnail must be JPG, PNG, or WEBP.'; $message_type = 'error'; }
      else {
        $new = uniqid('thumb_').'.'.$ext;
        $dest = $thumb_dir.$new; // filesystem path
        if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $dest)) {
          $thumbnail_path_rel = 'uploads/thumbnails/sermons/'.$new; // relative URL stored
        } else { $message = 'Failed to upload thumbnail (permissions?).'; $message_type = 'error'; }
      }
    }
  }

  // Short video (optional)
  $short_video_rel = null;
  if ($message_type !== 'error' && isset($_FILES['short_video']) && !empty($_FILES['short_video']['name'])) {
    if ($_FILES['short_video']['error'] !== UPLOAD_ERR_OK) {
      $message = upload_error_message($_FILES['short_video']['error']); $message_type = 'error';
    }
    if ($message_type !== 'error' && !is_writable($video_dir)) {
      $message = 'Uploads folder is not writable: ' . $video_dir . ' — check permissions (e.g., 755).'; $message_type = 'error';
    }
    $vext = strtolower(pathinfo($_FILES['short_video']['name'], PATHINFO_EXTENSION));
    $allowed_vid = ['mp4','webm','ogg','m4v','mov'];
    if ($message_type !== 'error' && !in_array($vext, $allowed_vid)) { $message = 'Video must be MP4, WEBM, OGG, M4V, or MOV.'; $message_type = 'error'; }
    if ($message_type !== 'error' && !is_uploaded_file($_FILES['short_video']['tmp_name'])) { $message = 'Upload failed: temporary file missing (likely server limit).'; $message_type = 'error'; }
    if ($message_type !== 'error') {
      $softCap = 200 * 1024 * 1024; // 200 MB
      if (!empty($_FILES['short_video']['size']) && $_FILES['short_video']['size'] > $softCap) { $message = 'Video is larger than 200MB.'; $message_type = 'error'; }
    }
    if ($message_type !== 'error') {
      $newv = uniqid('sv_').'.'.$vext;
      $vdest = $video_dir.$newv;
      if (move_uploaded_file($_FILES['short_video']['tmp_name'], $vdest)) {
        $short_video_rel = 'uploads/videos/shorts/'.$newv;
      } else { $message = 'Failed to move uploaded file. Check permissions on: ' . $video_dir; $message_type = 'error'; }
    }
  }

  if ($message_type !== 'error') {
    $stmt = $pdo->prepare('INSERT INTO sermon_videos (title, description, short_video_file, youtube_video_id, full_video_url, thumbnail_path) VALUES (?,?,?,?,?,?)');
    if ($stmt->execute([$title, $description, $short_video_rel, $youtube_id ?: null, $full_video_url ?: null, $thumbnail_path_rel])) {
      $message = 'Video sermon added successfully!'; $message_type = 'success';
    } else {
      safe_unlink($thumbnail_path_rel ? ('../'.$thumbnail_path_rel) : null);
      safe_unlink($short_video_rel ? ('../'.$short_video_rel) : null);
      $message = 'Error adding video sermon.'; $message_type = 'error';
    }
  }
}

/* -----------------------------
   Update VIDEO sermon (unchanged)
------------------------------*/
if (isset($_POST['update_video'])) {
  $id = (int)($_POST['video_id'] ?? 0);
  $title          = trim($_POST['title'] ?? '');
  $description    = trim($_POST['description'] ?? '');
  $youtube_id     = trim($_POST['youtube_id'] ?? '');
  $full_video_url = trim($_POST['full_video_url'] ?? '');

  $old = $pdo->prepare('SELECT * FROM sermon_videos WHERE id=?');
  $old->execute([$id]);
  $prev = $old->fetch(PDO::FETCH_ASSOC);
  if (!$prev) { $message='Video not found.'; $message_type='error'; }

  $thumbnail_path_rel = $prev['thumbnail_path'] ?? null;
  $short_video_rel    = $prev['short_video_file'] ?? null;

  // Replace thumbnail if new uploaded
  if ($message_type !== 'error' && isset($_FILES['thumbnail']) && !empty($_FILES['thumbnail']['name'])) {
    if ($_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) { $message = upload_error_message($_FILES['thumbnail']['error']); $message_type='error'; }
    $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
    $allowed_img = ['jpg','jpeg','png','webp'];
    if ($message_type !== 'error' && !in_array($ext, $allowed_img)) { $message = 'Thumbnail must be JPG, PNG, or WEBP.'; $message_type='error'; }
    if ($message_type !== 'error') {
      $new = uniqid('thumb_').'.'.$ext; $dest = $thumb_dir.$new;
      if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $dest)) {
        safe_unlink($thumbnail_path_rel ? ('../'.$thumbnail_path_rel) : null);
        $thumbnail_path_rel = 'uploads/thumbnails/sermons/'.$new;
      } else { $message='Failed to upload thumbnail.'; $message_type='error'; }
    }
  }

  // Replace short video if new uploaded
  if ($message_type !== 'error' && isset($_FILES['short_video']) && !empty($_FILES['short_video']['name'])) {
    if ($_FILES['short_video']['error'] !== UPLOAD_ERR_OK) { $message = upload_error_message($_FILES['short_video']['error']); $message_type = 'error'; }
    if ($message_type !== 'error' && !is_writable($video_dir)) { $message = 'Uploads folder is not writable: ' . $video_dir; $message_type = 'error'; }
    $vext = strtolower(pathinfo($_FILES['short_video']['name'], PATHINFO_EXTENSION));
    $allowed_vid = ['mp4','webm','ogg','m4v','mov'];
    if ($message_type !== 'error' && !in_array($vext, $allowed_vid)) { $message = 'Video must be MP4, WEBM, OGG, M4V, or MOV.'; $message_type = 'error'; }
    if ($message_type !== 'error' && !is_uploaded_file($_FILES['short_video']['tmp_name'])) { $message = 'Upload failed: temporary file missing (likely server limit).'; $message_type = 'error'; }
    if ($message_type !== 'error') {
      $newv = uniqid('sv_').'.'.$vext; $vdest = $video_dir.$newv;
      if (move_uploaded_file($_FILES['short_video']['tmp_name'], $vdest)) {
        safe_unlink($short_video_rel ? ('../'.$short_video_rel) : null);
        $short_video_rel = 'uploads/videos/shorts/'.$newv;
      } else { $message='Failed to move uploaded file.'; $message_type='error'; }
    }
  }

  if ($message_type !== 'error') {
    $stmt = $pdo->prepare('UPDATE sermon_videos SET title=?, description=?, short_video_file=?, youtube_video_id=?, full_video_url=?, thumbnail_path=? WHERE id=?');
    if ($stmt->execute([$title, $description, $short_video_rel ?: null, $youtube_id ?: null, $full_video_url ?: null, $thumbnail_path_rel ?: null, $id])) {
      $message = 'Video sermon updated successfully!'; $message_type='success';
    } else { $message='Error updating video sermon.'; $message_type='error'; }
  }
}

/* -----------------------------
   Delete VIDEO sermon (unchanged)
------------------------------*/
if (isset($_GET['delete_video'])) {
  $id = (int)$_GET['delete_video'];
  $q = $pdo->prepare('SELECT short_video_file, thumbnail_path FROM sermon_videos WHERE id=?');
  $q->execute([$id]);
  $row = $q->fetch(PDO::FETCH_ASSOC);

  $stmt = $pdo->prepare('DELETE FROM sermon_videos WHERE id=?');
  if ($stmt->execute([$id])) {
    safe_unlink(!empty($row['short_video_file']) ? ('../'.$row['short_video_file']) : null);
    safe_unlink(!empty($row['thumbnail_path']) ? ('../'.$row['thumbnail_path']) : null);
    $message='Video sermon deleted successfully!'; $message_type='success';
  } else { $message='Error deleting video sermon.'; $message_type='error'; }
}

// Fetch list (unchanged)
$video_stmt = $pdo->prepare('SELECT * FROM sermon_videos ORDER BY display_order, created_at DESC');
$video_stmt->execute();
$video_sermons = $video_stmt->fetchAll(PDO::FETCH_ASSOC);

// Edit target (unchanged)
$edit_video = null;
if (isset($_GET['edit_video'])) {
  $id = (int)$_GET['edit_video'];
  $stmt = $pdo->prepare('SELECT * FROM sermon_videos WHERE id=?');
  $stmt->execute([$id]);
  $edit_video = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Sermons</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css"/>
  <!-- PNG fallbacks (optional) -->
  <link rel="icon" type="image/png" sizes="32x32" href="../images/book-heart.png" >
  <link rel="icon" type="image/png" sizes="16x16" href="../images/book-heart.png" >

  <style>
    :root{
      /* Unified tokens */
      --brand:#3C91E6; --brand-dark:#2B6CB0;
      --accent:#3C91E6; --accent-light:#5BA3EE;
      --orange:#FD7238; --orange-light:#FF8A5B;
      --ink:#1A202C; --ink-light:#2D3748;
      --soft:#F7FAFC; --soft-dark:#EDF2F7; --white:#fff;
      --success:#48BB78; --warning:#ED8936; --danger:#F56565;

      --gradient-primary: linear-gradient(135deg, #2B6CB0 0%, #3C91E6 100%);
      --gradient-hover:   linear-gradient(135deg, #FD7238 0%, #FF8A5B 100%);
      --gradient-hero:    linear-gradient(135deg, rgba(60,145,230,0.9) 0%, rgba(43,108,176,0.85) 100%);

      --shadow-soft:   0 6px 16px rgba(0,0,0,.06);
      --shadow-medium: 0 10px 24px rgba(0,0,0,.10);
      --shadow-large:  0 20px 40px rgba(0,0,0,.12);
      --shadow-glow:   0 0 40px rgba(60,145,230,.15);
    }
    html{scroll-behavior:smooth}
    body{ background:linear-gradient(135deg, var(--soft) 0%, var(--soft-dark) 100%); color:var(--ink); font-family:'Inter',sans-serif; overflow-x:hidden; }

    /* Utilities */
    .glass{ background:rgba(255,255,255,.1); backdrop-filter: blur(10px); border:1px solid rgba(255,255,255,.2); border-radius:12px; }
    .btn-gradient{
      background: var(--gradient-primary); border:none; color:#fff; border-radius:14px; padding:.95rem 1rem; font-weight:600;
      box-shadow: var(--shadow-glow); transition: all .3s ease; position:relative; overflow:hidden;
    }
    .btn-gradient::before{
      content:''; position:absolute; top:0; left:-100%; width:100%; height:100%;
      background: linear-gradient(135deg, rgba(255,255,255,.25) 0%, rgba(255,255,255,.1) 100%);
      transition:left .6s ease;
    }
    .btn-gradient:hover::before{ left:100%; }
    .btn-gradient:hover{ background: var(--gradient-hover); transform:translateY(-2px); color:#fff; box-shadow:var(--shadow-large); }
    .btn-outline-primary{
      border:2px solid #E2E8F0; border-radius:12px; background:#fff; color:#2D3748; transition:.25s;
    }
    .btn-outline-primary:hover{
      background: var(--gradient-hover); border-color:transparent; color:#fff; transform:translateY(-1px);
    }

    /* Sidebar */
    .sidebar{
      width: 280px; min-height: 100vh; background:#fff; border-right:1px solid rgba(0,0,0,.06);
      position: fixed; left:0; top:0; z-index:100; display:flex; flex-direction:column; box-shadow:var(--shadow-soft);
    }
    .sidebar .brand{ background:var(--gradient-primary); color:#fff; padding:1rem 1.25rem; font-weight:700; font-family:'Playfair Display',serif; display:flex; align-items:center; gap:.5rem; }
    .sidebar .menu{ padding:1rem; overflow-y:auto; }
    .sidebar .nav-link{ color:var(--ink); border-radius:.7rem; font-weight:500; padding:.75rem .9rem; display:flex; align-items:center; gap:.6rem; transition:.25s ease; }
    .sidebar .nav-link:hover{ background:rgba(60,145,230,.08); color:var(--brand); transform:translateX(2px); }
    .sidebar .nav-link.active{ background:rgba(60,145,230,.14); color:var(--brand-dark); }

    /* Topbar (gradient + white text) */
    .topbar{
      height:72px; background:var(--gradient-hero); color:#fff;
      border-bottom:1px solid rgba(255,255,255,.2);
      display:flex; align-items:center; justify-content:flex-end;
      padding:0 1rem; position:fixed; top:0; right:0; left:280px; z-index:90; box-shadow:var(--shadow-medium);
    }
    .topbar .hamburger{ display:none; border:0; background:transparent; color:#fff; }
    .topbar .dropdown-toggle{ color:#fff; }
    .topbar .dropdown-toggle i{ color:#fff !important; }
    .topbar .dropdown-toggle span{ color:#fff; } /* Admin name white */

    /* Main */
    .main{ padding:1.25rem; margin-left:280px; }
    .page-content{ margin-top:84px; }

    .card{ border:0; border-radius:18px; box-shadow:var(--shadow-soft); }
    .card .card-title{ font-weight:700; }

    /* Header banner */
    .admin-hero{ background:var(--gradient-hero); color:#fff; border-radius:20px; padding:1.5rem 1.75rem; box-shadow:var(--shadow-medium); position:relative; overflow:hidden; }
    .admin-hero::after{
      content:''; position:absolute; inset:0;
      background:
        radial-gradient(800px 200px at 0% 0%, rgba(255,255,255,.15), transparent 60%),
        radial-gradient(800px 200px at 100% 100%, rgba(255,255,255,.12), transparent 60%);
      pointer-events:none;
    }

    /* Form fields */
    .form-control, .form-select{ border:2px solid #E2E8F0; border-radius:12px; padding:0.9rem 1rem; background:#FAFAFA; transition:.2s; }
    .form-control:focus, .form-select:focus{ border-color:var(--brand); box-shadow:0 0 0 .2rem rgba(60,145,230,.15); background:#fff; }
    .form-label{ font-weight:600; color:var(--ink); }

    /* Sticky save */
    .sticky-actions{ position:sticky; bottom:0; z-index:5; background:#fff; padding:1rem; border-top:1px solid #EDF2F7; border-bottom-left-radius:18px; border-bottom-right-radius:18px; }

    /* Table / thumb */
    .video-thumbnail { width: 100px; height: auto; border-radius:10px; box-shadow:var(--shadow-soft); object-fit:cover; }

    /* DataTable tweaks */
    table.dataTable thead th { border-bottom: 0; }
    table.dataTable.no-footer { border-bottom: 0; }

    @media (max-width: 992px){
      .sidebar{ transform:translateX(-100%); transition:transform .3s ease; width:100%; }
      .sidebar.open{ transform:translateX(0); }
      .topbar{ left:0; }
      .topbar .hamburger{ display:inline-block; }
      .main{ margin-left:0; }
    }
  </style>
</head>
<body>

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="brand"><i class="bx bxs-dashboard"></i><span>Admin Panel</span></div>
    <nav class="menu">
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bx bxs-dashboard"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="home.php"><i class="bx bxs-home"></i>Home/Hero</a></li>
        <li class="nav-item"><a class="nav-link" href="events.php"><i class="bx bxs-calendar"></i>Events</a></li>
        <li class="nav-item"><a class="nav-link active" href="sermons.php"><i class="bx bxs-microphone"></i>Sermons</a></li>
        <li class="nav-item"><a class="nav-link" href="posts.php"><i class="bx bxs-news"></i>Posts</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php"><i class="bx bxs-contact"></i>Contact</a></li>
         <li class="nav-item"><a class="nav-link" href="retreat_bookings.php"><i class="bx bxs-spa"></i>Retreat Bookings</a></li>
        <li class="nav-item"><a class="nav-link" href="admins.php"><i class="bx bxs-widget"></i>Admins</a></li>
      </ul>
    </nav>
  </aside>

  <!-- TOPBAR -->
  <div class="topbar">
    <button class="hamburger" id="toggleSidebar" aria-label="Toggle sidebar"><i class="bx bx-menu fs-3"></i></button>
    <div class="dropdown ms-auto">
      <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" id="adminMenu" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bx bxs-user-circle fs-3 me-2 text-white"></i>
        <span><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
      </a>
      <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="adminMenu">
        <li><a class="dropdown-item" href="admins.php"><i class="bx bx-user me-2"></i>Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bx bx-log-out me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>

  <!-- MAIN -->
  <main class="main">
    <div class="page-content">

      <!-- Page header banner -->
      <div class="admin-hero mb-4" data-aos="fade-up">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
          <div>
            <h2 class="mb-1">Sermons (Video Only)</h2>
            <div class="opacity-75">Upload thumbnails & short clips, add YouTube IDs, and link to full videos.</div>
          </div>
          <div class="d-flex gap-2">
            <a href="../index.php#sermons" target="_blank" class="btn btn-gradient">
              <i class="bx bx-show me-1"></i> View Site
            </a>
          </div>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show glass" role="alert" data-aos="fade-up">
          <?= htmlspecialchars($message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Form (top) -->
      <div class="card p-4 mb-4" data-aos="fade-up">
        <h5 class="card-title mb-4"><?= $edit_video ? 'Edit Video Sermon' : 'Add Video Sermon' ?></h5>

        <form method="POST" action="" enctype="multipart/form-data">
          <?php if ($edit_video): ?>
            <input type="hidden" name="video_id" value="<?= (int)$edit_video['id'] ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label" for="title">Title</label>
            <input class="form-control" id="title" name="title" required value="<?= $edit_video ? htmlspecialchars($edit_video['title']) : '' ?>">
          </div>

          <div class="mb-3">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= $edit_video ? htmlspecialchars($edit_video['description']) : '' ?></textarea>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="full_video_url">Full Video URL (optional)</label>
              <input class="form-control" type="url" id="full_video_url" name="full_video_url" value="<?= $edit_video ? htmlspecialchars($edit_video['full_video_url']) : '' ?>" placeholder="https://...">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="youtube_id">YouTube Video ID (optional)</label>
              <input class="form-control" id="youtube_id" name="youtube_id" value="<?= $edit_video ? htmlspecialchars($edit_video['youtube_video_id']) : '' ?>" placeholder="e.g. dQw4w9WgXcQ">
            </div>
          </div>

          <div class="mb-3 mt-3">
            <label class="form-label" for="thumbnail">Thumbnail Image (JPG/PNG/WEBP)</label>
            <input class="form-control" type="file" id="thumbnail" name="thumbnail" accept="image/*" <?= $edit_video ? '' : 'required' ?> >
            <?php if ($edit_video && !empty($edit_video['thumbnail_path'])): ?>
              <div class="mt-2">
                <img src="../<?= htmlspecialchars($edit_video['thumbnail_path']) ?>" class="video-thumbnail" alt="Current thumbnail">
              </div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label" for="short_video">Short Video (MP4/WEBM/OGG/M4V/MOV) — optional</label>
            <input class="form-control" type="file" id="short_video" name="short_video" accept="video/*">
            <?php if ($edit_video && !empty($edit_video['short_video_file'])): ?>
              <video class="mt-2" src="../<?= htmlspecialchars($edit_video['short_video_file']) ?>" controls style="max-width:220px; border-radius:12px; box-shadow:var(--shadow-soft)"></video>
            <?php endif; ?>
          </div>

          <div class="sticky-actions mt-4">
            <?php if ($edit_video): ?>
              <div class="d-grid gap-2">
                <button class="btn btn-gradient btn-lg" type="submit" name="update_video">
                  <i class="bx bx-save me-1"></i> Update Video
                </button>
                <a class="btn btn-outline-primary" href="sermons.php">Cancel</a>
              </div>
            <?php else: ?>
              <button class="btn btn-gradient btn-lg w-100" type="submit" name="add_video">
                <i class="bx bx-plus-circle me-1"></i> Add Video
              </button>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Video Sermons Table (below the form) -->
      <div class="card p-4" data-aos="fade-up" data-aos-delay="100">
        <h5 class="card-title mb-4">Video Sermons</h5>
        <?php if (count($video_sermons)>0): ?>
          <div class="table-responsive">
            <table id="videosTable" class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Thumbnail</th>
                  <th>Title</th>
                  <th>Short</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($video_sermons as $v): ?>
                <tr>
                  <td>
                    <?php if (!empty($v['thumbnail_path'])): ?>
                      <img src="../<?= htmlspecialchars($v['thumbnail_path']) ?>" class="video-thumbnail" alt="Thumb">
                    <?php endif; ?>
                  </td>
                  <td class="fw-semibold"><?= htmlspecialchars($v['title']) ?></td>
                  <td><?= !empty($v['short_video_file']) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="sermons.php?edit_video=<?= (int)$v['id'] ?>" title="Edit">
                      <i class="bx bxs-edit"></i>
                    </a>
                    <a class="btn btn-sm btn-outline-primary ms-1" href="sermons.php?delete_video=<?= (int)$v['id'] ?>" onclick="return confirm('Delete this video sermon? Media files will be removed.');" title="Delete">
                      <i class="bx bxs-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center py-4">
            <i class="bx bxs-video-recording fs-1 text-muted"></i>
            <p class="text-muted mt-2">No video sermons found. Add your first video sermon above.</p>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </main>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

  <script>
    AOS.init({ duration: 700, once: true });

    // Toastr defaults
    toastr.options = {
      closeButton: true, progressBar: true, newestOnTop: true, preventDuplicates: true,
      positionClass: "toast-top-right", timeOut: 3500, extendedTimeOut: 1500
    };

    // Mobile sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const toggleSidebar = document.getElementById('toggleSidebar');
    toggleSidebar?.addEventListener('click', () => sidebar.classList.toggle('open'));

    // DataTable
    $(function(){
      $('#videosTable').DataTable({
        responsive:true,
        ordering:true,
        searching:true,
        pageLength:10,
        language:{
          search:"_INPUT_",
          searchPlaceholder:"Search videos...",
          lengthMenu:"Show _MENU_ entries",
          info:"Showing _START_ to _END_ of _TOTAL_ entries",
          infoEmpty:"Showing 0 to 0 of 0 entries",
          infoFiltered:"(filtered from _MAX_ total entries)",
          paginate:{ first:"First", last:"Last", next:"Next", previous:"Previous" }
        },
        columnDefs:[{ orderable:false, targets:[0,3] }]
      });
    });
  </script>
</body>
</html>

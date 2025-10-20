<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
require_once '../includes/db.php';

// Handle form submission (unchanged backend logic)
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $bullet_points = json_encode(explode("\n", $_POST['bullet_points']));
    $button1_text = $_POST['button1_text'];
    $button1_link = $_POST['button1_link'];
    $button2_text = $_POST['button2_text'];
    $button2_link = $_POST['button2_link'];

    // Upload (unchanged)
    $image_path = $_POST['existing_image'];
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../images/";
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check !== false) {
            $new_filename = uniqid() . '.' . $imageFileType;
            $target_file = $target_dir . $new_filename;
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_path = './images/' . $new_filename;
                if ($_POST['existing_image'] != './images/a1.jpg') {
                    @unlink("../" . $_POST['existing_image']);
                }
            } else {
                $message = "Sorry, there was an error uploading your file.";
                $message_type = "error";
            }
        } else {
            $message = "File is not an image.";
            $message_type = "error";
        }
    }

    if ($message_type !== 'error') {
        $stmt = $pdo->prepare("UPDATE home_content SET title = ?, description = ?, image_path = ?, bullet_points = ?, button1_text = ?, button1_link = ?, button2_text = ?, button2_link = ? WHERE id = 1");
        if ($stmt->execute([$title, $description, $image_path, $bullet_points, $button1_text, $button1_link, $button2_text, $button2_link])) {
            $message = "Home content updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating home content.";
            $message_type = "error";
        }
    }
}

// Get current home content (unchanged)
$stmt = $pdo->prepare("SELECT * FROM home_content WHERE id = 1");
$stmt->execute();
$home_content = $stmt->fetch(PDO::FETCH_ASSOC);

// Bullet points for textarea (unchanged)
$bullet_points_text = '';
if ($home_content && !empty($home_content['bullet_points'])) {
    $bullet_points_array = json_decode($home_content['bullet_points'], true);
    $bullet_points_text = implode("\n", $bullet_points_array);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Home/Hero</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <!-- PNG fallbacks (optional) -->
  <link rel="icon" type="image/png" sizes="32x32" href="../images/book-heart.png" >
  <link rel="icon" type="image/png" sizes="16x16" href="../images/book-heart.png" >

  <style>
    :root{
      /* Unified tokens (match site) */
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
      --white: #fff;
      --success: #48BB78;
      --warning: #ED8936;
      --danger: #F56565;

      --gradient-primary: linear-gradient(135deg, #2B6CB0 0%, #3C91E6 100%);
      --gradient-hover: linear-gradient(135deg, #FD7238 0%, #FF8A5B 100%);
      --gradient-hero: linear-gradient(135deg, rgba(60,145,230,0.9) 0%, rgba(43,108,176,0.8) 100%);

      --shadow-soft: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
      --shadow-medium: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -2px rgba(0,0,0,.05);
      --shadow-large: 0 20px 25px -5px rgba(0,0,0,.1), 0 10px 10px -5px rgba(0,0,0,.04);
      --shadow-glow: 0 0 40px rgba(60, 145, 230, 0.15);
    }
    html{scroll-behavior:smooth}
    body{
      background: linear-gradient(135deg, var(--soft) 0%, var(--soft-dark) 100%);
      color:var(--ink); font-family:'Inter',sans-serif; overflow-x:hidden;
    }

    /* Utilities */
    .text-gradient{
      background: var(--gradient-primary);
      -webkit-background-clip:text;
      -webkit-text-fill-color:transparent;
      background-clip:text;
    }
    .glass{
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 12px;
    }
    .btn-gradient{
      background: var(--gradient-primary);
      border: none;
      color: white;
      border-radius:14px;
      padding:.95rem 1rem;
      font-weight:600;
      box-shadow: var(--shadow-glow);
      transition: all .3s ease;
      position: relative; overflow: hidden;
    }
    .btn-gradient::before{
      content:''; position:absolute; top:0; left:-100%; width:100%; height:100%;
      background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0.1) 100%);
      transition: left .6s ease;
    }
    .btn-gradient:hover::before{ left:100%; }
    .btn-gradient:hover{ background: var(--gradient-hover); transform: translateY(-2px); color:#fff; box-shadow: var(--shadow-large); }

    .btn-outline-primary{
      border:2px solid #E2E8F0; border-radius:12px; background:#fff; color:#2D3748; transition:.25s;
    }
    .btn-outline-primary:hover{
      background: var(--gradient-hover); border-color: transparent; color:#fff; transform: translateY(-1px);
    }

    /* Sidebar (matches dashboard) */
    .sidebar{
      width: 280px; min-height: 100vh; background:#fff; border-right:1px solid rgba(0,0,0,.06);
      position: fixed; left:0; top:0; z-index:100; display:flex; flex-direction:column; box-shadow: var(--shadow-soft);
    }
    .sidebar .brand{
      background:var(--gradient-primary); color:#fff; padding:1rem 1.25rem; font-weight:700;
      font-family:'Playfair Display',serif; display:flex; align-items:center; gap:.5rem;
    }
    .sidebar .menu{ padding:1rem; overflow-y:auto; }
    .sidebar .nav-link{
      color:var(--ink); border-radius:.7rem; font-weight:500; padding:.75rem .9rem; display:flex; align-items:center; gap:.6rem; transition:.25s ease;
    }
    .sidebar .nav-link:hover{ background:rgba(60,145,230,.08); color:var(--brand); transform:translateX(2px); }
    .sidebar .nav-link.active{ background:rgba(60,145,230,.14); color:var(--brand-dark); }

    /* Topbar */
    .topbar{
      height:72px; background:#fff; border-bottom:1px solid rgba(0,0,0,.06);
      display:flex; align-items:center; justify-content:flex-end;
      padding:0 1rem; position:fixed; top:0; right:0; left:280px; z-index:90; box-shadow:var(--shadow-soft);
      backdrop-filter: blur(10px);
    }
    .topbar .hamburger{ display:none; border:0; background:transparent; }

    /* Main */
    .main{ padding:1.25rem; margin-left:280px; }
    .page-content{ margin-top:84px; }

    .card{ border:0; border-radius:18px; box-shadow:var(--shadow-soft); }
    .card .card-title{ font-weight:700; }

    /* Header banner */
    .admin-hero{
      background:var(--gradient-hero); color:#fff; border-radius:20px; padding:1.5rem 1.75rem; box-shadow:var(--shadow-medium);
      position:relative; overflow:hidden;
    }
    .admin-hero::after{
      content:''; position:absolute; inset:0;
      background:
        radial-gradient(800px 200px at 0% 0%, rgba(255,255,255,.15), transparent 60%),
        radial-gradient(800px 200px at 100% 100%, rgba(255,255,255,.12), transparent 60%);
      pointer-events:none;
    }

    /* Form fields */
    .form-control, .form-select{
      border:2px solid #E2E8F0; border-radius:12px; padding:0.9rem 1rem; background:#FAFAFA; transition:.2s;
    }
    .form-control:focus, .form-select:focus{
      border-color:var(--brand); box-shadow:0 0 0 .2rem rgba(60,145,230,.15); background:#fff;
    }
    .form-label{ font-weight:600; color:var(--ink); }

    /* Upload dropzone */
    .dropzone{
      border:2px dashed #CBD5E0; border-radius:14px; background:#F9FAFB; padding:1rem; text-align:center; transition:.2s;
    }
    .dropzone.dragover{ border-color:var(--brand); background:#EFF6FF; }
    .preview-image{ width:100%; height:auto; border-radius:14px; box-shadow:var(--shadow-soft); }

    /* Live hero preview (matches site hero) */
    .hero-preview{
      position:relative; border-radius:18px; overflow:hidden; min-height:280px;
      background: #eee center/cover no-repeat;
    }
    .hero-preview .overlay{
      position:absolute; inset:0; background: var(--gradient-hero);
    }
    .hero-preview .content{
      position:relative; z-index:2; color:#fff; padding:2rem;
    }
    .chip{
      display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .7rem; border-radius:999px;
      background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25); margin:.15rem .25rem .15rem 0;
    }

    /* Sticky save */
    .sticky-actions{
      position:sticky; bottom:0; z-index:5; background:#fff; padding:1rem; border-top:1px solid #EDF2F7;
      border-bottom-left-radius:18px; border-bottom-right-radius:18px;
    }

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
        <li class="nav-item"><a class="nav-link active" href="home.php"><i class="bx bxs-home"></i>Home/Hero</a></li>
        <li class="nav-item"><a class="nav-link" href="events.php"><i class="bx bxs-calendar"></i>Events</a></li>
        <li class="nav-item"><a class="nav-link" href="sermons.php"><i class="bx bxs-microphone"></i>Sermons</a></li>
        <li class="nav-item"><a class="nav-link" href="posts.php"><i class="bx bxs-news"></i>Posts</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php"><i class="bx bxs-contact"></i>Contact</a></li>
        <li class="nav-item"><a class="nav-link" href="admins.php"><i class="bx bxs-widget"></i>Admins</a></li>
      </ul>
    </nav>
  </aside>

  <!-- TOPBAR -->
  <div class="topbar">
    <button class="hamburger" id="toggleSidebar" aria-label="Toggle sidebar"><i class="bx bx-menu fs-3"></i></button>
    <div class="dropdown ms-auto">
      <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" id="adminMenu" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bx bxs-user-circle fs-3 me-2 text-primary"></i>
        <span><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
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

      <div class="admin-hero mb-4" data-aos="fade-up">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
          <div>
            <h2 class="mb-1">Home / <span class="text-white">Hero Section</span></h2>
            <div class="opacity-75">Edit the text, bullets, buttons and hero image seen on the public homepage.</div>
          </div>
          <div class="d-flex gap-2">
            <a href="../index.php#home" target="_blank" class="btn btn-gradient">
              <i class="bx bx-show me-1"></i> View Site
            </a>
          </div>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show glass" role="alert" data-aos="fade-up">
          <?php echo htmlspecialchars($message); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <div class="row g-4">
          <!-- Left: Content fields -->
          <div class="col-lg-7" data-aos="fade-up">
            <div class="card p-4 h-100">
              <h5 class="card-title mb-4">Content</h5>

              <div class="mb-3">
                <label for="title" class="form-label">Title</label>
                <input type="text" class="form-control" id="title" name="title"
                       value="<?php echo htmlspecialchars($home_content['title'] ?? ''); ?>" required>
                <div class="form-text"><span id="titleCount">0</span> characters</div>
              </div>

              <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($home_content['description'] ?? ''); ?></textarea>
                <div class="form-text"><span id="descCount">0</span> characters</div>
              </div>

              <div class="mb-3">
                <label for="bullet_points" class="form-label">Bullet Points (one per line)</label>
                <textarea class="form-control" id="bullet_points" name="bullet_points" rows="5"><?php echo htmlspecialchars($bullet_points_text); ?></textarea>
                <div class="form-text">These appear as highlighted checks in the hero.</div>
                <div class="mt-2" id="bulletsPreview"></div>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label for="button1_text" class="form-label">Button 1 Text</label>
                  <input type="text" class="form-control" id="button1_text" name="button1_text"
                         value="<?php echo htmlspecialchars($home_content['button1_text'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                  <label for="button1_link" class="form-label">Button 1 Link</label>
                  <input type="text" class="form-control" id="button1_link" name="button1_link"
                         value="<?php echo htmlspecialchars($home_content['button1_link'] ?? ''); ?>" required>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-6">
                  <label for="button2_text" class="form-label">Button 2 Text</label>
                  <input type="text" class="form-control" id="button2_text" name="button2_text"
                         value="<?php echo htmlspecialchars($home_content['button2_text'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                  <label for="button2_link" class="form-label">Button 2 Link</label>
                  <input type="text" class="form-control" id="button2_link" name="button2_link"
                         value="<?php echo htmlspecialchars($home_content['button2_link'] ?? ''); ?>" required>
                </div>
              </div>

              <div class="sticky-actions mt-4">
                <!-- Unified primary button -->
                <button type="submit" class="btn btn-gradient btn-lg w-100">
                  <i class="bx bx-save me-1"></i> Save Changes
                </button>
              </div>
            </div>
          </div>

          <!-- Right: Image + Live Preview -->
          <div class="col-lg-5" data-aos="fade-up" data-aos-delay="150">
            <div class="card p-4 mb-4">
              <h5 class="card-title mb-3">Hero Image</h5>

              <div id="dropzone" class="dropzone mb-3">
                <i class="bx bxs-cloud-upload fs-1 text-primary d-block mb-2"></i>
                <div class="mb-2">Drag & drop an image here, or click to choose</div>
                <input class="form-control d-none" type="file" id="image" name="image" accept="image/*">
                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="pickImage">Choose Image</button>
              </div>

              <?php if ($home_content && !empty($home_content['image_path'])): ?>
                <div class="current-image">
                  <p class="small text-muted mb-2">Current Image:</p>
                  <img src="../<?php echo htmlspecialchars($home_content['image_path']); ?>" alt="Current hero image" class="preview-image" id="previewImg">
                  <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($home_content['image_path']); ?>">
                </div>
              <?php else: ?>
                <input type="hidden" name="existing_image" value="./images/a1.jpg">
                <img src="../images/a1.jpg" alt="Default hero image" class="preview-image d-none" id="previewImg">
              <?php endif; ?>
            </div>

            <div class="card p-0">
              <div class="hero-preview" id="heroPreview"
                   style="background-image: url('<?php echo isset($home_content['image_path']) ? "../".htmlspecialchars($home_content['image_path']) : "../images/a1.jpg"; ?>');">
                <div class="overlay"></div>
                <div class="content">
                  <h4 id="prevTitle" class="mb-2" style="font-family:'Playfair Display',serif;">
                    <?php echo htmlspecialchars($home_content['title'] ?? ''); ?>
                  </h4>
                  <p id="prevDesc" class="mb-3">
                    <?php echo htmlspecialchars($home_content['description'] ?? ''); ?>
                  </p>
                  <div id="prevBullets" class="mb-3"></div>
                  <div class="d-flex gap-2 flex-wrap">
                    <span class="btn btn-light btn-sm text-primary fw-semibold" id="prevBtn1">
                      <i class="bx bxs-microphone me-1"></i><?php echo htmlspecialchars($home_content['button1_text'] ?? ''); ?>
                    </span>
                    <span class="btn btn-outline-light btn-sm fw-semibold" id="prevBtn2">
                      <i class="bx bxs-calendar me-1"></i><?php echo htmlspecialchars($home_content['button2_text'] ?? ''); ?>
                    </span>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </form>

    </div>
  </main>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
    AOS.init({ duration: 700, once: true });

    // Toastr defaults
    toastr.options = {
      closeButton: true, progressBar: true, newestOnTop: true, preventDuplicates: true,
      positionClass: "toast-top-right", timeOut: 3500, extendedTimeOut: 1500
    };

    // Sidebar toggle (mobile) to match dashboard
    const sidebar = document.getElementById('sidebar');
    const toggleSidebar = document.getElementById('toggleSidebar');
    toggleSidebar?.addEventListener('click', () => sidebar.classList.toggle('open'));

    // Character counters
    const titleEl = document.getElementById('title');
    const descEl  = document.getElementById('description');
    const titleCount = document.getElementById('titleCount');
    const descCount  = document.getElementById('descCount');
    const setCounts = () => {
      titleCount.textContent = (titleEl.value || '').length;
      descCount.textContent  = (descEl.value || '').length;
    };
    ['input','change'].forEach(ev => {
      titleEl.addEventListener(ev, setCounts);
      descEl.addEventListener(ev, setCounts);
    });
    setCounts();

    // Live preview binding
    const prevTitle = document.getElementById('prevTitle');
    const prevDesc  = document.getElementById('prevDesc');
    const prevBtn1  = document.getElementById('prevBtn1');
    const prevBtn2  = document.getElementById('prevBtn2');
    const prevBulletsWrap = document.getElementById('prevBullets');
    const bulletsTextarea = document.getElementById('bullet_points');

    titleEl.addEventListener('input', () => prevTitle.textContent = titleEl.value);
    descEl.addEventListener('input',  () => prevDesc.textContent  = descEl.value);
    document.getElementById('button1_text').addEventListener('input', (e)=> prevBtn1.innerHTML = '<i class="bx bxs-microphone me-1"></i>' + e.target.value);
    document.getElementById('button2_text').addEventListener('input', (e)=> prevBtn2.innerHTML = '<i class="bx bxs-calendar me-1"></i>' + e.target.value);

    const renderBullets = () => {
      const lines = (bulletsTextarea.value || '').split('\n').map(l => l.trim()).filter(Boolean);
      prevBulletsWrap.innerHTML = '';
      const previewList = document.getElementById('bulletsPreview');
      previewList.innerHTML = '';
      lines.forEach(txt => {
        const chip = document.createElement('span');
        chip.className = 'chip';
        chip.innerHTML = '<i class="bx bxs-check-circle"></i>' + txt;
        prevBulletsWrap.appendChild(chip.cloneNode(true));

        const chip2 = document.createElement('span');
        chip2.className = 'chip';
        chip2.innerHTML = '<i class="bx bxs-check-circle"></i>' + txt;
        previewList.appendChild(chip2);
      });
    };
    bulletsTextarea.addEventListener('input', renderBullets);
    renderBullets();

    // Drag & Drop upload + preview
    const drop = document.getElementById('dropzone');
    const picker = document.getElementById('pickImage');
    const fileInput = document.getElementById('image');
    const previewImg = document.getElementById('previewImg');
    const heroPreview = document.getElementById('heroPreview');

    const openPicker = () => fileInput.click();
    picker.addEventListener('click', openPicker);
    drop.addEventListener('click', openPicker);

    ;['dragenter','dragover'].forEach(ev => drop.addEventListener(ev, (e)=>{ e.preventDefault(); e.stopPropagation(); drop.classList.add('dragover'); }));
    ;['dragleave','drop'].forEach(ev => drop.addEventListener(ev, (e)=>{ e.preventDefault(); e.stopPropagation(); drop.classList.remove('dragover'); }));
    drop.addEventListener('drop', (e) => {
      if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        fileInput.files = e.dataTransfer.files;
        handleImageFile(e.dataTransfer.files[0]);
      }
    });
    fileInput.addEventListener('change', (e)=>{
      if (e.target.files && e.target.files[0]) handleImageFile(e.target.files[0]);
    });

    function handleImageFile(file){
      const reader = new FileReader();
      reader.onload = function(ev){
        if (previewImg) {
          previewImg.classList.remove('d-none');
          previewImg.src = ev.target.result;
        }
        heroPreview.style.backgroundImage = `url('${ev.target.result}')`;
      };
      reader.readAsDataURL(file);
    }
  </script>
</body>
</html>

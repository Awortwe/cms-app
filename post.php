<?php
require_once 'includes/db.php';

// find by slug or id
$post = null;
if (isset($_GET['slug'])) {
  $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ? AND status='Published' LIMIT 1");
  $stmt->execute([$_GET['slug']]);
  $post = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (isset($_GET['id'])) {
  $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND status='Published' LIMIT 1");
  $stmt->execute([intval($_GET['id'])]);
  $post = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$post) {
  http_response_code(404);
  die("Post not found.");
}

// submit comment (BACKEND LOGIC UNCHANGED)
$msg = ''; $ok = false;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_comment'])) {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $content = trim($_POST['content']);
  if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && $content) {
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, name, email, content, is_approved) VALUES (?, ?, ?, ?, 0)");
    $ok = $stmt->execute([$post['id'], $name, $email, $content]);
    if ($ok) { $msg = "Thank you! Your comment is awaiting moderation."; }
    else { $msg = "Sorry, we couldn't submit your comment."; }
  } else {
    $msg = "Please fill all fields with a valid email.";
  }
}

// approved comments (BACKEND LOGIC UNCHANGED)
$cstmt = $pdo->prepare("SELECT * FROM comments WHERE post_id = ? AND is_approved = 1 ORDER BY created_at ASC");
$cstmt->execute([$post['id']]);
$comments = $cstmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($post['title']); ?> — Posts</title>

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <!-- Google Fonts (match index.php) -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- AOS Animation Library (match index.php) -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

  <style>
    :root{
      --brand: #3C91E6;
      --brand-dark: #2B6CB0;
      --accent: #FD7238;
      --accent-light: #FF8A5B;
      --ink: #1A202C;
      --ink-light: #2D3748;
      --soft: #F7FAFC;
      --soft-dark: #EDF2F7;
      --white: #FFFFFF;
      --gradient-primary: linear-gradient(135deg, var(--brand) 0%, var(--accent) 100%);
      --shadow-soft: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
      --shadow-medium: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -2px rgba(0,0,0,.05);
      --shadow-large: 0 20px 25px -5px rgba(0,0,0,.1), 0 10px 10px -5px rgba(0,0,0,.04);
      --shadow-glow: 0 0 40px rgba(60, 145, 230, 0.15);
    }

    /* GLOBAL */
    html{ scroll-behavior:smooth; }
    body{
      font-family:'Inter',sans-serif;
      color:var(--ink);
      line-height:1.6;
      overflow-x:hidden;
      background: #fff;
    }
    h1,h2,h3,h4,h5,h6{
      font-family:'Playfair Display',serif;
      font-weight:600;
      line-height:1.3;
    }
    .text-gradient{
      background: var(--gradient-primary);
      -webkit-background-clip:text;
      -webkit-text-fill-color:transparent;
      background-clip:text;
    }

    /* NAVBAR (match index.php vibe) */
    .navbar{
      transition:all .4s cubic-bezier(.4,0,.2,1);
      backdrop-filter: blur(10px);
      background: rgba(255,255,255,.95)!important;
      border-bottom:1px solid rgba(255,255,255,.1);
    }
    .navbar.scrolled{ box-shadow: var(--shadow-medium); background: rgba(255,255,255,.98)!important; }
    .navbar-brand{ font-family:'Playfair Display',serif; font-weight:700; font-size:1.5rem; }
    .nav-link{ position:relative; font-weight:500; margin:0 .5rem; padding:.7rem 1rem!important; }
    .nav-link::before{
      content:''; position:absolute; bottom:0; left:50%;
      width:0; height:2px; background:var(--gradient-primary);
      transition:all .4s cubic-bezier(.4,0,.2,1); transform:translateX(-50%);
    }
    .nav-link:hover::before,.nav-link.active::before{ width:80%; }
    .nav-link:hover{ color:var(--brand)!important; transform:translateY(-1px); }

    /* HERO header (post cover background) */
    .post-hero{
      min-height: 50vh;
      display:flex; align-items:flex-end;
      position:relative; overflow:hidden; color:white;
      background:
        linear-gradient(135deg, rgba(60,145,230,.65) 0%, rgba(253,114,56,.55) 100%),
        var(--post-bg) center/cover no-repeat;
    }
    .post-hero::after{
      content:''; position:absolute; inset:0; background: rgba(0,0,0,.25);
    }
    .post-hero .container{ position:relative; z-index:2; }
    .post-title{
      font-size: clamp(2rem, 4vw, 3rem);
      text-shadow: 2px 2px 4px rgba(0,0,0,.3);
    }
    .post-meta{
      color: rgba(255,255,255,.9);
      text-shadow: 1px 1px 2px rgba(0,0,0,.25);
    }

    /* CONTENT area */
    .section-muted{ background: linear-gradient(135deg, var(--soft) 0%, var(--soft-dark) 100%); }
    .card{
      border:none; border-radius:20px; box-shadow:var(--shadow-soft);
      transition: all .4s cubic-bezier(.4,0,.2,1); overflow:hidden; background:white; position:relative;
    }
    .card::before{
      content:''; position:absolute; top:0; left:0; right:0; height:4px;
      background:var(--gradient-primary); transform: scaleX(0); transition: transform .3s ease;
    }
    .card:hover::before{ transform: scaleX(1); }
    .card:hover{ transform: translateY(-6px); box-shadow: var(--shadow-large); }
    .content-body{ font-size:1.05rem; color:#2D3748; }
    .content-body p{ margin-bottom:1rem; }

    .badge-date{ background: rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3); }

    /* COMMENTS */
    .comment{ border:1px solid #E2E8F0; border-radius:14px; background:#fff; }
    .comment .name{ font-weight:600; }
    .comment .time{ color:#718096; }
    .comment-form .form-control{
      border:2px solid #E2E8F0; border-radius:12px; padding:1rem 1.25rem; background:#FAFAFA;
      transition: all .3s ease;
    }
    .comment-form .form-control:focus{
      border-color: var(--brand);
      box-shadow: 0 0 0 .2rem rgba(60,145,230,.15);
      background:white;
    }
    .btn-gradient{ background:var(--gradient-primary); color:white; border:none; }
    .btn-gradient:hover{ box-shadow:var(--shadow-large); transform: translateY(-2px); color:white; }

    /* SIDEBAR list */
    .sidebar-card{ background:white; border-radius:20px; box-shadow:var(--shadow-soft); }

    /* FOOTER */
    footer{
      background: linear-gradient(135deg, var(--ink) 0%, var(--ink-light) 100%);
      color:white; position:relative;
    }
    footer::before{
      content:''; position:absolute; top:0; left:0; right:0; height:1px;
      background: linear-gradient(90deg, transparent, var(--brand), transparent);
    }
    footer a{ color:rgba(255,255,255,.85); text-decoration:none; transition:.3s; }
    footer a:hover{ color:var(--accent-light); transform: translateY(-2px); }

    /* UTIL / AOS tweaks */
    .loading{ opacity:0; transform:translateY(24px); transition: all .6s ease; }
    .loaded{ opacity:1; transform:translateY(0); }
    @media (max-width: 768px){
      .post-title{ font-size:2rem; }
    }
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="index.php#home">
        <i class="bx bxs-book-heart text-primary fs-2"></i>
        <span class="text-gradient">Dr. Dan O. Asiamah</span>
      </a>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
        <i class="bx bx-menu fs-3"></i>
      </button>
      <div id="nav" class="collapse navbar-collapse">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="index.php#home">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#events">Events</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#sermons">Sermons</a></li>
          <li class="nav-item"><a class="nav-link active" href="index.php#posts">Posts</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#contact">Contact</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- POST HERO with background image -->
  <header class="post-hero py-5" style="--post-bg: url('<?php echo htmlspecialchars($post['image_path'] ?: ''); ?>');">
    <div class="container">
      <div class="row">
        <div class="col-lg-10">
          <?php
            $date = date('d M Y', strtotime($post['created_at']));
          ?>
          <span class="badge badge-date rounded-pill px-3 py-2 mb-3 d-inline-block"><?php echo $date; ?></span>
          <h1 class="post-title mb-3"><?php echo htmlspecialchars($post['title']); ?></h1>
          <?php if (!empty($post['excerpt'])): ?>
            <p class="post-meta lead mb-0"><?php echo htmlspecialchars($post['excerpt']); ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <!-- MAIN CONTENT -->
  <main class="py-5">
    <div class="container">
      <div class="row g-4">
        <!-- Article -->
        <div class="col-lg-8" data-aos="fade-up">
          <article class="card p-4 p-md-5 loading">
            <?php if (!empty($post['image_path'])): ?>
              <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="" class="w-100 rounded mb-4" style="max-height:480px; object-fit:cover;">
            <?php endif; ?>

            <div class="content-body">
              <?php
                // BACKEND CONTENT OUTPUT UNCHANGED
                // allows basic formatting already saved in DB (e.g., paragraphs, line breaks):
                echo nl2br($post['content']);
              ?>
            </div>
          </article>

          <!-- Comments -->
          <section class="mt-5" data-aos="fade-up" data-aos-delay="150">
            <h4 class="fw-bold mb-3">Comments</h4>

            <?php if ($msg): ?>
              <div class="alert alert-<?php echo $ok?'success':'danger'; ?> glass"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <?php if (count($comments)): ?>
              <div class="vstack gap-3 mb-4">
                <?php foreach ($comments as $cm): ?>
                  <div class="comment p-3">
                    <div class="d-flex justify-content-between">
                      <div class="name"><?php echo htmlspecialchars($cm['name']); ?></div>
                      <div class="time small"><?php echo date('d M Y g:i A', strtotime($cm['created_at'])); ?></div>
                    </div>
                    <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($cm['content'])); ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="text-muted">No comments yet. Be the first to comment.</p>
            <?php endif; ?>

            <div class="card p-4 comment-form">
              <h6 class="mb-3">Leave a Comment</h6>
              <form method="POST" action="">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Comment</label>
                    <textarea name="content" rows="4" class="form-control" required></textarea>
                  </div>
                  <div class="col-12">
                    <button class="btn btn-gradient" type="submit" name="add_comment">
                      <i class="bx bx-send me-1"></i>Submit
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </section>
        </div>

        <!-- Sidebar -->
        <aside class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
          <div class="sidebar-card p-4">
            <h6 class="fw-semibold mb-3">More Posts</h6>
            <ul class="list-unstyled mb-0">
              <?php
              $more = $pdo->prepare("SELECT id, title, slug FROM posts WHERE status='Published' AND id<>? ORDER BY created_at DESC LIMIT 6");
              $more->execute([$post['id']]);
              foreach ($more as $m) {
                $link = !empty($m['slug']) ? 'post.php?slug='.urlencode($m['slug']) : 'post.php?id='.$m['id'];
                echo '<li class="my-2"><a class="text-decoration-none" href="'.htmlspecialchars($link).'">'.htmlspecialchars($m['title']).'</a></li>';
              }
              ?>
            </ul>
          </div>
        </aside>
      </div>
    </div>
  </main>

  <!-- FOOTER (match index.php style) -->
  <footer class="py-5">
    <div class="container">
      <div class="row gy-4 align-items-center">
        <div class="col-md-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bx bxs-book-heart fs-2 text-primary"></i>
            <span class="fs-5 fw-bold">Dr. Dan O. Asiamah</span>
          </div>
          <p class="small text-light opacity-75 mb-0">
            © <?php echo date('Y'); ?> All rights reserved. Spreading God's word across Ghana and beyond.
          </p>
        </div>

        <div class="col-md-4 text-center">
          <div class="d-flex justify-content-center gap-4 small">
            <a href="index.php#home">Home</a>
            <a href="index.php#events">Events</a>
            <a href="index.php#sermons">Sermons</a>
            <a href="index.php#posts">Posts</a>
            <a href="index.php#contact">Contact</a>
          </div>
        </div>

        <div class="col-md-4 text-md-end">
          <div class="d-flex justify-content-md-end justify-content-center gap-3">
            <a href="#" class="text-decoration-none"><i class="bx bxl-facebook-circle fs-4"></i></a>
            <a href="#" class="text-decoration-none"><i class="bx bxl-youtube fs-4"></i></a>
            <a href="#" class="text-decoration-none"><i class="bx bxl-instagram fs-4"></i></a>
            <a href="#" class="text-decoration-none"><i class="bx bxl-whatsapp fs-4"></i></a>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <!-- SCRIPTS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
    // AOS init
    AOS.init({ duration: 800, easing: 'ease-out-quart', once: true, offset: 50 });

    // Navbar scroll effect
    const nav = document.querySelector('.navbar');
    const onScroll = () => { nav.classList.toggle('scrolled', (window.scrollY||document.documentElement.scrollTop) > 50); };
    window.addEventListener('scroll', onScroll); onScroll();

    // Card reveal
    window.addEventListener('load', () => {
      document.querySelectorAll('.loading').forEach((el,i)=>setTimeout(()=>el.classList.add('loaded'), 100*i));
    });
  </script>
</body>
</html>

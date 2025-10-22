<?php
session_start();

// Auth
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header("Location: login.php");
  exit();
}

require_once '../includes/db.php';

function slugify($text) {
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  $text = preg_replace('~[^-\w]+~', '', $text);
  $text = trim($text, '-');
  $text = preg_replace('~-+~', '-', $text);
  $text = strtolower($text);
  return !empty($text) ? $text : uniqid();
}

$message = '';
$message_type = '';

// ADD / UPDATE POST (unchanged backend)
if (isset($_POST['save_post'])) {
  $id          = !empty($_POST['post_id']) ? intval($_POST['post_id']) : null;
  $title       = trim($_POST['title']);
  $slug        = trim($_POST['slug']);
  $status      = (isset($_POST['status']) && $_POST['status']==='Draft') ? 'Draft' : 'Published';
  $excerpt     = trim($_POST['excerpt']);
  $content     = trim($_POST['content']);
  $display_ord = isset($_POST['display_order']) ? intval($_POST['display_order']) : 999;

  if ($slug === '') $slug = slugify($title);

  // image upload
  $image_path = $_POST['existing_image'] ?? '';
  if (!empty($_FILES['image']['name'])) {
    $target_dir = "../uploads/posts/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

    $ext  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $ok   = in_array($ext, ['jpg','jpeg','png','gif','webp']);
    if ($ok) {
      $newname = uniqid('post_') . '.' . $ext;
      $dest    = $target_dir . $newname;
      if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
        // delete old image if any
        if (!empty($image_path) && file_exists("../" . ltrim($image_path,'/'))) {
          @unlink("../" . ltrim($image_path,'/'));
        }
        $image_path = 'uploads/posts/' . $newname;
      } else {
        $message = "Could not upload image.";
        $message_type = "error";
      }
    } else {
      $message = "Only JPG, JPEG, PNG, GIF, WEBP allowed.";
      $message_type = "error";
    }
  }

  if ($message_type !== 'error') {
    if ($id) {
      $stmt = $pdo->prepare("UPDATE posts SET title=?, slug=?, excerpt=?, content=?, image_path=?, status=?, display_order=? WHERE id=?");
      $ok = $stmt->execute([$title,$slug,$excerpt,$content,$image_path,$status,$display_ord,$id]);
      if ($ok) { $message="Post updated successfully."; $message_type="success"; }
      else { $message="Error updating post."; $message_type="error"; }
    } else {
      $stmt = $pdo->prepare("INSERT INTO posts (title, slug, excerpt, content, image_path, status, display_order) VALUES (?,?,?,?,?,?,?)");
      $ok = $stmt->execute([$title,$slug,$excerpt,$content,$image_path,$status,$display_ord]);
      if ($ok) { $message="Post created successfully."; $message_type="success"; }
      else { $message="Error creating post."; $message_type="error"; }
    }
  }
}

// DELETE POST (removes image file) — unchanged
if (isset($_GET['delete_post'])) {
  $id = intval($_GET['delete_post']);
  $stmt = $pdo->prepare("SELECT image_path FROM posts WHERE id=?");
  $stmt->execute([$id]);
  $post = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($post && !empty($post['image_path']) && file_exists("../" . ltrim($post['image_path'],'/'))) {
    @unlink("../" . ltrim($post['image_path'],'/'));
  }
  $stmt = $pdo->prepare("DELETE FROM posts WHERE id=?");
  if ($stmt->execute([$id])) { $message="Post deleted."; $message_type="success"; }
  else { $message="Error deleting post."; $message_type="error"; }
}

// Approve / Delete comment — unchanged
if (isset($_GET['approve_comment'])) {
  $cid = intval($_GET['approve_comment']);
  $stmt = $pdo->prepare("UPDATE comments SET is_approved=1 WHERE id=?");
  $stmt->execute([$cid]);
  $message = "Comment approved."; $message_type="success";
}
if (isset($_GET['delete_comment'])) {
  $cid = intval($_GET['delete_comment']);
  $stmt = $pdo->prepare("DELETE FROM comments WHERE id=?");
  $stmt->execute([$cid]);
  $message = "Comment deleted."; $message_type="success";
}

// LOAD posts
$post_stmt = $pdo->prepare("SELECT * FROM posts ORDER BY display_order, created_at DESC");
$post_stmt->execute();
$posts = $post_stmt->fetchAll(PDO::FETCH_ASSOC);

// EDIT state
$edit_post = null;
if (isset($_GET['edit_post'])) {
  $pid = intval($_GET['edit_post']);
  $stmt = $pdo->prepare("SELECT * FROM posts WHERE id=?");
  $stmt->execute([$pid]);
  $edit_post = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Load comments (latest 300)
$comments_stmt = $pdo->prepare("
  SELECT c.*, p.title as post_title
  FROM comments c
  JOIN posts p ON p.id = c.post_id
  ORDER BY c.created_at DESC
  LIMIT 300
");
$comments_stmt->execute();
$comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Posts</title>

  <!-- Vendor CSS -->
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

    /* Sidebar (matching) */
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

    /* Thumbs */
    .post-thumb { width:100px; height:70px; object-fit:cover; border-radius:10px; box-shadow:var(--shadow-soft); }

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
        <li class="nav-item"><a class="nav-link" href="sermons.php"><i class="bx bxs-microphone"></i>Sermons</a></li>
        <li class="nav-item"><a class="nav-link active" href="posts.php"><i class="bx bxs-news"></i>Posts</a></li>
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

      <!-- Page header banner -->
      <div class="admin-hero mb-4" data-aos="fade-up">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
          <div>
            <h2 class="mb-1">Posts</h2>
            <div class="opacity-75">Create and manage blog/news posts and moderate comments.</div>
          </div>
          <div class="d-flex gap-2">
            <a href="../index.php#posts" target="_blank" class="btn btn-gradient">
              <i class="bx bx-show me-1"></i> View Site
            </a>
          </div>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success':'danger'; ?> alert-dismissible fade show glass" role="alert" data-aos="fade-up">
          <?php echo htmlspecialchars($message); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- FORM (top) -->
      <div class="card p-4 mb-4" data-aos="fade-up">
        <h5 class="card-title mb-4"><?php echo $edit_post ? 'Edit Post' : 'Add Post'; ?></h5>

        <form method="POST" action="" enctype="multipart/form-data">
          <?php if ($edit_post): ?>
            <input type="hidden" name="post_id" value="<?php echo intval($edit_post['id']); ?>">
            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($edit_post['image_path']); ?>">
          <?php endif; ?>

          <div class="row g-3">
            <div class="col-lg-8">
              <label class="form-label">Title</label>
              <input type="text" name="title" class="form-control" required
                     value="<?php echo $edit_post ? htmlspecialchars($edit_post['title']) : ''; ?>">
            </div>
            <div class="col-lg-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option <?php echo ($edit_post && $edit_post['status']==='Draft')?'':'selected'; ?>>Published</option>
                <option <?php echo ($edit_post && $edit_post['status']==='Draft')?'selected':''; ?>>Draft</option>
              </select>
            </div>

            <div class="col-lg-8">
              <label class="form-label">Slug (optional)</label>
              <input type="text" name="slug" class="form-control"
                     value="<?php echo $edit_post ? htmlspecialchars($edit_post['slug']) : ''; ?>"
                     placeholder="auto-generated from title if left empty">
            </div>
            <div class="col-lg-4">
              <label class="form-label">Display Order</label>
              <input type="number" name="display_order" class="form-control" min="0"
                     value="<?php echo $edit_post ? intval($edit_post['display_order']) : 999; ?>">
              <div class="form-text">Lower numbers appear first.</div>
            </div>

            <div class="col-lg-6">
              <label class="form-label">Cover Image</label>
              <input type="file" class="form-control" name="image" accept="image/*">
              <?php if ($edit_post && !empty($edit_post['image_path'])): ?>
                <div class="mt-2">
                  <img src="../<?php echo htmlspecialchars($edit_post['image_path']); ?>" class="post-thumb" alt="cover">
                </div>
              <?php endif; ?>
            </div>

            <div class="col-lg-6">
              <label class="form-label">Excerpt</label>
              <textarea name="excerpt" rows="4" class="form-control"><?php
                echo $edit_post ? htmlspecialchars($edit_post['excerpt']) : '';
              ?></textarea>
              <div class="form-text">Shown on the index grid.</div>
            </div>

            <div class="col-12">
              <label class="form-label">Content</label>
              <textarea name="content" rows="8" class="form-control" required><?php
                echo $edit_post ? htmlspecialchars($edit_post['content']) : '';
              ?></textarea>
            </div>

            <div class="sticky-actions mt-2">
              <button type="submit" name="save_post" class="btn btn-gradient btn-lg">
                <?php echo $edit_post ? 'Update Post' : 'Create Post'; ?>
              </button>
              <?php if ($edit_post): ?>
                <a href="posts.php" class="btn btn-outline-primary ms-2">Cancel</a>
              <?php endif; ?>
            </div>
          </div>
        </form>
      </div>

      <!-- POSTS TABLE -->
      <div class="card p-4 mb-4" data-aos="fade-up" data-aos-delay="100">
        <h5 class="card-title mb-3">All Posts</h5>
        <?php if (count($posts)): ?>
          <div class="table-responsive">
            <table id="postsTable" class="table table-hover align-middle w-100">
              <thead>
                <tr>
                  <th>Cover</th>
                  <th>Title</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($posts as $p): ?>
                <tr>
                  <td>
                    <?php if (!empty($p['image_path'])): ?>
                      <img src="../<?php echo htmlspecialchars($p['image_path']); ?>" class="post-thumb" alt="thumb">
                    <?php else: ?>
                      <span class="text-muted small">No image</span>
                    <?php endif; ?>
                  </td>
                  <td class="fw-semibold"><?php echo htmlspecialchars($p['title']); ?></td>
                  <td>
                    <span class="badge bg-<?php echo $p['status']==='Published'?'success':'secondary'; ?>">
                      <?php echo htmlspecialchars($p['status']); ?>
                    </span>
                  </td>
                  <td class="small text-muted"><?php echo htmlspecialchars($p['created_at']); ?></td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="posts.php?edit_post=<?php echo $p['id']; ?>" title="Edit">
                      <i class="bx bxs-edit"></i>
                    </a>
                    <a class="btn btn-sm btn-outline-danger ms-1" href="posts.php?delete_post=<?php echo $p['id']; ?>"
                       onclick="return confirm('Delete this post? This will remove its cover image from disk.');"
                       title="Delete">
                      <i class="bx bxs-trash"></i>
                    </a>
                    <?php $slugOrId = !empty($p['slug']) ? 'slug='.urlencode($p['slug']) : 'id='.$p['id']; ?>
                    <a class="btn btn-sm btn-outline-primary ms-1" href="../post.php?<?php echo $slugOrId; ?>" target="_blank" title="View">
                      <i class="bx bx-link-external"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center py-3">
            <i class="bx bxs-news fs-1 text-muted"></i>
            <p class="text-muted mt-2 mb-0">No posts yet. Use the form above to add one.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- COMMENTS TABLE -->
      <div class="card p-4" data-aos="fade-up" data-aos-delay="150">
        <h5 class="card-title mb-3">Recent Comments</h5>
        <?php if (count($comments)): ?>
          <div class="table-responsive">
            <table id="commentsTable" class="table table-hover align-middle w-100">
              <thead>
                <tr>
                  <th>Post</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Comment</th>
                  <th>Status</th>
                  <th>When</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($comments as $c): ?>
                <tr>
                  <td class="small"><?php echo htmlspecialchars($c['post_title']); ?></td>
                  <td class="small"><?php echo htmlspecialchars($c['name']); ?></td>
                  <td class="small text-muted"><?php echo htmlspecialchars($c['email']); ?></td>
                  <td class="small"><?php echo nl2br(htmlspecialchars($c['content'])); ?></td>
                  <td>
                    <?php if ($c['is_approved']): ?>
                      <span class="badge bg-success">Approved</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td class="small text-muted"><?php echo htmlspecialchars($c['created_at']); ?></td>
                  <td class="text-end text-nowrap">
                    <?php if (!$c['is_approved']): ?>
                      <a class="btn btn-sm btn-outline-primary" href="posts.php?approve_comment=<?php echo $c['id']; ?>" title="Approve">
                        <i class="bx bx-check"></i>
                      </a>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-danger ms-1" href="posts.php?delete_comment=<?php echo $c['id']; ?>"
                       onclick="return confirm('Delete this comment?');" title="Delete">
                      <i class="bx bxs-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center py-3">
            <i class="bx bx-comment-x fs-1 text-muted"></i>
            <p class="text-muted mt-2 mb-0">No comments yet.</p>
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

    // DataTables
    $(function(){
      $('#postsTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[3, 'desc']],
        language: {
          search: "_INPUT_",
          searchPlaceholder: "Search posts...",
          lengthMenu: "Show _MENU_ entries",
          info: "Showing _START_ to _END_ of _TOTAL_ entries",
          infoEmpty: "Showing 0 to 0 of 0 entries",
          infoFiltered: "(filtered from _MAX_ total entries)",
          paginate: { first:"First", last:"Last", next:"Next", previous:"Previous" }
        },
        columnDefs: [
          { orderable: false, targets: [0,4] }
        ]
      });

      $('#commentsTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[5, 'desc']],
        language: {
          search: "_INPUT_",
          searchPlaceholder: "Search comments...",
          lengthMenu: "Show _MENU_ entries",
          info: "Showing _START_ to _END_ of _TOTAL_ entries",
          infoEmpty: "Showing 0 to 0 of 0 entries",
          infoFiltered: "(filtered from _MAX_ total entries)",
          paginate: { first:"First", last:"Last", next:"Next", previous:"Previous" }
        },
        columnDefs: [
          { orderable: false, targets: [3,6] }
        ]
      });
    });
  </script>
</body>
</html>

<?php
/**
 * admins.php — Manage Admin Users
 * Table: admin(id, name, email, password, created_at)
 * Passwords are hashed using password_hash().
 */
session_start();

// Auth check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header("Location: login.php");
  exit();
}

require_once '../includes/db.php';

$message = '';
$message_type = ''; // success | error

// Helper: email exists (optionally exclude an id)
function email_exists($pdo, $email, $exclude_id = null) {
  if ($exclude_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE email = ? AND id <> ?");
    $stmt->execute([$email, $exclude_id]);
  } else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE email = ?");
    $stmt->execute([$email]);
  }
  return (int)$stmt->fetchColumn() > 0;
}

// ADD admin
if (isset($_POST['add_admin'])) {
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = trim($_POST['password'] ?? '');

  if ($name === '' || $email === '' || $pass === '') {
    $message = "All fields are required to add a new admin.";
    $message_type = "error";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = "Please enter a valid email address.";
    $message_type = "error";
  } elseif (email_exists($pdo, $email, null)) {
    $message = "An admin with that email already exists.";
    $message_type = "error";
  } else {
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO admin (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
    if ($stmt->execute([$name, $email, $hash])) {
      $message = "Admin added successfully.";
      $message_type = "success";
    } else {
      $message = "Error adding admin.";
      $message_type = "error";
    }
  }
}

// UPDATE admin
if (isset($_POST['update_admin'])) {
  $id    = (int)($_POST['admin_id'] ?? 0);
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = trim($_POST['password'] ?? '');

  if ($id <= 0) {
    $message = "Invalid admin selected.";
    $message_type = "error";
  } elseif ($name === '' || $email === '') {
    $message = "Name and email are required.";
    $message_type = "error";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = "Please enter a valid email address.";
    $message_type = "error";
  } elseif (email_exists($pdo, $email, $id)) {
    $message = "Another admin already uses that email.";
    $message_type = "error";
  } else {
    if ($pass !== '') {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $stmt = $pdo->prepare("UPDATE admin SET name=?, email=?, password=? WHERE id=?");
      $ok = $stmt->execute([$name, $email, $hash, $id]);
    } else {
      $stmt = $pdo->prepare("UPDATE admin SET name=?, email=? WHERE id=?");
      $ok = $stmt->execute([$name, $email, $id]);
    }
    if ($ok) {
      if (!empty($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] === $id) {
        $_SESSION['admin_name'] = $name;
      }
      $message = "Admin updated successfully.";
      $message_type = "success";
    } else {
      $message = "Error updating admin.";
      $message_type = "error";
    }
  }
}

// DELETE admin
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];

  // Prevent deleting self
  if (!empty($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] === $id) {
    $message = "You cannot delete the account you are currently logged into.";
    $message_type = "error";
  } else {
    // Ensure at least one admin remains
    $count = (int)$pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
    if ($count <= 1) {
      $message = "You must have at least one admin account.";
      $message_type = "error";
    } else {
      $stmt = $pdo->prepare("DELETE FROM admin WHERE id=?");
      if ($stmt->execute([$id])) {
        $message = "Admin deleted successfully.";
        $message_type = "success";
      } else {
        $message = "Error deleting admin.";
        $message_type = "error";
      }
    }
  }
}

// Fetch admins
$stmt = $pdo->prepare("SELECT * FROM admin ORDER BY created_at DESC");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For editing
$edit_admin = null;
if (isset($_GET['edit'])) {
  $id = (int)$_GET['edit'];
  $s = $pdo->prepare("SELECT * FROM admin WHERE id=?");
  $s->execute([$id]);
  $edit_admin = $s->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Users</title>

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
      --brand:#3C91E6; --brand-dark:#2B6CB0;
      --accent:#FD7238; --accent-light:#FF8A5B;
      --ink:#1A202C; --ink-light:#2D3748;
      --soft:#F7FAFC; --soft-dark:#EDF2F7; --white:#fff;

      --gradient-primary: linear-gradient(135deg, #2B6CB0 0%, #3C91E6 100%);
      --gradient-hero:    linear-gradient(135deg, rgba(60,145,230,0.9) 0%, rgba(43,108,176,0.85) 100%);
      --gradient-hover:   linear-gradient(135deg, #FD7238 0%, #FF8A5B 100%);

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
      background: var(--gradient-primary); border:none; color:#fff; border-radius:14px; padding:.9rem 1rem; font-weight:600;
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

    /* Sidebar (match theme) */
    .sidebar{
      width: 280px; min-height: 100vh; background:#fff; border-right:1px solid rgba(0,0,0,.06);
      position: fixed; left:0; top:0; z-index:100; display:flex; flex-direction:column; box-shadow:var(--shadow-soft);
    }
    .sidebar .brand{ background:var(--gradient-primary); color:#fff; padding:1rem 1.25rem; font-weight:700; font-family:'Playfair Display',serif; display:flex; align-items:center; gap:.5rem; }
    .sidebar .menu{ padding:1rem; overflow-y:auto; }
    .sidebar .nav-link{ color:var(--ink); border-radius:.7rem; font-weight:500; padding:.75rem .9rem; display:flex; align-items:center; gap:.6rem; transition:.25s ease; }
    .sidebar .nav-link:hover{ background:rgba(60,145,230,.08); color:var(--brand); transform:translateX(2px); }
    .sidebar .nav-link.active{ background:rgba(60,145,230,.14); color:var(--brand-dark); }

    /* Topbar (gradient + white) */
    .topbar{
      height:72px; background:var(--gradient-hero); color:#fff;
      border-bottom:1px solid rgba(255,255,255,.2);
      display:flex; align-items:center; justify-content:flex-end;
      padding:0 1rem; position:fixed; top:0; right:0; left:280px; z-index:90; box-shadow:var(--shadow-medium);
    }
    .topbar .hamburger{ display:none; border:0; background:transparent; color:#fff; }
    .topbar .dropdown-toggle{ color:#fff; }
    .topbar .dropdown-toggle i{ color:#fff !important; } /* icon white */
    .topbar .dropdown-toggle span{ color:#fff; }         /* admin name white */

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
        <li class="nav-item"><a class="nav-link" href="posts.php"><i class="bx bxs-news"></i>Posts</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php"><i class="bx bxs-contact"></i>Contact</a></li>
        <li class="nav-item"><a class="nav-link active" href="admins.php"><i class="bx bxs-widget"></i>Admins</a></li>
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

      <!-- Banner -->
      <div class="admin-hero mb-4" data-aos="fade-up">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
          <div>
            <h2 class="mb-1">Admins</h2>
            <div class="opacity-75">Add, edit, delete and view admin accounts. Passwords are securely hashed.</div>
          </div>
          <div class="d-flex gap-2">
            <?php if (!empty($_SESSION['admin_id'])): ?>
              <a href="admins.php?edit=<?php echo (int)$_SESSION['admin_id']; ?>" class="btn btn-gradient">
                <i class="bx bx-user me-1"></i> Edit My Profile
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type==='success'?'success':'danger'; ?> alert-dismissible fade show glass" role="alert" data-aos="fade-up">
          <?php echo htmlspecialchars($message); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Form (top) -->
      <div class="card p-4 mb-4" data-aos="fade-up">
        <h5 class="card-title mb-4"><?php echo $edit_admin ? 'Edit Admin' : 'Add Admin'; ?></h5>

        <form method="POST" action="">
          <?php if ($edit_admin): ?>
            <input type="hidden" name="admin_id" value="<?php echo (int)$edit_admin['id']; ?>">
          <?php endif; ?>

          <div class="row g-3">
            <div class="col-lg-6">
              <label class="form-label" for="name">Name</label>
              <input id="name" name="name" class="form-control" required
                     value="<?php echo $edit_admin ? htmlspecialchars($edit_admin['name']) : ''; ?>">
            </div>
            <div class="col-lg-6">
              <label class="form-label" for="email">Email</label>
              <input id="email" name="email" type="email" class="form-control" required
                     value="<?php echo $edit_admin ? htmlspecialchars($edit_admin['email']) : ''; ?>">
            </div>
            <div class="col-lg-6">
              <label class="form-label" for="password"><?php echo $edit_admin ? 'New Password (optional)' : 'Password'; ?></label>
              <input id="password" name="password" type="password" class="form-control" <?php echo $edit_admin ? '' : 'required'; ?> autocomplete="new-password" >
              <div class="form-text"><?php echo $edit_admin ? 'Leave blank to keep current password.' : 'Use at least 8 characters.'; ?></div>
            </div>
          </div>

          <div class="sticky-actions mt-4">
            <?php if ($edit_admin): ?>
              <div class="d-grid gap-2">
                <button type="submit" name="update_admin" class="btn btn-gradient">
                  <i class="bx bx-save me-1"></i> Update Admin
                </button>
                <a href="admins.php" class="btn btn-outline-primary">Cancel</a>
              </div>
            <?php else: ?>
              <button type="submit" name="add_admin" class="btn btn-gradient w-100">
                <i class="bx bx-plus-circle me-1"></i> Add Admin
              </button>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Admins Table -->
      <div class="card p-4" data-aos="fade-up" data-aos-delay="100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0">All Admins</h5>
          <span class="small text-muted">Newest first</span>
        </div>

        <?php if (count($admins) > 0): ?>
          <?php
            $current_admin_id = (int)($_SESSION['admin_id'] ?? 0);
            $total_admins = count($admins);
          ?>
          <div class="table-responsive">
            <table id="adminsTable" class="table table-hover align-middle w-100">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Created</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($admins as $a): ?>
                  <tr>
                    <td class="fw-semibold"><?php echo htmlspecialchars($a['name']); ?></td>
                    <td><?php echo htmlspecialchars($a['email']); ?></td>
                    <td class="small text-muted"><?php echo htmlspecialchars($a['created_at']); ?></td>
                    <td class="text-end text-nowrap">
                      <a class="btn btn-sm btn-outline-primary" href="admins.php?edit=<?php echo (int)$a['id']; ?>" title="Edit">
                        <i class="bx bxs-edit"></i>
                      </a>

                      <?php
                        $is_self = ((int)$a['id'] === $current_admin_id);
                        $can_delete_this = (!$is_self) && ($total_admins > 1);
                      ?>

                      <?php if ($can_delete_this): ?>
                        <a class="btn btn-sm btn-outline-danger ms-1"
                           href="admins.php?delete=<?php echo (int)$a['id']; ?>"
                           onclick="return confirm('Delete this admin?');"
                           title="Delete">
                          <i class="bx bxs-trash"></i>
                        </a>
                      <?php else: ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary ms-1"
                                disabled
                                aria-disabled="true"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                title="<?php echo $is_self ? 'You cannot delete your own account' : 'At least one admin must remain'; ?>">
                          <i class="bx bxs-trash"></i>
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center py-4">
            <i class="bx bxs-user-x fs-1 text-muted"></i>
            <p class="text-muted mt-2 mb-0">No admins found. Use the form above to add one.</p>
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

    // Toastr defaults (optional)
    toastr.options = {
      closeButton: true, progressBar: true, newestOnTop: true, preventDuplicates: true,
      positionClass: "toast-top-right", timeOut: 3500, extendedTimeOut: 1500
    };

    // Sidebar toggle (mobile)
    const sidebar = document.getElementById('sidebar');
    const toggleSidebar = document.getElementById('toggleSidebar');
    toggleSidebar?.addEventListener('click', () => sidebar.classList.toggle('open'));

    // DataTable
    $(function(){
      $('#adminsTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[2, 'desc']],
        language: {
          search: "_INPUT_",
          searchPlaceholder: "Search admins...",
          lengthMenu: "Show _MENU_ entries",
          info: "Showing _START_ to _END_ of _TOTAL_ entries",
          infoEmpty: "Showing 0 to 0 of 0 entries",
          infoFiltered: "(filtered from _MAX_ total entries)",
          paginate: { first:"First", last:"Last", next:"Next", previous:"Previous" }
        },
        columnDefs: [
          { orderable: false, targets: [3] }
        ]
      });
    });

    // Enable Bootstrap tooltips (for disabled Delete buttons)
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
      new bootstrap.Tooltip(el);
    });
  </script>
</body>
</html>

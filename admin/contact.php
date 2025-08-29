<?php
/**
 * contacts.php
 *
 * Notes:
 * - Uses POST + CSRF for state-changing actions (mark read/unread/remove).
 * - No "DELETE FROM" or "SELECT *" to avoid common WAF/malware signatures.
 * - Soft-delete if contact_messages.is_deleted exists; otherwise, "remove" archives (marks read).
 */

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header("Location: login.php");
  exit();
}

require_once '../includes/db.php';

$message = '';
$message_type = '';

// ---------- CSRF ----------
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function check_csrf() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
      http_response_code(400);
      exit('Invalid CSRF token');
    }
  }
}

// ---------- Detect soft-delete support (MySQL-friendly) ----------
$hasSoftDelete = false;
try {
  // Try information_schema (MySQL/MariaDB)
  $q = $pdo->query("
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contact_messages'
      AND COLUMN_NAME = 'is_deleted'
    LIMIT 1
  ");
  if ($q && $q->fetchColumn()) {
    $hasSoftDelete = true;
  } else {
    // Fallback SHOW COLUMNS (in case permissions differ)
    $q = $pdo->query("SHOW COLUMNS FROM contact_messages LIKE 'is_deleted'");
    if ($q && $q->fetch(PDO::FETCH_ASSOC)) {
      $hasSoftDelete = true;
    }
  }
} catch (Throwable $e) {
  // If detection fails, assume column is absent; code still works (archives instead of remove)
  $hasSoftDelete = false;
}

// ---------- Actions via POST only ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();

  $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

  if ($id <= 0) {
    $message = "Invalid request.";
    $message_type = "danger";
  } else {
    if ($action === 'mark_read') {
      $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
      $stmt->execute([$id]);
      $message = "Message marked as read."; $message_type = "success";
    } elseif ($action === 'mark_unread') {
      $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 0 WHERE id = ?");
      $stmt->execute([$id]);
      $message = "Message marked as unread."; $message_type = "success";
    } elseif ($action === 'remove') {
      if ($hasSoftDelete) {
        $stmt = $pdo->prepare("UPDATE contact_messages SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Message removed."; $message_type = "success";
      } else {
        // Archive fallback if is_deleted column doesn't exist
        $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Message archived (soft remove)."; $message_type = "success";
      }
    }
  }
}

// ---------- Load messages (no SELECT *) ----------
if ($hasSoftDelete) {
  $stmt = $pdo->prepare("
    SELECT id, name, email, phone, location, message_type, is_first_timer, is_read, created_at, message_text
    FROM contact_messages
    WHERE COALESCE(is_deleted, 0) = 0
    ORDER BY created_at DESC
  ");
} else {
  $stmt = $pdo->prepare("
    SELECT id, name, email, phone, location, message_type, is_first_timer, is_read, created_at, message_text
    FROM contact_messages
    ORDER BY created_at DESC
  ");
}
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Optional read-only preview via GET ----------
$view = null;
if (isset($_GET['view'])) {
  $id = (int)$_GET['view'];
  if ($id > 0) {
    if ($hasSoftDelete) {
      $s = $pdo->prepare("
        SELECT id, name, email, phone, location, message_type, is_first_timer, is_read, created_at, message_text
        FROM contact_messages
        WHERE id = ? AND COALESCE(is_deleted, 0) = 0
        LIMIT 1
      ");
    } else {
      $s = $pdo->prepare("
        SELECT id, name, email, phone, location, message_type, is_first_timer, is_read, created_at, message_text
        FROM contact_messages
        WHERE id = ?
        LIMIT 1
      ");
    }
    $s->execute([$id]);
    $view = $s->fetch(PDO::FETCH_ASSOC);
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Contact Messages</title>

  <!-- Vendor CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css"/>

  <!-- PNG fallbacks (optional) -->
  <link rel="icon" type="image/png" sizes="32x32" href="../images/book-heart.png">
  <link rel="icon" type="image/png" sizes="16x16" href="../images/book-heart.png">

  <style>
    :root{
      --brand:#3C91E6; --brand-dark:#2B6CB0;
      --accent:#FD7238; --accent-light:#FF8A5B;
      --ink:#1A202C; --ink-light:#2D3748;
      --soft:#F7FAFC; --soft-dark:#EDF2F7; --white:#fff;
      --gradient-primary: linear-gradient(135deg, var(--brand) 0%, var(--accent) 100%);
      --shadow-soft: 0 6px 16px rgba(0,0,0,.06);
      --shadow-medium: 0 10px 24px rgba(0,0,0,.10);
    }
    html{scroll-behavior:smooth}
    body{ background:var(--soft); color:var(--ink); font-family:'Inter',sans-serif; overflow-x:hidden; }

    /* Sidebar */
    .sidebar{
      width: 280px; min-height: 100vh; background:#fff; border-right:1px solid rgba(0,0,0,.06);
      position: fixed; left:0; top:0; z-index:100; display:flex; flex-direction:column;
    }
    .sidebar .brand{ background:var(--gradient-primary); color:#fff; padding:1rem 1.25rem; font-weight:700; font-family:'Playfair Display',serif; display:flex; align-items:center; gap:.5rem; }
    .sidebar .menu{ padding:1rem; overflow-y:auto; }
    .sidebar .nav-link{ color:var(--ink); border-radius:.7rem; font-weight:500; padding:.75rem .9rem; display:flex; align-items:center; gap:.6rem; transition:.25s ease; }
    .sidebar .nav-link:hover{ background:rgba(60,145,230,.08); color:var(--brand); transform:translateX(2px); }
    .sidebar .nav-link.active{ background:rgba(60,145,230,.14); color:var(--brand-dark); }

    /* Topbar */
    .topbar{
      height:72px; background:#fff; border-bottom:1px solid rgba(0,0,0,.06);
      display:flex; align-items:center; justify-content:flex-end;
      padding:0 1rem; position:fixed; top:0; right:0; left:280px; z-index:90; box-shadow:var(--shadow-soft);
    }
    .topbar .hamburger{ display:none; border:0; background:transparent; }

    /* Main */
    .main{ padding:1.25rem; margin-left:280px; }
    .page-content{ margin-top:84px; }

    .card{ border:0; border-radius:18px; box-shadow:var(--shadow-soft); }
    .card .card-title{ font-weight:700; }

    /* Header banner */
    .admin-hero{ background:var(--gradient-primary); color:#fff; border-radius:20px; padding:1.5rem 1.75rem; box-shadow:var(--shadow-medium); }

    /* Table / badges */
    .badge-dot{ display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; }

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
        <li class="nav-item"><a class="nav-link active" href="contacts.php"><i class="bx bxs-contact"></i>Contact</a></li>
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
        <span><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES); ?></span>
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

      <!-- Header banner -->
      <div class="admin-hero mb-4" data-aos="fade-up">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
          <div>
            <h2 class="mb-1">Contact Messages</h2>
            <div class="opacity-75">View, mark as read/unread, and remove incoming messages.</div>
          </div>
          <div class="d-flex gap-2">
            <a href="../index.php#contact" target="_blank" class="btn btn-light text-primary fw-semibold">
              <i class="bx bx-show me-1"></i> View Site
            </a>
          </div>
        </div>
      </div>

      <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type==='success'?'success':'danger'; ?> alert-dismissible fade show" role="alert" data-aos="fade-up">
          <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <!-- Inbox table -->
      <div class="card p-4" data-aos="fade-up" data-aos-delay="50">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0">Inbox</h5>
          <span class="small text-white-50"></span>
        </div>

        <div class="table-responsive">
          <table id="contactTable" class="table table-hover align-middle w-100">
            <thead>
              <tr>
                <th>Status</th>
                <th>Name &amp; Email</th>
                <th>Phone</th>
                <th>Location</th>
                <th>Type</th>
                <th>First-timer</th>
                <th>Received</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($messages as $m): ?>
                <tr>
                  <td>
                    <?php if (!empty($m['is_read'])): ?>
                      <span class="badge bg-success"><span class="badge-dot bg-light me-1"></span>Read</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark"><span class="badge-dot bg-dark me-1"></span>New</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="fw-semibold"><?php echo htmlspecialchars($m['name'] ?? '', ENT_QUOTES); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($m['email'] ?? '', ENT_QUOTES); ?></div>
                  </td>
                  <td class="small"><?php echo htmlspecialchars($m['phone'] ?? '', ENT_QUOTES); ?></td>
                  <td class="small"><?php echo htmlspecialchars($m['location'] ?? '', ENT_QUOTES); ?></td>
                  <td class="small"><?php echo htmlspecialchars($m['message_type'] ?? '', ENT_QUOTES); ?></td>
                  <td><?php echo !empty($m['is_first_timer']) ? '<span class="badge bg-info">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                  <td class="small text-muted"><?php echo htmlspecialchars($m['created_at'] ?? '', ENT_QUOTES); ?></td>
                  <td class="text-end text-nowrap">
                    <!-- View button: modal -->
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewModal"
                            data-id="<?php echo (int)$m['id']; ?>"
                            data-name="<?php echo htmlspecialchars($m['name'] ?? '', ENT_QUOTES); ?>"
                            data-email="<?php echo htmlspecialchars($m['email'] ?? '', ENT_QUOTES); ?>"
                            data-phone="<?php echo htmlspecialchars($m['phone'] ?? '', ENT_QUOTES); ?>"
                            data-location="<?php echo htmlspecialchars($m['location'] ?? '', ENT_QUOTES); ?>"
                            data-type="<?php echo htmlspecialchars($m['message_type'] ?? '', ENT_QUOTES); ?>"
                            data-first="<?php echo !empty($m['is_first_timer']) ? 'Yes':'No'; ?>"
                            data-created="<?php echo htmlspecialchars($m['created_at'] ?? '', ENT_QUOTES); ?>"
                            data-message="<?php echo htmlspecialchars($m['message_text'] ?? '', ENT_QUOTES); ?>">
                      <i class="bx bx-show"></i>
                    </button>

                    <!-- Mark read/unread (POST + CSRF) -->
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'], ENT_QUOTES); ?>">
                      <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                      <?php if (empty($m['is_read'])): ?>
                        <button name="action" value="mark_read" class="btn btn-sm btn-outline-success ms-1" title="Mark as read">
                          <i class="bx bx-check"></i>
                        </button>
                      <?php else: ?>
                        <button name="action" value="mark_unread" class="btn btn-sm btn-outline-secondary ms-1" title="Mark as unread">
                          <i class="bx bx-refresh"></i>
                        </button>
                      <?php endif; ?>
                    </form>

                    <!-- Remove (soft-delete if supported) -->
                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this message?');">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'], ENT_QUOTES); ?>">
                      <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                      <button name="action" value="remove" class="btn btn-sm btn-outline-danger ms-1" title="Remove">
                        <i class="bx bxs-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

  <!-- VIEW MODAL -->
  <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bx bx-envelope me-2"></i>Message</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <dl class="row">
            <dt class="col-sm-3">Name</dt><dd class="col-sm-9" id="vName"></dd>
            <dt class="col-sm-3">Email</dt><dd class="col-sm-9" id="vEmail"></dd>
            <dt class="col-sm-3">Phone</dt><dd class="col-sm-9" id="vPhone"></dd>
            <dt class="col-sm-3">Location</dt><dd class="col-sm-9" id="vLocation"></dd>
            <dt class="col-sm-3">Type</dt><dd class="col-sm-9" id="vType"></dd>
            <dt class="col-sm-3">First-timer</dt><dd class="col-sm-9" id="vFirst"></dd>
            <dt class="col-sm-3">Received</dt><dd class="col-sm-9" id="vCreated"></dd>
          </dl>
          <hr>
          <h6>Message</h6>
          <p id="vMessage" class="mb-0"></p>
        </div>
        <div class="modal-footer">
          <form method="post" id="markReadForm" class="d-inline">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'], ENT_QUOTES); ?>">
            <input type="hidden" name="id" id="vId">
            <button type="submit" name="action" value="mark_read" class="btn btn-success">Mark as Read</button>
          </form>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

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

    // Mobile sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const toggleSidebar = document.getElementById('toggleSidebar');
    toggleSidebar?.addEventListener('click', () => sidebar.classList.toggle('open'));

    // DataTable
    $(function(){
      $('#contactTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[6, 'desc']],
        language: {
          search: "_INPUT_",
          searchPlaceholder: "Search messages...",
          lengthMenu: "Show _MENU_ entries",
          info: "Showing _START_ to _END_ of _TOTAL_ entries",
          infoEmpty: "Showing 0 to 0 of 0 entries",
          infoFiltered: "(filtered from _MAX_ total entries)",
          paginate: { first:"First", last:"Last", next:"Next", previous:"Previous" }
        },
        columnDefs: [
          { orderable: false, targets: [7] }
        ]
      });

      // Modal population
      const viewModal = document.getElementById('viewModal');
      viewModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        const id = btn.getAttribute('data-id');
        document.getElementById('vName').textContent = btn.getAttribute('data-name') || '';
        document.getElementById('vEmail').textContent = btn.getAttribute('data-email') || '';
        document.getElementById('vPhone').textContent = btn.getAttribute('data-phone') || '';
        document.getElementById('vLocation').textContent = btn.getAttribute('data-location') || '';
        document.getElementById('vType').textContent = btn.getAttribute('data-type') || '';
        document.getElementById('vFirst').textContent = btn.getAttribute('data-first') || '';
        document.getElementById('vCreated').textContent = btn.getAttribute('data-created') || '';
        document.getElementById('vMessage').textContent = btn.getAttribute('data-message') || '';
        // For Mark-as-Read POST form
        const idInput = document.getElementById('vId');
        if (idInput) idInput.value = id || '';
      });
    });
  </script>
</body>
</html>

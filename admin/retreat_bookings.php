<?php
/**
 * retreat_bookings.php (view, delete/restore, CSV export)
 *
 * - Auth required
 * - CSRF-protected POST actions
 * - Soft-delete support if retreat_bookings.is_deleted exists
 * - "Trash" view when soft-delete exists; includes Restore
 * - CSV export of current view (Active or Trash)
 * - No SELECT *
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
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_SESSION['csrf'])) {
      http_response_code(400);
      exit('Invalid CSRF token');
    }
  }
}

// ---------- Detect soft-delete support ----------
$hasSoftDelete = false;
try {
  $q = $pdo->query("
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'retreat_bookings'
      AND COLUMN_NAME = 'is_deleted'
    LIMIT 1
  ");
  if ($q && $q->fetchColumn()) {
    $hasSoftDelete = true;
  } else {
    $q = $pdo->query("SHOW COLUMNS FROM retreat_bookings LIKE 'is_deleted'");
    if ($q && $q->fetch(PDO::FETCH_ASSOC)) $hasSoftDelete = true;
  }
} catch (Throwable $e) { $hasSoftDelete = false; }

// Which view? active or trash
$showTrash = $hasSoftDelete && (isset($_GET['show']) && $_GET['show'] === 'trash');

// ---------- Actions (POST only) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();

  $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
  $id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;

  if ($action === 'delete') {
    // Active view delete -> soft if supported, else hard
    if ($id <= 0) { $message="Invalid request."; $message_type="danger"; }
    else {
      try {
        if ($hasSoftDelete) {
          $stmt = $pdo->prepare("UPDATE retreat_bookings SET is_deleted = 1 WHERE id = ?");
          $stmt->execute([$id]);
          $message = "Booking moved to Trash.";
          $message_type = "success";
        } else {
          $stmt = $pdo->prepare("DELETE FROM retreat_bookings WHERE id = ?");
          $stmt->execute([$id]);
          $message = "Booking deleted.";
          $message_type = "success";
        }
      } catch (Throwable $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
      }
    }
  } elseif ($action === 'restore' && $hasSoftDelete) {
    // Trash view restore
    if ($id <= 0) { $message="Invalid request."; $message_type="danger"; }
    else {
      try {
        $stmt = $pdo->prepare("UPDATE retreat_bookings SET is_deleted = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Booking restored.";
        $message_type = "success";
      } catch (Throwable $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
      }
    }
  } elseif ($action === 'export_csv') {
    // Export current view as CSV (respects Active vs Trash)
    try {
      if ($hasSoftDelete) {
        if ($showTrash) {
          $exp = $pdo->prepare("
            SELECT id, name, phone, email, checkin, checkout, guests, purpose, notes, created_at, 1 AS deleted
            FROM retreat_bookings
            WHERE COALESCE(is_deleted,0) = 1
            ORDER BY created_at DESC
          ");
        } else {
          $exp = $pdo->prepare("
            SELECT id, name, phone, email, checkin, checkout, guests, purpose, notes, created_at, 0 AS deleted
            FROM retreat_bookings
            WHERE COALESCE(is_deleted,0) = 0
            ORDER BY created_at DESC
          ");
        }
      } else {
        $exp = $pdo->prepare("
          SELECT id, name, phone, email, checkin, checkout, guests, purpose, notes, created_at, NULL AS deleted
          FROM retreat_bookings
          ORDER BY created_at DESC
        ");
      }
      $exp->execute();
      $rows = $exp->fetchAll(PDO::FETCH_ASSOC);

      $filename = 'retreat_bookings_' . ($showTrash ? 'trash_' : 'active_') . date('Ymd_His') . '.csv';
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="'.$filename.'"');

      $out = fopen('php://output', 'w');
      // Header
      fputcsv($out, ['ID','Name','Phone','Email','Check-in','Check-out','Guests','Purpose','Notes','Created At','Deleted']);
      foreach ($rows as $r) {
        fputcsv($out, [
          $r['id'],
          $r['name'],
          $r['phone'],
          $r['email'],
          $r['checkin'],
          $r['checkout'],
          $r['guests'],
          $r['purpose'],
          $r['notes'],
          $r['created_at'],
          ($r['deleted'] === null ? '' : ($r['deleted'] ? 'Yes' : 'No'))
        ]);
      }
      fclose($out);
      exit; // Important: stop normal HTML output
    } catch (Throwable $e) {
      $message = "Export error: " . $e->getMessage();
      $message_type = "danger";
    }
  }
}

// ---------- Load bookings (no SELECT *) ----------
if ($hasSoftDelete) {
  if ($showTrash) {
    $stmt = $pdo->prepare("
      SELECT id, name, email, phone, checkin, checkout, guests, purpose, notes, created_at
      FROM retreat_bookings
      WHERE COALESCE(is_deleted, 0) = 1
      ORDER BY created_at DESC
    ");
  } else {
    $stmt = $pdo->prepare("
      SELECT id, name, email, phone, checkin, checkout, guests, purpose, notes, created_at
      FROM retreat_bookings
      WHERE COALESCE(is_deleted, 0) = 0
      ORDER BY created_at DESC
    ");
  }
} else {
  $stmt = $pdo->prepare("
    SELECT id, name, email, phone, checkin, checkout, guests, purpose, notes, created_at
    FROM retreat_bookings
    ORDER BY created_at DESC
  ");
}
$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin · Retreat Bookings</title>

  <!-- Vendor CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css"/>

  <!-- PNG fallbacks -->
  <link rel="icon" type="image/png" sizes="32x32" href="../images/book-heart.png">
  <link rel="icon" type="image/png" sizes="16x16" href="../images/book-heart.png">

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
    .btn-outline-primary{ border:2px solid #E2E8F0; border-radius:12px; background:#fff; color:#2D3748; transition:.25s; }
    .btn-outline-primary:hover{ background: var(--gradient-hover); border-color:transparent; color:#fff; transform:translateY(-1px); }

    .sidebar{
      width: 280px; min-height: 100vh; background:#fff; border-right:1px solid rgba(0,0,0,.06);
      position: fixed; left:0; top:0; z-index:100; display:flex; flex-direction:column; box-shadow:var(--shadow-soft);
    }
    .sidebar .brand{ background:var(--gradient-primary); color:#fff; padding:1rem 1.25rem; font-weight:700; font-family:'Playfair Display',serif; display:flex; align-items:center; gap:.5rem; }
    .sidebar .menu{ padding:1rem; overflow-y:auto; }
    .sidebar .nav-link{ color:var(--ink); border-radius:.7rem; font-weight:500; padding:.75rem .9rem; display:flex; align-items:center; gap:.6rem; transition:.25s ease; }
    .sidebar .nav-link:hover{ background:rgba(60,145,230,.08); color:var(--brand); transform:translateX(2px); }
    .sidebar .nav-link.active{ background:rgba(60,145,230,.14); color:var(--brand-dark); }

    .topbar{
      height:72px; background:var(--gradient-hero); color:#fff;
      border-bottom:1px solid rgba(255,255,255,.2);
      display:flex; align-items:center; justify-content:flex-end;
      padding:0 1rem; position:fixed; top:0; right:0; left:280px; z-index:90; box-shadow:var(--shadow-medium);
    }
    .topbar .hamburger{ display:none; border:0; background:transparent; color:#fff; }
    .topbar .dropdown-toggle{ color:#fff; }
    .topbar .dropdown-toggle i, .topbar .dropdown-toggle span{ color:#fff !important; }

    .main{ padding:1.25rem; margin-left:280px; }
    .page-content{ margin-top:84px; }
    .card{ border:0; border-radius:18px; box-shadow:var(--shadow-soft); }
    .card .card-title{ font-weight:700; }
    .admin-hero{ background:var(--gradient-hero); color:#fff; border-radius:20px; padding:1.5rem 1.75rem; box-shadow:var(--shadow-medium); position:relative; overflow:hidden; }
    .admin-hero::after{
      content:''; position:absolute; inset:0;
      background:
        radial-gradient(800px 200px at 0% 0%, rgba(255,255,255,.15), transparent 60%),
        radial-gradient(800px 200px at 100% 100%, rgba(255,255,255,.12), transparent 60%);
      pointer-events:none;
    }

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
        <li class="nav-item"><a class="nav-link active" href="retreat_bookings.php"><i class="bx bxs-spa"></i>Retreat Bookings</a></li>
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
            <h2 class="mb-1">Retreat Center Bookings <?php echo $showTrash ? '(Trash)' : ''; ?></h2>
            <div class="opacity-75"><?php echo $showTrash ? 'Restore or export deleted requests.' : 'View, delete, or export requests.'; ?></div>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <?php if ($hasSoftDelete): ?>
              <?php if ($showTrash): ?>
                <a href="retreat_bookings.php" class="btn btn-outline-primary"><i class="bx bx-left-arrow-alt me-1"></i>Back to Active</a>
              <?php else: ?>
                <a href="retreat_bookings.php?show=trash" class="btn btn-outline-primary"><i class="bx bx-trash me-1"></i>View Trash</a>
              <?php endif; ?>
            <?php endif; ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'], ENT_QUOTES); ?>">
              <button class="btn btn-gradient" name="action" value="export_csv" type="submit">
                <i class="bx bx-download me-1"></i>Export CSV
              </button>
            </form>
            <a href="../index.php#retreat" target="_blank" class="btn btn-outline-primary">
              <i class="bx bx-show me-1"></i>View Site
            </a>
          </div>
        </div>
      </div>

      <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type==='success'?'success':'danger'; ?> alert-dismissible fade show glass" role="alert" data-aos="fade-up">
          <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <!-- Table -->
      <div class="card p-4" data-aos="fade-up" data-aos-delay="50">
        <div class="table-responsive">
          <table id="retreatTable" class="table table-hover align-middle w-100">
            <thead>
              <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Dates</th>
                <th>Guests</th>
                <th>Purpose</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $b): ?>
                <tr>
                  <td class="fw-semibold"><?php echo htmlspecialchars($b['name'] ?? '', ENT_QUOTES); ?></td>
                  <td class="small"><?php echo htmlspecialchars($b['phone'] ?? '', ENT_QUOTES); ?></td>
                  <td class="small text-muted"><?php echo htmlspecialchars($b['email'] ?? '', ENT_QUOTES); ?></td>
                  <td class="small">
                    <?php
                      $din = htmlspecialchars($b['checkin'] ?? '', ENT_QUOTES);
                      $dout = htmlspecialchars($b['checkout'] ?? '', ENT_QUOTES);
                      echo $din . ' → ' . $dout;
                    ?>
                  </td>
                  <td class="small"><?php echo (int)($b['guests'] ?? 0); ?></td>
                  <td class="small"><?php echo htmlspecialchars($b['purpose'] ?? '', ENT_QUOTES); ?></td>
                  <td class="small text-muted"><?php echo htmlspecialchars($b['created_at'] ?? '', ENT_QUOTES); ?></td>
                  <td class="text-end text-nowrap">
                    <!-- View -->
                    <button class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#viewModal"
                            data-id="<?php echo (int)$b['id']; ?>"
                            data-name="<?php echo htmlspecialchars($b['name'] ?? '', ENT_QUOTES); ?>"
                            data-email="<?php echo htmlspecialchars($b['email'] ?? '', ENT_QUOTES); ?>"
                            data-phone="<?php echo htmlspecialchars($b['phone'] ?? '', ENT_QUOTES); ?>"
                            data-checkin="<?php echo htmlspecialchars($b['checkin'] ?? '', ENT_QUOTES); ?>"
                            data-checkout="<?php echo htmlspecialchars($b['checkout'] ?? '', ENT_QUOTES); ?>"
                            data-guests="<?php echo (int)($b['guests'] ?? 0); ?>"
                            data-purpose="<?php echo htmlspecialchars($b['purpose'] ?? '', ENT_QUOTES); ?>"
                            data-notes="<?php echo htmlspecialchars($b['notes'] ?? '', ENT_QUOTES); ?>"
                            data-created="<?php echo htmlspecialchars($b['created_at'] ?? '', ENT_QUOTES); ?>">
                      <i class="bx bx-show"></i>
                    </button>

                    <?php if ($showTrash && $hasSoftDelete): ?>
                      <!-- Restore -->
                      <form method="post" class="d-inline" onsubmit="return confirm('Restore this booking?');">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'], ENT_QUOTES); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                        <button name="action" value="restore" class="btn btn-sm btn-outline-success ms-1" title="Restore">
                          <i class="bx bx-undo"></i>
                        </button>
                      </form>
                    <?php else: ?>
                      <!-- Delete (to Trash if soft-delete) -->
                      <form method="post" class="d-inline" onsubmit="return confirm('Delete this booking?');">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'], ENT_QUOTES); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                        <button name="action" value="delete" class="btn btn-sm btn-outline-danger ms-1" title="Delete">
                          <i class="bx bxs-trash"></i>
                        </button>
                      </form>
                    <?php endif; ?>
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
          <h5 class="modal-title"><i class="bx bx-spa me-2"></i>Retreat Booking</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <dl class="row">
            <dt class="col-sm-3">Name</dt><dd class="col-sm-9" id="vName"></dd>
            <dt class="col-sm-3">Phone</dt><dd class="col-sm-9" id="vPhone"></dd>
            <dt class="col-sm-3">Email</dt><dd class="col-sm-9" id="vEmail"></dd>
            <dt class="col-sm-3">Check-in</dt><dd class="col-sm-9" id="vCheckin"></dd>
            <dt class="col-sm-3">Check-out</dt><dd class="col-sm-9" id="vCheckout"></dd>
            <dt class="col-sm-3">Guests</dt><dd class="col-sm-9" id="vGuests"></dd>
            <dt class="col-sm-3">Purpose</dt><dd class="col-sm-9" id="vPurpose"></dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9" id="vCreated"></dd>
          </dl>
          <hr>
          <h6>Notes / Special Requests</h6>
          <p id="vNotes" class="mb-0"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button>
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

    // Toastr defaults
    toastr.options = {
      closeButton: true, progressBar: true, newestOnTop: true, preventDuplicates: true,
      positionClass: "toast-top-right", timeOut: 3500, extendedTimeOut: 1500
    };

    // Mobile sidebar toggle
    const sidebar = document.getElementById('sidebar');
    document.getElementById('toggleSidebar')?.addEventListener('click', () => sidebar.classList.toggle('open'));

    // DataTable
    $(function(){
      $('#retreatTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[6, 'desc']],
        language: {
          search: "_INPUT_",
          searchPlaceholder: "Search bookings...",
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

      // View modal population
      const viewModal = document.getElementById('viewModal');
      viewModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('vName').textContent     = btn.getAttribute('data-name') || '';
        document.getElementById('vPhone').textContent    = btn.getAttribute('data-phone') || '';
        document.getElementById('vEmail').textContent    = btn.getAttribute('data-email') || '';
        document.getElementById('vCheckin').textContent  = btn.getAttribute('data-checkin') || '';
        document.getElementById('vCheckout').textContent = btn.getAttribute('data-checkout') || '';
        document.getElementById('vGuests').textContent   = btn.getAttribute('data-guests') || '';
        document.getElementById('vPurpose').textContent  = btn.getAttribute('data-purpose') || '';
        document.getElementById('vCreated').textContent  = btn.getAttribute('data-created') || '';
        document.getElementById('vNotes').textContent    = btn.getAttribute('data-notes') || '';
      });
    });
  </script>
</body>
</html>

<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
require_once '../includes/db.php';

/** ---------- Helpers ---------- */
function fetchCount($pdo, $sql, $params = []) {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (int)$st->fetchColumn();
  } catch (Throwable $e) { return 0; }
}

function fetchAllSafe($pdo, $sql, $params = []) {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { return []; }
}

/**
 * Monthly buckets for the last 12 months as [labels[], data[]]
 * $dateExpr is a SQL expression that yields a date/datetime (e.g., created_at OR COALESCE(created_at,event_date))
 */
function monthlySeries($pdo, $table, $dateExpr = 'created_at') {
  // Build last 12 months map: YYYY-MM => 0
  $labels = [];
  $map = [];
  $cur = new DateTime('first day of this month');
  for ($i=11; $i>=0; $i--) {
    $d = (clone $cur)->modify("-{$i} months")->format('Y-m');
    $labels[] = $d;
    $map[$d] = 0;
  }

  try {
    $sql = "SELECT DATE_FORMAT($dateExpr, '%Y-%m') ym, COUNT(*) c
            FROM `$table`
            WHERE $dateExpr IS NOT NULL AND $dateExpr >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
            GROUP BY ym";
    $rows = fetchAllSafe($pdo, $sql);
    foreach ($rows as $r) {
      $ym = $r['ym'];
      if (isset($map[$ym])) $map[$ym] = (int)$r['c'];
    }
  } catch (Throwable $e) {
    // keep zeros on error
  }

  return ['labels'=>$labels, 'data'=>array_values($map)];
}

/** ---------- Live counts ---------- */
$events_total     = fetchCount($pdo, "SELECT COUNT(*) FROM events");
$events_active    = fetchCount($pdo, "SELECT COUNT(*) FROM events WHERE status IN ('Upcoming','Ongoing')");
$sermons_total    = fetchCount($pdo, "SELECT COUNT(*) FROM sermon_videos");
$posts_total      = fetchCount($pdo, "SELECT COUNT(*) FROM posts WHERE status='Published'");
$messages_total   = fetchCount($pdo, "SELECT COUNT(*) FROM contact_messages");
$comments_pending = fetchCount($pdo, "SELECT COUNT(*) FROM comments WHERE is_approved = 0");

/** ---------- Recent activity (latest across entities) ---------- */
$activity = [];

// Events: newest 5 by COALESCE(created_at, event_date)
$ev = fetchAllSafe($pdo, "SELECT id, title, COALESCE(created_at, event_date) AS ts FROM events ORDER BY ts DESC LIMIT 5");
foreach ($ev as $row) {
  $activity[] = [
    'who' => 'System',
    'action' => 'Event: ' . ($row['title'] ?? 'Updated'),
    'time' => $row['ts'],
  ];
}

// Sermons: newest 5
$sv = fetchAllSafe($pdo, "SELECT id, title, created_at AS ts FROM sermon_videos ORDER BY created_at DESC LIMIT 5");
foreach ($sv as $row) {
  $activity[] = [
    'who' => 'System',
    'action' => 'Sermon: ' . ($row['title'] ?? 'Uploaded'),
    'time' => $row['ts'],
  ];
}

// Posts: newest 5
$pv = fetchAllSafe($pdo, "SELECT id, title, created_at AS ts FROM posts ORDER BY created_at DESC LIMIT 5");
foreach ($pv as $row) {
  $activity[] = [
    'who' => 'System',
    'action' => 'Post: ' . ($row['title'] ?? 'Created'),
    'time' => $row['ts'],
  ];
}

// Contact messages: newest 5
$cm = fetchAllSafe($pdo, "SELECT id, name, created_at AS ts FROM contact_messages ORDER BY created_at DESC LIMIT 5");
foreach ($cm as $row) {
  $activity[] = [
    'who' => $row['name'] ?: 'Visitor',
    'action' => 'Submitted a contact message',
    'time' => $row['ts'],
  ];
}

// Sort all by time desc and keep top 8
usort($activity, function($a,$b){
  $ta = strtotime($a['time'] ?? '1970-01-01');
  $tb = strtotime($b['time'] ?? '1970-01-01');
  return $tb <=> $ta;
});
$activity = array_slice($activity, 0, 3);

/** ---------- Sparkline data (last 12 months) ---------- */
$seriesEvents   = monthlySeries($pdo, 'events', "COALESCE(created_at, event_date)");
$seriesSermons  = monthlySeries($pdo, 'sermon_videos', 'created_at');
$seriesPosts    = monthlySeries($pdo, 'posts', 'created_at');
$seriesMessages = monthlySeries($pdo, 'contact_messages', 'created_at');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard</title>

  <!-- CSS: Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <!-- AOS -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <!-- Toastr -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <!-- PNG fallbacks (optional) -->
  <link rel="icon" type="image/png" sizes="32x32" href="../images/book-heart.png" >
  <link rel="icon" type="image/png" sizes="16x16" href="../images/book-heart.png" >

  <style>
    :root{
      /* === Unified tokens (same as index.php) === */
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
      --shadow-soft: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
      --shadow-medium: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -2px rgba(0,0,0,.05);
      --shadow-large: 0 20px 25px -5px rgba(0,0,0,.1), 0 10px 10px -5px rgba(0,0,0,.04);
      --shadow-glow: 0 0 40px rgba(60, 145, 230, 0.15);
    }

    html{scroll-behavior:smooth}
    body{
      background: linear-gradient(135deg, var(--soft) 0%, var(--soft-dark) 100%);
      color: var(--ink);
      font-family: 'Inter', sans-serif;
      overflow-x:hidden;
    }

    /* UTILITIES (shared vibe) */
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
      padding:.85rem 1rem;
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
      border:2px solid #E2E8F0; border-radius:14px; background:#fff; color:#2D3748; transition:.25s;
    }
    .btn-outline-primary:hover{
      background: var(--gradient-hover); border-color: transparent; color:#fff; transform: translateY(-1px);
    }

    /* Sidebar */
    .sidebar{
      width: 280px; min-height: 100vh; background: #fff; border-right: 1px solid rgba(0,0,0,.06);
      position: fixed; left: 0; top: 0; z-index: 100; display:flex; flex-direction:column; box-shadow: var(--shadow-soft);
    }
    .sidebar .brand{
      background: var(--gradient-primary); color:#fff; padding:1rem 1.25rem; font-weight:700;
      font-family:'Playfair Display',serif; display:flex; align-items:center; gap:.5rem;
    }
    .sidebar .menu{ padding:1rem; overflow-y:auto; }
    .sidebar .nav-link{
      color:var(--ink); border-radius:.7rem; font-weight:500; padding:.75rem .9rem;
      display:flex; align-items:center; gap:.6rem; transition:.25s ease;
      position: relative;
    }
    .sidebar .nav-link:hover{ background:rgba(60,145,230,.08); color:var(--brand); transform:translateX(2px); }
    .sidebar .nav-link.active{
      background:rgba(60,145,230,.14); color:var(--brand-dark);
    }

    /* Topbar */
    .topbar{
      height:72px; background:#fff; border-bottom:1px solid rgba(0,0,0,.06);
      display:flex; align-items:center; justify-content:space-between;
      padding:0 1rem; position:fixed; top:0; right:0; left:280px; z-index:90; box-shadow:var(--shadow-soft);
      backdrop-filter: blur(10px);
    }
    .topbar .hamburger{ display:none; border:0; background:transparent; }
    .topbar .hamburger i{ font-size:1.8rem; }

    /* Main */
    .main{ padding:1.25rem; margin-left:280px; }
    .page-content{ margin-top:84px; }

    /* Hero */
    .admin-hero{
      background:var(--gradient-hero); color:#fff; border-radius:20px; box-shadow:var(--shadow-medium);
      padding:2rem; position:relative; overflow:hidden;
    }
    .admin-hero::after{ content:''; position:absolute; inset:0;
      background:
        radial-gradient(800px 200px at 0% 0%, rgba(255,255,255,.15), transparent 60%),
        radial-gradient(800px 200px at 100% 100%, rgba(255,255,255,.12), transparent 60%);
      pointer-events:none; }
    .admin-hero h2{ font-family:'Playfair Display',serif; font-weight:700; }

    /* Stat Cards */
    .stat-card{ border:0; border-radius:18px; box-shadow:var(--shadow-soft); overflow:hidden; background:#fff; position:relative; }
    .stat-card .top{ display:flex; align-items:center; justify-content:space-between; padding:1.2rem 1.2rem .6rem 1.2rem; }
    .stat-card .icon-badge{ width:46px; height:46px; border-radius:12px; display:grid; place-items:center; color:#fff; background:var(--gradient-primary); box-shadow:var(--shadow-soft); }
    .stat-card .value{ font-size:1.8rem; font-weight:700; }
    .spark-wrap{ padding:0 1rem 1rem 1rem; }

    .card{ border:0; border-radius:18px; box-shadow:var(--shadow-soft); }
    .card .card-title{ font-weight:700; }

    .quick-actions .btn{ border-radius:14px; padding:.9rem 1rem; font-weight:600; display:flex; align-items:center; gap:.5rem; box-shadow:var(--shadow-soft); }

    .table thead th{ font-weight:700; color:#4A5568; border-bottom:2px solid #EDF2F7; }
    .table-hover tbody tr:hover{ background:#F8FAFC; }

    /* Responsive */
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
    <div class="brand">
      <i class="bx bxs-dashboard"></i><span>Admin Panel</span>
    </div>
    <nav class="menu">
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bx bxs-dashboard"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="home.php"><i class="bx bxs-home"></i>Home/Hero</a></li>
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
    <button class="hamburger" id="toggleSidebar" aria-label="Toggle sidebar"><i class="bx bx-menu"></i></button>
    <div class="d-flex align-items-center gap-3 ms-auto">
      <span class="text-muted small d-none d-md-inline" id="today"></span>
      <div class="dropdown">
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
  </div>

  <!-- MAIN -->
  <main class="main">
    <div class="page-content">

      <!-- HERO -->
      <div class="admin-hero mb-4" data-aos="fade-up">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
          <div>
            <h2 class="mb-1">Welcome, <span class="text-white"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span> 👋</h2>
            <div class="meta">Live overview of sermons, events, posts, and messages.</div>
          </div>
          <div class="d-flex gap-2">
            <a href="sermons.php" class="btn btn-gradient"><i class="bx bx-video me-1"></i> Upload Sermon</a>
            <a href="events.php" class="btn btn-outline-primary"><i class="bx bx-calendar-plus me-1"></i> Add Event</a>
          </div>
        </div>
      </div>

      <!-- STATS (Dynamic) -->
      <div class="row g-3">
        <div class="col-md-6 col-xl-3" data-aos="fade-up">
          <div class="stat-card">
            <div class="top">
              <div>
                <div class="small text-muted">Events (Active)</div>
                <div class="value"><?php echo number_format($events_active); ?> / <?php echo number_format($events_total); ?></div>
              </div>
              <div class="icon-badge"><i class="bx bxs-calendar"></i></div>
            </div>
            <div class="px-3 pb-2 small text-muted">Upcoming & Ongoing</div>
            <div class="spark-wrap"><canvas id="sparkEvents" height="60"></canvas></div>
          </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="100">
          <div class="stat-card">
            <div class="top">
              <div>
                <div class="small text-muted">Sermons</div>
                <div class="value"><?php echo number_format($sermons_total); ?></div>
              </div>
              <div class="icon-badge"><i class="bx bxs-microphone"></i></div>
            </div>
            <div class="px-3 pb-2 small text-muted">Total uploaded</div>
            <div class="spark-wrap"><canvas id="sparkSermons" height="60"></canvas></div>
          </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="200">
          <div class="stat-card">
            <div class="top">
              <div>
                <div class="small text-muted">Posts</div>
                <div class="value"><?php echo number_format($posts_total); ?></div>
              </div>
              <div class="icon-badge"><i class="bx bxs-news"></i></div>
            </div>
            <div class="px-3 pb-2 small text-muted">Published</div>
            <div class="spark-wrap"><canvas id="sparkPosts" height="60"></canvas></div>
          </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="300">
          <div class="stat-card">
            <div class="top">
              <div>
                <div class="small text-muted">Messages</div>
                <div class="value"><?php echo number_format($messages_total); ?></div>
              </div>
              <div class="icon-badge"><i class="bx bxs-message"></i></div>
            </div>
            <div class="px-3 pb-2 small text-muted">
              <?php if ($comments_pending > 0): ?>
                <span class="text-warning"><i class="bx bx-comment-dots me-1"></i><?php echo number_format($comments_pending); ?> comments pending</span>
              <?php else: ?>
                <span class="text-muted">No pending comments</span>
              <?php endif; ?>
            </div>
            <div class="spark-wrap"><canvas id="sparkMessages" height="60"></canvas></div>
          </div>
        </div>
      </div>

      <!-- CONTENT ROW -->
      <div class="row mt-4">
        <!-- Recent Activity (Dynamic) -->
        <div class="col-lg-8 mb-4" data-aos="fade-up">
          <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="card-title mb-0">Recent Activity</h5>
              <span class="text-muted small">Auto-generated</span>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr><th>User/Source</th><th>Action</th><th style="width:180px;">Time</th></tr>
                </thead>
                <tbody>
                  <?php if (empty($activity)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">No recent activity yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($activity as $row): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($row['who'] ?? 'System'); ?></td>
                        <td><?php echo htmlspecialchars($row['action'] ?? 'Updated'); ?></td>
                        <td class="text-muted small">
                          <?php
                            $ts = $row['time'] ?? '';
                            echo $ts ? date('d M Y g:i A', strtotime($ts)) : '-';
                          ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="150">
          <div class="card p-4">
            <h5 class="card-title mb-3">Quick Actions</h5>
            <div class="d-grid gap-2 quick-actions">
              <a class="btn btn-gradient" href="events.php"><i class="bx bx-plus"></i> Add New Event</a>
              <a class="btn btn-gradient" href="sermons.php"><i class="bx bx-upload"></i> Upload Sermon</a>
              <a class="btn btn-gradient" href="posts.php"><i class="bx bx-edit"></i> Write Post</a>
              <a class="btn btn-outline-primary" href="footer.php"><i class="bx bx-cog"></i> Settings</a>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /.page-content -->
  </main>

  <!-- JS: Bootstrap, jQuery, Toastr, AOS, Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <script>
    // AOS init
    AOS.init({ duration: 800, easing: 'ease-out-quart', once: true, offset: 50 });

    // Toastr defaults
    if (window.toastr) {
      toastr.options = {
        closeButton: true, progressBar: true, newestOnTop: true, preventDuplicates: true,
        positionClass: "toast-top-right", timeOut: 3500, extendedTimeOut: 1500,
        showMethod: "fadeIn", hideMethod: "fadeOut"
      };
    }

    // Sidebar toggle (mobile)
    const sidebar = document.getElementById('sidebar');
    const toggleSidebar = document.getElementById('toggleSidebar');
    toggleSidebar?.addEventListener('click', () => sidebar.classList.toggle('open'));

    // Today text
    const today = document.getElementById('today');
    const fmt = new Intl.DateTimeFormat(undefined, { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    if (today) today.textContent = fmt.format(new Date());

    // Simple sparkline helper (uses rgba background to avoid helpers.color)
    const sparkline = (id, labels, data, borderColor, bgAlpha=0.15) => {
      const ctx = document.getElementById(id);
      if (!ctx) return;
      // convert hex to rgba with alpha
      const hex = borderColor.replace('#','');
      const r = parseInt(hex.substring(0,2),16);
      const g = parseInt(hex.substring(2,4),16);
      const b = parseInt(hex.substring(4,6),16);
      const bg = `rgba(${r}, ${g}, ${b}, ${bgAlpha})`;

      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            data,
            tension:.35,
            borderColor,
            backgroundColor: bg,
            fill:true,
            borderWidth:2,
            pointRadius:0
          }]
        },
        options: {
          plugins:{ legend:{display:false}, tooltip:{enabled:false} },
          scales:{ x:{display:false}, y:{display:false} },
          responsive:true, maintainAspectRatio:false
        }
      });
    };

    // Data from PHP
    const evLabels = <?php echo json_encode($seriesEvents['labels']); ?>;
    const evData   = <?php echo json_encode($seriesEvents['data']); ?>;
    const svLabels = <?php echo json_encode($seriesSermons['labels']); ?>;
    const svData   = <?php echo json_encode($seriesSermons['data']); ?>;
    const psLabels = <?php echo json_encode($seriesPosts['labels']); ?>;
    const psData   = <?php echo json_encode($seriesPosts['data']); ?>;
    const msLabels = <?php echo json_encode($seriesMessages['labels']); ?>;
    const msData   = <?php echo json_encode($seriesMessages['data']); ?>;

    // Draw sparklines
    sparkline('sparkEvents',   evLabels, evData,   '#3C91E6'); // brand
    sparkline('sparkSermons',  svLabels, svData,   '#8A63D2'); // purple
    sparkline('sparkPosts',    psLabels, psData,   '#2BB673'); // green
    sparkline('sparkMessages', msLabels, msData,   '#FD7238'); // orange

    // Welcome toast on fresh login
    <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
    $(function(){ if (window.toastr) toastr.success("Welcome to the Admin Dashboard.", "Login Successful"); });
    <?php endif; ?>
  </script>
</body>
</html>

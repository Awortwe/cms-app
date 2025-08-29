<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
require_once '../includes/db.php';

// Handle form actions (unchanged backend logic)
$message = '';
$message_type = '';

// Add new event
if (isset($_POST['add_event'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $location = $_POST['location'];
    $event_type = $_POST['event_type'];
    $status = $_POST['status'];
    $action_text = $_POST['action_text'];
    $action_link = $_POST['action_link'];

    $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, event_time, location, event_type, status, action_text, action_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt->execute([$title, $description, $event_date, $event_time, $location, $event_type, $status, $action_text, $action_link])) {
        $message = "Event added successfully!";
        $message_type = "success";
    } else {
        $message = "Error adding event.";
        $message_type = "error";
    }
}

// Update event
if (isset($_POST['update_event'])) {
    $id = $_POST['event_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $location = $_POST['location'];
    $event_type = $_POST['event_type'];
    $status = $_POST['status'];
    $action_text = $_POST['action_text'];
    $action_link = $_POST['action_link'];

    $stmt = $pdo->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, event_time = ?, location = ?, event_type = ?, status = ?, action_text = ?, action_link = ? WHERE id = ?");

    if ($stmt->execute([$title, $description, $event_date, $event_time, $location, $event_type, $status, $action_text, $action_link, $id])) {
        $message = "Event updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating event.";
        $message_type = "error";
    }
}

// Delete event
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");

    if ($stmt->execute([$id])) {
        $message = "Event deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting event.";
        $message_type = "error";
    }
}

// Get all events
$stmt = $pdo->prepare("SELECT * FROM events ORDER BY event_date DESC, event_time DESC");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get event for editing
$edit_event = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $edit_event = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Events</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <!-- DataTables -->
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
      --gradient-primary: linear-gradient(135deg, var(--brand) 0%, var(--accent) 100%);
      --shadow-soft: 0 6px 16px rgba(0,0,0,.06);
      --shadow-medium: 0 10px 24px rgba(0,0,0,.10);
    }
    html{scroll-behavior:smooth}
    body{ background:var(--soft); color:var(--ink); font-family:'Inter',sans-serif; overflow-x:hidden; }

    /* Sidebar (same as home.php) */
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

    /* Form fields */
    .form-control, .form-select{ border:2px solid #E2E8F0; border-radius:12px; padding:0.9rem 1rem; background:#FAFAFA; transition:.2s; }
    .form-control:focus, .form-select:focus{ border-color:var(--brand); box-shadow:0 0 0 .2rem rgba(60,145,230,.15); background:#fff; }
    .form-label{ font-weight:600; color:var(--ink); }

    /* Sticky save */
    .sticky-actions{ position:sticky; bottom:0; z-index:5; background:#fff; padding:1rem; border-top:1px solid #EDF2F7; border-bottom-left-radius:18px; border-bottom-right-radius:18px; }

    /* Badges */
    .badge-soft{ background: rgba(60,145,230,.12); color: var(--brand); border: 1px solid rgba(60,145,230,.25); }
    .badge-completed { background: rgba(108,117,125,.12); color: #6c757d; border: 1px solid rgba(108,117,125,.25); }

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
        <li class="nav-item"><a class="nav-link active" href="events.php"><i class="bx bxs-calendar"></i>Events</a></li>
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

      <!-- Page header banner -->
      <div class="admin-hero mb-4" data-aos="fade-up">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
          <div>
            <h2 class="mb-1">Events</h2>
            <div class="opacity-75">Create, update, and organize events shown on the site.</div>
          </div>
          <div class="d-flex gap-2">
            <a href="../index.php#events" target="_blank" class="btn btn-light text-primary fw-semibold">
              <i class="bx bx-show me-1"></i> View Site
            </a>
          </div>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert" data-aos="fade-up">
          <?php echo htmlspecialchars($message); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <!-- Left: Event form -->
        <div class="col-lg-5" data-aos="fade-up">
          <div class="card p-4 h-100">
            <h5 class="card-title mb-4"><?php echo $edit_event ? 'Edit Event' : 'Add New Event'; ?></h5>

            <form method="POST" action="">
              <?php if ($edit_event): ?>
                <input type="hidden" name="event_id" value="<?php echo $edit_event['id']; ?>">
              <?php endif; ?>

              <div class="mb-3">
                <label for="title" class="form-label">Event Title</label>
                <input type="text" class="form-control" id="title" name="title"
                       value="<?php echo $edit_event ? htmlspecialchars($edit_event['title']) : ''; ?>" required>
              </div>

              <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" required><?php echo $edit_event ? htmlspecialchars($edit_event['description']) : ''; ?></textarea>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label for="event_date" class="form-label">Event Date</label>
                  <input type="date" class="form-control" id="event_date" name="event_date"
                         value="<?php echo $edit_event ? htmlspecialchars($edit_event['event_date']) : ''; ?>" required>
                </div>
                <div class="col-md-6">
                  <label for="event_time" class="form-label">Event Time (optional)</label>
                  <input type="time" class="form-control" id="event_time" name="event_time"
                         value="<?php echo $edit_event ? htmlspecialchars($edit_event['event_time']) : ''; ?>">
                </div>
              </div>

              <div class="mb-3 mt-1">
                <label for="location" class="form-label">Location</label>
                <input type="text" class="form-control" id="location" name="location"
                       value="<?php echo $edit_event ? htmlspecialchars($edit_event['location']) : ''; ?>" required>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label for="event_type" class="form-label">Event Type</label>
                  <select class="form-select" id="event_type" name="event_type" required>
                    <option value="Revival"    <?php echo ($edit_event && $edit_event['event_type'] == 'Revival')    ? 'selected' : ''; ?>>Revival</option>
                    <option value="Conference" <?php echo ($edit_event && $edit_event['event_type'] == 'Conference') ? 'selected' : ''; ?>>Conference</option>
                    <option value="Youth"      <?php echo ($edit_event && $edit_event['event_type'] == 'Youth')      ? 'selected' : ''; ?>>Youth</option>
                    <option value="Teaching"   <?php echo ($edit_event && $edit_event['event_type'] == 'Teaching')   ? 'selected' : ''; ?>>Teaching</option>
                    <option value="Other"      <?php echo ($edit_event && $edit_event['event_type'] == 'Other')      ? 'selected' : ''; ?>>Other</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="status" class="form-label">Status</label>
                  <select class="form-select" id="status" name="status" required>
                    <option value="Upcoming"  <?php echo ($edit_event && $edit_event['status'] == 'Upcoming')  ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="Ongoing"   <?php echo ($edit_event && $edit_event['status'] == 'Ongoing')   ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="Completed" <?php echo ($edit_event && $edit_event['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo ($edit_event && $edit_event['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                  </select>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-6">
                  <label for="action_text" class="form-label">Button Text</label>
                  <input type="text" class="form-control" id="action_text" name="action_text"
                         value="<?php echo $edit_event ? htmlspecialchars($edit_event['action_text']) : 'Request Invite'; ?>">
                </div>
                <div class="col-md-6">
                  <label for="action_link" class="form-label">Button Link</label>
                  <input type="text" class="form-control" id="action_link" name="action_link"
                         value="<?php echo $edit_event ? htmlspecialchars($edit_event['action_link']) : '#contact'; ?>">
                </div>
              </div>

              <div class="sticky-actions mt-4">
                <?php if ($edit_event): ?>
                  <div class="d-grid gap-2">
                    <button type="submit" name="update_event" class="btn btn-primary btn-lg"><i class="bx bx-save me-1"></i> Update Event</button>
                    <a href="events.php" class="btn btn-outline-secondary">Cancel</a>
                  </div>
                <?php else: ?>
                  <button type="submit" name="add_event" class="btn btn-primary btn-lg w-100">
                    <i class="bx bx-plus-circle me-1"></i> Add Event
                  </button>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>

        <!-- Right: Events table -->
        <div class="col-lg-7" data-aos="fade-up" data-aos-delay="150">
          <div class="card p-4">
            <h5 class="card-title mb-4">All Events</h5>

            <?php if (count($events) > 0): ?>
              <div class="table-responsive">
                <table id="eventsTable" class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Title</th>
                      <th>Date</th>
                      <th>Type</th>
                      <th>Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($events as $event): ?>
                      <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($event['title']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?><?php echo $event['event_time'] ? ' • '.htmlspecialchars(substr($event['event_time'],0,5)) : ''; ?></td>
                        <td><span class="badge badge-soft"><?php echo htmlspecialchars($event['event_type']); ?></span></td>
                        <td>
                          <?php if ($event['status'] == 'Completed'): ?>
                            <span class="badge badge-completed">Completed</span>
                          <?php else: ?>
                            <span class="badge bg-<?php 
                              echo $event['status'] == 'Upcoming' ? 'info' : 
                                   ($event['status'] == 'Ongoing' ? 'success' : 'danger'); 
                            ?>"><?php echo htmlspecialchars($event['status']); ?></span>
                          <?php endif; ?>
                        </td>
                        <td class="text-end">
                          <a href="events.php?edit=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="bx bxs-edit"></i>
                          </a>
                          <a href="events.php?delete=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" title="Delete"
                             onclick="return confirm('Are you sure you want to delete this event?')">
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
                <i class="bx bxs-calendar-x fs-1 text-muted"></i>
                <p class="text-muted mt-2">No events found. Add your first event using the form.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <!-- DataTables -->
  <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

  <script>
    AOS.init({ duration: 700, once: true });

    // Toastr defaults (same as home.php)
    toastr.options = {
      closeButton: true, progressBar: true, newestOnTop: true, preventDuplicates: true,
      positionClass: "toast-top-right", timeOut: 3500, extendedTimeOut: 1500
    };

    // Sidebar toggle (mobile) same as home.php
    const sidebar = document.getElementById('sidebar');
    const toggleSidebar = document.getElementById('toggleSidebar');
    toggleSidebar?.addEventListener('click', () => sidebar.classList.toggle('open'));

    // DataTable
    $(document).ready(function() {
      $('#eventsTable').DataTable({
        responsive: true,
        ordering:  true,
        searching: true,
        pageLength: 10,
        language: {
          search: "_INPUT_",
          searchPlaceholder: "Search events...",
          lengthMenu: "Show _MENU_ entries",
          info: "Showing _START_ to _END_ of _TOTAL_ entries",
          infoEmpty: "Showing 0 to 0 of 0 entries",
          infoFiltered: "(filtered from _MAX_ total entries)",
          paginate: { first:"First", last:"Last", next:"Next", previous:"Previous" }
        },
        columnDefs: [
          { orderable: false, targets: 4 }
        ]
      });
    });

    // Set minimum date to today for event date
    const dateEl = document.getElementById('event_date');
    if (dateEl) dateEl.min = new Date().toISOString().split('T')[0];
  </script>
</body>
</html>

<?php
session_start();
require_once '../includes/db.php';

// If already logged in, go to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
  header("Location: dashboard.php");
  exit();
}

$error_message = '';

// CSRF setup
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error_message = "Invalid session token. Please refresh and try again.";
    } elseif (!empty($email) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $stored = $user['password'];

                // Primary: hashed verification
                $ok = password_verify($password, $stored);

                // Migration: if not hashed, allow one-time plaintext and rehash
                if (!$ok && password_get_info($stored)['algo'] === 0) {
                    if (hash_equals($stored, $password)) {
                        $ok = true;
                        $newHash = password_hash($password, PASSWORD_BCRYPT);
                        $u = $pdo->prepare("UPDATE admin SET password=? WHERE id=?");
                        $u->execute([$newHash, (int)$user['id']]);
                    }
                }

                if ($ok) {
                    if (password_needs_rehash($stored, PASSWORD_BCRYPT)) {
                        $newHash = password_hash($password, PASSWORD_BCRYPT);
                        $u = $pdo->prepare("UPDATE admin SET password=? WHERE id=?");
                        $u->execute([$newHash, (int)$user['id']]);
                    }

                    $_SESSION['admin_id']    = (int)$user['id'];
                    $_SESSION['admin_name']  = $user['name'];
                    $_SESSION['admin_email'] = $user['email'];
                    $_SESSION['logged_in']   = true;

                    header("Location: dashboard.php?login=success");
                    exit();
                }
            }

            $error_message = "Invalid email or password.";
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login</title>

  <!-- Vendor CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
  <!-- PNG fallbacks (optional) -->
  <link rel="icon" type="image/png" sizes="32x32" href="../images/book-heart.png" >
  <link rel="icon" type="image/png" sizes="16x16" href="../images/book-heart.png" >

  <style>
    :root{
      /* === Unified tokens (copied from index.php) === */
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

    html, body { height:100%; scroll-behavior:smooth; }
    body{
      display:flex; align-items:center; justify-content:center;
      background: linear-gradient(135deg, var(--soft) 0%, var(--soft-dark) 100%); 
      color:var(--ink); font-family:'Inter',sans-serif;
      padding:1rem;
    }

    /* Card wrapper */
    .auth-wrap{
      width:100%; max-width:980px; border-radius:24px; overflow:hidden; 
      box-shadow:var(--shadow-large); background:#fff;
      display:grid; grid-template-columns: 1fr; position:relative;
    }
    @media(min-width: 992px){
      .auth-wrap{ grid-template-columns: 1.2fr .8fr; }
    }

    /* Left panel (form) */
    .auth-body{ padding:2rem 1.5rem; position:relative; }
    @media(min-width: 768px){ .auth-body{ padding:3rem; } }

    .brand{
      display:flex; align-items:center; gap:.65rem; margin-bottom:1.25rem;
      font-family:'Playfair Display',serif; font-weight:700;
    }
    .brand .logo{
      width:56px; height:56px; border-radius:16px; background:#fff; display:grid; place-items:center;
      border:1px solid rgba(0,0,0,.06); box-shadow:var(--shadow-soft);
    }

    .form-control{
      border:2px solid #E2E8F0; border-radius:12px; padding:.95rem 1.1rem; background:#FAFAFA; transition:.2s;
    }
    .form-control:focus{ border-color:var(--brand); box-shadow:0 0 0 .2rem rgba(60,145,230,.15); background:#fff; }
    .form-label{ font-weight:600; color:var(--ink); }

    /* Button gradient to match index.php */
    .btn-gradient{
      background: var(--gradient-primary);
      border: none;
      color: white;
      border-radius:12px; 
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

    /* Outline button for password toggle */
    .btn-outline-secondary{
      border:2px solid #E2E8F0; border-radius:12px;
      background:#fff; color:#2D3748; transition:.25s;
    }
    .btn-outline-secondary:hover{
      background: var(--gradient-hover); border-color: transparent; color:#fff;
      transform: translateY(-1px);
    }

    .muted{ color:#64748B; }

    /* Right panel (hero-ish) */
    .auth-aside{
      position:relative; 
      background: var(--gradient-hero); 
      color:#fff;
      display:none; align-items:center; justify-content:center; padding:2rem;
      overflow:hidden;
    }
    @media(min-width: 992px){
      .auth-aside{ display:flex; }
    }
    .aside-inner{ text-align:center; z-index:2; }
    .aside-inner i{ text-shadow: 0 10px 30px rgba(0,0,0,.25); }
    .aside-shape{
      position:absolute; inset:0; 
      background:
        radial-gradient(800px 500px at 30% 20%, rgba(60,145,230,.25), transparent 60%),
        radial-gradient(700px 400px at 80% 80%, rgba(43,108,176,.28), transparent 60%);
      pointer-events:none; z-index:1;
    }

    /* Subtle “glass” alerts (consistent with index.php) */
    .glass{
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 12px;
    }

    /* Back link subtle style */
    .back-link { opacity:.8; text-decoration:none; transition:.2s; }
    .back-link:hover { opacity:1; color: var(--accent); }

    /* Small loading entrance */
    .loading{ opacity:0; transform: translateY(24px); transition: all .6s ease; }
    .loaded{ opacity:1; transform: translateY(0); }
  </style>
</head>
<body>

  <div class="auth-wrap loading">
    <!-- Left: form -->
    <div class="auth-body">
      <div class="d-flex justify-content-between align-items-start">
        <div class="brand">
          <div class="logo"><i class="bx bxs-lock fs-3 text-primary"></i></div>
          <div class="fs-4">Admin Panel</div>
        </div>

        <!-- Back to website link -->
        <a href="../index.php" class="small back-link d-inline-flex align-items-center">
          <i class="bx bx-left-arrow-alt me-1"></i> Back to Website
        </a>
      </div>

      <h1 class="h4 fw-bold mb-2 text-gradient">Welcome back</h1>
      <p class="muted mb-4">Sign in to continue to your dashboard.</p>

      <form action="login.php" method="post" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="mb-3">
          <label class="form-label" for="email">Email address</label>
          <input class="form-control" type="email" id="email" name="email" placeholder="admin@example.com" required
                 value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>

        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <div class="input-group">
            <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePass()" aria-label="Show/Hide password">
              <i class="bx bx-low-vision" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <div class="d-grid mt-4">
          <!-- Switched to btn-gradient for unified look -->
          <button class="btn btn-gradient btn-lg" type="submit">
            <i class="bx bx-log-in-circle me-1"></i> Sign In
          </button>
        </div>
      </form>

      <div class="mt-4 small text-muted">
        Forgot your password? Contact a super admin to reset.
      </div>

      <!-- Secondary back link for mobile -->
      <div class="mt-3 d-lg-none">
        <a href="../index.php" class="btn btn-link p-0 text-decoration-none">
          <i class="bx bx-left-arrow-alt me-1"></i> Back to Website
        </a>
      </div>
    </div>

    <!-- Right: gradient panel with soft radial highlights -->
    <aside class="auth-aside">
      <div class="aside-inner">
        <i class="bx bxs-shield-alt-2 fs-1"></i>
      </div>
      <div class="aside-shape"></div>
    </aside>
  </div>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
  <script>
    // Toastr defaults (same feel across the site)
    if (window.toastr) {
      toastr.options = {
        closeButton: true, progressBar: true, newestOnTop: true, preventDuplicates: true,
        positionClass: "toast-top-right", timeOut: 3500, extendedTimeOut: 1500,
        showMethod: "fadeIn", hideMethod: "fadeOut"
      };
    }

    // Password toggle
    function togglePass(){
      const input = document.getElementById('password');
      const icon  = document.getElementById('eyeIcon');
      const isPwd = input.type === 'password';
      input.type = isPwd ? 'text' : 'password';
      icon.classList.toggle('bx-low-vision', !isPwd);
      icon.classList.toggle('bx-show', isPwd);
    }

    // Simple entrance animation for the card
    window.addEventListener('load', () => {
      const wrap = document.querySelector('.auth-wrap');
      if (wrap) setTimeout(()=> wrap.classList.add('loaded'), 100);
    });
  </script>

  <?php if (!empty($error_message)): ?>
  <script>
    $(function(){ if (window.toastr) toastr.error(<?php echo json_encode($error_message); ?>, "Login Failed"); });
  </script>
  <?php endif; ?>

  <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
  <script>
    $(function(){ if (window.toastr) toastr.success("You have been successfully logged out.", "Logged Out"); });
  </script>
  <?php endif; ?>
</body>
</html>

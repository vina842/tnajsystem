<?php
include("includes/config.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in? Go to the right page.
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$email = '';

if (isset($_POST['submit'])) {
    csrf_check();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    $sql = "SELECT user_id, email, password, role, status FROM users WHERE email = ? LIMIT 1";
    $result = mysqli_execute_query($conn, $sql, [$email]);

    if ($result->num_rows === 1) {
        $row = mysqli_fetch_assoc($result);

        if (password_verify($password, $row['password'])) {
            if ($row['status'] !== 'active') {
                flash('danger', 'This account is inactive. Ask the shop to turn it back on.');
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $row['user_id'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];

                if ($row['role'] === 'admin') {
                    header("Location: admin/index.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            }
        } else {
            flash('danger', 'Wrong email or password. Check them and try again.');
        }
    } else {
        flash('danger', 'Wrong email or password. Check them and try again.');
    }
}

$pageTitle = 'Log in';
include("includes/header.php");
?>
<h1>Log in</h1>

<form action="login.php" method="POST">
  <?= csrf_field() ?>

  <p>
    <label for="email">Email address</label><br>
    <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
  </p>

  <p>
    <label for="password">Password</label><br>
    <input type="password" id="password" name="password" required>
  </p>

  <p><button type="submit" name="submit">Sign in</button></p>

  <p>Not a member? <a href="register.php">Register</a></p>
</form>
<?php include("includes/footer.php"); ?>
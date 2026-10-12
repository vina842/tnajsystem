<?php
include("includes/config.php");

if (is_logged_in()) {
    redirect('index.php');
}

$f = [
    'fname' => '',
    'lname' => '',
    'email' => '',
    'phone' => '',
    'addressline' => '',
    'town' => '',
    'zipcode' => '',
];

if (is_post()) {
    csrf_check();

    foreach ($f as $key => $value) {
        $f[$key] = post_str($key, 100);
    }

    try {
        if (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) {
            throw new RuntimeException('The two passwords do not match.');
        }

        $data = $f;
        $data['password'] = (string) $_POST['password'];
        $userId = register_customer($conn, $data);

        // Log the new user in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = strtolower($f['email']);
        $_SESSION['role'] = 'user';

        flash('success', 'Welcome, ' . $f['fname'] . '! Your account is ready.');
        redirect('index.php');
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
}

$pageTitle = 'Create an account';
include("includes/header.php");
?>
<h1>Create an account</h1>

<form method="post">
    <?= csrf_field() ?>

    <p>
        <label for="fname">First name</label><br>
        <input id="fname" name="fname" value="<?= e($f['fname']) ?>" required>
    </p>

    <p>
        <label for="lname">Last name</label><br>
        <input id="lname" name="lname" value="<?= e($f['lname']) ?>" required>
    </p>

    <p>
        <label for="email">Email</label><br>
        <input type="email" id="email" name="email" value="<?= e($f['email']) ?>" required>
    </p>

    <p>
        <label for="phone">Mobile number</label><br>
        <input type="tel" id="phone" name="phone" value="<?= e($f['phone']) ?>">
    </p>

    <p>
        <label for="addressline">Address</label><br>
        <input id="addressline" name="addressline" value="<?= e($f['addressline']) ?>">
    </p>

    <p>
        <label for="town">Town or city</label><br>
        <input id="town" name="town" value="<?= e($f['town']) ?>">
    </p>

    <p>
        <label for="zipcode">Zip code</label><br>
        <input id="zipcode" name="zipcode" value="<?= e($f['zipcode']) ?>">
    </p>

    <p>
        <label for="password">Password (at least 6 characters)</label><br>
        <input type="password" id="password" name="password" minlength="6" required>
    </p>

    <p>
        <label for="confirm">Confirm password</label><br>
        <input type="password" id="confirm" name="confirm" minlength="6" required>
    </p>

    <p><button type="submit">Create account</button></p>

    <p>Already have an account? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</form>

<?php 

include("includes/footer.php"); ?>
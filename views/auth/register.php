<?php
ob_start();
?>
<section class="auth-form">
    <h2>Create Account</h2>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= \CSRF::token() ?>">
        <label>Name
            <input type="text" name="name" required>
        </label>
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Password
            <input type="password" name="password" minlength="8" required>
        </label>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="/login.php">Login</a></p>
</section>
<?php
$content = ob_get_clean();
$title = 'Register';
require view_path('layouts/base.php');

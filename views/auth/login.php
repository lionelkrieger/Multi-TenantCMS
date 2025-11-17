<?php
ob_start();
?>
<section class="auth-form">
    <h2>Login</h2>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= \CSRF::token() ?>">
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Login</button>
    </form>
    <p>Need an account? <a href="/register.php">Register</a></p>
</section>
<?php
$content = ob_get_clean();
$title = 'Login';
require view_path('layouts/base.php');
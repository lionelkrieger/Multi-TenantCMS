<?php
ob_start();
?>
<section>
    <h2>Welcome <?= sanitize($user?->name ?? $user?->email ?? 'User') ?></h2>
    <p>Your organizations:</p>
    <ul>
        <?php foreach ($organizations as $org): ?>
            <li>
                <strong><?= sanitize($org->name) ?></strong>
                <?php if (!empty($org->customDomain)): ?>
                    <small>(<?= sanitize($org->customDomain) ?>)</small>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php
$content = ob_get_clean();
$title = 'Dashboard';
require view_path('layouts/base.php');

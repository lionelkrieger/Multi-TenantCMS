<?php
ob_start();
?>
<section>
    <header>
        <h2><?= sanitize($organization->name) ?> Team</h2>
        <p>Manage who has access to this organization.</p>
    </header>

    <?php if (empty($users)): ?>
        <p>No users assigned yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= sanitize($user->name ?? 'Unnamed') ?></td>
                        <td><?= sanitize($user->email) ?></td>
                        <td><?= sanitize($user->userType) ?></td>
                        <td><?= sanitize($user->status) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
$title = sprintf('%s Users', $organization->name);
require view_path('layouts/base.php');

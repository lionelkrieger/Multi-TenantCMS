<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= sanitize($title ?? 'Multi-Tenant CMS') ?></title>
        <link rel="stylesheet" href="/assets/css/app.css">
    </head>
    <body>
        <header>
            <h1><?= sanitize($title ?? 'Multi-Tenant CMS') ?></h1>
            <?php if (\Auth::check()): ?>
                <nav>
                    <a href="/index.php">Dashboard</a>
                    <?php if (\Auth::userType() === 'master_admin'): ?>
                        <a href="/admin/dashboard.php">Master Admin</a>
                        <a href="/admin/organizations.php">Organizations</a>
                        <a href="/admin/users.php">Users</a>
                    <?php endif; ?>
                    <a href="/org/dashboard.php">Org Dashboard</a>
                    <a href="/org/users.php">Org Users</a>
                    <a href="/org/settings.php">Org Settings</a>
                    <a href="/logout.php">Logout</a>
                </nav>
            <?php endif; ?>
        </header>
        <main>
            <?= $content ?? '' ?>
        </main>
    </body>
</html>

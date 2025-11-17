<?php
ob_start();
?>
<section class="card">
    <header>
        <p class="eyebrow">Next steps with <?= sanitize($organization->name) ?></p>
        <h2>Actions for <?= sanitize($property->name) ?></h2>
        <p>This space is intentionally reserved for custom lead or booking flows powered by extensions.</p>
    </header>

    <p class="muted">
        No interactive form is available yet. Install or build a business-specific extension to guide visitors through inquiries,
        reservations, or waitlists without touching core files.
    </p>
</section>
<?php
$content = ob_get_clean();
$title = sprintf('Action | %s', $property->name);
require view_path('layouts/public.php');

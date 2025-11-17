<?php
ob_start();
?>
<section class="card">
    <header>
        <p class="eyebrow">Secure your spot with <?= sanitize($organization->name) ?></p>
        <h2>Checkout placeholder for <?= sanitize($property->name) ?></h2>
        <p>This stage is where reservation, payment, or onboarding experiences appear once an extension hooks in.</p>
    </header>

    <p class="muted">Core keeps this lightweight so you can drop in any booking or commerce module without editing the platform.</p>
</section>
<?php
$content = ob_get_clean();
$title = sprintf('Checkout | %s', $property->name);
require view_path('layouts/public.php');

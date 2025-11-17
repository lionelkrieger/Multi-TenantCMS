<?php
ob_start();
?>
<section class="card empty-state">
    <h2>Property not available</h2>
    <p><?= sanitize($organization->name) ?> may have archived this listing or changed the link. Explore current offerings below.</p>
    <p>
        <a class="cta-primary" href="/search.php?<?= sanitize(http_build_query(['org' => $organization->id])) ?>">Return to listings</a>
    </p>
</section>
<?php
$content = ob_get_clean();
$title = 'Property unavailable';
require view_path('layouts/public.php');

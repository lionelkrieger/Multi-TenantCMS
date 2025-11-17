<?php
ob_start();
?>
<section class="card">
    <header>
        <h2>Confirmation placeholder</h2>
        <p>Extensions can render receipts, onboarding steps, or download links tailored to <?= sanitize($organization->name) ?>.</p>
    </header>

    <p>
        Return to the
        <a href="/search.php?<?= sanitize(http_build_query(['org' => $organization->id])) ?>">property list</a>
        or follow the next steps provided by your custom workflow.
    </p>
</section>
<?php
$content = ob_get_clean();
$title = 'Confirmation';
require view_path('layouts/public.php');

<?php
ob_start();
?>
<section class="card property-detail">
    <header>
        <p class="eyebrow">Presented by <?= sanitize($organization->name) ?></p>
        <h2><?= sanitize($property->name) ?></h2>
    </header>

    <div class="property-meta">
        <p>
            <strong>Address:</strong>
            <?php if (!empty($property->address)): ?>
                <?= sanitize($property->address) ?>
            <?php else: ?>
                <span class="muted">Details shared after you reach out.</span>
            <?php endif; ?>
        </p>
    </div>

    <article>
        <h3>About this property</h3>
        <?php if (!empty($property->description)): ?>
            <p><?= nl2br(sanitize($property->description)) ?></p>
        <?php else: ?>
            <p class="muted"><?= sanitize($organization->name) ?> is preparing a full description. In the meantime, use the action button below to request more info.</p>
        <?php endif; ?>
    </article>

    <div class="cta">
        <a class="cta-primary" href="/org/property/actions.php?<?= sanitize(http_build_query(['org' => $organization->id, 'property' => $property->id])) ?>">
            Continue with <?= sanitize($organization->name) ?>
        </a>
        <p class="muted">You’ll be guided through the next steps defined by this organization.</p>
    </div>
</section>
<?php
$content = ob_get_clean();
$title = sprintf('%s | %s', $property->name, $organization->name);
require view_path('layouts/public.php');

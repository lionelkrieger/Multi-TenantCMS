<?php
ob_start();
?>
<?php $baseQuery = ['org' => $organization->id]; ?>
<?php if (!empty($term)): ?>
    <?php $baseQuery['q'] = $term; ?>
<?php endif; ?>

<section class="public-search">
    <header class="card hero">
        <div>
            <p class="eyebrow">Discover <?= sanitize($organization->name) ?></p>
            <h2>Browse available properties</h2>
            <p class="muted">Use the filters below to find the perfect fit. Listings update as soon as your team publishes them.</p>
        </div>
    </header>

    <form class="card search-form" method="get" action="/search.php">
        <input type="hidden" name="org" value="<?= sanitize($organization->id) ?>">
        <label class="sr-only" for="property-search">Search properties</label>
        <div class="input-group">
            <input
                id="property-search"
                type="text"
                name="q"
                value="<?= sanitize($term ?? '') ?>"
                placeholder="Search by name or address"
            >
            <button type="submit" class="cta-primary">Search</button>
        </div>
    </form>

    <?php if (empty($properties)): ?>
        <div class="card empty-state">
            <?php if (!empty($term)): ?>
                <p>No properties matched "<?= sanitize($term) ?>". Try broadening your search or clearing the filters.</p>
            <?php else: ?>
                <p><?= sanitize($organization->name) ?> hasn't published any properties yet. Please check back soon.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="property-grid">
            <?php foreach ($properties as $property): ?>
                <li class="card">
                    <h3>
                        <a href="/org/property/view.php?<?= sanitize(http_build_query(array_merge($baseQuery, ['property' => $property->id]))) ?>">
                            <?= sanitize($property->name) ?>
                        </a>
                    </h3>
                    <?php if (!empty($property->address)): ?>
                        <p class="muted"><?= sanitize($property->address) ?></p>
                    <?php else: ?>
                        <p class="muted">Address shared during consultation.</p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (($page ?? 1) > 1 || !empty($hasMore)): ?>
            <nav class="pagination">
                <?php if (($page ?? 1) > 1): ?>
                    <?php $prevQuery = array_merge($baseQuery, ['page' => ($page ?? 1) - 1]); ?>
                    <a href="/search.php?<?= sanitize(http_build_query($prevQuery)) ?>">Previous</a>
                <?php endif; ?>
                <span>Page <?= sanitize((string) ($page ?? 1)) ?></span>
                <?php if (!empty($hasMore)): ?>
                    <?php $nextQuery = array_merge($baseQuery, ['page' => ($page ?? 1) + 1]); ?>
                    <a href="/search.php?<?= sanitize(http_build_query($nextQuery)) ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
$title = sprintf('%s Property Search', $organization->name);
require view_path('layouts/public.php');

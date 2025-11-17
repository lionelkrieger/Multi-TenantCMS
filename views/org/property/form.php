<?php
ob_start();

$values = $form['values'] ?? ['name' => '', 'address' => '', 'description' => ''];
$errors = $form['errors'] ?? [];
$isEdit = ($mode ?? 'create') === 'edit';
$titleText = $isEdit ? 'Edit property' : 'Create property';
$submitText = $submitLabel ?? ($isEdit ? 'Save changes' : 'Create property');
?>
<section class="org-property-form">
    <header class="page-header">
        <div>
            <p class="page-eyebrow">Organization</p>
            <h2><?= sanitize($titleText) ?></h2>
            <p class="muted">Manage listings for <?= sanitize($organization->name) ?>.</p>
        </div>
        <div class="cta-group">
            <a class="btn btn-link" href="/org/properties.php?id=<?= urlencode($organization->id) ?>">Back to properties</a>
        </div>
    </header>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= sanitize($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= sanitize($formAction ?? '') ?>" class="card form-grid">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken ?? '') ?>">

        <div class="form-group<?= isset($errors['name']) ? ' has-error' : '' ?>">
            <label for="property-name">Property name</label>
            <input
                id="property-name"
                name="name"
                type="text"
                maxlength="255"
                value="<?= sanitize($values['name'] ?? '') ?>"
                required
            >
            <?php if (isset($errors['name'])): ?>
                <p class="form-error"><?= sanitize($errors['name']) ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="property-address">Address</label>
            <input
                id="property-address"
                name="address"
                type="text"
                maxlength="500"
                value="<?= sanitize($values['address'] ?? '') ?>"
                placeholder="123 Main Street, Springfield"
            >
        </div>

        <div class="form-group">
            <label for="property-description">Description</label>
            <textarea
                id="property-description"
                name="description"
                rows="6"
                maxlength="4000"
                placeholder="Share highlights, amenities, or instructions"><?= sanitize($values['description'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= sanitize($submitText) ?></button>
            <a class="btn btn-secondary" href="/org/properties.php?id=<?= urlencode($organization->id) ?>">Cancel</a>
        </div>
    </form>
</section>
<?php
$content = ob_get_clean();
$title = $titleText;
require view_path('layouts/base.php');

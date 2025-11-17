<?php
ob_start();

$warnings = $warnings ?? [];
$recentProperties = $recentProperties ?? [];
$recentInvites = $recentInvites ?? [];
$recentTeamMembers = $recentTeamMembers ?? [];

$customDomain = $organization->customDomain ? sanitize($organization->customDomain) : 'Not configured';
$domainStatus = $organization->domainVerified ? 'Verified' : 'Pending';
$sslStatus = sanitize($organization->sslCertificateStatus);
$propertyCount = $stats['property_count'] ?? 0;
$activeUsers = $stats['active_user_count'] ?? 0;
$pendingInvites = $stats['pending_invite_count'] ?? 0;
$orgQuery = '?id=' . urlencode($organization->id);
?>
<section class="org-dashboard">
    <header class="dashboard-header">
        <div>
            <p class="dashboard-eyebrow">Organization</p>
            <h2><?= sanitize($organization->name) ?> Dashboard</h2>
            <p class="muted">Created <?= sanitize(date('M j, Y', strtotime($organization->createdAt))) ?> · Last updated <?= sanitize(date('M j, Y', strtotime($organization->updatedAt))) ?></p>
        </div>
        <div class="cta-group">
            <a href="/org/properties.php<?= $orgQuery ?>" class="btn btn-primary">Manage Properties</a>
            <a href="/org/settings.php<?= $orgQuery ?>" class="btn btn-secondary">Branding & Settings</a>
        </div>
    </header>

    <?php if (!empty($warnings)) : ?>
        <div class="alert alert-warning">
            <h3>Attention needed</h3>
            <ul>
                <?php foreach ($warnings as $warning) : ?>
                    <li><?= sanitize($warning) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <p class="stat-label">Active Properties</p>
            <p class="stat-value"><?= sanitize((string) $propertyCount) ?></p>
            <p class="muted">Add or update properties to publish public listings.</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Team Members</p>
            <p class="stat-value"><?= sanitize((string) $activeUsers) ?></p>
            <p class="muted">Active users assigned to this organization.</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Pending Invites</p>
            <p class="stat-value"><?= sanitize((string) $pendingInvites) ?></p>
            <p class="muted">
                <?php if ($pendingInvites > 0): ?>Send reminders or revoke invites from the Org Users page.<?php else: ?>No outstanding invitations.<?php endif; ?>
            </p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Custom Domain</p>
            <p class="stat-value"><?= $customDomain ?></p>
            <p class="muted">Status: <?= sanitize($domainStatus) ?> · SSL: <?= $sslStatus ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Brand Palette</p>
            <div class="brand-swatches">
                <span title="Primary" style="background-color: <?= sanitize($organization->primaryColor) ?>"></span>
                <span title="Secondary" style="background-color: <?= sanitize($organization->secondaryColor) ?>"></span>
                <span title="Accent" style="background-color: <?= sanitize($organization->accentColor) ?>"></span>
            </div>
            <p class="muted">Font: <?= sanitize($organization->fontFamily) ?></p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h3>Recent properties</h3>
            <a href="/org/properties.php<?= $orgQuery ?>" class="link">View all</a>
        </div>
        <?php if (empty($recentProperties)) : ?>
            <p class="muted">No properties yet. Use the button above to create your first property.</p>
        <?php else : ?>
            <ul class="property-list">
                <?php foreach ($recentProperties as $property) : ?>
                    <li>
                        <div>
                            <strong><?= sanitize($property->name) ?></strong>
                            <p class="muted">Added <?= sanitize(date('M j, Y', strtotime($property->createdAt))) ?></p>
                            <?php if ($property->address) : ?>
                                <p><?= sanitize($property->address) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="list-actions">
                            <a href="/org/property/edit.php?id=<?= urlencode($organization->id) ?>&property=<?= urlencode($property->id) ?>" class="btn btn-link">Edit</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h3>Pending invitations</h3>
            <a href="/org/users.php<?= $orgQuery ?>" class="link">Manage users</a>
        </div>
        <?php if ($recentInvites === []) : ?>
            <p class="muted">No outstanding invites for this organization.</p>
        <?php else : ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Expires</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentInvites as $invite) : ?>
                            <tr>
                                <td><?= sanitize($invite->email) ?></td>
                                <td><?= sanitize(ucwords(str_replace('_', ' ', $invite->inviteType))) ?></td>
                                <td>
                                    <?php if ($invite->expiresAt) : ?>
                                        <?= sanitize(date('M j, Y', strtotime($invite->expiresAt))) ?>
                                    <?php else : ?>
                                        <span class="muted">No expiry</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h3>Recent team members</h3>
            <a href="/org/users.php<?= $orgQuery ?>" class="link">View directory</a>
        </div>
        <?php if ($recentTeamMembers === []) : ?>
            <p class="muted">No team members yet. Invite admins or employees to collaborate.</p>
        <?php else : ?>
            <ul class="property-list">
                <?php foreach ($recentTeamMembers as $member) : ?>
                    <li>
                        <div>
                            <strong><?= sanitize($member->name ?? $member->email) ?></strong>
                            <p class="muted">
                                <?= sanitize($member->email) ?> · <?= sanitize(ucwords(str_replace('_', ' ', $member->userType))) ?>
                            </p>
                        </div>
                        <div class="muted">Joined <?= sanitize(date('M j, Y', strtotime($member->createdAt))) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</section>
<?php
$content = ob_get_clean();
$title = 'Organization Dashboard';
require view_path('layouts/base.php');

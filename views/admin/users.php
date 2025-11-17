<?php
/** @var array<int, \App\Models\User> $users */
/** @var array<string, \App\Models\Organization> $organizationMap */
/** @var array<int, \App\Models\Organization> $organizations */
/** @var array<int, \App\Models\UserInvite> $pendingInvites */
/** @var array{total:int,page:int,limit:int,total_pages:int,filters:array<string,mixed>} $pagination */
/** @var array{total:int,page:int,limit:int,total_pages:int,search:?string} $invitePagination */
/** @var array{values: array<string,string>, errors: array<string,string>} $inviteForm */
/** @var array{success:?string} $flash */
/** @var string $csrfToken */

ob_start();
?>
<section class="admin-users">
    <header class="page-header">
        <h2>Platform Users</h2>
        <p>Review all users across organizations and manage invitations.</p>
    </header>

    <?php if (!empty($flash['success'])): ?>
        <div class="alert success"><?= sanitize($flash['success']) ?></div>
    <?php endif; ?>

    <section class="panel">
        <form method="get" class="filter-grid">
            <div>
                <label for="filter_search">Search</label>
                <input type="text" id="filter_search" name="q" value="<?= sanitize($pagination['filters']['search'] ?? '') ?>" placeholder="Name or email">
            </div>
            <div>
                <label for="filter_role">Role</label>
                <select id="filter_role" name="role">
                    <option value="">All Roles</option>
                    <?php foreach (['master_admin' => 'Master Admin', 'org_admin' => 'Org Admin', 'employee' => 'Employee', 'user' => 'User'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= (($pagination['filters']['user_type'] ?? '') === $value) ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_status">Status</label>
                <select id="filter_status" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (['active' => 'Active', 'unassigned' => 'Unassigned', 'deleted' => 'Deleted'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= (($pagination['filters']['status'] ?? '') === $value) ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_org">Organization</label>
                <select id="filter_org" name="org_id">
                    <option value="">All Organizations</option>
                    <?php foreach ($organizations as $organization): ?>
                        <option value="<?= sanitize($organization->id) ?>" <?= (($pagination['filters']['organization_id'] ?? '') === $organization->id) ? 'selected' : '' ?>><?= sanitize($organization->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit">Apply Filters</button>
                <a href="/admin/users.php">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Organization</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users === []): ?>
                        <tr>
                            <td colspan="6">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php $org = $user->organizationId ? ($organizationMap[$user->organizationId] ?? null) : null; ?>
                            <tr>
                                <td><?= sanitize($user->name ?? '—') ?></td>
                                <td><?= sanitize($user->email) ?></td>
                                <td><?= sanitize(ucwords(str_replace('_', ' ', $user->userType))) ?></td>
                                <td><span class="badge status-<?= sanitize($user->status) ?>"><?= sanitize(ucwords($user->status)) ?></span></td>
                                <td>
                                    <?php if ($org): ?>
                                        <?= sanitize($org->name) ?>
                                    <?php else: ?>
                                        <span class="muted">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize(date('M j, Y', strtotime($user->createdAt))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <nav class="pagination">
                <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                    <?php $queryString = http_build_query(array_merge($pagination['filters'], ['page' => $p])); ?>
                    <a href="?<?= sanitize($queryString) ?>" class="<?= $p === $pagination['page'] ? 'active' : '' ?>">Page <?= $p ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>

    <section class="panel" id="pending-invites">
        <header class="panel-header">
            <h3>Pending Invitations</h3>
            <form method="get" class="inline-form">
                <input type="hidden" name="q" value="<?= sanitize($pagination['filters']['search'] ?? '') ?>">
                <input type="hidden" name="role" value="<?= sanitize($pagination['filters']['user_type'] ?? '') ?>">
                <input type="hidden" name="status" value="<?= sanitize($pagination['filters']['status'] ?? '') ?>">
                <input type="hidden" name="org_id" value="<?= sanitize($pagination['filters']['organization_id'] ?? '') ?>">
                <input type="hidden" name="page" value="<?= sanitize((string) $pagination['page']) ?>">
                <label for="invite_search" class="sr-only">Search invites</label>
                <input type="text" id="invite_search" name="inv_q" value="<?= sanitize($invitePagination['search'] ?? '') ?>" placeholder="Search pending invites">
                <button type="submit">Search</button>
                <a href="/admin/users.php#pending-invites" class="link">Reset</a>
            </form>
        </header>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Organization</th>
                        <th>Expires</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pendingInvites === []): ?>
                        <tr>
                            <td colspan="5">No pending invites.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pendingInvites as $invite): ?>
                            <?php $org = $invite->organizationId ? ($organizationMap[$invite->organizationId] ?? null) : null; ?>
                            <tr>
                                <td><?= sanitize($invite->email) ?></td>
                                <td><?= sanitize(ucwords(str_replace('_', ' ', $invite->inviteType))) ?></td>
                                <td>
                                    <?php if ($org): ?>
                                        <?= sanitize($org->name) ?>
                                    <?php else: ?>
                                        <span class="muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($invite->expiresAt): ?>
                                        <?= sanitize(date('M j, Y', strtotime($invite->expiresAt))) ?>
                                    <?php else: ?>
                                        <span class="muted">No expiry</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                                        <input type="hidden" name="action" value="revoke">
                                        <input type="hidden" name="invite_id" value="<?= sanitize($invite->id) ?>">
                                        <button type="submit" class="link">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h3>Send Invitation</h3>
        <?php if (!empty($inviteForm['errors']['general'])): ?>
            <div class="alert error"><?= sanitize($inviteForm['errors']['general']) ?></div>
        <?php endif; ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
            <input type="hidden" name="action" value="invite">
            <div>
                <label for="invite_email">Email</label>
                <input type="email" id="invite_email" name="email" value="<?= sanitize($inviteForm['values']['email'] ?? '') ?>" required>
                <?php if (!empty($inviteForm['errors']['email'])): ?>
                    <small class="error-text"><?= sanitize($inviteForm['errors']['email']) ?></small>
                <?php endif; ?>
            </div>
            <div>
                <label for="invite_type">Role</label>
                <select id="invite_type" name="invite_type">
                    <?php foreach (['org_admin' => 'Org Admin', 'employee' => 'Employee', 'user' => 'User'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= (($inviteForm['values']['invite_type'] ?? '') === $value) ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($inviteForm['errors']['invite_type'])): ?>
                    <small class="error-text"><?= sanitize($inviteForm['errors']['invite_type']) ?></small>
                <?php endif; ?>
            </div>
            <div>
                <label for="invite_org">Organization (required for org/admin)</label>
                <select id="invite_org" name="organization_id">
                    <option value="">Select organization</option>
                    <?php foreach ($organizations as $organization): ?>
                        <option value="<?= sanitize($organization->id) ?>" <?= (($inviteForm['values']['organization_id'] ?? '') === $organization->id) ? 'selected' : '' ?>><?= sanitize($organization->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($inviteForm['errors']['organization_id'])): ?>
                    <small class="error-text"><?= sanitize($inviteForm['errors']['organization_id']) ?></small>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit">Send Invite</button>
            </div>
        </form>
    </section>
</section>
<?php
$content = ob_get_clean();
$title = 'Users & Invites';
require view_path('layouts/base.php');

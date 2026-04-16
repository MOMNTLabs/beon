        <section class="users-wrap panel" id="users" data-dashboard-view-panel="users" hidden>
            <div class="panel-header board-header users-board-header">
                <div>
                    <h2>Usuarios</h2>
                    <p>Gerencie membros, permissoes e status do workspace.</p>
                </div>
            </div>

            <div class="workspace-settings-grid users-settings-grid">
                <?php if (!empty($canManageWorkspace)): ?>
                    <section class="workspace-settings-card">
                        <h3>Dados do workspace</h3>
                        <form method="post" class="workspace-settings-form workspace-profile-form" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="workspace_update_profile">
                            <div class="workspace-profile-photo-row">
                                <?= renderWorkspaceAvatar($currentWorkspace, 'avatar workspace-profile-avatar') ?>
                                <label class="workspace-profile-photo-field">
                                    <span>Foto do workspace</span>
                                    <input
                                        type="file"
                                        name="avatar"
                                        accept="image/png,image/jpeg,image/webp,image/gif"
                                    >
                                </label>
                            </div>
                            <label>
                                <span>Nome do workspace</span>
                                <input
                                    type="text"
                                    name="workspace_name"
                                    maxlength="80"
                                    value="<?= e((string) ($currentWorkspace['name'] ?? 'Workspace')) ?>"
                                    required
                                >
                            </label>
                            <button type="submit" class="btn btn-mini">Salvar nome</button>
                        </form>
                    </section>
                <?php endif; ?>

                <section class="workspace-settings-card workspace-settings-users-card<?= empty($canManageWorkspace) ? ' is-full' : '' ?>">
                    <h3>Usuarios do workspace</h3>
                    <?php if (!empty($canManageWorkspace)): ?>
                        <form method="post" class="workspace-settings-form workspace-settings-member-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="workspace_add_member">
                            <label>
                                <span>Adicionar usuario por e-mail</span>
                                <input type="email" name="member_email" placeholder="usuario@empresa.com" required>
                            </label>
                            <button type="submit" class="btn btn-mini">Adicionar</button>
                        </form>
                    <?php endif; ?>

                    <ul class="workspace-settings-members">
                        <?php if (!$workspaceMembers): ?>
                            <li class="workspace-settings-member-empty">Nenhum usuario cadastrado.</li>
                        <?php else: ?>
                            <?php foreach ($workspaceMembers as $workspaceMember): ?>
                                <?php
                                $memberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
                                $memberRoleLabel = workspaceRoles()[$memberRole] ?? 'Usuario';
                                $workspaceMemberId = (int) ($workspaceMember['id'] ?? 0);
                                ?>
                                <li class="workspace-settings-member-item">
                                    <?= renderUserAvatar($workspaceMember, 'avatar small') ?>
                                    <div class="workspace-settings-member-meta">
                                        <strong><?= e((string) $workspaceMember['name']) ?></strong>
                                        <span class="workspace-member-role workspace-role-<?= e((string) $memberRole) ?>"><?= e((string) $memberRoleLabel) ?></span>
                                        <span><?= e((string) $workspaceMember['email']) ?></span>
                                    </div>
                                    <?php if (!empty($canManageWorkspace) && $workspaceMemberId !== (int) $currentUser['id']): ?>
                                        <div class="workspace-settings-member-actions">
                                            <?php if ($memberRole !== 'admin'): ?>
                                                <form method="post" class="workspace-settings-member-remove">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="workspace_promote_member">
                                                    <input type="hidden" name="member_id" value="<?= e((string) $workspaceMemberId) ?>">
                                                    <button type="submit" class="btn btn-mini btn-ghost">Tornar admin</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" class="workspace-settings-member-remove">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="workspace_demote_member">
                                                    <input type="hidden" name="member_id" value="<?= e((string) $workspaceMemberId) ?>">
                                                    <button type="submit" class="btn btn-mini btn-ghost">Tornar usuario</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" class="workspace-settings-member-remove">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="workspace_remove_member">
                                                <input type="hidden" name="member_id" value="<?= e((string) $workspaceMemberId) ?>">
                                                <button type="submit" class="btn btn-mini btn-ghost">Remover</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </section>

                <?php include __DIR__ . '/workspace_statuses_card.php'; ?>
            </div>
        </section>

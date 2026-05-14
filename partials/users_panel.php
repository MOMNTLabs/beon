        <section class="users-wrap panel" id="users" data-dashboard-view-panel="users"<?= ($serverSelectedDashboardView ?? 'overview') !== 'users' ? ' hidden' : '' ?>>
            <?php
            $currentUserWorkspaceInvitations = is_array($currentUserWorkspaceInvitations ?? null)
                ? $currentUserWorkspaceInvitations
                : [];
            $workspacePendingInvitations = is_array($workspacePendingInvitations ?? null)
                ? $workspacePendingInvitations
                : [];
            $workspacePendingEmailInvitations = is_array($workspacePendingEmailInvitations ?? null)
                ? $workspacePendingEmailInvitations
                : [];
            $workspaceMembersCount = is_array($workspaceMembers) ? count($workspaceMembers) : 0;
            $workspaceMembersPreview = is_array($workspaceMembers) ? array_slice($workspaceMembers, 0, 4) : [];
            $hasMoreWorkspaceMembers = $workspaceMembersCount > count($workspaceMembersPreview);
            $workspacePendingInviteCount = count($workspacePendingInvitations) + count($workspacePendingEmailInvitations);
            $workspaceTypeLabel = !empty($isPersonalWorkspace) ? 'Pessoal' : 'Colaborativo';
            $workspaceMembersSummaryLabel = $workspaceMembersCount === 1 ? 'usuario' : 'usuarios';
            $workspacePendingSummaryLabel = $workspacePendingInviteCount === 1 ? 'convite' : 'convites';
            $workspaceMembersBadgeLabel = $workspaceMembersCount === 1 ? 'membro' : 'membros';
            ?>
            <div class="panel-header board-header users-board-header">
                <div class="users-board-header-copy">
                    <h2>Configuracoes do workspace</h2>
                </div>
                <div class="workspace-settings-summary-pills" aria-label="Resumo do workspace">
                    <span class="workspace-settings-summary-pill is-strong"><?= e($workspaceTypeLabel) ?></span>
                    <span class="workspace-settings-summary-pill">
                        <strong><?= e((string) $workspaceMembersCount) ?></strong>
                        <span><?= e($workspaceMembersSummaryLabel) ?></span>
                    </span>
                    <?php if ($workspacePendingInviteCount > 0): ?>
                        <span class="workspace-settings-summary-pill">
                            <strong><?= e((string) $workspacePendingInviteCount) ?></strong>
                            <span><?= e($workspacePendingSummaryLabel) ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="workspace-settings-grid users-settings-grid">
                <?php if (!empty($canManageWorkspace)): ?>
                    <section class="workspace-settings-card workspace-profile-card">
                        <div class="workspace-settings-card-head">
                            <div>
                                <h3>Dados do workspace</h3>
                            </div>
                        </div>

                        <form method="post" class="workspace-settings-form workspace-profile-form" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="workspace_update_profile">

                            <div class="workspace-profile-photo-row">
                                <div class="workspace-profile-avatar-stack">
                                    <?= renderWorkspaceAvatar($currentWorkspace, 'avatar workspace-profile-avatar') ?>
                                    <div class="workspace-profile-avatar-copy">
                                        <strong><?= e((string) ($currentWorkspace['name'] ?? 'Workspace')) ?></strong>
                                        <span><?= !empty($isPersonalWorkspace) ? 'Workspace pessoal' : 'Identidade do workspace' ?></span>
                                    </div>
                                </div>

                                <label class="workspace-profile-photo-field">
                                    <span><?= !empty($isPersonalWorkspace) ? 'Foto do perfil' : 'Foto do workspace' ?></span>
                                    <input
                                        type="file"
                                        name="avatar"
                                        accept="image/png,image/jpeg,image/webp,image/gif"
                                    >
                                    <small class="workspace-settings-field-hint">PNG, JPG, WebP ou GIF.</small>
                                </label>
                            </div>

                            <?php if (empty($isPersonalWorkspace)): ?>
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
                            <?php endif; ?>

                            <div class="workspace-settings-actions">
                                <button type="submit" class="btn btn-mini">
                                    <?= !empty($isPersonalWorkspace) ? 'Salvar foto' : 'Salvar perfil' ?>
                                </button>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>

                <?php if (count($currentUserWorkspaceInvitations) > 0): ?>
                    <section class="workspace-settings-card workspace-settings-invitations-card">
                        <div class="workspace-settings-card-head">
                            <div>
                                <h3>Convites para voce</h3>
                            </div>
                        </div>

                        <ul class="workspace-settings-members" aria-label="Convites de workspace pendentes">
                            <?php foreach ($currentUserWorkspaceInvitations as $workspaceInvitation): ?>
                                <?php
                                $invitationId = (int) ($workspaceInvitation['id'] ?? 0);
                                $invitedWorkspaceName = trim((string) ($workspaceInvitation['workspace_name'] ?? 'Workspace'));
                                $invitedWorkspace = [
                                    'name' => $invitedWorkspaceName,
                                    'avatar_data_url' => (string) ($workspaceInvitation['workspace_avatar_data_url'] ?? ''),
                                    'is_personal' => ((int) ($workspaceInvitation['workspace_is_personal'] ?? 0)) === 1,
                                ];
                                $invitedByName = trim((string) ($workspaceInvitation['invited_by_name'] ?? ''));
                                $invitedByEmail = trim((string) ($workspaceInvitation['invited_by_email'] ?? ''));
                                $inviterLabel = $invitedByName !== '' ? $invitedByName : $invitedByEmail;
                                ?>
                                <li class="workspace-settings-member-item">
                                    <?= renderWorkspaceAvatar($invitedWorkspace, 'avatar small') ?>
                                    <div class="workspace-settings-member-meta">
                                        <strong><?= e($invitedWorkspaceName) ?></strong>
                                        <span>Convite pendente</span>
                                        <?php if ($inviterLabel !== ''): ?>
                                            <span>Enviado por <?= e($inviterLabel) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="workspace-settings-member-actions">
                                        <form method="post" class="workspace-settings-member-remove">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="workspace_accept_invitation">
                                            <input type="hidden" name="invitation_id" value="<?= e((string) $invitationId) ?>">
                                            <button type="submit" class="btn btn-mini">Aceitar</button>
                                        </form>
                                        <form method="post" class="workspace-settings-member-remove">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="workspace_decline_invitation">
                                            <input type="hidden" name="invitation_id" value="<?= e((string) $invitationId) ?>">
                                            <button type="submit" class="btn btn-mini btn-ghost">Recusar</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <section class="workspace-settings-card workspace-settings-users-card<?= empty($canManageWorkspace) ? ' is-full' : '' ?>">
                    <?php
                    $workspaceBillingLimit = (!empty($currentWorkspaceId) && empty($isPersonalWorkspace))
                        ? workspaceBillingLimit((int) $currentWorkspaceId)
                        : [];
                    $workspaceCanInviteMembers = !empty($workspaceBillingLimit['can_invite_members']);
                    $workspaceMemberLimitReached = !empty($workspaceBillingLimit['limited'])
                        && (int) ($workspaceBillingLimit['max_users'] ?? 0) > 0
                        && (int) ($workspaceBillingLimit['member_count'] ?? 0) >= (int) ($workspaceBillingLimit['max_users'] ?? 0);
                    ?>
                    <div class="workspace-settings-card-head">
                        <div>
                            <h3>Usuarios do workspace</h3>
                        </div>
                    </div>

                    <?php if (!empty($workspaceBillingLimit['limited'])): ?>
                        <p class="workspace-settings-member-empty workspace-settings-inline-note">
                            Plano <?= e((string) ($workspaceBillingLimit['plan_name'] ?? 'atual')) ?>:
                            <?= e((string) ($workspaceBillingLimit['member_count'] ?? 0)) ?>/<?= e((string) ($workspaceBillingLimit['max_users'] ?? 0)) ?> usuarios.
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($canManageWorkspace) && empty($isPersonalWorkspace) && !empty($workspaceCanInviteMembers) && empty($workspaceMemberLimitReached)): ?>
                        <form method="post" class="workspace-settings-form workspace-settings-member-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="workspace_add_member">
                            <label>
                                <span>Convidar usuario por e-mail</span>
                                <input type="email" name="member_email" placeholder="usuario@empresa.com" required>
                            </label>
                            <div class="workspace-settings-actions">
                                <button type="submit" class="btn btn-mini">Enviar convite</button>
                            </div>
                        </form>
                    <?php elseif (!empty($canManageWorkspace) && empty($isPersonalWorkspace) && empty($workspaceCanInviteMembers)): ?>
                        <p class="workspace-settings-member-empty workspace-settings-inline-note">Faca upgrade para Team ou superior para convidar usuarios.</p>
                    <?php elseif (!empty($canManageWorkspace) && !empty($workspaceMemberLimitReached)): ?>
                        <p class="workspace-settings-member-empty workspace-settings-inline-note">Limite do plano atingido. Faca upgrade para convidar mais usuarios.</p>
                    <?php elseif (!empty($isPersonalWorkspace)): ?>
                        <p class="workspace-settings-member-empty workspace-settings-inline-note">Workspace pessoal nao permite convidar usuarios parceiros.</p>
                    <?php endif; ?>

                    <?php if (!empty($canManageWorkspace) && empty($isPersonalWorkspace) && count($workspacePendingInvitations) > 0): ?>
                        <div class="workspace-settings-subsection">
                            <div class="workspace-settings-subsection-head">
                                <span class="workspace-settings-subsection-title">Convites pendentes</span>
                                <span class="workspace-settings-subsection-copy">O usuario so entra no workspace depois de aceitar.</span>
                            </div>
                        </div>
                        <ul class="workspace-settings-members" aria-label="Convites pendentes do workspace">
                            <?php foreach ($workspacePendingInvitations as $workspaceInvitation): ?>
                                <?php
                                $invitationId = (int) ($workspaceInvitation['id'] ?? 0);
                                $invitedUserName = trim((string) ($workspaceInvitation['name'] ?? 'Usuario'));
                                $invitedUserEmail = trim((string) ($workspaceInvitation['email'] ?? ''));
                                $invitedByName = trim((string) ($workspaceInvitation['invited_by_name'] ?? ''));
                                $invitedByEmail = trim((string) ($workspaceInvitation['invited_by_email'] ?? ''));
                                $inviterLabel = $invitedByName !== '' ? $invitedByName : $invitedByEmail;
                                ?>
                                <li class="workspace-settings-member-item">
                                    <?= renderUserAvatar($workspaceInvitation, 'avatar small') ?>
                                    <div class="workspace-settings-member-meta">
                                        <strong><?= e($invitedUserName) ?></strong>
                                        <span>Pendente</span>
                                        <?php if ($invitedUserEmail !== ''): ?>
                                            <span><?= e($invitedUserEmail) ?></span>
                                        <?php endif; ?>
                                        <?php if ($inviterLabel !== ''): ?>
                                            <span>Enviado por <?= e($inviterLabel) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="workspace-settings-member-actions">
                                        <form method="post" class="workspace-settings-member-remove">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="workspace_cancel_invitation">
                                            <input type="hidden" name="invitation_id" value="<?= e((string) $invitationId) ?>">
                                            <button type="submit" class="btn btn-mini btn-ghost">Cancelar convite</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($canManageWorkspace) && empty($isPersonalWorkspace) && count($workspacePendingEmailInvitations) > 0): ?>
                        <div class="workspace-settings-subsection">
                            <div class="workspace-settings-subsection-head">
                                <span class="workspace-settings-subsection-title">Convites aguardando cadastro</span>
                                <span class="workspace-settings-subsection-copy">A pessoa precisa entrar pelo link enviado por e-mail.</span>
                            </div>
                        </div>
                        <ul class="workspace-settings-members" aria-label="Convites por e-mail pendentes do workspace">
                            <?php foreach ($workspacePendingEmailInvitations as $workspaceEmailInvitation): ?>
                                <?php
                                $emailInvitationId = (int) ($workspaceEmailInvitation['id'] ?? 0);
                                $invitedEmail = trim((string) ($workspaceEmailInvitation['invited_email'] ?? ''));
                                $invitedByName = trim((string) ($workspaceEmailInvitation['invited_by_name'] ?? ''));
                                $invitedByEmail = trim((string) ($workspaceEmailInvitation['invited_by_email'] ?? ''));
                                $inviterLabel = $invitedByName !== '' ? $invitedByName : $invitedByEmail;
                                $workspaceEmailInviteAvatar = [
                                    'name' => $invitedEmail !== '' ? $invitedEmail : 'Convidado',
                                ];
                                ?>
                                <li class="workspace-settings-member-item">
                                    <?= renderUserAvatar($workspaceEmailInviteAvatar, 'avatar small') ?>
                                    <div class="workspace-settings-member-meta">
                                        <strong><?= e($invitedEmail !== '' ? $invitedEmail : 'Convidado') ?></strong>
                                        <span>Aguardando cadastro</span>
                                        <?php if ($inviterLabel !== ''): ?>
                                            <span>Enviado por <?= e($inviterLabel) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="workspace-settings-member-actions">
                                        <form method="post" class="workspace-settings-member-remove">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="workspace_cancel_email_invitation">
                                            <input type="hidden" name="email_invitation_id" value="<?= e((string) $emailInvitationId) ?>">
                                            <button type="submit" class="btn btn-mini btn-ghost">Cancelar convite</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($workspaceMembersCount <= 0): ?>
                        <p class="workspace-settings-member-empty workspace-settings-inline-note">Nenhum usuario cadastrado.</p>
                    <?php else: ?>
                        <div class="workspace-users-preview-shell">
                            <div class="workspace-users-preview-head">
                                <span class="workspace-users-preview-label">Membros ativos</span>
                                <?php if ($hasMoreWorkspaceMembers): ?>
                                    <button type="button" class="btn btn-mini btn-ghost workspace-members-view-all" data-open-workspace-users-modal>
                                        Ver todos usuarios
                                    </button>
                                <?php endif; ?>
                            </div>
                            <ul class="workspace-members-preview-list" aria-label="Usuarios visiveis no workspace">
                                <?php foreach ($workspaceMembersPreview as $workspaceMember): ?>
                                    <?php
                                    $workspaceMemberName = trim((string) ($workspaceMember['name'] ?? 'Usuario'));
                                    $workspaceMemberEmail = trim((string) ($workspaceMember['email'] ?? ''));
                                    $workspaceMemberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
                                    $workspaceMemberRoleLabel = workspaceRoles()[$workspaceMemberRole] ?? 'Usuario';
                                    ?>
                                    <li class="workspace-members-preview-item">
                                        <?= renderUserAvatar($workspaceMember, 'avatar small') ?>
                                        <div class="workspace-members-preview-meta">
                                            <div class="workspace-members-preview-title-row">
                                                <strong><?= e($workspaceMemberName) ?></strong>
                                                <span class="workspace-member-role workspace-role-<?= e((string) $workspaceMemberRole) ?>">
                                                    <?= e((string) $workspaceMemberRoleLabel) ?>
                                                </span>
                                            </div>
                                            <?php if ($workspaceMemberEmail !== ''): ?>
                                                <span><?= e($workspaceMemberEmail) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if (!empty($hasMoreWorkspaceMembers)): ?>
                    <div class="modal-backdrop" data-workspace-users-modal hidden>
                        <div class="modal-scrim" data-close-workspace-users-modal></div>
                        <section class="modal-card workspace-users-modal-card" role="dialog" aria-modal="true" aria-labelledby="workspace-users-modal-title">
                            <header class="modal-head">
                                <h2 id="workspace-users-modal-title">Todos os usuarios do workspace</h2>
                                <button type="button" class="modal-close-button" data-close-workspace-users-modal aria-label="Fechar modal">
                                    <span aria-hidden="true">&#10005;</span>
                                </button>
                            </header>
                            <div class="workspace-users-modal-body">
                                <ul class="workspace-settings-members workspace-users-modal-members">
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
                                            <?php if (!empty($canManageWorkspace) && empty($isPersonalWorkspace) && $workspaceMemberId !== (int) $currentUser['id']): ?>
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
                                </ul>
                            </div>
                        </section>
                    </div>
                <?php endif; ?>

                <?php include __DIR__ . '/workspace_sidebar_tools_card.php'; ?>
                <?php include __DIR__ . '/workspace_statuses_card.php'; ?>
            </div>
        </section>

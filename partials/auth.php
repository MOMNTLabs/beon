<main class="auth-screen" id="auth-panels" data-auth-initial-panel="<?= e((string) ($authInitialPanel ?? 'login')) ?>">
    <section class="auth-card" aria-labelledby="auth-title">
        <div class="auth-card-glow" aria-hidden="true"></div>

        <div class="auth-brand-block">
            <img
                src="assets/Bexon - Logo Horizontal.png?v=1"
                alt="<?= e(APP_NAME) ?>"
                class="auth-brand-lockup"
                width="196"
                height="66"
            >
        </div>

        <div class="auth-card-head">
            <h1 id="auth-title">Acesso ao workspace</h1>
        </div>

        <?php if (!empty($workspaceInviteRequest)): ?>
            <p class="auth-switch-line">
                Convite para <?= e((string) ($workspaceInviteRequest['workspace_name'] ?? 'um workspace')) ?> com o e-mail
                <?= e((string) ($workspaceInviteRequest['invited_email'] ?? '')) ?>.
            </p>
        <?php endif; ?>

        <?php if (!empty($flashes)): ?>
            <div class="flash-stack auth-card-flash-stack" aria-live="assertive">
                <?php foreach ($flashes as $flash): ?>
                    <div
                        class="flash auth-card-flash flash-<?= e((string) ($flash['type'] ?? 'info')) ?>"
                        data-flash
                        data-flash-persist
                        role="alert"
                    >
                        <span><?= e((string) ($flash['message'] ?? '')) ?></span>
                        <button type="button" class="flash-close" data-flash-close aria-label="Fechar">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section
            class="auth-pane is-active"
            id="auth-panel-login"
            data-auth-panel="login"
        >
            <form method="post" class="form-stack auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="next" value="<?= e((string) ($authRedirectPath ?? 'index.php')) ?>">

                <label>
                    <span>E-mail</span>
                    <input type="email" name="email" placeholder="você@empresa.com" autocomplete="email" required>
                </label>

                <label>
                    <span>Senha</span>
                    <span class="auth-password-field">
                        <input type="password" name="password" placeholder="Sua senha" autocomplete="current-password" required>
                        <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Mostrar senha" aria-pressed="false">
                            <svg class="auth-password-icon auth-password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="auth-password-icon auth-password-icon-hide" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m3 3 18 18"></path>
                                <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2"></path>
                                <path d="M7.4 7.4C4.3 9 2.5 12 2.5 12s3.5 6 9.5 6c1.8 0 3.3-.5 4.6-1.2"></path>
                                <path d="M14 6.2C18.8 7.1 21.5 12 21.5 12a16.3 16.3 0 0 1-2.1 2.7"></path>
                            </svg>
                        </button>
                    </span>
                </label>

                <button class="btn btn-pill btn-accent btn-block" type="submit">Entrar</button>
            </form>

            <p class="auth-switch-line">
                <button type="button" class="auth-inline-link" data-auth-target="forgot-password">Esqueci minha senha</button>
            </p>

            <p class="auth-switch-line">
                <?php if (!empty($authAllowsDirectRegister)): ?>
                    Não tem conta?
                    <button type="button" class="auth-inline-link" data-auth-target="register">Criar conta</button>
                <?php elseif (!empty($workspaceInviteRequest)): ?>
                    Entre com o mesmo e-mail que recebeu o convite.
                <?php else: ?>
                    Ainda n&atilde;o tem conta?
                    <a href="<?= e(appPath('home#planos')) ?>" class="auth-inline-link">Conhe&ccedil;a os planos</a>
                <?php endif; ?>
            </p>
        </section>

        <?php if (!empty($authAllowsDirectRegister)): ?>
            <section
                class="auth-pane"
                id="auth-panel-register"
                data-auth-panel="register"
                hidden
            >
            <form method="post" class="form-stack auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="next" value="<?= e((string) ($authRedirectPath ?? 'index.php')) ?>">

                <label>
                    <span>Nome</span>
                    <input type="text" name="name" placeholder="Nome completo" autocomplete="name" required>
                </label>

                <label>
                    <span>E-mail</span>
                    <input type="email" name="email" placeholder="você@empresa.com" autocomplete="email" required>
                </label>

                <label>
                    <span>Senha</span>
                    <span class="auth-password-field">
                        <input type="password" name="password" placeholder="Mínimo 6 caracteres" minlength="6" autocomplete="new-password" required>
                        <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Mostrar senha" aria-pressed="false">
                            <svg class="auth-password-icon auth-password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="auth-password-icon auth-password-icon-hide" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m3 3 18 18"></path>
                                <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2"></path>
                                <path d="M7.4 7.4C4.3 9 2.5 12 2.5 12s3.5 6 9.5 6c1.8 0 3.3-.5 4.6-1.2"></path>
                                <path d="M14 6.2C18.8 7.1 21.5 12 21.5 12a16.3 16.3 0 0 1-2.1 2.7"></path>
                            </svg>
                        </button>
                    </span>
                </label>

                <label>
                    <span>Confirmar senha</span>
                    <span class="auth-password-field">
                        <input type="password" name="password_confirm" placeholder="Repita a senha" minlength="6" autocomplete="new-password" required>
                        <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Mostrar senha" aria-pressed="false">
                            <svg class="auth-password-icon auth-password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="auth-password-icon auth-password-icon-hide" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m3 3 18 18"></path>
                                <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2"></path>
                                <path d="M7.4 7.4C4.3 9 2.5 12 2.5 12s3.5 6 9.5 6c1.8 0 3.3-.5 4.6-1.2"></path>
                                <path d="M14 6.2C18.8 7.1 21.5 12 21.5 12a16.3 16.3 0 0 1-2.1 2.7"></path>
                            </svg>
                        </button>
                    </span>
                </label>

                <label class="auth-legal-check">
                    <input type="checkbox" name="accept_terms" value="1" required>
                    <span>
                        Li e aceito os
                        <a href="<?= e(appPath('termos')) ?>" target="_blank" rel="noopener">Termos de Uso</a>
                        e a
                        <a href="<?= e(appPath('privacidade')) ?>" target="_blank" rel="noopener">Pol&iacute;tica de Privacidade</a>.
                    </span>
                </label>

                <button class="btn btn-pill btn-accent btn-block" type="submit">Criar conta</button>
            </form>

            <p class="auth-switch-line">
                Já tem conta?
                <button type="button" class="auth-inline-link" data-auth-target="login">Entrar</button>
            </p>
            </section>
        <?php endif; ?>

        <section
            class="auth-pane"
            id="auth-panel-forgot-password"
            data-auth-panel="forgot-password"
            hidden
        >
            <form method="post" class="form-stack auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="request_password_reset">

                <label>
                    <span>E-mail</span>
                    <input type="email" name="email" placeholder="você@empresa.com" autocomplete="email" required>
                </label>

                <button class="btn btn-pill btn-accent btn-block" type="submit">Enviar link de redefinição</button>
            </form>

            <p class="auth-switch-line">
                Enviaremos um link para você cadastrar uma nova senha.
            </p>

            <p class="auth-switch-line">
                Lembrou a senha?
                <button type="button" class="auth-inline-link" data-auth-target="login">Voltar ao login</button>
            </p>
        </section>

        <section
            class="auth-pane"
            id="auth-panel-reset-password"
            data-auth-panel="reset-password"
            hidden
        >
            <?php if (!empty($passwordResetRequest)): ?>
                <form method="post" class="form-stack auth-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="perform_password_reset">
                    <input type="hidden" name="selector" value="<?= e((string) ($passwordResetRequest['selector'] ?? '')) ?>">
                    <input type="hidden" name="token" value="<?= e((string) ($passwordResetRequest['token'] ?? '')) ?>">

                    <label>
                        <span>Nova senha</span>
                        <span class="auth-password-field">
                            <input type="password" name="new_password" placeholder="Mínimo 6 caracteres" minlength="6" autocomplete="new-password" required>
                            <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Mostrar senha" aria-pressed="false">
                                <svg class="auth-password-icon auth-password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="auth-password-icon auth-password-icon-hide" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="m3 3 18 18"></path>
                                    <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2"></path>
                                    <path d="M7.4 7.4C4.3 9 2.5 12 2.5 12s3.5 6 9.5 6c1.8 0 3.3-.5 4.6-1.2"></path>
                                    <path d="M14 6.2C18.8 7.1 21.5 12 21.5 12a16.3 16.3 0 0 1-2.1 2.7"></path>
                                </svg>
                            </button>
                        </span>
                    </label>

                    <label>
                        <span>Confirmar nova senha</span>
                        <span class="auth-password-field">
                            <input type="password" name="new_password_confirm" placeholder="Repita a nova senha" minlength="6" autocomplete="new-password" required>
                            <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Mostrar senha" aria-pressed="false">
                                <svg class="auth-password-icon auth-password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="auth-password-icon auth-password-icon-hide" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="m3 3 18 18"></path>
                                    <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2"></path>
                                    <path d="M7.4 7.4C4.3 9 2.5 12 2.5 12s3.5 6 9.5 6c1.8 0 3.3-.5 4.6-1.2"></path>
                                    <path d="M14 6.2C18.8 7.1 21.5 12 21.5 12a16.3 16.3 0 0 1-2.1 2.7"></path>
                                </svg>
                            </button>
                        </span>
                    </label>

                    <button class="btn btn-pill btn-accent btn-block" type="submit">Salvar nova senha</button>
                </form>

                <p class="auth-switch-line">
                    Link validado. Defina uma nova senha para entrar novamente.
                </p>
            <?php else: ?>
                <p class="auth-switch-line">
                    Este link não esta disponível mais.
                </p>

                <p class="auth-switch-line">
                    <button type="button" class="auth-inline-link" data-auth-target="forgot-password">Solicitar novo link</button>
                </p>
            <?php endif; ?>
        </section>
    </section>
</main>

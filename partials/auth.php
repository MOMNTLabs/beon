<?php
$authGoogleLoginNextPath = (string) ($authRedirectPath ?? appDefaultAfterLoginPath());
$authGoogleRegisterNextPath = (string) ($authRegisterRedirectPath ?? appPlansPath());
$authGoogleLoginUrl = googleOAuthStartUrl('login', $authGoogleLoginNextPath);
$authGoogleRegisterUrl = googleOAuthStartUrl('register', $authGoogleRegisterNextPath);
?>
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
                <input type="hidden" name="next" value="<?= e((string) ($authRedirectPath ?? appDefaultAfterLoginPath())) ?>">

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

            <div class="auth-divider" aria-hidden="true"><span>ou</span></div>

            <div class="auth-social-row">
                <a class="auth-social-button" href="<?= e($authGoogleLoginUrl) ?>" aria-label="Continuar com Google">
                    <svg class="auth-social-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"></path>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"></path>
                        <path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l3.66-2.84z"></path>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06L5.84 9.9C6.71 7.31 9.14 5.38 12 5.38z"></path>
                    </svg>
                    <span>Continuar com Google</span>
                </a>
            </div>

            <p class="auth-switch-line">
                <button type="button" class="auth-inline-link" data-auth-target="forgot-password">Esqueci minha senha</button>
            </p>

            <p class="auth-switch-line">
                <?php if (!empty($authAllowsDirectRegister)): ?>
                    Ainda n&atilde;o tem conta?
                    <button type="button" class="auth-inline-link" data-auth-target="register">Cadastrar agora</button>
                <?php elseif (!empty($workspaceInviteRequest)): ?>
                    Entre com o mesmo e-mail que recebeu o convite.
                <?php else: ?>
                    Ainda n&atilde;o tem conta?
                    <a href="<?= e(appUrl('?auth=register&next=' . urlencode(appPlansPath()) . '#register')) ?>" class="auth-inline-link">Cadastrar agora</a>
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
            <?php if (!empty($pendingGoogleRegistration)): ?>
                <div class="auth-google-register-prompt" role="status">
                    <strong>Conta Google n&atilde;o cadastrada</strong>
                    <p>
                        Deseja cadastrar
                        <span><?= e((string) ($pendingGoogleRegistration['email'] ?? '')) ?></span>
                        agora e escolher um plano?
                    </p>
                    <div class="auth-google-register-actions">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="google_register_confirm">
                            <button class="btn btn-pill btn-accent" type="submit">Cadastrar essa conta</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="google_register_cancel">
                            <button class="auth-inline-link" type="submit">Usar outro login</button>
                        </form>
                    </div>
                </div>

                <div class="auth-divider" aria-hidden="true"><span>ou</span></div>
            <?php endif; ?>

            <form method="post" class="form-stack auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="next" value="<?= e((string) ($authRegisterRedirectPath ?? appPlansPath())) ?>">

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
                        <a href="<?= e(siteUrl('termos')) ?>" target="_blank" rel="noopener">Termos de Uso</a>
                        e a
                        <a href="<?= e(siteUrl('privacidade')) ?>" target="_blank" rel="noopener">Pol&iacute;tica de Privacidade</a>.
                    </span>
                </label>

                <button class="btn btn-pill btn-accent btn-block" type="submit">Criar conta</button>
            </form>

            <?php if (empty($pendingGoogleRegistration)): ?>
            <div class="auth-divider" aria-hidden="true"><span>ou</span></div>

            <div class="auth-social-row">
                <a class="auth-social-button" href="<?= e($authGoogleRegisterUrl) ?>" aria-label="Criar conta com Google">
                    <svg class="auth-social-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"></path>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"></path>
                        <path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l3.66-2.84z"></path>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06L5.84 9.9C6.71 7.31 9.14 5.38 12 5.38z"></path>
                    </svg>
                    <span>Criar conta com Google</span>
                </a>
            </div>

            <p class="auth-social-note">
                Ao continuar com Google, voce aceita os
                <a href="<?= e(siteUrl('termos')) ?>" target="_blank" rel="noopener">Termos de Uso</a>
                e a
                <a href="<?= e(siteUrl('privacidade')) ?>" target="_blank" rel="noopener">Pol&iacute;tica de Privacidade</a>.
            </p>
            <?php endif; ?>

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

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

        <?php if (!empty($currentUser)): ?>
            <p class="auth-switch-line">
                Você já tem uma sessão aberta. Entrar com outra conta substituirá a atual.
            </p>
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
                    <input type="password" name="password" placeholder="Sua senha" autocomplete="current-password" required>
                </label>

                <button class="btn btn-pill btn-accent btn-block" type="submit">Entrar</button>
            </form>

            <p class="auth-switch-line">
                <button type="button" class="auth-inline-link" data-auth-target="forgot-password">Esqueci minha senha</button>
            </p>

            <p class="auth-switch-line">
                Não tem conta?
                <button type="button" class="auth-inline-link" data-auth-target="register">Criar conta</button>
            </p>
        </section>

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
                    <input type="password" name="password" placeholder="Mínimo 6 caracteres" minlength="6" autocomplete="new-password" required>
                </label>

                <label>
                    <span>Confirmar senha</span>
                    <input type="password" name="password_confirm" placeholder="Repita a senha" minlength="6" autocomplete="new-password" required>
                </label>

                <button class="btn btn-pill btn-accent btn-block" type="submit">Criar conta</button>
            </form>

            <p class="auth-switch-line">
                Já tem conta?
                <button type="button" class="auth-inline-link" data-auth-target="login">Entrar</button>
            </p>
        </section>

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
                        <input type="password" name="new_password" placeholder="Mínimo 6 caracteres" minlength="6" autocomplete="new-password" required>
                    </label>

                    <label>
                        <span>Confirmar nova senha</span>
                        <input type="password" name="new_password_confirm" placeholder="Repita a nova senha" minlength="6" autocomplete="new-password" required>
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

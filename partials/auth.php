<main class="auth-screen" id="auth-panels">
    <section class="auth-card" aria-labelledby="auth-title">
        <div class="auth-card-glow" aria-hidden="true"></div>

        <div class="auth-brand-block">
            <img src="assets/logo-lockup.svg?v=2" alt="WorkForm" class="auth-brand-lockup" width="260" height="88">
        </div>

        <div class="auth-card-head">
            <h1 id="auth-title">Acesso ao workspace</h1>
        </div>

        <div class="auth-tabs" role="tablist" aria-label="Entrar ou cadastrar">
            <button
                type="button"
                class="auth-tab is-active"
                role="tab"
                aria-selected="true"
                aria-controls="auth-panel-login"
                id="auth-tab-login"
                data-auth-target="login"
            >
                Entrar
            </button>
            <button
                type="button"
                class="auth-tab"
                role="tab"
                aria-selected="false"
                aria-controls="auth-panel-register"
                id="auth-tab-register"
                data-auth-target="register"
            >
                Cadastrar
            </button>
        </div>

        <section
            class="auth-pane is-active"
            role="tabpanel"
            id="auth-panel-login"
            aria-labelledby="auth-tab-login"
            data-auth-panel="login"
        >
            <form method="post" class="form-stack auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="login">

                <label>
                    <span>E-mail</span>
                    <input type="email" name="email" placeholder="voce@empresa.com" autocomplete="email" required>
                </label>

                <label>
                    <span>Senha</span>
                    <input type="password" name="password" placeholder="Sua senha" autocomplete="current-password" required>
                </label>

                <button class="btn btn-pill btn-accent btn-block" type="submit">Entrar</button>
            </form>

            <p class="auth-switch-line">
                Nao tem conta?
                <button type="button" class="auth-inline-link" data-auth-target="register">Criar conta</button>
            </p>
        </section>

        <section
            class="auth-pane"
            role="tabpanel"
            id="auth-panel-register"
            aria-labelledby="auth-tab-register"
            data-auth-panel="register"
            hidden
        >
            <form method="post" class="form-stack auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="register">

                <label>
                    <span>Nome</span>
                    <input type="text" name="name" placeholder="Nome completo" autocomplete="name" required>
                </label>

                <label>
                    <span>E-mail</span>
                    <input type="email" name="email" placeholder="voce@empresa.com" autocomplete="email" required>
                </label>

                <label>
                    <span>Senha</span>
                    <input type="password" name="password" placeholder="Minimo 6 caracteres" minlength="6" autocomplete="new-password" required>
                </label>

                <label>
                    <span>Confirmar senha</span>
                    <input type="password" name="password_confirm" placeholder="Repita a senha" minlength="6" autocomplete="new-password" required>
                </label>

                <button class="btn btn-pill btn-accent btn-block" type="submit">Criar conta</button>
            </form>

            <p class="auth-switch-line">
                Ja tem conta?
                <button type="button" class="auth-inline-link" data-auth-target="login">Entrar</button>
            </p>
        </section>
    </section>
</main>

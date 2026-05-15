(() => {
  const INSTALL_DISMISS_KEY = "bexon_pwa_install_dismiss_until";
  const INSTALL_DISMISS_MS = 1000 * 60 * 60 * 24 * 7;
  const scriptUrl = document.currentScript?.src || "";
  const basePath = (() => {
    try {
      const pathname = new URL(scriptUrl, window.location.href).pathname;
      return pathname.replace(/\/assets\/pwa\.js(?:$|[?#].*)/i, "");
    } catch (_error) {
      return "";
    }
  })();

  const appScope = () => {
    const cleanBase = String(basePath || "").replace(/\/+$/, "");
    return cleanBase ? `${cleanBase}/` : "/";
  };

  const appPath = (path) => {
    const cleanBase = String(basePath || "").replace(/\/+$/, "");
    const cleanPath = String(path || "").replace(/^\/+/, "");
    if (cleanPath === "") {
      return cleanBase || "/";
    }

    return `${cleanBase}/${cleanPath}`.replace(/\/+/g, "/") || "/";
  };

  const isStandalone = () =>
    window.matchMedia?.("(display-mode: standalone)")?.matches === true ||
    window.navigator.standalone === true;

  const syncDisplayMode = () => {
    document.documentElement.dataset.displayMode = isStandalone()
      ? "standalone"
      : "browser";
  };

  const readDismissUntil = () => {
    try {
      return Number.parseInt(window.localStorage.getItem(INSTALL_DISMISS_KEY) || "0", 10) || 0;
    } catch (_error) {
      return 0;
    }
  };

  const dismissPrompt = () => {
    try {
      window.localStorage.setItem(
        INSTALL_DISMISS_KEY,
        String(Date.now() + INSTALL_DISMISS_MS)
      );
    } catch (_error) {
      // Ignore storage failures and keep the prompt ephemeral.
    }
  };

  const clearDismissPrompt = () => {
    try {
      window.localStorage.removeItem(INSTALL_DISMISS_KEY);
    } catch (_error) {
      // Ignore storage failures and keep the prompt ephemeral.
    }
  };

  syncDisplayMode();
  window
    .matchMedia?.("(display-mode: standalone)")
    ?.addEventListener?.("change", syncDisplayMode);

  if ("serviceWorker" in navigator && window.isSecureContext) {
    window.addEventListener(
      "load",
      () => {
        navigator.serviceWorker
          .register(appPath("service-worker.php"), {
            scope: appScope(),
            updateViaCache: "none",
          })
          .catch(() => {
            // A failed registration should not break the app shell.
          });
      },
      { once: true }
    );
  }

  if (isStandalone()) {
    return;
  }

  let deferredPrompt = null;
  let banner = null;

  const removeBanner = () => {
    if (banner instanceof HTMLElement) {
      banner.remove();
    }
    banner = null;
  };

  const showBanner = () => {
    if (!(document.body instanceof HTMLBodyElement)) return;
    if (banner || !deferredPrompt || Date.now() < readDismissUntil()) return;

    const wrapper = document.createElement("section");
    wrapper.className = "pwa-install-banner";
    wrapper.setAttribute("aria-label", "Instalar aplicativo");

    const copy = document.createElement("div");
    copy.className = "pwa-install-banner-copy";

    const title = document.createElement("strong");
    title.textContent = "Instale o Bexon";

    const text = document.createElement("p");
    text.textContent =
      "Abrir o Bexon como aplicativo deixa o acesso mais rapido e separado do navegador.";

    copy.append(title, text);

    const actions = document.createElement("div");
    actions.className = "pwa-install-banner-actions";

    const dismissButton = document.createElement("button");
    dismissButton.type = "button";
    dismissButton.className = "pwa-install-banner-dismiss";
    dismissButton.textContent = "Agora nao";
    dismissButton.addEventListener("click", () => {
      dismissPrompt();
      removeBanner();
    });

    const installButton = document.createElement("button");
    installButton.type = "button";
    installButton.className = "pwa-install-banner-install";
    installButton.textContent = "Instalar app";
    installButton.addEventListener("click", async () => {
      if (!deferredPrompt) return;

      installButton.disabled = true;

      try {
        await deferredPrompt.prompt();
        const choice = await deferredPrompt.userChoice;
        if (choice?.outcome !== "accepted") {
          dismissPrompt();
        }
      } catch (_error) {
        dismissPrompt();
      } finally {
        deferredPrompt = null;
        removeBanner();
      }
    });

    actions.append(dismissButton, installButton);
    wrapper.append(copy, actions);
    document.body.append(wrapper);
    banner = wrapper;
  };

  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    deferredPrompt = event;
    clearDismissPrompt();
    showBanner();
  });

  window.addEventListener("appinstalled", () => {
    deferredPrompt = null;
    clearDismissPrompt();
    removeBanner();
    syncDisplayMode();
  });
})();

(() => {
  const DEFAULT_LABEL = "Carregando...";
  const SUBMIT_LABEL = "Processando...";
  const SHOW_DELAY_MS = 140;
  const HIDE_DELAY_MS = 180;
  const PUBLIC_ROUTE_SLUGS = new Set(["home", "privacidade", "termos", "cookies", "dados", "vendas"]);

  let overlay = null;
  let labelNode = null;
  let showTimer = 0;
  let hideTimer = 0;
  let isVisible = false;
  const activeTokens = new Set();

  const ensureOverlay = () => {
    if (overlay && overlay.isConnected) {
      return overlay;
    }

    if (!document.body) {
      return null;
    }

    overlay = document.createElement("div");
    overlay.className = "app-loading-overlay";
    overlay.setAttribute("data-app-loading-overlay", "");
    overlay.setAttribute("aria-hidden", "true");
    overlay.hidden = true;

    const panel = document.createElement("div");
    panel.className = "app-loading-panel";
    panel.setAttribute("role", "status");
    panel.setAttribute("aria-live", "polite");

    const spinner = document.createElement("span");
    spinner.className = "app-loading-spinner";
    spinner.setAttribute("aria-hidden", "true");

    labelNode = document.createElement("span");
    labelNode.className = "app-loading-copy";
    labelNode.setAttribute("data-app-loading-label", "");
    labelNode.textContent = DEFAULT_LABEL;

    panel.append(spinner, labelNode);
    overlay.append(panel);
    document.body.append(overlay);

    return overlay;
  };

  const setLabel = (label) => {
    const nextLabel = String(label || "").trim() || DEFAULT_LABEL;
    const root = ensureOverlay();
    if (!root || !labelNode) return;
    labelNode.textContent = nextLabel;
  };

  const reveal = () => {
    const root = ensureOverlay();
    if (!root) return;

    root.hidden = false;
    root.setAttribute("aria-hidden", "false");
    document.body.classList.add("is-app-loading");

    window.requestAnimationFrame(() => {
      if (activeTokens.size > 0) {
        root.classList.add("is-visible");
      }
    });
  };

  const conceal = () => {
    if (!overlay) return;

    overlay.classList.remove("is-visible");
    overlay.setAttribute("aria-hidden", "true");
    document.body?.classList.remove("is-app-loading");

    window.setTimeout(() => {
      if (overlay && activeTokens.size === 0) {
        overlay.hidden = true;
      }
    }, HIDE_DELAY_MS);
  };

  const hideToken = (token) => {
    if (token && activeTokens.has(token)) {
      activeTokens.delete(token);
    } else if (!token) {
      activeTokens.clear();
    }

    if (activeTokens.size > 0) return;

    if (showTimer) {
      window.clearTimeout(showTimer);
      showTimer = 0;
    }

    if (!isVisible) return;

    if (hideTimer) {
      window.clearTimeout(hideTimer);
    }

    hideTimer = window.setTimeout(() => {
      hideTimer = 0;
      if (activeTokens.size > 0) return;
      isVisible = false;
      conceal();
    }, HIDE_DELAY_MS);
  };

  const show = (options = {}) => {
    const token = {};
    activeTokens.add(token);
    setLabel(options.label || DEFAULT_LABEL);

    if (hideTimer) {
      window.clearTimeout(hideTimer);
      hideTimer = 0;
    }

    if (!isVisible && !showTimer) {
      showTimer = window.setTimeout(() => {
        showTimer = 0;
        if (activeTokens.size <= 0) return;
        isVisible = true;
        reveal();
      }, Number.isFinite(options.delay) ? Math.max(0, options.delay) : SHOW_DELAY_MS);
    }

    let isDone = false;
    return {
      done() {
        if (isDone) return;
        isDone = true;
        hideToken(token);
      },
      hide() {
        this.done();
      },
    };
  };

  const withLoading = (work, options = {}) => {
    const token = show(options);
    return Promise.resolve()
      .then(work)
      .finally(() => {
        token.done();
      });
  };

  const reset = () => {
    activeTokens.clear();
    if (showTimer) {
      window.clearTimeout(showTimer);
      showTimer = 0;
    }
    if (hideTimer) {
      window.clearTimeout(hideTimer);
      hideTimer = 0;
    }
    isVisible = false;
    if (overlay) {
      overlay.classList.remove("is-visible");
      overlay.hidden = true;
      overlay.setAttribute("aria-hidden", "true");
    }
    document.body?.classList.remove("is-app-loading");
  };

  const routeSlugFromPathname = (pathname) => {
    const normalizedPath = String(pathname || "").replace(/\/+$/, "");
    if (!normalizedPath) return "";

    const segments = normalizedPath.split("/").filter(Boolean);
    const lastSegment = segments[segments.length - 1] || "";
    return lastSegment.replace(/\.php$/i, "").toLowerCase();
  };

  const isPublicRouteDestination = (pathname) => PUBLIC_ROUTE_SLUGS.has(routeSlugFromPathname(pathname));

  const shouldIgnoreAnchor = (anchor) => {
    if (!(anchor instanceof HTMLAnchorElement)) return true;
    if (anchor.hasAttribute("download")) return true;
    if (anchor.closest("[data-no-loading]")) return true;

    const target = String(anchor.getAttribute("target") || "").trim().toLowerCase();
    if (target && target !== "_self") return true;

    const rawHref = String(anchor.getAttribute("href") || "").trim();
    if (!rawHref) return true;

    const lowerHref = rawHref.toLowerCase();
    if (
      lowerHref.startsWith("#") ||
      lowerHref.startsWith("javascript:") ||
      lowerHref.startsWith("mailto:") ||
      lowerHref.startsWith("tel:")
    ) {
      return true;
    }

    try {
      const nextUrl = new URL(anchor.href, window.location.href);
      const currentUrl = new URL(window.location.href);
      const sameDocument =
        nextUrl.origin === currentUrl.origin &&
        nextUrl.pathname === currentUrl.pathname &&
        nextUrl.search === currentUrl.search &&
        nextUrl.hash !== currentUrl.hash;

      if (sameDocument) return true;
      if (nextUrl.href === currentUrl.href) return true;
      if (isPublicRouteDestination(nextUrl.pathname)) return true;
    } catch (_error) {
      return true;
    }

    return false;
  };

  const bindPageLoadingFeedback = () => {
    document.addEventListener(
      "click",
      (event) => {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const target =
          event.target instanceof Element ? event.target : event.target?.parentElement;
        const anchor = target?.closest("a[href]");
        if (!anchor) return;

        window.setTimeout(() => {
          if (event.defaultPrevented || shouldIgnoreAnchor(anchor)) return;
          show({ label: anchor.getAttribute("data-loading-label") || DEFAULT_LABEL });
        }, 0);
      },
      true
    );

    document.addEventListener(
      "submit",
      (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form || form.hasAttribute("data-no-loading")) return;

        window.setTimeout(() => {
          if (event.defaultPrevented || form.hasAttribute("data-no-loading")) return;

          const method = String(form.getAttribute("method") || "get").trim().toLowerCase();
          const target = String(form.getAttribute("target") || "").trim().toLowerCase();
          if (method === "dialog") return;
          if (target && target !== "_self") return;

          show({ label: form.getAttribute("data-loading-label") || SUBMIT_LABEL });
        }, 0);
      },
      true
    );
  };

  window.BexonLoading = {
    show,
    hide: () => hideToken(),
    reset,
    setLabel,
    withLoading,
  };

  bindPageLoadingFeedback();
  window.addEventListener("pageshow", reset);
})();

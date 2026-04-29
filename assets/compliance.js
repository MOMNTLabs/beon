(() => {
  const STORAGE_KEY = "bexon_cookie_consent";
  const COOKIE_NAME = "bexon_cookie_consent";
  const COOKIE_MAX_AGE = 60 * 60 * 24 * 180;
  const scriptUrl = document.currentScript?.src || "";
  const basePath = (() => {
    try {
      const pathname = new URL(scriptUrl, window.location.href).pathname;
      return pathname.replace(/\/assets\/compliance\.js(?:$|[?#].*)/i, "");
    } catch (_error) {
      return "";
    }
  })();

  const appPath = (path) => {
    const cleanBase = String(basePath || "").replace(/\/+$/, "");
    const cleanPath = String(path || "").replace(/^\/+/, "");
    return `${cleanBase}/${cleanPath}`.replace(/\/+/g, "/") || "/";
  };

  const readPreference = () => {
    try {
      const value = window.localStorage.getItem(STORAGE_KEY);
      if (value === "accepted" || value === "essential") {
        return value;
      }
    } catch (_error) {
      return "";
    }

    const match = document.cookie
      .split(";")
      .map((item) => item.trim())
      .find((item) => item.startsWith(`${COOKIE_NAME}=`));
    return match ? decodeURIComponent(match.split("=").slice(1).join("=")) : "";
  };

  const writePreference = (value) => {
    const normalized = value === "accepted" ? "accepted" : "essential";
    try {
      window.localStorage.setItem(STORAGE_KEY, normalized);
    } catch (_error) {
      // Cookie fallback below is enough for this preference.
    }

    const secure = window.location.protocol === "https:" ? "; Secure" : "";
    document.cookie = `${COOKIE_NAME}=${encodeURIComponent(normalized)}; Max-Age=${COOKIE_MAX_AGE}; Path=/; SameSite=Lax${secure}`;
  };

  const removeBanner = () => {
    document.querySelector("[data-cookie-banner]")?.remove();
  };

  const showBanner = ({ force = false } = {}) => {
    if (!force && readPreference()) return;
    removeBanner();

    const banner = document.createElement("section");
    banner.className = "cookie-banner";
    banner.setAttribute("data-cookie-banner", "");
    banner.setAttribute("aria-label", "Aviso de cookies");

    const content = document.createElement("div");
    content.className = "cookie-banner-content";

    const title = document.createElement("strong");
    title.textContent = "Cookies e privacidade";

    const text = document.createElement("p");
    text.textContent =
      "Usamos cookies essenciais para login, seguranca e preferencias da aplicacao. Voce pode aceitar o aviso ou manter apenas os essenciais.";

    const actions = document.createElement("div");
    actions.className = "cookie-banner-actions";

    const policy = document.createElement("a");
    policy.href = appPath("cookies");
    policy.textContent = "Ver politica";

    const essential = document.createElement("button");
    essential.type = "button";
    essential.textContent = "Manter essenciais";
    essential.addEventListener("click", () => {
      writePreference("essential");
      removeBanner();
    });

    const accept = document.createElement("button");
    accept.type = "button";
    accept.className = "cookie-banner-primary";
    accept.textContent = "Entendi";
    accept.addEventListener("click", () => {
      writePreference("accepted");
      removeBanner();
    });

    actions.append(policy, essential, accept);
    content.append(title, text);
    banner.append(content, actions);
    document.body.append(banner);
  };

  document.addEventListener("click", (event) => {
    const target =
      event.target instanceof Element ? event.target : event.target?.parentElement;
    const trigger = target?.closest("[data-cookie-preferences]");
    if (!trigger) return;
    event.preventDefault();
    showBanner({ force: true });
  });

  window.BexonCookieConsent = {
    showPreferences: () => showBanner({ force: true }),
    preference: readPreference,
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => showBanner());
  } else {
    showBanner();
  }
})();

(() => {
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

  const userAgent = String(window.navigator.userAgent || "");
  const isAppleMobile =
    /iPhone|iPad|iPod/i.test(userAgent) ||
    (window.navigator.platform === "MacIntel" && window.navigator.maxTouchPoints > 1);
  const isAndroidMobile = /Android/i.test(userAgent);
  const isMobileInstallContext = isAppleMobile || isAndroidMobile;
  const isSafariBrowser =
    /Safari/i.test(userAgent) &&
    !/CriOS|FxiOS|EdgiOS|OPiOS|OPT\//i.test(userAgent);

  const syncDisplayMode = () => {
    document.documentElement.dataset.displayMode = isStandalone()
      ? "standalone"
      : "browser";
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
  const entries = Array.from(document.querySelectorAll("[data-pwa-install-entry]"));
  const installModal = document.querySelector("[data-pwa-install-modal]");
  const installModalIntro = installModal?.querySelector("[data-pwa-install-modal-intro]");
  const installModalSteps = installModal?.querySelector("[data-pwa-install-modal-steps]");
  const installModalFootnote = installModal?.querySelector("[data-pwa-install-modal-footnote]");

  const getInstallMode = () => {
    if (isStandalone()) return "installed";
    if (!isMobileInstallContext) return "hidden";
    if (deferredPrompt) return "prompt";
    if (isAppleMobile && isSafariBrowser) return "ios_safari";
    if (isAppleMobile) return "ios_other";
    return "guide";
  };

  const getModeCopy = (mode) => {
    switch (mode) {
      case "prompt":
        return {
          message:
            "Instale o Bexon como aplicativo para abrir mais rapido e usar o painel em tela cheia.",
          triggerLabel: "Instalar app",
        };
      case "ios_safari":
        return {
          message:
            "No iPhone, a instalacao e feita pelo menu Compartilhar do Safari. O botao abaixo mostra o caminho.",
          triggerLabel: "Adicionar a Tela de Inicio",
        };
      case "ios_other":
        return {
          message:
            "No iPhone, a instalacao do Bexon precisa ser feita no Safari. O botao abaixo mostra como seguir.",
          triggerLabel: "Ver como instalar",
        };
      case "guide":
        return {
          message:
            "A instalacao do Bexon depende do navegador do celular. O botao abaixo mostra o caminho recomendado.",
          triggerLabel: "Como instalar",
        };
      default:
        return {
          message: "",
          triggerLabel: "Instalar app",
        };
    }
  };

  const getInstructionContent = (mode) => {
    if (mode === "guide") {
      if (isAndroidMobile) {
        return {
          intro:
            "Neste navegador, o Bexon nao recebeu um prompt nativo de instalacao. Ainda assim, voce pode instalar manualmente.",
          steps: [
            "Abra o menu do navegador.",
            "Procure por \"Instalar app\" ou \"Adicionar a tela inicial\".",
            "Confirme a instalacao para salvar o Bexon como app.",
          ],
          footnote:
            "Se essa opcao nao aparecer, abra o Bexon no Chrome ou Edge do Android.",
        };
      }

      return {
        intro:
          "A instalacao do Bexon precisa ser concluida em um navegador compativel no celular.",
        steps: [
          "No Android, abra o Bexon no Chrome ou Edge.",
          "No iPhone, abra o Bexon no Safari.",
          "No navegador escolhido, use a opcao \"Instalar app\" ou \"Adicionar a Tela de Inicio\".",
        ],
        footnote:
          "Em navegador interno, desktop ou contextos sem suporte ao prompt, a instalacao nao aparece diretamente aqui.",
      };
    }

    if (mode === "ios_other") {
      return {
        intro:
          "A instalacao do Bexon no iPhone precisa ser concluida no Safari.",
        steps: [
          "Abra esta mesma pagina no Safari.",
          "No Safari, toque no botao Compartilhar.",
          "Escolha \"Adicionar a Tela de Inicio\".",
          "Confirme o nome do app e toque em \"Adicionar\".",
        ],
        footnote:
          "Chrome e Edge no iPhone nao disparam o prompt de instalacao do PWA por conta propria.",
      };
    }

    return {
      intro:
        "No iPhone, a instalacao do Bexon e feita pelo menu do Safari.",
      steps: [
        "Toque no botao Compartilhar do Safari.",
        "Escolha \"Adicionar a Tela de Inicio\".",
        "Confirme o nome do app e toque em \"Adicionar\".",
      ],
      footnote: "",
    };
  };

  const setModalOpen = (open) => {
    if (!(installModal instanceof HTMLElement) || !(document.body instanceof HTMLBodyElement)) {
      return;
    }

    installModal.hidden = !open;
    document.body.classList.toggle("pwa-install-modal-open", open);
  };

  const openInstructionModal = (mode) => {
    if (
      !(installModal instanceof HTMLElement) ||
      !(installModalIntro instanceof HTMLElement) ||
      !(installModalSteps instanceof HTMLOListElement) ||
      !(installModalFootnote instanceof HTMLElement)
    ) {
      return;
    }

    const instructionContent = getInstructionContent(mode);
    installModalIntro.textContent = instructionContent.intro;
    installModalSteps.innerHTML = instructionContent.steps
      .map((step) => `<li>${step}</li>`)
      .join("");
    installModalFootnote.textContent = instructionContent.footnote;
    installModalFootnote.hidden = instructionContent.footnote === "";
    setModalOpen(true);
  };

  const closeInstructionModal = () => {
    setModalOpen(false);
  };

  const updateEntries = () => {
    const mode = getInstallMode();
    const modeCopy = getModeCopy(mode);

    entries.forEach((entry) => {
      if (!(entry instanceof HTMLElement)) return;

      const message = entry.querySelector("[data-pwa-install-message]");
      const trigger = entry.querySelector("[data-pwa-install-trigger]");
      const shouldHide = mode === "hidden" || mode === "installed";

      entry.hidden = shouldHide;
      if (message instanceof HTMLElement) {
        message.textContent = modeCopy.message;
      }
      if (trigger instanceof HTMLButtonElement) {
        trigger.textContent = modeCopy.triggerLabel;
        trigger.disabled = mode === "installed";
        trigger.dataset.pwaInstallMode = mode;
      }
    });
  };

  const triggerInstallFlow = async () => {
    const mode = getInstallMode();
    if (mode === "prompt" && deferredPrompt) {
      try {
        await deferredPrompt.prompt();
        await deferredPrompt.userChoice;
      } catch (_error) {
        // Ignore and keep the entry available for another attempt.
      } finally {
        deferredPrompt = null;
        updateEntries();
      }
      return;
    }

    if (mode === "ios_safari" || mode === "ios_other" || mode === "guide") {
      openInstructionModal(mode);
    }
  };

  entries.forEach((entry) => {
    if (!(entry instanceof HTMLElement)) return;

    const trigger = entry.querySelector("[data-pwa-install-trigger]");

    if (trigger instanceof HTMLButtonElement) {
      trigger.addEventListener("click", () => {
        triggerInstallFlow();
      });
    }
  });

  Array.from(installModal?.querySelectorAll("[data-pwa-install-close]") || []).forEach(
    (closeButton) => {
      closeButton.addEventListener("click", () => {
        closeInstructionModal();
      });
    }
  );

  window.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && installModal instanceof HTMLElement && !installModal.hidden) {
      closeInstructionModal();
    }
  });

  updateEntries();

  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    deferredPrompt = event;
    updateEntries();
  });

  window.addEventListener("appinstalled", () => {
    deferredPrompt = null;
    closeInstructionModal();
    updateEntries();
    syncDisplayMode();
  });
})();

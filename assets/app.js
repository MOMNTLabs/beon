document.addEventListener("click", (event) => {
  const button = event.target.closest("[data-flash-close]");
  if (!button) return;
  const flash = button.closest("[data-flash]");
  if (flash) flash.remove();
});

window.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-flash]").forEach((flash) => {
    window.setTimeout(() => {
      if (flash.isConnected) flash.remove();
    }, 5000);
  });

  const authTabs = Array.from(
    document.querySelectorAll('[role="tab"][data-auth-target]')
  );
  const authSwitches = Array.from(
    document.querySelectorAll('[data-auth-target]:not([role="tab"])')
  );
  const authPanels = Array.from(document.querySelectorAll("[data-auth-panel]"));

  if (authTabs.length && authPanels.length) {
    const setAuthTab = (target) => {
      const exists = authPanels.some((panel) => panel.dataset.authPanel === target);
      const next = exists ? target : "login";

      authTabs.forEach((tab) => {
        const active = tab.dataset.authTarget === next;
        tab.classList.toggle("is-active", active);
        tab.setAttribute("aria-selected", active ? "true" : "false");
      });

      authPanels.forEach((panel) => {
        const active = panel.dataset.authPanel === next;
        panel.classList.toggle("is-active", active);
        panel.hidden = !active;
      });
    };

    setAuthTab(window.location.hash === "#register" ? "register" : "login");

    authTabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        setAuthTab(tab.dataset.authTarget);
      });
    });

    authSwitches.forEach((trigger) => {
      trigger.addEventListener("click", () => {
        setAuthTab(trigger.dataset.authTarget);
      });
    });
  }

  const syncSelectColor = (select) => {
    if (!select) return;

    if (select.classList.contains("status-select")) {
      Array.from(select.classList).forEach((className) => {
        if (className.startsWith("status-") && className !== "status-select") {
          select.classList.remove(className);
        }
      });
      if (select.value) select.classList.add(`status-${select.value}`);
    }

    if (select.classList.contains("priority-select")) {
      Array.from(select.classList).forEach((className) => {
        if (className.startsWith("priority-") && className !== "priority-select") {
          select.classList.remove(className);
        }
      });
      if (select.value) select.classList.add(`priority-${select.value}`);
    }
  };

  document
    .querySelectorAll(".status-select, .priority-select")
    .forEach(syncSelectColor);

  document.addEventListener("change", (event) => {
    const select = event.target.closest(".status-select, .priority-select");
    if (select) {
      syncSelectColor(select);
    }
  });

  const updateAssigneePickerSummary = (details) => {
    const summary = details.querySelector("summary");
    if (!summary) return;

    const checkedNames = Array.from(
      details.querySelectorAll('input[type="checkbox"]:checked')
    )
      .map((checkbox) => checkbox.closest("label")?.querySelector("span")?.textContent?.trim())
      .filter(Boolean);

    if (!checkedNames.length) {
      summary.textContent = details.classList.contains("row-assignee-picker")
        ? "Sem responsável"
        : "Selecionar";
      return;
    }

    const text = checkedNames.join(", ");
    summary.textContent =
      details.classList.contains("row-assignee-picker") && text.length > 40
        ? `${text.slice(0, 37)}...`
        : text;
    summary.title = checkedNames.join(", ");
  };

  document.querySelectorAll(".assignee-picker").forEach((details) => {
    updateAssigneePickerSummary(details);
  });

  document.addEventListener("change", (event) => {
    const checkbox = event.target.closest('.assignee-picker input[type="checkbox"]');
    if (!checkbox) return;
    const picker = checkbox.closest(".assignee-picker");
    if (picker) updateAssigneePickerSummary(picker);
  });

  document.addEventListener("click", (event) => {
    const toggleButton = event.target.closest("[data-task-expand]");
    if (!toggleButton) return;

    const taskItem = toggleButton.closest("[data-task-item]");
    const details = taskItem?.querySelector(".task-line-details");
    if (!details) return;

    const isOpen = !details.hidden;
    details.hidden = isOpen;
    toggleButton.setAttribute("aria-expanded", isOpen ? "false" : "true");
    toggleButton.textContent = isOpen ? "Detalhes" : "Fechar";
  });
});

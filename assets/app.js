document.addEventListener("click", (event) => {
  const target =
    event.target instanceof Element ? event.target : event.target?.parentElement;
  if (!(target instanceof Element)) return;
  const button = target.closest("[data-flash-close]");
  if (!button) return;
  const flash = button.closest("[data-flash]");
  if (flash) flash.remove();
});

window.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-flash]:not([data-flash-persist])").forEach((flash) => {
    window.setTimeout(() => {
      if (flash.isConnected) flash.remove();
    }, 5000);
  });

  document.addEventListener("click", (event) => {
    const target =
      event.target instanceof Element ? event.target : event.target?.parentElement;
    if (!(target instanceof Element)) return;

    const toggle = target.closest("[data-password-toggle]");
    if (!(toggle instanceof HTMLButtonElement)) return;

    const field = toggle.closest(".auth-password-field");
    const input = field?.querySelector("input");
    if (!(input instanceof HTMLInputElement)) return;

    const shouldShow = input.type === "password";
    input.type = shouldShow ? "text" : "password";
    toggle.setAttribute("aria-pressed", shouldShow ? "true" : "false");
    toggle.setAttribute("aria-label", shouldShow ? "Ocultar senha" : "Mostrar senha");
  });

  document.documentElement.style.colorScheme = "light";

  const authTabs = Array.from(
    document.querySelectorAll('[role="tab"][data-auth-target]')
  );
  const authSwitches = Array.from(
    document.querySelectorAll('[data-auth-target]:not([role="tab"])')
  );
  const authPanels = Array.from(document.querySelectorAll("[data-auth-panel]"));
  const authPanelsRoot = document.querySelector("#auth-panels");

  if (authPanels.length) {
    const readAuthTargetFromHash = () =>
      String(window.location.hash || "").replace(/^#/, "").trim();
    const initialAuthPanel =
      authPanelsRoot instanceof HTMLElement
        ? String(authPanelsRoot.dataset.authInitialPanel || "").trim()
        : "";

    const syncAuthHash = (target) => {
      if (!target) return;
      const nextHash = `#${target}`;
      if (window.location.hash === nextHash) return;

      if (window.history && typeof window.history.replaceState === "function") {
        window.history.replaceState(null, "", nextHash);
        return;
      }

      window.location.hash = target;
    };

    const setAuthTab = (target, { updateHash = true } = {}) => {
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

      if (updateHash) {
        syncAuthHash(next);
      }
    };

    setAuthTab(readAuthTargetFromHash() || initialAuthPanel || "login", {
      updateHash: false,
    });

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

    window.addEventListener("hashchange", () => {
      setAuthTab(readAuthTargetFromHash(), { updateHash: false });
    });
  }

  const billingToggle = document.querySelector("[data-billing-toggle]");
  const billingButtons =
    billingToggle instanceof HTMLElement
      ? Array.from(billingToggle.querySelectorAll("[data-billing-interval]"))
      : [];
  const planCards = Array.from(document.querySelectorAll("[data-plan-card]"));

  if (billingButtons.length && planCards.length) {
    const applyBillingInterval = (interval) => {
      const normalizedInterval = interval === "month" ? "month" : "year";

      billingButtons.forEach((button) => {
        const isActive = button.getAttribute("data-billing-interval") === normalizedInterval;
        button.classList.toggle("is-active", isActive);
        button.setAttribute("aria-pressed", isActive ? "true" : "false");
      });

      planCards.forEach((card) => {
        if (!(card instanceof HTMLElement)) return;

        const price = card.querySelector("[data-plan-price-value]");
        const amount = price?.querySelector(".plans-price-amount, .sales-price-amount");
        let currency = price?.querySelector(".plans-price-currency, .sales-price-currency");
        const suffix = card.querySelector("[data-plan-price-suffix]");
        const billingNote = card.querySelector("[data-plan-billing-note]");
        const trialNote = card.querySelector("[data-plan-trial-note]");
        const action = card.querySelector("[data-plan-action]");
        const priceValue = card.getAttribute(`data-price-${normalizedInterval}`) || "";
        const noteValue = card.getAttribute(`data-note-${normalizedInterval}`) || "";
        const trialValue = card.getAttribute(`data-trial-${normalizedInterval}`) || "";
        const actionValue = card.getAttribute(`data-action-${normalizedInterval}`) || "";
        const priceMatch = priceValue.match(/^R\$\s*(.+)$/);

        if (price instanceof HTMLElement && amount instanceof HTMLElement) {
          if (priceMatch) {
            if (!(currency instanceof HTMLElement)) {
              currency = document.createElement("span");
              currency.className = card.classList.contains("plans-card")
                ? "plans-price-currency"
                : "sales-price-currency";
              price.insertBefore(currency, amount);
            }
            currency.textContent = "R$";
            currency.hidden = false;
            amount.textContent = priceMatch[1] || "";
          } else {
            if (currency instanceof HTMLElement) {
              currency.hidden = true;
            }
            amount.textContent = priceValue;
          }
        }

        if (suffix instanceof HTMLElement) {
          suffix.hidden = (card.getAttribute("data-suffix") || "") === "";
        }

        if (billingNote instanceof HTMLElement) {
          billingNote.textContent = noteValue;
          billingNote.hidden = noteValue === "";
        }

        if (trialNote instanceof HTMLElement) {
          trialNote.textContent = trialValue;
        }

        if (action instanceof HTMLAnchorElement && actionValue !== "") {
          action.setAttribute("href", actionValue);
        }
      });
    };

    billingButtons.forEach((button) => {
      button.addEventListener("click", () => {
        applyBillingInterval(button.getAttribute("data-billing-interval") || "year");
      });
    });

    applyBillingInterval(
      billingToggle instanceof HTMLElement
        ? billingToggle.getAttribute("data-default-billing-interval") || "year"
        : "year"
    );
  }

  const getEventTargetElement = (event) => {
    const rawTarget = event?.target;
    if (rawTarget instanceof Element) return rawTarget;
    if (rawTarget instanceof Node) return rawTarget.parentElement;
    return null;
  };

  const syncStatusStepper = (select) => {
    if (!(select instanceof HTMLSelectElement)) return;

    const stepper = select.closest("[data-status-stepper]");
    if (!(stepper instanceof HTMLElement)) return;

    const currentIndex = Math.max(0, select.selectedIndex);
    const lastIndex = Math.max(0, select.options.length - 1);

    const prevButton = stepper.querySelector('[data-status-step="-1"]');
    const nextButton = stepper.querySelector('[data-status-step="1"]');

    if (prevButton instanceof HTMLButtonElement) {
      const atStart = currentIndex <= 0;
      prevButton.hidden = atStart;
      prevButton.disabled = atStart;
    }

    if (nextButton instanceof HTMLButtonElement) {
      const atEnd = currentIndex >= lastIndex;
      nextButton.hidden = atEnd;
      nextButton.disabled = atEnd;
    }
  };

  const taskStatusSortRank = (status, order = null) => {
    if (Number.isFinite(order) && order > 0) {
      return order;
    }
    return 99;
  };

  const normalizeTaskStatusValue = (status) => String(status || "").trim();

  const normalizeTaskStatusKind = (kind) => {
    switch (String(kind || "").trim()) {
      case "todo":
      case "review":
      case "done":
      case "in_progress":
        return String(kind).trim();
      default:
        return "in_progress";
    }
  };

  const defaultStatusColorByKind = (kind) => {
    switch (normalizeTaskStatusKind(kind)) {
      case "todo":
        return "#6EA5E9";
      case "review":
        return "#9C84E6";
      case "done":
        return "#61BE92";
      default:
        return "#E8A15D";
    }
  };

  const normalizeStatusColorValue = (value, kind = "in_progress") => {
    const normalized = String(value || "").trim().toUpperCase();
    if (/^#[0-9A-F]{3}$/.test(normalized)) {
      return `#${normalized[1]}${normalized[1]}${normalized[2]}${normalized[2]}${normalized[3]}${normalized[3]}`;
    }
    if (/^#[0-9A-F]{6}$/.test(normalized)) {
      return normalized;
    }
    return defaultStatusColorByKind(kind);
  };

  const hexToRgbComponents = (value, kind = "in_progress") => {
    const normalized = normalizeStatusColorValue(value, kind).replace(/^#/, "");
    return [
      Number.parseInt(normalized.slice(0, 2), 16),
      Number.parseInt(normalized.slice(2, 4), 16),
      Number.parseInt(normalized.slice(4, 6), 16),
    ];
  };

  const mixHexColors = (source, target, targetWeight = 0.5, kind = "in_progress") => {
    const safeWeight = Math.max(0, Math.min(1, Number(targetWeight) || 0));
    const [sourceRed, sourceGreen, sourceBlue] = hexToRgbComponents(source, kind);
    const [targetRed, targetGreen, targetBlue] = hexToRgbComponents(target, kind);
    const mixChannel = (from, to) => Math.round(from * (1 - safeWeight) + to * safeWeight);
    const toHex = (channel) => channel.toString(16).padStart(2, "0").toUpperCase();

    return `#${toHex(mixChannel(sourceRed, targetRed))}${toHex(
      mixChannel(sourceGreen, targetGreen)
    )}${toHex(mixChannel(sourceBlue, targetBlue))}`;
  };

  const getStatusStyleVars = (color, kind = "in_progress") => {
    const normalizedColor = normalizeStatusColorValue(color, kind);
    const [red, green, blue] = hexToRgbComponents(normalizedColor, kind);
    return {
      color: normalizedColor,
      rgb: `${red}, ${green}, ${blue}`,
      text: mixHexColors(normalizedColor, "#24466F", 0.72, kind),
    };
  };

  const applyStatusStyleVars = (node, color, kind = "in_progress") => {
    if (!(node instanceof HTMLElement)) return;
    const vars = getStatusStyleVars(color, kind);
    node.style.setProperty("--wf-status-color", vars.color);
    node.style.setProperty("--wf-status-rgb", vars.rgb);
    node.style.setProperty("--task-status-rgb", vars.rgb);
    node.style.setProperty("--wf-status-text", vars.text);
    node.dataset.statusColor = vars.color;
  };

  const getSelectedStatusOption = (select) => {
    if (!(select instanceof HTMLSelectElement)) return null;
    const option = select.options[select.selectedIndex];
    return option instanceof HTMLOptionElement ? option : null;
  };

  const getStatusOptionKind = (option) =>
    normalizeTaskStatusKind(option?.dataset?.statusKind || "");

  const getStatusOptionColor = (option) =>
    normalizeStatusColorValue(option?.dataset?.statusColor || "", getStatusOptionKind(option));

  const getStatusOptionOrder = (option) => {
    const order = Number.parseInt(option?.dataset?.statusOrder || "", 10);
    return Number.isFinite(order) && order > 0 ? order : 99;
  };

  const taskPrioritySortRank = (priority) => {
    switch ((priority || "").trim()) {
      case "urgent":
        return 1;
      case "high":
        return 2;
      case "medium":
        return 3;
      case "low":
        return 4;
      default:
        return 99;
    }
  };

  const forceFirstLetterUppercase = (value) => {
    const raw = String(value || "");
    if (!raw) return raw;

    const match = raw.match(/^(\s*)([\s\S]*)$/u);
    if (!match) return raw;

    const leading = match[1] || "";
    const content = match[2] || "";
    if (!content) return raw;

    const chars = Array.from(content);
    if (!chars.length) return raw;

    chars[0] = chars[0].toLocaleUpperCase("pt-BR");
    return `${leading}${chars.join("")}`;
  };

  const applyFirstLetterUppercaseToInput = (field) => {
    if (!(field instanceof HTMLInputElement)) return false;
    const currentValue = String(field.value || "");
    const normalizedValue = forceFirstLetterUppercase(currentValue);
    if (normalizedValue === currentValue) return false;

    const selectionStart = Number.isFinite(field.selectionStart) ? field.selectionStart : null;
    const selectionEnd = Number.isFinite(field.selectionEnd) ? field.selectionEnd : null;
    field.value = normalizedValue;
    if (selectionStart !== null && selectionEnd !== null) {
      field.setSelectionRange(selectionStart, selectionEnd);
    }

    return true;
  };

  const normalizeInventoryIntegerInput = (value, { allowEmpty = false } = {}) => {
    const raw = String(value || "").trim();
    if (raw === "") {
      return allowEmpty ? "" : null;
    }

    if (!/^\d+$/.test(raw)) {
      return null;
    }

    const parsed = Number.parseInt(raw, 10);
    if (!Number.isFinite(parsed) || parsed < 0) {
      return null;
    }

    return String(parsed);
  };

  const normalizeInventoryEntryFields = ({
    quantityField = null,
    minQuantityField = null,
  } = {}) => {
    if (quantityField instanceof HTMLInputElement) {
      const normalizedQuantity = normalizeInventoryIntegerInput(quantityField.value);
      if (normalizedQuantity === null) {
        showClientFlash("error", "Use apenas numeros inteiros na quantidade.");
        quantityField.focus();
        return false;
      }
      quantityField.value = normalizedQuantity;
    }

    if (minQuantityField instanceof HTMLInputElement) {
      const normalizedMinQuantity = normalizeInventoryIntegerInput(minQuantityField.value, {
        allowEmpty: true,
      });
      if (normalizedMinQuantity === null) {
        showClientFlash("error", "Use apenas numeros inteiros no estoque mínimo.");
        minQuantityField.focus();
        return false;
      }
      minQuantityField.value = normalizedMinQuantity;
    }

    return true;
  };

  const uppercaseRequiredInputSelector = [
    ".task-title-input",
    "[data-create-task-title-input]",
    "[data-create-task-title-tag-custom]",
    "[data-task-detail-edit-title]",
    "[data-task-detail-edit-title-tag-custom]",
    "[data-group-name-input]",
    "[data-create-group-name-input]",
    "[data-vault-entry-label-input]",
    "[data-vault-entry-label]",
    "[data-vault-entry-edit-label]",
    "[data-vault-group-name-input]",
    "[data-due-entry-label]",
    "[data-due-entry-edit-label]",
    "[data-due-group-name-input]",
    "[data-inventory-entry-label]",
    "[data-inventory-entry-edit-label]",
    "[data-inventory-group-name-input]",
    "[data-task-detail-edit-subtask-input]",
    "[data-create-task-subtask-input]",
    ".task-subtasks-edit-title",
  ].join(", ");

  const getTaskItemStatusValue = (taskItem) => {
    if (!(taskItem instanceof HTMLElement)) return "";
    const select = taskItem.querySelector("select.status-select");
    if (select instanceof HTMLSelectElement) {
      const selectedValue = normalizeTaskStatusValue(select.value);
      if (selectedValue) {
        return selectedValue;
      }
    }

    return normalizeTaskStatusValue(taskItem.dataset.statusValue || "");
  };

  const getTaskItemStatusKind = (taskItem) => {
    if (!(taskItem instanceof HTMLElement)) return "in_progress";
    const select = taskItem.querySelector("select.status-select");
    if (select instanceof HTMLSelectElement) {
      return getStatusOptionKind(getSelectedStatusOption(select));
    }

    return normalizeTaskStatusKind(taskItem.dataset.statusKind || "");
  };

  const getTaskItemStatusOrder = (taskItem) => {
    if (!(taskItem instanceof HTMLElement)) return 99;
    const select = taskItem.querySelector("select.status-select");
    if (select instanceof HTMLSelectElement) {
      return getStatusOptionOrder(getSelectedStatusOption(select));
    }

    const order = Number.parseInt(taskItem.dataset.statusOrder || "", 10);
    return Number.isFinite(order) && order > 0 ? order : 99;
  };

  const isDoneTaskItem = (taskItem) => {
    if (!(taskItem instanceof HTMLElement)) return false;
    return getTaskItemStatusKind(taskItem) === "done";
  };

  const getTaskItemPriorityValue = (taskItem) => {
    if (!(taskItem instanceof HTMLElement)) return "";
    const select = taskItem.querySelector("select.priority-select");
    if (!(select instanceof HTMLSelectElement)) return "";
    return (select.value || "").trim();
  };

  const sortGroupTaskItemsByStatus = (groupSectionOrDropzone) => {
    let dropzone = groupSectionOrDropzone;

    if (dropzone instanceof HTMLElement && !dropzone.matches("[data-task-dropzone]")) {
      dropzone = dropzone.querySelector("[data-task-dropzone]");
    }

    if (!(dropzone instanceof HTMLElement)) return;

    const taskItems = Array.from(dropzone.children).filter(
      (child) => child instanceof HTMLElement && child.matches("[data-task-item]") && !child.hidden
    );

    if (taskItems.length < 2) return;

    const sorted = taskItems
      .map((taskItem, index) => ({
        taskItem,
        index,
        statusRank: taskStatusSortRank(getTaskItemStatusValue(taskItem), getTaskItemStatusOrder(taskItem)),
        priorityRank: taskPrioritySortRank(getTaskItemPriorityValue(taskItem)),
      }))
      .sort((a, b) => {
        if (a.statusRank !== b.statusRank) return a.statusRank - b.statusRank;
        if (a.priorityRank !== b.priorityRank) return a.priorityRank - b.priorityRank;
        return a.index - b.index;
      });

    sorted.forEach(({ taskItem }) => {
      dropzone.append(taskItem);
    });
  };

  const syncGroupStatusDividers = (groupSectionOrDropzone) => {
    let dropzone = groupSectionOrDropzone;

    if (dropzone instanceof HTMLElement && !dropzone.matches("[data-task-dropzone]")) {
      dropzone = dropzone.querySelector("[data-task-dropzone]");
    }

    if (!(dropzone instanceof HTMLElement)) return;

    dropzone
      .querySelectorAll("[data-task-status-divider]")
      .forEach((divider) => divider.remove());

    const taskItems = Array.from(dropzone.children).filter(
      (child) => child instanceof HTMLElement && child.matches("[data-task-item]")
    );

    if (taskItems.length < 2) return;

    const uniqueStatuses = new Set(
      taskItems.map((taskItem) => getTaskItemStatusValue(taskItem)).filter(Boolean)
    );

    if (uniqueStatuses.size <= 1) return;

    let previousStatus = getTaskItemStatusValue(taskItems[0]);

    taskItems.slice(1).forEach((taskItem) => {
      const currentStatus = getTaskItemStatusValue(taskItem);
      if (!currentStatus || currentStatus === previousStatus) {
        previousStatus = currentStatus || previousStatus;
        return;
      }

      const divider = document.createElement("div");
      divider.className = "task-status-subgroup-divider";
      divider.dataset.taskStatusDivider = "";
      divider.setAttribute("aria-hidden", "true");
      dropzone.insertBefore(divider, taskItem);

      previousStatus = currentStatus;
    });
  };

  const syncTaskRowStatusOverlay = (select) => {
    if (!(select instanceof HTMLSelectElement)) return;
    if (!select.classList.contains("status-select")) return;

    const taskItem = select.closest("[data-task-item]");
    if (!(taskItem instanceof HTMLElement)) return;
    const selectedOption = getSelectedStatusOption(select);
    const statusKind = getStatusOptionKind(selectedOption);
    const statusColor = getStatusOptionColor(selectedOption);
    const statusOrder = getStatusOptionOrder(selectedOption);
    const statusValue = normalizeTaskStatusValue(select.value);

    Array.from(taskItem.classList).forEach((className) => {
      if (className.startsWith("task-status-")) {
        taskItem.classList.remove(className);
      }
    });

    if (statusKind) {
      taskItem.classList.add(`task-status-${statusKind}`);
    }
    applyStatusStyleVars(taskItem, statusColor, statusKind);
    taskItem.dataset.statusValue = statusValue;
    taskItem.dataset.statusKind = statusKind;
    taskItem.dataset.statusColor = statusColor;
    taskItem.dataset.statusOrder = String(statusOrder);

    const groupSection = taskItem.closest("[data-task-group]");
    if (groupSection instanceof HTMLElement) {
      sortGroupTaskItemsByStatus(groupSection);
      syncGroupStatusDividers(groupSection);
    }
  };

  const syncTaskItemOverlayState = (detailsEl) => {
    const taskItem = detailsEl?.closest?.("[data-task-item]");
    if (!(taskItem instanceof HTMLElement)) return;

    const hasOpenOverlay = Boolean(
      taskItem.querySelector('details[open].assignee-picker, details[open][data-inline-select-picker]')
    );
    taskItem.classList.toggle("has-open-overlay", hasOpenOverlay);

    const groupSection = taskItem.closest("[data-task-group]");
    if (groupSection instanceof HTMLElement) {
      const groupHasOpenOverlay = Boolean(
        groupSection.querySelector('details[open].assignee-picker, details[open][data-inline-select-picker]')
      );
      groupSection.classList.toggle("has-open-overlay", groupHasOpenOverlay);
    }
  };

  const closeSiblingTaskOverlays = (detailsEl) => {
    const taskItem = detailsEl?.closest?.("[data-task-item]");
    if (!(taskItem instanceof HTMLElement)) return;

    taskItem
      .querySelectorAll('details[open].assignee-picker, details[open][data-inline-select-picker]')
      .forEach((item) => {
        if (item === detailsEl) return;
        item.open = false;
      });
  };

  const closeOpenDropdownDetails = (targetNode = null) => {
    document
      .querySelectorAll('details[open].assignee-picker, details[open][data-inline-select-picker]')
      .forEach((details) => {
        if (!(details instanceof HTMLDetailsElement)) return;
        if (targetNode instanceof Node && details.contains(targetNode)) return;
        details.open = false;
        syncTaskItemOverlayState(details);
      });
  };

  const closeOpenWorkspaceSidebarPickers = (targetNode = null) => {
    document
      .querySelectorAll("details.workspace-sidebar-picker[open], details.workspace-sidebar-tool-adder[open]")
      .forEach((details) => {
        if (!(details instanceof HTMLDetailsElement)) return;
        if (targetNode instanceof Node && details.contains(targetNode)) return;
        details.open = false;
      });
  };

  const getInlineSelectWrap = (node) => {
    if (!(node instanceof Element)) return null;
    return node.closest("[data-inline-select-wrap], .row-inline-picker-wrap");
  };

  const getClosestFromEventTarget = (target, selector) => {
    if (!(selector && typeof selector === "string")) return null;
    if (target instanceof HTMLElement) return target.closest(selector);
    if (target instanceof Node && target.parentElement instanceof HTMLElement) {
      return target.parentElement.closest(selector);
    }
    return null;
  };

  const syncInlineSelectOptionButtons = (select) => {
    if (!(select instanceof HTMLSelectElement)) return;
    if ((select.dataset.inlineSelectSyncOptions || "").trim() !== "group") return;

    const wrap = getInlineSelectWrap(select);
    if (!(wrap instanceof HTMLElement)) return;

    const menu = wrap.querySelector(".row-inline-picker-menu");
    if (!(menu instanceof HTMLElement)) return;

    menu.innerHTML = "";

    const options = Array.from(select.options);
    if (!options.length) {
      const emptyButton = document.createElement("button");
      emptyButton.type = "button";
      emptyButton.className = "row-inline-picker-option";
      emptyButton.textContent = "Sem grupo com acesso";
      emptyButton.disabled = true;
      emptyButton.dataset.inlineSelectOption = "";
      emptyButton.dataset.value = "";
      emptyButton.dataset.label = "Sem grupo com acesso";
      emptyButton.setAttribute("role", "option");
      emptyButton.setAttribute("aria-selected", "true");
      menu.append(emptyButton);
      return;
    }

    options.forEach((option) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "row-inline-picker-option";
      button.textContent = option.textContent?.trim() || option.value || "";
      button.dataset.inlineSelectOption = "";
      button.dataset.value = option.value || "";
      button.dataset.label = option.textContent?.trim() || option.value || "";
      button.setAttribute("role", "option");
      button.setAttribute("aria-selected", option.selected ? "true" : "false");
      button.disabled = option.disabled;
      menu.append(button);
    });
  };

  const syncInlineSelectPicker = (select) => {
    if (!(select instanceof HTMLSelectElement)) return;
    if (!select.matches("[data-inline-select-source]")) return;

    syncInlineSelectOptionButtons(select);

    const wrap = getInlineSelectWrap(select);
    if (!(wrap instanceof HTMLElement)) return;

    const details = wrap.querySelector("[data-inline-select-picker]");
    const summaryText = wrap.querySelector("[data-inline-select-text]");
    const optionButtons = Array.from(
      wrap.querySelectorAll("[data-inline-select-option]")
    ).filter((button) => button instanceof HTMLButtonElement);

    let selectedLabel = "";

    optionButtons.forEach((button) => {
      const active = (button.dataset.value || "") === (select.value || "");
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-selected", active ? "true" : "false");
      if (active) {
        selectedLabel =
          (button.dataset.label || "").trim() ||
          button.textContent?.trim() ||
          "";
      }
    });

    if (!selectedLabel) {
      const selectedOption = select.options[select.selectedIndex];
      selectedLabel = selectedOption?.textContent?.trim() || "";
    }

    if (summaryText instanceof HTMLElement) {
      summaryText.textContent = selectedLabel;
    }

    if (details instanceof HTMLElement) {
      const selectedStatusOption = getSelectedStatusOption(select);
      const selectedStatusKind = getStatusOptionKind(selectedStatusOption);
      const selectedStatusColor = getStatusOptionColor(selectedStatusOption);
      Array.from(details.classList).forEach((className) => {
        if (
          (className.startsWith("status-") && className !== "status-inline-picker") ||
          (className.startsWith("priority-") && className !== "priority-inline-picker")
        ) {
          details.classList.remove(className);
        }
      });

      if (select.classList.contains("status-select") && selectedStatusKind) {
        details.classList.add(`status-${selectedStatusKind}`);
        applyStatusStyleVars(details, selectedStatusColor, selectedStatusKind);
      }
      if (select.classList.contains("priority-select") && select.value) {
        details.classList.add(`priority-${select.value}`);
      }
    }
  };

  const syncSelectColor = (select) => {
    if (!select) return;

    if (select.classList.contains("status-select")) {
      const selectedStatusOption = getSelectedStatusOption(select);
      const selectedStatusKind = getStatusOptionKind(selectedStatusOption);
      const selectedStatusColor = getStatusOptionColor(selectedStatusOption);
      Array.from(select.classList).forEach((className) => {
        if (className.startsWith("status-") && className !== "status-select") {
          select.classList.remove(className);
        }
      });
      if (selectedStatusKind) select.classList.add(`status-${selectedStatusKind}`);
      applyStatusStyleVars(select, selectedStatusColor, selectedStatusKind);
      syncStatusStepper(select);
      syncTaskRowStatusOverlay(select);
      syncInlineSelectPicker(select);
    }

    if (select.classList.contains("priority-select")) {
      Array.from(select.classList).forEach((className) => {
        if (className.startsWith("priority-") && className !== "priority-select") {
          select.classList.remove(className);
        }
      });
      if (select.value) select.classList.add(`priority-${select.value}`);
      syncInlineSelectPicker(select);

      const taskItem = select.closest("[data-task-item]");
      if (taskItem instanceof HTMLElement) {
        const groupSection = taskItem.closest("[data-task-group]");
        if (groupSection instanceof HTMLElement) {
          sortGroupTaskItemsByStatus(groupSection);
          syncGroupStatusDividers(groupSection);
        }
      }
    }
  };

  const getWorkspaceStatusRowKind = (row) => {
    if (!(row instanceof HTMLElement)) return "in_progress";
    const className = Array.from(row.classList).find(
      (entry) => entry.startsWith("status-") && entry !== "workspace-status-row"
    );
    return normalizeTaskStatusKind((className || "").replace(/^status-/, ""));
  };

  const isWorkspaceStatusColorInput = (input) =>
    input instanceof HTMLInputElement || input instanceof HTMLSelectElement;

  const workspaceStatusColorLabelFromControl = (control, color) => {
    if (!(control instanceof HTMLElement)) return color;
    const normalizedColor = String(color || "").trim().toUpperCase();
    const matchedOption = Array.from(
      control.querySelectorAll("[data-workspace-status-color-option]")
    ).find((option) => {
      if (!(option instanceof HTMLElement)) return false;
      return String(option.dataset.value || "").trim().toUpperCase() === normalizedColor;
    });
    const nextLabel = matchedOption instanceof HTMLElement ? String(matchedOption.dataset.label || "").trim() : "";
    return nextLabel || color;
  };

  const setWorkspaceStatusColorMenuOpen = (control, isOpen) => {
    if (!(control instanceof HTMLElement)) return;
    const trigger = control.querySelector("[data-workspace-status-color-trigger]");
    const menu = control.querySelector("[data-workspace-status-color-menu]");
    const nextState = Boolean(isOpen);
    control.classList.toggle("is-open", nextState);
    if (trigger instanceof HTMLElement) {
      trigger.setAttribute("aria-expanded", nextState ? "true" : "false");
    }
    if (menu instanceof HTMLElement) {
      menu.hidden = !nextState;
    }
  };

  const closeWorkspaceStatusColorMenus = (exceptControl = null) => {
    document.querySelectorAll("[data-workspace-status-color-control].is-open").forEach((control) => {
      if (!(control instanceof HTMLElement)) return;
      if (exceptControl instanceof HTMLElement && control === exceptControl) return;
      setWorkspaceStatusColorMenuOpen(control, false);
    });
  };

  const syncWorkspaceStatusColorControlState = (control, color) => {
    if (!(control instanceof HTMLElement)) return;
    const normalizedColor = String(color || "").trim().toUpperCase();
    const safeColor = normalizedColor || "#6EA5E9";
    const vars = getStatusStyleVars(safeColor, "in_progress");
    control.style.setProperty("--workspace-status-selected-color", safeColor);

    const currentSwatch = control.querySelector("[data-workspace-status-color-current-swatch]");
    if (currentSwatch instanceof HTMLElement) {
      currentSwatch.style.setProperty("--workspace-status-option-color", vars.color);
      currentSwatch.style.backgroundColor = vars.color;
      currentSwatch.style.boxShadow = "0 0 0 2px rgba(255, 255, 255, 0.86)";
    }

    const currentLabel = control.querySelector("[data-workspace-status-color-current-label]");
    if (currentLabel instanceof HTMLElement) {
      currentLabel.textContent = workspaceStatusColorLabelFromControl(control, safeColor);
    }

    control.querySelectorAll("[data-workspace-status-color-option]").forEach((option) => {
      if (!(option instanceof HTMLElement)) return;
      const optionColor = String(option.dataset.value || "").trim().toUpperCase();
      const selected = optionColor === safeColor;
      option.classList.toggle("is-selected", selected);
      option.setAttribute("aria-selected", selected ? "true" : "false");
    });
  };

  const getWorkspaceStatusColorInputFromControl = (control) => {
    if (!(control instanceof HTMLElement)) return null;

    const directInput = control.querySelector("[data-workspace-status-color-input]");
    if (isWorkspaceStatusColorInput(directInput)) {
      return directInput;
    }

    const scope = control.closest("[data-workspace-status-row], [data-workspace-status-create-row]");
    if (!(scope instanceof HTMLElement)) return null;

    const scopedInput = scope.querySelector("[data-workspace-status-color-input]");
    return isWorkspaceStatusColorInput(scopedInput) ? scopedInput : null;
  };

  const syncWorkspaceStatusRowColor = (input) => {
    if (!isWorkspaceStatusColorInput(input)) return;
    const scope = input.closest("[data-workspace-status-row], [data-workspace-status-create-row]");
    const row = input.closest("[data-workspace-status-row]");
    const statusKind =
      row instanceof HTMLElement
        ? getWorkspaceStatusRowKind(row)
        : normalizeTaskStatusKind(input.dataset.workspaceStatusColorKind || "in_progress");

    const color = normalizeStatusColorValue(input.value, statusKind);
    input.value = color;

    const colorControl =
      input.closest("[data-workspace-status-color-control]") ||
      (scope instanceof HTMLElement
        ? scope.querySelector("[data-workspace-status-color-control]")
        : null);
    if (colorControl instanceof HTMLElement) {
      syncWorkspaceStatusColorControlState(colorControl, color);
    }

    if (row instanceof HTMLElement) {
      applyStatusStyleVars(row, color, statusKind);
      const tone = row.querySelector("[data-workspace-status-tone]");
      if (tone instanceof HTMLElement) {
        const vars = getStatusStyleVars(color, statusKind);
        tone.style.backgroundColor = vars.color;
        tone.style.boxShadow = `0 0 0 3px rgba(${vars.rgb}, 0.14)`;
      }

      const hiddenInput = row.querySelector("[data-workspace-status-color-hidden]");
      if (hiddenInput instanceof HTMLInputElement) {
        hiddenInput.value = color;
      }
    }

    const form = input.closest(".workspace-statuses-form");
    syncWorkspaceStatusColorOptionAvailability(form);
  };

  const collectWorkspaceStatusRowColorCounts = (form) => {
    if (!(form instanceof HTMLFormElement)) {
      return new Map();
    }

    const counts = new Map();
    form.querySelectorAll("[data-workspace-status-row] [data-workspace-status-color-hidden]").forEach((field) => {
      if (!(field instanceof HTMLInputElement)) return;
      const color = String(field.value || "").trim().toUpperCase();
      if (!color) return;
      counts.set(color, (counts.get(color) || 0) + 1);
    });
    return counts;
  };

  const workspaceStatusColorLabelByValue = (form, color) => {
    if (!(form instanceof HTMLFormElement)) return color;
    const normalizedColor = String(color || "").trim().toUpperCase();
    if (!normalizedColor) return color;

    const matchedOption = Array.from(
      form.querySelectorAll("[data-workspace-status-color-option]")
    ).find((option) => {
      if (!(option instanceof HTMLElement)) return false;
      return String(option.dataset.value || "").trim().toUpperCase() === normalizedColor;
    });

    const label = matchedOption instanceof HTMLElement ? String(matchedOption.dataset.label || "").trim() : "";
    return label || normalizedColor;
  };

  const workspaceStatusDuplicateColors = (form) => {
    const counts = collectWorkspaceStatusRowColorCounts(form);
    return Array.from(counts.entries())
      .filter(([, amount]) => amount > 1)
      .map(([color]) => color);
  };

  const validateWorkspaceStatusUniqueColors = (form, { showMessage = false } = {}) => {
    if (!(form instanceof HTMLFormElement)) return true;
    const duplicatedColors = workspaceStatusDuplicateColors(form);
    if (!duplicatedColors.length) return true;

    if (showMessage) {
      const duplicatedLabels = duplicatedColors.map((color) =>
        workspaceStatusColorLabelByValue(form, color)
      );
      showClientFlash(
        "error",
        `Cada status precisa ter uma cor diferente. Cores repetidas: ${duplicatedLabels.join(", ")}.`
      );
    }
    return false;
  };

  const syncWorkspaceStatusColorOptionAvailability = (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const rowColorCounts = collectWorkspaceStatusRowColorCounts(form);

    form.querySelectorAll("[data-workspace-status-color-control]").forEach((control) => {
      if (!(control instanceof HTMLElement)) return;
      const input = getWorkspaceStatusColorInputFromControl(control);
      if (!isWorkspaceStatusColorInput(input)) return;

      const scope = control.closest("[data-workspace-status-row], [data-workspace-status-create-row]");
      const isStatusRow = scope instanceof HTMLElement && scope.hasAttribute("data-workspace-status-row");
      const currentColor = String(input.value || "").trim().toUpperCase();

      control.querySelectorAll("[data-workspace-status-color-option]").forEach((option) => {
        if (!(option instanceof HTMLButtonElement)) return;
        const optionColor = String(option.dataset.value || "").trim().toUpperCase();
        const usedCount = rowColorCounts.get(optionColor) || 0;
        const isUnavailable = optionColor !== "" && (isStatusRow
          ? optionColor !== currentColor && usedCount > 0
          : usedCount > 0);

        option.disabled = isUnavailable;
        option.classList.toggle("is-unavailable", isUnavailable);
        option.setAttribute("aria-disabled", isUnavailable ? "true" : "false");
        if (isUnavailable) {
          option.setAttribute("title", "Cor já usada em outro status");
        } else {
          option.removeAttribute("title");
        }
      });
    });
  };

  const syncWorkspaceStatusReviewToggles = (scope = document) => {
    const root = scope instanceof Element || scope instanceof Document ? scope : document;
    root.querySelectorAll(".workspace-statuses-form").forEach((form) => {
      if (!(form instanceof HTMLFormElement)) return;
      const valueField = form.querySelector("[data-workspace-status-review-value]");
      if (!(valueField instanceof HTMLInputElement)) return;

      const selectedKey = String(valueField.value || "").trim();
      form.querySelectorAll("[data-workspace-status-review-toggle]").forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) return;
        const statusKey = String(button.dataset.statusKey || "").trim();
        const active = selectedKey !== "" && selectedKey === statusKey;
        button.classList.toggle("is-active", active);
        button.setAttribute("aria-pressed", active ? "true" : "false");
        button.setAttribute(
          "title",
          active ? "Remover status de revisão" : "Definir como status de revisão"
        );
      });
    });
  };

  const clearWorkspaceStatusDropIndicators = () => {
    document
      .querySelectorAll(".workspace-status-row.is-drop-before, .workspace-status-row.is-drop-after")
      .forEach((row) => row.classList.remove("is-drop-before", "is-drop-after"));
  };

  const getWorkspaceStatusSortableRows = (list, excludeRow = null) => {
    if (!(list instanceof HTMLElement)) return [];
    return Array.from(list.querySelectorAll("[data-workspace-status-row]")).filter(
      (row) =>
        row instanceof HTMLElement &&
        row.dataset.workspaceStatusSortable === "true" &&
        row !== excludeRow
    );
  };

  const moveWorkspaceStatusRowByPointer = (list, pointerY) => {
    if (!(list instanceof HTMLElement)) return;
    const sortableRows = getWorkspaceStatusSortableRows(list, draggedWorkspaceStatusRow);
    const endRow = list.querySelector('[data-workspace-status-edge="end"]');
    const form = list.closest(".workspace-statuses-form");

    let nextRow = null;
    for (const row of sortableRows) {
      const rect = row.getBoundingClientRect();
      if (pointerY < rect.top + rect.height / 2) {
        nextRow = row;
        break;
      }
    }

    clearWorkspaceStatusDropIndicators();

    if (nextRow instanceof HTMLElement) {
      nextRow.classList.add("is-drop-before");
      if (draggedWorkspaceStatusRow !== nextRow.previousElementSibling) {
        list.insertBefore(draggedWorkspaceStatusRow, nextRow);
      }
      if (form instanceof HTMLFormElement) {
        syncWorkspaceStatusesSaveState(form);
      }
      return;
    }

    if (endRow instanceof HTMLElement) {
      endRow.classList.add("is-drop-before");
      if (draggedWorkspaceStatusRow !== endRow.previousElementSibling) {
        list.insertBefore(draggedWorkspaceStatusRow, endRow);
      }
      if (form instanceof HTMLFormElement) {
        syncWorkspaceStatusesSaveState(form);
      }
      return;
    }

    const lastSortableRow = sortableRows[sortableRows.length - 1];
    if (lastSortableRow instanceof HTMLElement) {
      lastSortableRow.classList.add("is-drop-after");
    }
    list.append(draggedWorkspaceStatusRow);
    if (form instanceof HTMLFormElement) {
      syncWorkspaceStatusesSaveState(form);
    }
  };

  const serializeWorkspaceStatusesForm = (form) => {
    if (!(form instanceof HTMLFormElement)) return "";

    const rows = Array.from(form.querySelectorAll("[data-workspace-status-row]"))
      .filter((row) => row instanceof HTMLElement)
      .map((row) => {
        const keyField = row.querySelector('input[name="status_keys[]"]');
        const labelField = row.querySelector('input[name="status_labels[]"]');
        const colorField = row.querySelector("[data-workspace-status-color-hidden]");
        const key = keyField instanceof HTMLInputElement ? String(keyField.value || "").trim() : "";
        const label =
          labelField instanceof HTMLInputElement ? String(labelField.value || "").trim() : "";
        const color =
          colorField instanceof HTMLInputElement ? String(colorField.value || "").trim().toUpperCase() : "";
        return `${key}::${label}::${color}`;
      });

    const reviewField = form.querySelector("[data-workspace-status-review-value]");
    const reviewKey =
      reviewField instanceof HTMLInputElement ? String(reviewField.value || "").trim() : "";

    return JSON.stringify({
      rows,
      reviewKey,
    });
  };

  const syncWorkspaceStatusesSaveState = (form) => {
    if (!(form instanceof HTMLFormElement)) return false;

    const saveButton = form.querySelector("[data-workspace-status-save-button]");
    if (!(saveButton instanceof HTMLButtonElement)) return false;

    const baseline = String(form.dataset.workspaceStatusesBaseline || "");
    const current = serializeWorkspaceStatusesForm(form);
    const isDirty = baseline !== "" && baseline !== current;
    const hasDuplicateColors = !validateWorkspaceStatusUniqueColors(form, { showMessage: false });

    form.dataset.workspaceStatusesDirty = isDirty ? "true" : "false";
    form.dataset.workspaceStatusesDuplicateColors = hasDuplicateColors ? "true" : "false";
    saveButton.disabled = !isDirty || hasDuplicateColors;
    saveButton.setAttribute("aria-disabled", saveButton.disabled ? "true" : "false");
    if (hasDuplicateColors) {
      saveButton.setAttribute("title", "Cada status precisa ter uma cor diferente.");
    } else {
      saveButton.removeAttribute("title");
    }
    return isDirty && !hasDuplicateColors;
  };

  const initializeWorkspaceStatusesForms = (scope = document) => {
    const root = scope instanceof Element || scope instanceof Document ? scope : document;
    root.querySelectorAll(".workspace-statuses-form").forEach((form) => {
      if (!(form instanceof HTMLFormElement)) return;
      syncWorkspaceStatusColorOptionAvailability(form);
      form.dataset.workspaceStatusesBaseline = serializeWorkspaceStatusesForm(form);
      syncWorkspaceStatusesSaveState(form);
    });
  };

  const clearWorkspaceStatusDraftFields = (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const newStatusLabelField = form.querySelector('input[name="new_status_label"]');
    if (newStatusLabelField instanceof HTMLInputElement) {
      newStatusLabelField.value = "";
    }
  };

  const priorityFlagGlyph = "\u2691";
  const priorityLabels = {
    low: "Baixa",
    medium: "Media",
    high: "Alta",
    urgent: "Urgente",
  };

  document
    .querySelectorAll(".status-select, .priority-select")
    .forEach(syncSelectColor);

  document
    .querySelectorAll("[data-workspace-status-color-input]")
    .forEach(syncWorkspaceStatusRowColor);
  syncWorkspaceStatusReviewToggles();
  initializeWorkspaceStatusesForms();

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;

    const workspaceStatusColorControl = target.closest("[data-workspace-status-color-control]");
    if (!(workspaceStatusColorControl instanceof HTMLElement)) {
      closeWorkspaceStatusColorMenus();
    }

    const workspaceStatusColorOption = target.closest("[data-workspace-status-color-option]");
    if (workspaceStatusColorOption instanceof HTMLButtonElement) {
      event.preventDefault();
      if (workspaceStatusColorOption.disabled || workspaceStatusColorOption.classList.contains("is-unavailable")) {
        showClientFlash("error", "Essa cor já esta sendo usada em outro status.");
        return;
      }
      const control = workspaceStatusColorOption.closest("[data-workspace-status-color-control]");
      const colorInput = getWorkspaceStatusColorInputFromControl(control);
      const nextColor = String(workspaceStatusColorOption.dataset.value || "").trim();

      if (!(control instanceof HTMLElement) || !isWorkspaceStatusColorInput(colorInput) || !nextColor) {
        return;
      }

      const trigger = control.querySelector("[data-workspace-status-color-trigger]");
      if (trigger instanceof HTMLButtonElement && trigger.disabled) {
        return;
      }

      colorInput.value = nextColor;
      syncWorkspaceStatusRowColor(colorInput);
      const form = colorInput.closest(".workspace-statuses-form");
      syncWorkspaceStatusesSaveState(form);
      setWorkspaceStatusColorMenuOpen(control, false);
      return;
    }

    const workspaceStatusColorTrigger = target.closest("[data-workspace-status-color-trigger]");
    if (workspaceStatusColorTrigger instanceof HTMLButtonElement) {
      event.preventDefault();
      const control = workspaceStatusColorTrigger.closest("[data-workspace-status-color-control]");
      if (!(control instanceof HTMLElement)) {
        return;
      }
      const form = control.closest(".workspace-statuses-form");
      syncWorkspaceStatusColorOptionAvailability(form);
      if (workspaceStatusColorTrigger.disabled || control.classList.contains("is-disabled")) {
        setWorkspaceStatusColorMenuOpen(control, false);
        return;
      }
      const shouldOpen = !control.classList.contains("is-open");
      closeWorkspaceStatusColorMenus(control);
      setWorkspaceStatusColorMenuOpen(control, shouldOpen);
      return;
    }

    const workspaceStatusReviewToggle = target.closest("[data-workspace-status-review-toggle]");
    if (workspaceStatusReviewToggle instanceof HTMLButtonElement) {
      if (workspaceStatusReviewToggle.disabled) return;

      const form = workspaceStatusReviewToggle.closest(".workspace-statuses-form");
      const valueField = form?.querySelector("[data-workspace-status-review-value]");
      if (!(form instanceof HTMLFormElement) || !(valueField instanceof HTMLInputElement)) {
        return;
      }

      const nextKey = String(workspaceStatusReviewToggle.dataset.statusKey || "").trim();
      valueField.value = String(valueField.value || "").trim() === nextKey ? "" : nextKey;
      syncWorkspaceStatusReviewToggles(form);
      syncWorkspaceStatusesSaveState(form);
      return;
    }

    const quantityStepButton = target.closest("[data-inventory-inline-quantity-step]");
    if (!(quantityStepButton instanceof HTMLButtonElement)) return;

    event.preventDefault();
    const quantityForm = quantityStepButton.closest("[data-inventory-inline-quantity-form]");
    if (!(quantityForm instanceof HTMLFormElement)) return;
    if (quantityForm.dataset.submitting === "1") return;

    const quantityInput = quantityForm.querySelector("[data-inventory-inline-quantity-input]");
    if (!(quantityInput instanceof HTMLInputElement)) return;

    const rawStep = Number.parseInt(quantityStepButton.dataset.step || "0", 10);
    if (!Number.isFinite(rawStep) || rawStep === 0) return;

    const normalizedCurrentValue = normalizeInventoryIntegerInput(quantityInput.value);
    const currentValue =
      normalizedCurrentValue === null ? 0 : Number.parseInt(normalizedCurrentValue, 10);
    const nextValue = Math.max(0, currentValue + rawStep);

    if (nextValue === currentValue && normalizedCurrentValue !== null) return;

    quantityInput.value = String(nextValue);
    quantityInput.dispatchEvent(new Event("change", { bubbles: true }));
  });

  document.addEventListener("change", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;

    const vaultLabelInput = target.closest("[data-vault-entry-label-input]");
    if (vaultLabelInput instanceof HTMLInputElement) {
      const renameForm = vaultLabelInput.closest("[data-vault-entry-name-form]");
      void submitVaultEntryNameForm(renameForm);
      return;
    }

    const inventoryInlineQuantityInput = target.closest("[data-inventory-inline-quantity-input]");
    if (inventoryInlineQuantityInput instanceof HTMLInputElement) {
      const quantityForm = inventoryInlineQuantityInput.closest(
        "[data-inventory-inline-quantity-form]"
      );
      if (!(quantityForm instanceof HTMLFormElement)) return;

      const normalizedQuantity = normalizeInventoryIntegerInput(inventoryInlineQuantityInput.value);
      if (normalizedQuantity === null) {
        inventoryInlineQuantityInput.value = inventoryInlineQuantityInput.defaultValue || "0";
        showClientFlash("error", "Use apenas numeros inteiros na quantidade.");
        return;
      }
      inventoryInlineQuantityInput.value = normalizedQuantity;
      void submitInventoryActionForm(quantityForm, {
        showSuccess: false,
        fallbackError: "Falha ao atualizar quantidade.",
      }).catch(() => {});
      return;
    }

    const select = target.closest(".status-select, .priority-select");
    if (select) {
      syncSelectColor(select);
    }

    const workspaceStatusColorInput = target.closest("[data-workspace-status-color-input]");
    if (isWorkspaceStatusColorInput(workspaceStatusColorInput)) {
      syncWorkspaceStatusRowColor(workspaceStatusColorInput);
      const form = workspaceStatusColorInput.closest(".workspace-statuses-form");
      syncWorkspaceStatusesSaveState(form);
      return;
    }

  });

  document.addEventListener("input", (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.matches(uppercaseRequiredInputSelector)) {
      applyFirstLetterUppercaseToInput(target);
    }
    if (isWorkspaceStatusColorInput(target) && target.matches("[data-workspace-status-color-input]")) {
      syncWorkspaceStatusRowColor(target);
      const form = target.closest(".workspace-statuses-form");
      syncWorkspaceStatusesSaveState(form);
      return;
    }
    if (target instanceof HTMLInputElement && target.matches('.workspace-statuses-form input[name="status_labels[]"]')) {
      const form = target.closest(".workspace-statuses-form");
      syncWorkspaceStatusesSaveState(form);
      return;
    }
    const taskDetailDescriptionEditorTarget = getClosestFromEventTarget(
      target,
      "[data-task-detail-edit-description-editor]"
    );
    if (taskDetailDescriptionEditorTarget instanceof HTMLElement) {
      if (
        event instanceof InputEvent &&
        event.inputType === "insertText" &&
        (event.data === " " || event.data === "\u00A0")
      ) {
        convertDashLineToListInTaskDetailEditor();
      }
      normalizeTaskDetailDescriptionEditorLists();
      syncTaskDetailDescriptionTextareaFromEditor();
      syncTaskDetailDescriptionToolbar();
      return;
    }

    const createTaskDescriptionEditorTarget = getClosestFromEventTarget(
      target,
      "[data-create-task-description-editor]"
    );
    if (createTaskDescriptionEditorTarget instanceof HTMLElement) {
      if (
        event instanceof InputEvent &&
        event.inputType === "insertText" &&
        (event.data === " " || event.data === "\u00A0")
      ) {
        convertDashLineToListInCreateTaskEditor();
      }
      normalizeDescriptionEditorLists(createTaskDescriptionEditor);
      syncCreateTaskDescriptionTextareaFromEditor();
      return;
    }

    if (!(target instanceof HTMLTextAreaElement)) return;
    if (target.matches("[data-task-detail-edit-description]")) {
      autoResizeTextarea(target);
      return;
    }

    if (target.matches("[data-task-detail-edit-links]")) {
      syncReferenceTextareaLayout(target);
      return;
    }

    if (target.matches("[data-create-task-links]")) {
      syncReferenceTextareaLayout(target);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeWorkspaceStatusColorMenus();
    }

    const target = event.target;
    const taskDetailDescriptionEditorTarget = getClosestFromEventTarget(
      target,
      "[data-task-detail-edit-description-editor]"
    );
    if (taskDetailDescriptionEditorTarget instanceof HTMLElement) {
      if (event.key === " " && convertDashLineToListInTaskDetailEditor()) {
        event.preventDefault();
        return;
      }

      if (!(event.ctrlKey || event.metaKey) || event.altKey) {
        return;
      }

      const key = event.key.toLowerCase();
      if (key === "b") {
        event.preventDefault();
        applyTaskDetailDescriptionFormat("bold");
        return;
      }
      if (key === "i") {
        event.preventDefault();
        applyTaskDetailDescriptionFormat("italic");
      }
      return;
    }

    const createTaskDescriptionEditorTarget = getClosestFromEventTarget(
      target,
      "[data-create-task-description-editor]"
    );
    if (createTaskDescriptionEditorTarget instanceof HTMLElement) {
      if (event.key === " " && convertDashLineToListInCreateTaskEditor()) {
        event.preventDefault();
        return;
      }

      if (!(event.ctrlKey || event.metaKey) || event.altKey) {
        return;
      }

      const key = event.key.toLowerCase();
      if (key === "b") {
        event.preventDefault();
        applyCreateTaskDescriptionFormat("bold");
        return;
      }
      if (key === "i") {
        event.preventDefault();
        applyCreateTaskDescriptionFormat("italic");
      }
      return;
    }

    if (target instanceof HTMLTextAreaElement && target.matches("[data-task-detail-edit-links]")) {
      if (event.key !== "Enter" || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey) {
        return;
      }

      const value = target.value || "";
      const selectionStart = Number.isFinite(target.selectionStart) ? target.selectionStart : 0;
      const lineStart = value.lastIndexOf("\n", Math.max(0, selectionStart - 1)) + 1;
      const rawLineEnd = value.indexOf("\n", selectionStart);
      const lineEnd = rawLineEnd === -1 ? value.length : rawLineEnd;
      const currentLine = value.slice(lineStart, lineEnd).trim();

      if (currentLine === "") {
        event.preventDefault();
      }
      return;
    }

    if (target instanceof HTMLTextAreaElement && target.matches("[data-create-task-links]")) {
      if (event.key !== "Enter" || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey) {
        return;
      }

      const value = target.value || "";
      const selectionStart = Number.isFinite(target.selectionStart) ? target.selectionStart : 0;
      const lineStart = value.lastIndexOf("\n", Math.max(0, selectionStart - 1)) + 1;
      const rawLineEnd = value.indexOf("\n", selectionStart);
      const lineEnd = rawLineEnd === -1 ? value.length : rawLineEnd;
      const currentLine = value.slice(lineStart, lineEnd).trim();

      if (currentLine === "") {
        event.preventDefault();
      }
      return;
    }

    if (!(target instanceof HTMLTextAreaElement)) return;
    if (!target.matches("[data-task-autosave-form] textarea[name=\"description\"]")) {
      return;
    }
    if (!(event.ctrlKey || event.metaKey) || event.altKey || event.key.toLowerCase() !== "b") {
      return;
    }

    event.preventDefault();
    wrapSelectionWithBoldMarkdown(target);
  });

  document.addEventListener("dblclick", (event) => {
    if (event.button !== 0) return;
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;

    if (
      createTaskDescriptionEditor instanceof HTMLElement &&
      createTaskDescriptionEditor.contains(target)
    ) {
      window.setTimeout(() => {
        applyDescriptionBoldOnDoubleClick(createTaskDescriptionEditor, createTaskDescription);
      }, 0);
      return;
    }

    if (
      taskDetailEditDescriptionEditor instanceof HTMLElement &&
      taskDetailEditDescriptionEditor.contains(target)
    ) {
      window.setTimeout(() => {
        const applied = applyDescriptionBoldOnDoubleClick(
          taskDetailEditDescriptionEditor,
          taskDetailEditDescription
        );
        if (applied) {
          syncTaskDetailDescriptionToolbar();
        }
      }, 0);
    }
  });

  const toLocalIsoDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };

  const formatTaskHumanDate = (date, fallback = "") => {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return fallback || "";
    }

    const monthNames = [
      "Janeiro",
      "Fevereiro",
      "Março",
      "Abril",
      "Maio",
      "Junho",
      "Julho",
      "Agosto",
      "Setembro",
      "Outubro",
      "Novembro",
      "Dezembro",
    ];

    const day = date.getDate();
    const monthLabel = monthNames[date.getMonth()] || String(date.getMonth() + 1);
    let label = `${day} de ${monthLabel}`;

    if (date.getFullYear() !== new Date().getFullYear()) {
      label += ` de ${date.getFullYear()}`;
    }

    return label;
  };

  const dueDateMeta = (value) => {
    const raw = (value || "").trim();
    if (!raw) {
      return {
        display: "Sem prazo",
        title: "Sem prazo",
        isRelative: false,
      };
    }

    const today = new Date();
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);

    const todayIso = toLocalIsoDate(today);
    const tomorrowIso = toLocalIsoDate(tomorrow);

    const parsed = new Date(`${raw}T00:00:00`);
    const formatted = formatTaskHumanDate(parsed, raw);

    if (raw === todayIso) {
      return {
        display: "Hoje",
        title: `Hoje (${formatted})`,
        isRelative: true,
      };
    }

    if (raw === tomorrowIso) {
      return {
        display: "Amanhã",
        title: `Amanhã (${formatted})`,
        isRelative: true,
      };
    }

    return {
      display: formatted,
      title: formatted,
      isRelative: false,
    };
  };

  const autoResizeTextarea = (textarea) => {
    if (!(textarea instanceof HTMLTextAreaElement)) return;
    textarea.style.height = "0px";
    textarea.style.height = `${textarea.scrollHeight}px`;
  };

  const syncReferenceTextareaLayout = (textarea) => {
    if (!(textarea instanceof HTMLTextAreaElement)) return;

    const value = String(textarea.value || "").replace(/\r/g, "");
    const lines = value.split("\n");
    const lineCount = Math.max(1, lines.length);
    const filledLineCount = lines.filter((line) => line.trim() !== "").length;
    const targetRows = Math.max(lineCount, filledLineCount > 0 ? filledLineCount + 1 : 1);

    textarea.rows = targetRows;
    textarea.classList.toggle("has-multiple-rows", targetRows > 1);
    textarea.style.height = "0px";
    let targetHeight = textarea.scrollHeight;
    const style = window.getComputedStyle(textarea);
    const lineHeight = Number.parseFloat(style.lineHeight) || 20;
    const verticalPadding =
      (Number.parseFloat(style.paddingTop) || 0) + (Number.parseFloat(style.paddingBottom) || 0);
    const wrapsToMultipleLines = targetHeight > lineHeight + verticalPadding + 6;
    textarea.classList.toggle("has-multiple-rows", targetRows > 1 || wrapsToMultipleLines);
    textarea.style.height = "0px";
    targetHeight = textarea.scrollHeight;
    textarea.style.height = `${targetHeight}px`;
  };

  const escapeHtml = (value) =>
    String(value || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#39;");

  const formatTaskDescriptionInlineHtml = (value) => {
    const withBold = escapeHtml(value).replace(/\*\*([^*\n]+)\*\*/g, "<strong>$1</strong>");
    return withBold.replace(/_([^_\n]+)_/g, "<em>$1</em>");
  };

  const isTaskDescriptionSeparatorLine = (value) => /^-{3,}$/.test(String(value || "").trim());
  const taskDescriptionListLinePattern = /^(?:-\s+|\u2022\s*)(.+)$/;

  const getTaskDescriptionListItemText = (value) => {
    const line = String(value || "").trim();
    if (!line || isTaskDescriptionSeparatorLine(line)) {
      return null;
    }

    const match = line.match(taskDescriptionListLinePattern);
    if (!match) {
      return null;
    }

    const itemText = String(match[1] || "").trim();
    return itemText || null;
  };

  const formatTaskDescriptionHtml = (value) => {
    const lines = String(value || "").replace(/\r/g, "").split("\n");
    const parts = [];
    const listItems = [];

    const flushList = () => {
      if (!listItems.length) return;
      parts.push(
        `<ul class="task-detail-description-list">${listItems
          .map((item) => `<li>${formatTaskDescriptionInlineHtml(item)}</li>`)
          .join("")}</ul>`
      );
      listItems.length = 0;
    };

    lines.forEach((rawLine) => {
      const line = rawLine.trim();
      if (!line) {
        flushList();
        return;
      }

      if (isTaskDescriptionSeparatorLine(line)) {
        flushList();
        parts.push('<hr class="task-description-divider">');
        return;
      }

      const listItemText = getTaskDescriptionListItemText(line);
      if (listItemText) {
        listItems.push(listItemText);
        return;
      }

      flushList();
      parts.push(`<p>${formatTaskDescriptionInlineHtml(line)}</p>`);
    });

    flushList();
    return parts.join("");
  };

  const normalizeTaskTitleTagValue = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "";
    const limited = raw.slice(0, 40);
    return forceFirstLetterUppercase(limited).trim();
  };

  const syncTaskTitleTagBadge = (taskItem, titleTag, titleTagColor = "") => {
    if (!(taskItem instanceof HTMLElement)) return;
    const normalizedTag = normalizeTaskTitleTagValue(titleTag);
    const field = taskItem.querySelector("[data-task-title-tag]");
    if (field instanceof HTMLInputElement) {
      field.value = normalizedTag;
    }
    const colorField = taskItem.querySelector("[data-task-title-tag-color]");
    const resolvedColor = normalizedTag
      ? resolveTaskTitleTagColor(
          normalizedTag,
          titleTagColor || (colorField instanceof HTMLInputElement ? colorField.value || "" : "")
        )
      : normalizeTaskTitleTagColorValue(titleTagColor, taskTitleTagDefaultColor);
    if (colorField instanceof HTMLInputElement) {
      colorField.value = resolvedColor;
    }

    const badge = taskItem.querySelector("[data-task-title-tag-badge]");
    if (!(badge instanceof HTMLElement)) return;
    if (!normalizedTag) {
      badge.hidden = true;
      badge.textContent = "";
      paintTagColorSwatch(badge, resolvedColor, false);
      return;
    }

    badge.hidden = false;
    badge.textContent = normalizedTag;
    paintTagColorSwatch(badge, resolvedColor, true);
  };

  const syncTaskDetailViewTitleTag = (titleTag, titleTagColor = "") => {
    if (!(taskDetailViewTitleTag instanceof HTMLElement)) return;
    const normalizedTag = normalizeTaskTitleTagValue(titleTag);
    if (!normalizedTag) {
      taskDetailViewTitleTag.hidden = true;
      taskDetailViewTitleTag.textContent = "";
      paintTagColorSwatch(taskDetailViewTitleTag, titleTagColor, false);
      return;
    }

    const resolvedColor = resolveTaskTitleTagColor(normalizedTag, titleTagColor);
    taskDetailViewTitleTag.hidden = false;
    taskDetailViewTitleTag.textContent = normalizedTag;
    paintTagColorSwatch(taskDetailViewTitleTag, resolvedColor, true);
  };

  const buildActiveTaskRevisionStack = (history = []) => {
    const stack = [];
    const orderedEntries = Array.isArray(history) ? [...history].reverse() : [];

    orderedEntries.forEach((entry) => {
      const eventType = String(entry?.event_type || "").trim();
      const payload = entry?.payload || {};

      if (eventType === "revision_requested") {
        const previousDescription = String(payload?.previous_description || "").trim();
        const newDescription = String(payload?.new_description || "").trim();
        if (!previousDescription || !newDescription || previousDescription === newDescription) {
          return;
        }

        stack.push({
          previousDescription,
          newDescription,
          createdAt: String(entry?.created_at || "").trim(),
          actorName: String(entry?.actor_name || "").trim(),
        });
        return;
      }

      if (eventType !== "revision_removed") {
        return;
      }

      const removedDescription = String(payload?.removed_description || "").trim();
      const restoredDescription = String(payload?.restored_description || "").trim();
      if (!removedDescription) {
        return;
      }

      for (let index = stack.length - 1; index >= 0; index -= 1) {
        const candidate = stack[index];
        const matchesRemoved = candidate.newDescription === removedDescription;
        const matchesRestored =
          !restoredDescription || candidate.previousDescription === restoredDescription;
        if (!matchesRemoved || !matchesRestored) {
          continue;
        }

        stack.splice(index, 1);
        break;
      }
    });

    return stack;
  };

  const collectTaskDescriptionRevisions = (description = "", history = []) => {
    const currentDescription = String(description || "").trim();
    const revisions = [];

    if (currentDescription) {
      revisions.push({
        kind: "current",
        text: currentDescription,
      });
    }

    const activeStack = buildActiveTaskRevisionStack(history);
    if (!currentDescription || !activeStack.length) {
      return revisions;
    }

    let chainCurrentDescription = currentDescription;
    const previousRevisions = [];

    for (let index = activeStack.length - 1; index >= 0; index -= 1) {
      const revision = activeStack[index];
      if (revision.newDescription !== chainCurrentDescription) {
        continue;
      }

      previousRevisions.push({
        kind: "previous",
        text: revision.previousDescription,
        createdAt: revision.createdAt,
        actorName: revision.actorName,
      });
      chainCurrentDescription = revision.previousDescription;
    }

    revisions.push(...previousRevisions);
    return revisions;
  };

  const hasActiveTaskRevisionRequest = ({ description = "", history = [] } = {}) => {
    const currentDescription = String(description || "").trim();
    if (!currentDescription) return false;

    const activeStack = buildActiveTaskRevisionStack(history);
    if (!activeStack.length) return false;

    const latestActiveRevision = activeStack[activeStack.length - 1];
    return String(latestActiveRevision?.newDescription || "").trim() === currentDescription;
  };

  const renderTaskDetailDescriptionView = ({ description = "", history = [] } = {}) => {
    if (!(taskDetailViewDescription instanceof HTMLElement)) return;

    const currentDescription = String(description || "").trim();
    if (currentDescription) {
      taskDetailViewDescription.innerHTML = formatTaskDescriptionHtml(currentDescription);
      taskDetailViewDescription.classList.remove("is-empty");
    } else {
      taskDetailViewDescription.textContent = "Sem descrição.";
      taskDetailViewDescription.classList.add("is-empty");
    }

    if (!(taskDetailViewDescriptionVersions instanceof HTMLElement)) return;
    taskDetailViewDescriptionVersions.innerHTML = "";

    const revisions = collectTaskDescriptionRevisions(currentDescription, history);
    const previousRevisions = revisions.filter((item) => item.kind === "previous");
    if (!previousRevisions.length) {
      taskDetailViewDescriptionVersions.hidden = true;
      return;
    }

    previousRevisions.forEach((revision, index) => {
      const details = document.createElement("details");
      details.className = "task-detail-description-version";

      const summary = document.createElement("summary");
      const summaryDate = formatHistoryDateTime(revision.createdAt || "");
      const summaryActor = revision.actorName ? ` · ${revision.actorName}` : "";
      summary.textContent = summaryDate
        ? `Descrição anterior ${index + 1} · ${summaryDate}${summaryActor}`
        : `Descrição anterior ${index + 1}`;

      const body = document.createElement("div");
      body.className = "task-detail-description-version-body";
      body.innerHTML = formatTaskDescriptionHtml(revision.text);

      details.append(summary, body);
      taskDetailViewDescriptionVersions.append(details);
    });

    taskDetailViewDescriptionVersions.hidden = false;
  };

  const wrapSelectionWithBoldMarkdown = (textarea) => {
    if (!(textarea instanceof HTMLTextAreaElement)) return;
    const start = Number.isFinite(textarea.selectionStart) ? textarea.selectionStart : 0;
    const end = Number.isFinite(textarea.selectionEnd) ? textarea.selectionEnd : start;
    const selected = textarea.value.slice(start, end);

    if (selected) {
      textarea.setRangeText(`**${selected}**`, start, end, "end");
      textarea.setSelectionRange(start + 2, start + 2 + selected.length);
    } else {
      textarea.setRangeText("****", start, end, "end");
      textarea.setSelectionRange(start + 2, start + 2);
    }

    autoResizeTextarea(textarea);
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
  };

  const normalizeDescriptionEditorLists = (editor) => {
    if (!(editor instanceof HTMLElement)) return;
    editor.querySelectorAll("ul").forEach((list) => {
      list.classList.add("task-detail-description-list");
    });
  };

  const syncDescriptionEditorFromTextarea = (textarea, editor) => {
    if (!(textarea instanceof HTMLTextAreaElement) || !(editor instanceof HTMLElement)) return;
    const text = String(textarea.value || "");
    if (!text.trim()) {
      editor.innerHTML = "";
      return;
    }

    editor.innerHTML = formatTaskDescriptionHtml(text);
    normalizeDescriptionEditorLists(editor);
  };

  const descriptionInlineNodeToText = (node) => {
    if (node.nodeType === Node.TEXT_NODE) {
      return node.textContent || "";
    }

    if (!(node instanceof HTMLElement)) {
      return "";
    }

    if (node.tagName === "BR") {
      return "\n";
    }

    const inner = Array.from(node.childNodes)
      .map((child) => descriptionInlineNodeToText(child))
      .join("");

    if (!inner) {
      return "";
    }

    if (node.tagName === "STRONG" || node.tagName === "B") {
      return `**${inner}**`;
    }

    if (node.tagName === "EM" || node.tagName === "I") {
      return `_${inner}_`;
    }

    return inner;
  };

  const descriptionStructuredBlockTags = new Set([
    "BLOCKQUOTE",
    "DIV",
    "HR",
    "LI",
    "OL",
    "P",
    "PRE",
    "UL",
  ]);

  const isDescriptionStructuredBlockNode = (node) =>
    node instanceof HTMLElement && descriptionStructuredBlockTags.has(node.tagName);

  const descriptionDirectInlineText = (element) =>
    Array.from(element.childNodes)
      .filter((child) => !isDescriptionStructuredBlockNode(child))
      .map((child) => descriptionInlineNodeToText(child))
      .join("");

  const descriptionListItemToLines = (item) => {
    const lines = [];
    const inlineText = descriptionDirectInlineText(item).replace(/\s+/g, " ").trim();
    if (inlineText) {
      lines.push(`- ${inlineText}`);
    }

    Array.from(item.childNodes)
      .filter((child) => isDescriptionStructuredBlockNode(child))
      .forEach((child) => {
        if (child instanceof HTMLElement && child.tagName === "LI") return;
        const childLines = descriptionBlockToLines(child).filter((line) => line.trim() !== "");
        if (child instanceof HTMLElement && (child.tagName === "UL" || child.tagName === "OL")) {
          lines.push(...childLines);
          return;
        }

        childLines.forEach((line) => {
          const trimmed = line.trim();
          lines.push(isTaskDescriptionSeparatorLine(trimmed) ? trimmed : `- ${trimmed}`);
        });
      });

    return lines;
  };

  const descriptionBlockToLines = (node) => {
    if (node.nodeType === Node.TEXT_NODE) {
      return String(node.textContent || "").split("\n");
    }

    if (!(node instanceof HTMLElement)) {
      return [];
    }

    if (node.tagName === "UL" || node.tagName === "OL") {
      const lines = [];
      Array.from(node.children)
        .filter((child) => child instanceof HTMLElement && child.tagName === "LI")
        .forEach((item) => lines.push(...descriptionListItemToLines(item)));

      return lines;
    }

    if (node.tagName === "HR") {
      return ["---"];
    }

    const hasStructuredChildren = Array.from(node.childNodes).some((child) =>
      isDescriptionStructuredBlockNode(child)
    );

    if (hasStructuredChildren) {
      const lines = [];
      const inlinePieces = [];
      const flushInlinePieces = () => {
        const inlineText = inlinePieces.join("");
        inlinePieces.length = 0;
        if (!inlineText) return;
        inlineText.split("\n").forEach((line) => {
          lines.push(line.trimEnd());
        });
      };

      Array.from(node.childNodes).forEach((child) => {
        if (isDescriptionStructuredBlockNode(child)) {
          flushInlinePieces();
          lines.push(...descriptionBlockToLines(child));
          return;
        }

        inlinePieces.push(descriptionInlineNodeToText(child));
      });

      flushInlinePieces();
      return lines;
    }

    return descriptionInlineNodeToText(node)
      .split("\n")
      .map((line) => line.trimEnd());
  };

  const descriptionTextFromEditor = (editor) => {
    if (!(editor instanceof HTMLElement)) return "";
    normalizeDescriptionEditorLists(editor);

    const rawLines = [];
    Array.from(editor.childNodes).forEach((node) => {
      rawLines.push(...descriptionBlockToLines(node).map((line) => line.replace(/\u00a0/g, " ")));
    });

    const lines = [];
    let previousBlank = false;
    rawLines.forEach((line) => {
      const isBlank = line.trim() === "";
      if (isBlank) {
        if (!previousBlank) {
          lines.push("");
        }
      } else {
        lines.push(line);
      }
      previousBlank = isBlank;
    });

    while (lines.length && lines[0].trim() === "") {
      lines.shift();
    }
    while (lines.length && lines[lines.length - 1].trim() === "") {
      lines.pop();
    }

    return lines.join("\n");
  };

  const syncDescriptionTextareaFromEditor = (textarea, editor) => {
    if (!(textarea instanceof HTMLTextAreaElement)) return;
    textarea.value = descriptionTextFromEditor(editor);
  };

  const getDescriptionSelectionRange = (editor) => {
    if (!(editor instanceof HTMLElement)) return null;
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) return null;

    const range = selection.getRangeAt(0);
    if (!editor.contains(range.startContainer) || !editor.contains(range.endContainer)) return null;

    return range;
  };

  const pointInsideRect = (rect, clientX, clientY) =>
    clientX >= rect.left &&
    clientX <= rect.right &&
    clientY >= rect.top &&
    clientY <= rect.bottom;

  const selectionRangeContainsPoint = (range, clientX, clientY) => {
    const rects = Array.from(range.getClientRects());
    if (!rects.length) {
      const bounds = range.getBoundingClientRect();
      if (bounds.width <= 0 || bounds.height <= 0) return false;
      return pointInsideRect(bounds, clientX, clientY);
    }

    return rects.some((rect) => pointInsideRect(rect, clientX, clientY));
  };

  const collapseDescriptionSelectionAtPoint = (editor, clientX, clientY) => {
    if (!(editor instanceof HTMLElement)) return;
    const selection = window.getSelection();
    if (!selection) return;

    let nextRange = null;

    if (typeof document.caretRangeFromPoint === "function") {
      nextRange = document.caretRangeFromPoint(clientX, clientY);
    } else if (typeof document.caretPositionFromPoint === "function") {
      const caret = document.caretPositionFromPoint(clientX, clientY);
      if (caret && caret.offsetNode) {
        const range = document.createRange();
        range.setStart(caret.offsetNode, caret.offset);
        range.collapse(true);
        nextRange = range;
      }
    }

    if (!nextRange) return;
    if (!editor.contains(nextRange.startContainer)) return;

    selection.removeAllRanges();
    selection.addRange(nextRange);
  };

  const positionDescriptionToolbar = (wrap, toolbar, range) => {
    if (!(wrap instanceof HTMLElement) || !(toolbar instanceof HTMLElement)) return;
    const selectionRect = range.getBoundingClientRect();
    if (selectionRect.width <= 0 && selectionRect.height <= 0) return;

    const wrapRect = wrap.getBoundingClientRect();
    const toolbarRect = toolbar.getBoundingClientRect();
    const margin = 8;

    const centerX = selectionRect.left + selectionRect.width / 2;
    const rawLeft = centerX - wrapRect.left - toolbarRect.width / 2;
    const maxLeft = Math.max(margin, wrapRect.width - toolbarRect.width - margin);
    const left = Math.min(Math.max(rawLeft, margin), maxLeft);

    let top = selectionRect.top - wrapRect.top - toolbarRect.height - 10;
    if (top < margin) {
      const rawBottomTop = selectionRect.bottom - wrapRect.top + 10;
      const maxTop = Math.max(margin, wrapRect.height - toolbarRect.height - margin);
      top = Math.min(Math.max(rawBottomTop, margin), maxTop);
    }

    toolbar.style.left = `${Math.round(left)}px`;
    toolbar.style.top = `${Math.round(top)}px`;
  };

  const setSelectionAtElementStart = (element) => {
    const selection = window.getSelection();
    if (!selection) return;
    const range = document.createRange();
    range.selectNodeContents(element);
    range.collapse(true);
    selection.removeAllRanges();
    selection.addRange(range);
  };

  const setSelectionAtElementEnd = (element) => {
    const selection = window.getSelection();
    if (!selection) return;
    const range = document.createRange();
    range.selectNodeContents(element);
    range.collapse(false);
    selection.removeAllRanges();
    selection.addRange(range);
  };

  const applyDescriptionFormat = (editor, textarea, format) => {
    if (!(editor instanceof HTMLElement)) return;
    const range = getDescriptionSelectionRange(editor);
    if (!range) return;

    const command = format === "italic" ? "italic" : "bold";
    editor.focus();
    document.execCommand(command, false);
    normalizeDescriptionEditorLists(editor);
    syncDescriptionTextareaFromEditor(textarea, editor);
  };

  const applyDescriptionBoldOnDoubleClick = (editor, textarea) => {
    if (!(editor instanceof HTMLElement)) return false;
    const range = getDescriptionSelectionRange(editor);
    if (!range || range.collapsed) return false;
    applyDescriptionFormat(editor, textarea, "bold");
    return true;
  };

  const convertDashLineToListInEditor = (editor, textarea) => {
    if (!(editor instanceof HTMLElement)) return false;

    const range = getDescriptionSelectionRange(editor);
    if (!range || !range.collapsed) return false;

    let block =
      range.startContainer instanceof HTMLElement
        ? range.startContainer
        : range.startContainer.parentElement;

    while (block && block !== editor && !["P", "DIV", "LI"].includes(block.tagName)) {
      block = block.parentElement;
    }

    const blockText = block
      ? (block.textContent || "").replace(/\u00a0/g, " ").trim()
      : (editor.textContent || "").replace(/\u00a0/g, " ").trim();

    if (blockText !== "-" || (block && block.tagName === "LI")) {
      return false;
    }

    editor.focus();
    document.execCommand("insertUnorderedList", false);
    normalizeDescriptionEditorLists(editor);

    const selection = window.getSelection();
    const node = selection?.anchorNode || null;
    const currentLi =
      node instanceof HTMLElement ? node.closest("li") : node?.parentElement?.closest("li");

    if (currentLi instanceof HTMLElement) {
      const lineText = (currentLi.textContent || "").replace(/\u00a0/g, " ").trim();
      if (lineText === "-") {
        currentLi.innerHTML = "<br>";
        setSelectionAtElementStart(currentLi);
      }
    }

    syncDescriptionTextareaFromEditor(textarea, editor);
    return true;
  };

  const insertDescriptionDivider = (editor, textarea) => {
    if (!(editor instanceof HTMLElement)) return;
    editor.focus();

    const range = getDescriptionSelectionRange(editor);
    let referenceBlock =
      range?.startContainer instanceof HTMLElement
        ? range.startContainer
        : range?.startContainer?.parentElement || null;

    while (
      referenceBlock &&
      referenceBlock !== editor &&
      !["P", "DIV", "LI", "UL", "OL"].includes(referenceBlock.tagName)
    ) {
      referenceBlock = referenceBlock.parentElement;
    }

    if (referenceBlock instanceof HTMLElement && referenceBlock.tagName === "LI") {
      const parentList = referenceBlock.closest("ul, ol");
      if (parentList instanceof HTMLElement && editor.contains(parentList)) {
        referenceBlock = parentList;
      }
    }

    const separator = document.createElement("hr");
    separator.className = "task-description-divider";
    const paragraph = document.createElement("p");
    paragraph.append(document.createElement("br"));

    if (
      referenceBlock instanceof HTMLElement &&
      referenceBlock.parentNode instanceof Node &&
      referenceBlock !== editor
    ) {
      referenceBlock.parentNode.insertBefore(paragraph, referenceBlock.nextSibling);
      referenceBlock.parentNode.insertBefore(separator, paragraph);
    } else {
      editor.append(separator, paragraph);
    }

    setSelectionAtElementStart(paragraph);
    normalizeDescriptionEditorLists(editor);
    syncDescriptionTextareaFromEditor(textarea, editor);
  };

  const normalizeTaskDetailDescriptionEditorLists = () => {
    normalizeDescriptionEditorLists(taskDetailEditDescriptionEditor);
  };

  const syncTaskDetailDescriptionEditorFromTextarea = () => {
    syncDescriptionEditorFromTextarea(taskDetailEditDescription, taskDetailEditDescriptionEditor);
  };

  const taskDetailDescriptionTextFromEditor = () =>
    descriptionTextFromEditor(taskDetailEditDescriptionEditor);

  const syncTaskDetailDescriptionTextareaFromEditor = () => {
    syncDescriptionTextareaFromEditor(taskDetailEditDescription, taskDetailEditDescriptionEditor);
  };

  const getTaskDetailDescriptionSelectionRange = () =>
    getDescriptionSelectionRange(taskDetailEditDescriptionEditor);

  const collapseTaskDetailSelectionAtPoint = (clientX, clientY) => {
    collapseDescriptionSelectionAtPoint(taskDetailEditDescriptionEditor, clientX, clientY);
  };

  const syncTaskDetailDescriptionToolbar = () => {
    if (!(taskDetailEditDescriptionToolbar instanceof HTMLElement)) return;
    taskDetailEditDescriptionToolbar.hidden = false;
  };

  const applyTaskDetailDescriptionFormat = (format) => {
    applyDescriptionFormat(taskDetailEditDescriptionEditor, taskDetailEditDescription, format);
    syncTaskDetailDescriptionToolbar();
  };

  const convertDashLineToListInTaskDetailEditor = () => {
    const converted = convertDashLineToListInEditor(
      taskDetailEditDescriptionEditor,
      taskDetailEditDescription
    );
    if (converted) {
      syncTaskDetailDescriptionToolbar();
    }
    return converted;
  };

  const insertTaskDetailDescriptionDivider = () => {
    insertDescriptionDivider(taskDetailEditDescriptionEditor, taskDetailEditDescription);
    syncTaskDetailDescriptionToolbar();
  };

  const syncCreateTaskDescriptionEditorFromTextarea = () => {
    syncDescriptionEditorFromTextarea(createTaskDescription, createTaskDescriptionEditor);
  };

  const syncCreateTaskDescriptionTextareaFromEditor = () => {
    syncDescriptionTextareaFromEditor(createTaskDescription, createTaskDescriptionEditor);
  };

  const applyCreateTaskDescriptionFormat = (format) => {
    applyDescriptionFormat(createTaskDescriptionEditor, createTaskDescription, format);
  };

  const convertDashLineToListInCreateTaskEditor = () =>
    convertDashLineToListInEditor(createTaskDescriptionEditor, createTaskDescription);

  const insertCreateTaskDescriptionDivider = () => {
    insertDescriptionDivider(createTaskDescriptionEditor, createTaskDescription);
  };

  const maxReferenceItems = 20;
  const maxReferenceImageChars = 2_000_000;
  const maxReferenceImageTitleChars = 80;

  const parseReferenceRawList = (value) => {
    if (Array.isArray(value)) {
      return value;
    }

    const raw = String(value || "").trim();
    if (!raw) {
      return [];
    }

    try {
      const decoded = JSON.parse(raw);
      if (Array.isArray(decoded)) {
        return decoded;
      }
    } catch (_error) {
      // Fallback to line-by-line parsing.
    }

    return raw.split(/\r?\n/);
  };

  const normalizeHttpReference = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return null;

    const hasExplicitScheme = /^[a-z][a-z0-9+.-]*:\/\//i.test(raw);
    const candidate = hasExplicitScheme ? raw : `https://${raw}`;

    try {
      const parsed = new URL(candidate);
      if (!["http:", "https:"].includes(parsed.protocol)) {
        return null;
      }
      return parsed.toString();
    } catch (_error) {
      return null;
    }
  };

  const normalizeImageReference = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return null;

    if (/^data:image\//i.test(raw)) {
      const compact = raw.replace(/\s+/g, "");
      if (!/^data:image\/[a-z0-9.+-]+;base64,[a-z0-9+/]+=*$/i.test(compact)) {
        return null;
      }
      if (compact.length > maxReferenceImageChars) {
        return null;
      }
      return compact;
    }

    return normalizeHttpReference(raw);
  };

  const normalizeReferenceImageTitle = (value) =>
    String(value || "")
      .replace(/\s+/g, " ")
      .trim()
      .slice(0, maxReferenceImageTitleChars);

  const normalizeGoogleDriveFileId = (value) => {
    const raw = String(value || "").trim();
    return /^[A-Za-z0-9_-]{6,220}$/.test(raw) ? raw : "";
  };

  const normalizeReferenceMediaName = (value) =>
    String(value || "")
      .replace(/\s+/g, " ")
      .trim()
      .slice(0, 180);

  const normalizeReferenceMediaMimeType = (value) =>
    String(value || "")
      .replace(/\s+/g, "")
      .trim()
      .slice(0, 140);

  const isGoogleDriveMediaItem = (item) =>
    Boolean(item && typeof item === "object" && item.provider === "google_drive" && item.fileId);

  const referenceMediaItemKey = (item) =>
    isGoogleDriveMediaItem(item) ? `google_drive:${item.fileId}` : String(item?.src || "").trim();

  const googleDriveThumbnailProxyUrl = (fileId) => {
    const normalizedFileId = normalizeGoogleDriveFileId(fileId);
    return normalizedFileId
      ? `?action=google_drive_thumbnail&file_id=${encodeURIComponent(normalizedFileId)}`
      : "";
  };

  const isVideoReferenceMediaItem = (item) =>
    String(item?.mimeType || "").toLowerCase().startsWith("video/");

  const isImageReferenceMediaItem = (item) =>
    !isVideoReferenceMediaItem(item) &&
    (String(item?.mimeType || "").toLowerCase().startsWith("image/") || Boolean(item?.src));

  const referenceMediaKind = (item) =>
    isVideoReferenceMediaItem(item) ? "video" : "image";

  const referenceMediaThumbnailUrl = (item) => {
    if (!item || typeof item !== "object") return "";

    if (isGoogleDriveMediaItem(item)) {
      const proxiedThumbnailUrl = googleDriveThumbnailProxyUrl(item.fileId);
      if (proxiedThumbnailUrl) return proxiedThumbnailUrl;
    }

    if (isVideoReferenceMediaItem(item)) {
      const thumbnailUrl = normalizeImageReference(item.thumbnailUrl || "");
      if (thumbnailUrl) return thumbnailUrl;

      const source = normalizeImageReference(item.src || "");
      if (
        source &&
        source !== normalizeHttpReference(item.downloadUrl || "") &&
        source !== normalizeHttpReference(item.webViewLink || "")
      ) {
        return source;
      }

      return "";
    }

    return normalizeImageReference(
      item.src || item.thumbnailUrl || item.downloadUrl || item.webViewLink || ""
    );
  };

  const referenceMediaPreviewUrl = (item) => {
    if (!item || typeof item !== "object") return "";

    if (isVideoReferenceMediaItem(item)) {
      return normalizeHttpReference(item.downloadUrl || item.webViewLink || item.src || "");
    }

    if (isGoogleDriveMediaItem(item)) {
      return normalizeImageReference(
        item.downloadUrl || item.src || item.webViewLink || item.thumbnailUrl || ""
      );
    }

    return normalizeImageReference(
      item.src || item.downloadUrl || item.webViewLink || item.thumbnailUrl || ""
    );
  };

  const normalizeReferenceImageMediaItem = (value) => {
    let source = value;
    let title = "";

    if (value && typeof value === "object" && !Array.isArray(value)) {
      const provider = String(value.provider || "").trim().toLowerCase();
      const fileId = normalizeGoogleDriveFileId(
        value.file_id ?? value.fileId ?? value.drive_file_id ?? value.driveFileId ?? ""
      );
      if (provider === "google_drive" || fileId) {
        if (!fileId) return null;

        const mimeType = normalizeReferenceMediaMimeType(value.mime_type ?? value.mimeType ?? "");
        const name = normalizeReferenceMediaName(value.name ?? value.label ?? "");
        const thumbnailUrl = normalizeHttpReference(value.thumbnail_url ?? value.thumbnailUrl ?? "");
        const webViewLink = normalizeHttpReference(value.web_view_link ?? value.webViewLink ?? "");
        const downloadUrl = normalizeHttpReference(value.download_url ?? value.downloadUrl ?? "");
        const explicitSrc = normalizeImageReference(
          value.src ?? value.url ?? value.image ?? value.value ?? ""
        );
        const src =
          explicitSrc ||
          (mimeType.toLowerCase().startsWith("image/") ? downloadUrl : "") ||
          thumbnailUrl ||
          downloadUrl ||
          webViewLink ||
          "";

        return {
          provider: "google_drive",
          fileId,
          name,
          mimeType,
          thumbnailUrl: thumbnailUrl || "",
          webViewLink: webViewLink || "",
          downloadUrl: downloadUrl || "",
          src,
          title: normalizeReferenceImageTitle(value.title || ""),
        };
      }

      source = value.src ?? value.url ?? value.image ?? value.value ?? "";
      title = normalizeReferenceImageTitle(value.title ?? value.name ?? value.label ?? "");
    }

    const src = normalizeImageReference(source);
    if (!src) return null;

    return { src, title };
  };

  const formatReferenceLinkLabel = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "";

    try {
      const parsed = new URL(raw);
      const path = parsed.pathname && parsed.pathname !== "/" ? parsed.pathname : "";
      const suffix = `${path}${parsed.search}${parsed.hash}`;
      return `${parsed.host}${suffix}` || raw;
    } catch (_error) {
      return raw.replace(/^https?:\/\//i, "");
    }
  };

  const buildReferenceFaviconUrl = (value) => {
    try {
      const parsed = new URL(String(value || "").trim());
      return `https://www.google.com/s2/favicons?domain=${encodeURIComponent(parsed.hostname)}&sz=64`;
    } catch (_error) {
      return "";
    }
  };

  const parseReferenceUrlLines = (value, maxItems = maxReferenceItems) => {
    const seen = new Set();
    const result = [];

    parseReferenceRawList(value).forEach((item) => {
      if (result.length >= maxItems) return;
      const normalized = normalizeHttpReference(item);
      if (!normalized || seen.has(normalized)) return;
      seen.add(normalized);
      result.push(normalized);
    });

    return result;
  };

  const parseReferenceImageMediaItems = (value, maxItems = maxReferenceItems) => {
    const seen = new Set();
    const result = [];

    parseReferenceRawList(value).forEach((item) => {
      if (result.length >= maxItems) return;
      const normalized = normalizeReferenceImageMediaItem(item);
      const itemKey = referenceMediaItemKey(normalized);
      if (!normalized || !itemKey || seen.has(itemKey)) return;
      seen.add(itemKey);
      result.push(normalized);
    });

    return result;
  };

  const serializeReferenceImageMediaItems = (items) =>
    parseReferenceImageMediaItems(items || []).map((item) => {
      if (isGoogleDriveMediaItem(item)) {
        const serialized = {
          provider: "google_drive",
          file_id: item.fileId,
        };
        if (item.name) serialized.name = item.name;
        if (item.mimeType) serialized.mime_type = item.mimeType;
        if (item.thumbnailUrl) serialized.thumbnail_url = item.thumbnailUrl;
        if (item.webViewLink) serialized.web_view_link = item.webViewLink;
        if (item.downloadUrl) serialized.download_url = item.downloadUrl;
        if (item.src) serialized.src = item.src;
        if (item.title) serialized.title = item.title;
        return serialized;
      }

      return item.title ? { src: item.src, title: item.title } : item.src;
    });

  const parseReferenceImageItems = (value, maxItems = maxReferenceItems) =>
    parseReferenceImageMediaItems(value, maxItems).map((item) => item.src);

  const readReferenceImageMediaField = (field) => {
    if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) return [];
    const raw = (field.value || "").trim();
    if (!raw) return [];
    try {
      const decoded = JSON.parse(raw);
      return parseReferenceImageMediaItems(Array.isArray(decoded) ? decoded : []);
    } catch (_error) {
      return parseReferenceImageMediaItems(raw);
    }
  };

  const writeReferenceImageMediaField = (field, values) => {
    if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) return;
    field.value = JSON.stringify(serializeReferenceImageMediaItems(values || []));
  };

  const readJsonUrlListField = (field, parser = parseReferenceUrlLines) => {
    if (!(field instanceof HTMLInputElement)) return [];
    const raw = (field.value || "").trim();
    if (!raw) return [];
    try {
      const decoded = JSON.parse(raw);
      return parser(Array.isArray(decoded) ? decoded : []);
    } catch (_error) {
      return parser(raw);
    }
  };

  const writeJsonUrlListField = (field, values, parser = parseReferenceUrlLines) => {
    if (!(field instanceof HTMLInputElement)) return;
    field.value = JSON.stringify(parser(Array.isArray(values) ? values : [values]));
  };

  const readTaskHistoryField = (field) => {
    if (!(field instanceof HTMLInputElement)) return [];
    const raw = (field.value || "").trim();
    if (!raw) return [];
    try {
      const decoded = JSON.parse(raw);
      return Array.isArray(decoded) ? decoded : [];
    } catch (_error) {
      return [];
    }
  };

  const writeTaskHistoryField = (field, history) => {
    if (!(field instanceof HTMLInputElement)) return;
    field.value = JSON.stringify(Array.isArray(history) ? history : []);
  };

  const ensureTaskHiddenField = (
    form,
    { name = "", dataSelector = "", dataAttrName = "", withName = true } = {}
  ) => {
    if (!(form instanceof HTMLFormElement) || !dataSelector || !dataAttrName) {
      return null;
    }

    let field = form.querySelector(dataSelector);
    if (!(field instanceof HTMLInputElement)) {
      field = document.createElement("input");
      field.type = "hidden";
      field.setAttribute(dataAttrName, "");
      form.append(field);
    }

    if (withName && name) {
      field.name = name;
    }

    return field;
  };

  const readTaskRevisionStateField = (field) => {
    if (!(field instanceof HTMLInputElement)) return null;
    const raw = String(field.value || "").trim();
    if (raw === "") return null;
    return raw === "1";
  };

  const writeTaskRevisionStateField = (field, hasActiveRevision) => {
    if (!(field instanceof HTMLInputElement)) return;
    field.value = hasActiveRevision ? "1" : "0";
  };

  const normalizeTaskSubtasksDependencyValue = (value, fallback = false) => {
    const raw = String(value ?? "").trim().toLowerCase();
    if (raw === "1" || raw === "true" || raw === "on" || raw === "yes") {
      return true;
    }
    if (raw === "0" || raw === "false" || raw === "off" || raw === "no" || raw === "") {
      return false;
    }
    return Boolean(fallback);
  };

  const readTaskSubtasksDependencyField = (field, fallback = false) => {
    if (!(field instanceof HTMLInputElement)) {
      return Boolean(fallback);
    }
    return normalizeTaskSubtasksDependencyValue(field.value, fallback);
  };

  const writeTaskSubtasksDependencyField = (field, enabled) => {
    if (!(field instanceof HTMLInputElement)) return;
    field.value = enabled ? "1" : "0";
  };

  const parseTaskSubtaskList = (
    value,
    maxItems = 40,
    { enforceDependency = false } = {}
  ) => {
    let source = [];
    if (Array.isArray(value)) {
      source = value;
    } else if (typeof value === "string") {
      const raw = value.trim();
      if (!raw) {
        source = [];
      } else {
        try {
          const decoded = JSON.parse(raw);
          source = Array.isArray(decoded) ? decoded : [];
        } catch (_error) {
          source = raw
            .split(/\r?\n/)
            .map((item) => item.trim())
            .filter(Boolean);
        }
      }
    }

    const normalized = [];
    source.forEach((entry) => {
      if (normalized.length >= maxItems) return;

      let title = "";
      let done = false;
      if (typeof entry === "string") {
        title = entry.trim();
      } else if (entry && typeof entry === "object") {
        title = String(entry.title || entry.name || "").trim();
        done = Boolean(entry.done || entry.completed || entry.checked);
      }

      if (!title) return;
      if (title.length > 120) {
        title = title.slice(0, 120).trim();
      }
      title = forceFirstLetterUppercase(title);
      if (!title) return;

      normalized.push({
        title,
        done,
      });
    });

    if (enforceDependency) {
      let unlockNext = true;
      normalized.forEach((item) => {
        if (!unlockNext) {
          item.done = false;
        }
        if (!item.done) {
          unlockNext = false;
        }
      });
    }

    return normalized;
  };

  const taskSubtasksProgressMeta = (subtasks, { enforceDependency = false } = {}) => {
    const normalized = parseTaskSubtaskList(subtasks || [], 40, {
      enforceDependency,
    });
    const total = normalized.length;
    const completed = normalized.reduce((count, item) => count + (item.done ? 1 : 0), 0);
    const percent = total > 0 ? Math.round((completed / total) * 100) : 0;

    return {
      total,
      completed,
      pending: Math.max(0, total - completed),
      percent: Math.max(0, Math.min(100, percent)),
      isComplete: total > 0 && completed >= total,
      items: normalized,
    };
  };

  const readTaskSubtasksField = (field, { enforceDependency = false } = {}) => {
    if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) {
      return [];
    }
    return parseTaskSubtaskList(field.value || "[]", 40, {
      enforceDependency,
    });
  };

  const writeTaskSubtasksField = (field, subtasks, { enforceDependency = false } = {}) => {
    if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) {
      return;
    }
    field.value = JSON.stringify(
      parseTaskSubtaskList(subtasks || [], 40, {
        enforceDependency,
      })
    );
  };

  const formatHistoryDate = (value) => {
    const raw = (value || "").trim();
    if (!raw) return "Sem prazo";
    const parsed = new Date(`${raw}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return raw;
    return formatTaskHumanDate(parsed, raw);
  };

  const formatHistoryDateTime = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "";
    const normalized = raw.includes("T") ? raw : raw.replace(" ", "T");
    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) return raw;
    return parsed.toLocaleString("pt-BR", {
      day: "2-digit",
      month: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const taskHistoryMessage = (eventType, payload = {}) => {
    const transitionSymbol = "➜";
    switch (String(eventType || "").trim()) {
      case "created":
        return "Tarefa criada";
      case "title_changed":
        return "Titulo atualizado";
      case "title_tag_changed":
        return `Tag do titulo: ${payload.old || "Sem tag"} ${transitionSymbol} ${payload.new || "Sem tag"}`;
      case "status_changed":
        return `Status: ${payload.old_label || payload.old || "-"} ${transitionSymbol} ${
          payload.new_label || payload.new || "-"
        }`;
      case "priority_changed":
        return `Prioridade: ${payload.old_label || payload.old || "-"} ${transitionSymbol} ${
          payload.new_label || payload.new || "-"
        }`;
      case "due_date_changed":
        return `Prazo: ${formatHistoryDate(payload.old || "")} ${transitionSymbol} ${formatHistoryDate(
          payload.new || ""
        )}`;
      case "group_changed":
        return `Grupo: ${payload.old || "-"} ${transitionSymbol} ${payload.new || "-"}`;
      case "assignees_changed":
        return "Responsaveis atualizados";
      case "subtasks_changed":
        return `Etapas: ${Number(payload.old_completed) || 0}/${Number(payload.old_total) || 0} ${transitionSymbol} ${
          Number(payload.new_completed) || 0
        }/${Number(payload.new_total) || 0}`;
      case "revision_requested":
        return "Solicitação de ajuste na descrição";
      case "revision_removed":
        return "Solicitação de ajuste removida";
      case "overdue_started":
        return `Atraso detectado (${Math.max(0, Number(payload.overdue_days) || 0)} dia(s))`;
      case "overdue_cleared":
        return "Sinalizacao de atraso removida";
      default:
        return "Atualizacao registrada";
    }
  };

  const renderTaskDetailHistoryView = ({
    history = [],
    overdueFlag = 0,
    overdueDays = 0,
    overdueSinceDate = "",
  } = {}) => {
    if (!(taskDetailViewHistory instanceof HTMLElement)) return;

    taskDetailViewHistory.innerHTML = "";
    const items = [];

    if (Number(overdueFlag) === 1) {
      const overdueItem = document.createElement("div");
      overdueItem.className = "task-detail-history-item is-alert";
      const title = document.createElement("strong");
      title.textContent = `Em atraso h? ${Math.max(0, Number(overdueDays) || 0)} dia(s)`;
      const subtitle = document.createElement("span");
      subtitle.textContent = overdueSinceDate
        ? `Desde ${formatHistoryDate(overdueSinceDate)}`
        : "Aguardando regularizacao";
      overdueItem.append(title, subtitle);
      items.push(overdueItem);
    }

    (Array.isArray(history) ? history : []).forEach((entry) => {
      const card = document.createElement("div");
      const eventType = String(entry?.event_type || "").trim();
      card.className = `task-detail-history-item${eventType === "overdue_started" ? " is-alert" : ""}`;

      const title = document.createElement("strong");
      title.textContent = taskHistoryMessage(eventType, entry?.payload || {});

      const subtitle = document.createElement("span");
      const timeLabel = formatHistoryDateTime(entry?.created_at || "");
      const actorName = String(entry?.actor_name || "").trim();
      subtitle.textContent = actorName
        ? `${timeLabel || "Registro"} · ${actorName}`
        : timeLabel || "Registro automatico";

      card.append(title, subtitle);
      items.push(card);
    });

    if (!items.length) {
      const empty = document.createElement("div");
      empty.className = "task-detail-history-empty";
      empty.textContent = "Sem historico registrado.";
      taskDetailViewHistory.append(empty);
      return;
    }

    items.forEach((item) => taskDetailViewHistory.append(item));
  };

  const renderTaskDetailReferencesView = ({ links = [], images = [] } = {}) => {
    const safeLinks = parseReferenceUrlLines(links || []);
    const safeMedia = parseReferenceImageMediaItems(images || []);
    const previewMedia = normalizeTaskImagePreviewCollection(safeMedia);
    const previewIndexByKey = new Map(
      previewMedia.map((item, index) => [referenceMediaItemKey(item) || item.previewUrl, index])
    );
    taskDetailViewPreviewItems = [...previewMedia];
    if (!previewMedia.length) {
      taskImagePreviewState.currentIndex = -1;
    }

    if (taskDetailViewLinks instanceof HTMLElement) {
      taskDetailViewLinks.innerHTML = "";
      safeLinks.forEach((url) => {
        const a = document.createElement("a");
        a.href = url;
        a.target = "_blank";
        a.rel = "noreferrer noopener";
        a.className = "task-detail-ref-link";
        a.title = url;

        const faviconUrl = buildReferenceFaviconUrl(url);
        if (faviconUrl) {
          const icon = document.createElement("img");
          icon.src = faviconUrl;
          icon.alt = "";
          icon.className = "task-detail-ref-favicon";
          icon.loading = "lazy";
          icon.decoding = "async";
          icon.referrerPolicy = "no-referrer";
          icon.setAttribute("aria-hidden", "true");
          icon.onerror = () => {
            icon.remove();
            a.classList.add("task-detail-ref-link-no-favicon");
          };
          a.append(icon);
        } else {
          a.classList.add("task-detail-ref-link-no-favicon");
        }

        const label = document.createElement("span");
        label.className = "task-detail-ref-link-text";
        label.textContent = formatReferenceLinkLabel(url) || url;
        a.append(label);
        taskDetailViewLinks.append(a);
      });
    }
    if (taskDetailViewLinksWrap instanceof HTMLElement) {
      taskDetailViewLinksWrap.hidden = safeLinks.length === 0;
    }

    if (taskDetailViewImages instanceof HTMLElement) {
      taskDetailViewImages.innerHTML = "";
      safeMedia.forEach((mediaItem, index) => {
        const url = referenceMediaPreviewUrl(mediaItem);
        if (!url) return;

        const trigger = document.createElement("button");
        trigger.type = "button";
        trigger.className = "task-detail-ref-image-link";
        trigger.dataset.taskRefImagePreview = url;
        trigger.dataset.taskRefImageIndex = String(
          previewIndexByKey.get(referenceMediaItemKey(mediaItem) || url) ?? index
        );
        trigger.setAttribute(
          "aria-label",
          isVideoReferenceMediaItem(mediaItem)
            ? "Abrir video de referencia"
            : "Ampliar imagem de referencia"
        );

        const img = createReferenceMediaThumbnailElement(mediaItem, {
          className: "task-detail-ref-image",
          imageAlt: "Referencia da tarefa",
        });

        trigger.append(img, createReferenceMediaKindOverlay(mediaItem, { compact: true }));
        if (mediaItem.title || mediaItem.name) {
          const title = document.createElement("span");
          title.className = "task-detail-ref-image-title";
          title.textContent = mediaItem.title || mediaItem.name;
          trigger.append(title);
        }
        taskDetailViewImages.append(trigger);
      });
    }
    if (taskDetailViewImagesWrap instanceof HTMLElement) {
      taskDetailViewImagesWrap.hidden = safeMedia.length === 0;
    }

    if (taskDetailViewReferences instanceof HTMLElement) {
      taskDetailViewReferences.hidden = safeLinks.length === 0 && safeMedia.length === 0;
    }
  };

  const syncDueDateDisplay = (input) => {
    if (!(input instanceof HTMLInputElement)) return;
    const wrap = input.closest(".due-tag-field");
    const display = wrap?.querySelector("[data-due-date-display]");
    if (!(display instanceof HTMLElement)) return;

    const meta = dueDateMeta(input.value);
    display.textContent = meta.display;
    display.setAttribute("aria-label", `Prazo: ${meta.title}`);
    display.classList.toggle("is-relative", meta.isRelative);
  };

  const normalizeIsoDateInputValue = (value) => {
    const rawValue = String(value || "").trim();
    return /^\d{4}-\d{2}-\d{2}$/.test(rawValue) ? rawValue : "";
  };

  const setIsoDateInputValue = (input, value) => {
    if (!(input instanceof HTMLInputElement)) return;
    const normalized = normalizeIsoDateInputValue(value);
    const picker = input._flatpickr;

    if (picker && typeof picker.setDate === "function") {
      if (normalized) {
        picker.setDate(normalized, false, "Y-m-d");
      } else {
        picker.setDate([], false);
      }
    }

    input.value = normalized;
  };

  const initializeDatePickerInput = (
    input,
    { onValueChange = null, clickOpens = true, useBrazilianDisplay = true } = {}
  ) => {
    if (!(input instanceof HTMLInputElement)) return;
    if (input.dataset.flatpickrBound === "1") return;
    input.dataset.flatpickrBound = "1";

    if (typeof window.flatpickr !== "function") return;

    const localePt = window.flatpickr?.l10ns?.pt;
    window.flatpickr(input, {
      dateFormat: "Y-m-d",
      altInput: useBrazilianDisplay,
      altFormat: "d/m/Y",
      ariaDateFormat: "d/m/Y",
      allowInput: true,
      disableMobile: true,
      monthSelectorType: "static",
      clickOpens,
      locale: localePt || undefined,
      onChange: (_selectedDates, dateString) => {
        const normalized = normalizeIsoDateInputValue(dateString);
        input.value = normalized;
        if (typeof onValueChange === "function") {
          onValueChange(input);
        }
        input.dispatchEvent(new Event("change", { bubbles: true }));
      },
      onClose: () => {
        const normalized = normalizeIsoDateInputValue(input.value);
        if (normalized === input.value) return;
        input.value = normalized;
        if (typeof onValueChange === "function") {
          onValueChange(input);
        }
        input.dispatchEvent(new Event("change", { bubbles: true }));
      },
    });
  };

  const initializeDatePickers = (scope = document) => {
    const root = scope instanceof Element || scope instanceof Document ? scope : document;
    root.querySelectorAll('input[type="date"]').forEach((input) => {
      if (!(input instanceof HTMLInputElement)) return;
      const isTaskRowDueDateInput = input.matches("[data-due-date-input]");
      initializeDatePickerInput(input, {
        onValueChange: isTaskRowDueDateInput ? syncDueDateDisplay : null,
        clickOpens: !isTaskRowDueDateInput,
        useBrazilianDisplay: !isTaskRowDueDateInput,
      });
      if (isTaskRowDueDateInput) {
        syncDueDateDisplay(input);
      }
    });
  };

  const createTaskOverdueBadge = () => {
    const badge = document.createElement("button");
    badge.type = "button";
    badge.className = "task-overdue-badge";
    badge.dataset.taskOverdueBadge = "";
    badge.textContent = "Atraso";
    badge.title = "Tarefa em atraso. Clique para remover o aviso.";
    badge.setAttribute("aria-label", "Remover aviso de atraso");
    return badge;
  };

  const createTaskRevisionBadge = () => {
    const badge = document.createElement("button");
    badge.type = "button";
    badge.className = "task-revision-badge";
    badge.dataset.taskRevisionBadge = "";
    badge.textContent = "Revisão";
    badge.title = "Solicitação de revisão ativa. Clique para remover.";
    badge.setAttribute("aria-label", "Remover solicitação de revisão");
    return badge;
  };

  const syncTaskRevisionBadge = (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const statusStepper = form.querySelector("[data-status-stepper]");
    const historyField = form.querySelector("[data-task-history-json]");
    const revisionStateField = form.querySelector("[data-task-has-active-revision]");
    const descriptionField = form.querySelector('textarea[name="description"]');
    if (!(statusStepper instanceof HTMLElement)) {
      return;
    }

    const history = readTaskHistoryField(historyField);
    let hasRevision = hasActiveTaskRevisionRequest({
      description: descriptionField instanceof HTMLTextAreaElement ? descriptionField.value || "" : "",
      history,
    });
    if (!history.length) {
      const fallbackRevisionState = readTaskRevisionStateField(revisionStateField);
      if (fallbackRevisionState !== null) {
        hasRevision = fallbackRevisionState;
      }
    }

    writeTaskRevisionStateField(revisionStateField, hasRevision);
    const currentBadge = statusStepper.querySelector("[data-task-revision-badge]");

    if (hasRevision && !(currentBadge instanceof HTMLElement)) {
      statusStepper.prepend(createTaskRevisionBadge());
      return;
    }

    if (!hasRevision && currentBadge instanceof HTMLElement) {
      currentBadge.remove();
    }
  };

  const syncTaskOverdueBadge = (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const flagField = form.querySelector("[data-task-overdue-flag]");
    const dueTagField = form.querySelector(".due-tag-field");
    const taskItem = form.closest("[data-task-item]");
    if (!(flagField instanceof HTMLInputElement) || !(dueTagField instanceof HTMLElement)) {
      return;
    }

    const isOverdueMarked = String(flagField.value || "0") === "1";
    let badge = dueTagField.querySelector("[data-task-overdue-badge]");

    if (isOverdueMarked && !(badge instanceof HTMLButtonElement)) {
      badge = createTaskOverdueBadge();
      dueTagField.prepend(badge);
    } else if (!isOverdueMarked && badge instanceof HTMLElement) {
      badge.remove();
    }

    if (taskItem instanceof HTMLElement) {
      taskItem.classList.toggle("has-overdue-flag", isOverdueMarked);
    }
  };

  const getAssigneeCheckboxData = (checkbox) => {
    if (!(checkbox instanceof HTMLInputElement)) return null;

    const label = checkbox.closest("label");
    const fallbackName = label?.querySelector(".assignee-option-text")?.textContent?.trim() || "";
    const name = String(checkbox.dataset.assigneeName || fallbackName).trim();
    if (!name) return null;

    const fallbackInitial = name.slice(0, 1).toUpperCase();

    return {
      name,
      avatar: String(checkbox.dataset.assigneeAvatar || "").trim(),
      initial: String(checkbox.dataset.assigneeInitial || fallbackInitial)
        .trim()
        .slice(0, 1)
        .toUpperCase(),
    };
  };

  const getCheckedAssigneeData = (picker) => {
    if (!(picker instanceof HTMLElement)) return [];

    return Array.from(picker.querySelectorAll('input[type="checkbox"]:checked'))
      .map((checkbox) => getAssigneeCheckboxData(checkbox))
      .filter(Boolean);
  };

  const renderAssigneeAvatarMarkup = (assignee, className) => {
    if (!assignee || typeof assignee !== "object") return "";

    const safeClassName = escapeHtml(className || "avatar");
    const safeInitial = escapeHtml(
      String(assignee.initial || assignee.name || "").trim().slice(0, 1).toUpperCase()
    );
    const avatar = String(assignee.avatar || "").trim();

    if (avatar) {
      return `<span class="${safeClassName} has-image" aria-hidden="true"><img src="${escapeHtml(
        avatar
      )}" alt=""></span>`;
    }

    return `<span class="${safeClassName}" aria-hidden="true">${safeInitial}</span>`;
  };

  const renderAssigneeSummaryMarkup = (assignees, emptyText = "Selecionar") => {
    if (!Array.isArray(assignees) || !assignees.length) {
      return `<span class="assignee-summary"><span class="assignee-summary-text is-empty">${escapeHtml(
        emptyText
      )}</span></span>`;
    }

    const primaryAssignee = assignees[0];
    const hasMultiple = assignees.length > 1;
    const summaryText = hasMultiple
      ? `${primaryAssignee.name} +${assignees.length - 1}`
      : primaryAssignee.name;

    return `<span class="assignee-summary"><span class="assignee-summary-avatars${
      hasMultiple ? " has-multiple" : ""
    }" aria-hidden="true">${
      hasMultiple ? '<span class="assignee-summary-avatar-back"></span>' : ""
    }${renderAssigneeAvatarMarkup(primaryAssignee, "avatar assignee-summary-avatar")}</span><span class="assignee-summary-text">${escapeHtml(
      summaryText
    )}</span></span>`;
  };

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
      summary.removeAttribute("title");
      summary.setAttribute("aria-label", summary.textContent || "");
      return;
    }

    const text = checkedNames.join(", ");
    summary.textContent =
      details.classList.contains("row-assignee-picker") && text.length > 40
        ? `${text.slice(0, 37)}...`
        : text;
    summary.removeAttribute("title");
    summary.setAttribute("aria-label", checkedNames.join(", "));
  };

  const updateAssigneePickerSummaryVisual = (details) => {
    const summary = details?.querySelector?.("summary");
    if (!(summary instanceof HTMLElement)) return;

    const checkedAssignees = getCheckedAssigneeData(details);
    const checkedNames = checkedAssignees.map((assignee) => assignee.name).filter(Boolean);
    const emptyText = details.classList.contains("row-assignee-picker")
      ? "Sem responsavel"
      : "Selecionar";

    summary.innerHTML = renderAssigneeSummaryMarkup(checkedAssignees, emptyText);

    if (!checkedNames.length) {
      summary.removeAttribute("title");
      summary.setAttribute("aria-label", emptyText);
      return;
    }

    summary.title = checkedNames.join(", ");
    summary.setAttribute("aria-label", checkedNames.join(", "));
  };

  const bindTaskOverlayToggleListener = (details) => {
    if (!(details instanceof HTMLDetailsElement)) return;
    if (details.dataset.overlayToggleBound === "1") return;
    details.dataset.overlayToggleBound = "1";

    details.addEventListener("toggle", () => {
      if (details.open) {
        closeSiblingTaskOverlays(details);
      }
      syncTaskItemOverlayState(details);
    });
  };

  const hydrateTaskInteractiveFields = (root = document) => {
    if (!root || typeof root.querySelectorAll !== "function") return;

    initializeDatePickers(root);

    root
      .querySelectorAll(".status-select, .priority-select")
      .forEach(syncSelectColor);

    root.querySelectorAll("[data-due-date-input]").forEach((input) => {
      syncDueDateDisplay(input);
    });

    root.querySelectorAll(".assignee-picker").forEach((details) => {
      updateAssigneePickerSummaryVisual(details);
    });

    root
      .querySelectorAll("[data-inline-select-source]")
      .forEach((select) => syncInlineSelectPicker(select));

    root
      .querySelectorAll('.assignee-picker, [data-inline-select-picker]')
      .forEach((details) => {
        bindTaskOverlayToggleListener(details);
      });
  };

  hydrateTaskInteractiveFields(document);

  document.addEventListener("mousedown", (event) => {
    const target = event.target;
    if (!(target instanceof Node)) return;
    closeOpenDropdownDetails(target);
    closeOpenWorkspaceSidebarPickers(target);
  });

  document.addEventListener("change", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;
    const checkbox = target.closest('.assignee-picker input[type="checkbox"]');
    if (!checkbox) return;
    const picker = checkbox.closest(".assignee-picker");
    if (picker) updateAssigneePickerSummaryVisual(picker);
  });

  const ensureFlashStack = () => {
    let stack = document.querySelector(".flash-stack");
    if (stack) return stack;

    const appShell = document.querySelector(".app-shell");
    if (!appShell) return null;

    stack = document.createElement("div");
    stack.className = "flash-stack";
    stack.setAttribute("aria-live", "polite");
    appShell.prepend(stack);
    return stack;
  };

  const showClientFlash = (type, message) => {
    if (!message) return;
    const stack = ensureFlashStack();
    if (!stack) return;

    const item = document.createElement("div");
    item.className = `flash flash-${type}`;
    item.dataset.flash = "";
    item.innerHTML =
      `<span></span><button type="button" class="flash-close" data-flash-close aria-label="Fechar">×</button>`;
    item.querySelector("span").textContent = message;
    stack.append(item);

    window.setTimeout(() => {
      if (item.isConnected) item.remove();
    }, 4500);
  };

  const updateBoardCountText = (selector, suffix, delta) => {
    if (!delta) return;
    const el = document.querySelector(selector);
    if (!(el instanceof HTMLElement)) return;
    const match = (el.textContent || "").trim().match(/^(\d+)/);
    if (!match) return;
    const current = Number.parseInt(match[1], 10) || 0;
    const next = Math.max(0, current + delta);
    el.textContent = `${next} ${suffix}`;
  };

  const adjustBoardSummaryCounts = ({ visible = 0, total = 0 } = {}) => {
    updateBoardCountText("[data-board-visible-count]", "visíveis", visible);
    updateBoardCountText("[data-board-total-count]", "total", total);
  };

  const renderDashboardSummary = (dashboard) => {
    if (!dashboard || typeof dashboard !== "object") return;

    const total = Number.parseInt(dashboard.total, 10);
    const done = Number.parseInt(dashboard.done, 10);
    const completionRate = Number.parseInt(dashboard.completion_rate, 10);
    const dueToday = Number.parseInt(dashboard.due_today, 10);
    const urgent = Number.parseInt(dashboard.urgent, 10);
    const myOpen = Number.parseInt(dashboard.my_open, 10);

    const totalEl = document.querySelector("[data-dashboard-stat-total]");
    const doneEl = document.querySelector("[data-dashboard-stat-done]");
    const dueTodayEl = document.querySelector("[data-dashboard-stat-due-today]");
    const urgentEl = document.querySelector("[data-dashboard-stat-urgent]");
    const myOpenEl = document.querySelector("[data-dashboard-stat-my-open]");
    const boardTotalEl = document.querySelector("[data-board-total-count]");

    if (totalEl && Number.isFinite(total)) {
      totalEl.textContent = String(total);
    }
    if (doneEl && Number.isFinite(done)) {
      const rate = Number.isFinite(completionRate) ? completionRate : 0;
      doneEl.textContent = `${done} (${rate}%)`;
    }
    if (dueTodayEl && Number.isFinite(dueToday)) {
      dueTodayEl.textContent = String(dueToday);
    }
    if (urgentEl && Number.isFinite(urgent)) {
      urgentEl.textContent = String(urgent);
    }
    if (myOpenEl && Number.isFinite(myOpen)) {
      myOpenEl.textContent = String(myOpen);
    }
    if (boardTotalEl && Number.isFinite(total)) {
      boardTotalEl.textContent = `${total} total`;
    }
  };

  const createEmptyGroupRow = (groupName) => {
    const row = document.createElement("div");
    row.className = "task-group-empty-row";

    const button = document.createElement("button");
    button.type = "button";
    button.className = "task-group-empty-add";
    button.dataset.openCreateTaskModal = "";
    button.dataset.createGroup = groupName || "Geral";
    button.setAttribute("aria-label", `Criar tarefa no grupo ${groupName || "Geral"}`);
    button.textContent = "+";

    row.append(button);
    return row;
  };

  const createTaskGroupDoneHiddenRow = () => {
    const row = document.createElement("div");
    row.className = "task-group-hidden-done-row";
    row.dataset.taskGroupHiddenDoneRow = "";
    row.textContent = "Tarefas concluídas ocultas.";
    return row;
  };

  const syncTaskGroupDoneToggleButton = (groupSection) => {
    if (!(groupSection instanceof HTMLElement)) return;
    const toggleButton = groupSection.querySelector("[data-toggle-group-done]");
    if (!(toggleButton instanceof HTMLButtonElement)) return;

    const hideLabel = (toggleButton.dataset.labelHide || "").trim() || "Ocultar concluídas";
    const showLabel = (toggleButton.dataset.labelShow || "").trim() || "Exibir concluídas";
    const isDoneHidden = groupSection.classList.contains("is-done-hidden");
    const nextLabel = isDoneHidden ? showLabel : hideLabel;
    const groupName = (groupSection.dataset.groupName || "Geral").trim() || "Geral";

    toggleButton.textContent = nextLabel;
    toggleButton.classList.toggle("is-active", isDoneHidden);
    toggleButton.setAttribute("aria-pressed", isDoneHidden ? "true" : "false");
    toggleButton.setAttribute("aria-label", `${nextLabel} do grupo ${groupName}`);
  };

  const syncTaskGroupDoneVisibility = (groupSection) => {
    if (!(groupSection instanceof HTMLElement)) {
      return { totalTaskCount: 0, visibleTaskCount: 0, hiddenDoneCount: 0 };
    }

    const dropzone = groupSection.querySelector("[data-task-dropzone]");
    if (!(dropzone instanceof HTMLElement)) {
      return { totalTaskCount: 0, visibleTaskCount: 0, hiddenDoneCount: 0 };
    }

    const hideDone = groupSection.classList.contains("is-done-hidden");
    let totalTaskCount = 0;
    let visibleTaskCount = 0;
    let hiddenDoneCount = 0;

    dropzone.querySelectorAll("[data-task-item]").forEach((taskItem) => {
      if (!(taskItem instanceof HTMLElement)) return;
      totalTaskCount += 1;

      const isDoneTask = isDoneTaskItem(taskItem);
      const shouldHide = hideDone && isDoneTask;

      taskItem.hidden = shouldHide;
      if (shouldHide) {
        hiddenDoneCount += 1;
      } else {
        visibleTaskCount += 1;
      }
    });

    return { totalTaskCount, visibleTaskCount, hiddenDoneCount };
  };

  const refreshTaskGroupSection = (groupSection) => {
    if (!(groupSection instanceof HTMLElement)) return;
    const dropzone = groupSection.querySelector("[data-task-dropzone]");
    if (!(dropzone instanceof HTMLElement)) return;

    const { totalTaskCount, visibleTaskCount, hiddenDoneCount } =
      syncTaskGroupDoneVisibility(groupSection);
    sortGroupTaskItemsByStatus(dropzone);

    const countEl = groupSection.querySelector(".task-group-count");
    if (countEl) countEl.textContent = String(totalTaskCount);

    const emptyRow = dropzone.querySelector(".task-group-empty-row");
    const doneHiddenRow = dropzone.querySelector("[data-task-group-hidden-done-row]");
    const groupName = (groupSection.dataset.groupName || "Geral").trim() || "Geral";

    if (totalTaskCount === 0) {
      if (!emptyRow) dropzone.append(createEmptyGroupRow(groupName));
    } else if (emptyRow) {
      emptyRow.remove();
    }

    if (hiddenDoneCount > 0 && visibleTaskCount === 0 && totalTaskCount > 0) {
      const hiddenLabel =
        hiddenDoneCount === 1
          ? "1 tarefa concluída oculta."
          : `${hiddenDoneCount} tarefas concluídas ocultas.`;
      if (doneHiddenRow instanceof HTMLElement) {
        doneHiddenRow.textContent = hiddenLabel;
      } else {
        const row = createTaskGroupDoneHiddenRow();
        row.textContent = hiddenLabel;
        dropzone.append(row);
      }
    } else if (doneHiddenRow instanceof HTMLElement) {
      doneHiddenRow.remove();
    }

    syncGroupStatusDividers(dropzone);
    syncTaskGroupDoneToggleButton(groupSection);
  };

  const collapseStorageWorkspaceId = (() => {
    const workspaceId = String(document.body?.dataset?.workspaceId || "").trim();
    return workspaceId || "0";
  })();

  const normalizeGroupCollapseStorageName = (groupName) =>
    String(groupName || "").trim().toLocaleLowerCase();

  const getGroupCollapseStorageKey = (scope) =>
    `wf_group_collapsed:${collapseStorageWorkspaceId}:${scope}`;

  const readStoredGroupCollapsedMap = (scope) => {
    if (!window.localStorage) return {};

    try {
      const raw = window.localStorage.getItem(getGroupCollapseStorageKey(scope));
      const decoded = raw ? JSON.parse(raw) : {};
      if (!decoded || typeof decoded !== "object" || Array.isArray(decoded)) {
        return {};
      }

      const map = {};
      Object.entries(decoded).forEach(([key, value]) => {
        const normalizedKey = normalizeGroupCollapseStorageName(key);
        if (!normalizedKey) return;
        map[normalizedKey] = Boolean(value);
      });
      return map;
    } catch (error) {
      return {};
    }
  };

  const writeStoredGroupCollapsedMap = (scope, map) => {
    if (!window.localStorage) return;

    try {
      window.localStorage.setItem(getGroupCollapseStorageKey(scope), JSON.stringify(map));
    } catch (error) {
      // noop
    }
  };

  const getStoredGroupCollapsedState = (scope, groupName) => {
    const normalizedGroupName = normalizeGroupCollapseStorageName(groupName);
    if (!normalizedGroupName) return null;
    const map = readStoredGroupCollapsedMap(scope);
    if (!Object.prototype.hasOwnProperty.call(map, normalizedGroupName)) {
      return null;
    }
    return Boolean(map[normalizedGroupName]);
  };

  const setStoredGroupCollapsedState = (scope, groupName, collapsed) => {
    const normalizedGroupName = normalizeGroupCollapseStorageName(groupName);
    if (!normalizedGroupName) return;

    const map = readStoredGroupCollapsedMap(scope);
    if (collapsed) {
      map[normalizedGroupName] = true;
    } else {
      delete map[normalizedGroupName];
    }

    writeStoredGroupCollapsedMap(scope, map);
  };

  const getTaskGroupDoneHiddenStorageKey = () =>
    `wf_group_done_hidden:${collapseStorageWorkspaceId}:tasks`;

  const readStoredTaskGroupDoneHiddenMap = () => {
    if (!window.localStorage) return {};

    try {
      const raw = window.localStorage.getItem(getTaskGroupDoneHiddenStorageKey());
      const decoded = raw ? JSON.parse(raw) : {};
      if (!decoded || typeof decoded !== "object" || Array.isArray(decoded)) {
        return {};
      }

      const map = {};
      Object.entries(decoded).forEach(([key, value]) => {
        const normalizedKey = normalizeGroupCollapseStorageName(key);
        if (!normalizedKey) return;
        map[normalizedKey] = Boolean(value);
      });
      return map;
    } catch (error) {
      return {};
    }
  };

  const writeStoredTaskGroupDoneHiddenMap = (map) => {
    if (!window.localStorage) return;

    try {
      window.localStorage.setItem(getTaskGroupDoneHiddenStorageKey(), JSON.stringify(map));
    } catch (error) {
      // noop
    }
  };

  const getStoredTaskGroupDoneHiddenState = (groupName) => {
    const normalizedGroupName = normalizeGroupCollapseStorageName(groupName);
    if (!normalizedGroupName) return null;
    const map = readStoredTaskGroupDoneHiddenMap();
    if (!Object.prototype.hasOwnProperty.call(map, normalizedGroupName)) {
      return null;
    }
    return Boolean(map[normalizedGroupName]);
  };

  const setStoredTaskGroupDoneHiddenState = (groupName, hidden) => {
    const normalizedGroupName = normalizeGroupCollapseStorageName(groupName);
    if (!normalizedGroupName) return;

    const map = readStoredTaskGroupDoneHiddenMap();
    if (hidden) {
      map[normalizedGroupName] = true;
    } else {
      delete map[normalizedGroupName];
    }
    writeStoredTaskGroupDoneHiddenMap(map);
  };

  const replaceStoredTaskGroupDoneHiddenStateName = (oldName, nextName) => {
    const previous = normalizeGroupCollapseStorageName(oldName);
    const current = normalizeGroupCollapseStorageName(nextName);
    if (!previous || !current || previous === current) return;

    const map = readStoredTaskGroupDoneHiddenMap();
    if (!Object.prototype.hasOwnProperty.call(map, previous)) return;

    const shouldHide = Boolean(map[previous]);
    delete map[previous];
    if (shouldHide) {
      map[current] = true;
    }
    writeStoredTaskGroupDoneHiddenMap(map);
  };

  const clearStoredTaskGroupDoneHiddenState = (groupName) => {
    const normalizedGroupName = normalizeGroupCollapseStorageName(groupName);
    if (!normalizedGroupName) return;
    const map = readStoredTaskGroupDoneHiddenMap();
    if (!Object.prototype.hasOwnProperty.call(map, normalizedGroupName)) return;
    delete map[normalizedGroupName];
    writeStoredTaskGroupDoneHiddenMap(map);
  };

  const resolveInitialGroupCollapsedState = (scope, groupSection) => {
    if (!(groupSection instanceof HTMLElement)) return false;
    const storedState = getStoredGroupCollapsedState(scope, groupSection.dataset.groupName || "");
    if (storedState !== null) {
      return storedState;
    }
    return groupSection.classList.contains("is-collapsed");
  };

  const resolveInitialTaskGroupDoneHiddenState = (groupSection) => {
    if (!(groupSection instanceof HTMLElement)) return false;
    const storedState = getStoredTaskGroupDoneHiddenState(groupSection.dataset.groupName || "");
    if (storedState !== null) {
      return storedState;
    }
    return groupSection.classList.contains("is-done-hidden");
  };

  const setTaskGroupCollapsed = (groupSection, collapsed, options = {}) => {
    if (!(groupSection instanceof HTMLElement)) return;
    const dropzone = groupSection.querySelector("[data-task-dropzone]");
    const shouldCollapse = Boolean(collapsed);
    const shouldPersist = options.persist !== false;

    groupSection.classList.toggle("is-collapsed", shouldCollapse);
    if (dropzone instanceof HTMLElement) {
      dropzone.hidden = shouldCollapse;
    }
    if (shouldPersist) {
      setStoredGroupCollapsedState("tasks", groupSection.dataset.groupName || "", shouldCollapse);
    }
  };

  const setTaskGroupDoneHidden = (groupSection, hideDone, options = {}) => {
    if (!(groupSection instanceof HTMLElement)) return;
    const shouldHideDone = Boolean(hideDone);
    const shouldPersist = options.persist !== false;
    const shouldRefresh = options.refresh !== false;

    groupSection.classList.toggle("is-done-hidden", shouldHideDone);
    if (shouldPersist) {
      setStoredTaskGroupDoneHiddenState(groupSection.dataset.groupName || "", shouldHideDone);
    }

    if (shouldRefresh) {
      refreshTaskGroupSection(groupSection);
    } else {
      syncTaskGroupDoneToggleButton(groupSection);
    }
  };

  const setVaultGroupCollapsed = (groupSection, collapsed, options = {}) => {
    if (!(groupSection instanceof HTMLElement)) return;
    const rows = groupSection.querySelector("[data-vault-group-rows]");
    const shouldCollapse = Boolean(collapsed);
    const shouldPersist = options.persist !== false;

    groupSection.classList.toggle("is-collapsed", shouldCollapse);
    if (rows instanceof HTMLElement) {
      rows.hidden = shouldCollapse;
    }
    if (shouldPersist) {
      setStoredGroupCollapsedState("vault", groupSection.dataset.groupName || "", shouldCollapse);
    }
  };

  const setDueGroupCollapsed = (groupSection, collapsed, options = {}) => {
    if (!(groupSection instanceof HTMLElement)) return;
    const rows = groupSection.querySelector("[data-due-group-rows]");
    const shouldCollapse = Boolean(collapsed);
    const shouldPersist = options.persist !== false;

    groupSection.classList.toggle("is-collapsed", shouldCollapse);
    if (rows instanceof HTMLElement) {
      rows.hidden = shouldCollapse;
    }
    if (shouldPersist) {
      setStoredGroupCollapsedState("dues", groupSection.dataset.groupName || "", shouldCollapse);
    }
  };

  const setInventoryGroupCollapsed = (groupSection, collapsed, options = {}) => {
    if (!(groupSection instanceof HTMLElement)) return;
    const rows = groupSection.querySelector("[data-inventory-group-rows]");
    const shouldCollapse = Boolean(collapsed);
    const shouldPersist = options.persist !== false;

    groupSection.classList.toggle("is-collapsed", shouldCollapse);
    if (rows instanceof HTMLElement) {
      rows.hidden = shouldCollapse;
    }
    if (shouldPersist) {
      setStoredGroupCollapsedState("inventory", groupSection.dataset.groupName || "", shouldCollapse);
    }
  };

  const isGroupHeadToggleTargetBlocked = (target, groupHead) => {
    if (!(target instanceof HTMLElement) || !(groupHead instanceof HTMLElement)) return true;
    const blockedTarget = target.closest(
      [
        ".task-group-head-actions",
        "button",
        "a[href]",
        "input",
        "select",
        "textarea",
        "label",
        "summary",
        "details",
        "[contenteditable='true']",
        "[role='button']",
        "[role='option']",
      ].join(",")
    );
    return blockedTarget instanceof HTMLElement && groupHead.contains(blockedTarget);
  };

  const moveTaskItemToGroupDom = (taskItem, groupName) => {
    if (!(taskItem instanceof HTMLElement) || !taskItem.isConnected) return false;
    const nextGroup = (groupName || "").trim() || "Geral";
    const targetDropzone = document.querySelector(
      `[data-task-dropzone][data-group-name="${CSS.escape(nextGroup)}"]`
    );
    if (!(targetDropzone instanceof HTMLElement)) return false;

    const taskItemId = String(taskItem.id || "").trim();
    if (taskItemId) {
      const selector = `[data-task-item]#${CSS.escape(taskItemId)}`;
      document.querySelectorAll(selector).forEach((duplicateItem) => {
        if (duplicateItem === taskItem) return;
        if (duplicateItem instanceof HTMLElement) {
          duplicateItem.remove();
        }
      });
    }

    const sourceSection = taskItem.closest("[data-task-group]");
    const targetSection = targetDropzone.closest("[data-task-group]");
    targetDropzone.append(taskItem);
    taskItem.dataset.groupName = nextGroup;

    refreshTaskGroupSection(sourceSection);
    if (targetSection !== sourceSection) {
      refreshTaskGroupSection(targetSection);
    } else {
      refreshTaskGroupSection(sourceSection);
    }
    return true;
  };

  const refreshTaskUpdatedAtMeta = (form, updatedAtLabel) => {
    if (!(form instanceof HTMLFormElement) || !updatedAtLabel) return;
    const details = form.querySelector(".task-line-details");
    if (!(details instanceof HTMLElement)) return;

    let el = details.querySelector("[data-task-updated-at]");
    if (!(el instanceof HTMLElement)) {
      const meta = details.querySelector(".task-line-meta");
      if (!(meta instanceof HTMLElement)) return;
      el = document.createElement("span");
      el.dataset.taskUpdatedAt = "";
      meta.append(el);
    }
    el.textContent = `Atualizado em ${updatedAtLabel}`;
  };

  const syncTaskExpectedUpdatedAt = (form, updatedAtValue) => {
    if (!(form instanceof HTMLFormElement)) return;
    const expectedUpdatedAtField = form.querySelector("[data-task-expected-updated-at]");
    if (!(expectedUpdatedAtField instanceof HTMLInputElement)) return;
    expectedUpdatedAtField.value = String(updatedAtValue || "").trim();
  };

  const isDatabaseLockedMessage = (message) => {
    const normalized = String(message || "").trim().toLowerCase();
    if (!normalized) return false;
    return (
      normalized.includes("database is locked") ||
      normalized.includes("database table is locked") ||
      (normalized.includes("sqlstate[hy000]") && normalized.includes("locked")) ||
      normalized.includes("banco de dados bloqueado") ||
      normalized.includes("base de dados bloqueada")
    );
  };

  const parseJsonSafely = async (response) => {
    try {
      return await response.json();
    } catch (_error) {
      return null;
    }
  };

  const createRequestError = (message, response = null, data = null) => {
    const error = new Error(message);
    if (response && Number.isFinite(response.status)) {
      error.status = response.status;
    }
    if (data && typeof data === "object") {
      error.payload = data;
    }
    return error;
  };

  const runWithAppLoading = (work, options = {}) => {
    const loader = window.BexonLoading;
    if (!loader || typeof loader.withLoading !== "function") {
      return Promise.resolve().then(work);
    }
    return loader.withLoading(work, options);
  };

  const isTaskConflictError = (error) => {
    if (!(error instanceof Error)) return false;
    const status = Number.parseInt(String(error.status || "0"), 10) || 0;
    const payload = error.payload && typeof error.payload === "object" ? error.payload : {};
    const code = typeof payload.code === "string" ? payload.code.trim().toLowerCase() : "";
    return status === 409 || code === "task_conflict";
  };

  const postFormJson = async (form) => runWithAppLoading(async () => {
    const runAttempt = async () => {
      const response = await fetch(form.getAttribute("action") || window.location.href, {
        method: "POST",
        body: new FormData(form),
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
        credentials: "same-origin",
      });

      const data = await parseJsonSafely(response);
      return { response, data };
    };

    let lastMessage = "Não foi possível concluir a operação.";

    for (let attempt = 0; attempt < 2; attempt += 1) {
      const { response, data } = await runAttempt();
      if (response.ok && data && data.ok === true) {
        return data;
      }

      const message =
        (data && (data.error || data.message)) ||
        "Não foi possível concluir a operação.";
      lastMessage = message;

      const shouldRetry = attempt === 0 && isDatabaseLockedMessage(message);
      if (!shouldRetry) {
        throw createRequestError(message, response, data);
      }

      await new Promise((resolve) => window.setTimeout(resolve, 180));
    }

    throw new Error(lastMessage);
  }, { label: form?.getAttribute("data-loading-label") || "Salvando..." });

  const postActionJson = async (action, payload = {}) => runWithAppLoading(async () => {
    const formData = new FormData();
    formData.append("action", String(action || "").trim());
    Object.entries(payload || {}).forEach(([key, value]) => {
      if (!key || value === undefined || value === null) return;
      formData.append(key, String(value));
    });

    const response = await fetch(window.location.pathname, {
      method: "POST",
      body: formData,
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      credentials: "same-origin",
    });

    let data = null;
    try {
      data = await response.json();
    } catch (_error) {
      data = null;
    }

    if (!response.ok || !data || data.ok !== true) {
      const message = (data && (data.error || data.message)) || "Não foi possível concluir a operação.";
      throw new Error(message);
    }

    return data;
  }, { label: "Carregando..." });

  const syncTaskHistoryControls = (state = null) => {
    const controls = document.querySelector("[data-task-history-controls]");
    if (!(controls instanceof HTMLElement)) return;

    const normalizedState = state && typeof state === "object" ? state : {};
    const configs = [
      {
        action: "undo",
        canKey: "can_undo",
        labelKey: "undo_label",
        fallbackTitle: "Nada para desfazer",
        activePrefix: "Desfazer: ",
      },
      {
        action: "redo",
        canKey: "can_redo",
        labelKey: "redo_label",
        fallbackTitle: "Nada para refazer",
        activePrefix: "Refazer: ",
      },
    ];

    configs.forEach((config) => {
      const button = controls.querySelector(`[data-task-history-button="${config.action}"]`);
      if (!(button instanceof HTMLButtonElement)) return;

      const canRun = normalizedState[config.canKey] === true;
      const label = String(normalizedState[config.labelKey] || "").trim();
      const title = canRun && label ? `${config.activePrefix}${label}` : config.fallbackTitle;
      button.disabled = !canRun;
      button.title = title;
      button.setAttribute("aria-label", canRun && label ? title : config.action === "undo" ? "Desfazer" : "Refazer");
    });
  };

  const notificationWorkspaceId = Number.parseInt(
    String(document.body?.dataset?.workspaceId || "").trim() || "0",
    10
  );
  const notificationUserId = Number.parseInt(
    String(document.body?.dataset?.userId || "").trim() || "0",
    10
  );
  const headerNotificationsRoot = document.querySelector("[data-header-notifications]");
  const headerNotificationsToggle = document.querySelector("[data-header-notifications-toggle]");
  const headerNotificationsDropdown = document.querySelector("[data-header-notifications-dropdown]");
  const headerNotificationsList = document.querySelector("[data-header-notifications-list]");
  const headerNotificationsCount = document.querySelector("[data-header-notifications-count]");

  const taskNotificationState = {
    isPolling: false,
    isSyncingTasks: false,
    intervalId: null,
    lastHistoryId: 0,
    seenHistoryId: 0,
    notifications: [],
    isDropdownOpen: false,
    pendingTasksSync: false,
  };

  const taskNotificationStorageKey = () =>
    `wf_task_notification_last_history:${notificationUserId}:${notificationWorkspaceId}`;
  const taskNotificationListStorageKey = () =>
    `wf_task_notification_items:${notificationUserId}:${notificationWorkspaceId}`;
  const taskNotificationSeenStorageKey = () =>
    `wf_task_notification_seen_history:${notificationUserId}:${notificationWorkspaceId}`;

  const readStoredTaskNotificationHistoryId = () => {
    if (!window.localStorage) return 0;
    try {
      const raw = String(window.localStorage.getItem(taskNotificationStorageKey()) || "").trim();
      const parsed = Number.parseInt(raw || "0", 10);
      return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    } catch (error) {
      return 0;
    }
  };

  const storeTaskNotificationHistoryId = (historyId) => {
    if (!window.localStorage) return;
    const nextValue = Number.parseInt(String(historyId || "0"), 10);
    if (!Number.isFinite(nextValue) || nextValue < 0) return;

    try {
      window.localStorage.setItem(taskNotificationStorageKey(), String(nextValue));
    } catch (error) {
      // noop
    }
  };

  const normalizeTaskNotificationEntry = (value) => {
    const historyId = Number.parseInt(String(value?.history_id || "0"), 10) || 0;
    const taskId = Number.parseInt(String(value?.task_id || "0"), 10) || 0;
    const title = String(value?.title || "").trim();
    const message = String(value?.message || "").trim();
    const createdAt = String(value?.created_at || "").trim();
    const eventType = String(value?.event_type || "").trim();

    if (!(historyId > 0) || !(taskId > 0) || !message) {
      return null;
    }

    return {
      history_id: historyId,
      task_id: taskId,
      title: title || "Notificacao",
      message,
      created_at: createdAt,
      event_type: eventType,
    };
  };

  const normalizeTaskNotificationCollection = (items = []) => {
    const map = new Map();
    (Array.isArray(items) ? items : []).forEach((item) => {
      const normalized = normalizeTaskNotificationEntry(item);
      if (!normalized) return;
      map.set(normalized.history_id, normalized);
    });

    return Array.from(map.values())
      .sort((a, b) => b.history_id - a.history_id)
      .slice(0, 60);
  };

  const readStoredTaskNotificationItems = () => {
    if (!window.localStorage) return [];
    try {
      const raw = window.localStorage.getItem(taskNotificationListStorageKey());
      if (!raw) return [];
      const parsed = JSON.parse(raw);
      return normalizeTaskNotificationCollection(parsed);
    } catch (error) {
      return [];
    }
  };

  const storeTaskNotificationItems = (items = []) => {
    if (!window.localStorage) return;
    try {
      const normalized = normalizeTaskNotificationCollection(items);
      window.localStorage.setItem(taskNotificationListStorageKey(), JSON.stringify(normalized));
    } catch (error) {
      // noop
    }
  };

  const readStoredTaskNotificationSeenHistoryId = () => {
    if (!window.localStorage) return 0;
    try {
      const raw = String(window.localStorage.getItem(taskNotificationSeenStorageKey()) || "").trim();
      const parsed = Number.parseInt(raw || "0", 10);
      return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    } catch (error) {
      return 0;
    }
  };

  const storeTaskNotificationSeenHistoryId = (historyId) => {
    if (!window.localStorage) return;
    const nextValue = Number.parseInt(String(historyId || "0"), 10);
    if (!Number.isFinite(nextValue) || nextValue < 0) return;

    try {
      window.localStorage.setItem(taskNotificationSeenStorageKey(), String(nextValue));
    } catch (error) {
      // noop
    }
  };

  const maxTaskNotificationHistoryId = (items = []) => {
    return normalizeTaskNotificationCollection(items).reduce((maxId, item) => {
      const historyId = Number.parseInt(String(item?.history_id || "0"), 10) || 0;
      return historyId > maxId ? historyId : maxId;
    }, 0);
  };

  const updateHeaderNotificationCount = () => {
    if (!(headerNotificationsCount instanceof HTMLElement)) return;
    const unread = taskNotificationState.notifications.filter((item) => {
      const historyId = Number.parseInt(String(item?.history_id || "0"), 10) || 0;
      return historyId > taskNotificationState.seenHistoryId;
    }).length;

    if (unread <= 0) {
      headerNotificationsCount.hidden = true;
      headerNotificationsCount.textContent = "0";
      return;
    }

    headerNotificationsCount.hidden = false;
    headerNotificationsCount.textContent = unread > 99 ? "99+" : String(unread);
  };

  const renderHeaderTaskNotifications = () => {
    if (!(headerNotificationsList instanceof HTMLElement)) return;

    headerNotificationsList.innerHTML = "";
    if (taskNotificationState.notifications.length === 0) {
      const empty = document.createElement("p");
      empty.className = "header-notification-empty";
      empty.textContent = "Sem notificações.";
      headerNotificationsList.append(empty);
      updateHeaderNotificationCount();
      return;
    }

    taskNotificationState.notifications.forEach((item) => {
      const historyId = Number.parseInt(String(item?.history_id || "0"), 10) || 0;
      const taskId = Number.parseInt(String(item?.task_id || "0"), 10) || 0;
      const unread = historyId > taskNotificationState.seenHistoryId;

      const button = document.createElement("button");
      button.type = "button";
      button.className = `header-notification-item${unread ? " is-unread" : ""}`;
      button.dataset.taskNotificationItem = "";
      button.dataset.taskNotificationHistoryId = String(historyId);
      button.dataset.taskNotificationTaskId = String(taskId);

      const title = document.createElement("p");
      title.className = "header-notification-item-title";
      title.textContent = String(item?.title || "Notificacao");

      const message = document.createElement("p");
      message.className = "header-notification-item-message";
      message.textContent = String(item?.message || "");

      const time = document.createElement("p");
      time.className = "header-notification-item-time";
      const formattedTime = formatHistoryDateTime(String(item?.created_at || ""));
      time.textContent = formattedTime || "Agora";

      button.append(title, message, time);
      headerNotificationsList.append(button);
    });

    updateHeaderNotificationCount();
  };

  const mergeTaskNotifications = (notifications = []) => {
    const merged = normalizeTaskNotificationCollection([
      ...taskNotificationState.notifications,
      ...(Array.isArray(notifications) ? notifications : []),
    ]);
    taskNotificationState.notifications = merged;
    storeTaskNotificationItems(merged);
    renderHeaderTaskNotifications();
  };

  const markTaskNotificationsAsSeen = () => {
    const highestId = maxTaskNotificationHistoryId(taskNotificationState.notifications);
    if (highestId <= taskNotificationState.seenHistoryId) {
      updateHeaderNotificationCount();
      return;
    }

    taskNotificationState.seenHistoryId = highestId;
    storeTaskNotificationSeenHistoryId(highestId);
    renderHeaderTaskNotifications();
  };

  const setHeaderNotificationDropdownOpen = (open) => {
    const nextOpen = Boolean(open);
    taskNotificationState.isDropdownOpen = nextOpen;

    if (headerNotificationsToggle instanceof HTMLButtonElement) {
      headerNotificationsToggle.setAttribute("aria-expanded", nextOpen ? "true" : "false");
      headerNotificationsToggle.classList.toggle("is-open", nextOpen);
    }

    if (headerNotificationsDropdown instanceof HTMLElement) {
      headerNotificationsDropdown.hidden = !nextOpen;
    }

    if (nextOpen) {
      markTaskNotificationsAsSeen();
    }
  };

  const maybeEnableBrowserNotifications = () => {
    if (!("Notification" in window)) return;
    if (Notification.permission !== "default") return;

    const requestPermission = () => {
      document.removeEventListener("click", requestPermission);
      document.removeEventListener("keydown", requestPermission);
      Notification.requestPermission().catch(() => {});
    };

    document.addEventListener("click", requestPermission, { once: true });
    document.addEventListener("keydown", requestPermission, { once: true });
  };

  const fetchTaskNotificationsFeed = async ({ initialize = false, sinceHistoryId = 0 } = {}) => {
    const params = new URLSearchParams();
    params.set("action", "task_notifications_feed");
    params.set("since_id", String(Math.max(0, Number.parseInt(String(sinceHistoryId || "0"), 10) || 0)));
    params.set("limit", "24");
    if (initialize) {
      params.set("initialize", "1");
    }

    const url = `${window.location.pathname}?${params.toString()}`;
    const response = await fetch(url, {
      method: "GET",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      credentials: "same-origin",
    });

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      data = null;
    }

    if (!response.ok || !data || data.ok !== true) {
      throw new Error(
        (data && (data.error || data.message)) || "Não foi possível carregar notificações."
      );
    }

    return data;
  };

  const showTaskBrowserNotification = (notification) => {
    if (!("Notification" in window)) return;
    if (Notification.permission !== "granted") return;

    const title = String(notification?.title || "Notificacao");
    const body = String(notification?.message || "").trim();
    if (!body) return;

    try {
      new Notification(title, {
        body,
        tag: `wf-task-${String(notification?.history_id || "")}`,
      });
    } catch (error) {
      // noop
    }
  };

  const publishTaskNotifications = (notifications = []) => {
    if (!Array.isArray(notifications) || notifications.length === 0) {
      return;
    }

    const shouldUseBrowserNotifications =
      typeof document !== "undefined" &&
      document.visibilityState !== "visible" &&
      "Notification" in window &&
      Notification.permission === "granted";

    notifications.forEach((item) => {
      const message = String(item?.message || "").trim();
      if (!message) return;

      if (shouldUseBrowserNotifications) {
        showTaskBrowserNotification(item);
      } else {
        showClientFlash("success", message);
      }
    });
  };

  const isTaskRealtimeSyncBlocked = () => {
    if (taskDetailModal instanceof HTMLElement && !taskDetailModal.hidden) {
      return true;
    }
    if (createTaskModal instanceof HTMLElement && !createTaskModal.hidden) {
      return true;
    }
    if (taskReviewModal instanceof HTMLElement && !taskReviewModal.hidden) {
      return true;
    }
    if (confirmModal instanceof HTMLElement && !confirmModal.hidden) {
      return true;
    }

    const activeElement = document.activeElement;
    if (activeElement instanceof HTMLElement) {
      const insideTaskList = activeElement.closest("[data-task-groups-list]");
      const isEditingField =
        activeElement instanceof HTMLInputElement ||
        activeElement instanceof HTMLTextAreaElement ||
        activeElement instanceof HTMLSelectElement ||
        activeElement.getAttribute("contenteditable") === "true";
      if (insideTaskList && isEditingField) {
        return true;
      }
    }

    if (document.querySelector('[data-task-autosave-form][data-autosave-submitting="1"]')) {
      return true;
    }

    return false;
  };

  const syncTaskSectionAfterWorkspaceChange = async () => {
    if (taskNotificationState.isSyncingTasks) return false;
    if (isTaskRealtimeSyncBlocked()) return false;

    taskNotificationState.isSyncingTasks = true;
    try {
      await refreshTasksSectionFromServer();
      taskNotificationState.pendingTasksSync = false;
      return true;
    } catch (error) {
      return false;
    } finally {
      taskNotificationState.isSyncingTasks = false;
    }
  };

  const pollTaskNotifications = async ({ initialize = false } = {}) => {
    if (taskNotificationState.isPolling) return;

    taskNotificationState.isPolling = true;
    try {
      const previousHistoryId = taskNotificationState.lastHistoryId;
      const data = await fetchTaskNotificationsFeed({
        initialize,
        sinceHistoryId: taskNotificationState.lastHistoryId,
      });

      const notifications = Array.isArray(data.notifications) ? data.notifications : [];
      const latestHistoryId = Number.parseInt(String(data.latest_history_id || "0"), 10) || 0;
      mergeTaskNotifications(notifications);

      let maxHistoryId = Math.max(taskNotificationState.lastHistoryId, latestHistoryId);
      notifications.forEach((item) => {
        const historyId = Number.parseInt(String(item?.history_id || "0"), 10) || 0;
        if (historyId > maxHistoryId) {
          maxHistoryId = historyId;
        }
      });

      taskNotificationState.lastHistoryId = Math.max(0, maxHistoryId);
      storeTaskNotificationHistoryId(taskNotificationState.lastHistoryId);

      const hasWorkspaceChanges =
        !initialize && taskNotificationState.lastHistoryId > previousHistoryId;
      if (hasWorkspaceChanges) {
        const synced = await syncTaskSectionAfterWorkspaceChange();
        if (!synced) {
          taskNotificationState.pendingTasksSync = true;
        }
      } else if (taskNotificationState.pendingTasksSync) {
        await syncTaskSectionAfterWorkspaceChange();
      }

      if (!initialize) {
        publishTaskNotifications(notifications);
      }
    } catch (error) {
      // Keep silent to avoid noisy flashes in background polling.
    } finally {
      taskNotificationState.isPolling = false;
    }
  };

  const startTaskNotificationsPolling = () => {
    if (!(notificationWorkspaceId > 0) || !(notificationUserId > 0)) {
      return;
    }

    taskNotificationState.notifications = readStoredTaskNotificationItems();
    taskNotificationState.seenHistoryId = readStoredTaskNotificationSeenHistoryId();
    taskNotificationState.lastHistoryId = Math.max(
      readStoredTaskNotificationHistoryId(),
      maxTaskNotificationHistoryId(taskNotificationState.notifications)
    );
    renderHeaderTaskNotifications();

    maybeEnableBrowserNotifications();
    if (taskNotificationState.lastHistoryId <= 0) {
      void pollTaskNotifications({ initialize: true });
    } else {
      void pollTaskNotifications();
    }

    if (taskNotificationState.intervalId !== null) {
      window.clearInterval(taskNotificationState.intervalId);
    }

    taskNotificationState.intervalId = window.setInterval(() => {
      void pollTaskNotifications();
    }, 20000);

    if (headerNotificationsToggle instanceof HTMLButtonElement) {
      headerNotificationsToggle.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        setHeaderNotificationDropdownOpen(!taskNotificationState.isDropdownOpen);
      });
    }

    if (headerNotificationsList instanceof HTMLElement) {
      headerNotificationsList.addEventListener("click", (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const row = target?.closest?.("[data-task-notification-item]");
        if (!(row instanceof HTMLElement)) return;

        const taskId = Number.parseInt(String(row.dataset.taskNotificationTaskId || "0"), 10) || 0;
        if (!(taskId > 0)) {
          return;
        }

        setHeaderNotificationDropdownOpen(false);
        const taskAnchorId = `task-${taskId}`;
        const taskRow = document.getElementById(taskAnchorId);
        setDashboardView("tasks", { updateUrl: true, taskId });
        if (taskRow instanceof HTMLElement) {
          openTaskDetailModal(taskRow, { updateUrl: true, scrollIntoView: true });
        }
      });
    }

    document.addEventListener("mousedown", (event) => {
      if (!taskNotificationState.isDropdownOpen) return;
      const target = event.target;
      if (!(target instanceof Node)) return;
      if (headerNotificationsRoot instanceof HTMLElement && headerNotificationsRoot.contains(target)) {
        return;
      }
      setHeaderNotificationDropdownOpen(false);
    });

    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape") return;
      if (!taskNotificationState.isDropdownOpen) return;
      setHeaderNotificationDropdownOpen(false);
    });

    window.addEventListener("focus", () => {
      if (!taskNotificationState.pendingTasksSync) return;
      void syncTaskSectionAfterWorkspaceChange();
    });

    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState !== "visible") return;
      if (!taskNotificationState.pendingTasksSync) return;
      void syncTaskSectionAfterWorkspaceChange();
    });
  };

  const syncSelectOptionsFromSource = (targetSelect, sourceSelect) => {
    if (!(targetSelect instanceof HTMLSelectElement)) return;
    if (!(sourceSelect instanceof HTMLSelectElement)) return;

    const previousValue = String(targetSelect.value || "").trim();
    targetSelect.innerHTML = "";
    Array.from(sourceSelect.options).forEach((option) => {
      if (!(option instanceof HTMLOptionElement)) return;
      targetSelect.append(option.cloneNode(true));
    });

    targetSelect.disabled = sourceSelect.disabled;
    if (previousValue && Array.from(targetSelect.options).some((option) => option.value === previousValue)) {
      targetSelect.value = previousValue;
    }
  };

  const syncSelectOptionsFromHtml = (targetSelect, optionsHtml, { disabled = null } = {}) => {
    if (!(targetSelect instanceof HTMLSelectElement)) return;
    const nextOptionsHtml = String(optionsHtml || "").trim();
    if (!nextOptionsHtml) return;

    const previousValue = String(targetSelect.value || "").trim();
    targetSelect.innerHTML = nextOptionsHtml;
    if (previousValue && Array.from(targetSelect.options).some((option) => option.value === previousValue)) {
      targetSelect.value = previousValue;
    }

    if (typeof disabled === "boolean") {
      targetSelect.disabled = disabled;
    }
  };

  const parseLeadingIntegerFromText = (value) => {
    const match = String(value || "").match(/(\d+)/);
    if (!match) return null;
    const parsed = Number.parseInt(match[1], 10);
    return Number.isFinite(parsed) ? parsed : null;
  };

  const parseDashboardSummaryFromDocument = (doc) => {
    if (!(doc instanceof Document)) return null;

    const totalText = doc.querySelector("[data-dashboard-stat-total]")?.textContent || "";
    const doneText = doc.querySelector("[data-dashboard-stat-done]")?.textContent || "";
    const dueTodayText = doc.querySelector("[data-dashboard-stat-due-today]")?.textContent || "";
    const urgentText = doc.querySelector("[data-dashboard-stat-urgent]")?.textContent || "";
    const myOpenText = doc.querySelector("[data-dashboard-stat-my-open]")?.textContent || "";

    const total = parseLeadingIntegerFromText(totalText);
    const done = parseLeadingIntegerFromText(doneText);
    const dueToday = parseLeadingIntegerFromText(dueTodayText);
    const urgent = parseLeadingIntegerFromText(urgentText);
    const myOpen = parseLeadingIntegerFromText(myOpenText);
    const completionRateMatch = String(doneText).match(/\((\d+)\s*%\)/);
    const completionRateFromText = completionRateMatch
      ? Number.parseInt(completionRateMatch[1], 10)
      : null;

    if (
      !Number.isFinite(total) &&
      !Number.isFinite(done) &&
      !Number.isFinite(dueToday) &&
      !Number.isFinite(urgent) &&
      !Number.isFinite(myOpen)
    ) {
      return null;
    }

    const normalizedTotal = Number.isFinite(total) ? total : 0;
    const normalizedDone = Number.isFinite(done) ? done : 0;
    const completionRate = Number.isFinite(completionRateFromText)
      ? completionRateFromText
      : normalizedTotal > 0
        ? Math.round((normalizedDone / normalizedTotal) * 100)
        : 0;

    return {
      total: normalizedTotal,
      done: normalizedDone,
      completion_rate: completionRate,
      due_today: Number.isFinite(dueToday) ? dueToday : 0,
      urgent: Number.isFinite(urgent) ? urgent : 0,
      my_open: Number.isFinite(myOpen) ? myOpen : 0,
    };
  };

  const fetchPanelSnapshot = async (action, fallbackErrorMessage) => runWithAppLoading(async () => {
    const params = new URLSearchParams(window.location.search || "");
    params.set("action", String(action || "").trim());
    const url = `${window.location.pathname}?${params.toString()}`;
    const response = await fetch(url, {
      method: "GET",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      credentials: "same-origin",
    });

    let data = null;
    try {
      data = await response.json();
    } catch (_error) {
      data = null;
    }

    if (!response.ok || !data || data.ok !== true) {
      throw new Error(
        (data && (data.error || data.message)) || fallbackErrorMessage
      );
    }

    return data;
  }, { label: "Atualizando..." });

  const fetchTaskPanelSnapshot = async () =>
    fetchPanelSnapshot("task_panel_snapshot", "Não foi possível atualizar tarefas.");

  const fetchDashboardDocumentLegacy = async (fallbackErrorMessage) => runWithAppLoading(async () => {
    const url = `${window.location.pathname}${window.location.search}`;
    const response = await fetch(url, {
      method: "GET",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "text/html",
      },
      credentials: "same-origin",
    });

    if (!response.ok) {
      throw new Error(fallbackErrorMessage);
    }

    const html = await response.text();
    const parser = new DOMParser();
    return parser.parseFromString(html, "text/html");
  }, { label: "Atualizando..." });

  const refreshTasksSectionFromServer = async () => {
    let snapshotData = null;
    let nextDoc = null;

    try {
      snapshotData = await fetchTaskPanelSnapshot();
      const panelHtml = String(snapshotData.tasks_panel_html || "").trim();
      if (!panelHtml) {
        throw new Error("Snapshot de tarefas vazio.");
      }
      const parser = new DOMParser();
      nextDoc = parser.parseFromString(panelHtml, "text/html");
    } catch (_snapshotError) {
      snapshotData = null;
      nextDoc = await fetchDashboardDocumentLegacy("Não foi possível atualizar tarefas.");
    }

    const currentGroupsList =
      taskGroupsListElement instanceof HTMLElement
        ? taskGroupsListElement
        : document.querySelector("[data-task-groups-list]");
    const nextGroupsList = nextDoc.querySelector("[data-task-groups-list]");
    if (currentGroupsList instanceof HTMLElement && nextGroupsList instanceof HTMLElement) {
      currentGroupsList.innerHTML = nextGroupsList.innerHTML;
    }

    const currentVisible = document.querySelector("[data-board-visible-count]");
    const nextVisible = nextDoc.querySelector("[data-board-visible-count]");
    if (currentVisible instanceof HTMLElement && nextVisible instanceof HTMLElement) {
      currentVisible.textContent = nextVisible.textContent || currentVisible.textContent;
    }

    if (snapshotData && snapshotData.summary && typeof snapshotData.summary === "object") {
      renderDashboardSummary(snapshotData.summary);
    } else {
      const summary = parseDashboardSummaryFromDocument(nextDoc);
      if (summary) {
        renderDashboardSummary(summary);
      }
    }
    if (snapshotData && snapshotData.undo_state && typeof snapshotData.undo_state === "object") {
      syncTaskHistoryControls(snapshotData.undo_state);
    }

    if (createTaskGroupInput instanceof HTMLSelectElement) {
      const createTaskGroupOptionsHtml = String(
        snapshotData?.create_task_group_options_html || ""
      ).trim();
      if (createTaskGroupOptionsHtml) {
        const previousValue = createTaskGroupInput.value;
        createTaskGroupInput.innerHTML = createTaskGroupOptionsHtml;
        if (
          previousValue &&
          Array.from(createTaskGroupInput.options).some(
            (option) => option.value === previousValue
          )
        ) {
          createTaskGroupInput.value = previousValue;
        }
        if (typeof snapshotData?.has_task_group_access === "boolean") {
          createTaskGroupInput.disabled = !snapshotData.has_task_group_access;
        }
      } else {
        syncSelectOptionsFromSource(
          createTaskGroupInput,
          nextDoc.querySelector("[data-create-task-group-input]")
        );
      }
      syncInlineSelectPicker(createTaskGroupInput);
    }

    const currentGroupFilterSelect = taskFilterForm?.querySelector('select[name="group"]');
    const nextGroupFilterSelect = nextDoc.querySelector('[data-task-filter-form] select[name="group"]');
    syncSelectOptionsFromSource(currentGroupFilterSelect, nextGroupFilterSelect);
    syncInlineSelectPicker(currentGroupFilterSelect);

    const currentCreatorFilterSelect = taskFilterForm?.querySelector('select[name="created_by"]');
    const nextCreatorFilterSelect = nextDoc.querySelector(
      '[data-task-filter-form] select[name="created_by"]'
    );
    syncSelectOptionsFromSource(currentCreatorFilterSelect, nextCreatorFilterSelect);
    syncInlineSelectPicker(currentCreatorFilterSelect);

    const currentAssigneeFilterSelect = taskFilterForm?.querySelector('select[name="assignee"]');
    const nextAssigneeFilterSelect = nextDoc.querySelector(
      '[data-task-filter-form] select[name="assignee"]'
    );
    syncSelectOptionsFromSource(currentAssigneeFilterSelect, nextAssigneeFilterSelect);
    syncInlineSelectPicker(currentAssigneeFilterSelect);

    if (taskGroupsDatalist instanceof HTMLDataListElement) {
      const nextGroupsDatalist = nextDoc.querySelector("#task-group-options");
      if (nextGroupsDatalist instanceof HTMLDataListElement) {
        taskGroupsDatalist.innerHTML = nextGroupsDatalist.innerHTML;
      }
    }

    if (typeof applyStoredTaskGroupOrder === "function") {
      applyStoredTaskGroupOrder();
    }

    const taskGroupsRoot =
      taskGroupsListElement instanceof HTMLElement
        ? taskGroupsListElement
        : document.querySelector("[data-task-groups-list]");
    if (taskGroupsRoot instanceof HTMLElement) {
      taskGroupsRoot.querySelectorAll("[data-task-group]").forEach((section) => {
        setTaskGroupCollapsed(section, resolveInitialGroupCollapsedState("tasks", section), {
          persist: false,
        });
        setTaskGroupDoneHidden(section, resolveInitialTaskGroupDoneHiddenState(section), {
          persist: false,
          refresh: false,
        });
        refreshTaskGroupSection(section);
      });

      taskGroupsRoot.querySelectorAll("[data-task-autosave-form]").forEach((form) => {
        syncTaskRevisionBadge(form);
        syncTaskOverdueBadge(form);
      });

      taskGroupsRoot.querySelectorAll("[data-task-item]").forEach((taskItem) => {
        if (!(taskItem instanceof HTMLElement)) return;
        const titleTagField = taskItem.querySelector("[data-task-title-tag]");
        const titleTagValue =
          titleTagField instanceof HTMLInputElement ? titleTagField.value || "" : "";
        syncTaskTitleTagBadge(taskItem, titleTagValue);
      });

      hydrateTaskInteractiveFields(taskGroupsRoot);
    }

    if (typeof syncTaskGroupInputs === "function") {
      syncTaskGroupInputs();
    }
  };

  const formatItemCountLabel = (count) => {
    const numericCount = Number.parseInt(String(count), 10);
    const safeCount = Number.isFinite(numericCount) && numericCount > 0 ? numericCount : 0;
    return `${safeCount} ${safeCount === 1 ? "item" : "itens"}`;
  };

  const refreshVaultSectionFromServer = async () => {
    let snapshotData = null;
    let nextDoc = null;

    try {
      snapshotData = await fetchPanelSnapshot(
        "vault_panel_snapshot",
        "Não foi possível atualizar o cofre."
      );
      const panelHtml = String(snapshotData.panel_html || "").trim();
      if (!panelHtml) {
        throw new Error("Snapshot de cofre vazio.");
      }

      const parser = new DOMParser();
      nextDoc = parser.parseFromString(panelHtml, "text/html");
    } catch (_snapshotError) {
      snapshotData = null;
      nextDoc = await fetchDashboardDocumentLegacy("Não foi possível atualizar o cofre.");
    }

    const currentGroupsList = document.querySelector("#vault .vault-groups-list");
    const nextGroupsList = nextDoc.querySelector("#vault .vault-groups-list");
    if (currentGroupsList instanceof HTMLElement && nextGroupsList instanceof HTMLElement) {
      currentGroupsList.replaceWith(nextGroupsList);
    }

    const currentTotal = document.querySelector("[data-vault-total-count]");
    const nextTotal = nextDoc.querySelector("[data-vault-total-count]");
    if (currentTotal instanceof HTMLElement) {
      if (nextTotal instanceof HTMLElement) {
        currentTotal.textContent = nextTotal.textContent || currentTotal.textContent;
      } else {
        const nextEntriesCount = document.querySelectorAll("#vault [data-vault-entry]").length;
        currentTotal.textContent = formatItemCountLabel(nextEntriesCount);
      }
    }

    const vaultOptionsHtml = String(snapshotData?.group_options_html || "").trim();
    if (vaultOptionsHtml) {
      const hasGroupAccess = snapshotData?.has_group_access === true;
      syncSelectOptionsFromHtml(vaultEntryGroupField, vaultOptionsHtml, {
        disabled: !hasGroupAccess,
      });
      syncSelectOptionsFromHtml(vaultEntryEditGroupField, vaultOptionsHtml, {
        disabled: !hasGroupAccess,
      });
    } else {
      syncSelectOptionsFromSource(vaultEntryGroupField, nextDoc.querySelector("[data-vault-entry-group]"));
      syncSelectOptionsFromSource(
        vaultEntryEditGroupField,
        nextDoc.querySelector("[data-vault-entry-edit-group]")
      );
    }

    document.querySelectorAll("#vault [data-vault-group]").forEach((section) => {
      setVaultGroupCollapsed(section, resolveInitialGroupCollapsedState("vault", section), {
        persist: false,
      });
    });

    document.querySelectorAll("#vault [data-vault-password-cell]").forEach((cell) => {
      syncVaultPasswordCell(cell, false);
    });
  };

  const refreshDueSectionFromServer = async () => {
    let snapshotData = null;
    let nextDoc = null;

    try {
      snapshotData = await fetchPanelSnapshot(
        "due_panel_snapshot",
        "Não foi possível atualizar os vencimentos."
      );
      const panelHtml = String(snapshotData.panel_html || "").trim();
      if (!panelHtml) {
        throw new Error("Snapshot de vencimentos vazio.");
      }

      const parser = new DOMParser();
      nextDoc = parser.parseFromString(panelHtml, "text/html");
    } catch (_snapshotError) {
      snapshotData = null;
      nextDoc = await fetchDashboardDocumentLegacy("Não foi possível atualizar os vencimentos.");
    }

    const currentGroupsList = document.querySelector("#dues .due-groups-list");
    const nextGroupsList = nextDoc.querySelector("#dues .due-groups-list");
    if (currentGroupsList instanceof HTMLElement && nextGroupsList instanceof HTMLElement) {
      currentGroupsList.replaceWith(nextGroupsList);
    }

    const currentTotal = document.querySelector("[data-due-total-count]");
    const nextTotal = nextDoc.querySelector("[data-due-total-count]");
    if (currentTotal instanceof HTMLElement) {
      if (nextTotal instanceof HTMLElement) {
        currentTotal.textContent = nextTotal.textContent || currentTotal.textContent;
      } else {
        const nextEntriesCount = document.querySelectorAll("#dues [data-due-entry]").length;
        currentTotal.textContent = formatItemCountLabel(nextEntriesCount);
      }
    }

    const dueOptionsHtml = String(snapshotData?.group_options_html || "").trim();
    if (dueOptionsHtml) {
      const hasGroupAccess = snapshotData?.has_group_access === true;
      syncSelectOptionsFromHtml(dueEntryGroupField, dueOptionsHtml, {
        disabled: !hasGroupAccess,
      });
      syncSelectOptionsFromHtml(dueEntryEditGroupField, dueOptionsHtml, {
        disabled: !hasGroupAccess,
      });
    } else {
      syncSelectOptionsFromSource(dueEntryGroupField, nextDoc.querySelector("[data-due-entry-group]"));
      syncSelectOptionsFromSource(
        dueEntryEditGroupField,
        nextDoc.querySelector("[data-due-entry-edit-group]")
      );
    }

    document.querySelectorAll("#dues [data-due-group]").forEach((section) => {
      setDueGroupCollapsed(section, resolveInitialGroupCollapsedState("dues", section), {
        persist: false,
      });
    });
  };

  const refreshInventorySectionFromServer = async () => {
    let snapshotData = null;
    let nextDoc = null;

    try {
      snapshotData = await fetchPanelSnapshot(
        "inventory_panel_snapshot",
        "Não foi possível atualizar o estoque."
      );
      const panelHtml = String(snapshotData.panel_html || "").trim();
      if (!panelHtml) {
        throw new Error("Snapshot de estoque vazio.");
      }

      const parser = new DOMParser();
      nextDoc = parser.parseFromString(panelHtml, "text/html");
    } catch (_snapshotError) {
      snapshotData = null;
      nextDoc = await fetchDashboardDocumentLegacy("Não foi possível atualizar o estoque.");
    }

    const currentGroupsList = document.querySelector("#inventory .inventory-groups-list");
    const nextGroupsList = nextDoc.querySelector("#inventory .inventory-groups-list");
    if (currentGroupsList instanceof HTMLElement && nextGroupsList instanceof HTMLElement) {
      currentGroupsList.replaceWith(nextGroupsList);
    }

    const currentTotal = document.querySelector("[data-inventory-total-count]");
    const nextTotal = nextDoc.querySelector("[data-inventory-total-count]");
    if (currentTotal instanceof HTMLElement) {
      if (nextTotal instanceof HTMLElement) {
        currentTotal.textContent = nextTotal.textContent || currentTotal.textContent;
      } else {
        const nextEntriesCount = document.querySelectorAll("#inventory [data-inventory-entry]").length;
        currentTotal.textContent = formatItemCountLabel(nextEntriesCount);
      }
    }

    const inventoryOptionsHtml = String(snapshotData?.group_options_html || "").trim();
    if (inventoryOptionsHtml) {
      const hasGroupAccess = snapshotData?.has_group_access === true;
      syncSelectOptionsFromHtml(inventoryEntryGroupField, inventoryOptionsHtml, {
        disabled: !hasGroupAccess,
      });
      syncSelectOptionsFromHtml(inventoryEntryEditGroupField, inventoryOptionsHtml, {
        disabled: !hasGroupAccess,
      });
    } else {
      syncSelectOptionsFromSource(
        inventoryEntryGroupField,
        nextDoc.querySelector("[data-inventory-entry-group]")
      );
      syncSelectOptionsFromSource(
        inventoryEntryEditGroupField,
        nextDoc.querySelector("[data-inventory-entry-edit-group]")
      );
    }

    document.querySelectorAll("#inventory [data-inventory-group]").forEach((section) => {
      setInventoryGroupCollapsed(section, resolveInitialGroupCollapsedState("inventory", section), {
        persist: false,
      });
    });
  };

  const refreshWorkspaceUsersSectionFromServer = async () => {
    let snapshotData = null;
    let nextDoc = null;

    try {
      snapshotData = await fetchPanelSnapshot(
        "users_panel_snapshot",
        "Não foi possível atualizar os usuários do workspace."
      );
      const panelHtml = String(snapshotData.panel_html || "").trim();
      if (!panelHtml) {
        throw new Error("Snapshot de usuários vazio.");
      }

      const parser = new DOMParser();
      nextDoc = parser.parseFromString(panelHtml, "text/html");
    } catch (_snapshotError) {
      snapshotData = null;
      nextDoc = await fetchDashboardDocumentLegacy(
        "Não foi possível atualizar os usuários do workspace."
      );
    }

    const currentUsersGrid = document.querySelector("#users .users-settings-grid");
    const nextUsersGrid = nextDoc.querySelector("#users .users-settings-grid");
    if (!(currentUsersGrid instanceof HTMLElement) || !(nextUsersGrid instanceof HTMLElement)) {
      throw new Error("Não foi possível atualizar os usuários do workspace.");
    }
    currentUsersGrid.replaceWith(nextUsersGrid);
    initializeWorkspaceSidebarToolsForms();

    const currentWorkspacePickerList = document.querySelector(
      ".workspace-sidebar-picker-list"
    );
    const workspacePickerListHtml = String(
      snapshotData?.workspace_picker_list_html || ""
    ).trim();
    if (currentWorkspacePickerList instanceof HTMLElement) {
      if (workspacePickerListHtml) {
        currentWorkspacePickerList.innerHTML = workspacePickerListHtml;
      } else {
        const nextWorkspacePickerList = nextDoc.querySelector(
          ".workspace-sidebar-picker-list"
        );
        if (nextWorkspacePickerList instanceof HTMLElement) {
          currentWorkspacePickerList.innerHTML = nextWorkspacePickerList.innerHTML;
        }
      }
    }

    const currentWorkspacePickerSummaryMain = document.querySelector(
      ".workspace-sidebar-picker-summary-main"
    );
    if (currentWorkspacePickerSummaryMain instanceof HTMLElement) {
      const workspacePickerSummaryHtml = String(
        snapshotData?.workspace_picker_summary_html || ""
      ).trim();
      if (workspacePickerSummaryHtml) {
        currentWorkspacePickerSummaryMain.outerHTML = workspacePickerSummaryHtml;
      } else {
        const nextWorkspacePickerSummaryMain = nextDoc.querySelector(
          ".workspace-sidebar-picker-summary-main"
        );
        if (nextWorkspacePickerSummaryMain instanceof HTMLElement) {
          currentWorkspacePickerSummaryMain.outerHTML =
            nextWorkspacePickerSummaryMain.outerHTML;
        } else {
          const currentWorkspacePickerTitle = document.querySelector(
            ".workspace-sidebar-picker-title"
          );
          const workspacePickerTitle = String(snapshotData?.workspace_picker_title || "").trim();
          if (currentWorkspacePickerTitle instanceof HTMLElement && workspacePickerTitle) {
            currentWorkspacePickerTitle.textContent = workspacePickerTitle;
          }
        }
      }
    }
  };

  const submitVaultActionForm = async (
    form,
    {
      onSuccess = null,
      successMessage = "",
      showSuccess = true,
      fallbackError = "Falha ao atualizar dado de acesso.",
    } = {}
  ) => {
    if (!(form instanceof HTMLFormElement)) return false;
    if (form.dataset.submitting === "1") return false;

    form.dataset.submitting = "1";
    try {
      const data = await postFormJson(form);
      if (typeof onSuccess === "function") {
        onSuccess(data);
      }

      await refreshVaultSectionFromServer();

      if (showSuccess) {
        const message = String(data?.message || "").trim() || successMessage;
        if (message) {
          showClientFlash("success", message);
        }
      }

      return true;
    } catch (error) {
      showClientFlash("error", error instanceof Error ? error.message : fallbackError);
      throw error;
    } finally {
      delete form.dataset.submitting;
    }
  };

  const submitDueActionForm = async (
    form,
    {
      onSuccess = null,
      successMessage = "",
      showSuccess = true,
      fallbackError = "Falha ao atualizar vencimento.",
    } = {}
  ) => {
    if (!(form instanceof HTMLFormElement)) return false;
    if (form.dataset.submitting === "1") return false;

    form.dataset.submitting = "1";
    try {
      const data = await postFormJson(form);
      if (typeof onSuccess === "function") {
        onSuccess(data);
      }

      await refreshDueSectionFromServer();

      if (showSuccess) {
        const message = String(data?.message || "").trim() || successMessage;
        if (message) {
          showClientFlash("success", message);
        }
      }

      return true;
    } catch (error) {
      showClientFlash("error", error instanceof Error ? error.message : fallbackError);
      throw error;
    } finally {
      delete form.dataset.submitting;
    }
  };

  const resolvePostActionName = (form) => {
    if (!(form instanceof HTMLFormElement)) return "";
    const actionField = form.querySelector('input[name="action"]');
    if (!(actionField instanceof HTMLInputElement)) return "";
    return String(actionField.value || "").trim();
  };

  const workspaceSidebarToolLabels = {
    vault: "Gerenciador de acessos",
    inventory: "Estoque",
    accounting: "Contabilidade",
  };

  const normalizeWorkspaceSidebarToolCandidate = (value) => {
    let normalized = String(value || "").trim().toLowerCase();
    if (normalized === "dues") {
      normalized = "accounting";
    }

    return Object.prototype.hasOwnProperty.call(workspaceSidebarToolLabels, normalized)
      ? normalized
      : "";
  };

  const workspaceSidebarToolRows = (form) => {
    if (!(form instanceof HTMLFormElement)) return [];
    const list = form.querySelector("[data-sidebar-tools-list]");
    if (!(list instanceof HTMLElement)) return [];
    return Array.from(list.querySelectorAll("[data-sidebar-tool-key]")).filter(
      (row) => row instanceof HTMLElement
    );
  };

  const syncWorkspaceSidebarToolsFormState = (form) => {
    if (!(form instanceof HTMLFormElement)) return;

    const list = form.querySelector("[data-sidebar-tools-list]");
    if (!(list instanceof HTMLElement)) return;

    const rows = workspaceSidebarToolRows(form);
    const seenTools = new Set();
    rows.forEach((row) => {
      const toolKey = normalizeWorkspaceSidebarToolCandidate(row.dataset.sidebarToolKey || "");
      if (!toolKey || seenTools.has(toolKey)) {
        row.remove();
        return;
      }

      seenTools.add(toolKey);
      row.dataset.sidebarToolKey = toolKey;

      const hiddenField = row.querySelector("[data-sidebar-tool-input]");
      if (hiddenField instanceof HTMLInputElement) {
        hiddenField.value = toolKey;
      }

      const label = row.querySelector(".workspace-sidebar-tool-item-label");
      if (label instanceof HTMLElement) {
        label.textContent = workspaceSidebarToolLabels[toolKey] || toolKey;
      }
    });

    const activeRows = workspaceSidebarToolRows(form);
    activeRows.forEach((row, index) => {
      const moveUp = row.querySelector('[data-sidebar-tools-move="up"]');
      const moveDown = row.querySelector('[data-sidebar-tools-move="down"]');
      if (moveUp instanceof HTMLButtonElement) {
        moveUp.disabled = index <= 0;
      }
      if (moveDown instanceof HTMLButtonElement) {
        moveDown.disabled = index >= activeRows.length - 1;
      }
    });

    const emptyState = form.querySelector("[data-sidebar-tools-empty]");
    if (emptyState instanceof HTMLElement) {
      emptyState.hidden = activeRows.length > 0;
    }

    const addSelect = form.querySelector("[data-sidebar-tools-add-select]");
    const addButton = form.querySelector("[data-sidebar-tools-add-button]");
    if (addSelect instanceof HTMLSelectElement) {
      let hasAvailableOption = false;
      Array.from(addSelect.options).forEach((option) => {
        const key = normalizeWorkspaceSidebarToolCandidate(option.value);
        if (!key) return;
        const isUsed = seenTools.has(key);
        option.disabled = isUsed;
        option.hidden = isUsed;
        if (!isUsed) {
          hasAvailableOption = true;
        }
      });

      const selectedTool = normalizeWorkspaceSidebarToolCandidate(addSelect.value);
      if (!selectedTool || seenTools.has(selectedTool)) {
        const nextOption = Array.from(addSelect.options).find((option) => {
          const key = normalizeWorkspaceSidebarToolCandidate(option.value);
          return key !== "" && !option.disabled;
        });
        addSelect.value = nextOption ? nextOption.value : "";
      }

      if (addButton instanceof HTMLButtonElement) {
        addButton.disabled = !hasAvailableOption;
      }
    }
  };

  const createWorkspaceSidebarToolRow = (form, toolKey) => {
    if (!(form instanceof HTMLFormElement)) return null;
    const normalizedTool = normalizeWorkspaceSidebarToolCandidate(toolKey);
    if (!normalizedTool) return null;

    const template = form.querySelector("template[data-sidebar-tools-row-template]");
    if (!(template instanceof HTMLTemplateElement)) return null;

    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector("[data-sidebar-tool-key]");
    if (!(row instanceof HTMLElement)) return null;
    row.dataset.sidebarToolKey = normalizedTool;

    const hiddenField = row.querySelector("[data-sidebar-tool-input]");
    if (hiddenField instanceof HTMLInputElement) {
      hiddenField.value = normalizedTool;
    }

    const label = row.querySelector(".workspace-sidebar-tool-item-label");
    if (label instanceof HTMLElement) {
      label.textContent = workspaceSidebarToolLabels[normalizedTool] || normalizedTool;
    }

    return row;
  };

  const initializeWorkspaceSidebarToolsForms = (scope = document) => {
    const root = scope instanceof Element || scope instanceof Document ? scope : document;
    root
      .querySelectorAll("[data-sidebar-tools-form]")
      .forEach((form) => {
        if (!(form instanceof HTMLFormElement)) return;
        syncWorkspaceSidebarToolsFormState(form);
      });
  };

  const workspaceUsersActionNames = new Set([
    "workspace_update_profile",
    "workspace_update_name",
    "workspace_add_member",
    "add_workspace_member",
    "workspace_accept_invitation",
    "workspace_decline_invitation",
    "workspace_cancel_invitation",
    "workspace_promote_member",
    "workspace_demote_member",
    "workspace_remove_member",
  ]);

  const submitWorkspaceUsersActionForm = async (
    form,
    {
      successMessage = "",
      fallbackError = "Falha ao atualizar usuários do workspace.",
    } = {}
  ) => {
    if (!(form instanceof HTMLFormElement)) return false;
    if (form.dataset.submitting === "1") return false;

    const submitButtons = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
    form.dataset.submitting = "1";
    form.classList.add("is-saving");
    submitButtons.forEach((button) => {
      if (
        button instanceof HTMLButtonElement ||
        button instanceof HTMLInputElement
      ) {
        button.disabled = true;
      }
    });

    try {
      const data = await postFormJson(form);
      await refreshWorkspaceUsersSectionFromServer();
      const message = String(data?.message || "").trim() || successMessage;
      if (message) {
        showClientFlash("success", message);
      }
      return true;
    } catch (error) {
      showClientFlash("error", error instanceof Error ? error.message : fallbackError);
      throw error;
    } finally {
      form.classList.remove("is-saving");
      delete form.dataset.submitting;
      if (form.isConnected) {
        submitButtons.forEach((button) => {
          if (
            button instanceof HTMLButtonElement ||
            button instanceof HTMLInputElement
          ) {
            button.disabled = false;
          }
        });
      }
    }
  };

  const submitInventoryActionForm = async (
    form,
    {
      onSuccess = null,
      successMessage = "",
      showSuccess = true,
      fallbackError = "Falha ao atualizar estoque.",
    } = {}
  ) => {
    if (!(form instanceof HTMLFormElement)) return false;
    if (form.dataset.submitting === "1") return false;

    form.dataset.submitting = "1";
    try {
      const data = await postFormJson(form);
      if (typeof onSuccess === "function") {
        onSuccess(data);
      }

      await refreshInventorySectionFromServer();

      if (showSuccess) {
        const message = String(data?.message || "").trim() || successMessage;
        if (message) {
          showClientFlash("success", message);
        }
      }

      return true;
    } catch (error) {
      showClientFlash("error", error instanceof Error ? error.message : fallbackError);
      throw error;
    } finally {
      delete form.dataset.submitting;
    }
  };

  const normalizeAccountingLabelField = (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const labelField = form.querySelector('input[name="label"]');
    if (labelField instanceof HTMLInputElement) {
      applyFirstLetterUppercaseToInput(labelField);
    }
  };

  const parseAccountingCurrencyToCents = (value, { allowNegative = false } = {}) => {
    const rawValue = String(value || "").trim();
    if (!rawValue) return null;

    let normalized = rawValue.replace(/R\$\s*/gi, "").replace(/\s+/g, "");
    let isNegative = false;
    if (normalized.startsWith("-") || normalized.startsWith("+")) {
      isNegative = normalized.startsWith("-");
      normalized = normalized.slice(1);
    }
    if (normalized.includes(",")) {
      if (normalized.includes(".")) {
        normalized = normalized.replace(/\./g, "");
      }
      normalized = normalized.replace(",", ".");
    }

    if (!/^\d+(?:\.\d{1,2})?$/.test(normalized)) {
      return null;
    }

    const [integerPart = "0", decimalPartRaw = ""] = normalized.split(".", 2);
    const decimalPart = `${decimalPartRaw}00`.slice(0, 2);
    const cents = Number.parseInt(integerPart, 10) * 100 + Number.parseInt(decimalPart, 10);
    if (!Number.isFinite(cents) || cents < 0) return null;
    if (isNegative && !allowNegative) return null;
    return isNegative ? -cents : cents;
  };

  const formatAccountingCentsToInputValue = (value, { allowNegative = false } = {}) => {
    const parsed = Number.parseInt(String(value || "").trim(), 10);
    if (!Number.isFinite(parsed)) return "";
    if (parsed < 0 && !allowNegative) return "";

    const absoluteValue = Math.abs(parsed);
    return `${parsed < 0 ? "-R$ " : "R$ "}${new Intl.NumberFormat("pt-BR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(absoluteValue / 100)}`;
  };

  const isAccountingCurrencyInputField = (field) =>
    field instanceof HTMLInputElement &&
    field.type !== "hidden" &&
    !field.readOnly &&
    !field.disabled &&
    ["amount_value", "total_amount_value", "opening_balance_value"].includes(field.name);

  const getAccountingCurrencyFieldState = (field) => {
    const allowNegative = field instanceof HTMLInputElement && field.dataset.accountingAllowNegative === "1";
    const rawValue = field instanceof HTMLInputElement ? String(field.value || "").trim() : "";
    const parsedCents = field instanceof HTMLInputElement
      ? parseAccountingCurrencyToCents(rawValue, { allowNegative })
      : null;
    const isNegative =
      allowNegative &&
      ((parsedCents !== null && parsedCents < 0) || field?.dataset?.accountingNegative === "1");

    let digits = "";
    if (parsedCents !== null) {
      digits = String(Math.abs(parsedCents));
    } else {
      const extractedDigits = rawValue.replace(/\D/g, "");
      if (extractedDigits) {
        digits = String(Number.parseInt(extractedDigits, 10) || 0);
      }
    }

    if (!rawValue) {
      digits = "";
    }

    return {
      allowNegative,
      isNegative,
      digits,
    };
  };

  const setAccountingCurrencyFieldDigits = (field, digits, { isNegative = null } = {}) => {
    if (!(field instanceof HTMLInputElement)) return;

    const state = getAccountingCurrencyFieldState(field);
    const allowNegative = state.allowNegative;
    const nextNegative = allowNegative && (isNegative === null ? state.isNegative : isNegative);
    const normalizedDigits = String(digits || "").replace(/\D/g, "");

    if (!normalizedDigits) {
      field.value = "";
      if (allowNegative) {
        field.dataset.accountingNegative = nextNegative ? "1" : "0";
      }
      return;
    }

    const cents = Number.parseInt(normalizedDigits, 10);
    if (!Number.isFinite(cents)) {
      field.value = "";
      return;
    }

    if (allowNegative) {
      field.dataset.accountingNegative = nextNegative ? "1" : "0";
    }
    field.value = formatAccountingCentsToInputValue(nextNegative ? -cents : cents, { allowNegative });

    const caret = field.value.length;
    window.requestAnimationFrame(() => {
      if (!(document.activeElement instanceof HTMLInputElement) || document.activeElement !== field) {
        return;
      }
      try {
        field.setSelectionRange(caret, caret);
      } catch (_error) {}
    });
  };

  const formatAccountingCurrencyInputFieldWhileTyping = (field) => {
    if (!isAccountingCurrencyInputField(field)) return;

    const allowNegative = field.dataset.accountingAllowNegative === "1";
    const rawValue = String(field.value || "").trim();
    const isNegative =
      allowNegative && (rawValue.startsWith("-") || field.dataset.accountingNegative === "1");
    const digits = rawValue.replace(/\D/g, "");

    if (!digits) {
      field.value = "";
      if (allowNegative) {
        field.dataset.accountingNegative = isNegative ? "1" : "0";
      }
      return;
    }

    setAccountingCurrencyFieldDigits(field, digits, { isNegative });
  };

  const hasAccountingCurrencySelection = (field) =>
    field instanceof HTMLInputElement &&
    field.selectionStart !== null &&
    field.selectionEnd !== null &&
    field.selectionStart !== field.selectionEnd;

  const normalizeAccountingCurrencyInputField = (field) => {
    if (!(field instanceof HTMLInputElement)) return;
    const rawValue = String(field.value || "").trim();
    if (!rawValue) return;

    const allowNegative = field.dataset.accountingAllowNegative === "1";
    const cents = parseAccountingCurrencyToCents(rawValue, { allowNegative });
    if (cents === null) return;
    if (allowNegative) {
      field.dataset.accountingNegative = cents < 0 ? "1" : "0";
    }
    field.value = formatAccountingCentsToInputValue(cents, { allowNegative });
  };

  const calculateAccountingInstallmentAmountCents = (
    totalAmountCents,
    installmentNumber,
    installmentTotal
  ) => {
    const total = Math.max(0, Number.parseInt(String(totalAmountCents || "0"), 10) || 0);
    const current = Math.max(0, Number.parseInt(String(installmentNumber || "0"), 10) || 0);
    const count = Math.max(0, Number.parseInt(String(installmentTotal || "0"), 10) || 0);
    if (total <= 0 || current <= 0 || count <= 0) return 0;

    const baseAmount = Math.floor(total / count);
    const remainder = total - baseAmount * count;
    return baseAmount + (current <= remainder ? 1 : 0);
  };

  const syncAccountingInstallmentForm = (form) => {
    if (!(form instanceof HTMLFormElement)) return;

    const installmentToggle = form.querySelector("[data-accounting-installment-toggle]");
    const installmentFields = form.querySelector("[data-accounting-installment-fields]");
    const installmentProgressField = form.querySelector("[data-accounting-installment-progress]");
    const installmentNumberField = form.querySelector("[data-accounting-installment-number]");
    const installmentTotalCountField = form.querySelector("[data-accounting-installment-total-count]");
    const totalAmountField = form.querySelector("[data-accounting-installment-total-amount]");
    const primaryAmountField = form.querySelector("[data-accounting-primary-amount]");
    const monthlyToggle = form.querySelector("[data-accounting-monthly-toggle]");
    const monthlyFields = form.querySelector("[data-accounting-monthly-fields]");
    const monthlyDayField = form.querySelector("[data-accounting-monthly-day]");
    const typeSelect = form.querySelector("[data-accounting-type-select]");

    if (!(installmentToggle instanceof HTMLInputElement)) return;
    if (!(installmentFields instanceof HTMLElement)) return;
    if (!(installmentProgressField instanceof HTMLInputElement)) return;
    if (!(installmentNumberField instanceof HTMLSelectElement)) return;
    if (!(installmentTotalCountField instanceof HTMLSelectElement)) return;
    if (!(totalAmountField instanceof HTMLInputElement)) return;
    if (!(primaryAmountField instanceof HTMLInputElement)) return;

    if (typeSelect instanceof HTMLSelectElement) {
      installmentToggle.checked = typeSelect.value === "installment";
      if (monthlyToggle instanceof HTMLInputElement) {
        monthlyToggle.checked = typeSelect.value === "monthly";
      }
    }

    const isMonthlyDue = monthlyToggle instanceof HTMLInputElement && monthlyToggle.checked;
    if (monthlyToggle instanceof HTMLInputElement) {
      installmentToggle.disabled = isMonthlyDue;
      if (isMonthlyDue) {
        installmentToggle.checked = false;
      }
    }

    const isInstallment = installmentToggle.checked;
    installmentFields.hidden = !isInstallment;
    installmentProgressField.disabled = !isInstallment;
    installmentNumberField.disabled = !isInstallment;
    installmentTotalCountField.disabled = !isInstallment;
    totalAmountField.disabled = !isInstallment;
    totalAmountField.required = isInstallment;
    primaryAmountField.readOnly = isInstallment;
    if (monthlyFields instanceof HTMLElement) {
      monthlyFields.hidden = !isMonthlyDue;
    }
    if (monthlyDayField instanceof HTMLSelectElement) {
      monthlyDayField.disabled = !isMonthlyDue;
      monthlyDayField.required = isMonthlyDue;
      if (isMonthlyDue && !String(monthlyDayField.value || "").trim()) {
        monthlyDayField.value = String(new Date().getDate());
      }
    }
    if (monthlyToggle instanceof HTMLInputElement) {
      monthlyToggle.disabled = isInstallment;
      if (isInstallment) {
        monthlyToggle.checked = false;
      }
    }
    if (typeSelect instanceof HTMLSelectElement) {
      typeSelect.value = isInstallment ? "installment" : (isMonthlyDue ? "monthly" : "single");
    }

    let installmentTotal = Number.parseInt(installmentTotalCountField.value, 10);
    if (!Number.isFinite(installmentTotal) || installmentTotal < 2) {
      installmentTotal = 2;
    }

    let installmentNumber = Number.parseInt(installmentNumberField.value, 10);
    if (!Number.isFinite(installmentNumber) || installmentNumber < 1) {
      installmentNumber = 1;
    }
    if (installmentNumber > installmentTotal) {
      installmentNumber = installmentTotal;
    }

    const nextNumberOptions = [];
    for (let value = 1; value <= installmentTotal; value += 1) {
      nextNumberOptions.push(`<option value="${value}">${value}</option>`);
    }
    installmentNumberField.innerHTML = nextNumberOptions.join("");
    installmentNumberField.value = String(installmentNumber);
    installmentTotalCountField.value = String(installmentTotal);

    if (!isInstallment) {
      installmentProgressField.value = "";
      if (String(totalAmountField.value || "").trim() !== "") {
        primaryAmountField.value = totalAmountField.value;
      }
      return;
    }

    installmentProgressField.value = `${installmentNumber}/${installmentTotal}`;

    if (String(totalAmountField.value || "").trim() === "") {
      totalAmountField.value = primaryAmountField.value || "";
    }

    const totalAmountCents = parseAccountingCurrencyToCents(totalAmountField.value);
    if (totalAmountCents !== null) {
      primaryAmountField.value = formatAccountingCentsToInputValue(
        calculateAccountingInstallmentAmountCents(
          totalAmountCents,
          installmentNumber,
          installmentTotal
        )
      );
      return;
    }

    primaryAmountField.value = totalAmountField.value || "";
  };

  const refreshAccountingSectionFromServer = async () => {
    let snapshotData = null;
    let nextDoc = null;

    try {
      snapshotData = await fetchPanelSnapshot(
        "accounting_panel_snapshot",
        "Não foi possível atualizar a contabilidade."
      );
      const sheetHtml = String(snapshotData.accounting_sheet_html || "").trim();
      if (!sheetHtml) {
        throw new Error("Snapshot de contabilidade vazio.");
      }
      const parser = new DOMParser();
      nextDoc = parser.parseFromString(sheetHtml, "text/html");
    } catch (_snapshotError) {
      snapshotData = null;
      nextDoc = await fetchDashboardDocumentLegacy(
        "Não foi possível atualizar a contabilidade."
      );
    }

    const currentSheet = document.querySelector("#accounting .accounting-sheet");
    const nextSheet =
      nextDoc.querySelector(".accounting-sheet") ||
      nextDoc.querySelector("#accounting .accounting-sheet");
    if (!(currentSheet instanceof HTMLElement) || !(nextSheet instanceof HTMLElement)) {
      throw new Error("Não foi possível atualizar a contabilidade.");
    }

    currentSheet.replaceWith(nextSheet);
  };

  const submitAccountingActionForm = async (
    form,
    {
      showSuccess = true,
      successMessage = "",
      fallbackError = "Falha ao atualizar a contabilidade.",
      refresh = true,
    } = {}
  ) => {
    if (!(form instanceof HTMLFormElement)) return false;
    if (form.dataset.submitting === "1") return false;

    normalizeAccountingLabelField(form);
    syncAccountingInstallmentForm(form);

    form.dataset.submitting = "1";
    form.classList.add("is-saving");

    try {
      const data = await postFormJson(form);
      if (refresh) {
        await refreshAccountingSectionFromServer();
      }

      if (showSuccess) {
        const message = String(data?.message || "").trim() || successMessage;
        if (message) {
          showClientFlash("success", message);
        }
      }

      return true;
    } catch (error) {
      showClientFlash("error", error instanceof Error ? error.message : fallbackError);
      throw error;
    } finally {
      form.classList.remove("is-saving");
      delete form.dataset.submitting;
    }
  };

  const accountingAutosaveTimers = new WeakMap();
  const submitAccountingAutosaveForm = async (form, options = {}) => {
    if (!(form instanceof HTMLFormElement) || !form.isConnected) return false;
    if (form.dataset.submitting === "1") {
      form.dataset.accountingPending = "1";
      return false;
    }

    try {
      return await submitAccountingActionForm(form, {
        ...options,
        showSuccess: false,
        refresh: true,
      });
    } finally {
      if (form.isConnected && form.dataset.accountingPending === "1") {
        delete form.dataset.accountingPending;
        scheduleAccountingAutosave(form, 80, options);
      }
    }
  };

  const scheduleAccountingAutosave = (form, delay = 160, options = {}) => {
    if (!(form instanceof HTMLFormElement) || !form.isConnected) return;

    if (form.dataset.submitting === "1") {
      form.dataset.accountingPending = "1";
      return;
    }

    const previousTimer = accountingAutosaveTimers.get(form);
    if (previousTimer) {
      window.clearTimeout(previousTimer);
    }

    const nextTimer = window.setTimeout(() => {
      accountingAutosaveTimers.delete(form);
      if (!form.isConnected) return;
      if (typeof form.reportValidity === "function" && !form.reportValidity()) {
        return;
      }
      void submitAccountingAutosaveForm(form, options).catch(() => {});
    }, delay);

    accountingAutosaveTimers.set(form, nextTimer);
  };

  const closeAccountingEntryEditor = (entryRow, { reset = true } = {}) => {
    if (!(entryRow instanceof HTMLElement)) return;

    const summary = entryRow.querySelector("[data-accounting-entry-toggle]");
    const form = entryRow.querySelector(".accounting-entry-editor-form");
    if (!(summary instanceof HTMLButtonElement) || !(form instanceof HTMLFormElement)) return;

    if (reset) {
      form.reset();
      syncAccountingInstallmentForm(form);
    }

    form.hidden = true;
    summary.hidden = false;
    summary.setAttribute("aria-expanded", "false");
    entryRow.classList.remove("is-editing");
  };

  const openAccountingEntryEditor = (entryRow) => {
    if (!(entryRow instanceof HTMLElement)) return;

    const summary = entryRow.querySelector("[data-accounting-entry-toggle]");
    const form = entryRow.querySelector(".accounting-entry-editor-form");
    if (!(summary instanceof HTMLButtonElement) || !(form instanceof HTMLFormElement)) return;

    const sheet = entryRow.closest(".accounting-sheet");
    if (sheet instanceof HTMLElement) {
      sheet.querySelectorAll(".accounting-entry-row.is-editing").forEach((openRow) => {
        if (openRow !== entryRow && openRow instanceof HTMLElement) {
          closeAccountingEntryEditor(openRow);
        }
      });
    }

    summary.hidden = true;
    summary.setAttribute("aria-expanded", "true");
    form.hidden = false;
    entryRow.classList.add("is-editing");

    const labelField = form.querySelector('input[name="label"]');
    if (labelField instanceof HTMLInputElement) {
      labelField.focus();
      labelField.select();
      return;
    }

    const amountField = form.querySelector('input[name="amount_value"]');
    if (amountField instanceof HTMLInputElement && !amountField.readOnly) {
      amountField.focus();
      amountField.select();
    }
  };

  const closeAccountingOpeningBalanceEditor = ({ reset = true } = {}) => {
    const editor = document.querySelector(".accounting-opening-balance-editor");
    const toggle = editor?.querySelector("[data-accounting-opening-balance-toggle]");
    const form = editor?.querySelector(".accounting-opening-balance-form");
    if (!(editor instanceof HTMLElement)) return;
    if (!(toggle instanceof HTMLButtonElement) || !(form instanceof HTMLFormElement)) return;

    if (reset) {
      form.reset();
      const openingBalanceField = form.querySelector('input[name="opening_balance_value"]');
      if (openingBalanceField instanceof HTMLInputElement) {
        normalizeAccountingCurrencyInputField(openingBalanceField);
      }
    }

    form.hidden = true;
    toggle.hidden = false;
    toggle.setAttribute("aria-expanded", "false");
    editor.classList.remove("is-editing");
  };

  const openAccountingOpeningBalanceEditor = () => {
    const editor = document.querySelector(".accounting-opening-balance-editor");
    const toggle = editor?.querySelector("[data-accounting-opening-balance-toggle]");
    const form = editor?.querySelector(".accounting-opening-balance-form");
    if (!(editor instanceof HTMLElement)) return;
    if (!(toggle instanceof HTMLButtonElement) || !(form instanceof HTMLFormElement)) return;

    toggle.hidden = true;
    toggle.setAttribute("aria-expanded", "true");
    form.hidden = false;
    editor.classList.add("is-editing");

    const openingBalanceField = form.querySelector('input[name="opening_balance_value"]');
    if (openingBalanceField instanceof HTMLInputElement) {
      openingBalanceField.focus();
      openingBalanceField.select();
    }
  };

  const closeAccountingCreateToggle = (toggle, { reset = true } = {}) => {
    if (!(toggle instanceof HTMLDetailsElement)) return;

    const form = toggle.querySelector(".accounting-create-form");
    if (reset && form instanceof HTMLFormElement) {
      form.reset();
      syncAccountingInstallmentForm(form);
    }

    toggle.open = false;
  };

  const autosaveTimers = new WeakMap();
  const submitTaskAutosave = async (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.isConnected) return false;
    if (form.dataset.autosaveSubmitting === "1") return false;

    form.dataset.autosaveSubmitting = "1";
    form.classList.add("is-saving");
    let success = false;
    let shouldProcessPendingAutosave = true;

    try {
      const data = await postFormJson(form);
      if (!form.isConnected) {
        success = true;
        return true;
      }
      const task = data.task || {};
      const taskItem = form.closest("[data-task-item]");

      if (typeof task.reference_links_json === "string") {
        const linksField = form.querySelector("[data-task-reference-links-json]");
        if (linksField instanceof HTMLInputElement) {
          linksField.value = task.reference_links_json;
        }
      }
      if (typeof task.reference_images_json === "string") {
        const imagesField = ensureTaskHiddenField(form, {
          name: "reference_images_json",
          withName: false,
          dataSelector: "[data-task-reference-images-json]",
          dataAttrName: "data-task-reference-images-json",
        });
        if (imagesField instanceof HTMLInputElement) {
          imagesField.value = task.reference_images_json;
          imagesField.removeAttribute("name");
        }
      }
      if (typeof task.subtasks_json === "string") {
        const subtasksField = form.querySelector("[data-task-subtasks-json]");
        const subtasksDependencyField = ensureTaskHiddenField(form, {
          name: "subtasks_dependency_enabled",
          withName: true,
          dataSelector: "[data-task-subtasks-dependency]",
          dataAttrName: "data-task-subtasks-dependency",
        });
        const dependencyEnabled = Object.prototype.hasOwnProperty.call(
          task,
          "subtasks_dependency_enabled"
        )
          ? normalizeTaskSubtasksDependencyValue(task.subtasks_dependency_enabled, false)
          : readTaskSubtasksDependencyField(subtasksDependencyField, false);
        if (subtasksDependencyField instanceof HTMLInputElement) {
          writeTaskSubtasksDependencyField(subtasksDependencyField, dependencyEnabled);
        }
        if (subtasksField instanceof HTMLInputElement) {
          const subtasks = parseTaskSubtaskList(task.subtasks_json, 40, {
            enforceDependency: dependencyEnabled,
          });
          writeTaskSubtasksField(subtasksField, subtasks, {
            enforceDependency: dependencyEnabled,
          });
          if (taskItem instanceof HTMLElement) {
            renderTaskRowSubtasksProgress(taskItem, subtasks);
          }
          if (
            taskDetailContext &&
            taskDetailContext.form === form &&
            taskDetailContext.subtasksField instanceof HTMLInputElement
          ) {
            writeTaskSubtasksField(taskDetailContext.subtasksField, subtasks, {
              enforceDependency: dependencyEnabled,
            });
            if (taskDetailContext.subtasksDependencyField instanceof HTMLInputElement) {
              writeTaskSubtasksDependencyField(
                taskDetailContext.subtasksDependencyField,
                dependencyEnabled
              );
            }
          }
        }
      }
      if (Object.prototype.hasOwnProperty.call(task, "title_tag")) {
        const titleTagField = form.querySelector("[data-task-title-tag]");
        const titleTagColorField = form.querySelector("[data-task-title-tag-color]");
        const normalizedTag = normalizeTaskTitleTagValue(task.title_tag || "");
        const normalizedColor = normalizedTag
          ? resolveTaskTitleTagColor(
              normalizedTag,
              task.title_tag_color || (titleTagColorField instanceof HTMLInputElement ? titleTagColorField.value || "" : "")
            )
          : normalizeTaskTitleTagColorValue(
              task.title_tag_color || (titleTagColorField instanceof HTMLInputElement ? titleTagColorField.value || "" : ""),
              taskTitleTagDefaultColor
            );
        if (titleTagField instanceof HTMLInputElement) {
          titleTagField.value = normalizedTag;
        }
        if (titleTagColorField instanceof HTMLInputElement) {
          titleTagColorField.value = normalizedColor;
        }
        if (taskItem instanceof HTMLElement) {
          syncTaskTitleTagBadge(taskItem, normalizedTag, normalizedColor);
        }
      }
      if (Object.prototype.hasOwnProperty.call(task, "due_date")) {
        const dueDateField = form.querySelector("[data-due-date-input]");
        if (dueDateField instanceof HTMLInputElement) {
          dueDateField.value = task.due_date ? String(task.due_date) : "";
          syncDueDateDisplay(dueDateField);
        }
      }
      if (typeof task.status === "string") {
        const statusField = form.querySelector('select[name="status"]');
        if (statusField instanceof HTMLSelectElement) {
          statusField.value = task.status;
          syncSelectColor(statusField);
        }
      }
      if (typeof task.priority === "string") {
        const priorityField = form.querySelector('select[name="priority"]');
        if (priorityField instanceof HTMLSelectElement) {
          priorityField.value = task.priority;
          syncSelectColor(priorityField);
        }
      }
      if (Object.prototype.hasOwnProperty.call(task, "overdue_flag")) {
        const overdueField = form.querySelector("[data-task-overdue-flag]");
        if (overdueField instanceof HTMLInputElement) {
          overdueField.value = Number(task.overdue_flag) === 1 ? "1" : "0";
          syncTaskOverdueBadge(form);
        }
      }
      if (Object.prototype.hasOwnProperty.call(task, "overdue_since_date")) {
        const overdueSinceField = form.querySelector("[data-task-overdue-since-date]");
        if (overdueSinceField instanceof HTMLInputElement) {
          overdueSinceField.value = task.overdue_since_date ? String(task.overdue_since_date) : "";
        }
      }
      if (Object.prototype.hasOwnProperty.call(task, "overdue_days")) {
        const overdueDaysField = form.querySelector("[data-task-overdue-days]");
        if (overdueDaysField instanceof HTMLInputElement) {
          const nextValue = Math.max(0, Number.parseInt(task.overdue_days, 10) || 0);
          overdueDaysField.value = String(nextValue);
        }
      }
      if (Array.isArray(task.history)) {
        const historyField = ensureTaskHiddenField(form, {
          withName: false,
          dataSelector: "[data-task-history-json]",
          dataAttrName: "data-task-history-json",
        });
        if (historyField instanceof HTMLInputElement) {
          writeTaskHistoryField(historyField, task.history);
        }
      }
      if (Object.prototype.hasOwnProperty.call(task, "has_active_revision")) {
        const revisionStateField = ensureTaskHiddenField(form, {
          withName: false,
          dataSelector: "[data-task-has-active-revision]",
          dataAttrName: "data-task-has-active-revision",
        });
        if (revisionStateField instanceof HTMLInputElement) {
          writeTaskRevisionStateField(
            revisionStateField,
            Number.parseInt(String(task.has_active_revision || "0"), 10) === 1
          );
        }
      }
      syncTaskRevisionBadge(form);

      if (taskItem instanceof HTMLElement && typeof task.group_name === "string") {
        moveTaskItemToGroupDom(taskItem, task.group_name);
      }

      if (typeof task.updated_at === "string") {
        syncTaskExpectedUpdatedAt(form, task.updated_at);
      }
      refreshTaskUpdatedAtMeta(form, task.updated_at_label || "");
      renderDashboardSummary(data.dashboard);
      syncTaskHistoryControls(data.undo_state);
      if (taskDetailContext && taskDetailContext.form === form && taskDetailModal && !taskDetailModal.hidden) {
        populateTaskDetailModalFromRow(taskDetailContext);
        void hydrateTaskDetailPayloadFromServer(taskDetailContext, { force: true }).catch(() => {});
      }
      delete form.dataset.autosaveError;
      success = true;
    } catch (error) {
      if (isTaskConflictError(error)) {
        const payload = error.payload && typeof error.payload === "object" ? error.payload : {};
        const conflictTask = payload.task && typeof payload.task === "object" ? payload.task : {};
        if (typeof conflictTask.updated_at === "string") {
          syncTaskExpectedUpdatedAt(form, conflictTask.updated_at);
        }
        if (typeof conflictTask.updated_at_label === "string") {
          refreshTaskUpdatedAtMeta(form, conflictTask.updated_at_label);
        }
        shouldProcessPendingAutosave = false;
        delete form.dataset.autosavePending;
      }
      form.dataset.autosaveError = "1";
      showClientFlash("error", error instanceof Error ? error.message : "Falha ao salvar tarefa.");
    } finally {
      if (success) {
        const referenceImagesField = form.querySelector("[data-task-reference-images-json]");
        if (referenceImagesField instanceof HTMLInputElement) {
          referenceImagesField.removeAttribute("name");
        }
      }
      form.classList.remove("is-saving");
      delete form.dataset.autosaveSubmitting;
      if (shouldProcessPendingAutosave && form.dataset.autosavePending === "1") {
        delete form.dataset.autosavePending;
        scheduleTaskAutosave(form, 80);
      }
    }
    return success;
  };

  const scheduleTaskAutosave = (form, delay = 180) => {
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.isConnected) return;

    if (form.dataset.autosaveSubmitting === "1") {
      form.dataset.autosavePending = "1";
      return;
    }

    const previousTimer = autosaveTimers.get(form);
    if (previousTimer) window.clearTimeout(previousTimer);

    const nextTimer = window.setTimeout(() => {
      if (!form.isConnected) {
        autosaveTimers.delete(form);
        return;
      }
      if (typeof form.reportValidity === "function" && !form.reportValidity()) {
        return;
      }
      submitTaskAutosave(form);
    }, delay);

    autosaveTimers.set(form, nextTimer);
  };

  const flushTaskAutosaveNow = (form) => {
    if (!(form instanceof HTMLFormElement) || !form.isConnected) return;
    const pendingTimer = autosaveTimers.get(form);
    if (pendingTimer) {
      window.clearTimeout(pendingTimer);
      autosaveTimers.delete(form);
    }

    if (form.dataset.autosaveSubmitting === "1") {
      form.dataset.autosavePending = "1";
      return;
    }

    if (typeof form.reportValidity === "function" && !form.reportValidity()) {
      return;
    }

    void submitTaskAutosave(form).catch(() => {});
  };

  const flushAccountingAutosaveNow = (form, options = {}) => {
    if (!(form instanceof HTMLFormElement) || !form.isConnected) return;
    const pendingTimer = accountingAutosaveTimers.get(form);
    if (pendingTimer) {
      window.clearTimeout(pendingTimer);
      accountingAutosaveTimers.delete(form);
    }

    if (form.dataset.submitting === "1") {
      form.dataset.accountingPending = "1";
      return;
    }

    if (typeof form.reportValidity === "function" && !form.reportValidity()) {
      return;
    }

    void submitAccountingAutosaveForm(form, options).catch(() => {});
  };

  const flushFocusedAutosaveForms = () => {
    const activeElement = document.activeElement;
    if (!(activeElement instanceof Element)) return;

    const taskAutosaveForm = activeElement.closest("[data-task-autosave-form]");
    if (taskAutosaveForm instanceof HTMLFormElement) {
      flushTaskAutosaveNow(taskAutosaveForm);
    }

    const accountingEntryForm = activeElement.closest(".accounting-entry-form");
    if (accountingEntryForm instanceof HTMLFormElement) {
      flushAccountingAutosaveNow(accountingEntryForm, {
        fallbackError: "Falha ao atualizar registro.",
      });
    }
  };

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState !== "hidden") return;
    flushFocusedAutosaveForms();
  });

  window.addEventListener("pagehide", () => {
    flushFocusedAutosaveForms();
  });

  document.addEventListener("change", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (target instanceof HTMLInputElement && target.matches(uppercaseRequiredInputSelector)) {
      applyFirstLetterUppercaseToInput(target);
    }

    if (target instanceof HTMLSelectElement && target.matches(".status-select")) {
      const groupSection = target.closest("[data-task-group]");
      if (groupSection instanceof HTMLElement) {
        refreshTaskGroupSection(groupSection);
      }
    }

    if (target.matches("[data-permission-all-checkbox]")) {
      const permissionModal = target.closest("[data-group-permissions-modal]");
      if (permissionModal instanceof HTMLElement && target instanceof HTMLInputElement) {
        permissionModal.querySelectorAll("[data-permission-enabled-checkbox]").forEach((checkbox) => {
          if (!(checkbox instanceof HTMLInputElement)) return;
          if (checkbox.disabled) return;
          checkbox.checked = target.checked;
        });
        syncGroupPermissionsModal(permissionModal);
      }
      return;
    }

    if (target.matches("[data-permission-enabled-checkbox]")) {
      const permissionModal = target.closest("[data-group-permissions-modal]");
      if (permissionModal instanceof HTMLElement) {
        syncGroupPermissionsModal(permissionModal);
      }
      return;
    }

    if (target.matches("[data-group-name-input]")) {
      const renameForm = target.closest("[data-group-rename-form]");
      if (renameForm instanceof HTMLFormElement) {
        submitRenameGroup(renameForm).catch(() => {});
      }
      return;
    }

    if (target.matches("[data-due-date-input]")) {
      syncDueDateDisplay(target);
    }

    const form = target.closest("[data-task-autosave-form]");
    if (!(form instanceof HTMLFormElement)) return;

    if (target.matches('.row-assignee-picker input[type="checkbox"]')) {
      scheduleTaskAutosave(form, 120);
      return;
    }

    if (
      target.matches(
        'select, input[type="date"], input[type="text"], textarea'
      )
    ) {
      if (target.matches("[data-task-group-select]")) {
        const taskItem = target.closest("[data-task-item]");
        if (taskItem instanceof HTMLElement) {
          moveTaskItemToGroupDom(taskItem, target.value || "Geral");
          syncTaskGroupInputs();
        }
      }
      scheduleTaskAutosave(form, 180);
    }
  });

  document.addEventListener("input", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const form = target.closest("[data-task-autosave-form]");
    if (!(form instanceof HTMLFormElement)) return;

    if (target.matches('input[type="text"], textarea')) {
      scheduleTaskAutosave(form, 420);
    }
  });

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches("[data-task-autosave-form]")) {
      return;
    }
    event.preventDefault();
    submitTaskAutosave(form);
  });

  document.querySelectorAll("[data-task-autosave-form]").forEach((form) => {
    syncTaskOverdueBadge(form);
    syncTaskRevisionBadge(form);
    syncTaskRowSubtasksFromField(form);
  });

  document.querySelectorAll("[data-group-rename-form]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      submitRenameGroup(form).catch(() => {});
    });
  });

  let draggedTaskItem = null;
  let activeDropzone = null;
  let activeTaskGroupDropTarget = null;
  let draggedWorkspaceStatusRow = null;
  let draggedWorkspaceStatusList = null;

  const clearDropzoneHighlight = () => {
    document
      .querySelectorAll(".task-list-rows.is-drop-target")
      .forEach((zone) => zone.classList.remove("is-drop-target"));
    activeDropzone = null;
  };

  const clearTaskGroupDropTarget = () => {
    if (activeTaskGroupDropTarget instanceof HTMLElement) {
      activeTaskGroupDropTarget.classList.remove("is-group-drop-before", "is-group-drop-after");
    }
    activeTaskGroupDropTarget = null;
    clearTaskGroupDropIndicators();
  };

  const getTaskGroupField = (taskItem) => {
    if (!(taskItem instanceof HTMLElement)) return null;
    const form = taskItem.querySelector("[data-task-autosave-form]");
    if (!(form instanceof HTMLFormElement)) return null;
    const field = form.querySelector('[name="group_name"]');
    if (
      field instanceof HTMLSelectElement ||
      field instanceof HTMLInputElement
    ) {
      return { form, field };
    }
    return null;
  };

  document.addEventListener("dragstart", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    const workspaceStatusHandle = target.closest("[data-workspace-status-reorder-handle]");
    const workspaceStatusRow = workspaceStatusHandle?.closest("[data-workspace-status-row]");
    const workspaceStatusList = workspaceStatusRow?.closest("[data-workspace-status-list]");
    if (workspaceStatusHandle instanceof HTMLElement) {
      if (
        !(workspaceStatusRow instanceof HTMLElement) ||
        !(workspaceStatusList instanceof HTMLElement) ||
        workspaceStatusRow.dataset.workspaceStatusSortable !== "true"
      ) {
        event.preventDefault();
        return;
      }

      draggedWorkspaceStatusRow = workspaceStatusRow;
      draggedWorkspaceStatusList = workspaceStatusList;
      workspaceStatusRow.classList.add("is-sorting");
      clearWorkspaceStatusDropIndicators();

      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = "move";
        try {
          event.dataTransfer.setData("text/plain", workspaceStatusRow.dataset.statusKey || "status");
        } catch (e) {
          // noop
        }
      }
      return;
    }

    if (taskGroupReorderMode) {
      const dragSource = target.closest(".task-group-head, [data-task-group]");
      const groupSection = dragSource?.closest("[data-task-group]");
      if (!(groupSection instanceof HTMLElement)) {
        event.preventDefault();
        return;
      }

      if (groupSection.getAttribute("draggable") !== "true") {
        event.preventDefault();
        return;
      }

      draggedTaskGroup = groupSection;
      draggedTaskGroupInitialOrder = getCurrentTaskGroupOrder();
      groupSection.classList.add("is-group-dragging");
      clearTaskGroupDropTarget();

      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = "move";
        try {
          event.dataTransfer.setData("text/plain", groupSection.dataset.groupName || "group");
        } catch (e) {
          // noop
        }
      }
      return;
    }

    const taskItem = target.closest("[data-task-item]");
    if (!(taskItem instanceof HTMLElement)) return;

    // Preserve normal interactions with fields and controls.
    if (
      target.closest(
        "input, select, textarea, button, summary, label, a"
      )
    ) {
      event.preventDefault();
      return;
    }

    draggedTaskItem = taskItem;
    taskItem.classList.add("is-dragging");

    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = "move";
      try {
        event.dataTransfer.setData("text/plain", taskItem.id || "task");
      } catch (e) {
        // noop
      }
    }

    window.requestAnimationFrame(() => {
      if (draggedTaskItem === taskItem) {
        taskItem.classList.add("drag-ghost");
      }
    });
  });

  document.addEventListener("dragend", () => {
    const workspaceStatusesForm =
      draggedWorkspaceStatusRow instanceof HTMLElement
        ? draggedWorkspaceStatusRow.closest(".workspace-statuses-form")
        : draggedWorkspaceStatusList instanceof HTMLElement
          ? draggedWorkspaceStatusList.closest(".workspace-statuses-form")
          : null;
    if (draggedWorkspaceStatusRow instanceof HTMLElement) {
      draggedWorkspaceStatusRow.classList.remove("is-sorting");
    }
    draggedWorkspaceStatusRow = null;
    draggedWorkspaceStatusList = null;
    clearWorkspaceStatusDropIndicators();
    syncWorkspaceStatusesSaveState(workspaceStatusesForm);

    if (draggedTaskGroup instanceof HTMLElement) {
      draggedTaskGroup.classList.remove("is-group-dragging");
      clearTaskGroupDropTarget();
      const finalOrder = getCurrentTaskGroupOrder();
      if (draggedTaskGroupInitialOrder.join("|") !== finalOrder.join("|")) {
        persistTaskGroupOrder();
        if (typeof syncTaskGroupInputs === "function") {
          syncTaskGroupInputs();
        }
      }
    }
    draggedTaskGroup = null;
    draggedTaskGroupInitialOrder = [];

    if (draggedTaskItem) {
      draggedTaskItem.classList.remove("is-dragging", "drag-ghost");
    }
    draggedTaskItem = null;
    clearDropzoneHighlight();
  });

  document.addEventListener("dragover", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (draggedWorkspaceStatusRow instanceof HTMLElement) {
      const statusList = target.closest("[data-workspace-status-list]");
      if (!(statusList instanceof HTMLElement) || statusList !== draggedWorkspaceStatusList) {
        return;
      }

      event.preventDefault();
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = "move";
      }

      moveWorkspaceStatusRowByPointer(statusList, event.clientY);
      return;
    }

    if (draggedTaskGroup instanceof HTMLElement) {
      const overGroup = target.closest("[data-task-group]");
      if (!(overGroup instanceof HTMLElement) || overGroup === draggedTaskGroup) {
        return;
      }
      if (
        !(taskGroupsListElement instanceof HTMLElement) ||
        overGroup.parentElement !== taskGroupsListElement ||
        draggedTaskGroup.parentElement !== taskGroupsListElement
      ) {
        return;
      }

      event.preventDefault();
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = "move";
      }

      clearTaskGroupDropIndicators();
      const overRect = overGroup.getBoundingClientRect();
      const placeAfter = event.clientY > overRect.top + overRect.height / 2;
      overGroup.classList.add(placeAfter ? "is-group-drop-after" : "is-group-drop-before");
      activeTaskGroupDropTarget = overGroup;

      const referenceNode = placeAfter ? overGroup.nextElementSibling : overGroup;
      if (referenceNode !== draggedTaskGroup) {
        taskGroupsListElement.insertBefore(draggedTaskGroup, referenceNode);
      }
      return;
    }

    if (!draggedTaskItem) return;

    const dropzone = target.closest("[data-task-dropzone]");
    if (!(dropzone instanceof HTMLElement)) return;

    event.preventDefault();
    if (event.dataTransfer) {
      event.dataTransfer.dropEffect = "move";
    }

    if (activeDropzone && activeDropzone !== dropzone) {
      activeDropzone.classList.remove("is-drop-target");
    }

    activeDropzone = dropzone;
    dropzone.classList.add("is-drop-target");
  });

  document.addEventListener("dragleave", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (draggedWorkspaceStatusRow instanceof HTMLElement) {
      const statusList = target.closest("[data-workspace-status-list]");
      if (!(statusList instanceof HTMLElement) || statusList !== draggedWorkspaceStatusList) {
        return;
      }

      const related = event.relatedTarget;
      if (related instanceof Node && statusList.contains(related)) {
        return;
      }

      clearWorkspaceStatusDropIndicators();
      return;
    }

    if (draggedTaskGroup instanceof HTMLElement) {
      const overGroup = target.closest("[data-task-group]");
      if (!(overGroup instanceof HTMLElement)) return;

      const related = event.relatedTarget;
      if (related instanceof Node && overGroup.contains(related)) {
        return;
      }

      overGroup.classList.remove("is-group-drop-before", "is-group-drop-after");
      if (activeTaskGroupDropTarget === overGroup) {
        activeTaskGroupDropTarget = null;
      }
      return;
    }

    const dropzone = target.closest("[data-task-dropzone]");
    if (!(dropzone instanceof HTMLElement)) return;

    const related = event.relatedTarget;
    if (related instanceof Node && dropzone.contains(related)) {
      return;
    }

    dropzone.classList.remove("is-drop-target");
    if (activeDropzone === dropzone) {
      activeDropzone = null;
    }
  });

  document.addEventListener("drop", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (draggedWorkspaceStatusRow instanceof HTMLElement) {
      const statusList = target.closest("[data-workspace-status-list]");
      if (statusList instanceof HTMLElement && statusList === draggedWorkspaceStatusList) {
        event.preventDefault();
      }
      clearWorkspaceStatusDropIndicators();
      return;
    }

    if (draggedTaskGroup instanceof HTMLElement) {
      const overGroup = target.closest("[data-task-group]");
      if (overGroup instanceof HTMLElement) {
        event.preventDefault();
      }
      clearTaskGroupDropTarget();
      return;
    }

    if (!draggedTaskItem) return;

    const dropzone = target.closest("[data-task-dropzone]");
    if (!(dropzone instanceof HTMLElement)) return;

    event.preventDefault();

    const nextGroup = (dropzone.dataset.groupName || "").trim() || "Geral";
    const currentGroup = (draggedTaskItem.dataset.groupName || "").trim() || "Geral";
    const taskBinding = getTaskGroupField(draggedTaskItem);

    clearDropzoneHighlight();

    if (!taskBinding) return;

    const { form, field } = taskBinding;

    if (field instanceof HTMLSelectElement) {
      const hasOption = Array.from(field.options).some(
        (option) => option.value === nextGroup
      );
      if (!hasOption) {
        const option = document.createElement("option");
        option.value = nextGroup;
        option.textContent = nextGroup;
        field.append(option);
      }
    }

    field.value = nextGroup;
    draggedTaskItem.dataset.groupName = nextGroup;

    if (currentGroup !== nextGroup) {
      moveTaskItemToGroupDom(draggedTaskItem, nextGroup);
      syncTaskGroupInputs();
      scheduleTaskAutosave(form, 60);
    }
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;

    const statusStepButton = target.closest("[data-status-step]");
    if (statusStepButton) {
      const stepper = statusStepButton.closest("[data-status-stepper]");
      const statusSelect = stepper?.querySelector("select.status-select");
      const step = Number.parseInt(statusStepButton.dataset.statusStep || "0", 10);

      if (!(statusSelect instanceof HTMLSelectElement) || !step) {
        return;
      }

      const nextIndex = statusSelect.selectedIndex + step;
      if (nextIndex < 0 || nextIndex >= statusSelect.options.length) {
        return;
      }

      statusSelect.selectedIndex = nextIndex;
      syncSelectColor(statusSelect);
      statusSelect.dispatchEvent(new Event("change", { bubbles: true }));
      return;
    }

    const inlineSelectOption = target.closest("[data-inline-select-option]");
    if (inlineSelectOption) {
      const wrap = getInlineSelectWrap(inlineSelectOption);
      const details = inlineSelectOption.closest("[data-inline-select-picker]");
      const select = wrap?.querySelector("select[data-inline-select-source]");
      const hasValueAttr = Object.prototype.hasOwnProperty.call(
        inlineSelectOption.dataset,
        "value"
      );
      const nextValue = (inlineSelectOption.dataset.value ?? "").trim();

      if (!(select instanceof HTMLSelectElement) || !hasValueAttr) {
        return;
      }

      if (!Array.from(select.options).some((option) => option.value === nextValue)) {
        return;
      }

      const changed = select.value !== nextValue;
      select.value = nextValue;
      syncSelectColor(select);

      if (details instanceof HTMLDetailsElement) {
        details.open = false;
      }

      if (changed) {
        const filterForm = select.closest("[data-task-filter-form]");
        if (filterForm instanceof HTMLFormElement) {
          applyTaskFilterForm(filterForm);
          return;
        }
        select.dispatchEvent(new Event("change", { bubbles: true }));
      }
      return;
    }

    const dueDisplay = target.closest("[data-due-date-display]");
    const revisionBadgeTrigger = target.closest("[data-task-revision-badge]");
    if (revisionBadgeTrigger) {
      const form = revisionBadgeTrigger.closest("[data-task-autosave-form]");
      if (form instanceof HTMLFormElement) {
        void submitTaskRevisionRemovalFromRow(form);
      }
      return;
    }

    const overdueBadgeTrigger = target.closest("[data-task-overdue-badge]");
    if (overdueBadgeTrigger) {
      const form = overdueBadgeTrigger.closest("[data-task-autosave-form]");
      const overdueField = form?.querySelector?.("[data-task-overdue-flag]");
      const overdueSinceField = form?.querySelector?.("[data-task-overdue-since-date]");
      const overdueDaysField = form?.querySelector?.("[data-task-overdue-days]");
      if (form instanceof HTMLFormElement && overdueField instanceof HTMLInputElement) {
        if (overdueField.value !== "0") {
          overdueField.value = "0";
          if (overdueSinceField instanceof HTMLInputElement) {
            overdueSinceField.value = "";
          }
          if (overdueDaysField instanceof HTMLInputElement) {
            overdueDaysField.value = "0";
          }
          syncTaskOverdueBadge(form);
          scheduleTaskAutosave(form, 60);
        }
      }
      return;
    }

    if (dueDisplay) {
      const wrap = dueDisplay.closest(".due-tag-field");
      const input = wrap?.querySelector("[data-due-date-input]");
      if (input instanceof HTMLInputElement) {
        if (input._flatpickr && typeof input._flatpickr.open === "function") {
          input._flatpickr.open();
        } else if (typeof input.showPicker === "function") {
          input.showPicker();
        } else {
          input.focus();
          input.click();
        }
      }
      return;
    }

    const deleteButton = target.closest(".task-row-delete");
    if (deleteButton) {
      const formId = deleteButton.getAttribute("form");
      const deleteForm = formId ? document.getElementById(formId) : null;
      const taskItem = deleteButton.closest("[data-task-item]");
      const taskTitle =
        taskItem?.querySelector('[name="title"]')?.value?.trim() ||
        taskItem?.querySelector(".task-title-input")?.value?.trim() ||
        "esta tarefa";

      if (deleteForm instanceof HTMLFormElement) {
        openConfirmModal({
          title: "Excluir tarefa",
          message: `Remover ${taskTitle}?`,
          confirmLabel: "Excluir",
          confirmVariant: "danger",
          onConfirm: async () => {
            await submitDeleteTask(deleteForm);
          },
        });
      }
      return;
    }

    const groupDeleteButton = target.closest("[data-group-delete]");
    if (groupDeleteButton) {
      const deleteForm = groupDeleteButton.closest("[data-group-delete-form]");
      const groupSection = groupDeleteButton.closest("[data-task-group]");
      const groupName =
        groupSection?.dataset.groupName?.trim() ||
        deleteForm?.querySelector('[name="group_name"]')?.value?.trim() ||
        "este grupo";
      const groupCountText =
        groupSection?.querySelector(".task-group-count")?.textContent?.trim() || "0";
      const groupTaskCount = Number.parseInt(groupCountText, 10) || 0;
      const message =
        groupTaskCount > 0
          ? `Remover o grupo ${groupName}? As tarefas desse grupo tambem serao excluidas.`
          : `Remover o grupo ${groupName}?`;

      if (deleteForm instanceof HTMLFormElement) {
        openConfirmModal({
          title: "Excluir grupo",
          message,
          confirmLabel: "Excluir",
          confirmVariant: "danger",
          onConfirm: async () => {
            await submitDeleteGroup(deleteForm);
          },
        });
      }
      return;
    }

    const groupDoneToggleButton = target.closest("[data-toggle-group-done]");
    if (groupDoneToggleButton instanceof HTMLButtonElement) {
      if (
        taskGroupsListElement instanceof HTMLElement &&
        taskGroupsListElement.classList.contains("is-reorder-mode")
      ) {
        return;
      }

      const groupSection = groupDoneToggleButton.closest("[data-task-group]");
      if (groupSection instanceof HTMLElement) {
        const shouldHideDone = !groupSection.classList.contains("is-done-hidden");
        setTaskGroupDoneHidden(groupSection, shouldHideDone);
      }
      return;
    }

    const taskGroupHeadToggle = target.closest("[data-task-group-head-toggle]");
    if (taskGroupHeadToggle instanceof HTMLElement) {
      if (isGroupHeadToggleTargetBlocked(target, taskGroupHeadToggle)) {
        return;
      }

      if (
        taskGroupsListElement instanceof HTMLElement &&
        taskGroupsListElement.classList.contains("is-reorder-mode")
      ) {
        return;
      }

      const groupSection = taskGroupHeadToggle.closest("[data-task-group]");
      if (groupSection instanceof HTMLElement) {
        const shouldCollapse = !groupSection.classList.contains("is-collapsed");
        setTaskGroupCollapsed(groupSection, shouldCollapse);
      }
      return;
    }

    const taskItem = target.closest("[data-task-item]");
    if (!(taskItem instanceof HTMLElement)) return;

    const toggleButton = target.closest("[data-task-expand]");
    if (toggleButton) {
      openTaskDetailModal(taskItem);
      return;
    }

    const interactiveTarget = target.closest(
      [
        "a[href]",
        "button",
        "input",
        "select",
        "textarea",
        "summary",
        "label",
        "details",
        "[contenteditable='true']",
        "[role='button']",
        "[role='option']",
        "[data-inline-select-option]",
        "[data-inline-select-picker]",
        "[data-task-overdue-badge]",
      ].join(",")
    );

    const interactiveDisabled =
      (interactiveTarget instanceof HTMLButtonElement && interactiveTarget.disabled) ||
      (interactiveTarget instanceof HTMLInputElement && interactiveTarget.disabled) ||
      (interactiveTarget instanceof HTMLSelectElement && interactiveTarget.disabled) ||
      (interactiveTarget instanceof HTMLTextAreaElement && interactiveTarget.disabled);

    if (interactiveTarget && taskItem.contains(interactiveTarget) && !interactiveDisabled) {
      return;
    }

    openTaskDetailModal(taskItem);
  });

  const fabWrap = document.querySelector("[data-task-fab-wrap]");
  const fabToggleButton = document.querySelector("[data-task-fab-toggle]");
  const fabMenu = document.querySelector("[data-task-fab-menu]");
  const taskGroupsDatalist = document.querySelector("#task-group-options");
  const taskFilterForm = document.querySelector("[data-task-filter-form]");
  const taskGroupsListElement = document.querySelector("[data-task-groups-list]");
  const taskGroupReorderButtons = Array.from(
    document.querySelectorAll("[data-toggle-task-group-reorder]")
  );
  const dashboardViewPanels = Array.from(
    document.querySelectorAll("[data-dashboard-view-panel]")
  );
  const dashboardViewToggleButtons = Array.from(
    document.querySelectorAll("[data-dashboard-view-toggle]")
  );
  const usersSidebar = document.querySelector(".users-sidebar");
  const workspaceSidebarHeader = document.querySelector(".workspace-sidebar-header");
  const dashboardMobileHeaderActions = document.querySelector("[data-dashboard-mobile-header-actions]");
  const mobileSidebarToggleButton = document.querySelector("[data-mobile-sidebar-toggle]");
  const dashboardContentNav = document.querySelector(".dashboard-content-nav");
  const dashboardNavMain = document.querySelector(".dashboard-content-nav .dashboard-nav-main");
  const mobileSidebarMediaQuery =
    typeof window.matchMedia === "function"
      ? window.matchMedia("(max-width: 768px)")
      : null;

  const setFabMenuOpen = (open) => {
    if (!fabWrap || !fabToggleButton || !fabMenu) return;
    fabWrap.classList.toggle("is-open", open);
    fabToggleButton.setAttribute("aria-expanded", open ? "true" : "false");
    fabMenu.setAttribute("aria-hidden", open ? "false" : "true");
  };

  const normalizeDashboardViewCandidate = (value) => {
    let normalized = String(value || "").trim().toLowerCase();
    if (normalized === "dues") {
      normalized = "accounting";
    }

    return normalized === "overview" ||
      normalized === "tasks" ||
      normalized === "vault" ||
      normalized === "inventory" ||
      normalized === "accounting" ||
      normalized === "users"
      ? normalized
      : "";
  };

  const dashboardViewsFromBody = (() => {
    if (!(document.body instanceof HTMLBodyElement)) return [];
    const rawViews = String(document.body.dataset.workspaceEnabledViews || "").trim();
    if (!rawViews) return [];
    const uniqueViews = new Set();
    rawViews.split(",").forEach((rawView) => {
      const normalized = normalizeDashboardViewCandidate(rawView);
      if (normalized) {
        uniqueViews.add(normalized);
      }
    });
    return Array.from(uniqueViews);
  })();

  const dashboardViews = new Set();
  if (dashboardViewsFromBody.length > 0) {
    dashboardViewsFromBody.forEach((view) => dashboardViews.add(view));
  } else {
    dashboardViewPanels.forEach((panel) => {
      if (!(panel instanceof HTMLElement)) return;
      const panelView = normalizeDashboardViewCandidate(panel.dataset.dashboardViewPanel || "");
      if (panelView) dashboardViews.add(panelView);
    });
    dashboardViewToggleButtons.forEach((button) => {
      if (!(button instanceof HTMLElement)) return;
      const buttonView = normalizeDashboardViewCandidate(button.dataset.view || "");
      if (buttonView) dashboardViews.add(buttonView);
    });
  }
  if (!dashboardViews.has("tasks")) {
    dashboardViews.add("tasks");
  }

  const defaultDashboardView = dashboardViews.has("overview") ? "overview" : "tasks";
  let syncTaskDetailModalFromUrl = null;

  const normalizeDashboardView = (value) => {
    const normalized = normalizeDashboardViewCandidate(value);
    return normalized && dashboardViews.has(normalized) ? normalized : defaultDashboardView;
  };

  const dashboardTaskIdFromUrl = () => {
    const currentUrl = new URL(window.location.href);
    const rawTaskId = Number.parseInt(currentUrl.searchParams.get("task") || "0", 10) || 0;
    return rawTaskId > 0 ? rawTaskId : 0;
  };

  const syncDashboardNavStats = (view) => {
    const mode = view === "overview" ? "overview" : "workspace";
    document.querySelectorAll("[data-dashboard-stat-label]").forEach((label) => {
      if (!(label instanceof HTMLElement)) return;
      const nextLabel =
        mode === "overview" ? label.dataset.overviewLabel : label.dataset.workspaceLabel;
      if (nextLabel) {
        label.textContent = nextLabel;
      }
    });
    document.querySelectorAll("[data-dashboard-stat-value]").forEach((value) => {
      if (!(value instanceof HTMLElement)) return;
      const nextValue =
        mode === "overview" ? value.dataset.overviewValue : value.dataset.workspaceValue;
      if (nextValue !== undefined) {
        value.textContent = nextValue;
      }
    });
  };

  const dashboardViewFromUrl = () => {
    const currentUrl = new URL(window.location.href);
    const rawView = String(currentUrl.searchParams.get("view") || "").trim();
    if (!rawView && dashboardTaskIdFromUrl() > 0) {
      return normalizeDashboardView("tasks");
    }

    return normalizeDashboardView(rawView);
  };

  const buildDashboardStateUrl = (nextView, { taskId = 0 } = {}) => {
    const currentUrl = new URL(window.location.href);
    const nextUrl = new URL(currentUrl.toString());
    const view = normalizeDashboardView(nextView);
    const normalizedTaskId =
      view === "tasks" ? Math.max(0, Number.parseInt(String(taskId || "0"), 10) || 0) : 0;

    if (view === defaultDashboardView) {
      nextUrl.searchParams.delete("view");
    } else {
      nextUrl.searchParams.set("view", view);
    }

    if (normalizedTaskId > 0) {
      nextUrl.searchParams.set("task", String(normalizedTaskId));
    } else {
      nextUrl.searchParams.delete("task");
    }

    nextUrl.hash = "";
    return `${nextUrl.pathname}${nextUrl.search}`;
  };

  const replaceDashboardStateUrl = (nextView, options = {}) => {
    if (!(window.history && typeof window.history.replaceState === "function")) {
      return;
    }

    const nextUrl = buildDashboardStateUrl(nextView, options);
    const currentUrl = `${window.location.pathname}${window.location.search}`;
    if (nextUrl === currentUrl) {
      return;
    }

    window.history.replaceState(null, "", nextUrl);
  };

  const setDashboardView = (nextView, { updateUrl = false, taskId = 0 } = {}) => {
    if (!dashboardViewPanels.length) return;

    const view = normalizeDashboardView(nextView);
    dashboardViewPanels.forEach((panel) => {
      if (!(panel instanceof HTMLElement)) return;
      const panelView = normalizeDashboardViewCandidate(panel.dataset.dashboardViewPanel || "");
      const isAllowedPanel = panelView !== "" && dashboardViews.has(panelView);
      panel.hidden = !isAllowedPanel || panelView !== view;
    });

    dashboardViewToggleButtons.forEach((button) => {
      if (!(button instanceof HTMLElement)) return;
      const buttonView = normalizeDashboardViewCandidate(button.dataset.view || "");
      const isAllowedButton = buttonView !== "" && dashboardViews.has(buttonView);
      button.hidden = !isAllowedButton;
      if (button instanceof HTMLButtonElement) {
        button.disabled = !isAllowedButton;
      }
      if (!isAllowedButton) {
        button.classList.remove("is-active");
        button.setAttribute("aria-pressed", "false");
        button.removeAttribute("aria-current");
        return;
      }
      const isActive = buttonView === view;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-pressed", isActive ? "true" : "false");
      if (isActive) {
        button.setAttribute("aria-current", "page");
      } else {
        button.removeAttribute("aria-current");
      }
    });

    if (document.body instanceof HTMLBodyElement) {
      document.body.dataset.dashboardView = view;
    }
    syncDashboardNavStats(view);

    if (updateUrl) {
      replaceDashboardStateUrl(view, { taskId });
    }
  };

  if (dashboardViewPanels.length) {
    setDashboardView(dashboardViewFromUrl(), {
      updateUrl: false,
      taskId: dashboardTaskIdFromUrl(),
    });
  }
  initializeWorkspaceSidebarToolsForms();

  const isMobileSidebarViewport = () =>
    mobileSidebarMediaQuery ? mobileSidebarMediaQuery.matches : window.innerWidth <= 768;

  let mobileSidebarWasEnabled = false;

  const syncMobileSidebarHeaderHeight = () => {
    if (!(usersSidebar instanceof HTMLElement)) return;
    if (!(workspaceSidebarHeader instanceof HTMLElement)) return;
    usersSidebar.style.setProperty(
      "--mobile-sidebar-header-height",
      `${Math.ceil(workspaceSidebarHeader.getBoundingClientRect().height)}px`
    );
  };

  const syncMobileHeaderActionsLayout = () => {
    if (
      !(document.body instanceof HTMLBodyElement) ||
      !(dashboardContentNav instanceof HTMLElement) ||
      !(dashboardNavMain instanceof HTMLElement)
    ) {
      return;
    }

    const dashboardTopNavActions = document.querySelector(".top-nav-actions");
    const topActions =
      dashboardTopNavActions instanceof HTMLElement ? dashboardTopNavActions : null;

    const shouldInlineHeaderActions = Boolean(
      isMobileSidebarViewport() &&
        dashboardMobileHeaderActions instanceof HTMLElement &&
        topActions instanceof HTMLElement
    );

    if (shouldInlineHeaderActions) {
      document.body.classList.add("dashboard-header-actions-inline");
      if (topActions.parentElement !== dashboardMobileHeaderActions) {
        dashboardMobileHeaderActions.appendChild(topActions);
      }
      return;
    }

    document.body.classList.remove("dashboard-header-actions-inline");

    if (topActions instanceof HTMLElement && topActions.parentElement !== dashboardNavMain) {
      dashboardNavMain.appendChild(topActions);
    }
  };

  const setMobileSidebarOpen = (open) => {
    if (!(document.body instanceof HTMLBodyElement)) return;
    syncMobileSidebarHeaderHeight();
    const shouldEnable = Boolean(
      usersSidebar instanceof HTMLElement &&
        mobileSidebarToggleButton instanceof HTMLElement &&
        isMobileSidebarViewport()
    );
    document.body.classList.toggle("dashboard-mobile-nav-collapsible", shouldEnable);
    const shouldOpen = shouldEnable && Boolean(open);
    document.body.classList.toggle("dashboard-sidebar-open", shouldOpen);
    if (mobileSidebarToggleButton instanceof HTMLElement) {
      mobileSidebarToggleButton.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
    }
  };

  const isMobileSidebarOpen = () =>
    document.body instanceof HTMLBodyElement &&
    document.body.classList.contains("dashboard-sidebar-open");

  const syncMobileSidebarState = () => {
    if (!(document.body instanceof HTMLBodyElement)) return;
    syncMobileSidebarHeaderHeight();

    const enabled = Boolean(
      usersSidebar instanceof HTMLElement &&
        mobileSidebarToggleButton instanceof HTMLElement &&
        isMobileSidebarViewport()
    );

    document.body.classList.toggle("dashboard-mobile-nav-collapsible", enabled);

    if (!enabled) {
      document.body.classList.remove("dashboard-sidebar-open");
      if (mobileSidebarToggleButton instanceof HTMLElement) {
        mobileSidebarToggleButton.setAttribute("aria-expanded", "false");
      }
      mobileSidebarWasEnabled = false;
      syncMobileHeaderActionsLayout();
      return;
    }

    if (!mobileSidebarWasEnabled) {
      document.body.classList.remove("dashboard-sidebar-open");
    }
    mobileSidebarWasEnabled = true;

    if (mobileSidebarToggleButton instanceof HTMLElement) {
      mobileSidebarToggleButton.setAttribute(
        "aria-expanded",
        document.body.classList.contains("dashboard-sidebar-open") ? "true" : "false"
      );
    }
    syncMobileHeaderActionsLayout();
  };

  syncMobileSidebarState();
  if (mobileSidebarMediaQuery && typeof mobileSidebarMediaQuery.addEventListener === "function") {
    mobileSidebarMediaQuery.addEventListener("change", syncMobileSidebarState);
  } else {
    window.addEventListener("resize", syncMobileSidebarState);
  }
  window.addEventListener("resize", syncMobileSidebarHeaderHeight);
  window.addEventListener("pageshow", syncMobileSidebarState);

  const createTaskModal = document.querySelector("[data-create-modal]");
  const createTaskModalCard =
    createTaskModal instanceof HTMLElement ? createTaskModal.querySelector(".create-task-modal") : null;
  const createTaskGroupInput = document.querySelector("[data-create-task-group-input]");
  const createTaskTitleComposer = document.querySelector("[data-create-task-title-composer]");
  const createTaskTitleTagPicker = document.querySelector("[data-create-task-title-tag-picker]");
  const createTaskTitleTagTrigger = document.querySelector("[data-create-task-title-tag-trigger]");
  const createTaskTitleTagMenu = document.querySelector("[data-create-task-title-tag-menu]");
  const createTaskTitleInput = document.querySelector("[data-create-task-title-input]");
  const createTaskTitleTagCustom = document.querySelector("[data-create-task-title-tag-custom]");
  const createTaskTitleTagInput = document.querySelector("[data-create-task-title-tag-input]");
  const createTaskTitleTagColorInput = document.querySelector(
    "[data-create-task-title-tag-color-input]"
  );
  const taskTitleTagOptionsDataElement = document.querySelector("#task-title-tag-options-data");
  const createTaskForm = document.querySelector("[data-create-task-form]");
  const createTaskDescription = document.querySelector("[data-create-task-description]");
  const createTaskDescriptionWrap = document.querySelector("[data-create-task-description-wrap]");
  const createTaskDescriptionEditor = document.querySelector("[data-create-task-description-editor]");
  const createTaskDescriptionToolbar = document.querySelector("[data-create-task-description-toolbar]");
  const createTaskLinksField = document.querySelector("[data-create-task-links]");
  const createTaskLinksList = document.querySelector("[data-create-task-links-list]");
  const createTaskLinkToggleAddButton = document.querySelector("[data-create-task-link-toggle-add]");
  const createTaskLinkAddForm = document.querySelector("[data-create-task-link-add-form]");
  const createTaskLinkInput = document.querySelector("[data-create-task-link-input]");
  const createTaskLinkConfirmButton = document.querySelector("[data-create-task-link-confirm]");
  const createTaskLinkCancelButton = document.querySelector("[data-create-task-link-cancel]");
  const createTaskImagesField = document.querySelector("[data-create-task-images]");
  const createTaskSubtasksField = document.querySelector("[data-create-task-subtasks]");
  const createTaskSubtasksList = document.querySelector("[data-create-task-subtasks-list]");
  const createTaskSubtasksDependencyInput = document.querySelector(
    "[data-create-task-subtasks-dependency]"
  );
  const createTaskSubtasksDependencyToggle = document.querySelector(
    "[data-create-task-subtasks-dependency-toggle]"
  );
  const createTaskSubtaskInput = document.querySelector("[data-create-task-subtask-input]");
  const createTaskSubtaskAddForm = document.querySelector("[data-create-task-subtask-add-form]");
  const createTaskSubtaskToggleAddButton = document.querySelector("[data-create-task-subtask-toggle-add]");
  const createTaskSubtaskCancelButton = document.querySelector("[data-create-task-subtask-cancel]");
  const createTaskSubtaskAddButton = document.querySelector("[data-create-task-subtask-add]");
  const createTaskImagePicker = document.querySelector("[data-create-task-image-picker]");
  const createTaskImageInput = document.querySelector("[data-create-task-image-input]");
  const createTaskImageAddButton = document.querySelector("[data-create-task-image-add]");
  const createTaskDriveAddButton = document.querySelector("[data-create-task-drive-add]");
  const createTaskImageList = document.querySelector("[data-create-task-image-list]");
  const createTaskOpenMediaButton = document.querySelector("[data-create-task-open-media]");
  const createTaskBackMainButton = document.querySelector("[data-create-task-back-main]");
  const createTaskSubmitButton = document.querySelector("[data-create-task-submit]");
  const createTaskImagesFieldWrap =
    createTaskImagePicker instanceof HTMLElement
      ? createTaskImagePicker.closest(".task-detail-edit-images-field")
      : null;
  const createTaskMainRow =
    createTaskImagePicker instanceof HTMLElement
      ? createTaskImagePicker.closest(".task-detail-edit-main-row")
      : null;
  const workspaceCreateModal = document.querySelector("[data-workspace-create-modal]");
  const getWorkspaceUsersModal = () => document.querySelector("[data-workspace-users-modal]");
  const workspaceCreateForm = document.querySelector("[data-workspace-create-form]");
  const workspaceCreateNameInput = document.querySelector("[data-workspace-create-name-input]");
  const createGroupModal = document.querySelector("[data-create-group-modal]");
  const createGroupNameInput = document.querySelector("[data-create-group-name-input]");
  const createGroupForm = document.querySelector("[data-create-group-form]");
  const vaultGroupModal = document.querySelector("[data-vault-group-modal]");
  const vaultGroupForm = document.querySelector("[data-vault-group-form]");
  const vaultGroupNameInput = document.querySelector("[data-vault-group-name-input]");
  const vaultEntryModal = document.querySelector("[data-vault-entry-modal]");
  const vaultEntryForm = document.querySelector("[data-vault-entry-form]");
  const vaultEntryGroupField = document.querySelector("[data-vault-entry-group]");
  const vaultEntryLabelField = document.querySelector("[data-vault-entry-label]");
  const vaultEntryLoginField = document.querySelector("[data-vault-entry-login]");
  const vaultEntryPasswordField = document.querySelector("[data-vault-entry-password]");
  const vaultEntryEditModal = document.querySelector("[data-vault-entry-edit-modal]");
  const vaultEntryEditForm = document.querySelector("[data-vault-entry-edit-form]");
  const vaultEntryEditIdField = document.querySelector("[data-vault-entry-edit-id]");
  const vaultEntryEditGroupField = document.querySelector("[data-vault-entry-edit-group]");
  const vaultEntryEditLabelField = document.querySelector("[data-vault-entry-edit-label]");
  const vaultEntryEditLoginField = document.querySelector("[data-vault-entry-edit-login]");
  const vaultEntryEditPasswordField = document.querySelector("[data-vault-entry-edit-password]");
  const vaultEntryEditPasswordUnavailableField = document.querySelector(
    "[data-vault-entry-edit-password-unavailable]"
  );
  const dueGroupModal = document.querySelector("[data-due-group-modal]");
  const dueGroupForm = document.querySelector("[data-due-group-form]");
  const dueGroupNameInput = document.querySelector("[data-due-group-name-input]");
  const dueEntryModal = document.querySelector("[data-due-entry-modal]");
  const dueEntryForm = document.querySelector("[data-due-entry-form]");
  const dueEntryGroupField = document.querySelector("[data-due-entry-group]");
  const dueEntryLabelField = document.querySelector("[data-due-entry-label]");
  const dueEntryAmountField = document.querySelector("[data-due-entry-amount]");
  const dueEntryRecurrenceField = document.querySelector("[data-due-entry-recurrence]");
  const dueEntryMonthlyWrap = document.querySelector("[data-due-entry-monthly-wrap]");
  const dueEntryMonthlyDayField = document.querySelector("[data-due-entry-monthly-day]");
  const dueEntryFixedWrap = document.querySelector("[data-due-entry-fixed-wrap]");
  const dueEntryFixedDateField = document.querySelector("[data-due-entry-fixed-date]");
  const dueEntryAnnualWrap = document.querySelector("[data-due-entry-annual-wrap]");
  const dueEntryAnnualMonthField = document.querySelector("[data-due-entry-annual-month]");
  const dueEntryAnnualDayField = document.querySelector("[data-due-entry-annual-day]");
  const dueEntryDateField = document.querySelector("[data-due-entry-date]");
  const dueEntryEditModal = document.querySelector("[data-due-entry-edit-modal]");
  const dueEntryEditForm = document.querySelector("[data-due-entry-edit-form]");
  const dueEntryEditIdField = document.querySelector("[data-due-entry-edit-id]");
  const dueEntryEditGroupField = document.querySelector("[data-due-entry-edit-group]");
  const dueEntryEditLabelField = document.querySelector("[data-due-entry-edit-label]");
  const dueEntryEditAmountField = document.querySelector("[data-due-entry-edit-amount]");
  const dueEntryEditRecurrenceField = document.querySelector("[data-due-entry-edit-recurrence]");
  const dueEntryEditMonthlyWrap = document.querySelector("[data-due-entry-edit-monthly-wrap]");
  const dueEntryEditMonthlyDayField = document.querySelector("[data-due-entry-edit-monthly-day]");
  const dueEntryEditFixedWrap = document.querySelector("[data-due-entry-edit-fixed-wrap]");
  const dueEntryEditFixedDateField = document.querySelector("[data-due-entry-edit-fixed-date]");
  const dueEntryEditAnnualWrap = document.querySelector("[data-due-entry-edit-annual-wrap]");
  const dueEntryEditAnnualMonthField = document.querySelector("[data-due-entry-edit-annual-month]");
  const dueEntryEditAnnualDayField = document.querySelector("[data-due-entry-edit-annual-day]");
  const dueEntryEditDateField = document.querySelector("[data-due-entry-edit-date]");
  const inventoryGroupModal = document.querySelector("[data-inventory-group-modal]");
  const inventoryGroupForm = document.querySelector("[data-inventory-group-form]");
  const inventoryGroupNameInput = document.querySelector("[data-inventory-group-name-input]");
  const inventoryEntryModal = document.querySelector("[data-inventory-entry-modal]");
  const inventoryEntryForm = document.querySelector("[data-inventory-entry-form]");
  const inventoryEntryGroupField = document.querySelector("[data-inventory-entry-group]");
  const inventoryEntryLabelField = document.querySelector("[data-inventory-entry-label]");
  const inventoryEntryQuantityField = document.querySelector("[data-inventory-entry-quantity]");
  const inventoryEntryUnitField = document.querySelector("[data-inventory-entry-unit]");
  const inventoryEntryMinQuantityField = document.querySelector("[data-inventory-entry-min-quantity]");
  const inventoryEntryNotesField = document.querySelector("[data-inventory-entry-notes]");
  const inventoryEntryEditModal = document.querySelector("[data-inventory-entry-edit-modal]");
  const inventoryEntryEditForm = document.querySelector("[data-inventory-entry-edit-form]");
  const inventoryEntryEditIdField = document.querySelector("[data-inventory-entry-edit-id]");
  const inventoryEntryEditGroupField = document.querySelector("[data-inventory-entry-edit-group]");
  const inventoryEntryEditLabelField = document.querySelector("[data-inventory-entry-edit-label]");
  const inventoryEntryEditQuantityField = document.querySelector("[data-inventory-entry-edit-quantity]");
  const inventoryEntryEditUnitField = document.querySelector("[data-inventory-entry-edit-unit]");
  const inventoryEntryEditMinQuantityField = document.querySelector(
    "[data-inventory-entry-edit-min-quantity]"
  );
  const inventoryEntryEditNotesField = document.querySelector("[data-inventory-entry-edit-notes]");
  const taskDetailModal = document.querySelector("[data-task-detail-modal]");
  const taskDetailModalCard =
    taskDetailModal instanceof HTMLElement ? taskDetailModal.querySelector(".task-detail-modal") : null;
  const taskDetailTitle = document.querySelector("[data-task-detail-title]");
  const taskDetailViewPanel = document.querySelector("[data-task-detail-view]");
  const taskDetailEditPanel = document.querySelector("[data-task-detail-edit-panel]");
  const taskDetailViewTitle = document.querySelector("[data-task-detail-view-title]");
  const taskDetailViewStatus = document.querySelector("[data-task-detail-view-status]");
  const taskDetailViewPriority = document.querySelector("[data-task-detail-view-priority]");
  const taskDetailViewTitleTag = document.querySelector("[data-task-detail-view-title-tag]");
  const taskDetailViewGroup = document.querySelector("[data-task-detail-view-group]");
  const taskDetailViewDue = document.querySelector("[data-task-detail-view-due]");
  const taskDetailViewAssignees = document.querySelector("[data-task-detail-view-assignees]");
  const taskDetailViewDescription = document.querySelector("[data-task-detail-view-description]");
  const taskDetailViewDescriptionVersions = document.querySelector(
    "[data-task-detail-view-description-versions]"
  );
  const taskDetailViewSubtasksWrap = document.querySelector("[data-task-detail-view-subtasks-wrap]");
  const taskDetailViewSubtasks = document.querySelector("[data-task-detail-view-subtasks]");
  const taskDetailViewReferences = document.querySelector("[data-task-detail-view-references]");
  const taskDetailViewLinksWrap = document.querySelector("[data-task-detail-view-links-wrap]");
  const taskDetailViewLinks = document.querySelector("[data-task-detail-view-links]");
  const taskDetailViewImagesWrap = document.querySelector("[data-task-detail-view-images-wrap]");
  const taskDetailViewImages = document.querySelector("[data-task-detail-view-images]");
  const taskImagePreviewModal = document.querySelector("[data-task-image-preview-modal]");
  const taskImagePreviewTitle = document.querySelector("[data-task-image-preview-title]");
  const taskImagePreviewImage = document.querySelector("[data-task-image-preview-img]");
  const taskImagePreviewVideo = document.querySelector("[data-task-image-preview-video]");
  const taskImagePreviewPrevButton = document.querySelector("[data-task-image-preview-prev]");
  const taskImagePreviewNextButton = document.querySelector("[data-task-image-preview-next]");
  const taskDetailViewHistory = document.querySelector("[data-task-detail-view-history]");
  const taskDetailViewCreatedBy = document.querySelector("[data-task-detail-view-created-by]");
  const taskDetailViewUpdatedAt = document.querySelector("[data-task-detail-view-updated-at]");
  const taskDetailEditTitleComposer = document.querySelector("[data-task-detail-edit-title-composer]");
  const taskDetailEditTitleTagPicker = document.querySelector("[data-task-detail-edit-title-tag-picker]");
  const taskDetailEditTitleTagTrigger = document.querySelector(
    "[data-task-detail-edit-title-tag-trigger]"
  );
  const taskDetailEditTitleTagMenu = document.querySelector("[data-task-detail-edit-title-tag-menu]");
  const taskDetailEditTitle = document.querySelector("[data-task-detail-edit-title]");
  const taskDetailEditTitleTagInput = document.querySelector(
    "[data-task-detail-edit-title-tag-input]"
  );
  const taskDetailEditTitleTagColorInput = document.querySelector(
    "[data-task-detail-edit-title-tag-color-input]"
  );
  const taskDetailEditTitleTagCustom = document.querySelector(
    "[data-task-detail-edit-title-tag-custom]"
  );
  const taskDetailEditStatus = document.querySelector("[data-task-detail-edit-status]");
  const taskDetailEditPriority = document.querySelector("[data-task-detail-edit-priority]");
  const taskDetailEditGroup = document.querySelector("[data-task-detail-edit-group]");
  const taskDetailEditDueDate = document.querySelector("[data-task-detail-edit-due-date]");
  const taskDetailEditDescription = document.querySelector("[data-task-detail-edit-description]");
  const taskDetailEditDescriptionWrap = document.querySelector(
    "[data-task-detail-edit-description-wrap]"
  );
  const taskDetailEditDescriptionEditor = document.querySelector(
    "[data-task-detail-edit-description-editor]"
  );
  const taskDetailEditDescriptionToolbar = document.querySelector(
    "[data-task-detail-edit-description-toolbar]"
  );
  const taskDetailEditSubtasksField = document.querySelector("[data-task-detail-edit-subtasks]");
  const taskDetailEditSubtasksList = document.querySelector("[data-task-detail-edit-subtasks-list]");
  const taskDetailEditSubtasksDependencyInput = document.querySelector(
    "[data-task-detail-edit-subtasks-dependency]"
  );
  const taskDetailEditSubtasksDependencyToggle = document.querySelector(
    "[data-task-detail-edit-subtasks-dependency-toggle]"
  );
  const taskDetailEditSubtaskInput = document.querySelector("[data-task-detail-edit-subtask-input]");
  const taskDetailEditSubtaskAddForm = document.querySelector("[data-task-detail-edit-subtask-add-form]");
  const taskDetailEditSubtaskToggleAddButton = document.querySelector("[data-task-detail-edit-subtask-toggle-add]");
  const taskDetailEditSubtaskCancelButton = document.querySelector("[data-task-detail-edit-subtask-cancel]");
  const taskDetailEditSubtaskAddButton = document.querySelector("[data-task-detail-edit-subtask-add]");
  const taskDetailEditLinks = document.querySelector("[data-task-detail-edit-links]");
  const taskDetailEditLinksList = document.querySelector("[data-task-detail-edit-links-list]");
  const taskDetailEditLinkToggleAddButton = document.querySelector("[data-task-detail-edit-link-toggle-add]");
  const taskDetailEditLinkAddForm = document.querySelector("[data-task-detail-edit-link-add-form]");
  const taskDetailEditLinkInput = document.querySelector("[data-task-detail-edit-link-input]");
  const taskDetailEditLinkConfirmButton = document.querySelector("[data-task-detail-edit-link-confirm]");
  const taskDetailEditLinkCancelButton = document.querySelector("[data-task-detail-edit-link-cancel]");
  const taskDetailEditImages = document.querySelector("[data-task-detail-edit-images]");
  const taskDetailImagePicker = document.querySelector("[data-task-detail-image-picker]");
  const taskDetailImageInput = document.querySelector("[data-task-detail-image-input]");
  const taskDetailImageAddButton = document.querySelector("[data-task-detail-image-add]");
  const taskDetailDriveAddButton = document.querySelector("[data-task-detail-drive-add]");
  const taskDetailImageList = document.querySelector("[data-task-detail-image-list]");
  const taskDetailOpenMediaButton = document.querySelector("[data-task-detail-open-media]");
  const taskDetailBackMainButton = document.querySelector("[data-task-detail-back-main]");
  const taskDetailImagesFieldWrap =
    taskDetailImagePicker instanceof HTMLElement
      ? taskDetailImagePicker.closest(".task-detail-edit-images-field")
      : null;
  const taskDetailMainRow =
    taskDetailImagePicker instanceof HTMLElement
      ? taskDetailImagePicker.closest(".task-detail-edit-main-row")
      : null;
  const taskDetailEditAssignees = document.querySelector("[data-task-detail-edit-assignees]");
  const taskDetailEditAssigneesMenu = document.querySelector("[data-task-detail-edit-assignees-menu]");
  const taskDetailEditButton = document.querySelector("[data-task-detail-edit]");
  const taskDetailRequestRevisionButton = document.querySelector(
    "[data-task-detail-request-revision]"
  );
  const taskDetailRemoveRevisionButton = document.querySelector(
    "[data-task-detail-remove-revision]"
  );
  const taskDetailSaveButton = document.querySelector("[data-task-detail-save]");
  const taskDetailDeleteButton = document.querySelector("[data-task-detail-delete]");
  const taskDetailCancelEditButton = document.querySelector("[data-task-detail-cancel-edit]");
  const taskReviewModal = document.querySelector("[data-task-review-modal]");
  const taskReviewForm = document.querySelector("[data-task-review-form]");
  const taskReviewTaskIdInput = document.querySelector("[data-task-review-task-id]");
  const taskReviewDescriptionInput = document.querySelector("[data-task-review-description]");
  const taskReviewSubmitButton = document.querySelector("[data-task-review-submit]");
  const taskRemoveRevisionForm = document.querySelector("[data-task-remove-revision-form]");
  const taskRemoveRevisionTaskIdInput = document.querySelector(
    "[data-task-remove-revision-task-id]"
  );
  const confirmModal = document.querySelector("[data-confirm-modal]");
  const confirmModalTitle = document.querySelector("#confirm-modal-title");
  const confirmModalMessage = document.querySelector("[data-confirm-modal-message]");
  const confirmModalSubmit = document.querySelector("[data-confirm-modal-submit]");
  const googleDriveBrowserModal = document.querySelector("[data-google-drive-browser-modal]");
  const googleDriveBrowserRoots = document.querySelector("[data-google-drive-browser-roots]");
  const googleDriveBrowserExplorer = document.querySelector("[data-google-drive-browser-explorer]");
  const googleDriveBrowserBreadcrumbs = document.querySelector("[data-google-drive-browser-breadcrumbs]");
  const googleDriveBrowserList = document.querySelector("[data-google-drive-browser-list]");
  const googleDriveBrowserState = document.querySelector("[data-google-drive-browser-state]");
  const googleDriveBrowserSelectionCount = document.querySelector(
    "[data-google-drive-browser-selection-count]"
  );
  const googleDriveBrowserAttachButton = document.querySelector("[data-google-drive-browser-attach]");
  const googleDriveBrowserMoreWrap = document.querySelector("[data-google-drive-browser-more-wrap]");
  const googleDriveBrowserMoreButton = document.querySelector("[data-google-drive-browser-more]");
  const googleDriveBrowserBackRootButton = document.querySelector("[data-google-drive-browser-back-root]");
  const groupPermissionModals = Array.from(
    document.querySelectorAll("[data-group-permissions-modal]")
  );
  let confirmModalAction = null;
  let taskDetailContext = null;
  let taskDetailEditImageItems = [];
  let taskDetailEditReferenceLinks = [];
  let taskDetailEditSubtaskItems = [];
  let taskDetailEditSubtasksDependencyEnabled = false;
  let createTaskImageItems = [];
  let createTaskReferenceLinks = [];
  let taskDetailImagePickerExpanded = false;
  let createTaskImagePickerExpanded = false;
  let googleDriveBrowserTarget = "";
  let googleDriveBrowserRoot = "";
  let googleDriveBrowserFolderId = "";
  let googleDriveBrowserItems = [];
  let googleDriveBrowserBreadcrumbTrail = [];
  let googleDriveBrowserNextPageToken = "";
  let googleDriveBrowserSelectedItems = new Map();
  let googleDriveBrowserLoading = false;
  const googleDriveBrowserResumeStorageKey = "wf_google_drive_browser_resume_v1";
  const googleDriveBrowserResumeQueryParam = "google_drive_browser_resume";
  const googleDriveBrowserResumeOpenQueryParam = "google_drive_browser_resume_open";
  const googleDriveBrowserResumeMaxAgeMs = 20 * 60 * 1000;
  let createTaskSubtaskItems = [];
  let createTaskSubtasksDependencyEnabled = false;
  const TASK_TITLE_TAG_DEFAULT_PALETTE = [
    "#6967AE",
    "#D1495B",
    "#F28F3B",
    "#E9C46A",
    "#2A9D8F",
    "#4CC9F0",
    "#4361EE",
    "#3A0CA3",
    "#B5179E",
    "#F72585",
    "#6C757D",
    "#2B9348",
    "#0077B6",
    "#E76F51",
    "#8D99AE",
    "#8338EC",
    "#00B4D8",
    "#588157",
    "#EF476F",
    "#118AB2",
  ];
  let taskTitleTagPalette = [...TASK_TITLE_TAG_DEFAULT_PALETTE];
  let taskTitleTagDefaultColor = TASK_TITLE_TAG_DEFAULT_PALETTE[0];
  let taskTitleTagColorsByKey = new Map();
  let createTaskTitleTagOptions = [];
  let createTaskCurrentTitleTag = "";
  let createTaskCurrentTitleTagColor = taskTitleTagDefaultColor;
  let createTaskOpenColorPaletteTag = "";
  let createTaskTitleTagIsCreating = false;
  let taskDetailEditCurrentTitleTag = "";
  let taskDetailEditCurrentTitleTagColor = taskTitleTagDefaultColor;
  let taskDetailEditOpenColorPaletteTag = "";
  let taskDetailEditTitleTagIsCreating = false;
  let taskDetailSaveInFlight = false;
  let taskDetailViewPreviewItems = [];
  const taskImagePreviewState = {
    items: [],
    currentIndex: -1,
  };

  const normalizeTaskTitleTagCollection = (values = []) => {
    const uniqueMap = new Map();
    (Array.isArray(values) ? values : []).forEach((value) => {
      const normalized = normalizeTaskTitleTagValue(value);
      if (!normalized) return;
      const key = normalized.toLocaleLowerCase("pt-BR");
      if (!uniqueMap.has(key)) {
        uniqueMap.set(key, normalized);
      }
    });

    return Array.from(uniqueMap.values()).sort((left, right) =>
      left.localeCompare(right, "pt-BR", { sensitivity: "base" })
    );
  };

  const taskTitleTagColorKey = (tagValue = "") =>
    normalizeTaskTitleTagValue(tagValue).toLocaleLowerCase("pt-BR");

  const normalizeTaskTitleTagPalette = (values = []) => {
    const unique = [];
    (Array.isArray(values) ? values : []).forEach((value) => {
      const normalized = String(value || "").trim().toUpperCase();
      if (!/^#[0-9A-F]{6}$/.test(normalized)) return;
      if (unique.includes(normalized)) return;
      unique.push(normalized);
    });
    return unique.length ? unique : [...TASK_TITLE_TAG_DEFAULT_PALETTE];
  };

  const normalizeTaskTitleTagColorLoose = (value = "") => {
    const normalized = String(value || "").trim().toUpperCase();
    if (!/^#[0-9A-F]{6}$/.test(normalized)) {
      return "";
    }
    return taskTitleTagPalette.includes(normalized) ? normalized : "";
  };

  const normalizeTaskTitleTagColorValue = (value = "", fallback = "") => {
    const direct = normalizeTaskTitleTagColorLoose(value);
    if (direct) {
      return direct;
    }

    const fallbackDirect = normalizeTaskTitleTagColorLoose(fallback);
    if (fallbackDirect) {
      return fallbackDirect;
    }

    return (
      taskTitleTagPalette[0] ||
      TASK_TITLE_TAG_DEFAULT_PALETTE[0] ||
      "#6967AE"
    );
  };

  const hashTaskTitleTag = (tagValue = "") => {
    const normalized = normalizeTaskTitleTagValue(tagValue);
    if (!normalized) return 0;
    let hash = 0;
    Array.from(normalized).forEach((char) => {
      hash = (hash * 31 + char.codePointAt(0)) % 2147483647;
    });
    return Math.abs(hash);
  };

  const setTaskTitleTagColorForTag = (tagValue = "", colorValue = "") => {
    const normalizedTag = normalizeTaskTitleTagValue(tagValue);
    if (!normalizedTag) return "";
    const normalizedColor = normalizeTaskTitleTagColorValue(colorValue);
    taskTitleTagColorsByKey.set(taskTitleTagColorKey(normalizedTag), normalizedColor);
    return normalizedColor;
  };

  const resolveTaskTitleTagColor = (tagValue = "", explicitColor = "") => {
    const normalizedTag = normalizeTaskTitleTagValue(tagValue);
    if (!normalizedTag) {
      return normalizeTaskTitleTagColorValue(explicitColor, taskTitleTagDefaultColor);
    }

    const key = taskTitleTagColorKey(normalizedTag);
    const explicitNormalized = normalizeTaskTitleTagColorLoose(explicitColor);
    if (explicitNormalized) {
      taskTitleTagColorsByKey.set(key, explicitNormalized);
      return explicitNormalized;
    }

    const current = normalizeTaskTitleTagColorLoose(taskTitleTagColorsByKey.get(key) || "");
    if (current) {
      return current;
    }

    const palette = taskTitleTagPalette.length ? taskTitleTagPalette : TASK_TITLE_TAG_DEFAULT_PALETTE;
    const fallbackColor = palette[hashTaskTitleTag(normalizedTag) % palette.length] || taskTitleTagDefaultColor;
    taskTitleTagColorsByKey.set(key, fallbackColor);
    return fallbackColor;
  };

  const paintTagColorSwatch = (element, colorValue = "", enabled = true) => {
    if (!(element instanceof HTMLElement)) return;

    if (!enabled) {
      element.classList.remove("has-color-dot");
      element.style.removeProperty("--wf-tag-color");
      delete element.dataset.tagColor;
      return;
    }

    const normalizedColor = normalizeTaskTitleTagColorValue(colorValue, taskTitleTagDefaultColor);
    element.classList.add("has-color-dot");
    element.style.setProperty("--wf-tag-color", normalizedColor);
    element.dataset.tagColor = normalizedColor;
  };

  const readTaskTitleTagOptionsFromData = () => {
    const baseConfig = {
      options: [],
      tagColors: {},
      palette: [...TASK_TITLE_TAG_DEFAULT_PALETTE],
      defaultColor: TASK_TITLE_TAG_DEFAULT_PALETTE[0],
    };

    if (!(taskTitleTagOptionsDataElement instanceof HTMLScriptElement)) {
      return baseConfig;
    }

    try {
      const parsed = JSON.parse(taskTitleTagOptionsDataElement.textContent || "[]");
      if (Array.isArray(parsed)) {
        return {
          ...baseConfig,
          options: parsed,
        };
      }

      if (!parsed || typeof parsed !== "object") {
        return baseConfig;
      }

      const options = [];
      const tagColors = {};
      const optionsSource = Array.isArray(parsed.options)
        ? parsed.options
        : Array.isArray(parsed.tags)
          ? parsed.tags
          : [];

      optionsSource.forEach((entry) => {
        if (typeof entry === "string") {
          const normalized = normalizeTaskTitleTagValue(entry);
          if (normalized) {
            options.push(normalized);
          }
          return;
        }

        if (!entry || typeof entry !== "object") return;
        const normalized = normalizeTaskTitleTagValue(entry.value || entry.label || entry.name || "");
        if (!normalized) return;
        options.push(normalized);
        if (typeof entry.color === "string" && entry.color.trim()) {
          tagColors[normalized] = entry.color;
        }
      });

      if (parsed.tag_colors && typeof parsed.tag_colors === "object") {
        Object.entries(parsed.tag_colors).forEach(([tag, color]) => {
          const normalized = normalizeTaskTitleTagValue(tag);
          if (!normalized) return;
          tagColors[normalized] = String(color || "");
        });
      }

      const palette = normalizeTaskTitleTagPalette(parsed.palette || []);
      const rawDefaultColor =
        typeof parsed.default_color === "string"
          ? parsed.default_color
          : typeof parsed.defaultColor === "string"
            ? parsed.defaultColor
            : "";
      const normalizedDefaultColor = /^#[0-9A-F]{6}$/i.test(rawDefaultColor)
        ? rawDefaultColor.trim().toUpperCase()
        : "";

      return {
        options,
        tagColors,
        palette,
        defaultColor: palette.includes(normalizedDefaultColor)
          ? normalizedDefaultColor
          : palette[0] || TASK_TITLE_TAG_DEFAULT_PALETTE[0],
      };
    } catch (error) {
      return baseConfig;
    }
  };

  const getTaskTitleTagCsrfToken = () => {
    const csrfField =
      (createTaskForm instanceof HTMLFormElement
        ? createTaskForm.querySelector('input[name="csrf_token"]')
        : null) ||
      (taskDetailContext?.form instanceof HTMLFormElement
        ? taskDetailContext.form.querySelector('input[name="csrf_token"]')
        : null) ||
      document.querySelector('input[name="csrf_token"]');

    return csrfField instanceof HTMLInputElement ? csrfField.value.trim() : "";
  };

  const persistTaskTitleTagColorSelection = async (tagValue = "", colorValue = "") => {
    const normalizedTag = normalizeTaskTitleTagValue(tagValue);
    if (!normalizedTag) return false;

    const normalizedColor = normalizeTaskTitleTagColorValue(colorValue, taskTitleTagDefaultColor);
    const csrfToken = getTaskTitleTagCsrfToken();
    if (!csrfToken) return false;

    try {
      await postActionJson("set_task_title_tag_color", {
        csrf_token: csrfToken,
        title_tag: normalizedTag,
        title_tag_color: normalizedColor,
      });
      return true;
    } catch (_error) {
      return false;
    }
  };

  const persistTaskTitleTagOptionChange = async (action = "", tagValue = "") => {
    const normalizedAction = String(action || "").trim();
    if (
      normalizedAction !== "add_task_title_tag_option" &&
      normalizedAction !== "remove_task_title_tag_option"
    ) {
      return false;
    }

    const normalizedTag = normalizeTaskTitleTagValue(tagValue);
    if (!normalizedTag) return false;

    const csrfToken = getTaskTitleTagCsrfToken();
    if (!csrfToken) return false;

    try {
      await postActionJson(normalizedAction, {
        csrf_token: csrfToken,
        title_tag: normalizedTag,
      });
      return true;
    } catch (_error) {
      return false;
    }
  };

  const applyTaskTitleTagColorEverywhere = (tagValue = "", colorValue = "") => {
    const normalizedTag = normalizeTaskTitleTagValue(tagValue);
    if (!normalizedTag) return "";

    const nextColor = setTaskTitleTagColorForTag(normalizedTag, colorValue);

    if (normalizeTaskTitleTagValue(createTaskCurrentTitleTag) === normalizedTag) {
      createTaskCurrentTitleTagColor = nextColor;
      syncCreateTaskTitleTagTrigger();
    }
    if (normalizeTaskTitleTagValue(taskDetailEditCurrentTitleTag) === normalizedTag) {
      taskDetailEditCurrentTitleTagColor = nextColor;
      syncTaskDetailTitleTagTrigger();
    }

    document.querySelectorAll("[data-task-item]").forEach((taskItem) => {
      if (!(taskItem instanceof HTMLElement)) return;
      const tagField = taskItem.querySelector("[data-task-title-tag]");
      const currentTag = tagField instanceof HTMLInputElement ? tagField.value || "" : "";
      if (normalizeTaskTitleTagValue(currentTag) !== normalizedTag) return;
      syncTaskTitleTagBadge(taskItem, currentTag, nextColor);
    });

    if (taskDetailContext && taskDetailContext.titleTagField instanceof HTMLInputElement) {
      const currentTag = normalizeTaskTitleTagValue(taskDetailContext.titleTagField.value || "");
      if (currentTag === normalizedTag) {
        syncTaskDetailViewTitleTag(currentTag, nextColor);
      }
    }

    renderCreateTaskTitleTagMenu();
    renderTaskDetailTitleTagMenu();
    void persistTaskTitleTagColorSelection(normalizedTag, nextColor);
    return nextColor;
  };

  const closeCreateTaskTitleTagMenu = () => {
    createTaskOpenColorPaletteTag = "";
    if (createTaskTitleTagMenu instanceof HTMLElement) {
      createTaskTitleTagMenu.hidden = true;
    }
    if (createTaskTitleTagPicker instanceof HTMLElement) {
      createTaskTitleTagPicker.classList.remove("is-open");
    }
    if (createTaskTitleTagTrigger instanceof HTMLButtonElement) {
      createTaskTitleTagTrigger.setAttribute("aria-expanded", "false");
    }
  };

  const syncCreateTaskTitleTagTrigger = () => {
    const normalizedTag = normalizeTaskTitleTagValue(createTaskCurrentTitleTag);
    createTaskCurrentTitleTag = normalizedTag;
    const normalizedColor = normalizedTag
      ? resolveTaskTitleTagColor(normalizedTag)
      : normalizeTaskTitleTagColorValue(createTaskCurrentTitleTagColor, taskTitleTagDefaultColor);
    createTaskCurrentTitleTagColor = normalizedColor;

    if (createTaskTitleTagInput instanceof HTMLInputElement) {
      createTaskTitleTagInput.value = normalizedTag;
    }
    if (createTaskTitleTagColorInput instanceof HTMLInputElement) {
      createTaskTitleTagColorInput.value = normalizedColor;
    }

    if (!(createTaskTitleTagTrigger instanceof HTMLButtonElement)) return;

    createTaskTitleTagTrigger.textContent = normalizedTag || "tag";
    createTaskTitleTagTrigger.classList.toggle("is-empty", !normalizedTag);
    paintTagColorSwatch(createTaskTitleTagTrigger, normalizedColor, Boolean(normalizedTag));
    createTaskTitleTagTrigger.setAttribute(
      "aria-label",
      normalizedTag ? `Tag selecionada: ${normalizedTag}` : "Sem tag"
    );
  };

  const renderCreateTaskTitleTagMenu = () => {
    if (!(createTaskTitleTagMenu instanceof HTMLElement)) return;

    const selectedTag = normalizeTaskTitleTagValue(createTaskCurrentTitleTag);
    createTaskTitleTagMenu.innerHTML = "";

    const clearButton = document.createElement("button");
    clearButton.type = "button";
    clearButton.className = "create-task-title-tag-option is-clear";
    clearButton.dataset.createTaskTitleTagOption = "";
    clearButton.textContent = "Sem tag";
    if (!selectedTag) {
      clearButton.classList.add("is-selected");
    }
    createTaskTitleTagMenu.append(clearButton);

    createTaskTitleTagOptions.forEach((tagValue) => {
      const row = document.createElement("div");
      row.className = "create-task-title-tag-menu-item";
      const tagColor = resolveTaskTitleTagColor(tagValue);
      const isPaletteOpen = createTaskOpenColorPaletteTag === tagValue;

      const colorButton = document.createElement("button");
      colorButton.type = "button";
      colorButton.className = "create-task-title-tag-color";
      colorButton.dataset.createTaskTitleTagColor = tagValue;
      colorButton.setAttribute("aria-label", `Alterar cor da tag ${tagValue}`);
      colorButton.setAttribute("aria-expanded", isPaletteOpen ? "true" : "false");
      paintTagColorSwatch(colorButton, tagColor, true);
      colorButton.classList.toggle("is-open", isPaletteOpen);
      colorButton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        createTaskOpenColorPaletteTag =
          createTaskOpenColorPaletteTag === tagValue ? "" : tagValue;
        taskDetailEditOpenColorPaletteTag = "";
        renderCreateTaskTitleTagMenu();
      });

      const optionButton = document.createElement("button");
      optionButton.type = "button";
      optionButton.className = "create-task-title-tag-option";
      optionButton.dataset.createTaskTitleTagOption = tagValue;
      optionButton.textContent = tagValue;
      if (selectedTag === tagValue) {
        optionButton.classList.add("is-selected");
      }

      const removeButton = document.createElement("button");
      removeButton.type = "button";
      removeButton.className = "create-task-title-tag-remove";
      removeButton.dataset.createTaskTitleTagRemove = tagValue;
      removeButton.setAttribute("aria-label", `Remover tag ${tagValue}`);
      removeButton.textContent = "x";

      row.append(colorButton, optionButton, removeButton);
      if (isPaletteOpen) {
        const paletteWrap = document.createElement("div");
        paletteWrap.className = "create-task-title-tag-color-palette";
        paletteWrap.dataset.createTaskTitleTagColorPalette = tagValue;

        taskTitleTagPalette.forEach((paletteColor) => {
          const paletteButton = document.createElement("button");
          paletteButton.type = "button";
          paletteButton.className = "create-task-title-tag-color-option";
          paletteButton.dataset.createTaskTitleTagColorOption = "1";
          paletteButton.dataset.createTaskTitleTagColorTag = tagValue;
          paletteButton.dataset.createTaskTitleTagColorValue = paletteColor;
          paletteButton.setAttribute("aria-label", `Aplicar cor ${paletteColor} para ${tagValue}`);
          paintTagColorSwatch(paletteButton, paletteColor, true);
          if (paletteColor === tagColor) {
            paletteButton.classList.add("is-active");
          }
          paletteButton.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            createTaskOpenColorPaletteTag = "";
            taskDetailEditOpenColorPaletteTag = "";
            applyTaskTitleTagColorEverywhere(tagValue, paletteColor);
          });
          paletteWrap.append(paletteButton);
        });

        row.append(paletteWrap);
      }
      createTaskTitleTagMenu.append(row);
    });

    const createButton = document.createElement("button");
    createButton.type = "button";
    createButton.className = "create-task-title-tag-create";
    createButton.dataset.createTaskTitleTagCreate = "1";
    createButton.textContent = "Criar tag";
    createTaskTitleTagMenu.append(createButton);
  };

  const openCreateTaskTitleTagMenu = () => {
    if (!(createTaskTitleTagMenu instanceof HTMLElement)) return;
    if (createTaskTitleTagIsCreating) return;

    renderCreateTaskTitleTagMenu();
    createTaskTitleTagMenu.hidden = false;
    if (createTaskTitleTagPicker instanceof HTMLElement) {
      createTaskTitleTagPicker.classList.add("is-open");
    }
    if (createTaskTitleTagTrigger instanceof HTMLButtonElement) {
      createTaskTitleTagTrigger.setAttribute("aria-expanded", "true");
    }
  };

  const setCreateTaskTitleTagValue = (value = "", colorValue = "") => {
    createTaskCurrentTitleTag = normalizeTaskTitleTagValue(value);
    createTaskOpenColorPaletteTag = "";
    const explicitColor = normalizeTaskTitleTagColorLoose(colorValue);
    createTaskCurrentTitleTagColor = createTaskCurrentTitleTag
      ? explicitColor
        ? resolveTaskTitleTagColor(createTaskCurrentTitleTag, explicitColor)
        : resolveTaskTitleTagColor(createTaskCurrentTitleTag)
      : normalizeTaskTitleTagColorValue(colorValue || createTaskCurrentTitleTagColor, taskTitleTagDefaultColor);
    syncCreateTaskTitleTagTrigger();
    renderCreateTaskTitleTagMenu();
  };

  const stopCreateTaskTitleTagCreation = ({ focusTrigger = false } = {}) => {
    createTaskTitleTagIsCreating = false;
    if (createTaskTitleComposer instanceof HTMLElement) {
      createTaskTitleComposer.classList.remove("is-creating-tag");
    }
    if (createTaskTitleTagCustom instanceof HTMLInputElement) {
      createTaskTitleTagCustom.hidden = true;
      createTaskTitleTagCustom.value = "";
    }
    if (createTaskTitleTagTrigger instanceof HTMLButtonElement) {
      createTaskTitleTagTrigger.hidden = false;
      if (focusTrigger) {
        createTaskTitleTagTrigger.focus();
      }
    }
    syncCreateTaskTitleTagTrigger();
  };

  const commitCreateTaskTitleTagCreation = () => {
    if (createTaskTitleTagCustom instanceof HTMLInputElement) {
      applyFirstLetterUppercaseToInput(createTaskTitleTagCustom);
      const newTag = normalizeTaskTitleTagValue(createTaskTitleTagCustom.value || "");
      if (newTag) {
        createTaskTitleTagOptions = normalizeTaskTitleTagCollection([
          ...createTaskTitleTagOptions,
          newTag,
        ]);
        createTaskCurrentTitleTag = newTag;
        createTaskCurrentTitleTagColor = resolveTaskTitleTagColor(newTag);
        void persistTaskTitleTagOptionChange("add_task_title_tag_option", newTag);
      }
    }
    stopCreateTaskTitleTagCreation();
    renderCreateTaskTitleTagMenu();
    renderTaskDetailTitleTagMenu();
    return normalizeTaskTitleTagValue(createTaskCurrentTitleTag);
  };

  const startCreateTaskTitleTagCreation = () => {
    if (!(createTaskTitleTagCustom instanceof HTMLInputElement)) return;
    closeCreateTaskTitleTagMenu();
    createTaskTitleTagIsCreating = true;
    if (createTaskTitleComposer instanceof HTMLElement) {
      createTaskTitleComposer.classList.add("is-creating-tag");
    }
    if (createTaskTitleTagTrigger instanceof HTMLButtonElement) {
      createTaskTitleTagTrigger.hidden = true;
    }
    createTaskTitleTagCustom.hidden = false;
    createTaskTitleTagCustom.value = "";
    createTaskTitleTagCustom.focus();
  };

  const resetCreateTaskTitleTagPicker = (value = "", colorValue = "") => {
    closeCreateTaskTitleTagMenu();
    stopCreateTaskTitleTagCreation();
    setCreateTaskTitleTagValue(value, colorValue);
  };

  const removeTaskTitleTagOption = (tagValue = "") => {
    const removedTag = normalizeTaskTitleTagValue(tagValue);
    if (!removedTag) return false;

    const removedKey = removedTag.toLocaleLowerCase("pt-BR");
    createTaskTitleTagOptions = createTaskTitleTagOptions.filter(
      (tag) => normalizeTaskTitleTagValue(tag).toLocaleLowerCase("pt-BR") !== removedKey
    );
    taskTitleTagColorsByKey.delete(removedKey);
    if (createTaskOpenColorPaletteTag.toLocaleLowerCase("pt-BR") === removedKey) {
      createTaskOpenColorPaletteTag = "";
    }
    if (taskDetailEditOpenColorPaletteTag.toLocaleLowerCase("pt-BR") === removedKey) {
      taskDetailEditOpenColorPaletteTag = "";
    }

    if (createTaskCurrentTitleTag.toLocaleLowerCase("pt-BR") === removedKey) {
      createTaskCurrentTitleTag = "";
      createTaskCurrentTitleTagColor = taskTitleTagDefaultColor;
    }
    if (taskDetailEditCurrentTitleTag.toLocaleLowerCase("pt-BR") === removedKey) {
      taskDetailEditCurrentTitleTag = "";
      taskDetailEditCurrentTitleTagColor = taskTitleTagDefaultColor;
    }

    syncCreateTaskTitleTagTrigger();
    syncTaskDetailTitleTagTrigger();
    renderCreateTaskTitleTagMenu();
    renderTaskDetailTitleTagMenu();
    void persistTaskTitleTagOptionChange("remove_task_title_tag_option", removedTag);
    return true;
  };

  const closeTaskDetailTitleTagMenu = () => {
    taskDetailEditOpenColorPaletteTag = "";
    if (taskDetailEditTitleTagMenu instanceof HTMLElement) {
      taskDetailEditTitleTagMenu.hidden = true;
    }
    if (taskDetailEditTitleTagPicker instanceof HTMLElement) {
      taskDetailEditTitleTagPicker.classList.remove("is-open");
    }
    if (taskDetailEditTitleTagTrigger instanceof HTMLButtonElement) {
      taskDetailEditTitleTagTrigger.setAttribute("aria-expanded", "false");
    }
  };

  const syncTaskDetailTitleTagTrigger = () => {
    const normalizedTag = normalizeTaskTitleTagValue(taskDetailEditCurrentTitleTag);
    taskDetailEditCurrentTitleTag = normalizedTag;
    const normalizedColor = normalizedTag
      ? resolveTaskTitleTagColor(normalizedTag)
      : normalizeTaskTitleTagColorValue(taskDetailEditCurrentTitleTagColor, taskTitleTagDefaultColor);
    taskDetailEditCurrentTitleTagColor = normalizedColor;

    if (taskDetailEditTitleTagInput instanceof HTMLInputElement) {
      taskDetailEditTitleTagInput.value = normalizedTag;
    }
    if (taskDetailEditTitleTagColorInput instanceof HTMLInputElement) {
      taskDetailEditTitleTagColorInput.value = normalizedColor;
    }

    if (!(taskDetailEditTitleTagTrigger instanceof HTMLButtonElement)) return;

    taskDetailEditTitleTagTrigger.textContent = normalizedTag || "tag";
    taskDetailEditTitleTagTrigger.classList.toggle("is-empty", !normalizedTag);
    paintTagColorSwatch(taskDetailEditTitleTagTrigger, normalizedColor, Boolean(normalizedTag));
    taskDetailEditTitleTagTrigger.setAttribute(
      "aria-label",
      normalizedTag ? `Tag selecionada: ${normalizedTag}` : "Sem tag"
    );
  };

  const renderTaskDetailTitleTagMenu = () => {
    if (!(taskDetailEditTitleTagMenu instanceof HTMLElement)) return;

    const selectedTag = normalizeTaskTitleTagValue(taskDetailEditCurrentTitleTag);
    taskDetailEditTitleTagMenu.innerHTML = "";

    const clearButton = document.createElement("button");
    clearButton.type = "button";
    clearButton.className = "create-task-title-tag-option is-clear";
    clearButton.dataset.taskDetailEditTitleTagOption = "";
    clearButton.textContent = "Sem tag";
    if (!selectedTag) {
      clearButton.classList.add("is-selected");
    }
    taskDetailEditTitleTagMenu.append(clearButton);

    createTaskTitleTagOptions.forEach((tagValue) => {
      const row = document.createElement("div");
      row.className = "create-task-title-tag-menu-item";
      const tagColor = resolveTaskTitleTagColor(tagValue);
      const isPaletteOpen = taskDetailEditOpenColorPaletteTag === tagValue;

      const colorButton = document.createElement("button");
      colorButton.type = "button";
      colorButton.className = "create-task-title-tag-color";
      colorButton.dataset.taskDetailEditTitleTagColor = tagValue;
      colorButton.setAttribute("aria-label", `Alterar cor da tag ${tagValue}`);
      colorButton.setAttribute("aria-expanded", isPaletteOpen ? "true" : "false");
      paintTagColorSwatch(colorButton, tagColor, true);
      colorButton.classList.toggle("is-open", isPaletteOpen);
      colorButton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        taskDetailEditOpenColorPaletteTag =
          taskDetailEditOpenColorPaletteTag === tagValue ? "" : tagValue;
        createTaskOpenColorPaletteTag = "";
        renderTaskDetailTitleTagMenu();
      });

      const optionButton = document.createElement("button");
      optionButton.type = "button";
      optionButton.className = "create-task-title-tag-option";
      optionButton.dataset.taskDetailEditTitleTagOption = tagValue;
      optionButton.textContent = tagValue;
      if (selectedTag === tagValue) {
        optionButton.classList.add("is-selected");
      }

      const removeButton = document.createElement("button");
      removeButton.type = "button";
      removeButton.className = "create-task-title-tag-remove";
      removeButton.dataset.taskDetailEditTitleTagRemove = tagValue;
      removeButton.setAttribute("aria-label", `Remover tag ${tagValue}`);
      removeButton.textContent = "x";

      row.append(colorButton, optionButton, removeButton);
      if (isPaletteOpen) {
        const paletteWrap = document.createElement("div");
        paletteWrap.className = "create-task-title-tag-color-palette";
        paletteWrap.dataset.taskDetailEditTitleTagColorPalette = tagValue;

        taskTitleTagPalette.forEach((paletteColor) => {
          const paletteButton = document.createElement("button");
          paletteButton.type = "button";
          paletteButton.className = "create-task-title-tag-color-option";
          paletteButton.dataset.taskDetailEditTitleTagColorOption = "1";
          paletteButton.dataset.taskDetailEditTitleTagColorTag = tagValue;
          paletteButton.dataset.taskDetailEditTitleTagColorValue = paletteColor;
          paletteButton.setAttribute("aria-label", `Aplicar cor ${paletteColor} para ${tagValue}`);
          paintTagColorSwatch(paletteButton, paletteColor, true);
          if (paletteColor === tagColor) {
            paletteButton.classList.add("is-active");
          }
          paletteButton.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            createTaskOpenColorPaletteTag = "";
            taskDetailEditOpenColorPaletteTag = "";
            applyTaskTitleTagColorEverywhere(tagValue, paletteColor);
          });
          paletteWrap.append(paletteButton);
        });

        row.append(paletteWrap);
      }
      taskDetailEditTitleTagMenu.append(row);
    });

    const createButton = document.createElement("button");
    createButton.type = "button";
    createButton.className = "create-task-title-tag-create";
    createButton.dataset.taskDetailEditTitleTagCreate = "1";
    createButton.textContent = "Criar tag";
    taskDetailEditTitleTagMenu.append(createButton);
  };

  const openTaskDetailTitleTagMenu = () => {
    if (!(taskDetailEditTitleTagMenu instanceof HTMLElement)) return;
    if (taskDetailEditTitleTagIsCreating) return;

    renderTaskDetailTitleTagMenu();
    taskDetailEditTitleTagMenu.hidden = false;
    if (taskDetailEditTitleTagPicker instanceof HTMLElement) {
      taskDetailEditTitleTagPicker.classList.add("is-open");
    }
    if (taskDetailEditTitleTagTrigger instanceof HTMLButtonElement) {
      taskDetailEditTitleTagTrigger.setAttribute("aria-expanded", "true");
    }
  };

  const setTaskDetailTitleTagValue = (value = "", colorValue = "") => {
    taskDetailEditCurrentTitleTag = normalizeTaskTitleTagValue(value);
    taskDetailEditOpenColorPaletteTag = "";
    const explicitColor = normalizeTaskTitleTagColorLoose(colorValue);
    taskDetailEditCurrentTitleTagColor = taskDetailEditCurrentTitleTag
      ? explicitColor
        ? resolveTaskTitleTagColor(taskDetailEditCurrentTitleTag, explicitColor)
        : resolveTaskTitleTagColor(taskDetailEditCurrentTitleTag)
      : normalizeTaskTitleTagColorValue(
          colorValue || taskDetailEditCurrentTitleTagColor,
          taskTitleTagDefaultColor
        );
    syncTaskDetailTitleTagTrigger();
    renderTaskDetailTitleTagMenu();
  };

  const stopTaskDetailTitleTagCreation = ({ focusTrigger = false } = {}) => {
    taskDetailEditTitleTagIsCreating = false;
    if (taskDetailEditTitleComposer instanceof HTMLElement) {
      taskDetailEditTitleComposer.classList.remove("is-creating-tag");
    }
    if (taskDetailEditTitleTagCustom instanceof HTMLInputElement) {
      taskDetailEditTitleTagCustom.hidden = true;
      taskDetailEditTitleTagCustom.value = "";
    }
    if (taskDetailEditTitleTagTrigger instanceof HTMLButtonElement) {
      taskDetailEditTitleTagTrigger.hidden = false;
      if (focusTrigger) {
        taskDetailEditTitleTagTrigger.focus();
      }
    }
    syncTaskDetailTitleTagTrigger();
  };

  const commitTaskDetailTitleTagCreation = () => {
    if (taskDetailEditTitleTagCustom instanceof HTMLInputElement) {
      applyFirstLetterUppercaseToInput(taskDetailEditTitleTagCustom);
      const newTag = normalizeTaskTitleTagValue(taskDetailEditTitleTagCustom.value || "");
      if (newTag) {
        createTaskTitleTagOptions = normalizeTaskTitleTagCollection([
          ...createTaskTitleTagOptions,
          newTag,
        ]);
        taskDetailEditCurrentTitleTag = newTag;
        taskDetailEditCurrentTitleTagColor = resolveTaskTitleTagColor(newTag);
        void persistTaskTitleTagOptionChange("add_task_title_tag_option", newTag);
      }
    }
    stopTaskDetailTitleTagCreation();
    renderTaskDetailTitleTagMenu();
    renderCreateTaskTitleTagMenu();
    return normalizeTaskTitleTagValue(taskDetailEditCurrentTitleTag);
  };

  const startTaskDetailTitleTagCreation = () => {
    if (!(taskDetailEditTitleTagCustom instanceof HTMLInputElement)) return;
    closeTaskDetailTitleTagMenu();
    taskDetailEditTitleTagIsCreating = true;
    if (taskDetailEditTitleComposer instanceof HTMLElement) {
      taskDetailEditTitleComposer.classList.add("is-creating-tag");
    }
    if (taskDetailEditTitleTagTrigger instanceof HTMLButtonElement) {
      taskDetailEditTitleTagTrigger.hidden = true;
    }
    taskDetailEditTitleTagCustom.hidden = false;
    taskDetailEditTitleTagCustom.value = "";
    taskDetailEditTitleTagCustom.focus();
  };

  const resetTaskDetailTitleTagPicker = (value = "", colorValue = "") => {
    closeTaskDetailTitleTagMenu();
    stopTaskDetailTitleTagCreation();
    setTaskDetailTitleTagValue(value, colorValue);
  };

  const taskTitleTagConfig = readTaskTitleTagOptionsFromData();
  taskTitleTagPalette = normalizeTaskTitleTagPalette(taskTitleTagConfig.palette || []);
  taskTitleTagDefaultColor = normalizeTaskTitleTagColorValue(
    taskTitleTagConfig.defaultColor || "",
    taskTitleTagPalette[0] || TASK_TITLE_TAG_DEFAULT_PALETTE[0]
  );
  taskTitleTagColorsByKey = new Map();
  if (taskTitleTagConfig.tagColors && typeof taskTitleTagConfig.tagColors === "object") {
    Object.entries(taskTitleTagConfig.tagColors).forEach(([tagValue, colorValue]) => {
      setTaskTitleTagColorForTag(tagValue, String(colorValue || ""));
    });
  }
  createTaskTitleTagOptions = normalizeTaskTitleTagCollection(taskTitleTagConfig.options || []);
  resetCreateTaskTitleTagPicker();
  resetTaskDetailTitleTagPicker();

  const setCreateTaskSubtasksDependencyEnabled = (enabled) => {
    createTaskSubtasksDependencyEnabled = Boolean(enabled);
    if (createTaskSubtasksDependencyToggle instanceof HTMLInputElement) {
      createTaskSubtasksDependencyToggle.checked = createTaskSubtasksDependencyEnabled;
    }
    writeTaskSubtasksDependencyField(
      createTaskSubtasksDependencyInput,
      createTaskSubtasksDependencyEnabled
    );
    if (createTaskSubtasksField instanceof HTMLTextAreaElement) {
      writeTaskSubtasksField(createTaskSubtasksField, createTaskSubtaskItems, {
        enforceDependency: createTaskSubtasksDependencyEnabled,
      });
    }
  };

  const setTaskDetailEditSubtasksDependencyEnabled = (enabled) => {
    taskDetailEditSubtasksDependencyEnabled = Boolean(enabled);
    if (taskDetailEditSubtasksDependencyToggle instanceof HTMLInputElement) {
      taskDetailEditSubtasksDependencyToggle.checked = taskDetailEditSubtasksDependencyEnabled;
    }
    writeTaskSubtasksDependencyField(
      taskDetailEditSubtasksDependencyInput,
      taskDetailEditSubtasksDependencyEnabled
    );
    if (taskDetailEditSubtasksField instanceof HTMLTextAreaElement) {
      writeTaskSubtasksField(taskDetailEditSubtasksField, taskDetailEditSubtaskItems, {
        enforceDependency: taskDetailEditSubtasksDependencyEnabled,
      });
    }
  };

  function readTaskRowSubtasksDependencyEnabled(scope) {
    if (scope instanceof HTMLFormElement) {
      return readTaskSubtasksDependencyField(
        scope.querySelector("[data-task-subtasks-dependency]"),
        false
      );
    }

    if (scope instanceof HTMLElement) {
      const form = scope.matches("form")
        ? scope
        : scope.querySelector?.("[data-task-autosave-form]");
      if (form instanceof HTMLFormElement) {
        return readTaskSubtasksDependencyField(
          form.querySelector("[data-task-subtasks-dependency]"),
          false
        );
      }
    }

    return false;
  }

  function renderTaskRowSubtasksProgress(taskItem, subtasks) {
    if (!(taskItem instanceof HTMLElement)) return;
    const progressWrap = taskItem.querySelector("[data-task-subtasks-progress]");
    if (!(progressWrap instanceof HTMLElement)) return;

    const stepsWrap = progressWrap.querySelector("[data-task-subtasks-progress-steps]");
    const textEl = progressWrap.querySelector("[data-task-subtasks-progress-text]");
    const dependencyEnabled = readTaskRowSubtasksDependencyEnabled(taskItem);
    const progress = taskSubtasksProgressMeta(subtasks, {
      enforceDependency: dependencyEnabled,
    });
    const items = progress.items;

    if (!(stepsWrap instanceof HTMLElement) || !(textEl instanceof HTMLElement)) {
      return;
    }

    if (!items.length) {
      progressWrap.classList.add("is-hidden");
      stepsWrap.innerHTML = "";
      textEl.textContent = "";
      return;
    }

    progressWrap.classList.remove("is-hidden");
    stepsWrap.innerHTML = "";

    items.forEach((_item, index) => {
      const dot = document.createElement("span");
      dot.className = "task-subtasks-progress-step";
      if (index < progress.completed) {
        dot.classList.add("is-done");
      }
      dot.setAttribute("aria-hidden", "true");
      stepsWrap.append(dot);
    });

    textEl.textContent = `${progress.completed}/${progress.total} etapas`;
  }

  function syncTaskRowSubtasksFromField(form, explicitTaskItem = null) {
    if (!(form instanceof HTMLFormElement)) return;
    const subtasksField = form.querySelector("[data-task-subtasks-json]");
    if (!(subtasksField instanceof HTMLInputElement)) return;

    const taskItem =
      explicitTaskItem instanceof HTMLElement
        ? explicitTaskItem
        : form.closest("[data-task-item]");
    if (!(taskItem instanceof HTMLElement)) return;

    const dependencyEnabled = readTaskRowSubtasksDependencyEnabled(form);
    const subtasks = readTaskSubtasksField(subtasksField, {
      enforceDependency: dependencyEnabled,
    });
    writeTaskSubtasksField(subtasksField, subtasks, {
      enforceDependency: dependencyEnabled,
    });
    renderTaskRowSubtasksProgress(taskItem, subtasks);
  }

  const renderTaskSubtasksViewList = ({
    subtasks = [],
    readOnly = false,
    editable = true,
    dependencyEnabled = false,
  } = {}) => {
    if (!(taskDetailViewSubtasks instanceof HTMLElement)) return;

    const normalized = parseTaskSubtaskList(subtasks || [], 40, {
      enforceDependency: dependencyEnabled,
    });
    taskDetailViewSubtasks.innerHTML = "";

    if (!normalized.length) {
      if (taskDetailViewSubtasksWrap instanceof HTMLElement) {
        taskDetailViewSubtasksWrap.hidden = true;
      }
      return;
    }

    if (taskDetailViewSubtasksWrap instanceof HTMLElement) {
      taskDetailViewSubtasksWrap.hidden = false;
    }

    normalized.forEach((item, index) => {
      const row = document.createElement("label");
      row.className = "task-detail-subtask-row";

      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.className = "task-detail-subtask-check";
      checkbox.dataset.taskDetailSubtaskToggle = String(index);
      checkbox.checked = Boolean(item.done);
      const isUnlocked =
        !dependencyEnabled || index === 0 || Boolean(normalized[index - 1]?.done) || item.done;
      checkbox.disabled = readOnly || !editable || (dependencyEnabled && !isUnlocked && !item.done);
      if (dependencyEnabled && !isUnlocked && !item.done) {
        row.classList.add("is-locked");
      }
      if (item.done) {
        row.classList.add("is-done");
      }

      const text = document.createElement("span");
      text.className = "task-detail-subtask-text";
      text.textContent = item.title || `Etapa ${index + 1}`;

      row.append(checkbox, text);
      taskDetailViewSubtasks.append(row);
    });
  };

  const setTaskDetailEditSubtasks = (subtasks, { dependencyEnabled = false } = {}) => {
    setTaskDetailEditSubtasksDependencyEnabled(dependencyEnabled);
    taskDetailEditSubtaskItems = parseTaskSubtaskList(subtasks || [], 40, {
      enforceDependency: taskDetailEditSubtasksDependencyEnabled,
    });
    if (taskDetailEditSubtasksField instanceof HTMLTextAreaElement) {
      writeTaskSubtasksField(taskDetailEditSubtasksField, taskDetailEditSubtaskItems, {
        enforceDependency: taskDetailEditSubtasksDependencyEnabled,
      });
    }
  };

  const setCreateTaskSubtasks = (subtasks, { dependencyEnabled = false } = {}) => {
    setCreateTaskSubtasksDependencyEnabled(dependencyEnabled);
    createTaskSubtaskItems = parseTaskSubtaskList(subtasks || [], 40, {
      enforceDependency: createTaskSubtasksDependencyEnabled,
    });
    if (createTaskSubtasksField instanceof HTMLTextAreaElement) {
      writeTaskSubtasksField(createTaskSubtasksField, createTaskSubtaskItems, {
        enforceDependency: createTaskSubtasksDependencyEnabled,
      });
    }
  };

  const writeReferenceLinksEditField = (field, links) => {
    if (!(field instanceof HTMLTextAreaElement) && !(field instanceof HTMLInputElement)) return;
    field.value = parseReferenceUrlLines(links || []).join("\n");
  };

  const openInlineAddForm = (form, input) => {
    if (form instanceof HTMLElement) {
      form.hidden = false;
    }
    if (input instanceof HTMLInputElement) {
      input.focus();
      input.select();
    }
  };

  const closeInlineAddForm = (form, input, { clear = true } = {}) => {
    if (form instanceof HTMLElement) {
      form.hidden = true;
    }
    if (clear && input instanceof HTMLInputElement) {
      input.value = "";
    }
  };

  const renderReferenceLinksEditList = ({ container, links = [], removeAttribute = "" } = {}) => {
    if (!(container instanceof HTMLElement)) return;

    const safeLinks = parseReferenceUrlLines(links || []);
    container.innerHTML = "";
    if (!safeLinks.length) return;

    safeLinks.forEach((url, index) => {
      const row = document.createElement("div");
      row.className = "task-reference-edit-item";

      const link = document.createElement("a");
      link.href = url;
      link.target = "_blank";
      link.rel = "noreferrer noopener";
      link.className = "task-reference-edit-link";
      link.title = url;

      const faviconUrl = buildReferenceFaviconUrl(url);
      if (faviconUrl) {
        const icon = document.createElement("img");
        icon.src = faviconUrl;
        icon.alt = "";
        icon.className = "task-reference-edit-favicon";
        icon.loading = "lazy";
        icon.decoding = "async";
        icon.referrerPolicy = "no-referrer";
        icon.setAttribute("aria-hidden", "true");
        icon.onerror = () => {
          icon.remove();
          link.classList.add("task-reference-edit-link-no-favicon");
        };
        link.append(icon);
      } else {
        link.classList.add("task-reference-edit-link-no-favicon");
      }

      const label = document.createElement("span");
      label.className = "task-reference-edit-label";
      label.textContent = formatReferenceLinkLabel(url) || url;
      link.append(label);

      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "task-inline-item-remove";
      remove.setAttribute("aria-label", "Remover link");
      remove.textContent = "X";
      if (removeAttribute) {
        remove.setAttribute(removeAttribute, String(index));
      }

      row.append(link, remove);
      container.append(row);
    });
  };

  const renderCreateTaskReferenceLinks = () => {
    renderReferenceLinksEditList({
      container: createTaskLinksList,
      links: createTaskReferenceLinks,
      removeAttribute: "data-create-task-link-remove",
    });
  };

  const renderTaskDetailEditReferenceLinks = () => {
    renderReferenceLinksEditList({
      container: taskDetailEditLinksList,
      links: taskDetailEditReferenceLinks,
      removeAttribute: "data-task-detail-edit-link-remove",
    });
  };

  const setCreateTaskReferenceLinks = (links = []) => {
    createTaskReferenceLinks = parseReferenceUrlLines(links || []);
    writeReferenceLinksEditField(createTaskLinksField, createTaskReferenceLinks);
    renderCreateTaskReferenceLinks();
  };

  const setTaskDetailEditReferenceLinks = (links = []) => {
    taskDetailEditReferenceLinks = parseReferenceUrlLines(links || []);
    writeReferenceLinksEditField(taskDetailEditLinks, taskDetailEditReferenceLinks);
    renderTaskDetailEditReferenceLinks();
  };

  const addReferenceLinkFromInput = ({ input, currentLinks = [], setLinks } = {}) => {
    if (!(input instanceof HTMLInputElement) || typeof setLinks !== "function") return false;
    const raw = String(input.value || "").trim();
    if (!raw) return false;
    const normalized = parseReferenceUrlLines([raw], 1);
    if (!normalized.length) return false;

    setLinks(parseReferenceUrlLines([...(currentLinks || []), normalized[0]]));
    input.value = "";
    return true;
  };

  const renderTaskDetailSubtasksEditList = () => {
    if (!(taskDetailEditSubtasksList instanceof HTMLElement)) return;

    taskDetailEditSubtasksList.innerHTML = "";
    if (!taskDetailEditSubtaskItems.length) return;

    taskDetailEditSubtaskItems.forEach((item, index) => {
      const row = document.createElement("div");
      row.className = "task-subtasks-edit-row";

      const check = document.createElement("input");
      check.type = "checkbox";
      check.className = "task-subtasks-edit-check";
      check.dataset.taskDetailEditSubtaskDone = String(index);
      check.checked = Boolean(item.done);
      const checkLocked =
        taskDetailEditSubtasksDependencyEnabled &&
        index > 0 &&
        !taskDetailEditSubtaskItems[index - 1]?.done &&
        !item.done;
      check.disabled = checkLocked;
      if (checkLocked) {
        row.classList.add("is-locked");
      }

      const title = document.createElement("input");
      title.type = "text";
      title.maxLength = 120;
      title.className = "task-subtasks-edit-title";
      title.dataset.taskDetailEditSubtaskTitle = String(index);
      title.value = item.title || "";

      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "task-subtasks-edit-remove";
      remove.dataset.taskDetailEditSubtaskRemove = String(index);
      remove.setAttribute("aria-label", "Remover etapa");
      remove.textContent = "X";

      row.append(check, title, remove);
      taskDetailEditSubtasksList.append(row);
    });

    if (taskDetailEditSubtasksField instanceof HTMLTextAreaElement) {
      writeTaskSubtasksField(taskDetailEditSubtasksField, taskDetailEditSubtaskItems, {
        enforceDependency: taskDetailEditSubtasksDependencyEnabled,
      });
    }
  };

  const renderCreateTaskSubtasksEditList = () => {
    if (!(createTaskSubtasksList instanceof HTMLElement)) return;

    createTaskSubtasksList.innerHTML = "";
    if (!createTaskSubtaskItems.length) return;

    createTaskSubtaskItems.forEach((item, index) => {
      const row = document.createElement("div");
      row.className = "task-subtasks-edit-row";

      const check = document.createElement("input");
      check.type = "checkbox";
      check.className = "task-subtasks-edit-check";
      check.dataset.createTaskSubtaskDone = String(index);
      check.checked = Boolean(item.done);
      const checkLocked =
        createTaskSubtasksDependencyEnabled &&
        index > 0 &&
        !createTaskSubtaskItems[index - 1]?.done &&
        !item.done;
      check.disabled = checkLocked;
      if (checkLocked) {
        row.classList.add("is-locked");
      }

      const title = document.createElement("input");
      title.type = "text";
      title.maxLength = 120;
      title.className = "task-subtasks-edit-title";
      title.dataset.createTaskSubtaskTitle = String(index);
      title.value = item.title || "";

      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "task-subtasks-edit-remove";
      remove.dataset.createTaskSubtaskRemove = String(index);
      remove.setAttribute("aria-label", "Remover etapa");
      remove.textContent = "X";

      row.append(check, title, remove);
      createTaskSubtasksList.append(row);
    });

    if (createTaskSubtasksField instanceof HTMLTextAreaElement) {
      writeTaskSubtasksField(createTaskSubtasksField, createTaskSubtaskItems, {
        enforceDependency: createTaskSubtasksDependencyEnabled,
      });
    }
  };

  const normalizeTaskPreviewMediaItem = (value) => {
    const mediaItem = normalizeReferenceImageMediaItem(value);
    if (!mediaItem) return null;

    const previewUrl = referenceMediaPreviewUrl(mediaItem);
    if (!previewUrl) return null;

    return {
      ...mediaItem,
      kind: referenceMediaKind(mediaItem),
      previewUrl,
      thumbnailPreviewUrl: referenceMediaThumbnailUrl(mediaItem),
    };
  };

  const referenceMediaDisplayLabel = (item) => {
    if (!item || typeof item !== "object") return "";

    const explicitTitle = normalizeReferenceImageTitle(item.title || "");
    if (explicitTitle) return explicitTitle;

    const explicitName = normalizeReferenceMediaName(item.name || item.label || "");
    if (explicitName) return explicitName;

    const rawUrl = String(
      item.downloadUrl || item.webViewLink || item.src || item.thumbnailUrl || item.previewUrl || ""
    ).trim();
    if (!rawUrl) return "";

    try {
      const parsed = new URL(rawUrl, window.location.origin);
      const segments = parsed.pathname.split("/").filter(Boolean);
      const lastSegment = segments.length ? segments[segments.length - 1] : "";
      return lastSegment ? decodeURIComponent(lastSegment) : "";
    } catch (_error) {
      return "";
    }
  };

  const normalizeTaskImagePreviewCollection = (images = []) => {
    const seen = new Set();
    const items = [];

    parseReferenceImageMediaItems(images || []).forEach((mediaItem) => {
      const normalized = normalizeTaskPreviewMediaItem(mediaItem);
      if (!normalized) return;

      const itemKey = referenceMediaItemKey(normalized) || normalized.previewUrl;
      if (!itemKey || seen.has(itemKey)) return;
      seen.add(itemKey);
      items.push(normalized);
    });

    return items;
  };

  const syncTaskImagePreviewNavigation = () => {
    const total = taskImagePreviewState.items.length;
    const hasMultiple = total > 1;

    if (taskImagePreviewPrevButton instanceof HTMLButtonElement) {
      const canGoPrev = hasMultiple && taskImagePreviewState.currentIndex > 0;
      taskImagePreviewPrevButton.hidden = !canGoPrev;
      taskImagePreviewPrevButton.disabled = !canGoPrev;
    }

    if (taskImagePreviewNextButton instanceof HTMLButtonElement) {
      const canGoNext = hasMultiple && taskImagePreviewState.currentIndex < total - 1;
      taskImagePreviewNextButton.hidden = !canGoNext;
      taskImagePreviewNextButton.disabled = !canGoNext;
    }
  };

  const showTaskImagePreviewByIndex = (index) => {
    if (!(taskImagePreviewImage instanceof HTMLImageElement)) return false;
    if (!(taskImagePreviewVideo instanceof HTMLVideoElement)) return false;
    const total = taskImagePreviewState.items.length;
    if (!(total > 0)) return false;

    const nextIndex = Math.max(0, Math.min(total - 1, Number.parseInt(String(index || "0"), 10) || 0));
    const previewItem = taskImagePreviewState.items[nextIndex] || null;
    const previewUrl = String(previewItem?.previewUrl || "").trim();
    if (!previewItem || !previewUrl) return false;
    if (taskImagePreviewTitle instanceof HTMLElement) {
      taskImagePreviewTitle.textContent =
        referenceMediaDisplayLabel(previewItem) || `Midia ${nextIndex + 1}`;
      taskImagePreviewTitle.title = taskImagePreviewTitle.textContent;
    }

    taskImagePreviewState.currentIndex = nextIndex;
    if (previewItem.kind === "video") {
      taskImagePreviewModal.classList.add("is-video-preview");
      taskImagePreviewImage.hidden = true;
      taskImagePreviewImage.src = "";
      taskImagePreviewImage.removeAttribute("src");
      taskImagePreviewImage.alt = "Imagem de referencia ampliada";

      taskImagePreviewVideo.pause();
      try {
        taskImagePreviewVideo.currentTime = 0;
      } catch (_error) {
        // Ignore reset failures before metadata is available.
      }
      taskImagePreviewVideo.removeAttribute("src");
      taskImagePreviewVideo.load();
      taskImagePreviewVideo.hidden = false;
      taskImagePreviewVideo.preload = "auto";
      taskImagePreviewVideo.src = previewUrl;
      const posterUrl = String(previewItem.thumbnailPreviewUrl || "").trim();
      if (posterUrl) {
        taskImagePreviewVideo.poster = posterUrl;
      } else {
        taskImagePreviewVideo.removeAttribute("poster");
      }
      taskImagePreviewVideo.setAttribute("aria-label", `Video de referencia ${nextIndex + 1} de ${total}`);
      taskImagePreviewVideo.load();
    } else {
      taskImagePreviewModal.classList.remove("is-video-preview");
      taskImagePreviewVideo.pause();
      taskImagePreviewVideo.hidden = true;
      taskImagePreviewVideo.removeAttribute("src");
      taskImagePreviewVideo.removeAttribute("poster");
      taskImagePreviewVideo.removeAttribute("aria-label");

      taskImagePreviewImage.hidden = false;
      taskImagePreviewImage.src = previewUrl;
      taskImagePreviewImage.alt = `Imagem de referencia ${nextIndex + 1} de ${total}`;
    }
    syncTaskImagePreviewNavigation();
    return true;
  };

  const stepTaskImagePreview = (step = 0) => {
    const delta = Number.parseInt(String(step || "0"), 10) || 0;
    if (!delta) return;
    if (!(taskImagePreviewState.items.length > 1)) return;
    const targetIndex = taskImagePreviewState.currentIndex + delta;
    showTaskImagePreviewByIndex(targetIndex);
  };

  const closeTaskImagePreview = () => {
    if (!(taskImagePreviewModal instanceof HTMLElement)) return;
    taskImagePreviewModal.hidden = true;
    taskImagePreviewState.currentIndex = -1;
    taskImagePreviewState.items = [];
    if (taskImagePreviewTitle instanceof HTMLElement) {
      taskImagePreviewTitle.textContent = "";
      taskImagePreviewTitle.removeAttribute("title");
    }
    if (taskImagePreviewImage instanceof HTMLImageElement) {
      taskImagePreviewImage.src = "";
      taskImagePreviewImage.removeAttribute("src");
      taskImagePreviewImage.alt = "Imagem de referencia ampliada";
      taskImagePreviewImage.hidden = false;
    }
    if (taskImagePreviewVideo instanceof HTMLVideoElement) {
      taskImagePreviewVideo.pause();
      taskImagePreviewVideo.hidden = true;
      taskImagePreviewVideo.removeAttribute("src");
      taskImagePreviewVideo.removeAttribute("poster");
      taskImagePreviewVideo.removeAttribute("aria-label");
      taskImagePreviewVideo.load();
    }
    taskImagePreviewModal.classList.remove("is-video-preview");
    syncTaskImagePreviewNavigation();
    syncBodyModalLock();
  };

  const openTaskImagePreview = ({ src = "", items = null, images = null, index = 0 } = {}) => {
    if (!(taskImagePreviewModal instanceof HTMLElement)) return;
    if (!(taskImagePreviewImage instanceof HTMLImageElement)) return;
    if (!(taskImagePreviewVideo instanceof HTMLVideoElement)) return;

    const sourceImages = Array.isArray(items)
      ? items
      : Array.isArray(images)
        ? images
        : taskImagePreviewState.items;
    const normalizedImages = normalizeTaskImagePreviewCollection(sourceImages);
    const fallbackSrc = String(src || "").trim();
    if (!normalizedImages.length && !fallbackSrc) return;

    if (!normalizedImages.length && fallbackSrc) {
      const fallbackItem = normalizeTaskPreviewMediaItem({ src: fallbackSrc });
      if (fallbackItem) {
        normalizedImages.push(fallbackItem);
      }
    } else if (
      fallbackSrc &&
      !normalizedImages.some(
        (item) =>
          item.previewUrl === fallbackSrc ||
          item.thumbnailPreviewUrl === fallbackSrc ||
          item.src === fallbackSrc
      )
    ) {
      const fallbackItem = normalizeTaskPreviewMediaItem({ src: fallbackSrc });
      if (fallbackItem) {
        normalizedImages.push(fallbackItem);
      }
    }

    taskImagePreviewState.items = normalizedImages;

    const parsedIndex = Number.parseInt(String(index || "0"), 10);
    const hasProvidedIndex = Number.isFinite(parsedIndex) && parsedIndex >= 0;
    const fallbackIndex = fallbackSrc
      ? normalizedImages.findIndex(
          (item) =>
            item.previewUrl === fallbackSrc ||
            item.thumbnailPreviewUrl === fallbackSrc ||
            item.src === fallbackSrc
        )
      : 0;
    const startIndex = hasProvidedIndex
      ? parsedIndex
      : fallbackIndex >= 0
        ? fallbackIndex
        : 0;

    if (!showTaskImagePreviewByIndex(startIndex)) return;

    taskImagePreviewModal.hidden = false;
    syncBodyModalLock();
  };

  const getDefaultGroupName = () => {
    const bodyDefault = document.body?.dataset?.defaultGroupName?.trim();
    if (bodyDefault) return bodyDefault;

    const firstGroupSection =
      document.querySelector('[data-task-group][data-group-can-access="1"]') ||
      document.querySelector("[data-task-group]");
    const firstGroupName = firstGroupSection?.dataset?.groupName?.trim();
    if (firstGroupName) return firstGroupName;

    if (createTaskGroupInput instanceof HTMLSelectElement && createTaskGroupInput.options.length > 0) {
      const optionName = createTaskGroupInput.options[0]?.value?.trim();
      if (optionName) return optionName;
    }

    return "Geral";
  };

  let taskGroupReorderMode = false;
  let draggedTaskGroup = null;
  let draggedTaskGroupInitialOrder = [];

  const normalizeTaskGroupNameKey = (value) =>
    String(value || "").trim().toLocaleLowerCase("pt-BR");

  const getTaskGroupOrderStorageKey = () => {
    const workspaceId = String(document.body?.dataset?.workspaceId || "").trim() || "default";
    return `wf_task_group_order:${workspaceId}`;
  };

  const getTaskGroupSections = () => {
    if (!(taskGroupsListElement instanceof HTMLElement)) return [];
    return Array.from(taskGroupsListElement.querySelectorAll("[data-task-group]")).filter(
      (section) => section instanceof HTMLElement
    );
  };

  const getCurrentTaskGroupOrder = () =>
    getTaskGroupSections()
      .map((section) => String(section.dataset.groupName || "").trim())
      .filter((name) => name !== "");

  const persistTaskGroupOrder = () => {
    if (!window.localStorage) return;
    const key = getTaskGroupOrderStorageKey();
    const order = getCurrentTaskGroupOrder();
    try {
      if (!order.length) {
        window.localStorage.removeItem(key);
        return;
      }
      window.localStorage.setItem(key, JSON.stringify(order));
    } catch (error) {
      // noop
    }
  };

  const replaceStoredTaskGroupName = (oldName, nextName) => {
    if (!window.localStorage) return;

    const previous = String(oldName || "").trim();
    const current = String(nextName || "").trim();
    if (!previous || !current) return;

    const key = getTaskGroupOrderStorageKey();
    let order = [];
    try {
      const raw = window.localStorage.getItem(key);
      const decoded = raw ? JSON.parse(raw) : [];
      order = Array.isArray(decoded) ? decoded.map((value) => String(value || "").trim()) : [];
    } catch (error) {
      order = [];
    }

    if (!order.length) return;

    const previousKey = normalizeTaskGroupNameKey(previous);
    let changed = false;
    order = order.map((value) => {
      if (!value || normalizeTaskGroupNameKey(value) !== previousKey) {
        return value;
      }
      changed = true;
      return current;
    });

    if (!changed) return;

    try {
      window.localStorage.setItem(key, JSON.stringify(order));
    } catch (error) {
      // noop
    }
  };

  const applyStoredTaskGroupOrder = () => {
    if (!(taskGroupsListElement instanceof HTMLElement) || !window.localStorage) return;

    const key = getTaskGroupOrderStorageKey();
    let storedOrder = [];
    try {
      const raw = window.localStorage.getItem(key);
      const decoded = raw ? JSON.parse(raw) : [];
      storedOrder = Array.isArray(decoded) ? decoded : [];
    } catch (error) {
      storedOrder = [];
    }

    if (!storedOrder.length) return;

    const sectionsByKey = new Map();
    getTaskGroupSections().forEach((section) => {
      const groupName = String(section.dataset.groupName || "").trim();
      if (!groupName) return;
      sectionsByKey.set(normalizeTaskGroupNameKey(groupName), section);
    });

    if (!sectionsByKey.size) return;

    let moved = false;
    storedOrder.forEach((groupNameRaw) => {
      const keyName = normalizeTaskGroupNameKey(groupNameRaw);
      const section = sectionsByKey.get(keyName);
      if (!(section instanceof HTMLElement)) return;
      taskGroupsListElement.append(section);
      sectionsByKey.delete(keyName);
      moved = true;
    });

    sectionsByKey.forEach((section) => {
      taskGroupsListElement.append(section);
    });

    if (moved && typeof syncTaskGroupInputs === "function") {
      syncTaskGroupInputs();
    }
  };

  const clearTaskGroupDropIndicators = () => {
    if (!(taskGroupsListElement instanceof HTMLElement)) return;
    taskGroupsListElement
      .querySelectorAll(".task-group.is-group-drop-before, .task-group.is-group-drop-after")
      .forEach((section) => section.classList.remove("is-group-drop-before", "is-group-drop-after"));
  };

  const setTaskGroupReorderMode = (enabled) => {
    const shouldEnable = Boolean(enabled) && taskGroupsListElement instanceof HTMLElement;
    taskGroupReorderMode = shouldEnable;

    if (taskGroupsListElement instanceof HTMLElement) {
      taskGroupsListElement.classList.toggle("is-reorder-mode", shouldEnable);
    }

    taskGroupReorderButtons.forEach((button) => {
      if (!(button instanceof HTMLElement)) return;
      button.classList.toggle("is-active", shouldEnable);
      button.setAttribute("aria-pressed", shouldEnable ? "true" : "false");
      button.setAttribute(
        "aria-label",
        shouldEnable ? "Desativar organizacao de grupos" : "Ativar organizacao de grupos"
      );
    });

    getTaskGroupSections().forEach((section) => {
      section.setAttribute("draggable", shouldEnable ? "true" : "false");
      const head = section.querySelector(".task-group-head");
      if (head instanceof HTMLElement) {
        head.setAttribute("draggable", shouldEnable ? "true" : "false");
      }
    });

    document.querySelectorAll("[data-task-item]").forEach((taskItem) => {
      if (!(taskItem instanceof HTMLElement)) return;
      const canDragTask = (taskItem.dataset.taskReadonly || "0") !== "1";
      taskItem.setAttribute("draggable", !shouldEnable && canDragTask ? "true" : "false");
    });

    if (!shouldEnable) {
      clearTaskGroupDropIndicators();
      if (draggedTaskGroup instanceof HTMLElement) {
        draggedTaskGroup.classList.remove("is-group-dragging");
      }
      draggedTaskGroup = null;
      draggedTaskGroupInitialOrder = [];
    }
  };

  const syncGroupPermissionsModal = (modalElement) => {
    if (!(modalElement instanceof HTMLElement)) return;

    const memberCheckboxes = Array.from(
      modalElement.querySelectorAll("[data-permission-enabled-checkbox]")
    ).filter((checkbox) => checkbox instanceof HTMLInputElement);

    const totalMembers = memberCheckboxes.length;
    const enabledMembers = memberCheckboxes.reduce(
      (total, checkbox) => total + (checkbox.checked ? 1 : 0),
      0
    );
    const countLabel = `${enabledMembers}/${totalMembers}`;

    const allCheckbox = modalElement.querySelector("[data-permission-all-checkbox]");
    if (allCheckbox instanceof HTMLInputElement) {
      allCheckbox.disabled = totalMembers === 0;
      allCheckbox.checked = totalMembers > 0 && enabledMembers === totalMembers;
      allCheckbox.indeterminate =
        totalMembers > 0 && enabledMembers > 0 && enabledMembers < totalMembers;
    }

    modalElement.querySelectorAll("[data-permission-counter]").forEach((counter) => {
      if (!(counter instanceof HTMLElement)) return;
      counter.textContent = `${countLabel} permitidos`;
    });
    modalElement.querySelectorAll("[data-permission-summary-count]").forEach((counter) => {
      if (!(counter instanceof HTMLElement)) return;
      counter.textContent = countLabel;
    });
  };

  const syncTaskDetailImageHiddenField = () => {
    writeReferenceImageMediaField(taskDetailEditImages, taskDetailEditImageItems);
  };

  const syncCreateTaskImageHiddenField = () => {
    writeReferenceImageMediaField(createTaskImagesField, createTaskImageItems);
  };

  const setCreateTaskImagePickerCompactMode = (compact) => {
    const isCompact = Boolean(compact);
    if (createTaskImagesFieldWrap instanceof HTMLElement) {
      createTaskImagesFieldWrap.classList.toggle("is-compact", isCompact);
    }
    if (createTaskMainRow instanceof HTMLElement) {
      createTaskMainRow.classList.toggle("is-images-compact", isCompact);
    }
  };

  const setTaskDetailImagePickerCompactMode = (compact) => {
    const isCompact = Boolean(compact);
    if (taskDetailImagesFieldWrap instanceof HTMLElement) {
      taskDetailImagesFieldWrap.classList.toggle("is-compact", isCompact);
    }
    if (taskDetailMainRow instanceof HTMLElement) {
      taskDetailMainRow.classList.toggle("is-images-compact", isCompact);
    }
  };

  const syncCreateTaskImagePickerLayout = () => {
    const shouldCompact = !createTaskImagePickerExpanded && createTaskImageItems.length === 0;
    setCreateTaskImagePickerCompactMode(shouldCompact);
  };

  const syncTaskDetailImagePickerLayout = () => {
    const shouldCompact = !taskDetailImagePickerExpanded && taskDetailEditImageItems.length === 0;
    setTaskDetailImagePickerCompactMode(shouldCompact);
  };

  const setCreateTaskMediaPage = (enabled) => {
    const isEnabled = Boolean(enabled);
    if (createTaskModal instanceof HTMLElement) {
      createTaskModal.classList.toggle("is-task-media-page", isEnabled);
    }
    if (createTaskModalCard instanceof HTMLElement) {
      createTaskModalCard.classList.toggle("is-task-media-page", isEnabled);
    }
    if (createTaskOpenMediaButton instanceof HTMLButtonElement) {
      createTaskOpenMediaButton.hidden = isEnabled;
    }
    if (createTaskBackMainButton instanceof HTMLButtonElement) {
      createTaskBackMainButton.hidden = !isEnabled;
    }
    if (createTaskSubmitButton instanceof HTMLButtonElement) {
      createTaskSubmitButton.hidden = isEnabled;
    }

    createTaskImagePickerExpanded = isEnabled || createTaskImageItems.length > 0;
    syncCreateTaskImagePickerLayout();
    if (isEnabled && createTaskImageAddButton instanceof HTMLButtonElement) {
      window.setTimeout(() => createTaskImageAddButton.focus(), 20);
    }
  };

  const setTaskDetailMediaPage = (enabled) => {
    const isEnabled = Boolean(enabled);
    if (taskDetailModal instanceof HTMLElement) {
      taskDetailModal.classList.toggle("is-task-media-page", isEnabled);
    }
    if (taskDetailModalCard instanceof HTMLElement) {
      taskDetailModalCard.classList.toggle("is-task-media-page", isEnabled);
    }
    if (taskDetailOpenMediaButton instanceof HTMLButtonElement) {
      taskDetailOpenMediaButton.hidden = isEnabled;
    }
    if (taskDetailBackMainButton instanceof HTMLButtonElement) {
      taskDetailBackMainButton.hidden = !isEnabled;
    }

    taskDetailImagePickerExpanded = isEnabled || taskDetailEditImageItems.length > 0;
    syncTaskDetailImagePickerLayout();
    if (isEnabled && taskDetailImageAddButton instanceof HTMLButtonElement) {
      window.setTimeout(() => taskDetailImageAddButton.focus(), 20);
    }
  };

  const collapseEmptyImagePickersIfOutside = (target) => {
    const clickedElement = target instanceof Element ? target : null;

    if (taskDetailImagePickerExpanded && taskDetailEditImageItems.length === 0) {
      const clickedInsideTaskDetailImagesField =
        clickedElement instanceof Element &&
        taskDetailImagesFieldWrap instanceof HTMLElement &&
        taskDetailImagesFieldWrap.contains(clickedElement);
      if (!clickedInsideTaskDetailImagesField) {
        taskDetailImagePickerExpanded = false;
        syncTaskDetailImagePickerLayout();
      }
    }

    if (createTaskImagePickerExpanded && createTaskImageItems.length === 0) {
      const clickedInsideCreateTaskImagesField =
        clickedElement instanceof Element &&
        createTaskImagesFieldWrap instanceof HTMLElement &&
        createTaskImagesFieldWrap.contains(clickedElement);
      if (!clickedInsideCreateTaskImagesField) {
        createTaskImagePickerExpanded = false;
        syncCreateTaskImagePickerLayout();
      }
    }
  };

  const createReferenceMediaKindOverlay = (mediaItem, { compact = false } = {}) => {
    const overlay = document.createElement("span");
    overlay.className = `task-reference-media-kind-overlay is-${referenceMediaKind(mediaItem)}${
      compact ? " is-compact" : ""
    }`;
    overlay.setAttribute("aria-hidden", "true");

    overlay.innerHTML = isVideoReferenceMediaItem(mediaItem)
      ? `
        <svg viewBox="0 0 20 20" focusable="false">
          <path d="M7.2 6.5v7l5.8-3.5-5.8-3.5Z" fill="currentColor" stroke="none"></path>
        </svg>
      `
      : `
        <svg viewBox="0 0 20 20" focusable="false">
          <rect x="3.1" y="4.2" width="13.8" height="11.6" rx="2.2"></rect>
          <circle cx="8" cy="8.3" r="1.3"></circle>
          <path d="M4.9 13.7 8.3 10.4l2.5 2.2 2.2-1.9 2.1 3"></path>
        </svg>
      `;

    return overlay;
  };

  const createReferenceMediaThumbnailElement = (
    mediaItem,
    { className = "task-detail-edit-image-preview", imageAlt = "Midia de referencia" } = {}
  ) => {
    const thumbnailUrl = referenceMediaThumbnailUrl(mediaItem);
    if (thumbnailUrl) {
      const image = document.createElement("img");
      image.src = thumbnailUrl;
      image.alt = imageAlt;
      image.className = className;
      image.loading = "lazy";
      image.decoding = "async";
      return image;
    }

    const placeholder = document.createElement("div");
    placeholder.className = `${className} task-detail-edit-image-placeholder`;
    placeholder.textContent = isVideoReferenceMediaItem(mediaItem) ? "Video" : "Imagem";
    return placeholder;
  };

  const createReferenceMediaPreviewButton = (
    mediaItem,
    attrName,
    index,
    { compact = false } = {}
  ) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = `task-detail-edit-image-preview-button${compact ? " is-compact" : ""}`;
    button.setAttribute(attrName, String(index));
    button.setAttribute(
      "aria-label",
      isVideoReferenceMediaItem(mediaItem)
        ? "Abrir video de referencia"
        : "Ampliar imagem de referencia"
    );

    const preview = createReferenceMediaThumbnailElement(mediaItem);
    button.append(preview, createReferenceMediaKindOverlay(mediaItem, { compact }));
    return button;
  };

  const appendReferenceMediaProviderBadge = (container, mediaItem) => {
    if (!isGoogleDriveMediaItem(mediaItem)) return;
    const badge = document.createElement("span");
    badge.className = "task-detail-edit-image-provider";
    badge.textContent = "Drive";
    container.append(badge);
  };

  const renderTaskDetailImageList = () => {
    if (!(taskDetailImageList instanceof HTMLElement)) return;

    taskDetailImageList.innerHTML = "";
    if (!taskDetailEditImageItems.length) {
      syncTaskDetailImagePickerLayout();
      return;
    }

    taskDetailEditImageItems.forEach((imageValue, index) => {
      const mediaItem = normalizeReferenceImageMediaItem(imageValue);
      if (!mediaItem) return;
      const item = document.createElement("div");
      item.className = "task-detail-edit-image-item";

      const preview = createReferenceMediaPreviewButton(
        mediaItem,
        "data-task-detail-image-preview",
        index
      );

      const titleInput = document.createElement("input");
      titleInput.type = "text";
      titleInput.maxLength = maxReferenceImageTitleChars;
      titleInput.className = "task-detail-edit-image-title";
      titleInput.placeholder = "Título da mídia";
      titleInput.value = mediaItem.title;
      titleInput.dataset.taskDetailImageTitle = String(index);
      titleInput.setAttribute("aria-label", "Título da mídia");

      const removeButton = document.createElement("button");
      removeButton.type = "button";
      removeButton.className = "task-detail-edit-image-remove";
      removeButton.dataset.taskDetailImageRemove = String(index);
      removeButton.setAttribute("aria-label", "Remover imagem de referencia");
      removeButton.textContent = "x";

      const topBar = document.createElement("div");
      topBar.className = "task-detail-edit-image-topbar";
      topBar.append(titleInput, removeButton);

      item.append(preview, topBar);
      appendReferenceMediaProviderBadge(item, mediaItem);
      taskDetailImageList.append(item);
    });
    syncTaskDetailImagePickerLayout();
  };

  const renderCreateTaskImageList = () => {
    if (!(createTaskImageList instanceof HTMLElement)) return;

    createTaskImageList.innerHTML = "";
    if (!createTaskImageItems.length) {
      syncCreateTaskImagePickerLayout();
      return;
    }

    createTaskImageItems.forEach((imageValue, index) => {
      const mediaItem = normalizeReferenceImageMediaItem(imageValue);
      if (!mediaItem) return;
      const item = document.createElement("div");
      item.className = "task-detail-edit-image-item";

      const preview = createReferenceMediaPreviewButton(
        mediaItem,
        "data-create-task-image-preview",
        index
      );

      const titleInput = document.createElement("input");
      titleInput.type = "text";
      titleInput.maxLength = maxReferenceImageTitleChars;
      titleInput.className = "task-detail-edit-image-title";
      titleInput.placeholder = "Título da mídia";
      titleInput.value = mediaItem.title;
      titleInput.dataset.createTaskImageTitle = String(index);
      titleInput.setAttribute("aria-label", "Título da mídia");

      const removeButton = document.createElement("button");
      removeButton.type = "button";
      removeButton.className = "task-detail-edit-image-remove";
      removeButton.dataset.createTaskImageRemove = String(index);
      removeButton.setAttribute("aria-label", "Remover imagem de referencia");
      removeButton.textContent = "x";

      const topBar = document.createElement("div");
      topBar.className = "task-detail-edit-image-topbar";
      topBar.append(titleInput, removeButton);

      item.append(preview, topBar);
      appendReferenceMediaProviderBadge(item, mediaItem);
      createTaskImageList.append(item);
    });
    syncCreateTaskImagePickerLayout();
  };

  const setTaskDetailEditImageItems = (items) => {
    taskDetailEditImageItems = parseReferenceImageMediaItems(items || []);
    if (taskDetailEditImageItems.length) {
      taskDetailImagePickerExpanded = true;
    }
    syncTaskDetailImageHiddenField();
    renderTaskDetailImageList();
  };

  const mergeTaskDetailEditImageItems = (items) => {
    const merged = parseReferenceImageMediaItems([...(taskDetailEditImageItems || []), ...(items || [])]);
    taskDetailEditImageItems = merged;
    if (taskDetailEditImageItems.length) {
      taskDetailImagePickerExpanded = true;
    }
    syncTaskDetailImageHiddenField();
    renderTaskDetailImageList();
  };

  const setCreateTaskImageItems = (items) => {
    createTaskImageItems = parseReferenceImageMediaItems(items || []);
    if (createTaskImageItems.length) {
      createTaskImagePickerExpanded = true;
    }
    syncCreateTaskImageHiddenField();
    renderCreateTaskImageList();
  };

  const mergeCreateTaskImageItems = (items) => {
    const merged = parseReferenceImageMediaItems([...(createTaskImageItems || []), ...(items || [])]);
    createTaskImageItems = merged;
    if (createTaskImageItems.length) {
      createTaskImagePickerExpanded = true;
    }
    syncCreateTaskImageHiddenField();
    renderCreateTaskImageList();
  };

  const readFileAsDataUrl = (file) =>
    new Promise((resolve, reject) => {
      if (!(file instanceof File)) {
        reject(new Error("Arquivo inválido."));
        return;
      }

      const reader = new FileReader();
      reader.onload = () => {
        resolve(String(reader.result || ""));
      };
      reader.onerror = () => {
        reject(reader.error || new Error("Falha ao ler imagem."));
      };
      reader.readAsDataURL(file);
    });

  const addTaskDetailImagesFromFiles = async (files) => {
    const imageFiles = Array.from(files || []).filter(
      (file) => file instanceof File && String(file.type || "").toLowerCase().startsWith("image/")
    );
    if (!imageFiles.length) return;

    const nextValues = [];
    for (const file of imageFiles) {
      try {
        const dataUrl = await readFileAsDataUrl(file);
        const normalized = normalizeImageReference(dataUrl);
        if (normalized) {
          nextValues.push({ src: normalized, title: "" });
        }
      } catch (_error) {
        // Ignore invalid files and keep processing remaining images.
      }
    }

    if (nextValues.length) {
      mergeTaskDetailEditImageItems(nextValues);
    }
  };

  const addCreateTaskImagesFromFiles = async (files) => {
    const imageFiles = Array.from(files || []).filter(
      (file) => file instanceof File && String(file.type || "").toLowerCase().startsWith("image/")
    );
    if (!imageFiles.length) return;

    const nextValues = [];
    for (const file of imageFiles) {
      try {
        const dataUrl = await readFileAsDataUrl(file);
        const normalized = normalizeImageReference(dataUrl);
        if (normalized) {
          nextValues.push({ src: normalized, title: "" });
        }
      } catch (_error) {
        // Ignore invalid files and keep processing remaining images.
      }
    }

    if (nextValues.length) {
      mergeCreateTaskImageItems(nextValues);
    }
  };

  const googleDriveBrowserMaxSelectionCount = 20;

  const canUseSessionStorage = () => {
    try {
      return typeof window.sessionStorage !== "undefined";
    } catch (_error) {
      return false;
    }
  };

  const getGoogleDriveBrowserResumeCurrentPath = () =>
    `${window.location.pathname}${window.location.search || ""}`;

  const buildGoogleDriveBrowserResumeNextPath = () => {
    const nextUrl = new URL(window.location.href);
    nextUrl.searchParams.set(googleDriveBrowserResumeQueryParam, "1");
    nextUrl.hash = "";
    return `${nextUrl.pathname}${nextUrl.search}`;
  };

  const hasGoogleDriveBrowserResumeMarker = () => {
    const currentUrl = new URL(window.location.href);
    return currentUrl.searchParams.get(googleDriveBrowserResumeQueryParam) === "1";
  };

  const shouldResumeGoogleDriveBrowserOpen = () => {
    const currentUrl = new URL(window.location.href);
    return currentUrl.searchParams.get(googleDriveBrowserResumeOpenQueryParam) === "1";
  };

  const clearGoogleDriveBrowserResumeMarker = () => {
    const currentUrl = new URL(window.location.href);
    const hadResumeMarker = currentUrl.searchParams.has(googleDriveBrowserResumeQueryParam);
    const hadOpenMarker = currentUrl.searchParams.has(googleDriveBrowserResumeOpenQueryParam);
    if (!hadResumeMarker && !hadOpenMarker) {
      return;
    }

    currentUrl.searchParams.delete(googleDriveBrowserResumeQueryParam);
    currentUrl.searchParams.delete(googleDriveBrowserResumeOpenQueryParam);
    currentUrl.hash = "";
    if (window.history && typeof window.history.replaceState === "function") {
      window.history.replaceState(null, "", `${currentUrl.pathname}${currentUrl.search}`);
    }
  };

  const clearGoogleDriveBrowserResumeState = () => {
    if (!canUseSessionStorage()) return;
    try {
      window.sessionStorage.removeItem(googleDriveBrowserResumeStorageKey);
    } catch (_error) {
      // noop
    }
  };

  const writeGoogleDriveBrowserResumeState = (payload = {}) => {
    if (!canUseSessionStorage()) return false;
    try {
      window.sessionStorage.setItem(
        googleDriveBrowserResumeStorageKey,
        JSON.stringify({
          createdAt: Date.now(),
          returnPath: buildGoogleDriveBrowserResumeNextPath(),
          payload,
        })
      );
      return true;
    } catch (_error) {
      return false;
    }
  };

  const consumeGoogleDriveBrowserResumeState = () => {
    if (!canUseSessionStorage()) return null;

    let parsed = null;
    try {
      const raw = window.sessionStorage.getItem(googleDriveBrowserResumeStorageKey);
      parsed = raw ? JSON.parse(raw) : null;
    } catch (_error) {
      parsed = null;
    }
    clearGoogleDriveBrowserResumeState();

    if (!parsed || typeof parsed !== "object") {
      return null;
    }

    const createdAt = Math.max(0, Number(parsed.createdAt || 0));
    if (!createdAt || Date.now() - createdAt > googleDriveBrowserResumeMaxAgeMs) {
      return null;
    }

    const expectedPath = String(parsed.returnPath || "").trim();
    if (!expectedPath || expectedPath !== getGoogleDriveBrowserResumeCurrentPath()) {
      return null;
    }

    return parsed.payload && typeof parsed.payload === "object" ? parsed.payload : null;
  };

  const getCheckedFieldValues = (scope, selector) => {
    if (!scope || typeof scope.querySelectorAll !== "function") return [];
    return Array.from(scope.querySelectorAll(selector))
      .filter((field) => field instanceof HTMLInputElement && field.checked)
      .map((field) => String(field.value || "").trim())
      .filter(Boolean);
  };

  const applyCheckedFieldValues = (scope, selector, values = []) => {
    if (!scope || typeof scope.querySelectorAll !== "function") return;
    const allowed = new Set((Array.isArray(values) ? values : []).map((value) => String(value || "").trim()));
    scope.querySelectorAll(selector).forEach((field) => {
      if (!(field instanceof HTMLInputElement)) return;
      field.checked = allowed.has(String(field.value || "").trim());
    });
  };

  const captureCreateTaskDraftState = () => {
    if (!(createTaskModal instanceof HTMLElement) || createTaskModal.hidden) {
      return null;
    }

    syncCreateTaskDescriptionTextareaFromEditor();

    return {
      kind: "create",
      groupName:
        createTaskGroupInput instanceof HTMLSelectElement ? String(createTaskGroupInput.value || "") : "",
      title: createTaskTitleInput instanceof HTMLInputElement ? String(createTaskTitleInput.value || "") : "",
      titleTag: createTaskTitleTagInput instanceof HTMLInputElement
        ? String(createTaskTitleTagInput.value || "")
        : normalizeTaskTitleTagValue(createTaskCurrentTitleTag),
      titleTagColor: createTaskTitleTagColorInput instanceof HTMLInputElement
        ? String(createTaskTitleTagColorInput.value || "")
        : String(createTaskCurrentTitleTagColor || ""),
      assigneeIds: getCheckedFieldValues(createTaskForm, 'input[name="assigned_to[]"]'),
      status:
        createTaskForm instanceof HTMLFormElement
          ? String(createTaskForm.querySelector('select[name="status"]')?.value || "")
          : "",
      priority:
        createTaskForm instanceof HTMLFormElement
          ? String(createTaskForm.querySelector('select[name="priority"]')?.value || "")
          : "",
      dueDate:
        createTaskForm instanceof HTMLFormElement
          ? String(createTaskForm.querySelector('input[name="due_date"]')?.value || "")
          : "",
      description:
        createTaskDescription instanceof HTMLTextAreaElement ? String(createTaskDescription.value || "") : "",
      referenceLinks: parseReferenceUrlLines(createTaskReferenceLinks || []),
      referenceImages: parseReferenceImageMediaItems(createTaskImageItems || []),
      subtasks: parseTaskSubtaskList(createTaskSubtaskItems || [], 40, {
        enforceDependency: createTaskSubtasksDependencyEnabled,
      }),
      subtasksDependencyEnabled: Boolean(createTaskSubtasksDependencyEnabled),
      isMediaPage: Boolean(createTaskModal.classList.contains("is-task-media-page")),
    };
  };

  const restoreCreateTaskDraftState = (draft) => {
    if (!draft || draft.kind !== "create") {
      return false;
    }

    openCreateModal(String(draft.groupName || ""));

    if (createTaskTitleInput instanceof HTMLInputElement) {
      createTaskTitleInput.value = String(draft.title || "");
    }
    resetCreateTaskTitleTagPicker(
      String(draft.titleTag || ""),
      String(draft.titleTagColor || "")
    );

    if (createTaskForm instanceof HTMLFormElement) {
      const statusField = createTaskForm.querySelector('select[name="status"]');
      if (statusField instanceof HTMLSelectElement) {
        statusField.value = String(draft.status || statusField.value || "todo");
        syncInlineSelectPicker(statusField);
        syncSelectColor(statusField);
      }

      const priorityField = createTaskForm.querySelector('select[name="priority"]');
      if (priorityField instanceof HTMLSelectElement) {
        priorityField.value = String(draft.priority || priorityField.value || "medium");
        syncInlineSelectPicker(priorityField);
        syncSelectColor(priorityField);
      }

      const dueDateField = createTaskForm.querySelector('input[name="due_date"]');
      if (dueDateField instanceof HTMLInputElement) {
        dueDateField.value = String(draft.dueDate || "");
      }

      applyCheckedFieldValues(createTaskForm, 'input[name="assigned_to[]"]', draft.assigneeIds || []);
      createTaskForm
        .querySelectorAll(".assignee-picker")
        .forEach(updateAssigneePickerSummaryVisual);
    }

    if (createTaskDescription instanceof HTMLTextAreaElement) {
      createTaskDescription.value = String(draft.description || "");
      syncCreateTaskDescriptionEditorFromTextarea();
    }

    setCreateTaskReferenceLinks(draft.referenceLinks || []);
    setCreateTaskSubtasks(draft.subtasks || [], {
      dependencyEnabled: Boolean(draft.subtasksDependencyEnabled),
    });
    renderCreateTaskSubtasksEditList();
    setCreateTaskImageItems(draft.referenceImages || []);
    setCreateTaskMediaPage(Boolean(draft.isMediaPage));

    return true;
  };

  const captureTaskDetailDraftState = () => {
    if (
      !(taskDetailModal instanceof HTMLElement) ||
      taskDetailModal.hidden ||
      !taskDetailModal.classList.contains("is-editing")
    ) {
      return null;
    }

    const taskId = currentTaskDetailTaskId();
    if (!(taskId > 0)) {
      return null;
    }

    syncTaskDetailDescriptionTextareaFromEditor();

    return {
      kind: "task-detail",
      taskId,
      title: taskDetailEditTitle instanceof HTMLInputElement ? String(taskDetailEditTitle.value || "") : "",
      titleTag: taskDetailEditTitleTagInput instanceof HTMLInputElement
        ? String(taskDetailEditTitleTagInput.value || "")
        : normalizeTaskTitleTagValue(taskDetailEditCurrentTitleTag),
      titleTagColor: taskDetailEditTitleTagColorInput instanceof HTMLInputElement
        ? String(taskDetailEditTitleTagColorInput.value || "")
        : String(taskDetailEditCurrentTitleTagColor || ""),
      assigneeIds: getCheckedFieldValues(taskDetailEditAssigneesMenu, 'input[type="checkbox"]'),
      status: taskDetailEditStatus instanceof HTMLSelectElement ? String(taskDetailEditStatus.value || "") : "",
      priority: taskDetailEditPriority instanceof HTMLSelectElement ? String(taskDetailEditPriority.value || "") : "",
      groupName: taskDetailEditGroup instanceof HTMLSelectElement ? String(taskDetailEditGroup.value || "") : "",
      dueDate: taskDetailEditDueDate instanceof HTMLInputElement ? String(taskDetailEditDueDate.value || "") : "",
      description:
        taskDetailEditDescription instanceof HTMLTextAreaElement
          ? String(taskDetailEditDescription.value || "")
          : "",
      referenceLinks: parseReferenceUrlLines(taskDetailEditReferenceLinks || []),
      referenceImages: parseReferenceImageMediaItems(taskDetailEditImageItems || []),
      subtasks: parseTaskSubtaskList(taskDetailEditSubtaskItems || [], 40, {
        enforceDependency: taskDetailEditSubtasksDependencyEnabled,
      }),
      subtasksDependencyEnabled: Boolean(taskDetailEditSubtasksDependencyEnabled),
      isMediaPage: Boolean(taskDetailModal.classList.contains("is-task-media-page")),
    };
  };

  const restoreTaskDetailDraftState = async (draft) => {
    if (!draft || draft.kind !== "task-detail") {
      return false;
    }

    const taskId = Math.max(0, Number.parseInt(String(draft.taskId || "0"), 10) || 0);
    if (!(taskId > 0)) {
      return false;
    }

    const taskItem = document.getElementById(`task-${taskId}`);
    if (!(taskItem instanceof HTMLElement)) {
      return false;
    }

    const bindings = getTaskDetailBindings(taskItem);
    if (!bindings) {
      return false;
    }

    openTaskDetailModal(taskItem, {
      updateUrl: false,
      scrollIntoView: false,
    });
    await hydrateTaskDetailPayloadFromServer(bindings, { force: true }).catch(() => {});
    setTaskDetailEditMode(true);

    if (taskDetailEditTitle instanceof HTMLInputElement) {
      taskDetailEditTitle.value = String(draft.title || "");
    }
    resetTaskDetailTitleTagPicker(
      String(draft.titleTag || ""),
      String(draft.titleTagColor || "")
    );

    if (taskDetailEditStatus instanceof HTMLSelectElement) {
      taskDetailEditStatus.value = String(draft.status || taskDetailEditStatus.value || "todo");
      syncInlineSelectPicker(taskDetailEditStatus);
      syncSelectColor(taskDetailEditStatus);
    }
    if (taskDetailEditPriority instanceof HTMLSelectElement) {
      taskDetailEditPriority.value = String(draft.priority || taskDetailEditPriority.value || "medium");
      syncInlineSelectPicker(taskDetailEditPriority);
      syncSelectColor(taskDetailEditPriority);
    }
    if (taskDetailEditGroup instanceof HTMLSelectElement) {
      taskDetailEditGroup.value = String(draft.groupName || taskDetailEditGroup.value || "");
      syncInlineSelectPicker(taskDetailEditGroup);
    }
    if (taskDetailEditDueDate instanceof HTMLInputElement) {
      taskDetailEditDueDate.value = String(draft.dueDate || "");
    }
    if (taskDetailEditDescription instanceof HTMLTextAreaElement) {
      taskDetailEditDescription.value = String(draft.description || "");
      syncTaskDetailDescriptionEditorFromTextarea();
    }

    applyCheckedFieldValues(taskDetailEditAssigneesMenu, 'input[type="checkbox"]', draft.assigneeIds || []);
    if (taskDetailEditAssignees instanceof HTMLElement) {
      updateAssigneePickerSummaryVisual(taskDetailEditAssignees);
    }

    setTaskDetailEditReferenceLinks(draft.referenceLinks || []);
    setTaskDetailEditSubtasks(draft.subtasks || [], {
      dependencyEnabled: Boolean(draft.subtasksDependencyEnabled),
    });
    renderTaskDetailSubtasksEditList();
    setTaskDetailEditImageItems(draft.referenceImages || []);
    setTaskDetailMediaPage(Boolean(draft.isMediaPage));

    return true;
  };

  const captureGoogleDriveBrowserResumePayload = (targetName) => {
    const normalizedTarget = targetName === "task-detail" ? "task-detail" : "create";
    const draft =
      normalizedTarget === "task-detail"
        ? captureTaskDetailDraftState()
        : captureCreateTaskDraftState();

    if (!draft) {
      return null;
    }

    return {
      targetName: normalizedTarget,
      draft,
    };
  };

  const resumeGoogleDriveBrowserFlowAfterAuth = async () => {
    if (!hasGoogleDriveBrowserResumeMarker()) {
      return false;
    }

    const shouldOpenBrowser = shouldResumeGoogleDriveBrowserOpen();
    const resumePayload = consumeGoogleDriveBrowserResumeState();
    clearGoogleDriveBrowserResumeMarker();
    if (!resumePayload) {
      return false;
    }

    const targetName = resumePayload.targetName === "task-detail" ? "task-detail" : "create";
    const restored =
      targetName === "task-detail"
        ? await restoreTaskDetailDraftState(resumePayload.draft)
        : restoreCreateTaskDraftState(resumePayload.draft);

    if (!restored) {
      return false;
    }

    if (shouldOpenBrowser) {
      window.setTimeout(() => {
        void openGoogleDriveBrowserModal(targetName);
      }, 60);
    }

    return true;
  };

  const postGoogleDriveBrowserAction = async (action, payload = {}) => {
    const formData = new FormData();
    formData.append("action", String(action || "").trim());
    Object.entries(payload || {}).forEach(([key, value]) => {
      if (!key || value === undefined || value === null) return;
      formData.append(key, String(value));
    });

    const response = await fetch(window.location.pathname, {
      method: "POST",
      body: formData,
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      credentials: "same-origin",
    });

    let data = null;
    try {
      data = await response.json();
    } catch (_error) {
      data = null;
    }

    if (!response.ok || !data || data.ok !== true) {
      const message = (data && (data.error || data.message)) || "Nao foi possivel concluir a operacao.";
      throw new Error(message);
    }

    return data;
  };

  const getGoogleDriveBrowserSession = async (targetName) => {
    const csrfToken = getTaskTitleTagCsrfToken();
    if (!csrfToken) {
      throw new Error("Sessao expirada. Recarregue a pagina.");
    }

    const data = await postGoogleDriveBrowserAction("google_drive_browser_session", {
      csrf_token: csrfToken,
      next: buildGoogleDriveBrowserResumeNextPath(),
    });

    if (data.configured === false) {
      throw new Error("Configure GOOGLE_DRIVE_CLIENT_ID e GOOGLE_DRIVE_CLIENT_SECRET para usar o Drive.");
    }
    if (data.connected === false || data.reconnect_required === true) {
      const authUrl = String(data.auth_url || "").trim();
      if (authUrl) {
        const resumePayload = captureGoogleDriveBrowserResumePayload(targetName);
        if (resumePayload) {
          writeGoogleDriveBrowserResumeState(resumePayload);
        }
        window.location.href = authUrl;
        return null;
      }
      throw new Error("Conecte o Google Drive para navegar pelas midias.");
    }
    if (data.browser_ready === false) {
      throw new Error(String(data.message || "Google Drive ainda nao esta pronto para navegacao."));
    }

    return data;
  };

  const addGoogleDriveMediaItems = async (targetName, fileIds) => {
    const csrfToken = getTaskTitleTagCsrfToken();
    if (!csrfToken) {
      throw new Error("Sessao expirada. Recarregue a pagina.");
    }

    const data = await postGoogleDriveBrowserAction("google_drive_file_metadata", {
      csrf_token: csrfToken,
      file_ids: JSON.stringify(fileIds || []),
    });
    const mediaItems = parseReferenceImageMediaItems(data.media || []);
    if (!mediaItems.length) return;

    if (targetName === "task-detail") {
      mergeTaskDetailEditImageItems(mediaItems);
      return;
    }

    mergeCreateTaskImageItems(mediaItems);
  };

  const getGoogleDriveBrowserReturnTarget = () =>
    googleDriveBrowserTarget === "task-detail" ? taskDetailDriveAddButton : createTaskDriveAddButton;

  const setGoogleDriveBrowserStatus = (message, type = "info") => {
    if (!(googleDriveBrowserState instanceof HTMLElement)) return;
    const normalized = String(message || "").trim();
    googleDriveBrowserState.hidden = normalized === "";
    googleDriveBrowserState.textContent = normalized;
    googleDriveBrowserState.dataset.stateType = normalized === "" ? "" : type;
  };

  const formatGoogleDriveBrowserDate = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "";
    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return "";

    try {
      return new Intl.DateTimeFormat("pt-BR", {
        dateStyle: "short",
        timeStyle: "short",
      }).format(date);
    } catch (_error) {
      return raw;
    }
  };

  const createGoogleDriveBrowserIcon = (kind) => {
    const icon = document.createElement("span");
    icon.className = `google-drive-browser-item-icon is-${kind}`;
    icon.setAttribute("aria-hidden", "true");

    const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("viewBox", "0 0 20 20");
    svg.setAttribute("focusable", "false");

    if (kind === "folder") {
      const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
      path.setAttribute("d", "M2.4 5.8h5.3l1.4 1.7h8.5v7.4a2 2 0 0 1-2 2H4.4a2 2 0 0 1-2-2V7.8a2 2 0 0 1 2-2Z");
      svg.append(path);
    } else if (kind === "video") {
      const rect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
      rect.setAttribute("x", "2.6");
      rect.setAttribute("y", "4.1");
      rect.setAttribute("width", "14.8");
      rect.setAttribute("height", "11.8");
      rect.setAttribute("rx", "2.2");
      const play = document.createElementNS("http://www.w3.org/2000/svg", "path");
      play.setAttribute("d", "m8.3 7.2 5 2.8-5 2.8Z");
      svg.append(rect, play);
    } else {
      const rect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
      rect.setAttribute("x", "2.6");
      rect.setAttribute("y", "3.4");
      rect.setAttribute("width", "14.8");
      rect.setAttribute("height", "13.2");
      rect.setAttribute("rx", "2.2");
      const circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
      circle.setAttribute("cx", "7.4");
      circle.setAttribute("cy", "8");
      circle.setAttribute("r", "1.3");
      const mountain = document.createElementNS("http://www.w3.org/2000/svg", "path");
      mountain.setAttribute("d", "M4.5 14.2 8.5 10l2.6 2.3 2-1.7 2.5 3.6");
      svg.append(rect, circle, mountain);
    }

    icon.append(svg);
    return icon;
  };

  const getGoogleDriveBrowserItemKind = (item) => {
    if (item && item.is_folder) return "folder";
    const mimeType = String(item?.mime_type || "").toLowerCase();
    return mimeType.startsWith("video/") ? "video" : "image";
  };

  const syncGoogleDriveBrowserSelectionUi = () => {
    if (googleDriveBrowserSelectionCount instanceof HTMLElement) {
      const count = googleDriveBrowserSelectedItems.size;
      googleDriveBrowserSelectionCount.textContent =
        count === 0
          ? "Nenhuma midia selecionada"
          : count === 1
            ? "1 midia selecionada"
            : `${count} midias selecionadas`;
    }

    if (googleDriveBrowserAttachButton instanceof HTMLButtonElement) {
      googleDriveBrowserAttachButton.disabled =
        googleDriveBrowserLoading || googleDriveBrowserSelectedItems.size === 0;
    }
  };

  const resetGoogleDriveBrowserState = () => {
    googleDriveBrowserRoot = "";
    googleDriveBrowserFolderId = "";
    googleDriveBrowserItems = [];
    googleDriveBrowserBreadcrumbTrail = [];
    googleDriveBrowserNextPageToken = "";
    googleDriveBrowserSelectedItems = new Map();
    googleDriveBrowserLoading = false;
    setGoogleDriveBrowserStatus("");
    syncGoogleDriveBrowserSelectionUi();
  };

  const renderGoogleDriveBrowserBreadcrumbs = () => {
    if (!(googleDriveBrowserBreadcrumbs instanceof HTMLElement)) return;
    googleDriveBrowserBreadcrumbs.innerHTML = "";

    const crumbs = Array.isArray(googleDriveBrowserBreadcrumbTrail)
      ? googleDriveBrowserBreadcrumbTrail
      : [];

    crumbs.forEach((crumb, index) => {
      if (index > 0) {
        const separator = document.createElement("span");
        separator.className = "google-drive-browser-breadcrumb-separator";
        separator.textContent = "/";
        googleDriveBrowserBreadcrumbs.append(separator);
      }

      const isLast = index === crumbs.length - 1;
      if (isLast) {
        const label = document.createElement("span");
        label.className = "google-drive-browser-breadcrumb-current";
        label.textContent = String(crumb?.label || "Pasta");
        googleDriveBrowserBreadcrumbs.append(label);
        return;
      }

      const button = document.createElement("button");
      button.type = "button";
      button.className = "google-drive-browser-breadcrumb";
      button.dataset.googleDriveBrowserCrumbRoot = index === 0 ? "1" : "0";
      button.dataset.googleDriveBrowserCrumbFolderId = String(crumb?.id || "");
      button.textContent = String(crumb?.label || "Pasta");
      googleDriveBrowserBreadcrumbs.append(button);
    });
  };

  const renderGoogleDriveBrowserItems = () => {
    if (!(googleDriveBrowserList instanceof HTMLElement)) return;
    googleDriveBrowserList.innerHTML = "";

    if (!googleDriveBrowserItems.length) {
      setGoogleDriveBrowserStatus("Nenhuma pasta ou midia disponivel neste nivel.");
    } else if (!googleDriveBrowserLoading) {
      setGoogleDriveBrowserStatus("");
    }

    googleDriveBrowserItems.forEach((item) => {
      const row = document.createElement("button");
      row.type = "button";
      row.className = "google-drive-browser-item";
      row.dataset.googleDriveBrowserItemId = String(item?.id || "");
      row.dataset.googleDriveBrowserItemAction = item?.is_folder ? "open" : "toggle";

      const isSelected = googleDriveBrowserSelectedItems.has(String(item?.id || ""));
      if (isSelected) {
        row.classList.add("is-selected");
      }
      if (item?.is_folder) {
        row.classList.add("is-folder");
        row.setAttribute("aria-label", `Abrir pasta ${String(item?.name || "Pasta")}`);
      } else {
        row.classList.add("is-file");
        row.setAttribute("aria-pressed", isSelected ? "true" : "false");
        row.setAttribute("aria-label", `${isSelected ? "Remover" : "Selecionar"} ${String(item?.name || "arquivo")}`);
      }

      const nameColumn = document.createElement("span");
      nameColumn.className = "google-drive-browser-item-name";
      nameColumn.append(createGoogleDriveBrowserIcon(getGoogleDriveBrowserItemKind(item)));

      const copy = document.createElement("span");
      copy.className = "google-drive-browser-item-copy";
      const title = document.createElement("strong");
      title.textContent = String(item?.name || "Arquivo");
      const meta = document.createElement("span");
      meta.className = "google-drive-browser-item-meta";
      meta.textContent = item?.is_folder
        ? "Pasta"
        : getGoogleDriveBrowserItemKind(item) === "video"
          ? "Video"
          : "Imagem";
      if (isSelected && !item?.is_folder) {
        meta.textContent += " selecionada";
      } else if (item?.shared) {
        meta.textContent += " compartilhado";
      }
      copy.append(title, meta);
      nameColumn.append(copy);

      const ownerColumn = document.createElement("span");
      ownerColumn.className = "google-drive-browser-item-owner";
      ownerColumn.textContent = String(item?.owner || "");

      const modifiedColumn = document.createElement("span");
      modifiedColumn.className = "google-drive-browser-item-modified";
      modifiedColumn.textContent = formatGoogleDriveBrowserDate(item?.modified_at);

      row.append(nameColumn, ownerColumn, modifiedColumn);
      googleDriveBrowserList.append(row);
    });

    if (googleDriveBrowserMoreWrap instanceof HTMLElement) {
      googleDriveBrowserMoreWrap.hidden = !googleDriveBrowserNextPageToken;
    }
    syncGoogleDriveBrowserSelectionUi();
  };

  const showGoogleDriveBrowserRoots = () => {
    if (googleDriveBrowserRoots instanceof HTMLElement) {
      googleDriveBrowserRoots.hidden = false;
    }
    if (googleDriveBrowserExplorer instanceof HTMLElement) {
      googleDriveBrowserExplorer.hidden = true;
    }
    setGoogleDriveBrowserStatus("");
  };

  const showGoogleDriveBrowserExplorer = () => {
    if (googleDriveBrowserRoots instanceof HTMLElement) {
      googleDriveBrowserRoots.hidden = true;
    }
    if (googleDriveBrowserExplorer instanceof HTMLElement) {
      googleDriveBrowserExplorer.hidden = false;
    }
  };

  const loadGoogleDriveBrowserLocation = async (
    root,
    folderId = "",
    { append = false, pageToken = "" } = {}
  ) => {
    if (!root) return;
    const csrfToken = getTaskTitleTagCsrfToken();
    if (!csrfToken) {
      throw new Error("Sessao expirada. Recarregue a pagina.");
    }

    googleDriveBrowserLoading = true;
    setGoogleDriveBrowserStatus("Carregando Google Drive...");
    syncGoogleDriveBrowserSelectionUi();

    try {
      const data = await postGoogleDriveBrowserAction("google_drive_browser_list", {
        csrf_token: csrfToken,
        root,
        folder_id: folderId,
        page_token: pageToken,
      });

      googleDriveBrowserRoot = String(data.root || root).trim() || "my_drive";
      googleDriveBrowserFolderId = String(data.folder_id || "").trim();
      googleDriveBrowserBreadcrumbTrail = Array.isArray(data.breadcrumbs) ? data.breadcrumbs : [];
      googleDriveBrowserNextPageToken = String(data.next_page_token || "").trim();

      const incomingItems = Array.isArray(data.items) ? data.items : [];
      if (append) {
        const deduped = new Map(
          [...googleDriveBrowserItems, ...incomingItems].map((item) => [String(item?.id || ""), item])
        );
        googleDriveBrowserItems = Array.from(deduped.values()).filter((item) => item && item.id);
      } else {
        googleDriveBrowserItems = incomingItems;
      }

      renderGoogleDriveBrowserBreadcrumbs();
      renderGoogleDriveBrowserItems();
      showGoogleDriveBrowserExplorer();
    } catch (error) {
      setGoogleDriveBrowserStatus(
        error instanceof Error ? error.message : "Falha ao carregar Google Drive.",
        "error"
      );
      throw error;
    } finally {
      googleDriveBrowserLoading = false;
      if (googleDriveBrowserMoreButton instanceof HTMLButtonElement) {
        googleDriveBrowserMoreButton.disabled = false;
        googleDriveBrowserMoreButton.classList.remove("is-loading");
      }
      syncGoogleDriveBrowserSelectionUi();
    }
  };

  const toggleGoogleDriveBrowserSelection = (itemId) => {
    const normalizedId = normalizeGoogleDriveFileId(itemId);
    if (!normalizedId) return;

    if (googleDriveBrowserSelectedItems.has(normalizedId)) {
      googleDriveBrowserSelectedItems.delete(normalizedId);
      renderGoogleDriveBrowserItems();
      return;
    }

    if (googleDriveBrowserSelectedItems.size >= googleDriveBrowserMaxSelectionCount) {
      showClientFlash("error", `Selecione no maximo ${googleDriveBrowserMaxSelectionCount} midias por vez.`);
      return;
    }

    const selectedItem = googleDriveBrowserItems.find((item) => String(item?.id || "") === normalizedId);
    if (!selectedItem || selectedItem.is_folder) {
      return;
    }

    googleDriveBrowserSelectedItems.set(normalizedId, selectedItem);
    renderGoogleDriveBrowserItems();
  };

  const closeGoogleDriveBrowserModal = () => {
    if (!(googleDriveBrowserModal instanceof HTMLElement)) return;
    googleDriveBrowserModal.hidden = true;
    resetGoogleDriveBrowserState();
    showGoogleDriveBrowserRoots();
    syncBodyModalLock();
    const returnTarget = getGoogleDriveBrowserReturnTarget();
    if (returnTarget instanceof HTMLElement) {
      window.setTimeout(() => returnTarget.focus(), 20);
    }
  };

  const openGoogleDriveBrowserModal = async (targetName) => {
    try {
      const session = await getGoogleDriveBrowserSession(targetName);
      if (!session) return;

      googleDriveBrowserTarget = targetName;
      resetGoogleDriveBrowserState();
      showGoogleDriveBrowserRoots();

      if (!(googleDriveBrowserModal instanceof HTMLElement)) {
        throw new Error("Modal do Google Drive indisponivel.");
      }

      googleDriveBrowserModal.hidden = false;
      syncBodyModalLock();

      const firstRootButton = googleDriveBrowserModal.querySelector("[data-google-drive-browser-root]");
      if (firstRootButton instanceof HTMLElement) {
        window.setTimeout(() => firstRootButton.focus(), 20);
      }
    } catch (error) {
      showClientFlash("error", error instanceof Error ? error.message : "Falha ao abrir Google Drive.");
    }
  };

  const attachGoogleDriveBrowserSelection = async () => {
    if (googleDriveBrowserLoading || googleDriveBrowserSelectedItems.size === 0) return;
    const targetName = googleDriveBrowserTarget;
    const fileIds = Array.from(googleDriveBrowserSelectedItems.keys());

    if (googleDriveBrowserAttachButton instanceof HTMLButtonElement) {
      googleDriveBrowserAttachButton.disabled = true;
      googleDriveBrowserAttachButton.classList.add("is-loading");
      googleDriveBrowserAttachButton.textContent = "Adicionando";
    }

    try {
      await addGoogleDriveMediaItems(targetName, fileIds);
      closeGoogleDriveBrowserModal();
    } finally {
      if (googleDriveBrowserAttachButton instanceof HTMLButtonElement) {
        googleDriveBrowserAttachButton.disabled = false;
        googleDriveBrowserAttachButton.classList.remove("is-loading");
        googleDriveBrowserAttachButton.textContent = "Adicionar selecionadas";
      }
    }
  };

  if (googleDriveBrowserModal instanceof HTMLElement) {
    googleDriveBrowserModal.addEventListener("click", (event) => {
      const target = getEventTargetElement(event);
      if (!(target instanceof HTMLElement)) return;
      if (googleDriveBrowserLoading) return;

      const rootTrigger = target.closest("[data-google-drive-browser-root]");
      if (rootTrigger instanceof HTMLElement) {
        const nextRoot = String(rootTrigger.dataset.googleDriveBrowserRoot || "").trim();
        if (!nextRoot) return;
        void loadGoogleDriveBrowserLocation(nextRoot).catch((error) => {
          showClientFlash("error", error instanceof Error ? error.message : "Falha ao carregar Google Drive.");
        });
        return;
      }

      const crumbTrigger = target.closest("[data-google-drive-browser-crumb-root], [data-google-drive-browser-crumb-folder-id]");
      if (crumbTrigger instanceof HTMLElement) {
        const isRootCrumb = crumbTrigger.dataset.googleDriveBrowserCrumbRoot === "1";
        const nextFolderId = isRootCrumb ? "" : String(crumbTrigger.dataset.googleDriveBrowserCrumbFolderId || "");
        void loadGoogleDriveBrowserLocation(googleDriveBrowserRoot, nextFolderId).catch((error) => {
          showClientFlash("error", error instanceof Error ? error.message : "Falha ao abrir a pasta.");
        });
        return;
      }

      const rowTrigger = target.closest("[data-google-drive-browser-item-id]");
      if (rowTrigger instanceof HTMLElement) {
        const action = String(rowTrigger.dataset.googleDriveBrowserItemAction || "").trim();
        const itemId = String(rowTrigger.dataset.googleDriveBrowserItemId || "").trim();
        if (action === "open") {
          void loadGoogleDriveBrowserLocation(googleDriveBrowserRoot, itemId).catch((error) => {
            showClientFlash("error", error instanceof Error ? error.message : "Falha ao abrir a pasta.");
          });
          return;
        }
        toggleGoogleDriveBrowserSelection(itemId);
        return;
      }
    });
  }

  if (googleDriveBrowserBackRootButton instanceof HTMLButtonElement) {
    googleDriveBrowserBackRootButton.addEventListener("click", () => {
      googleDriveBrowserRoot = "";
      googleDriveBrowserFolderId = "";
      googleDriveBrowserItems = [];
      googleDriveBrowserBreadcrumbTrail = [];
      googleDriveBrowserNextPageToken = "";
      renderGoogleDriveBrowserBreadcrumbs();
      renderGoogleDriveBrowserItems();
      showGoogleDriveBrowserRoots();
    });
  }

  if (googleDriveBrowserMoreButton instanceof HTMLButtonElement) {
    googleDriveBrowserMoreButton.addEventListener("click", () => {
      if (!googleDriveBrowserRoot || !googleDriveBrowserNextPageToken || googleDriveBrowserLoading) {
        return;
      }

      googleDriveBrowserMoreButton.disabled = true;
      googleDriveBrowserMoreButton.classList.add("is-loading");
      void loadGoogleDriveBrowserLocation(googleDriveBrowserRoot, googleDriveBrowserFolderId, {
        append: true,
        pageToken: googleDriveBrowserNextPageToken,
      }).catch((error) => {
        showClientFlash("error", error instanceof Error ? error.message : "Falha ao carregar mais arquivos.");
      });
    });
  }

  if (googleDriveBrowserAttachButton instanceof HTMLButtonElement) {
    googleDriveBrowserAttachButton.addEventListener("click", () => {
      void attachGoogleDriveBrowserSelection().catch((error) => {
        showClientFlash("error", error instanceof Error ? error.message : "Falha ao adicionar arquivos do Drive.");
      });
    });
  }

  if (createTaskOpenMediaButton instanceof HTMLButtonElement) {
    createTaskOpenMediaButton.addEventListener("click", () => {
      setCreateTaskMediaPage(true);
    });
  }
  if (createTaskBackMainButton instanceof HTMLButtonElement) {
    createTaskBackMainButton.addEventListener("click", () => {
      setCreateTaskMediaPage(false);
    });
  }
  if (taskDetailOpenMediaButton instanceof HTMLButtonElement) {
    taskDetailOpenMediaButton.addEventListener("click", () => {
      setTaskDetailMediaPage(true);
    });
  }
  if (taskDetailBackMainButton instanceof HTMLButtonElement) {
    taskDetailBackMainButton.addEventListener("click", () => {
      setTaskDetailMediaPage(false);
    });
  }

  if (taskDetailImageAddButton instanceof HTMLButtonElement && taskDetailImageInput instanceof HTMLInputElement) {
    taskDetailImageAddButton.addEventListener("click", () => {
      taskDetailImagePickerExpanded = true;
      syncTaskDetailImagePickerLayout();
      taskDetailImageInput.click();
    });
  }

  if (createTaskImageAddButton instanceof HTMLButtonElement && createTaskImageInput instanceof HTMLInputElement) {
    createTaskImageAddButton.addEventListener("click", () => {
      createTaskImagePickerExpanded = true;
      syncCreateTaskImagePickerLayout();
      createTaskImageInput.click();
    });
  }

  if (taskDetailDriveAddButton instanceof HTMLButtonElement) {
    taskDetailDriveAddButton.addEventListener("click", () => {
      taskDetailImagePickerExpanded = true;
      syncTaskDetailImagePickerLayout();
      void openGoogleDriveBrowserModal("task-detail");
    });
  }

  if (createTaskDriveAddButton instanceof HTMLButtonElement) {
    createTaskDriveAddButton.addEventListener("click", () => {
      createTaskImagePickerExpanded = true;
      syncCreateTaskImagePickerLayout();
      void openGoogleDriveBrowserModal("create");
    });
  }

  if (taskDetailImageInput instanceof HTMLInputElement) {
    taskDetailImageInput.addEventListener("change", () => {
      const files = Array.from(taskDetailImageInput.files || []);
      taskDetailImageInput.value = "";
      void addTaskDetailImagesFromFiles(files);
    });
  }

  if (createTaskImageInput instanceof HTMLInputElement) {
    createTaskImageInput.addEventListener("change", () => {
      const files = Array.from(createTaskImageInput.files || []);
      createTaskImageInput.value = "";
      void addCreateTaskImagesFromFiles(files);
    });
  }

  if (taskDetailImagePicker instanceof HTMLElement) {
    taskDetailImagePicker.addEventListener("paste", (event) => {
      const clipboardItems = Array.from(event.clipboardData?.items || []);
      const files = clipboardItems
        .map((item) =>
          item.kind === "file" && String(item.type || "").toLowerCase().startsWith("image/")
            ? item.getAsFile()
            : null
        )
        .filter((file) => file instanceof File);

      if (!files.length) return;
      event.preventDefault();
      event.stopPropagation();
      taskDetailImagePickerExpanded = true;
      syncTaskDetailImagePickerLayout();
      void addTaskDetailImagesFromFiles(files);
    });
  }

  if (createTaskImagePicker instanceof HTMLElement) {
    createTaskImagePicker.addEventListener("paste", (event) => {
      const clipboardItems = Array.from(event.clipboardData?.items || []);
      const files = clipboardItems
        .map((item) =>
          item.kind === "file" && String(item.type || "").toLowerCase().startsWith("image/")
            ? item.getAsFile()
            : null
        )
        .filter((file) => file instanceof File);

      if (!files.length) return;
      event.preventDefault();
      event.stopPropagation();
      createTaskImagePickerExpanded = true;
      syncCreateTaskImagePickerLayout();
      void addCreateTaskImagesFromFiles(files);
    });
  }

  document.addEventListener("paste", (event) => {
    if (event.defaultPrevented) return;

    const files = Array.from(event.clipboardData?.items || [])
      .map((item) =>
        item.kind === "file" && String(item.type || "").toLowerCase().startsWith("image/")
          ? item.getAsFile()
          : null
      )
      .filter((file) => file instanceof File);

    if (!files.length) return;

    if (
      createTaskModal instanceof HTMLElement &&
      !createTaskModal.hidden &&
      createTaskModal.classList.contains("is-task-media-page")
    ) {
      event.preventDefault();
      createTaskImagePickerExpanded = true;
      syncCreateTaskImagePickerLayout();
      void addCreateTaskImagesFromFiles(files);
      return;
    }

    if (
      taskDetailModal instanceof HTMLElement &&
      !taskDetailModal.hidden &&
      taskDetailModal.classList.contains("is-task-media-page")
    ) {
      event.preventDefault();
      taskDetailImagePickerExpanded = true;
      syncTaskDetailImagePickerLayout();
      void addTaskDetailImagesFromFiles(files);
    }
  });

  document.addEventListener("input", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLInputElement)) return;

    if (target.matches("[data-create-task-image-title]")) {
      const index = Number.parseInt(target.dataset.createTaskImageTitle || "-1", 10);
      if (!Number.isFinite(index) || index < 0 || index >= createTaskImageItems.length) return;
      const current = normalizeReferenceImageMediaItem(createTaskImageItems[index]);
      if (!current) return;
      createTaskImageItems[index] = {
        ...current,
        title: normalizeReferenceImageTitle(target.value),
      };
      syncCreateTaskImageHiddenField();
      return;
    }

    if (target.matches("[data-task-detail-image-title]")) {
      const index = Number.parseInt(target.dataset.taskDetailImageTitle || "-1", 10);
      if (!Number.isFinite(index) || index < 0 || index >= taskDetailEditImageItems.length) return;
      const current = normalizeReferenceImageMediaItem(taskDetailEditImageItems[index]);
      if (!current) return;
      taskDetailEditImageItems[index] = {
        ...current,
        title: normalizeReferenceImageTitle(target.value),
      };
      syncTaskDetailImageHiddenField();
    }
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;
    const previewButton = target.closest("[data-task-detail-image-preview]");
    if (!(previewButton instanceof HTMLButtonElement)) return;

    event.preventDefault();
    const index = Number.parseInt(previewButton.getAttribute("data-task-detail-image-preview") || "-1", 10);
    if (!Number.isFinite(index) || index < 0 || index >= taskDetailEditImageItems.length) return;

    openTaskImagePreview({
      items: taskDetailEditImageItems,
      index,
    });
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;
    const removeButton = target.closest("[data-task-detail-image-remove]");
    if (!(removeButton instanceof HTMLButtonElement)) return;

    event.preventDefault();
    const index = Number.parseInt(removeButton.dataset.taskDetailImageRemove || "-1", 10);
    if (!Number.isFinite(index) || index < 0) return;
    if (index >= taskDetailEditImageItems.length) return;

    taskDetailEditImageItems = taskDetailEditImageItems.filter((_item, itemIndex) => itemIndex !== index);
    syncTaskDetailImageHiddenField();
    renderTaskDetailImageList();
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;
    const previewButton = target.closest("[data-create-task-image-preview]");
    if (!(previewButton instanceof HTMLButtonElement)) return;

    event.preventDefault();
    const index = Number.parseInt(previewButton.getAttribute("data-create-task-image-preview") || "-1", 10);
    if (!Number.isFinite(index) || index < 0 || index >= createTaskImageItems.length) return;

    openTaskImagePreview({
      items: createTaskImageItems,
      index,
    });
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;
    const removeButton = target.closest("[data-create-task-image-remove]");
    if (!(removeButton instanceof HTMLButtonElement)) return;

    event.preventDefault();
    const index = Number.parseInt(removeButton.dataset.createTaskImageRemove || "-1", 10);
    if (!Number.isFinite(index) || index < 0) return;
    if (index >= createTaskImageItems.length) return;

    createTaskImageItems = createTaskImageItems.filter((_item, itemIndex) => itemIndex !== index);
    syncCreateTaskImageHiddenField();
    renderCreateTaskImageList();
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    collapseEmptyImagePickersIfOutside(target);
  });

  const addTaskDetailSubtaskFromInput = () => {
    if (!(taskDetailEditSubtaskInput instanceof HTMLInputElement)) return;
    const title = (taskDetailEditSubtaskInput.value || "").trim();
    if (!title) return;
    taskDetailEditSubtaskItems = parseTaskSubtaskList([
      ...taskDetailEditSubtaskItems,
      { title, done: false },
    ], 40, {
      enforceDependency: taskDetailEditSubtasksDependencyEnabled,
    });
    taskDetailEditSubtaskInput.value = "";
    renderTaskDetailSubtasksEditList();
    closeInlineAddForm(taskDetailEditSubtaskAddForm, taskDetailEditSubtaskInput);
  };

  const addCreateTaskSubtaskFromInput = () => {
    if (!(createTaskSubtaskInput instanceof HTMLInputElement)) return;
    const title = (createTaskSubtaskInput.value || "").trim();
    if (!title) return;
    createTaskSubtaskItems = parseTaskSubtaskList([
      ...createTaskSubtaskItems,
      { title, done: false },
    ], 40, {
      enforceDependency: createTaskSubtasksDependencyEnabled,
    });
    createTaskSubtaskInput.value = "";
    renderCreateTaskSubtasksEditList();
    closeInlineAddForm(createTaskSubtaskAddForm, createTaskSubtaskInput);
  };

  const addCreateTaskReferenceLinkFromInput = () => {
    const added = addReferenceLinkFromInput({
      input: createTaskLinkInput,
      currentLinks: createTaskReferenceLinks,
      setLinks: setCreateTaskReferenceLinks,
    });
    if (added) {
      closeInlineAddForm(createTaskLinkAddForm, createTaskLinkInput);
    }
  };

  const addTaskDetailReferenceLinkFromInput = () => {
    const added = addReferenceLinkFromInput({
      input: taskDetailEditLinkInput,
      currentLinks: taskDetailEditReferenceLinks,
      setLinks: setTaskDetailEditReferenceLinks,
    });
    if (added) {
      closeInlineAddForm(taskDetailEditLinkAddForm, taskDetailEditLinkInput);
    }
  };

  if (createTaskLinkToggleAddButton instanceof HTMLButtonElement) {
    createTaskLinkToggleAddButton.addEventListener("click", () => {
      openInlineAddForm(createTaskLinkAddForm, createTaskLinkInput);
    });
  }
  if (taskDetailEditLinkToggleAddButton instanceof HTMLButtonElement) {
    taskDetailEditLinkToggleAddButton.addEventListener("click", () => {
      openInlineAddForm(taskDetailEditLinkAddForm, taskDetailEditLinkInput);
    });
  }
  if (createTaskLinkConfirmButton instanceof HTMLButtonElement) {
    createTaskLinkConfirmButton.addEventListener("click", addCreateTaskReferenceLinkFromInput);
  }
  if (taskDetailEditLinkConfirmButton instanceof HTMLButtonElement) {
    taskDetailEditLinkConfirmButton.addEventListener("click", addTaskDetailReferenceLinkFromInput);
  }
  if (createTaskLinkCancelButton instanceof HTMLButtonElement) {
    createTaskLinkCancelButton.addEventListener("click", () => {
      closeInlineAddForm(createTaskLinkAddForm, createTaskLinkInput);
    });
  }
  if (taskDetailEditLinkCancelButton instanceof HTMLButtonElement) {
    taskDetailEditLinkCancelButton.addEventListener("click", () => {
      closeInlineAddForm(taskDetailEditLinkAddForm, taskDetailEditLinkInput);
    });
  }
  if (createTaskSubtaskToggleAddButton instanceof HTMLButtonElement) {
    createTaskSubtaskToggleAddButton.addEventListener("click", () => {
      openInlineAddForm(createTaskSubtaskAddForm, createTaskSubtaskInput);
    });
  }
  if (taskDetailEditSubtaskToggleAddButton instanceof HTMLButtonElement) {
    taskDetailEditSubtaskToggleAddButton.addEventListener("click", () => {
      openInlineAddForm(taskDetailEditSubtaskAddForm, taskDetailEditSubtaskInput);
    });
  }
  if (createTaskSubtaskCancelButton instanceof HTMLButtonElement) {
    createTaskSubtaskCancelButton.addEventListener("click", () => {
      closeInlineAddForm(createTaskSubtaskAddForm, createTaskSubtaskInput);
    });
  }
  if (taskDetailEditSubtaskCancelButton instanceof HTMLButtonElement) {
    taskDetailEditSubtaskCancelButton.addEventListener("click", () => {
      closeInlineAddForm(taskDetailEditSubtaskAddForm, taskDetailEditSubtaskInput);
    });
  }

  if (taskDetailEditSubtaskAddButton instanceof HTMLButtonElement) {
    taskDetailEditSubtaskAddButton.addEventListener("click", addTaskDetailSubtaskFromInput);
  }
  if (createTaskSubtaskAddButton instanceof HTMLButtonElement) {
    createTaskSubtaskAddButton.addEventListener("click", addCreateTaskSubtaskFromInput);
  }

  if (taskDetailEditSubtasksDependencyToggle instanceof HTMLInputElement) {
    taskDetailEditSubtasksDependencyToggle.addEventListener("change", () => {
      setTaskDetailEditSubtasksDependencyEnabled(taskDetailEditSubtasksDependencyToggle.checked);
      taskDetailEditSubtaskItems = parseTaskSubtaskList(taskDetailEditSubtaskItems, 40, {
        enforceDependency: taskDetailEditSubtasksDependencyEnabled,
      });
      renderTaskDetailSubtasksEditList();
    });
  }

  if (createTaskSubtasksDependencyToggle instanceof HTMLInputElement) {
    createTaskSubtasksDependencyToggle.addEventListener("change", () => {
      setCreateTaskSubtasksDependencyEnabled(createTaskSubtasksDependencyToggle.checked);
      createTaskSubtaskItems = parseTaskSubtaskList(createTaskSubtaskItems, 40, {
        enforceDependency: createTaskSubtasksDependencyEnabled,
      });
      renderCreateTaskSubtasksEditList();
    });
  }

  if (taskDetailEditSubtaskInput instanceof HTMLInputElement) {
    taskDetailEditSubtaskInput.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        event.preventDefault();
        closeInlineAddForm(taskDetailEditSubtaskAddForm, taskDetailEditSubtaskInput);
        return;
      }
      if (event.key !== "Enter") return;
      event.preventDefault();
      addTaskDetailSubtaskFromInput();
    });
  }
  if (createTaskSubtaskInput instanceof HTMLInputElement) {
    createTaskSubtaskInput.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        event.preventDefault();
        closeInlineAddForm(createTaskSubtaskAddForm, createTaskSubtaskInput);
        return;
      }
      if (event.key !== "Enter") return;
      event.preventDefault();
      addCreateTaskSubtaskFromInput();
    });
  }
  if (createTaskLinkInput instanceof HTMLInputElement) {
    createTaskLinkInput.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        event.preventDefault();
        closeInlineAddForm(createTaskLinkAddForm, createTaskLinkInput);
        return;
      }
      if (event.key !== "Enter") return;
      event.preventDefault();
      addCreateTaskReferenceLinkFromInput();
    });
  }
  if (taskDetailEditLinkInput instanceof HTMLInputElement) {
    taskDetailEditLinkInput.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        event.preventDefault();
        closeInlineAddForm(taskDetailEditLinkAddForm, taskDetailEditLinkInput);
        return;
      }
      if (event.key !== "Enter") return;
      event.preventDefault();
      addTaskDetailReferenceLinkFromInput();
    });
  }

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;

    const removeDetailLink = target.closest("[data-task-detail-edit-link-remove]");
    if (removeDetailLink instanceof HTMLButtonElement) {
      const index = Number.parseInt(removeDetailLink.dataset.taskDetailEditLinkRemove || "-1", 10);
      if (!Number.isFinite(index) || index < 0) return;
      setTaskDetailEditReferenceLinks(
        taskDetailEditReferenceLinks.filter((_item, itemIndex) => itemIndex !== index)
      );
      return;
    }

    const removeCreateLink = target.closest("[data-create-task-link-remove]");
    if (removeCreateLink instanceof HTMLButtonElement) {
      const index = Number.parseInt(removeCreateLink.dataset.createTaskLinkRemove || "-1", 10);
      if (!Number.isFinite(index) || index < 0) return;
      setCreateTaskReferenceLinks(
        createTaskReferenceLinks.filter((_item, itemIndex) => itemIndex !== index)
      );
      return;
    }

    const removeDetailSubtask = target.closest("[data-task-detail-edit-subtask-remove]");
    if (removeDetailSubtask instanceof HTMLButtonElement) {
      const index = Number.parseInt(removeDetailSubtask.dataset.taskDetailEditSubtaskRemove || "-1", 10);
      if (!Number.isFinite(index) || index < 0) return;
      taskDetailEditSubtaskItems = taskDetailEditSubtaskItems.filter(
        (_item, itemIndex) => itemIndex !== index
      );
      taskDetailEditSubtaskItems = parseTaskSubtaskList(taskDetailEditSubtaskItems, 40, {
        enforceDependency: taskDetailEditSubtasksDependencyEnabled,
      });
      renderTaskDetailSubtasksEditList();
      return;
    }

    const removeCreateSubtask = target.closest("[data-create-task-subtask-remove]");
    if (removeCreateSubtask instanceof HTMLButtonElement) {
      const index = Number.parseInt(removeCreateSubtask.dataset.createTaskSubtaskRemove || "-1", 10);
      if (!Number.isFinite(index) || index < 0) return;
      createTaskSubtaskItems = createTaskSubtaskItems.filter(
        (_item, itemIndex) => itemIndex !== index
      );
      createTaskSubtaskItems = parseTaskSubtaskList(createTaskSubtaskItems, 40, {
        enforceDependency: createTaskSubtasksDependencyEnabled,
      });
      renderCreateTaskSubtasksEditList();
      return;
    }
  });

  document.addEventListener("input", (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const detailTitleInput = target.closest("[data-task-detail-edit-subtask-title]");
    if (detailTitleInput instanceof HTMLInputElement) {
      const index = Number.parseInt(detailTitleInput.dataset.taskDetailEditSubtaskTitle || "-1", 10);
      if (!Number.isFinite(index) || index < 0 || index >= taskDetailEditSubtaskItems.length) return;
      taskDetailEditSubtaskItems[index].title = String(detailTitleInput.value || "").slice(0, 120);
      if (taskDetailEditSubtasksField instanceof HTMLTextAreaElement) {
        writeTaskSubtasksField(taskDetailEditSubtasksField, taskDetailEditSubtaskItems, {
          enforceDependency: taskDetailEditSubtasksDependencyEnabled,
        });
      }
      return;
    }

    const createTitleInput = target.closest("[data-create-task-subtask-title]");
    if (createTitleInput instanceof HTMLInputElement) {
      const index = Number.parseInt(createTitleInput.dataset.createTaskSubtaskTitle || "-1", 10);
      if (!Number.isFinite(index) || index < 0 || index >= createTaskSubtaskItems.length) return;
      createTaskSubtaskItems[index].title = String(createTitleInput.value || "").slice(0, 120);
      if (createTaskSubtasksField instanceof HTMLTextAreaElement) {
        writeTaskSubtasksField(createTaskSubtasksField, createTaskSubtaskItems, {
          enforceDependency: createTaskSubtasksDependencyEnabled,
        });
      }
    }
  });

  document.addEventListener("change", (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const detailCheck = target.closest("[data-task-detail-edit-subtask-done]");
    if (detailCheck instanceof HTMLInputElement) {
      const index = Number.parseInt(detailCheck.dataset.taskDetailEditSubtaskDone || "-1", 10);
      if (!Number.isFinite(index) || index < 0 || index >= taskDetailEditSubtaskItems.length) return;
      taskDetailEditSubtaskItems[index].done = detailCheck.checked;
      taskDetailEditSubtaskItems = parseTaskSubtaskList(taskDetailEditSubtaskItems, 40, {
        enforceDependency: taskDetailEditSubtasksDependencyEnabled,
      });
      renderTaskDetailSubtasksEditList();
      return;
    }

    const createCheck = target.closest("[data-create-task-subtask-done]");
    if (createCheck instanceof HTMLInputElement) {
      const index = Number.parseInt(createCheck.dataset.createTaskSubtaskDone || "-1", 10);
      if (!Number.isFinite(index) || index < 0 || index >= createTaskSubtaskItems.length) return;
      createTaskSubtaskItems[index].done = createCheck.checked;
      createTaskSubtaskItems = parseTaskSubtaskList(createTaskSubtaskItems, 40, {
        enforceDependency: createTaskSubtasksDependencyEnabled,
      });
      renderCreateTaskSubtasksEditList();
    }
  });

  document.addEventListener("change", (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const subtaskToggle = target.closest("[data-task-detail-subtask-toggle]");
    if (!(subtaskToggle instanceof HTMLInputElement)) return;
    if (!taskDetailContext || taskDetailContext.readOnly) {
      subtaskToggle.checked = !subtaskToggle.checked;
      return;
    }

    const index = Number.parseInt(subtaskToggle.dataset.taskDetailSubtaskToggle || "-1", 10);
    if (!Number.isFinite(index) || index < 0) return;
    if (!(taskDetailContext.subtasksField instanceof HTMLInputElement)) return;

    const dependencyEnabled = readTaskSubtasksDependencyField(
      taskDetailContext?.subtasksDependencyField,
      false
    );
    const current = readTaskSubtasksField(taskDetailContext.subtasksField, {
      enforceDependency: dependencyEnabled,
    });
    if (index >= current.length) return;

    current[index].done = subtaskToggle.checked;
    const normalized = parseTaskSubtaskList(current, 40, {
      enforceDependency: dependencyEnabled,
    });
    writeTaskSubtasksField(taskDetailContext.subtasksField, normalized, {
      enforceDependency: dependencyEnabled,
    });
    renderTaskRowSubtasksProgress(taskDetailContext.taskItem, normalized);
    renderTaskSubtasksViewList({
      subtasks: normalized,
      readOnly: Boolean(taskDetailContext.readOnly),
      editable: true,
      dependencyEnabled,
    });
    scheduleTaskAutosave(taskDetailContext.form, 60);
  });

  const syncTaskDetailRevisionActionButtons = ({ isEditing = false } = {}) => {
    const hasRequestButton = taskDetailRequestRevisionButton instanceof HTMLButtonElement;
    const hasRemoveButton = taskDetailRemoveRevisionButton instanceof HTMLButtonElement;
    if (!hasRequestButton && !hasRemoveButton) return;

    const statusValue = String(taskDetailContext?.statusSelect?.value || "").trim();
    const statusKind = getStatusOptionKind(getSelectedStatusOption(taskDetailContext?.statusSelect));
    const canUseRevisionActions =
      !isEditing &&
      Boolean(taskDetailContext) &&
      !Boolean(taskDetailContext?.readOnly);
    const canRequestRevision = canUseRevisionActions && statusValue && statusKind === "review";

    const currentDescription = String(taskDetailContext?.descriptionField?.value || "").trim();
    const history = readTaskHistoryField(taskDetailContext?.historyField);
    let hasActiveRevision = hasActiveTaskRevisionRequest({
      description: currentDescription,
      history,
    });
    if (!history.length) {
      const fallbackRevisionState = readTaskRevisionStateField(taskDetailContext?.revisionStateField);
      if (fallbackRevisionState !== null) {
        hasActiveRevision = fallbackRevisionState;
      }
    }

    if (hasRequestButton) {
      taskDetailRequestRevisionButton.hidden = !canRequestRevision;
    }
    if (hasRemoveButton) {
      taskDetailRemoveRevisionButton.hidden = !canUseRevisionActions || !hasActiveRevision;
    }
  };

  const setTaskDetailEditMode = (editing) => {
    if (!taskDetailModal) return;
    const isReadOnlyTask = Boolean(taskDetailContext?.readOnly);
    const isEditing = isReadOnlyTask ? false : Boolean(editing);
    taskDetailModal.classList.toggle("is-editing", isEditing);
    setTaskDetailMediaPage(false);
    if (taskDetailViewPanel instanceof HTMLElement) {
      taskDetailViewPanel.hidden = isEditing;
    }
    if (taskDetailEditPanel instanceof HTMLElement) {
      taskDetailEditPanel.hidden = !isEditing;
    }
    if (taskDetailEditButton instanceof HTMLButtonElement) {
      taskDetailEditButton.hidden = isEditing || isReadOnlyTask;
    }
    if (taskDetailDeleteButton instanceof HTMLButtonElement) {
      taskDetailDeleteButton.hidden = isReadOnlyTask;
    }
    if (taskDetailSaveButton instanceof HTMLButtonElement) {
      taskDetailSaveButton.hidden = !isEditing;
    }
    if (taskDetailCancelEditButton instanceof HTMLButtonElement) {
      taskDetailCancelEditButton.hidden = !isEditing;
    }
    syncTaskDetailRevisionActionButtons({ isEditing });
    if (!isEditing) {
      closeTaskDetailTitleTagMenu();
      closeInlineAddForm(taskDetailEditLinkAddForm, taskDetailEditLinkInput);
      closeInlineAddForm(taskDetailEditSubtaskAddForm, taskDetailEditSubtaskInput);
      if (taskDetailEditTitleTagIsCreating) {
        stopTaskDetailTitleTagCreation();
      }
    }

    if (isEditing) {
      window.setTimeout(() => {
        syncTaskDetailDescriptionEditorFromTextarea();
        writeReferenceLinksEditField(taskDetailEditLinks, taskDetailEditReferenceLinks);
        renderTaskDetailEditReferenceLinks();
        renderTaskDetailImageList();
        syncTaskDetailDescriptionToolbar();
        taskDetailEditTitle?.focus();
      }, 20);
    }
  };

  const getTaskDetailBindings = (taskItem) => {
    if (!(taskItem instanceof HTMLElement)) return null;
    const readOnly = (taskItem.dataset.taskReadonly || "0") === "1";

    const form = taskItem.querySelector("[data-task-autosave-form]");
    const deleteForm = taskItem.querySelector(".task-delete-form");
    if (!(form instanceof HTMLFormElement) || !(deleteForm instanceof HTMLFormElement)) {
      return null;
    }

    const titleInput = form.querySelector('input[name="title"]');
    const statusSelect = form.querySelector('select[name="status"]');
    const prioritySelect = form.querySelector('select[name="priority"]');
    const dueDateInput = form.querySelector('input[name="due_date"]');
    const rowAssigneePicker = form.querySelector(".row-assignee-picker");
    const groupSelect = form.querySelector('[name="group_name"]');
    const descriptionField = form.querySelector('textarea[name="description"]');
    const titleTagField = form.querySelector("[data-task-title-tag]");
    const titleTagColorField = form.querySelector("[data-task-title-tag-color]");
    const referenceLinksField = form.querySelector('[data-task-reference-links-json]');
    const referenceImagesField = form.querySelector('[data-task-reference-images-json]');
    const subtasksField = form.querySelector("[data-task-subtasks-json]");
    const subtasksDependencyField = form.querySelector("[data-task-subtasks-dependency]");
    const overdueFlagField = form.querySelector("[data-task-overdue-flag]");
    const overdueSinceDateField = form.querySelector("[data-task-overdue-since-date]");
    const overdueDaysField = form.querySelector("[data-task-overdue-days]");
    const historyField = form.querySelector("[data-task-history-json]");
    const revisionStateField = form.querySelector("[data-task-has-active-revision]");
    const metaRow = form.querySelector(".task-line-meta");

    if (
      !(titleInput instanceof HTMLInputElement) ||
      !(statusSelect instanceof HTMLSelectElement) ||
      !(prioritySelect instanceof HTMLSelectElement) ||
      !(dueDateInput instanceof HTMLInputElement) ||
      !(rowAssigneePicker instanceof HTMLDetailsElement) ||
      !(groupSelect instanceof HTMLSelectElement) ||
      !(descriptionField instanceof HTMLTextAreaElement)
    ) {
      return null;
    }

    return {
      taskItem,
      form,
      deleteForm,
      titleInput,
      statusSelect,
      prioritySelect,
      dueDateInput,
      rowAssigneePicker,
      groupSelect,
      descriptionField,
      titleTagField: titleTagField instanceof HTMLInputElement ? titleTagField : null,
      titleTagColorField: titleTagColorField instanceof HTMLInputElement ? titleTagColorField : null,
      referenceLinksField: referenceLinksField instanceof HTMLInputElement ? referenceLinksField : null,
      referenceImagesField: referenceImagesField instanceof HTMLInputElement ? referenceImagesField : null,
      subtasksField: subtasksField instanceof HTMLInputElement ? subtasksField : null,
      subtasksDependencyField:
        subtasksDependencyField instanceof HTMLInputElement ? subtasksDependencyField : null,
      overdueFlagField: overdueFlagField instanceof HTMLInputElement ? overdueFlagField : null,
      overdueSinceDateField:
        overdueSinceDateField instanceof HTMLInputElement ? overdueSinceDateField : null,
      overdueDaysField: overdueDaysField instanceof HTMLInputElement ? overdueDaysField : null,
      historyField: historyField instanceof HTMLInputElement ? historyField : null,
      revisionStateField: revisionStateField instanceof HTMLInputElement ? revisionStateField : null,
      metaRow,
      readOnly,
    };
  };

  const hydrateTaskDetailPayloadFromServer = async (context = taskDetailContext, { force = false } = {}) => {
    if (!context || !(context.form instanceof HTMLFormElement)) return false;
    const isHydrated = context.form.dataset.taskDetailHydrated === "1";
    if (isHydrated && !force) {
      return true;
    }
    if (context.form.dataset.taskDetailHydrating === "1") {
      return false;
    }

    const taskIdField = context.form.querySelector('input[name="task_id"]');
    const csrfField = context.form.querySelector('input[name="csrf_token"]');
    if (!(taskIdField instanceof HTMLInputElement) || !(csrfField instanceof HTMLInputElement)) {
      return false;
    }

    context.form.dataset.taskDetailHydrating = "1";
    try {
      const data = await postActionJson("load_task_detail", {
        csrf_token: csrfField.value || "",
        task_id: taskIdField.value || "",
      });
      const task = data?.task || {};

      if (typeof task.description === "string") {
        context.descriptionField.value = task.description;
      }

      const linksField = ensureTaskHiddenField(context.form, {
        name: "reference_links_json",
        withName: true,
        dataSelector: "[data-task-reference-links-json]",
        dataAttrName: "data-task-reference-links-json",
      });
      if (linksField instanceof HTMLInputElement) {
        if (typeof task.reference_links_json === "string") {
          linksField.value = task.reference_links_json;
        }
        context.referenceLinksField = linksField;
      }

      const imagesField = ensureTaskHiddenField(context.form, {
        name: "reference_images_json",
        withName: false,
        dataSelector: "[data-task-reference-images-json]",
        dataAttrName: "data-task-reference-images-json",
      });
      if (imagesField instanceof HTMLInputElement) {
        if (typeof task.reference_images_json === "string") {
          imagesField.value = task.reference_images_json;
        }
        imagesField.removeAttribute("name");
        context.referenceImagesField = imagesField;
      }

      const subtasksField = ensureTaskHiddenField(context.form, {
        name: "subtasks_json",
        withName: true,
        dataSelector: "[data-task-subtasks-json]",
        dataAttrName: "data-task-subtasks-json",
      });
      const subtasksDependencyField = ensureTaskHiddenField(context.form, {
        name: "subtasks_dependency_enabled",
        withName: true,
        dataSelector: "[data-task-subtasks-dependency]",
        dataAttrName: "data-task-subtasks-dependency",
      });
      if (subtasksDependencyField instanceof HTMLInputElement) {
        const nextDependencyValue = Object.prototype.hasOwnProperty.call(
          task,
          "subtasks_dependency_enabled"
        )
          ? normalizeTaskSubtasksDependencyValue(task.subtasks_dependency_enabled, false)
          : readTaskSubtasksDependencyField(subtasksDependencyField, false);
        writeTaskSubtasksDependencyField(subtasksDependencyField, nextDependencyValue);
        context.subtasksDependencyField = subtasksDependencyField;
      }
      if (subtasksField instanceof HTMLInputElement) {
        const enforceDependency = readTaskSubtasksDependencyField(
          context.subtasksDependencyField,
          false
        );
        if (typeof task.subtasks_json === "string") {
          const parsedSubtasks = parseTaskSubtaskList(task.subtasks_json, 40, {
            enforceDependency,
          });
          writeTaskSubtasksField(subtasksField, parsedSubtasks, {
            enforceDependency,
          });
        }
        context.subtasksField = subtasksField;
      }

      const historyField = ensureTaskHiddenField(context.form, {
        withName: false,
        dataSelector: "[data-task-history-json]",
        dataAttrName: "data-task-history-json",
      });
      if (historyField instanceof HTMLInputElement) {
        if (Array.isArray(task.history)) {
          writeTaskHistoryField(historyField, task.history);
        }
        context.historyField = historyField;
      }

      const revisionStateField = ensureTaskHiddenField(context.form, {
        withName: false,
        dataSelector: "[data-task-has-active-revision]",
        dataAttrName: "data-task-has-active-revision",
      });
      if (revisionStateField instanceof HTMLInputElement) {
        if (Object.prototype.hasOwnProperty.call(task, "has_active_revision")) {
          writeTaskRevisionStateField(
            revisionStateField,
            Number.parseInt(String(task.has_active_revision || "0"), 10) === 1
          );
        }
        context.revisionStateField = revisionStateField;
      }

      if (typeof task.updated_at === "string") {
        syncTaskExpectedUpdatedAt(context.form, task.updated_at);
      }
      if (typeof task.updated_at_label === "string") {
        refreshTaskUpdatedAtMeta(context.form, task.updated_at_label);
      }

      context.form.dataset.taskDetailHydrated = "1";
      if (taskDetailContext === context && taskDetailModal instanceof HTMLElement && !taskDetailModal.hidden) {
        populateTaskDetailModalFromRow(context);
      }
      return true;
    } catch (error) {
      context.form.dataset.taskDetailHydrated = "0";
      throw error;
    } finally {
      delete context.form.dataset.taskDetailHydrating;
    }
  };

  const copySelectOptions = (sourceSelect, targetSelect) => {
    if (!(sourceSelect instanceof HTMLSelectElement) || !(targetSelect instanceof HTMLSelectElement)) {
      return;
    }

    const current = sourceSelect.value;
    targetSelect.innerHTML = "";
    Array.from(sourceSelect.options).forEach((option) => {
      const next = document.createElement("option");
      next.value = option.value;
      next.textContent = option.textContent;
      next.selected = option.value === current;
      targetSelect.append(next);
    });
    targetSelect.value = current;
  };

  const copyAssigneesToTaskDetailModal = (rowAssigneePicker) => {
    if (
      !(rowAssigneePicker instanceof HTMLDetailsElement) ||
      !(taskDetailEditAssignees instanceof HTMLDetailsElement) ||
      !(taskDetailEditAssigneesMenu instanceof HTMLElement)
    ) {
      return;
    }

    taskDetailEditAssignees.open = false;
    taskDetailEditAssigneesMenu.innerHTML = "";

    const options = rowAssigneePicker.querySelectorAll(".assignee-option");
    options.forEach((option) => {
      const clone = option.cloneNode(true);
      taskDetailEditAssigneesMenu.append(clone);
    });

    updateAssigneePickerSummaryVisual(taskDetailEditAssignees);
  };

  const syncTaskDetailViewPriorityTag = (priorityValue) => {
    if (!(taskDetailViewPriority instanceof HTMLElement)) return;

    Array.from(taskDetailViewPriority.classList).forEach((className) => {
      if (className.startsWith("priority-")) {
        taskDetailViewPriority.classList.remove(className);
      }
    });

    const normalizedPriority =
      typeof priorityValue === "string" && priorityValue.trim()
        ? priorityValue.trim()
        : "medium";
    taskDetailViewPriority.classList.add(`priority-${normalizedPriority}`);
    taskDetailViewPriority.textContent = priorityFlagGlyph;
    taskDetailViewPriority.setAttribute(
      "aria-label",
      `Prioridade: ${priorityLabels[normalizedPriority] || normalizedPriority}`
    );
  };

  const syncTaskDetailViewStatusTag = (
    statusValue,
    statusLabel,
    statusKind = "todo",
    statusColor = ""
  ) => {
    if (!(taskDetailViewStatus instanceof HTMLElement)) return;

    Array.from(taskDetailViewStatus.classList).forEach((className) => {
      if (className.startsWith("status-")) {
        taskDetailViewStatus.classList.remove(className);
      }
    });

    const normalizedStatus =
      typeof statusValue === "string" && statusValue.trim()
        ? statusValue.trim()
        : "todo";
    const normalizedStatusKind = normalizeTaskStatusKind(statusKind);
    taskDetailViewStatus.classList.add(`status-${normalizedStatusKind}`);
    applyStatusStyleVars(taskDetailViewStatus, statusColor, normalizedStatusKind);
    taskDetailViewStatus.textContent = statusLabel || normalizedStatus;
  };

  const populateTaskDetailModalFromRow = (context = taskDetailContext) => {
    if (!context) return;
    const {
      titleInput,
      statusSelect,
      prioritySelect,
      dueDateInput,
      rowAssigneePicker,
      groupSelect,
      descriptionField,
      titleTagField,
      titleTagColorField,
      referenceLinksField,
      referenceImagesField,
      subtasksField,
      subtasksDependencyField,
      overdueFlagField,
      overdueSinceDateField,
      overdueDaysField,
      historyField,
      revisionStateField,
      metaRow,
    } = context;

    const titleValue = (titleInput.value || "").trim() || "Tarefa";
    const statusLabel =
      statusSelect.options[statusSelect.selectedIndex]?.textContent?.trim() || statusSelect.value || "Status";
    const groupLabel =
      groupSelect.options[groupSelect.selectedIndex]?.textContent?.trim() || groupSelect.value || "Geral";
    const dueMeta = dueDateMeta(dueDateInput.value || "");
    const titleTag = normalizeTaskTitleTagValue(titleTagField?.value || "");
    const titleTagColor = titleTag
      ? resolveTaskTitleTagColor(titleTag, titleTagColorField?.value || "")
      : normalizeTaskTitleTagColorValue(titleTagColorField?.value || "", taskTitleTagDefaultColor);
    const assignees = getCheckedAssigneeData(rowAssigneePicker);
    const description = (descriptionField.value || "").trim();
    const referenceLinks = readJsonUrlListField(referenceLinksField, parseReferenceUrlLines);
    const referenceImages = readReferenceImageMediaField(referenceImagesField);
    const subtasksDependencyEnabled = readTaskSubtasksDependencyField(subtasksDependencyField, false);
    const subtasks = readTaskSubtasksField(subtasksField, {
      enforceDependency: subtasksDependencyEnabled,
    });
    const overdueFlag =
      overdueFlagField instanceof HTMLInputElement && overdueFlagField.value === "1" ? 1 : 0;
    const overdueSinceDate =
      overdueSinceDateField instanceof HTMLInputElement ? overdueSinceDateField.value || "" : "";
    const overdueDays =
      overdueDaysField instanceof HTMLInputElement
        ? Math.max(0, Number.parseInt(overdueDaysField.value || "0", 10) || 0)
        : 0;
    const history = readTaskHistoryField(historyField);
    let hasActiveRevision = hasActiveTaskRevisionRequest({
      description,
      history,
    });
    if (!history.length) {
      const fallbackRevisionState = readTaskRevisionStateField(revisionStateField);
      if (fallbackRevisionState !== null) {
        hasActiveRevision = fallbackRevisionState;
      }
    }
    const metaSpans = metaRow ? Array.from(metaRow.querySelectorAll("span")) : [];
    const createdByText = metaSpans[0]?.textContent?.trim() || "";
    const updatedAtText = metaRow?.querySelector("[data-task-updated-at]")?.textContent?.trim() || "";

    if (taskDetailTitle) taskDetailTitle.textContent = titleValue;
    syncTaskTitleTagBadge(context.taskItem, titleTag, titleTagColor);
    syncTaskDetailViewTitleTag(titleTag, titleTagColor);
    const selectedStatusOption = getSelectedStatusOption(statusSelect);
    syncTaskDetailViewStatusTag(
      statusSelect.value || "todo",
      statusLabel,
      getStatusOptionKind(selectedStatusOption),
      getStatusOptionColor(selectedStatusOption)
    );
    syncTaskDetailViewPriorityTag(prioritySelect.value || "medium");
    if (taskDetailViewGroup) taskDetailViewGroup.textContent = groupLabel;
    if (taskDetailViewDue) taskDetailViewDue.textContent = dueMeta.display;
    if (taskDetailViewAssignees) {
      const assigneeLabel = assignees.map((assignee) => assignee.name).join(", ");
      taskDetailViewAssignees.innerHTML = renderAssigneeSummaryMarkup(assignees, "Sem responsavel");
      if (assigneeLabel) {
        taskDetailViewAssignees.title = assigneeLabel;
        taskDetailViewAssignees.setAttribute("aria-label", assigneeLabel);
      } else {
        taskDetailViewAssignees.removeAttribute("title");
        taskDetailViewAssignees.setAttribute("aria-label", "Sem responsavel");
      }
    }
    renderTaskDetailDescriptionView({ description, history });
    renderTaskSubtasksViewList({
      subtasks,
      readOnly: Boolean(context.readOnly),
      editable: true,
      dependencyEnabled: subtasksDependencyEnabled,
    });
    renderTaskDetailReferencesView({ links: referenceLinks, images: referenceImages });
    renderTaskDetailHistoryView({
      history,
      overdueFlag,
      overdueDays,
      overdueSinceDate,
    });
    if (taskDetailViewCreatedBy) taskDetailViewCreatedBy.textContent = createdByText;
    if (taskDetailViewUpdatedAt) {
      taskDetailViewUpdatedAt.textContent = updatedAtText;
      taskDetailViewUpdatedAt.hidden = !updatedAtText;
    }

    if (taskDetailEditTitle instanceof HTMLInputElement) {
      taskDetailEditTitle.value = titleInput.value || "";
    }
    resetTaskDetailTitleTagPicker(titleTag, titleTagColor);
    if (taskDetailEditStatus instanceof HTMLSelectElement) {
      taskDetailEditStatus.value = statusSelect.value || "todo";
      syncSelectColor(taskDetailEditStatus);
    }
    if (taskDetailEditPriority instanceof HTMLSelectElement) {
      taskDetailEditPriority.value = prioritySelect.value || "medium";
      syncSelectColor(taskDetailEditPriority);
    }
    if (taskDetailEditGroup instanceof HTMLSelectElement) {
      copySelectOptions(groupSelect, taskDetailEditGroup);
      if (typeof collectGroupNames === "function") {
        const allGroupNames = collectGroupNames();
        allGroupNames.forEach((name) => {
          if (!Array.from(taskDetailEditGroup.options).some((opt) => opt.value === name)) {
            const option = document.createElement("option");
            option.value = name;
            option.textContent = name;
            taskDetailEditGroup.append(option);
          }
        });
      }
      taskDetailEditGroup.value = groupSelect.value || "Geral";
      syncInlineSelectPicker(taskDetailEditGroup);
    }
    if (taskDetailEditDueDate instanceof HTMLInputElement) {
      setIsoDateInputValue(taskDetailEditDueDate, dueDateInput.value || "");
    }
    if (taskDetailEditDescription instanceof HTMLTextAreaElement) {
      taskDetailEditDescription.value = descriptionField.value || "";
      syncTaskDetailDescriptionEditorFromTextarea();
    }
    setTaskDetailEditReferenceLinks(referenceLinks);
    setTaskDetailEditSubtasks(subtasks, {
      dependencyEnabled: subtasksDependencyEnabled,
    });
    renderTaskDetailSubtasksEditList();
    taskDetailImagePickerExpanded = false;
    setTaskDetailEditImageItems(referenceImages);
    copyAssigneesToTaskDetailModal(rowAssigneePicker);
    writeTaskRevisionStateField(revisionStateField, hasActiveRevision);
    syncTaskDetailRevisionActionButtons({
      isEditing: Boolean(taskDetailModal?.classList.contains("is-editing")),
    });
  };

  const currentTaskDetailTaskId = () => {
    const taskIdField = taskDetailContext?.form?.querySelector?.('input[name="task_id"]');
    return taskIdField instanceof HTMLInputElement
      ? Math.max(0, Number.parseInt(taskIdField.value || "0", 10) || 0)
      : 0;
  };

  const openTaskDetailModal = (taskItem, { updateUrl = true, scrollIntoView = false } = {}) => {
    if (!taskDetailModal) return;
    const bindings = getTaskDetailBindings(taskItem);
    if (!bindings) return;
    const taskIdField = bindings.form.querySelector('input[name="task_id"]');
    const taskId =
      taskIdField instanceof HTMLInputElement
        ? Math.max(0, Number.parseInt(taskIdField.value || "0", 10) || 0)
        : 0;

    taskDetailContext = bindings;
    populateTaskDetailModalFromRow(bindings);
    setTaskDetailEditMode(false);
    taskDetailModal.hidden = false;
    if (scrollIntoView && taskItem instanceof HTMLElement) {
      taskItem.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    if (updateUrl) {
      replaceDashboardStateUrl("tasks", { taskId });
    }
    syncBodyModalLock();
    void hydrateTaskDetailPayloadFromServer(bindings).catch(() => {});
    window.setTimeout(() => {
      const closeButton = taskDetailModal.querySelector(".modal-close-button[data-close-task-detail-modal]");
      if (closeButton instanceof HTMLElement) closeButton.focus();
    }, 20);
  };

  const closeTaskDetailModal = ({ updateUrl = true } = {}) => {
    if (!taskDetailModal) return;
    closeTaskReviewModal();
    closeTaskImagePreview();
    resetTaskDetailTitleTagPicker();
    setTaskDetailMediaPage(false);
    taskDetailModal.hidden = true;
    taskDetailContext = null;
    taskDetailImagePickerExpanded = false;
    setTaskDetailEditReferenceLinks([]);
    closeInlineAddForm(taskDetailEditLinkAddForm, taskDetailEditLinkInput);
    closeInlineAddForm(taskDetailEditSubtaskAddForm, taskDetailEditSubtaskInput);
    taskDetailEditSubtaskItems = [];
    setTaskDetailEditSubtasksDependencyEnabled(false);
    setTaskDetailEditMode(false);
    if (taskDetailSaveButton instanceof HTMLButtonElement) {
      taskDetailSaveButton.disabled = false;
      taskDetailSaveButton.classList.remove("is-loading");
      taskDetailSaveButton.textContent = "Salvar";
    }
    if (updateUrl) {
      replaceDashboardStateUrl(dashboardViewFromUrl(), { taskId: 0 });
    }
    syncBodyModalLock();
  };

  syncTaskDetailModalFromUrl = ({ closeIfMissing = true, scrollIntoView = false } = {}) => {
    const activeView = dashboardViewFromUrl();
    const taskId = activeView === "tasks" ? dashboardTaskIdFromUrl() : 0;
    if (!(taskId > 0)) {
      if (closeIfMissing) {
        closeTaskDetailModal({ updateUrl: false });
      }
      return;
    }

    const taskItem = document.getElementById(`task-${taskId}`);
    if (!(taskItem instanceof HTMLElement)) {
      if (closeIfMissing) {
        closeTaskDetailModal({ updateUrl: false });
      }
      return;
    }

    if (
      taskDetailModal instanceof HTMLElement &&
      !taskDetailModal.hidden &&
      currentTaskDetailTaskId() === taskId
    ) {
      if (scrollIntoView) {
        taskItem.scrollIntoView({ behavior: "smooth", block: "center" });
      }
      return;
    }

    openTaskDetailModal(taskItem, {
      updateUrl: false,
      scrollIntoView,
    });
  };

  const copyTaskDetailModalToRow = (context = taskDetailContext) => {
    if (!context) return false;
    if (context.readOnly) {
      showClientFlash("error", "Você não possui acesso para editar tarefas deste grupo.");
      return false;
    }
    if (
      !(taskDetailEditTitle instanceof HTMLInputElement) ||
      !(taskDetailEditStatus instanceof HTMLSelectElement) ||
      !(taskDetailEditPriority instanceof HTMLSelectElement) ||
      !(taskDetailEditGroup instanceof HTMLSelectElement) ||
      !(taskDetailEditDueDate instanceof HTMLInputElement) ||
      !(taskDetailEditDescription instanceof HTMLTextAreaElement)
    ) {
      return false;
    }

    if (typeof taskDetailEditTitle.reportValidity === "function" && !taskDetailEditTitle.reportValidity()) {
      return false;
    }

    applyFirstLetterUppercaseToInput(taskDetailEditTitle);
    if (taskDetailEditTitleTagCustom instanceof HTMLInputElement) {
      applyFirstLetterUppercaseToInput(taskDetailEditTitleTagCustom);
    }
    syncTaskDetailDescriptionTextareaFromEditor();

    const nextTitleTag = taskDetailEditTitleTagIsCreating
      ? commitTaskDetailTitleTagCreation()
      : normalizeTaskTitleTagValue(
          taskDetailEditTitleTagInput?.value || taskDetailEditCurrentTitleTag
        );
    const nextTitleTagColor = nextTitleTag
      ? resolveTaskTitleTagColor(
          nextTitleTag,
          taskDetailEditTitleTagColorInput?.value || taskDetailEditCurrentTitleTagColor
        )
      : normalizeTaskTitleTagColorValue(
          taskDetailEditTitleTagColorInput?.value || taskDetailEditCurrentTitleTagColor,
          taskTitleTagDefaultColor
        );
    setTaskDetailTitleTagValue(nextTitleTag, nextTitleTagColor);

    context.titleInput.value = taskDetailEditTitle.value;
    if (context.titleTagField instanceof HTMLInputElement) {
      context.titleTagField.value = nextTitleTag;
    }
    if (context.titleTagColorField instanceof HTMLInputElement) {
      context.titleTagColorField.value = nextTitleTagColor;
    }
    syncTaskTitleTagBadge(context.taskItem, nextTitleTag, nextTitleTagColor);
    context.statusSelect.value = taskDetailEditStatus.value;
    context.prioritySelect.value = taskDetailEditPriority.value;
    setIsoDateInputValue(context.dueDateInput, taskDetailEditDueDate.value);
    context.descriptionField.value = taskDetailEditDescription.value;
    if (context.referenceLinksField instanceof HTMLInputElement) {
      writeReferenceLinksEditField(taskDetailEditLinks, taskDetailEditReferenceLinks);
      writeJsonUrlListField(
        context.referenceLinksField,
        taskDetailEditReferenceLinks,
        parseReferenceUrlLines
      );
    }
    if (!(context.referenceImagesField instanceof HTMLInputElement)) {
      context.referenceImagesField = ensureTaskHiddenField(context.form, {
        name: "reference_images_json",
        withName: false,
        dataSelector: "[data-task-reference-images-json]",
        dataAttrName: "data-task-reference-images-json",
      });
    }
    if (context.referenceImagesField instanceof HTMLInputElement) {
      const referenceImages = parseReferenceImageMediaItems(taskDetailEditImageItems);
      context.referenceImagesField.name = "reference_images_json";
      writeReferenceImageMediaField(context.referenceImagesField, referenceImages);
    }
    if (context.subtasksField instanceof HTMLInputElement) {
      if (!(context.subtasksDependencyField instanceof HTMLInputElement)) {
        context.subtasksDependencyField = ensureTaskHiddenField(context.form, {
          name: "subtasks_dependency_enabled",
          withName: true,
          dataSelector: "[data-task-subtasks-dependency]",
          dataAttrName: "data-task-subtasks-dependency",
        });
      }
      if (context.subtasksDependencyField instanceof HTMLInputElement) {
        writeTaskSubtasksDependencyField(
          context.subtasksDependencyField,
          taskDetailEditSubtasksDependencyEnabled
        );
      }
      const normalizedSubtasks = parseTaskSubtaskList(taskDetailEditSubtaskItems, 40, {
        enforceDependency: taskDetailEditSubtasksDependencyEnabled,
      });
      writeTaskSubtasksField(context.subtasksField, normalizedSubtasks, {
        enforceDependency: taskDetailEditSubtasksDependencyEnabled,
      });
      renderTaskRowSubtasksProgress(context.taskItem, normalizedSubtasks);
    }

    const groupValue = (taskDetailEditGroup.value || "Geral").trim() || "Geral";
    if (!Array.from(context.groupSelect.options).some((option) => option.value === groupValue)) {
      const option = document.createElement("option");
      option.value = groupValue;
      option.textContent = groupValue;
      context.groupSelect.append(option);
    }
    context.groupSelect.value = groupValue;

    const selectedAssigneeIds = new Set(
      Array.from(
        taskDetailEditAssigneesMenu?.querySelectorAll('input[type="checkbox"]:checked') || []
      )
        .map((input) => String(input.value || "").trim())
        .filter(Boolean)
    );

    context.rowAssigneePicker
      .querySelectorAll('input[type="checkbox"][name="assigned_to[]"]')
      .forEach((checkbox) => {
        checkbox.checked = selectedAssigneeIds.has(String(checkbox.value));
      });

    syncSelectColor(context.statusSelect);
    syncSelectColor(context.prioritySelect);
    syncDueDateDisplay(context.dueDateInput);
    updateAssigneePickerSummaryVisual(context.rowAssigneePicker);

    return true;
  };

  const waitForFormAutosaveIdle = async (form, timeoutMs = 8000) => {
    if (!(form instanceof HTMLFormElement)) return false;
    const startedAt = Date.now();

    while (form.dataset.autosaveSubmitting === "1") {
      if (Date.now() - startedAt > timeoutMs) {
        return false;
      }
      await new Promise((resolve) => window.setTimeout(resolve, 70));
    }

    return true;
  };

  const closeTaskReviewModal = () => {
    if (!(taskReviewModal instanceof HTMLElement)) return;
    taskReviewModal.hidden = true;
    if (taskReviewForm instanceof HTMLFormElement) {
      taskReviewForm.reset();
    }
    if (taskReviewTaskIdInput instanceof HTMLInputElement) {
      taskReviewTaskIdInput.value = "";
    }
    syncBodyModalLock();
  };

  const openTaskReviewModal = () => {
    if (!(taskReviewModal instanceof HTMLElement)) return;
    if (!(taskDetailContext?.form instanceof HTMLFormElement)) return;
    if (Boolean(taskDetailContext.readOnly)) {
      showClientFlash("error", "Você não possui acesso para solicitar ajuste nesta tarefa.");
      return;
    }

    const statusValue = String(taskDetailContext.statusSelect?.value || "").trim();
    const statusKind = getStatusOptionKind(getSelectedStatusOption(taskDetailContext.statusSelect));
    if (!statusValue || statusKind !== "review") {
      showClientFlash("error", "A solicitação de ajuste so esta disponível para tarefas em revisão.");
      return;
    }

    const taskIdField = taskDetailContext.form.querySelector('input[name="task_id"]');
    if (!(taskIdField instanceof HTMLInputElement) || !taskIdField.value) return;
    if (taskReviewTaskIdInput instanceof HTMLInputElement) {
      taskReviewTaskIdInput.value = taskIdField.value;
    }
    if (taskReviewDescriptionInput instanceof HTMLTextAreaElement) {
      taskReviewDescriptionInput.value = "";
    }

    taskReviewModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      taskReviewDescriptionInput?.focus();
    }, 20);
  };

  const submitTaskReviewRequest = async () => {
    if (!(taskReviewForm instanceof HTMLFormElement)) return;
    if (!(taskReviewDescriptionInput instanceof HTMLTextAreaElement)) return;
    if (!(taskDetailContext?.form instanceof HTMLFormElement)) return;

    const nextDescription = String(taskReviewDescriptionInput.value || "").trim();
    if (!nextDescription) {
      taskReviewDescriptionInput.reportValidity?.();
      return;
    }

    if (taskReviewSubmitButton instanceof HTMLButtonElement) {
      taskReviewSubmitButton.disabled = true;
      taskReviewSubmitButton.classList.add("is-loading");
      taskReviewSubmitButton.textContent = "Salvando";
    }

    try {
      const data = await postFormJson(taskReviewForm);
      const task = data.task || {};

      if (typeof task.description === "string") {
        taskDetailContext.descriptionField.value = task.description;
      }
      if (Array.isArray(task.history)) {
        const historyField = ensureTaskHiddenField(taskDetailContext.form, {
          withName: false,
          dataSelector: "[data-task-history-json]",
          dataAttrName: "data-task-history-json",
        });
        if (historyField instanceof HTMLInputElement) {
          writeTaskHistoryField(historyField, task.history);
          taskDetailContext.historyField = historyField;
        }
      }
      if (Object.prototype.hasOwnProperty.call(task, "has_active_revision")) {
        const revisionStateField = ensureTaskHiddenField(taskDetailContext.form, {
          withName: false,
          dataSelector: "[data-task-has-active-revision]",
          dataAttrName: "data-task-has-active-revision",
        });
        if (revisionStateField instanceof HTMLInputElement) {
          writeTaskRevisionStateField(
            revisionStateField,
            Number.parseInt(String(task.has_active_revision || "0"), 10) === 1
          );
          taskDetailContext.revisionStateField = revisionStateField;
        }
      }
      if (typeof task.updated_at === "string") {
        syncTaskExpectedUpdatedAt(taskDetailContext.form, task.updated_at);
      }
      if (typeof task.updated_at_label === "string") {
        refreshTaskUpdatedAtMeta(taskDetailContext.form, task.updated_at_label);
      }
      if (typeof task.status === "string" && taskDetailContext.statusSelect instanceof HTMLSelectElement) {
        taskDetailContext.statusSelect.value = task.status;
        syncSelectColor(taskDetailContext.statusSelect);
      }
      syncTaskRevisionBadge(taskDetailContext.form);

      renderDashboardSummary(data.dashboard);
      populateTaskDetailModalFromRow(taskDetailContext);
      setTaskDetailEditMode(false);
      closeTaskReviewModal();
      showClientFlash("success", "Ajuste solicitado na tarefa.");
    } catch (error) {
      showClientFlash(
        "error",
        error instanceof Error ? error.message : "Não foi possível solicitar ajuste na tarefa."
      );
    } finally {
      if (taskReviewSubmitButton instanceof HTMLButtonElement) {
        taskReviewSubmitButton.disabled = false;
        taskReviewSubmitButton.classList.remove("is-loading");
        taskReviewSubmitButton.textContent = "Salvar ajuste";
      }
    }
  };

  const submitTaskRevisionRemoval = async () => {
    if (!(taskRemoveRevisionForm instanceof HTMLFormElement)) return;
    if (!(taskRemoveRevisionTaskIdInput instanceof HTMLInputElement)) return;
    const sourceForm = taskDetailContext?.form;
    if (!(sourceForm instanceof HTMLFormElement)) return;
    if (Boolean(taskDetailContext?.readOnly)) {
      showClientFlash("error", "Você não possui acesso para remover ajuste desta tarefa.");
      return;
    }

    const removeRevisionFromForm = async (form) => {
      if (!(form instanceof HTMLFormElement)) return false;
      if (form.dataset.revisionRemoving === "1") return false;

      const taskIdField = form.querySelector('input[name="task_id"]');
      const csrfField = form.querySelector('input[name="csrf_token"]');
      if (!(taskIdField instanceof HTMLInputElement) || !(csrfField instanceof HTMLInputElement)) {
        return false;
      }

      taskRemoveRevisionTaskIdInput.value = taskIdField.value;
      const removeCsrfField = taskRemoveRevisionForm.querySelector('input[name="csrf_token"]');
      if (removeCsrfField instanceof HTMLInputElement) {
        removeCsrfField.value = csrfField.value;
      }

      if (form.dataset.autosaveSubmitting === "1") {
        const idle = await waitForFormAutosaveIdle(form);
        if (!idle) {
          showClientFlash("error", "Aguarde a tarefa terminar de salvar para remover o ajuste.");
          return false;
        }
      }

      form.dataset.revisionRemoving = "1";
      try {
        const data = await postFormJson(taskRemoveRevisionForm);
        const task = data.task || {};

        const descriptionField = form.querySelector('textarea[name="description"]');
        const historyField = ensureTaskHiddenField(form, {
          withName: false,
          dataSelector: "[data-task-history-json]",
          dataAttrName: "data-task-history-json",
        });
        const statusField = form.querySelector('select[name="status"]');
        const revisionStateField = ensureTaskHiddenField(form, {
          withName: false,
          dataSelector: "[data-task-has-active-revision]",
          dataAttrName: "data-task-has-active-revision",
        });

        if (typeof task.description === "string" && descriptionField instanceof HTMLTextAreaElement) {
          descriptionField.value = task.description;
        }
        if (Array.isArray(task.history) && historyField instanceof HTMLInputElement) {
          writeTaskHistoryField(historyField, task.history);
        }
        if (Object.prototype.hasOwnProperty.call(task, "has_active_revision")) {
          writeTaskRevisionStateField(
            revisionStateField,
            Number.parseInt(String(task.has_active_revision || "0"), 10) === 1
          );
        }
        if (taskDetailContext && taskDetailContext.form === form) {
          taskDetailContext.historyField = historyField instanceof HTMLInputElement ? historyField : null;
          taskDetailContext.revisionStateField =
            revisionStateField instanceof HTMLInputElement ? revisionStateField : null;
        }
        if (typeof task.updated_at === "string") {
          syncTaskExpectedUpdatedAt(form, task.updated_at);
        }
        if (typeof task.updated_at_label === "string") {
          refreshTaskUpdatedAtMeta(form, task.updated_at_label);
        }
        if (typeof task.status === "string" && statusField instanceof HTMLSelectElement) {
          statusField.value = task.status;
          syncSelectColor(statusField);
        }

        syncTaskRevisionBadge(form);
        renderDashboardSummary(data.dashboard);

        if (
          taskDetailContext &&
          taskDetailContext.form === form &&
          taskDetailModal instanceof HTMLElement &&
          !taskDetailModal.hidden
        ) {
          populateTaskDetailModalFromRow(taskDetailContext);
          setTaskDetailEditMode(false);
        }

        showClientFlash("success", "Solicitação de ajuste removida.");
        return true;
      } catch (error) {
        showClientFlash(
          "error",
          error instanceof Error ? error.message : "Não foi possível remover a solicitação de ajuste."
        );
        return false;
      } finally {
        delete form.dataset.revisionRemoving;
      }
    };

    if (taskDetailRemoveRevisionButton instanceof HTMLButtonElement) {
      taskDetailRemoveRevisionButton.disabled = true;
      taskDetailRemoveRevisionButton.classList.add("is-loading");
      taskDetailRemoveRevisionButton.setAttribute("aria-busy", "true");
    }

    try {
      await removeRevisionFromForm(sourceForm);
    } finally {
      if (taskDetailRemoveRevisionButton instanceof HTMLButtonElement) {
        taskDetailRemoveRevisionButton.disabled = false;
        taskDetailRemoveRevisionButton.classList.remove("is-loading");
        taskDetailRemoveRevisionButton.removeAttribute("aria-busy");
      }
    }
  };

  const submitTaskRevisionRemovalFromRow = async (form) => {
    if (!(taskRemoveRevisionForm instanceof HTMLFormElement)) return false;
    if (!(taskRemoveRevisionTaskIdInput instanceof HTMLInputElement)) return false;
    if (!(form instanceof HTMLFormElement)) return false;
    if (form.dataset.revisionRemoving === "1") return false;

    const fieldset = form.querySelector("fieldset");
    if (fieldset instanceof HTMLFieldSetElement && fieldset.disabled) {
      return false;
    }

    const taskIdField = form.querySelector('input[name="task_id"]');
    const csrfField = form.querySelector('input[name="csrf_token"]');
    if (!(taskIdField instanceof HTMLInputElement) || !(csrfField instanceof HTMLInputElement)) {
      return false;
    }

    taskRemoveRevisionTaskIdInput.value = String(taskIdField.value || "");
    const removeCsrfField = taskRemoveRevisionForm.querySelector('input[name="csrf_token"]');
    if (removeCsrfField instanceof HTMLInputElement) {
      removeCsrfField.value = csrfField.value;
    }

    if (form.dataset.autosaveSubmitting === "1") {
      const idle = await waitForFormAutosaveIdle(form);
      if (!idle) {
        showClientFlash("error", "Aguarde a tarefa terminar de salvar para remover o ajuste.");
        return false;
      }
    }

    form.dataset.revisionRemoving = "1";
    try {
      const data = await postFormJson(taskRemoveRevisionForm);
      const task = data.task || {};

      const descriptionField = form.querySelector('textarea[name="description"]');
      const historyField = ensureTaskHiddenField(form, {
        withName: false,
        dataSelector: "[data-task-history-json]",
        dataAttrName: "data-task-history-json",
      });
      const statusField = form.querySelector('select[name="status"]');
      const revisionStateField = ensureTaskHiddenField(form, {
        withName: false,
        dataSelector: "[data-task-has-active-revision]",
        dataAttrName: "data-task-has-active-revision",
      });

      if (typeof task.description === "string" && descriptionField instanceof HTMLTextAreaElement) {
        descriptionField.value = task.description;
      }
      if (Array.isArray(task.history) && historyField instanceof HTMLInputElement) {
        writeTaskHistoryField(historyField, task.history);
      }
      if (Object.prototype.hasOwnProperty.call(task, "has_active_revision")) {
        writeTaskRevisionStateField(
          revisionStateField,
          Number.parseInt(String(task.has_active_revision || "0"), 10) === 1
        );
      }
      if (taskDetailContext && taskDetailContext.form === form) {
        taskDetailContext.historyField = historyField instanceof HTMLInputElement ? historyField : null;
        taskDetailContext.revisionStateField =
          revisionStateField instanceof HTMLInputElement ? revisionStateField : null;
      }
      if (typeof task.updated_at === "string") {
        syncTaskExpectedUpdatedAt(form, task.updated_at);
      }
      if (typeof task.updated_at_label === "string") {
        refreshTaskUpdatedAtMeta(form, task.updated_at_label);
      }
      if (typeof task.status === "string" && statusField instanceof HTMLSelectElement) {
        statusField.value = task.status;
        syncSelectColor(statusField);
      }
      syncTaskRevisionBadge(form);

      renderDashboardSummary(data.dashboard);
      if (
        taskDetailContext &&
        taskDetailContext.form === form &&
        taskDetailModal instanceof HTMLElement &&
        !taskDetailModal.hidden
      ) {
        populateTaskDetailModalFromRow(taskDetailContext);
        setTaskDetailEditMode(false);
      }

      showClientFlash("success", "Solicitação de ajuste removida.");
      return true;
    } catch (error) {
      showClientFlash(
        "error",
        error instanceof Error ? error.message : "Não foi possível remover a solicitação de ajuste."
      );
      return false;
    } finally {
      delete form.dataset.revisionRemoving;
    }
  };

  const saveTaskDetailModal = async () => {
    if (!taskDetailContext) return;
    if (taskDetailSaveInFlight) return;
    if (taskDetailContext.form.dataset.taskDetailHydrated !== "1") {
      try {
        await hydrateTaskDetailPayloadFromServer(taskDetailContext);
      } catch (_error) {
        // continua com os dados locais já exibidos
      }
    }
    if (!copyTaskDetailModalToRow(taskDetailContext)) return;
    taskDetailSaveInFlight = true;
    try {
      if (taskDetailSaveButton instanceof HTMLButtonElement) {
        taskDetailSaveButton.disabled = true;
        taskDetailSaveButton.classList.add("is-loading");
        taskDetailSaveButton.textContent = "Salvando";
      }

      if (taskDetailContext.form.dataset.autosaveSubmitting === "1") {
        const idle = await waitForFormAutosaveIdle(taskDetailContext.form);
        if (!idle) {
          return;
        }
      }

      const ok = await submitTaskAutosave(taskDetailContext.form);
      if (!ok) {
        return;
      }

      populateTaskDetailModalFromRow(taskDetailContext);
      setTaskDetailEditMode(false);
    } finally {
      taskDetailSaveInFlight = false;
      if (taskDetailSaveButton instanceof HTMLButtonElement) {
        taskDetailSaveButton.disabled = false;
        taskDetailSaveButton.classList.remove("is-loading");
        taskDetailSaveButton.textContent = "Salvar";
      }
    }
  };

  const syncBodyModalLock = () => {
    const hasOpenModal = [
      createTaskModal,
      getWorkspaceUsersModal(),
      workspaceCreateModal,
      createGroupModal,
      vaultGroupModal,
      vaultEntryModal,
      vaultEntryEditModal,
      dueGroupModal,
      dueEntryModal,
      dueEntryEditModal,
      inventoryGroupModal,
      inventoryEntryModal,
      inventoryEntryEditModal,
      taskDetailModal,
      taskReviewModal,
      taskImagePreviewModal,
      googleDriveBrowserModal,
      confirmModal,
      ...groupPermissionModals,
    ].some(
      (modal) => modal && !modal.hidden
    );
    document.body.classList.toggle("modal-open", hasOpenModal);
  };

  const closeConfirmModal = () => {
    if (!confirmModal) return;
    confirmModal.hidden = true;
    confirmModalAction = null;
    if (confirmModalSubmit instanceof HTMLButtonElement) {
      confirmModalSubmit.disabled = false;
      confirmModalSubmit.textContent = "Confirmar";
      confirmModalSubmit.classList.remove("is-loading");
    }
    syncBodyModalLock();
  };

  const openConfirmModal = ({
    title = "Confirmar",
    message = "Tem certeza?",
    confirmLabel = "Confirmar",
    confirmVariant = "default",
    onConfirm,
  }) => {
    if (!confirmModal) return;

    if (confirmModalTitle) confirmModalTitle.textContent = title;
    if (confirmModalMessage) confirmModalMessage.textContent = message;
    if (confirmModalSubmit instanceof HTMLButtonElement) {
      confirmModalSubmit.textContent = confirmLabel;
      confirmModalSubmit.disabled = false;
      confirmModalSubmit.classList.remove("is-loading", "btn-danger");
      if (confirmVariant === "danger") {
        confirmModalSubmit.classList.add("btn-danger");
      }
    }

    confirmModalAction = typeof onConfirm === "function" ? onConfirm : null;
    confirmModal.hidden = false;
    syncBodyModalLock();
  };

  const submitTaskHistoryForm = async (historyForm) => {
    if (!(historyForm instanceof HTMLFormElement)) return;
    if (historyForm.dataset.submitting === "1") return;

    const action = String(historyForm.dataset.taskHistoryAction || "").trim();
    const controls = document.querySelector("[data-task-history-controls]");
    const button =
      controls instanceof HTMLElement
        ? controls.querySelector(`[data-task-history-button="${action}"]`)
        : null;
    if (button instanceof HTMLButtonElement && button.disabled) return;

    historyForm.dataset.submitting = "1";
    if (button instanceof HTMLButtonElement) {
      button.disabled = true;
      button.classList.add("is-loading");
      button.setAttribute("aria-busy", "true");
    }

    try {
      const data = await postFormJson(historyForm);
      syncTaskHistoryControls(data.undo_state);
      if (taskDetailModal instanceof HTMLElement && !taskDetailModal.hidden) {
        closeTaskDetailModal();
      }
      await refreshTasksSectionFromServer();
      renderDashboardSummary(data.dashboard);
      showClientFlash("success", data.message || (action === "redo" ? "Acao refeita." : "Acao desfeita."));
    } catch (error) {
      showClientFlash(
        "error",
        error instanceof Error ? error.message : "Nao foi possivel aplicar esta acao."
      );
    } finally {
      delete historyForm.dataset.submitting;
      if (button instanceof HTMLButtonElement) {
        button.classList.remove("is-loading");
        button.removeAttribute("aria-busy");
      }
    }
  };

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches("[data-task-history-form]")) {
      return;
    }

    event.preventDefault();
    void submitTaskHistoryForm(form);
  });

  const submitDeleteTask = async (deleteForm) => {
    if (!(deleteForm instanceof HTMLFormElement)) return;
    if (deleteForm.dataset.submitting === "1") return;

    deleteForm.dataset.submitting = "1";
    try {
      const data = await postFormJson(deleteForm);

      const taskIdField = deleteForm.querySelector('[name="task_id"]');
      const taskId = taskIdField instanceof HTMLInputElement ? taskIdField.value : "";
      const taskItem =
        deleteForm.closest("[data-task-item]") ||
        (taskId ? document.getElementById(`task-${taskId}`) : null);

      const groupSection = taskItem?.closest("[data-task-group]");
      const removedActiveTask =
        taskDetailContext &&
        taskDetailContext.deleteForm === deleteForm;
      if (taskItem instanceof HTMLElement) {
        taskItem.remove();
      }
      if (removedActiveTask) {
        closeTaskDetailModal();
      }
      refreshTaskGroupSection(groupSection);
      adjustBoardSummaryCounts({ visible: -1, total: -1 });
      renderDashboardSummary(data.dashboard);
      syncTaskHistoryControls(data.undo_state);
      if (typeof syncTaskGroupInputs === "function") {
        syncTaskGroupInputs();
      }
      showClientFlash("success", "Tarefa removida.");
    } catch (error) {
      showClientFlash(
        "error",
        error instanceof Error ? error.message : "Falha ao remover tarefa."
      );
      throw error;
    } finally {
      delete deleteForm.dataset.submitting;
    }
  };

  const submitVaultEntryNameForm = async (renameForm) => {
    if (!(renameForm instanceof HTMLFormElement)) return;
    if (renameForm.dataset.submitting === "1") return;

    const labelInput = renameForm.querySelector("[data-vault-entry-label-input]");
    if (!(labelInput instanceof HTMLInputElement)) return;

    applyFirstLetterUppercaseToInput(labelInput);
    const nextLabel = (labelInput.value || "").trim();
    if (!nextLabel) {
      return;
    }

    const row = renameForm.closest("[data-vault-entry]");
    if (!(row instanceof HTMLElement)) return;
    const previousLabel = (row.dataset.entryLabel || "").trim();
    if (previousLabel !== "" && previousLabel === nextLabel) {
      return;
    }

    renameForm.dataset.submitting = "1";
    try {
      const data = await postFormJson(renameForm);
      const normalizedLabel = String(data?.label || nextLabel).trim() || nextLabel;
      row.dataset.entryLabel = normalizedLabel;
      labelInput.value = normalizedLabel;
    } catch (error) {
      labelInput.value = previousLabel || labelInput.value;
      showClientFlash(
        "error",
        error instanceof Error ? error.message : "Falha ao atualizar nome do acesso."
      );
    } finally {
      delete renameForm.dataset.submitting;
    }
  };

  const submitDeleteGroup = async (deleteForm) => {
    if (!(deleteForm instanceof HTMLFormElement)) return;
    if (deleteForm.dataset.submitting === "1") return;

    deleteForm.dataset.submitting = "1";
    try {
      const data = await postFormJson(deleteForm);

      const groupSection = deleteForm.closest("[data-task-group]");
      const groupName =
        groupSection?.dataset.groupName?.trim() ||
        deleteForm.querySelector('[name="group_name"]')?.value?.trim() ||
        "Grupo";
      const deletedTaskCount = Number.parseInt(data?.deleted_task_count, 10) || 0;
      const visibleTaskCountInGroup =
        groupSection instanceof HTMLElement
          ? groupSection.querySelectorAll("[data-task-item]").length
          : 0;

      if (groupSection instanceof HTMLElement) {
        if (
          taskDetailContext &&
          taskDetailContext.taskItem instanceof HTMLElement &&
          groupSection.contains(taskDetailContext.taskItem)
        ) {
          closeTaskDetailModal();
        }
        groupSection.remove();
      }
      clearStoredTaskGroupDoneHiddenState(groupName);

      if (deletedTaskCount > 0) {
        adjustBoardSummaryCounts({
          visible: -visibleTaskCountInGroup,
          total: -deletedTaskCount,
        });
      }
      renderDashboardSummary(data.dashboard);
      if (typeof syncTaskGroupInputs === "function") {
        syncTaskGroupInputs();
      }
      showClientFlash(
        "success",
        deletedTaskCount > 0
          ? `Grupo ${groupName} removido. ${deletedTaskCount} tarefa(s) excluida(s).`
          : `Grupo ${groupName} removido.`
      );
    } catch (error) {
      showClientFlash(
        "error",
        error instanceof Error ? error.message : "Falha ao remover grupo."
      );
      throw error;
    } finally {
      delete deleteForm.dataset.submitting;
    }
  };

  const submitRenameGroup = async (renameForm) => {
    if (!(renameForm instanceof HTMLFormElement)) return;
    if (renameForm.dataset.submitting === "1") return;

    const nameInput = renameForm.querySelector("[data-group-name-input]");
    const oldNameField = renameForm.querySelector('input[name="old_group_name"]');
    if (!(nameInput instanceof HTMLInputElement) || !(oldNameField instanceof HTMLInputElement)) {
      return;
    }

    applyFirstLetterUppercaseToInput(nameInput);
    const previousName = (oldNameField.value || "").trim() || "Grupo";
    const requestedName = (nameInput.value || "").trim();
    if (!requestedName) {
      nameInput.value = previousName;
      return;
    }
    if (requestedName === previousName) {
      return;
    }

    renameForm.dataset.submitting = "1";
    try {
      const data = await postFormJson(renameForm);
      const oldGroupName = (data.old_group_name || previousName).trim() || previousName;
      const nextGroupName = (data.group_name || requestedName).trim() || requestedName;
      const currentDefaultGroupName = document.body?.dataset?.defaultGroupName?.trim() || "";
      if (
        currentDefaultGroupName &&
        oldGroupName.localeCompare(currentDefaultGroupName, "pt-BR", { sensitivity: "base" }) === 0
      ) {
        document.body.dataset.defaultGroupName = nextGroupName;
      }
      replaceStoredTaskGroupName(oldGroupName, nextGroupName);
      replaceStoredTaskGroupDoneHiddenStateName(oldGroupName, nextGroupName);

      const groupSection = renameForm.closest("[data-task-group]");
      const dropzone = groupSection?.querySelector("[data-task-dropzone]");

      if (groupSection instanceof HTMLElement) {
        groupSection.dataset.groupName = nextGroupName;
      }
      if (dropzone instanceof HTMLElement) {
        dropzone.dataset.groupName = nextGroupName;
      }

      nameInput.value = nextGroupName;
      oldNameField.value = nextGroupName;

      const groupAddButtons = groupSection?.querySelectorAll("[data-open-create-task-modal][data-create-group]");
      groupAddButtons?.forEach((button) => {
        if (!(button instanceof HTMLElement)) return;
        button.dataset.createGroup = nextGroupName;
        button.setAttribute("aria-label", `Criar tarefa no grupo ${nextGroupName}`);
      });

      const deleteGroupNameField = groupSection?.querySelector(
        '.task-group-delete-form input[name="group_name"]'
      );
      if (deleteGroupNameField instanceof HTMLInputElement) {
        deleteGroupNameField.value = nextGroupName;
      }
      const deleteGroupButton = groupSection?.querySelector("[data-group-delete]");
      if (deleteGroupButton instanceof HTMLElement) {
        deleteGroupButton.setAttribute("aria-label", `Excluir grupo ${nextGroupName}`);
      }

      groupSection?.querySelectorAll("[data-task-item]").forEach((taskItem) => {
        if (!(taskItem instanceof HTMLElement)) return;
        taskItem.dataset.groupName = nextGroupName;

        const binding = getTaskGroupField(taskItem);
        const field = binding?.field;
        if (!(field instanceof HTMLSelectElement)) return;

        let optionUpdated = false;
        Array.from(field.options).forEach((option) => {
          if (option.value === oldGroupName) {
            option.value = nextGroupName;
            option.textContent = nextGroupName;
            optionUpdated = true;
          }
        });

        if (!optionUpdated && !Array.from(field.options).some((opt) => opt.value === nextGroupName)) {
          const option = document.createElement("option");
          option.value = nextGroupName;
          option.textContent = nextGroupName;
          field.append(option);
        }

        if (field.value === oldGroupName) {
          field.value = nextGroupName;
        }
      });

      if (taskDetailContext?.taskItem instanceof HTMLElement && groupSection?.contains(taskDetailContext.taskItem)) {
        populateTaskDetailModalFromRow(taskDetailContext);
      }

      if (groupSection instanceof HTMLElement) {
        refreshTaskGroupSection(groupSection);
      }

      if (typeof syncTaskGroupInputs === "function") {
        syncTaskGroupInputs();
      }

      showClientFlash("success", `Grupo renomeado para ${nextGroupName}.`);
    } catch (error) {
      nameInput.value = previousName;
      showClientFlash(
        "error",
        error instanceof Error ? error.message : "Falha ao renomear grupo."
      );
      throw error;
    } finally {
      delete renameForm.dataset.submitting;
    }
  };

  const collectGroupNames = () => {
    const names = [];
    const seen = new Set();
    const addName = (value) => {
      const text = String(value || "").trim();
      if (!text) return;
      const key = normalizeTaskGroupNameKey(text);
      if (seen.has(key)) return;
      seen.add(key);
      names.push(text);
    };

    const sections =
      taskGroupsListElement instanceof HTMLElement
        ? taskGroupsListElement.querySelectorAll("[data-task-group]")
        : document.querySelectorAll("[data-task-group]");
    sections.forEach((section) => {
      if (!(section instanceof HTMLElement)) return;
      const canAccess = (section.dataset.groupCanAccess || "1") !== "0";
      if (!canAccess) return;
      addName(section?.dataset?.groupName || "");
    });

    if (
      createTaskGroupInput &&
      createTaskGroupInput instanceof HTMLSelectElement
    ) {
      Array.from(createTaskGroupInput.options).forEach((option) => {
        addName(option.value || "");
      });
    }

    return names;
  };

  const syncTaskGroupInputs = () => {
    const groupNames = collectGroupNames();

    if (
      createTaskGroupInput &&
      createTaskGroupInput instanceof HTMLSelectElement
    ) {
      const currentValue = createTaskGroupInput.value;
      createTaskGroupInput.innerHTML = "";

      groupNames.forEach((groupName) => {
        const option = document.createElement("option");
        option.value = groupName;
        option.textContent = groupName;
        if (groupName === currentValue) option.selected = true;
        createTaskGroupInput.append(option);
      });

      if (
        currentValue &&
        !groupNames.some((name) => name === currentValue)
      ) {
        const option = document.createElement("option");
        option.value = currentValue;
        option.textContent = currentValue;
        option.selected = true;
        createTaskGroupInput.append(option);
      }

      if (!createTaskGroupInput.value && createTaskGroupInput.options.length) {
        createTaskGroupInput.value = groupNames[0] || getDefaultGroupName();
      }

      syncInlineSelectPicker(createTaskGroupInput);
    }

    if (taskGroupsDatalist) {
      taskGroupsDatalist.innerHTML = "";
      groupNames.forEach((groupName) => {
        const option = document.createElement("option");
        option.value = groupName;
        taskGroupsDatalist.append(option);
      });
    }
  };

  try {
    applyStoredTaskGroupOrder();
    syncTaskGroupInputs();
    setTaskGroupReorderMode(false);
    setCreateTaskSubtasks([]);
    renderCreateTaskSubtasksEditList();
    setTaskDetailEditSubtasks([]);
    renderTaskDetailSubtasksEditList();
    groupPermissionModals.forEach((modal) => syncGroupPermissionsModal(modal));
    document.querySelectorAll("[data-task-group]").forEach((section) => {
      setTaskGroupCollapsed(section, resolveInitialGroupCollapsedState("tasks", section), {
        persist: false,
      });
      setTaskGroupDoneHidden(section, resolveInitialTaskGroupDoneHiddenState(section), {
        persist: false,
        refresh: false,
      });
      refreshTaskGroupSection(section);
    });
    document.querySelectorAll("[data-vault-group]").forEach((section) => {
      setVaultGroupCollapsed(section, resolveInitialGroupCollapsedState("vault", section), {
        persist: false,
      });
    });
    document.querySelectorAll("[data-due-group]").forEach((section) => {
      setDueGroupCollapsed(section, resolveInitialGroupCollapsedState("dues", section), {
        persist: false,
      });
    });
    document.querySelectorAll("[data-inventory-group]").forEach((section) => {
      setInventoryGroupCollapsed(section, resolveInitialGroupCollapsedState("inventory", section), {
        persist: false,
      });
    });
    document.querySelectorAll("[data-vault-password-cell]").forEach((cell) => {
      syncVaultPasswordCell(cell, false);
    });
    document.querySelectorAll("[data-task-item]").forEach((taskItem) => {
      if (!(taskItem instanceof HTMLElement)) return;
      const titleTagField = taskItem.querySelector("[data-task-title-tag]");
      const titleTagColorField = taskItem.querySelector("[data-task-title-tag-color]");
      const titleTagValue =
        titleTagField instanceof HTMLInputElement ? titleTagField.value || "" : "";
      const titleTagColorValue =
        titleTagColorField instanceof HTMLInputElement ? titleTagColorField.value || "" : "";
      syncTaskTitleTagBadge(taskItem, titleTagValue, titleTagColorValue);
    });
  } catch (error) {
    console.error("[Bexon] Falha ao inicializar estado do dashboard.", error);
  }

  const openCreateModal = (groupName) => {
    if (!createTaskModal) return;
    setFabMenuOpen(false);
    syncTaskGroupInputs();
    if (createTaskGroupInput instanceof HTMLSelectElement && createTaskGroupInput.disabled) {
      return;
    }
    if (createTaskForm) {
      createTaskForm.reset();
      syncCreateTaskDescriptionEditorFromTextarea();
      createTaskImagePickerExpanded = false;
      setCreateTaskImageItems([]);
      setCreateTaskMediaPage(false);
      setCreateTaskReferenceLinks([]);
      setCreateTaskSubtasks([]);
      renderCreateTaskSubtasksEditList();
      closeInlineAddForm(createTaskLinkAddForm, createTaskLinkInput);
      closeInlineAddForm(createTaskSubtaskAddForm, createTaskSubtaskInput);
      createTaskForm
        .querySelectorAll(".assignee-picker")
        .forEach(updateAssigneePickerSummaryVisual);
      createTaskForm
        .querySelectorAll(".status-select, .priority-select")
        .forEach(syncSelectColor);
    }
    resetCreateTaskTitleTagPicker();
    if (createTaskGroupInput) {
      const nextGroup = (groupName || "").trim() || getDefaultGroupName();
      if (
        createTaskGroupInput instanceof HTMLSelectElement &&
        !Array.from(createTaskGroupInput.options).some(
          (option) => option.value === nextGroup
        )
      ) {
        const option = document.createElement("option");
        option.value = nextGroup;
        option.textContent = nextGroup;
        createTaskGroupInput.append(option);
      }
      createTaskGroupInput.value = nextGroup;
      if (createTaskGroupInput instanceof HTMLSelectElement) {
        syncInlineSelectPicker(createTaskGroupInput);
      }
    }
    createTaskModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      createTaskTitleInput?.focus();
    }, 20);
  };

  const closeCreateModal = () => {
    if (!createTaskModal) return;
    setCreateTaskMediaPage(false);
    createTaskModal.hidden = true;
    syncBodyModalLock();
  };

  const openWorkspaceCreateModal = () => {
    if (!(workspaceCreateModal instanceof HTMLElement)) return;
    if (workspaceCreateForm instanceof HTMLFormElement) {
      workspaceCreateForm.reset();
    }
    workspaceCreateModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      workspaceCreateNameInput?.focus();
    }, 20);
  };

  const closeWorkspaceCreateModal = () => {
    if (!(workspaceCreateModal instanceof HTMLElement)) return;
    workspaceCreateModal.hidden = true;
    syncBodyModalLock();
  };

  const openWorkspaceUsersModal = () => {
    const workspaceUsersModal = getWorkspaceUsersModal();
    if (!(workspaceUsersModal instanceof HTMLElement)) return;
    workspaceUsersModal.hidden = false;
    syncBodyModalLock();
  };

  const closeWorkspaceUsersModal = () => {
    const workspaceUsersModal = getWorkspaceUsersModal();
    if (!(workspaceUsersModal instanceof HTMLElement)) return;
    workspaceUsersModal.hidden = true;
    syncBodyModalLock();
  };

  const openCreateGroupModal = () => {
    if (!createGroupModal) return;
    setFabMenuOpen(false);
    if (createGroupForm) {
      createGroupForm.reset();
    }
    syncGroupPermissionsModal(createGroupModal);
    createGroupModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      createGroupNameInput?.focus();
    }, 20);
  };

  const closeCreateGroupModal = () => {
    if (!createGroupModal) return;
    createGroupModal.hidden = true;
    syncBodyModalLock();
  };

  const openGroupPermissionsModal = (modalKey = "") => {
    const key = String(modalKey || "").trim();
    if (!key) return;

    const modal = document.querySelector(
      `[data-group-permissions-modal="${CSS.escape(key)}"]`
    );
    if (!(modal instanceof HTMLElement)) return;

    syncGroupPermissionsModal(modal);
    modal.hidden = false;
    syncBodyModalLock();
  };

  const closeGroupPermissionsModal = (modalElement = null) => {
    if (modalElement instanceof HTMLElement) {
      modalElement.hidden = true;
      syncBodyModalLock();
      return;
    }

    groupPermissionModals.forEach((modal) => {
      if (!(modal instanceof HTMLElement) || modal.hidden) return;
      modal.hidden = true;
    });
    syncBodyModalLock();
  };

  const setVaultGroupSelectValue = (select, value) => {
    if (!(select instanceof HTMLSelectElement)) return;
    const next = (value || "").trim();
    if (!next) return;
    if (!Array.from(select.options).some((option) => option.value === next)) return;
    select.value = next;
  };

  const setDueGroupSelectValue = (select, value) => {
    if (!(select instanceof HTMLSelectElement)) return;
    const next = (value || "").trim();
    if (!next) return;
    if (!Array.from(select.options).some((option) => option.value === next)) return;
    select.value = next;
  };

  const setInventoryGroupSelectValue = (select, value) => {
    if (!(select instanceof HTMLSelectElement)) return;
    const next = (value || "").trim();
    if (!next) return;
    if (!Array.from(select.options).some((option) => option.value === next)) return;
    select.value = next;
  };

  const openVaultGroupModal = () => {
    if (!(vaultGroupModal instanceof HTMLElement)) return;
    if (vaultGroupForm instanceof HTMLFormElement) {
      vaultGroupForm.reset();
    }
    vaultGroupModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      vaultGroupNameInput?.focus();
    }, 20);
  };

  const closeVaultGroupModal = () => {
    if (!(vaultGroupModal instanceof HTMLElement)) return;
    vaultGroupModal.hidden = true;
    syncBodyModalLock();
  };

  const openVaultEntryModal = (groupName = "") => {
    if (!(vaultEntryModal instanceof HTMLElement)) return;
    if (vaultEntryGroupField instanceof HTMLSelectElement && vaultEntryGroupField.disabled) {
      return;
    }
    if (vaultEntryForm instanceof HTMLFormElement) {
      vaultEntryForm.reset();
    }
    if (vaultEntryGroupField instanceof HTMLSelectElement) {
      setVaultGroupSelectValue(vaultEntryGroupField, groupName);
    }
    vaultEntryModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      vaultEntryLabelField?.focus();
    }, 20);
  };

  const closeVaultEntryModal = () => {
    if (!(vaultEntryModal instanceof HTMLElement)) return;
    vaultEntryModal.hidden = true;
    syncBodyModalLock();
  };

  const openVaultEntryEditModalFromRow = (entryRow) => {
    if (!(entryRow instanceof HTMLElement)) return;
    if (!(vaultEntryEditModal instanceof HTMLElement)) return;
    if (!(vaultEntryEditForm instanceof HTMLFormElement)) return;
    if (vaultEntryEditGroupField instanceof HTMLSelectElement && vaultEntryEditGroupField.disabled) {
      return;
    }

    const entryId = (entryRow.dataset.entryId || "").trim();
    const labelInput = entryRow.querySelector("[data-vault-entry-label-input]");
    const label = labelInput instanceof HTMLInputElement ? labelInput.value : (entryRow.dataset.entryLabel || "");
    const login = entryRow.dataset.entryLogin || "";
    const password = entryRow.dataset.entryPassword || "";
    const passwordUnavailable = entryRow.dataset.entryPasswordUnavailable === "1";
    const groupName = entryRow.dataset.entryGroup || "";

    if (!(vaultEntryEditIdField instanceof HTMLInputElement)) return;
    vaultEntryEditForm.reset();
    vaultEntryEditIdField.value = entryId;
    if (vaultEntryEditLabelField instanceof HTMLInputElement) {
      vaultEntryEditLabelField.value = label;
    }
    if (vaultEntryEditLoginField instanceof HTMLInputElement) {
      vaultEntryEditLoginField.value = login;
    }
    if (vaultEntryEditPasswordField instanceof HTMLInputElement) {
      vaultEntryEditPasswordField.value = password;
      vaultEntryEditPasswordField.placeholder = passwordUnavailable
        ? "Informe uma nova senha para substituir"
        : "";
    }
    if (vaultEntryEditPasswordUnavailableField instanceof HTMLInputElement) {
      vaultEntryEditPasswordUnavailableField.value = passwordUnavailable ? "1" : "0";
    }
    if (vaultEntryEditGroupField instanceof HTMLSelectElement) {
      setVaultGroupSelectValue(vaultEntryEditGroupField, groupName);
    }

    vaultEntryEditModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      vaultEntryEditLabelField?.focus();
    }, 20);
  };

  const closeVaultEntryEditModal = () => {
    if (!(vaultEntryEditModal instanceof HTMLElement)) return;
    vaultEntryEditModal.hidden = true;
    syncBodyModalLock();
  };

  const openDueGroupModal = () => {
    if (!(dueGroupModal instanceof HTMLElement)) return;
    if (dueGroupForm instanceof HTMLFormElement) {
      dueGroupForm.reset();
    }
    dueGroupModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      dueGroupNameInput?.focus();
    }, 20);
  };

  const closeDueGroupModal = () => {
    if (!(dueGroupModal instanceof HTMLElement)) return;
    dueGroupModal.hidden = true;
    syncBodyModalLock();
  };

  const todayIsoDate = () => new Date().toISOString().slice(0, 10);

  const normalizeDueMonthlyDayInput = (value) => {
    const parsed = Number.parseInt(String(value || "").trim(), 10);
    if (!Number.isFinite(parsed)) return "";
    return String(Math.min(31, Math.max(1, parsed)));
  };

  const monthlyDayFromIsoDate = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "";
    const parts = raw.split("-");
    if (parts.length !== 3) return "";
    const parsedDay = Number.parseInt(parts[2] || "", 10);
    if (!Number.isFinite(parsedDay)) return "";
    return normalizeDueMonthlyDayInput(String(parsedDay));
  };

  const monthFromIsoDate = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "";
    const parts = raw.split("-");
    if (parts.length !== 3) return "";
    const parsedMonth = Number.parseInt(parts[1] || "", 10);
    if (!Number.isFinite(parsedMonth)) return "";
    if (parsedMonth < 1 || parsedMonth > 12) return "";
    return String(parsedMonth).padStart(2, "0");
  };

  const daysInMonth = (year, month) => {
    const safeYear = Number.isFinite(year) ? year : new Date().getFullYear();
    const safeMonth = Number.isFinite(month) ? month : 1;
    return new Date(safeYear, safeMonth, 0).getDate();
  };

  const normalizeAnnualMonthInput = (value) => {
    const parsed = Number.parseInt(String(value || "").trim(), 10);
    if (!Number.isFinite(parsed)) return "";
    if (parsed < 1 || parsed > 12) return "";
    return String(parsed).padStart(2, "0");
  };

  const normalizeAnnualDayInput = (dayValue, monthValue, yearValue = null) => {
    const month = Number.parseInt(String(monthValue || "").trim(), 10);
    if (!Number.isFinite(month) || month < 1 || month > 12) return "";

    const year =
      yearValue === null || !Number.isFinite(Number(yearValue))
        ? new Date().getFullYear()
        : Number.parseInt(String(yearValue), 10);

    const parsedDay = Number.parseInt(String(dayValue || "").trim(), 10);
    if (!Number.isFinite(parsedDay)) return "";

    const maxDay = daysInMonth(year, month);
    return String(Math.max(1, Math.min(maxDay, parsedDay))).padStart(2, "0");
  };

  const composeAnnualIsoDate = (monthValue, dayValue, yearValue = null) => {
    const month = normalizeAnnualMonthInput(monthValue);
    if (!month) return "";
    const year =
      yearValue === null || !Number.isFinite(Number(yearValue))
        ? new Date().getFullYear()
        : Number.parseInt(String(yearValue), 10);
    const day = normalizeAnnualDayInput(dayValue, month, year);
    if (!day) return "";
    return `${String(year)}-${month}-${day}`;
  };

  const centsToCurrencyInputValue = (value) => {
    const parsed = Number.parseInt(String(value || "").trim(), 10);
    if (!Number.isFinite(parsed) || parsed < 0) return "";
    return (parsed / 100).toFixed(2);
  };

  const syncDueRecurrenceFields = ({
    recurrenceField,
    monthlyWrap,
    monthlyDayField,
    fixedWrap,
    fixedDateField,
    annualWrap,
    annualMonthField,
    annualDayField,
    dueDateField,
  }) => {
    const recurrenceValue =
      recurrenceField instanceof HTMLSelectElement
        ? String(recurrenceField.value || "monthly").trim().toLowerCase()
        : "monthly";
    const isMonthly = recurrenceValue === "monthly";
    const isAnnual = recurrenceValue === "annual";
    const isFixed = recurrenceValue === "fixed";

    if (monthlyWrap instanceof HTMLElement) {
      monthlyWrap.hidden = !isMonthly;
    }
    if (fixedWrap instanceof HTMLElement) {
      fixedWrap.hidden = !isFixed;
    }
    if (annualWrap instanceof HTMLElement) {
      annualWrap.hidden = !isAnnual;
    }

    if (monthlyDayField instanceof HTMLInputElement) {
      monthlyDayField.disabled = !isMonthly;
      monthlyDayField.required = isMonthly;

      const normalizedDay = normalizeDueMonthlyDayInput(monthlyDayField.value);
      if (normalizedDay) {
        monthlyDayField.value = normalizedDay;
      }

      if (isMonthly && !monthlyDayField.value) {
        const dayFromDate = dueDateField instanceof HTMLInputElement ? monthlyDayFromIsoDate(dueDateField.value) : "";
        monthlyDayField.value = dayFromDate || String(new Date().getDate());
      }
    }

    if (fixedDateField instanceof HTMLInputElement) {
      fixedDateField.disabled = !isFixed;
      fixedDateField.required = isFixed;
      if (isFixed && !fixedDateField.value) {
        const fallbackDate = dueDateField instanceof HTMLInputElement ? String(dueDateField.value || "") : "";
        fixedDateField.value = fallbackDate || todayIsoDate();
      }
    }

    if (annualMonthField instanceof HTMLSelectElement) {
      annualMonthField.disabled = !isAnnual;
      annualMonthField.required = isAnnual;
      const normalizedMonth = normalizeAnnualMonthInput(annualMonthField.value);
      if (normalizedMonth) {
        annualMonthField.value = normalizedMonth;
      } else if (isAnnual) {
        const fallbackMonth = dueDateField instanceof HTMLInputElement ? monthFromIsoDate(dueDateField.value) : "";
        annualMonthField.value = fallbackMonth || String(new Date().getMonth() + 1).padStart(2, "0");
      }
    }

    if (annualDayField instanceof HTMLInputElement) {
      annualDayField.disabled = !isAnnual;
      annualDayField.required = isAnnual;

      if (isAnnual) {
        const selectedMonth =
          annualMonthField instanceof HTMLSelectElement ? annualMonthField.value : "";
        const fallbackDay = dueDateField instanceof HTMLInputElement ? monthlyDayFromIsoDate(dueDateField.value) : "";
        const daySource = annualDayField.value || fallbackDay || String(new Date().getDate());
        annualDayField.value = normalizeAnnualDayInput(daySource, selectedMonth) || "";
      }
    }

    if (dueDateField instanceof HTMLInputElement) {
      if (isFixed) {
        dueDateField.value = fixedDateField instanceof HTMLInputElement ? String(fixedDateField.value || "") : "";
      } else if (isAnnual) {
        const monthValue = annualMonthField instanceof HTMLSelectElement ? annualMonthField.value : "";
        const dayValue = annualDayField instanceof HTMLInputElement ? annualDayField.value : "";
        dueDateField.value = composeAnnualIsoDate(monthValue, dayValue);
      } else {
        dueDateField.value = "";
      }
    }
  };

  const syncDueCreateRecurrenceFields = () => {
    syncDueRecurrenceFields({
      recurrenceField: dueEntryRecurrenceField,
      monthlyWrap: dueEntryMonthlyWrap,
      monthlyDayField: dueEntryMonthlyDayField,
      fixedWrap: dueEntryFixedWrap,
      fixedDateField: dueEntryFixedDateField,
      annualWrap: dueEntryAnnualWrap,
      annualMonthField: dueEntryAnnualMonthField,
      annualDayField: dueEntryAnnualDayField,
      dueDateField: dueEntryDateField,
    });
  };

  const syncDueEditRecurrenceFields = () => {
    syncDueRecurrenceFields({
      recurrenceField: dueEntryEditRecurrenceField,
      monthlyWrap: dueEntryEditMonthlyWrap,
      monthlyDayField: dueEntryEditMonthlyDayField,
      fixedWrap: dueEntryEditFixedWrap,
      fixedDateField: dueEntryEditFixedDateField,
      annualWrap: dueEntryEditAnnualWrap,
      annualMonthField: dueEntryEditAnnualMonthField,
      annualDayField: dueEntryEditAnnualDayField,
      dueDateField: dueEntryEditDateField,
    });
  };

  const openDueEntryModal = (groupName = "") => {
    if (!(dueEntryModal instanceof HTMLElement)) return;
    if (dueEntryGroupField instanceof HTMLSelectElement && dueEntryGroupField.disabled) {
      return;
    }
    if (dueEntryForm instanceof HTMLFormElement) {
      dueEntryForm.reset();
    }
    if (dueEntryGroupField instanceof HTMLSelectElement) {
      setDueGroupSelectValue(dueEntryGroupField, groupName);
    }
    if (dueEntryAmountField instanceof HTMLInputElement) {
      dueEntryAmountField.value = "";
    }
    if (dueEntryRecurrenceField instanceof HTMLSelectElement) {
      dueEntryRecurrenceField.value = "monthly";
    }
    if (dueEntryMonthlyDayField instanceof HTMLInputElement) {
      dueEntryMonthlyDayField.value = String(new Date().getDate());
    }
    if (dueEntryFixedDateField instanceof HTMLInputElement) {
      setIsoDateInputValue(dueEntryFixedDateField, todayIsoDate());
    }
    if (dueEntryAnnualMonthField instanceof HTMLSelectElement) {
      dueEntryAnnualMonthField.value = String(new Date().getMonth() + 1).padStart(2, "0");
    }
    if (dueEntryAnnualDayField instanceof HTMLInputElement) {
      dueEntryAnnualDayField.value = String(new Date().getDate()).padStart(2, "0");
    }
    if (dueEntryDateField instanceof HTMLInputElement) {
      dueEntryDateField.value = "";
    }
    syncDueCreateRecurrenceFields();
    dueEntryModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      dueEntryLabelField?.focus();
    }, 20);
  };

  const closeDueEntryModal = () => {
    if (!(dueEntryModal instanceof HTMLElement)) return;
    dueEntryModal.hidden = true;
    syncBodyModalLock();
  };

  const openDueEntryEditModalFromRow = (entryRow) => {
    if (!(entryRow instanceof HTMLElement)) return;
    if (!(dueEntryEditModal instanceof HTMLElement)) return;
    if (!(dueEntryEditForm instanceof HTMLFormElement)) return;
    if (dueEntryEditGroupField instanceof HTMLSelectElement && dueEntryEditGroupField.disabled) {
      return;
    }

    const entryId = (entryRow.dataset.entryId || "").trim();
    const label = (entryRow.dataset.entryLabel || "").trim();
    const dueDate = (entryRow.dataset.entryDate || "").trim();
    const recurrenceType = (entryRow.dataset.entryRecurrenceType || "monthly").trim().toLowerCase();
    const monthlyDayRaw = (entryRow.dataset.entryMonthlyDay || "").trim();
    const amountCents = (entryRow.dataset.entryAmountCents || "").trim();
    const groupName = (entryRow.dataset.entryGroup || "").trim();

    if (!(dueEntryEditIdField instanceof HTMLInputElement)) return;
    dueEntryEditForm.reset();
    dueEntryEditIdField.value = entryId;
    if (dueEntryEditLabelField instanceof HTMLInputElement) {
      dueEntryEditLabelField.value = label;
    }
    if (dueEntryEditAmountField instanceof HTMLInputElement) {
      dueEntryEditAmountField.value = centsToCurrencyInputValue(amountCents);
    }
    if (dueEntryEditRecurrenceField instanceof HTMLSelectElement) {
      if (recurrenceType === "annual") {
        dueEntryEditRecurrenceField.value = "annual";
      } else if (recurrenceType === "fixed") {
        dueEntryEditRecurrenceField.value = "fixed";
      } else {
        dueEntryEditRecurrenceField.value = "monthly";
      }
    }
    if (dueEntryEditMonthlyDayField instanceof HTMLInputElement) {
      const normalizedDay = normalizeDueMonthlyDayInput(monthlyDayRaw);
      dueEntryEditMonthlyDayField.value = normalizedDay || monthlyDayFromIsoDate(dueDate) || "";
    }
    if (dueEntryEditFixedDateField instanceof HTMLInputElement) {
      setIsoDateInputValue(dueEntryEditFixedDateField, dueDate);
    }
    if (dueEntryEditAnnualMonthField instanceof HTMLSelectElement) {
      const monthValue = monthFromIsoDate(dueDate);
      if (monthValue) {
        dueEntryEditAnnualMonthField.value = monthValue;
      }
    }
    if (dueEntryEditAnnualDayField instanceof HTMLInputElement) {
      dueEntryEditAnnualDayField.value = monthlyDayFromIsoDate(dueDate) || "";
    }
    if (dueEntryEditDateField instanceof HTMLInputElement) {
      dueEntryEditDateField.value = dueDate;
    }
    if (dueEntryEditGroupField instanceof HTMLSelectElement) {
      setDueGroupSelectValue(dueEntryEditGroupField, groupName);
    }
    syncDueEditRecurrenceFields();

    dueEntryEditModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      dueEntryEditLabelField?.focus();
    }, 20);
  };

  const closeDueEntryEditModal = () => {
    if (!(dueEntryEditModal instanceof HTMLElement)) return;
    dueEntryEditModal.hidden = true;
    syncBodyModalLock();
  };

  const openInventoryGroupModal = () => {
    if (!(inventoryGroupModal instanceof HTMLElement)) return;
    if (inventoryGroupForm instanceof HTMLFormElement) {
      inventoryGroupForm.reset();
    }
    inventoryGroupModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      inventoryGroupNameInput?.focus();
    }, 20);
  };

  const closeInventoryGroupModal = () => {
    if (!(inventoryGroupModal instanceof HTMLElement)) return;
    inventoryGroupModal.hidden = true;
    syncBodyModalLock();
  };

  const openInventoryEntryModal = (groupName = "") => {
    if (!(inventoryEntryModal instanceof HTMLElement)) return;
    if (inventoryEntryGroupField instanceof HTMLSelectElement && inventoryEntryGroupField.disabled) {
      return;
    }
    if (inventoryEntryForm instanceof HTMLFormElement) {
      inventoryEntryForm.reset();
    }
    if (inventoryEntryGroupField instanceof HTMLSelectElement) {
      setInventoryGroupSelectValue(inventoryEntryGroupField, groupName);
    }
    if (inventoryEntryQuantityField instanceof HTMLInputElement) {
      inventoryEntryQuantityField.value = "1";
    }
    if (inventoryEntryUnitField instanceof HTMLInputElement) {
      inventoryEntryUnitField.value = "un";
    }
    if (inventoryEntryMinQuantityField instanceof HTMLInputElement) {
      inventoryEntryMinQuantityField.value = "";
    }
    if (inventoryEntryNotesField instanceof HTMLTextAreaElement) {
      inventoryEntryNotesField.value = "";
    }

    inventoryEntryModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      inventoryEntryLabelField?.focus();
    }, 20);
  };

  const closeInventoryEntryModal = () => {
    if (!(inventoryEntryModal instanceof HTMLElement)) return;
    inventoryEntryModal.hidden = true;
    syncBodyModalLock();
  };

  const openInventoryEntryEditModalFromRow = (entryRow) => {
    if (!(entryRow instanceof HTMLElement)) return;
    if (!(inventoryEntryEditModal instanceof HTMLElement)) return;
    if (!(inventoryEntryEditForm instanceof HTMLFormElement)) return;
    if (
      inventoryEntryEditGroupField instanceof HTMLSelectElement &&
      inventoryEntryEditGroupField.disabled
    ) {
      return;
    }

    const entryId = (entryRow.dataset.entryId || "").trim();
    const label = (entryRow.dataset.entryLabel || "").trim();
    const quantityValue = (entryRow.dataset.entryQuantityValue || "").trim();
    const minQuantityValue = (entryRow.dataset.entryMinQuantityValue || "").trim();
    const unitLabel = (entryRow.dataset.entryUnitLabel || "").trim();
    const groupName = (entryRow.dataset.entryGroup || "").trim();
    const notes = (entryRow.dataset.entryNotes || "").trim();

    if (!(inventoryEntryEditIdField instanceof HTMLInputElement)) return;
    inventoryEntryEditForm.reset();
    inventoryEntryEditIdField.value = entryId;
    if (inventoryEntryEditLabelField instanceof HTMLInputElement) {
      inventoryEntryEditLabelField.value = label;
    }
    if (inventoryEntryEditQuantityField instanceof HTMLInputElement) {
      inventoryEntryEditQuantityField.value = quantityValue;
    }
    if (inventoryEntryEditMinQuantityField instanceof HTMLInputElement) {
      inventoryEntryEditMinQuantityField.value = minQuantityValue;
    }
    if (inventoryEntryEditUnitField instanceof HTMLInputElement) {
      inventoryEntryEditUnitField.value = unitLabel || "un";
    }
    if (inventoryEntryEditNotesField instanceof HTMLTextAreaElement) {
      inventoryEntryEditNotesField.value = notes;
    }
    if (inventoryEntryEditGroupField instanceof HTMLSelectElement) {
      setInventoryGroupSelectValue(inventoryEntryEditGroupField, groupName);
    }

    inventoryEntryEditModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      inventoryEntryEditLabelField?.focus();
    }, 20);
  };

  const closeInventoryEntryEditModal = () => {
    if (!(inventoryEntryEditModal instanceof HTMLElement)) return;
    inventoryEntryEditModal.hidden = true;
    syncBodyModalLock();
  };

  syncDueCreateRecurrenceFields();
  syncDueEditRecurrenceFields();

  const copyTextToClipboard = async (value) => {
    const text = String(value || "");
    if (text.trim() === "") return false;

    if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
      await navigator.clipboard.writeText(text);
      return true;
    }

    const helper = document.createElement("textarea");
    helper.value = text;
    helper.setAttribute("readonly", "readonly");
    helper.style.position = "fixed";
    helper.style.opacity = "0";
    document.body.append(helper);
    helper.focus();
    helper.select();
    const ok = document.execCommand("copy");
    helper.remove();
    return ok;
  };

  function maskedVaultPassword(value) {
    const text = String(value || "");
    return text ? "\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022" : "-";
  }

  function syncVaultPasswordCell(cell, show) {
    if (!(cell instanceof HTMLElement)) return;
    const value = cell.dataset.passwordValue || "";
    const visible = Boolean(show) && value !== "";
    const textEl = cell.querySelector("[data-vault-password-text]");
    const toggleButton = cell.querySelector("[data-vault-toggle-password]");

    cell.dataset.visible = visible ? "true" : "false";
    if (textEl instanceof HTMLElement) {
      textEl.textContent = visible ? value : maskedVaultPassword(value);
    }
    if (toggleButton instanceof HTMLButtonElement) {
      toggleButton.setAttribute("aria-label", visible ? "Ocultar senha" : "Mostrar senha");
      toggleButton.classList.toggle("is-active", visible);
    }
  }

  document.addEventListener("click", (event) => {
    const target =
      event.target instanceof Element ? event.target : event.target?.parentElement;
    if (!(target instanceof Element)) return;
    closeOpenWorkspaceSidebarPickers(target);

    if (
      fabWrap &&
      fabToggleButton &&
      target.closest("[data-task-fab-toggle]")
    ) {
      setFabMenuOpen(!fabWrap.classList.contains("is-open"));
      return;
    }

    if (fabWrap && fabWrap.classList.contains("is-open") && !target.closest("[data-task-fab-wrap]")) {
      setFabMenuOpen(false);
    }

    const toggleTaskGroupReorder = target.closest("[data-toggle-task-group-reorder]");
    if (toggleTaskGroupReorder instanceof HTMLElement) {
      setTaskGroupReorderMode(!taskGroupReorderMode);
      return;
    }

    const mobileSidebarToggle = target.closest("[data-mobile-sidebar-toggle]");
    if (mobileSidebarToggle instanceof HTMLElement) {
      setMobileSidebarOpen(!isMobileSidebarOpen());
      return;
    }

    if (
      isMobileSidebarOpen() &&
      !(target.closest(".users-sidebar") instanceof HTMLElement)
    ) {
      setMobileSidebarOpen(false);
    }

    const sidebarToolsAddButton = target.closest("[data-sidebar-tools-add-button]");
    if (sidebarToolsAddButton instanceof HTMLButtonElement) {
      const form = sidebarToolsAddButton.closest("[data-sidebar-tools-form]");
      if (!(form instanceof HTMLFormElement)) return;
      const addSelect = form.querySelector("[data-sidebar-tools-add-select]");
      if (!(addSelect instanceof HTMLSelectElement)) return;

      const toolToAdd = normalizeWorkspaceSidebarToolCandidate(addSelect.value);
      if (!toolToAdd) return;

      const alreadyExists = workspaceSidebarToolRows(form).some(
        (row) => normalizeWorkspaceSidebarToolCandidate(row.dataset.sidebarToolKey || "") === toolToAdd
      );
      if (alreadyExists) {
        syncWorkspaceSidebarToolsFormState(form);
        return;
      }

      const nextRow = createWorkspaceSidebarToolRow(form, toolToAdd);
      const list = form.querySelector("[data-sidebar-tools-list]");
      if (nextRow instanceof HTMLElement && list instanceof HTMLElement) {
        list.appendChild(nextRow);
      }
      syncWorkspaceSidebarToolsFormState(form);
      if (form.dataset.sidebarToolsAutosaveAdd === "1") {
        if (typeof form.requestSubmit === "function") {
          form.requestSubmit();
        } else {
          form.submit();
        }
      }
      return;
    }

    const sidebarToolsMoveButton = target.closest("[data-sidebar-tools-move]");
    if (sidebarToolsMoveButton instanceof HTMLButtonElement) {
      const row = sidebarToolsMoveButton.closest("[data-sidebar-tool-key]");
      const form = sidebarToolsMoveButton.closest("[data-sidebar-tools-form]");
      const direction = String(sidebarToolsMoveButton.dataset.sidebarToolsMove || "").trim();
      if (!(row instanceof HTMLElement) || !(form instanceof HTMLFormElement)) return;

      if (direction === "up" && row.previousElementSibling instanceof HTMLElement) {
        row.parentElement?.insertBefore(row, row.previousElementSibling);
      } else if (direction === "down" && row.nextElementSibling instanceof HTMLElement) {
        row.parentElement?.insertBefore(row.nextElementSibling, row);
      }

      syncWorkspaceSidebarToolsFormState(form);
      return;
    }

    const sidebarToolsRemoveButton = target.closest("[data-sidebar-tools-remove]");
    if (sidebarToolsRemoveButton instanceof HTMLButtonElement) {
      const row = sidebarToolsRemoveButton.closest("[data-sidebar-tool-key]");
      const form = sidebarToolsRemoveButton.closest("[data-sidebar-tools-form]");
      if (!(row instanceof HTMLElement) || !(form instanceof HTMLFormElement)) return;
      row.remove();
      syncWorkspaceSidebarToolsFormState(form);
      return;
    }

    const dashboardViewToggle = target.closest("[data-dashboard-view-toggle]");
    if (dashboardViewToggle instanceof HTMLElement) {
      const targetViewCandidate = normalizeDashboardViewCandidate(
        dashboardViewToggle.dataset.view || ""
      );
      if (!targetViewCandidate || !dashboardViews.has(targetViewCandidate)) {
        return;
      }
      const targetView = normalizeDashboardView(targetViewCandidate);
      setDashboardView(targetView, { updateUrl: true });
      if (isMobileSidebarOpen()) {
        setMobileSidebarOpen(false);
      }
      return;
    }

    const openWorkspaceCreateTrigger = target.closest("[data-open-workspace-create-modal]");
    if (openWorkspaceCreateTrigger) {
      openWorkspaceCreateModal();
      return;
    }

    const openWorkspaceUsersTrigger = target.closest("[data-open-workspace-users-modal]");
    if (openWorkspaceUsersTrigger) {
      openWorkspaceUsersModal();
      return;
    }

    const openVaultGroupTrigger = target.closest("[data-open-vault-group-modal]");
    if (openVaultGroupTrigger) {
      openVaultGroupModal();
      return;
    }

    const openDueGroupTrigger = target.closest("[data-open-due-group-modal]");
    if (openDueGroupTrigger) {
      openDueGroupModal();
      return;
    }

    const openInventoryGroupTrigger = target.closest("[data-open-inventory-group-modal]");
    if (openInventoryGroupTrigger) {
      openInventoryGroupModal();
      return;
    }

    const openVaultEntryTrigger = target.closest("[data-open-vault-entry-modal]");
    if (openVaultEntryTrigger instanceof HTMLElement) {
      openVaultEntryModal((openVaultEntryTrigger.dataset.createGroup || "").trim());
      return;
    }

    const openDueEntryTrigger = target.closest("[data-open-due-entry-modal]");
    if (openDueEntryTrigger instanceof HTMLElement) {
      openDueEntryModal((openDueEntryTrigger.dataset.createGroup || "").trim());
      return;
    }

    const openInventoryEntryTrigger = target.closest("[data-open-inventory-entry-modal]");
    if (openInventoryEntryTrigger instanceof HTMLElement) {
      openInventoryEntryModal((openInventoryEntryTrigger.dataset.createGroup || "").trim());
      return;
    }

    const openVaultEditTrigger = target.closest("[data-open-vault-edit-modal]");
    if (openVaultEditTrigger instanceof HTMLElement) {
      const row = openVaultEditTrigger.closest("[data-vault-entry]");
      openVaultEntryEditModalFromRow(row);
      return;
    }

    const openDueEditTrigger = target.closest("[data-open-due-edit-modal]");
    if (openDueEditTrigger instanceof HTMLElement) {
      const row = openDueEditTrigger.closest("[data-due-entry]");
      openDueEntryEditModalFromRow(row);
      return;
    }

    const openInventoryEditTrigger = target.closest("[data-open-inventory-edit-modal]");
    if (openInventoryEditTrigger instanceof HTMLElement) {
      const row = openInventoryEditTrigger.closest("[data-inventory-entry]");
      openInventoryEntryEditModalFromRow(row);
      return;
    }

    const vaultCopyTrigger = target.closest("[data-vault-copy]");
    if (vaultCopyTrigger instanceof HTMLButtonElement) {
      const text = vaultCopyTrigger.dataset.vaultCopy || "";
      void copyTextToClipboard(text)
        .then((ok) => {
          if (ok) {
            showClientFlash("success", "Copiado.");
          }
        })
        .catch(() => {});
      return;
    }

    const vaultPasswordToggleTrigger = target.closest("[data-vault-toggle-password]");
    if (vaultPasswordToggleTrigger instanceof HTMLButtonElement) {
      const cell = vaultPasswordToggleTrigger.closest("[data-vault-password-cell]");
      if (cell instanceof HTMLElement) {
        const visible = (cell.dataset.visible || "false") === "true";
        syncVaultPasswordCell(cell, !visible);
      }
      return;
    }

    const vaultDeleteTrigger = target.closest("[data-vault-delete-entry]");
    if (vaultDeleteTrigger instanceof HTMLElement) {
      const formId = vaultDeleteTrigger.dataset.deleteFormId || "";
      const deleteForm = formId ? document.getElementById(formId) : null;
      const row = vaultDeleteTrigger.closest("[data-vault-entry]");
      const labelInput = row?.querySelector("[data-vault-entry-label-input]");
      const label =
        (labelInput instanceof HTMLInputElement ? labelInput.value : row?.dataset?.entryLabel || "").trim() ||
        "este dado de acesso";

      if (deleteForm instanceof HTMLFormElement) {
        openConfirmModal({
          title: "Excluir dado de acesso",
          message: `Remover ${label}?`,
          confirmLabel: "Excluir",
          confirmVariant: "danger",
          onConfirm: async () => {
            await submitVaultActionForm(deleteForm, {
              successMessage: "Item removido do cofre.",
              fallbackError: "Falha ao remover item do cofre.",
            });
          },
        });
      }
      return;
    }

    const dueDeleteTrigger = target.closest("[data-due-delete-entry]");
    if (dueDeleteTrigger instanceof HTMLElement) {
      const formId = dueDeleteTrigger.dataset.deleteFormId || "";
      const deleteForm = formId ? document.getElementById(formId) : null;
      const row = dueDeleteTrigger.closest("[data-due-entry]");
      const label = (row?.dataset?.entryLabel || "").trim() || "este vencimento";

      if (deleteForm instanceof HTMLFormElement) {
        openConfirmModal({
          title: "Excluir vencimento",
          message: `Remover ${label}?`,
          confirmLabel: "Excluir",
          confirmVariant: "danger",
          onConfirm: async () => {
            await submitDueActionForm(deleteForm, {
              successMessage: "Vencimento removido.",
              fallbackError: "Falha ao remover vencimento.",
            });
          },
        });
      }
      return;
    }

    const inventoryDeleteTrigger = target.closest("[data-inventory-delete-entry]");
    if (inventoryDeleteTrigger instanceof HTMLElement) {
      const formId = inventoryDeleteTrigger.dataset.deleteFormId || "";
      const deleteForm = formId ? document.getElementById(formId) : null;
      const row = inventoryDeleteTrigger.closest("[data-inventory-entry]");
      const label = (row?.dataset?.entryLabel || "").trim() || "este item de estoque";

      if (deleteForm instanceof HTMLFormElement) {
        openConfirmModal({
          title: "Excluir item de estoque",
          message: `Remover ${label}?`,
          confirmLabel: "Excluir",
          confirmVariant: "danger",
          onConfirm: async () => {
            await submitInventoryActionForm(deleteForm, {
              successMessage: "Item de estoque removido.",
              fallbackError: "Falha ao remover item de estoque.",
            });
          },
        });
      }
      return;
    }

    const vaultGroupHeadToggle = target.closest("[data-vault-group-head-toggle]");
    if (vaultGroupHeadToggle instanceof HTMLElement) {
      if (!isGroupHeadToggleTargetBlocked(target, vaultGroupHeadToggle)) {
        const groupSection = vaultGroupHeadToggle.closest("[data-vault-group]");
        if (groupSection instanceof HTMLElement) {
          const shouldCollapse = !groupSection.classList.contains("is-collapsed");
          setVaultGroupCollapsed(groupSection, shouldCollapse);
        }
        return;
      }
    }

    const dueGroupHeadToggle = target.closest("[data-due-group-head-toggle]");
    if (dueGroupHeadToggle instanceof HTMLElement) {
      if (!isGroupHeadToggleTargetBlocked(target, dueGroupHeadToggle)) {
        const groupSection = dueGroupHeadToggle.closest("[data-due-group]");
        if (groupSection instanceof HTMLElement) {
          const shouldCollapse = !groupSection.classList.contains("is-collapsed");
          setDueGroupCollapsed(groupSection, shouldCollapse);
        }
        return;
      }
    }

    const inventoryGroupHeadToggle = target.closest("[data-inventory-group-head-toggle]");
    if (inventoryGroupHeadToggle instanceof HTMLElement) {
      if (!isGroupHeadToggleTargetBlocked(target, inventoryGroupHeadToggle)) {
        const groupSection = inventoryGroupHeadToggle.closest("[data-inventory-group]");
        if (groupSection instanceof HTMLElement) {
          const shouldCollapse = !groupSection.classList.contains("is-collapsed");
          setInventoryGroupCollapsed(groupSection, shouldCollapse);
        }
        return;
      }
    }

    const openTaskTrigger = target.closest("[data-open-create-task-modal]");
    if (openTaskTrigger) {
      openCreateModal(openTaskTrigger.dataset.createGroup || getDefaultGroupName());
      return;
    }

    const previewImageTrigger = target.closest("[data-task-ref-image-preview]");
    if (previewImageTrigger instanceof HTMLElement) {
      const previewIndex = Number.parseInt(
        String(previewImageTrigger.dataset.taskRefImageIndex || "-1"),
        10
      );
      openTaskImagePreview({
        src: previewImageTrigger.dataset.taskRefImagePreview || "",
        items: taskDetailViewPreviewItems,
        index: Number.isFinite(previewIndex) && previewIndex >= 0 ? previewIndex : 0,
      });
      return;
    }

    const openGroupTrigger = target.closest("[data-open-create-group-modal]");
    if (openGroupTrigger) {
      openCreateGroupModal();
      return;
    }

    const openGroupPermissionsTrigger = target.closest(
      "[data-open-group-permissions-modal]"
    );
    if (openGroupPermissionsTrigger instanceof HTMLElement) {
      openGroupPermissionsModal(
        openGroupPermissionsTrigger.dataset.openGroupPermissionsModal || ""
      );
      return;
    }

    const openTaskDetailEditTrigger = target.closest("[data-task-detail-edit]");
    if (openTaskDetailEditTrigger) {
      if (taskDetailContext) {
        const currentContext = taskDetailContext;
        void hydrateTaskDetailPayloadFromServer(currentContext)
          .catch(() => {})
          .finally(() => {
            if (taskDetailContext !== currentContext) return;
            populateTaskDetailModalFromRow(currentContext);
            setTaskDetailEditMode(true);
          });
      }
      return;
    }

    const openTaskReviewTrigger = target.closest("[data-task-detail-request-revision]");
    if (openTaskReviewTrigger) {
      openTaskReviewModal();
      return;
    }

    const removeTaskReviewTrigger = target.closest("[data-task-detail-remove-revision]");
    if (removeTaskReviewTrigger) {
      openConfirmModal({
        title: "Remover ajuste",
        message: "Remover a solicitação de ajuste atual e restaurar a descrição anterior?",
        confirmLabel: "Remover ajuste",
        confirmVariant: "danger",
        onConfirm: async () => {
          await submitTaskRevisionRemoval();
        },
      });
      return;
    }

    const cancelTaskDetailEditTrigger = target.closest("[data-task-detail-cancel-edit]");
    if (cancelTaskDetailEditTrigger) {
      if (taskDetailContext) {
        populateTaskDetailModalFromRow(taskDetailContext);
      }
      setTaskDetailEditMode(false);
      return;
    }

    const saveTaskDetailTrigger = target.closest("[data-task-detail-save]");
    if (saveTaskDetailTrigger) {
      saveTaskDetailModal().catch(() => {});
      return;
    }

    const deleteTaskDetailTrigger = target.closest("[data-task-detail-delete]");
    if (deleteTaskDetailTrigger) {
      const ctx = taskDetailContext;
      if (ctx?.deleteForm instanceof HTMLFormElement) {
        const taskTitle =
          ctx.titleInput?.value?.trim() ||
          ctx.taskItem?.querySelector(".task-title-input")?.value?.trim() ||
          "esta tarefa";

        openConfirmModal({
          title: "Excluir tarefa",
          message: `Remover ${taskTitle}?`,
          confirmLabel: "Excluir",
          confirmVariant: "danger",
          onConfirm: async () => {
            await submitDeleteTask(ctx.deleteForm);
          },
        });
      }
      return;
    }

    const closeTrigger = target.closest("[data-close-create-modal]");
    if (closeTrigger) {
      closeCreateModal();
      return;
    }

    const closeWorkspaceCreateTrigger = target.closest("[data-close-workspace-create-modal]");
    if (closeWorkspaceCreateTrigger) {
      closeWorkspaceCreateModal();
      return;
    }

    const closeWorkspaceUsersTrigger = target.closest("[data-close-workspace-users-modal]");
    if (closeWorkspaceUsersTrigger) {
      closeWorkspaceUsersModal();
      return;
    }

    const closeGroupTrigger = target.closest("[data-close-create-group-modal]");
    if (closeGroupTrigger) {
      closeCreateGroupModal();
      return;
    }

    const closeGroupPermissionsTrigger = target.closest(
      "[data-close-group-permissions-modal]"
    );
    if (closeGroupPermissionsTrigger instanceof HTMLElement) {
      closeGroupPermissionsModal(
        closeGroupPermissionsTrigger.closest("[data-group-permissions-modal]")
      );
      return;
    }

    const closeVaultGroupTrigger = target.closest("[data-close-vault-group-modal]");
    if (closeVaultGroupTrigger) {
      closeVaultGroupModal();
      return;
    }

    const closeDueGroupTrigger = target.closest("[data-close-due-group-modal]");
    if (closeDueGroupTrigger) {
      closeDueGroupModal();
      return;
    }

    const closeInventoryGroupTrigger = target.closest("[data-close-inventory-group-modal]");
    if (closeInventoryGroupTrigger) {
      closeInventoryGroupModal();
      return;
    }

    const closeVaultEntryTrigger = target.closest("[data-close-vault-entry-modal]");
    if (closeVaultEntryTrigger) {
      closeVaultEntryModal();
      return;
    }

    const closeDueEntryTrigger = target.closest("[data-close-due-entry-modal]");
    if (closeDueEntryTrigger) {
      closeDueEntryModal();
      return;
    }

    const closeInventoryEntryTrigger = target.closest("[data-close-inventory-entry-modal]");
    if (closeInventoryEntryTrigger) {
      closeInventoryEntryModal();
      return;
    }

    const closeVaultEntryEditTrigger = target.closest("[data-close-vault-entry-edit-modal]");
    if (closeVaultEntryEditTrigger) {
      closeVaultEntryEditModal();
      return;
    }

    const closeDueEntryEditTrigger = target.closest("[data-close-due-entry-edit-modal]");
    if (closeDueEntryEditTrigger) {
      closeDueEntryEditModal();
      return;
    }

    const closeInventoryEntryEditTrigger = target.closest("[data-close-inventory-entry-edit-modal]");
    if (closeInventoryEntryEditTrigger) {
      closeInventoryEntryEditModal();
      return;
    }

    const closeTaskDetailTrigger = target.closest("[data-close-task-detail-modal]");
    if (closeTaskDetailTrigger) {
      closeTaskDetailModal();
      return;
    }

    const closeTaskReviewTrigger = target.closest("[data-close-task-review-modal]");
    if (closeTaskReviewTrigger) {
      closeTaskReviewModal();
      return;
    }

    const closeGoogleDriveBrowserTrigger = target.closest("[data-close-google-drive-browser]");
    if (closeGoogleDriveBrowserTrigger) {
      closeGoogleDriveBrowserModal();
      return;
    }

    const closeConfirmTrigger = target.closest("[data-close-confirm-modal]");
    if (closeConfirmTrigger) {
      closeConfirmModal();
      return;
    }

    const closeImagePreviewTrigger = target.closest("[data-close-task-image-preview]");
    if (closeImagePreviewTrigger) {
      closeTaskImagePreview();
      return;
    }

    const previousImageTrigger = target.closest("[data-task-image-preview-prev]");
    if (previousImageTrigger) {
      stepTaskImagePreview(-1);
      return;
    }

    const nextImageTrigger = target.closest("[data-task-image-preview-next]");
    if (nextImageTrigger) {
      stepTaskImagePreview(1);
      return;
    }

    const confirmSubmitTrigger = target.closest("[data-confirm-modal-submit]");
    if (confirmSubmitTrigger) {
      if (confirmModalSubmit instanceof HTMLButtonElement) {
        confirmModalSubmit.disabled = true;
        confirmModalSubmit.classList.add("is-loading");
      }
      Promise.resolve()
        .then(() => (confirmModalAction ? confirmModalAction() : null))
        .then(() => {
          closeConfirmModal();
        })
        .catch(() => {
          if (confirmModalSubmit instanceof HTMLButtonElement) {
            confirmModalSubmit.disabled = false;
            confirmModalSubmit.classList.remove("is-loading");
          }
        });
    }
  });

  document.addEventListener("keydown", (event) => {
    const target = event.target;
    const isImagePreviewOpen =
      taskImagePreviewModal instanceof HTMLElement && !taskImagePreviewModal.hidden;
    if (isImagePreviewOpen && event.key === "ArrowLeft") {
      event.preventDefault();
      stepTaskImagePreview(-1);
      return;
    }
    if (isImagePreviewOpen && event.key === "ArrowRight") {
      event.preventDefault();
      stepTaskImagePreview(1);
      return;
    }

    if (
      event.key === "Enter" &&
      target instanceof HTMLElement &&
      target.matches("[data-vault-entry-label-input]")
    ) {
      event.preventDefault();
      const renameForm = target.closest("[data-vault-entry-name-form]");
      void submitVaultEntryNameForm(renameForm);
      return;
    }

    if (
      event.key === "Enter" &&
      target instanceof HTMLElement &&
      target.matches("[data-inventory-inline-quantity-input]")
    ) {
      event.preventDefault();
      target.blur();
      return;
    }

    if (event.key === "Enter" && target instanceof HTMLElement && target.matches("[data-group-name-input]")) {
      event.preventDefault();
      target.blur();
      return;
    }

    if (event.key === "Escape" && createTaskTitleTagIsCreating) {
      event.preventDefault();
      stopCreateTaskTitleTagCreation({ focusTrigger: true });
      return;
    }

    if (
      event.key === "Escape" &&
      createTaskTitleTagMenu instanceof HTMLElement &&
      !createTaskTitleTagMenu.hidden
    ) {
      event.preventDefault();
      closeCreateTaskTitleTagMenu();
      createTaskTitleTagTrigger?.focus();
      return;
    }

    if (event.key === "Escape" && isMobileSidebarOpen()) {
      setMobileSidebarOpen(false);
      return;
    }

    if (event.key !== "Escape") return;

    if (googleDriveBrowserModal && !googleDriveBrowserModal.hidden) {
      closeGoogleDriveBrowserModal();
      return;
    }

    if (fabWrap?.classList.contains("is-open")) {
      setFabMenuOpen(false);
    }
    if (createTaskModal && !createTaskModal.hidden) {
      closeCreateModal();
    }
    if (workspaceCreateModal && !workspaceCreateModal.hidden) {
      closeWorkspaceCreateModal();
    }
    const workspaceUsersModal = getWorkspaceUsersModal();
    if (workspaceUsersModal && !workspaceUsersModal.hidden) {
      closeWorkspaceUsersModal();
    }
    if (createGroupModal && !createGroupModal.hidden) {
      closeCreateGroupModal();
    }
    if (vaultGroupModal && !vaultGroupModal.hidden) {
      closeVaultGroupModal();
    }
    if (dueGroupModal && !dueGroupModal.hidden) {
      closeDueGroupModal();
    }
    if (inventoryGroupModal && !inventoryGroupModal.hidden) {
      closeInventoryGroupModal();
    }
    if (vaultEntryModal && !vaultEntryModal.hidden) {
      closeVaultEntryModal();
    }
    if (dueEntryModal && !dueEntryModal.hidden) {
      closeDueEntryModal();
    }
    if (inventoryEntryModal && !inventoryEntryModal.hidden) {
      closeInventoryEntryModal();
    }
    if (vaultEntryEditModal && !vaultEntryEditModal.hidden) {
      closeVaultEntryEditModal();
    }
    if (dueEntryEditModal && !dueEntryEditModal.hidden) {
      closeDueEntryEditModal();
    }
    if (inventoryEntryEditModal && !inventoryEntryEditModal.hidden) {
      closeInventoryEntryEditModal();
    }
    if (taskReviewModal && !taskReviewModal.hidden) {
      closeTaskReviewModal();
      return;
    }
    if (taskImagePreviewModal && !taskImagePreviewModal.hidden) {
      closeTaskImagePreview();
      return;
    }
    if (taskDetailModal && !taskDetailModal.hidden) {
      closeTaskDetailModal();
    }
    if (groupPermissionModals.some((modal) => modal instanceof HTMLElement && !modal.hidden)) {
      closeGroupPermissionsModal();
    }
    if (confirmModal && !confirmModal.hidden) {
      closeConfirmModal();
    }
  });

  if (createTaskTitleTagPicker instanceof HTMLElement) {
    createTaskTitleTagPicker.addEventListener("click", (event) => {
      const target = getEventTargetElement(event);
      if (!(target instanceof Element)) return;

      const colorOptionTrigger = target.closest("[data-create-task-title-tag-color-option]");
      if (colorOptionTrigger instanceof HTMLButtonElement) {
        const tagValue = normalizeTaskTitleTagValue(
          colorOptionTrigger.dataset.createTaskTitleTagColorTag || ""
        );
        const colorValue = normalizeTaskTitleTagColorValue(
          colorOptionTrigger.dataset.createTaskTitleTagColorValue || "",
          taskTitleTagDefaultColor
        );
        if (tagValue) {
          createTaskOpenColorPaletteTag = "";
          taskDetailEditOpenColorPaletteTag = "";
          applyTaskTitleTagColorEverywhere(tagValue, colorValue);
        }
        return;
      }

      const colorTagTrigger = target.closest("[data-create-task-title-tag-color]");
      if (colorTagTrigger instanceof HTMLButtonElement) {
        const tagValue = normalizeTaskTitleTagValue(
          colorTagTrigger.dataset.createTaskTitleTagColor || ""
        );
        if (tagValue) {
          createTaskOpenColorPaletteTag =
            createTaskOpenColorPaletteTag === tagValue ? "" : tagValue;
          taskDetailEditOpenColorPaletteTag = "";
          renderCreateTaskTitleTagMenu();
        }
        return;
      }

      const removeTagTrigger = target.closest("[data-create-task-title-tag-remove]");
      if (removeTagTrigger instanceof HTMLButtonElement) {
        removeTaskTitleTagOption(removeTagTrigger.dataset.createTaskTitleTagRemove || "");
        return;
      }

      const selectTagTrigger = target.closest("[data-create-task-title-tag-option]");
      if (selectTagTrigger instanceof HTMLButtonElement) {
        setCreateTaskTitleTagValue(selectTagTrigger.dataset.createTaskTitleTagOption || "");
        closeCreateTaskTitleTagMenu();
        return;
      }

      const createTagTrigger = target.closest("[data-create-task-title-tag-create]");
      if (createTagTrigger instanceof HTMLButtonElement) {
        startCreateTaskTitleTagCreation();
        return;
      }

      const toggleMenuTrigger = target.closest("[data-create-task-title-tag-trigger]");
      if (toggleMenuTrigger instanceof HTMLButtonElement) {
        if (createTaskTitleTagMenu instanceof HTMLElement && !createTaskTitleTagMenu.hidden) {
          closeCreateTaskTitleTagMenu();
        } else {
          openCreateTaskTitleTagMenu();
        }
      }
    });
  }

  if (createTaskTitleTagCustom instanceof HTMLInputElement) {
    createTaskTitleTagCustom.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        commitCreateTaskTitleTagCreation();
        createTaskTitleInput?.focus();
        return;
      }

      if (event.key !== "Escape") return;
      event.preventDefault();
      stopCreateTaskTitleTagCreation({ focusTrigger: true });
    });

    createTaskTitleTagCustom.addEventListener("blur", () => {
      window.setTimeout(() => {
        if (!createTaskTitleTagIsCreating) return;
        commitCreateTaskTitleTagCreation();
      }, 0);
    });
  }

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;
    if (!(createTaskTitleTagPicker instanceof HTMLElement)) return;
    if (createTaskTitleTagPicker.contains(target)) return;

    closeCreateTaskTitleTagMenu();
    if (createTaskTitleTagIsCreating) {
      commitCreateTaskTitleTagCreation();
    }
  });

  if (taskDetailEditTitleTagPicker instanceof HTMLElement) {
    taskDetailEditTitleTagPicker.addEventListener("click", (event) => {
      const target = getEventTargetElement(event);
      if (!(target instanceof Element)) return;

      const colorOptionTrigger = target.closest("[data-task-detail-edit-title-tag-color-option]");
      if (colorOptionTrigger instanceof HTMLButtonElement) {
        const tagValue = normalizeTaskTitleTagValue(
          colorOptionTrigger.dataset.taskDetailEditTitleTagColorTag || ""
        );
        const colorValue = normalizeTaskTitleTagColorValue(
          colorOptionTrigger.dataset.taskDetailEditTitleTagColorValue || "",
          taskTitleTagDefaultColor
        );
        if (tagValue) {
          createTaskOpenColorPaletteTag = "";
          taskDetailEditOpenColorPaletteTag = "";
          applyTaskTitleTagColorEverywhere(tagValue, colorValue);
        }
        return;
      }

      const colorTagTrigger = target.closest("[data-task-detail-edit-title-tag-color]");
      if (colorTagTrigger instanceof HTMLButtonElement) {
        const tagValue = normalizeTaskTitleTagValue(
          colorTagTrigger.dataset.taskDetailEditTitleTagColor || ""
        );
        if (tagValue) {
          taskDetailEditOpenColorPaletteTag =
            taskDetailEditOpenColorPaletteTag === tagValue ? "" : tagValue;
          createTaskOpenColorPaletteTag = "";
          renderTaskDetailTitleTagMenu();
        }
        return;
      }

      const removeTagTrigger = target.closest("[data-task-detail-edit-title-tag-remove]");
      if (removeTagTrigger instanceof HTMLButtonElement) {
        removeTaskTitleTagOption(removeTagTrigger.dataset.taskDetailEditTitleTagRemove || "");
        return;
      }

      const selectTagTrigger = target.closest("[data-task-detail-edit-title-tag-option]");
      if (selectTagTrigger instanceof HTMLButtonElement) {
        setTaskDetailTitleTagValue(selectTagTrigger.dataset.taskDetailEditTitleTagOption || "");
        closeTaskDetailTitleTagMenu();
        return;
      }

      const createTagTrigger = target.closest("[data-task-detail-edit-title-tag-create]");
      if (createTagTrigger instanceof HTMLButtonElement) {
        startTaskDetailTitleTagCreation();
        return;
      }

      const toggleMenuTrigger = target.closest("[data-task-detail-edit-title-tag-trigger]");
      if (toggleMenuTrigger instanceof HTMLButtonElement) {
        if (taskDetailEditTitleTagMenu instanceof HTMLElement && !taskDetailEditTitleTagMenu.hidden) {
          closeTaskDetailTitleTagMenu();
        } else {
          openTaskDetailTitleTagMenu();
        }
      }
    });
  }

  if (taskDetailEditTitleTagCustom instanceof HTMLInputElement) {
    taskDetailEditTitleTagCustom.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        commitTaskDetailTitleTagCreation();
        taskDetailEditTitle?.focus();
        return;
      }

      if (event.key !== "Escape") return;
      event.preventDefault();
      stopTaskDetailTitleTagCreation({ focusTrigger: true });
    });

    taskDetailEditTitleTagCustom.addEventListener("blur", () => {
      window.setTimeout(() => {
        if (!taskDetailEditTitleTagIsCreating) return;
        commitTaskDetailTitleTagCreation();
      }, 0);
    });
  }

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;
    if (!(taskDetailEditTitleTagPicker instanceof HTMLElement)) return;
    if (taskDetailEditTitleTagPicker.contains(target)) return;

    closeTaskDetailTitleTagMenu();
    if (taskDetailEditTitleTagIsCreating) {
      commitTaskDetailTitleTagCreation();
    }
  });

  if (createTaskDescriptionToolbar instanceof HTMLElement) {
    createTaskDescriptionToolbar.addEventListener("mousedown", (event) => {
      const target = getEventTargetElement(event);
      if (!(target instanceof Element)) return;
      const toolbarButton = target.closest("button");
      if (!(toolbarButton instanceof HTMLButtonElement)) return;
      event.preventDefault();
    });

    createTaskDescriptionToolbar.addEventListener("click", (event) => {
      const target = getEventTargetElement(event);
      if (!(target instanceof Element)) return;

      const formatButton = target.closest("[data-create-task-description-format]");
      if (formatButton instanceof HTMLButtonElement) {
        event.preventDefault();
        applyCreateTaskDescriptionFormat(
          formatButton.dataset.createTaskDescriptionFormat || "bold"
        );
        return;
      }

      const actionButton = target.closest("[data-create-task-description-action]");
      if (!(actionButton instanceof HTMLButtonElement)) return;

      event.preventDefault();
      if (String(actionButton.dataset.createTaskDescriptionAction || "") === "divider") {
        insertCreateTaskDescriptionDivider();
      }
    });
  }

  if (taskDetailEditDescriptionToolbar instanceof HTMLElement) {
    taskDetailEditDescriptionToolbar.addEventListener("mousedown", (event) => {
      const target = getEventTargetElement(event);
      if (!(target instanceof Element)) return;
      const toolbarButton = target.closest("button");
      if (!(toolbarButton instanceof HTMLButtonElement)) return;
      event.preventDefault();
    });

    taskDetailEditDescriptionToolbar.addEventListener("click", (event) => {
      const target = getEventTargetElement(event);
      if (!(target instanceof Element)) return;

      const formatButton = target.closest("[data-task-detail-description-format]");
      if (formatButton instanceof HTMLButtonElement) {
        event.preventDefault();
        applyTaskDetailDescriptionFormat(
          formatButton.dataset.taskDetailDescriptionFormat || "bold"
        );
        return;
      }

      const actionButton = target.closest("[data-task-detail-description-action]");
      if (!(actionButton instanceof HTMLButtonElement)) return;

      event.preventDefault();
      if (String(actionButton.dataset.taskDetailDescriptionAction || "") === "divider") {
        insertTaskDetailDescriptionDivider();
      }
    });
  }

  if (createTaskForm) {
    createTaskForm.addEventListener("submit", () => {
      clearGoogleDriveBrowserResumeState();
      if (createTaskTitleInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(createTaskTitleInput);
      }
      syncCreateTaskDescriptionTextareaFromEditor();
      const createTitleTag = createTaskTitleTagIsCreating
        ? commitCreateTaskTitleTagCreation()
        : normalizeTaskTitleTagValue(createTaskTitleTagInput?.value || createTaskCurrentTitleTag);
      const createTitleTagColor = createTitleTag
        ? resolveTaskTitleTagColor(
            createTitleTag,
            createTaskTitleTagColorInput?.value || createTaskCurrentTitleTagColor
          )
        : normalizeTaskTitleTagColorValue(
            createTaskTitleTagColorInput?.value || createTaskCurrentTitleTagColor,
            taskTitleTagDefaultColor
          );
      setCreateTaskTitleTagValue(createTitleTag, createTitleTagColor);

      if (createTaskLinksField instanceof HTMLTextAreaElement) {
        writeReferenceLinksEditField(createTaskLinksField, createTaskReferenceLinks);
        createTaskLinksField.value = JSON.stringify(
          parseReferenceUrlLines(createTaskReferenceLinks || [])
        );
      }

      if (createTaskImagesField instanceof HTMLTextAreaElement) {
        writeReferenceImageMediaField(createTaskImagesField, createTaskImageItems);
      }
      if (createTaskSubtasksField instanceof HTMLTextAreaElement) {
        createTaskSubtasksField.value = JSON.stringify(
          parseTaskSubtaskList(createTaskSubtaskItems || [], 40, {
            enforceDependency: createTaskSubtasksDependencyEnabled,
          })
        );
      }
      writeTaskSubtasksDependencyField(
        createTaskSubtasksDependencyInput,
        createTaskSubtasksDependencyEnabled
      );

      syncBodyModalLock();
    });
  }

  if (taskReviewForm instanceof HTMLFormElement) {
    taskReviewForm.addEventListener("submit", (event) => {
      event.preventDefault();
      void submitTaskReviewRequest();
    });
  }

  const applyTaskFilterForm = (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const currentUrl = new URL(window.location.href);
    const params = new URLSearchParams();
    const groupField = form.querySelector('select[name="group"]');
    const creatorField = form.querySelector('select[name="created_by"]');
    const assigneeField = form.querySelector('select[name="assignee"]');

    if (groupField instanceof HTMLSelectElement && (groupField.value || "").trim() !== "") {
      params.set("group", groupField.value.trim());
    }
    if (creatorField instanceof HTMLSelectElement && (creatorField.value || "").trim() !== "") {
      params.set("created_by", creatorField.value.trim());
    }
    if (assigneeField instanceof HTMLSelectElement && (assigneeField.value || "").trim() !== "") {
      params.set("assignee", assigneeField.value.trim());
    }

    params.set("view", "tasks");
    params.delete("task");
    currentUrl.search = params.toString();
    currentUrl.hash = "";
    window.location.assign(`${currentUrl.pathname}${currentUrl.search}`);
  };

  if (taskFilterForm instanceof HTMLFormElement) {
    taskFilterForm.addEventListener("submit", (event) => {
      event.preventDefault();
      applyTaskFilterForm(taskFilterForm);
    });

    taskFilterForm.addEventListener("change", (event) => {
      const target = event.target;
      if (!(target instanceof Element)) return;
      const select = target.closest('select[name="group"], select[name="created_by"], select[name="assignee"]');
      if (!(select instanceof HTMLSelectElement)) return;
      applyTaskFilterForm(taskFilterForm);
    });
  }

  if (createGroupForm) {
    createGroupForm.addEventListener("submit", () => {
      if (createGroupNameInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(createGroupNameInput);
      }
      syncBodyModalLock();
    });
  }

  if (vaultGroupForm instanceof HTMLFormElement) {
    vaultGroupForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (vaultGroupNameInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(vaultGroupNameInput);
      }
      void submitVaultActionForm(vaultGroupForm, {
        onSuccess: () => {
          closeVaultGroupModal();
        },
        successMessage: "Grupo do cofre criado.",
        fallbackError: "Falha ao criar grupo do cofre.",
      }).catch(() => {});
    });
  }

  if (dueGroupForm instanceof HTMLFormElement) {
    dueGroupForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (dueGroupNameInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(dueGroupNameInput);
      }
      void submitDueActionForm(dueGroupForm, {
        onSuccess: () => {
          closeDueGroupModal();
        },
        successMessage: "Grupo de vencimentos criado.",
        fallbackError: "Falha ao criar grupo de vencimentos.",
      }).catch(() => {});
    });
  }

  if (inventoryGroupForm instanceof HTMLFormElement) {
    inventoryGroupForm.addEventListener("submit", () => {
      if (inventoryGroupNameInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(inventoryGroupNameInput);
      }
      syncBodyModalLock();
    });
  }

  if (dueEntryRecurrenceField instanceof HTMLSelectElement) {
    dueEntryRecurrenceField.addEventListener("change", () => {
      syncDueCreateRecurrenceFields();
    });
  }

  if (dueEntryEditRecurrenceField instanceof HTMLSelectElement) {
    dueEntryEditRecurrenceField.addEventListener("change", () => {
      syncDueEditRecurrenceFields();
    });
  }

  if (dueEntryFixedDateField instanceof HTMLInputElement) {
    dueEntryFixedDateField.addEventListener("change", () => {
      syncDueCreateRecurrenceFields();
    });
  }

  if (dueEntryEditFixedDateField instanceof HTMLInputElement) {
    dueEntryEditFixedDateField.addEventListener("change", () => {
      syncDueEditRecurrenceFields();
    });
  }

  if (dueEntryAnnualMonthField instanceof HTMLSelectElement) {
    dueEntryAnnualMonthField.addEventListener("change", () => {
      syncDueCreateRecurrenceFields();
    });
  }

  if (dueEntryEditAnnualMonthField instanceof HTMLSelectElement) {
    dueEntryEditAnnualMonthField.addEventListener("change", () => {
      syncDueEditRecurrenceFields();
    });
  }

  if (dueEntryAnnualDayField instanceof HTMLInputElement) {
    dueEntryAnnualDayField.addEventListener("blur", () => {
      syncDueCreateRecurrenceFields();
    });
  }

  if (dueEntryEditAnnualDayField instanceof HTMLInputElement) {
    dueEntryEditAnnualDayField.addEventListener("blur", () => {
      syncDueEditRecurrenceFields();
    });
  }

  if (dueEntryMonthlyDayField instanceof HTMLInputElement) {
    dueEntryMonthlyDayField.addEventListener("blur", () => {
      dueEntryMonthlyDayField.value = normalizeDueMonthlyDayInput(dueEntryMonthlyDayField.value);
    });
  }

  if (dueEntryEditMonthlyDayField instanceof HTMLInputElement) {
    dueEntryEditMonthlyDayField.addEventListener("blur", () => {
      dueEntryEditMonthlyDayField.value = normalizeDueMonthlyDayInput(
        dueEntryEditMonthlyDayField.value
      );
    });
  }

  if (vaultEntryForm instanceof HTMLFormElement) {
    vaultEntryForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (vaultEntryLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(vaultEntryLabelField);
      }
      void submitVaultActionForm(vaultEntryForm, {
        onSuccess: () => {
          closeVaultEntryModal();
        },
        successMessage: "Item salvo no cofre.",
        fallbackError: "Falha ao salvar item no cofre.",
      }).catch(() => {});
    });
  }

  if (dueEntryForm instanceof HTMLFormElement) {
    dueEntryForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (dueEntryLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(dueEntryLabelField);
      }
      syncDueCreateRecurrenceFields();
      if (dueEntryMonthlyDayField instanceof HTMLInputElement) {
        dueEntryMonthlyDayField.value = normalizeDueMonthlyDayInput(dueEntryMonthlyDayField.value);
      }
      void submitDueActionForm(dueEntryForm, {
        onSuccess: () => {
          closeDueEntryModal();
        },
        successMessage: "Vencimento criado.",
        fallbackError: "Falha ao criar vencimento.",
      }).catch(() => {});
    });
  }

  if (inventoryEntryForm instanceof HTMLFormElement) {
    inventoryEntryForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (inventoryEntryLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(inventoryEntryLabelField);
      }
      const normalized = normalizeInventoryEntryFields({
        quantityField: inventoryEntryQuantityField,
        minQuantityField: inventoryEntryMinQuantityField,
      });
      if (!normalized) {
        return;
      }
      if (inventoryEntryUnitField instanceof HTMLInputElement) {
        const normalizedUnit = String(inventoryEntryUnitField.value || "").trim().toLowerCase();
        inventoryEntryUnitField.value = normalizedUnit || "un";
      }

      void submitInventoryActionForm(inventoryEntryForm, {
        onSuccess: () => {
          closeInventoryEntryModal();
        },
        successMessage: "Item de estoque criado.",
        fallbackError: "Falha ao criar item de estoque.",
      }).catch(() => {});
    });
  }

  if (vaultEntryEditForm instanceof HTMLFormElement) {
    vaultEntryEditForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (vaultEntryEditLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(vaultEntryEditLabelField);
      }
      void submitVaultActionForm(vaultEntryEditForm, {
        onSuccess: () => {
          closeVaultEntryEditModal();
        },
        successMessage: "Item do cofre atualizado.",
        fallbackError: "Falha ao atualizar item do cofre.",
      }).catch(() => {});
    });
  }

  if (dueEntryEditForm instanceof HTMLFormElement) {
    dueEntryEditForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (dueEntryEditLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(dueEntryEditLabelField);
      }
      syncDueEditRecurrenceFields();
      if (dueEntryEditMonthlyDayField instanceof HTMLInputElement) {
        dueEntryEditMonthlyDayField.value = normalizeDueMonthlyDayInput(
          dueEntryEditMonthlyDayField.value
        );
      }
      void submitDueActionForm(dueEntryEditForm, {
        onSuccess: () => {
          closeDueEntryEditModal();
        },
        successMessage: "Vencimento atualizado.",
        fallbackError: "Falha ao atualizar vencimento.",
      }).catch(() => {});
    });
  }

  if (inventoryEntryEditForm instanceof HTMLFormElement) {
    inventoryEntryEditForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (inventoryEntryEditLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(inventoryEntryEditLabelField);
      }
      const normalized = normalizeInventoryEntryFields({
        quantityField: inventoryEntryEditQuantityField,
        minQuantityField: inventoryEntryEditMinQuantityField,
      });
      if (!normalized) {
        return;
      }
      if (inventoryEntryEditUnitField instanceof HTMLInputElement) {
        const normalizedUnit = String(inventoryEntryEditUnitField.value || "").trim().toLowerCase();
        inventoryEntryEditUnitField.value = normalizedUnit || "un";
      }

      void submitInventoryActionForm(inventoryEntryEditForm, {
        onSuccess: () => {
          closeInventoryEntryEditModal();
        },
        successMessage: "Item de estoque atualizado.",
        fallbackError: "Falha ao atualizar item de estoque.",
      }).catch(() => {});
    });
  }

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.matches(".workspace-statuses-form")) return;

    const saveButton = form.querySelector("[data-workspace-status-save-button]");
    const addButton = form.querySelector("[data-workspace-status-add-button]");
    const createRow = form.querySelector("[data-workspace-status-create-row]");
    const draftLabelField = form.querySelector('input[name="new_status_label"]');
    const activeElement = document.activeElement instanceof Element ? document.activeElement : null;
    const hasDraftLabel =
      draftLabelField instanceof HTMLInputElement && String(draftLabelField.value || "").trim() !== "";
    const isCreateContext =
      activeElement instanceof Element &&
      createRow instanceof Element &&
      createRow.contains(activeElement);
    const isDirty = syncWorkspaceStatusesSaveState(form);
    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    const hasUniqueColors = validateWorkspaceStatusUniqueColors(form, { showMessage: false });

    if (submitter === saveButton) {
      if (
        (saveButton instanceof HTMLButtonElement && saveButton.disabled) ||
        !hasUniqueColors
      ) {
        event.preventDefault();
        validateWorkspaceStatusUniqueColors(form, { showMessage: true });
        return;
      }
      clearWorkspaceStatusDraftFields(form);
      return;
    }

    if (submitter === addButton) {
      const newStatusColorField = form.querySelector(
        '[data-workspace-status-create-row] [data-workspace-status-color-input]'
      );
      const rowColorCounts = collectWorkspaceStatusRowColorCounts(form);
      const newStatusColor = isWorkspaceStatusColorInput(newStatusColorField)
        ? String(newStatusColorField.value || "").trim().toUpperCase()
        : "";
      if (
        newStatusColor !== "" &&
        (rowColorCounts.get(newStatusColor) || 0) > 0
      ) {
        event.preventDefault();
        const duplicatedLabel = workspaceStatusColorLabelByValue(
          form,
          newStatusColor
        );
        showClientFlash("error", `A cor ${duplicatedLabel} já esta em uso por outro status.`);
      }
      return;
    }

    if (submitter instanceof HTMLElement) {
      clearWorkspaceStatusDraftFields(form);
      return;
    }

    if (!submitter) {
      if (isCreateContext && hasDraftLabel) {
        return;
      }
      if (isDirty) {
        if (!hasUniqueColors) {
          event.preventDefault();
          validateWorkspaceStatusUniqueColors(form, { showMessage: true });
          return;
        }
        clearWorkspaceStatusDraftFields(form);
        return;
      }
      event.preventDefault();
    }
  });

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.matches("[data-vault-entry-name-form]")) return;
    event.preventDefault();
    void submitVaultEntryNameForm(form);
  });

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    const action = resolvePostActionName(form);
    if (
      action !== "rename_vault_group" &&
      action !== "rename_due_group" &&
      action !== "delete_vault_group" &&
      action !== "delete_due_group"
    ) {
      return;
    }

    event.preventDefault();

    if (action === "rename_vault_group") {
      const nameInput = form.querySelector('input[name="new_group_name"]');
      if (nameInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(nameInput);
      }
      void submitVaultActionForm(form, {
        successMessage: "Grupo do cofre renomeado.",
        fallbackError: "Falha ao renomear grupo do cofre.",
      }).catch(() => {});
      return;
    }

    if (action === "rename_due_group") {
      const nameInput = form.querySelector('input[name="new_group_name"]');
      if (nameInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(nameInput);
      }
      void submitDueActionForm(form, {
        successMessage: "Grupo de vencimentos renomeado.",
        fallbackError: "Falha ao renomear grupo de vencimentos.",
      }).catch(() => {});
      return;
    }

    const isVaultGroupDelete = action === "delete_vault_group";
    const groupSection = form.closest(isVaultGroupDelete ? "[data-vault-group]" : "[data-due-group]");
    const groupName =
      groupSection?.dataset?.groupName?.trim() ||
      form.querySelector('input[name="group_name"]')?.value?.trim() ||
      "este grupo";
    const groupCountText = groupSection?.querySelector(".task-group-count")?.textContent?.trim() || "0";
    const groupItemCount = Number.parseInt(groupCountText, 10) || 0;
    const message =
      groupItemCount > 0
        ? `Remover o grupo ${groupName}? Os itens desse grupo tambem serao excluidos.`
        : `Remover o grupo ${groupName}?`;

    openConfirmModal({
      title: isVaultGroupDelete ? "Excluir grupo do cofre" : "Excluir grupo de vencimentos",
      message,
      confirmLabel: "Excluir",
      confirmVariant: "danger",
      onConfirm: async () => {
        if (isVaultGroupDelete) {
          await submitVaultActionForm(form, {
            successMessage: "Grupo do cofre removido.",
            fallbackError: "Falha ao remover grupo do cofre.",
          });
          return;
        }
        await submitDueActionForm(form, {
          successMessage: "Grupo de vencimentos removido.",
          fallbackError: "Falha ao remover grupo de vencimentos.",
        });
      },
    });
  });

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    const action = resolvePostActionName(form);
    if (!workspaceUsersActionNames.has(action)) return;

    const usersPanel = form.closest("#users.users-wrap");
    if (!(usersPanel instanceof HTMLElement)) return;

    event.preventDefault();

    if (action === "workspace_update_profile" || action === "workspace_update_name") {
      const workspaceNameInput = form.querySelector('input[name="workspace_name"]');
      if (workspaceNameInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(workspaceNameInput);
      }
    }

    const successMessageByAction = {
      workspace_update_profile: "Dados do workspace atualizados.",
      workspace_update_name: "Dados do workspace atualizados.",
      workspace_add_member: "Convite enviado.",
      add_workspace_member: "Convite enviado.",
      workspace_accept_invitation: "Convite aceito.",
      workspace_decline_invitation: "Convite recusado.",
      workspace_cancel_invitation: "Convite cancelado.",
      workspace_promote_member: "Permissão de administrador concedida.",
      workspace_demote_member: "Permissão alterada para usuário.",
      workspace_remove_member: "Usuário removido do workspace.",
    };

    const fallbackErrorByAction = {
      workspace_update_profile: "Falha ao atualizar os dados do workspace.",
      workspace_update_name: "Falha ao atualizar os dados do workspace.",
      workspace_add_member: "Falha ao enviar convite.",
      add_workspace_member: "Falha ao enviar convite.",
      workspace_accept_invitation: "Falha ao aceitar convite.",
      workspace_decline_invitation: "Falha ao recusar convite.",
      workspace_cancel_invitation: "Falha ao cancelar convite.",
      workspace_promote_member: "Falha ao promover usuário.",
      workspace_demote_member: "Falha ao alterar permissão do usuário.",
      workspace_remove_member: "Falha ao remover usuário do workspace.",
    };

    void submitWorkspaceUsersActionForm(form, {
      successMessage: successMessageByAction[action] || "",
      fallbackError: fallbackErrorByAction[action] || "Falha ao atualizar usuários do workspace.",
    }).catch(() => {});
  });

  document.addEventListener("change", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLElement)) return;

    if (target.matches("[data-sidebar-tools-add-select]")) {
      const form = target.closest("[data-sidebar-tools-form]");
      if (form instanceof HTMLFormElement) {
        syncWorkspaceSidebarToolsFormState(form);
      }
    }

    const accountingEntryForm = target.closest(".accounting-entry-form, .accounting-entry-quick-status-form");
    const isAccountingEntryField =
      target instanceof HTMLInputElement || target instanceof HTMLSelectElement;
    if (
      accountingEntryForm instanceof HTMLFormElement &&
      isAccountingEntryField &&
      ["label", "amount_value", "is_settled", "monthly_day"].includes(target.name)
    ) {
      syncAccountingInstallmentForm(accountingEntryForm);
      if (accountingEntryForm.classList.contains("accounting-entry-editor-form")) {
        return;
      }
      scheduleAccountingAutosave(accountingEntryForm, target instanceof HTMLInputElement && target.type === "checkbox" ? 120 : 240, {
        fallbackError: "Falha ao atualizar registro.",
      });
      return;
    }

    const accountingCreateForm = target.closest(".accounting-create-form");
    const isAccountingCreateField =
      target instanceof HTMLInputElement || target instanceof HTMLSelectElement;
    if (
      accountingCreateForm instanceof HTMLFormElement &&
      isAccountingCreateField &&
      ["accounting_type_choice", "is_installment", "is_monthly_due", "installment_number", "installment_total", "total_amount_value", "amount_value", "monthly_day"].includes(target.name)
    ) {
      if (target.name === "is_installment" && target instanceof HTMLInputElement && target.checked) {
        const monthlyToggle = accountingCreateForm.querySelector("[data-accounting-monthly-toggle]");
        if (monthlyToggle instanceof HTMLInputElement) {
          monthlyToggle.checked = false;
        }
      }
      if (target.name === "is_monthly_due" && target instanceof HTMLInputElement && target.checked) {
        const installmentToggle = accountingCreateForm.querySelector("[data-accounting-installment-toggle]");
        if (installmentToggle instanceof HTMLInputElement) {
          installmentToggle.checked = false;
        }
      }
      syncAccountingInstallmentForm(accountingCreateForm);
      return;
    }

    if (
      target instanceof HTMLInputElement &&
      target.id === "accounting-period-input"
    ) {
      const accountingPeriodForm = target.form;
      if (!(accountingPeriodForm instanceof HTMLFormElement)) return;
      if (typeof accountingPeriodForm.requestSubmit === "function") {
        accountingPeriodForm.requestSubmit();
      } else {
        accountingPeriodForm.submit();
      }
    }
  });

  document.addEventListener("input", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLElement)) return;
    if (!(target instanceof HTMLInputElement)) return;

    if (isAccountingCurrencyInputField(target)) {
      formatAccountingCurrencyInputFieldWhileTyping(target);
    }

    if (
      !["amount_value", "total_amount_value", "opening_balance_value"].includes(target.name)
    ) {
      return;
    }

    const accountingForm = target.closest(".accounting-entry-form, .accounting-create-form");
    if (!(accountingForm instanceof HTMLFormElement)) return;
    syncAccountingInstallmentForm(accountingForm);
  });

  document.addEventListener("keydown", (event) => {
    const target = getEventTargetElement(event);
    if (!isAccountingCurrencyInputField(target)) return;

    if (event.ctrlKey || event.metaKey || event.altKey) return;

    if (/^\d$/.test(event.key)) {
      event.preventDefault();
      const state = getAccountingCurrencyFieldState(target);
      const baseDigits = hasAccountingCurrencySelection(target) ? "" : state.digits;
      setAccountingCurrencyFieldDigits(target, `${baseDigits}${event.key}`, {
        isNegative: state.isNegative,
      });
      return;
    }

    if (event.key === "Backspace") {
      event.preventDefault();
      const state = getAccountingCurrencyFieldState(target);
      const nextDigits = hasAccountingCurrencySelection(target) ? "" : state.digits.slice(0, -1);
      setAccountingCurrencyFieldDigits(target, nextDigits, {
        isNegative: state.isNegative,
      });
      return;
    }

    if (event.key === "Delete") {
      event.preventDefault();
      setAccountingCurrencyFieldDigits(target, "", {
        isNegative: false,
      });
      return;
    }

    if ((event.key === "-" || event.key === "Subtract") && target.dataset.accountingAllowNegative === "1") {
      event.preventDefault();
      const state = getAccountingCurrencyFieldState(target);
      if (!state.digits) {
        target.dataset.accountingNegative = state.isNegative ? "0" : "1";
        return;
      }
      setAccountingCurrencyFieldDigits(target, state.digits, {
        isNegative: !state.isNegative,
      });
      return;
    }

    const allowedNavigationKeys = [
      "Tab",
      "Enter",
      "Escape",
      "ArrowLeft",
      "ArrowRight",
      "ArrowUp",
      "ArrowDown",
      "Home",
      "End",
    ];
    if (allowedNavigationKeys.includes(event.key)) return;

    if (event.key.length === 1) {
      event.preventDefault();
    }
  });

  document.addEventListener("paste", (event) => {
    const target = getEventTargetElement(event);
    if (!isAccountingCurrencyInputField(target)) return;

    const pastedText = event.clipboardData?.getData("text") || "";
    const digits = pastedText.replace(/\D/g, "");
    const allowNegative = target.dataset.accountingAllowNegative === "1";
    const isNegative = allowNegative && /^\s*-/.test(pastedText);

    event.preventDefault();
    setAccountingCurrencyFieldDigits(target, digits, { isNegative });
  });

  document.addEventListener("focusout", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLInputElement)) return;

    if (["amount_value", "total_amount_value", "opening_balance_value"].includes(target.name)) {
      normalizeAccountingCurrencyInputField(target);
    }

    const accountingEntryForm = target.closest(".accounting-entry-form, .accounting-entry-quick-status-form");
    if (
      accountingEntryForm instanceof HTMLFormElement &&
      ["label", "amount_value", "monthly_day"].includes(target.name)
    ) {
      syncAccountingInstallmentForm(accountingEntryForm);
      if (accountingEntryForm.classList.contains("accounting-entry-editor-form")) {
        return;
      }
      scheduleAccountingAutosave(accountingEntryForm, 160, {
        fallbackError: "Falha ao atualizar registro.",
      });
    }
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLElement)) return;

    const cancelButton = target.closest("[data-accounting-entry-cancel]");
    if (!(cancelButton instanceof HTMLButtonElement)) return;

    const entryRow = cancelButton.closest(".accounting-entry-row");
    if (!(entryRow instanceof HTMLElement)) return;

    event.preventDefault();
    closeAccountingEntryEditor(entryRow);
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLElement)) return;

    const cancelButton = target.closest("[data-accounting-create-cancel]");
    if (!(cancelButton instanceof HTMLButtonElement)) return;

    const accountingCreateForm = cancelButton.closest(".accounting-create-form");
    if (!(accountingCreateForm instanceof HTMLFormElement)) return;

    event.preventDefault();
    if (accountingCreateForm.dataset.submitting === "1") return;
    const accountingCreateToggle = accountingCreateForm.closest(".accounting-create-toggle");
    closeAccountingCreateToggle(accountingCreateToggle);
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLElement)) return;

    const openingBalanceToggle = target.closest("[data-accounting-opening-balance-toggle]");
    if (!(openingBalanceToggle instanceof HTMLButtonElement)) return;

    event.preventDefault();
    openAccountingOpeningBalanceEditor();
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLElement)) return;

    const cancelButton = target.closest("[data-accounting-opening-balance-cancel]");
    if (!(cancelButton instanceof HTMLButtonElement)) return;

    event.preventDefault();
    closeAccountingOpeningBalanceEditor();
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLElement)) return;

    const editButton = target.closest("[data-accounting-entry-toggle], [data-accounting-entry-edit]");
    if (!(editButton instanceof HTMLElement)) return;

    event.preventDefault();

    const entryRow = editButton.closest(".accounting-entry-row");
    if (!(entryRow instanceof HTMLElement)) return;

    const accountingEntryForm = entryRow.querySelector(".accounting-entry-editor-form");
    if (!(accountingEntryForm instanceof HTMLFormElement)) {
      return;
    }
    if (accountingEntryForm.hidden) {
      openAccountingEntryEditor(entryRow);
      return;
    }

    const labelField = accountingEntryForm.querySelector('input[name="label"]');
    if (labelField instanceof HTMLInputElement) {
      labelField.focus();
      labelField.select();
    }
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLElement)) return;

    document.querySelectorAll(".accounting-entry-row.is-editing").forEach((openRow) => {
      if (!(openRow instanceof HTMLElement)) return;
      if (openRow.contains(target)) return;
      closeAccountingEntryEditor(openRow);
    });
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLElement)) return;

    const openingBalanceEditor = document.querySelector(".accounting-opening-balance-editor.is-editing");
    if (!(openingBalanceEditor instanceof HTMLElement)) return;
    if (openingBalanceEditor.contains(target)) return;

    closeAccountingOpeningBalanceEditor();
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof HTMLElement)) return;

    document.querySelectorAll("details.accounting-create-toggle[open]").forEach((toggle) => {
      if (!(toggle instanceof HTMLDetailsElement)) return;
      if (toggle.contains(target)) return;
      closeAccountingCreateToggle(toggle);
    });
  });

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    if (form.matches(".accounting-entry-form, .accounting-entry-quick-status-form")) {
      event.preventDefault();
      syncAccountingInstallmentForm(form);
      if (typeof form.reportValidity === "function" && !form.reportValidity()) {
        return;
      }
      void submitAccountingAutosaveForm(form, {
        fallbackError: "Falha ao atualizar registro.",
      }).catch(() => {});
      return;
    }

    if (form.matches(".accounting-create-form")) {
      event.preventDefault();
      syncAccountingInstallmentForm(form);
      if (typeof form.reportValidity === "function" && !form.reportValidity()) {
        return;
      }

      const entryTypeField = form.querySelector('input[name="entry_type"]');
      const isIncome =
        entryTypeField instanceof HTMLInputElement && entryTypeField.value === "income";

      void submitAccountingActionForm(form, {
        successMessage: isIncome ? "Entrada adicionada." : "Conta adicionada.",
        fallbackError: isIncome ? "Falha ao adicionar entrada." : "Falha ao adicionar conta.",
        refresh: true,
      }).catch(() => {});
      return;
    }

    if (form.matches(".accounting-entry-delete-form")) {
      event.preventDefault();
      void submitAccountingActionForm(form, {
        successMessage: "Registro removido.",
        fallbackError: "Falha ao remover registro.",
        refresh: true,
      }).catch(() => {});
      return;
    }

    if (form.matches(".accounting-opening-balance-form")) {
      event.preventDefault();
      const openingBalanceField = form.querySelector('input[name="opening_balance_value"]');
      if (openingBalanceField instanceof HTMLInputElement) {
        normalizeAccountingCurrencyInputField(openingBalanceField);
      }
      if (typeof form.reportValidity === "function" && !form.reportValidity()) {
        return;
      }
      void submitAccountingActionForm(form, {
        successMessage: "Saldo atualizado.",
        fallbackError: "Falha ao atualizar saldo.",
        refresh: true,
      }).catch(() => {});
      return;
    }

  });

  document.querySelectorAll("[data-accounting-form]").forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    syncAccountingInstallmentForm(form);
    form
      .querySelectorAll('input[name="amount_value"], input[name="total_amount_value"], input[name="opening_balance_value"]')
      .forEach((field) => {
        if (field instanceof HTMLInputElement) {
          normalizeAccountingCurrencyInputField(field);
        }
      });
  });

  if (typeof syncTaskDetailModalFromUrl === "function") {
    syncTaskDetailModalFromUrl({
      closeIfMissing: false,
      scrollIntoView: dashboardTaskIdFromUrl() > 0,
    });
  }

  void resumeGoogleDriveBrowserFlowAfterAuth();

  window.addEventListener("popstate", () => {
    if (dashboardViewPanels.length) {
      setDashboardView(dashboardViewFromUrl(), {
        updateUrl: false,
        taskId: dashboardTaskIdFromUrl(),
      });
    }

    if (typeof syncTaskDetailModalFromUrl === "function") {
      syncTaskDetailModalFromUrl({
        closeIfMissing: true,
        scrollIntoView: false,
      });
    }
  });

  startTaskNotificationsPolling();
});

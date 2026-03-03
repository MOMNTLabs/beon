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

  const taskStatusSortRank = (status) => {
    switch ((status || "").trim()) {
      case "review":
        return 1;
      case "in_progress":
        return 2;
      case "todo":
        return 3;
      case "done":
        return 4;
      default:
        return 99;
    }
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
    if (!(select instanceof HTMLSelectElement)) return "";
    return (select.value || "").trim();
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
      (child) => child instanceof HTMLElement && child.matches("[data-task-item]")
    );

    if (taskItems.length < 2) return;

    const sorted = taskItems
      .map((taskItem, index) => ({
        taskItem,
        index,
        statusRank: taskStatusSortRank(getTaskItemStatusValue(taskItem)),
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

    Array.from(taskItem.classList).forEach((className) => {
      if (className.startsWith("task-status-")) {
        taskItem.classList.remove(className);
      }
    });

    if (select.value) {
      taskItem.classList.add(`task-status-${select.value}`);
    }

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

  const getInlineSelectWrap = (node) => {
    if (!(node instanceof Element)) return null;
    return node.closest("[data-inline-select-wrap], .row-inline-picker-wrap");
  };

  const syncInlineSelectPicker = (select) => {
    if (!(select instanceof HTMLSelectElement)) return;
    if (!select.matches("[data-inline-select-source]")) return;

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
      Array.from(details.classList).forEach((className) => {
        if (
          (className.startsWith("status-") && className !== "status-inline-picker") ||
          (className.startsWith("priority-") && className !== "priority-inline-picker")
        ) {
          details.classList.remove(className);
        }
      });

      if (select.classList.contains("status-select") && select.value) {
        details.classList.add(`status-${select.value}`);
      }
      if (select.classList.contains("priority-select") && select.value) {
        details.classList.add(`priority-${select.value}`);
      }
    }
  };

  const syncSelectColor = (select) => {
    if (!select) return;

    if (select.classList.contains("status-select")) {
      Array.from(select.classList).forEach((className) => {
        if (className.startsWith("status-") && className !== "status-select") {
          select.classList.remove(className);
        }
      });
      if (select.value) select.classList.add(`status-${select.value}`);
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

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;

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

      if (quantityForm.dataset.submitting === "1") return;
      quantityForm.dataset.submitting = "1";
      quantityForm.submit();
      return;
    }

    const select = target.closest(".status-select, .priority-select");
    if (select) {
      syncSelectColor(select);
    }
  });

  document.addEventListener("input", (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.matches(uppercaseRequiredInputSelector)) {
      applyFirstLetterUppercaseToInput(target);
    }
    if (
      target instanceof HTMLElement &&
      target.matches("[data-task-detail-edit-description-editor]")
    ) {
      normalizeTaskDetailDescriptionEditorLists();
      syncTaskDetailDescriptionTextareaFromEditor();
      syncTaskDetailDescriptionToolbar();
      return;
    }

    if (!(target instanceof HTMLTextAreaElement)) return;
    if (target.matches("[data-task-detail-edit-description]")) {
      autoResizeTextarea(target);
      return;
    }

    if (target.matches("[data-task-detail-edit-links]")) {
      syncReferenceTextareaLayout(target);
    }
  });

  document.addEventListener("keydown", (event) => {
    const target = event.target;
    if (
      target instanceof HTMLElement &&
      target.matches("[data-task-detail-edit-description-editor]")
    ) {
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

  document.addEventListener("selectionchange", () => {
    syncTaskDetailDescriptionToolbar();
  });

  let taskDetailFormatButtonPressed = null;

  window.addEventListener("resize", () => {
    syncTaskDetailDescriptionToolbar();
  });

  document.addEventListener(
    "scroll",
    () => {
      syncTaskDetailDescriptionToolbar();
    },
    true
  );

  document.addEventListener("mousedown", (event) => {
    const target = event.target;
    if (!(target instanceof Node)) return;

    if (target instanceof HTMLElement) {
      const formatButton = target.closest("[data-task-detail-description-format]");
      if (formatButton) {
        taskDetailFormatButtonPressed = formatButton;
        event.preventDefault();
        event.stopPropagation();
        return;
      }
    }

    taskDetailFormatButtonPressed = null;

    if (!(taskDetailEditDescriptionEditor instanceof HTMLElement)) return;

    const clickedEditor = taskDetailEditDescriptionEditor.contains(target);
    const clickedToolbar =
      taskDetailEditDescriptionToolbar instanceof HTMLElement &&
      taskDetailEditDescriptionToolbar.contains(target);

    if (!clickedEditor && !clickedToolbar) {
      if (taskDetailEditDescriptionToolbar instanceof HTMLElement) {
        taskDetailEditDescriptionToolbar.hidden = true;
      }
      return;
    }

    if (!clickedEditor) return;

    const range = getTaskDetailDescriptionSelectionRange();
    if (!range || range.collapsed) return;

    if (selectionRangeContainsPoint(range, event.clientX, event.clientY)) {
      return;
    }

    collapseTaskDetailSelectionAtPoint(event.clientX, event.clientY);
    window.setTimeout(syncTaskDetailDescriptionToolbar, 0);
  });

  document.addEventListener("mouseup", (event) => {
    const target = event.target;
    if (!(target instanceof Node)) return;
    if (!(taskDetailEditDescriptionEditor instanceof HTMLElement)) return;
    if (!taskDetailEditDescriptionEditor.contains(target)) return;
    window.setTimeout(syncTaskDetailDescriptionToolbar, 0);
  });

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;
    const formatButton = target.closest("[data-task-detail-description-format]");
    if (!formatButton) return;
    event.preventDefault();
    event.stopPropagation();

    const keyboardActivation = event.detail === 0;
    if (!keyboardActivation && taskDetailFormatButtonPressed !== formatButton) {
      return;
    }

    taskDetailFormatButtonPressed = null;
    applyTaskDetailDescriptionFormat(formatButton.dataset.taskDetailDescriptionFormat || "bold");
  });

  const toLocalIsoDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
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

    let formatted = raw;
    const parsed = new Date(`${raw}T00:00:00`);
    if (!Number.isNaN(parsed.getTime())) {
      formatted = parsed.toLocaleDateString("pt-BR");
    }

    if (raw === todayIso) {
      return {
        display: "Hoje",
        title: `Hoje (${formatted})`,
        isRelative: true,
      };
    }

    if (raw === tomorrowIso) {
      return {
        display: "Amanha",
        title: `Amanha (${formatted})`,
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
    textarea.style.height = `${textarea.scrollHeight}px`;
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

      const listMatch = line.match(/^-\s+(.+)$/);
      if (listMatch) {
        listItems.push(listMatch[1].trim());
        return;
      }

      flushList();
      parts.push(`<p>${formatTaskDescriptionInlineHtml(line)}</p>`);
    });

    flushList();
    return parts.join("");
  };

  const TASK_TITLE_TAG_CUSTOM_VALUE = "__custom__";

  const normalizeTaskTitleTagValue = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "";
    const limited = raw.slice(0, 40);
    return forceFirstLetterUppercase(limited).trim();
  };

  const ensureTaskTitleTagOption = (selectEl, tagValue) => {
    if (!(selectEl instanceof HTMLSelectElement)) return;
    const normalizedTag = normalizeTaskTitleTagValue(tagValue);
    if (!normalizedTag) return;
    const hasOption = Array.from(selectEl.options).some(
      (option) => normalizeTaskTitleTagValue(option.value) === normalizedTag
    );
    if (hasOption) return;

    const option = document.createElement("option");
    option.value = normalizedTag;
    option.textContent = normalizedTag;

    const customOption = Array.from(selectEl.options).find(
      (entry) => entry.value === TASK_TITLE_TAG_CUSTOM_VALUE
    );
    if (customOption) {
      customOption.insertAdjacentElement("beforebegin", option);
    } else {
      selectEl.append(option);
    }
  };

  const syncTaskTitleTagEditorControls = ({
    selectEl = null,
    customInputEl = null,
    currentTag = "",
  } = {}) => {
    if (!(selectEl instanceof HTMLSelectElement) || !(customInputEl instanceof HTMLInputElement)) {
      return;
    }

    const normalizedCurrentTag = normalizeTaskTitleTagValue(currentTag);
    if (normalizedCurrentTag) {
      ensureTaskTitleTagOption(selectEl, normalizedCurrentTag);
    }

    if (!normalizedCurrentTag) {
      selectEl.value = "";
      customInputEl.hidden = true;
      customInputEl.value = "";
      return;
    }

    const hasOption = Array.from(selectEl.options).some(
      (option) => normalizeTaskTitleTagValue(option.value) === normalizedCurrentTag
    );
    if (hasOption) {
      selectEl.value = normalizedCurrentTag;
      customInputEl.hidden = true;
      customInputEl.value = "";
      return;
    }

    selectEl.value = TASK_TITLE_TAG_CUSTOM_VALUE;
    customInputEl.hidden = false;
    customInputEl.value = normalizedCurrentTag;
  };

  const resolveTaskTitleTagFromEditor = ({
    selectEl = null,
    customInputEl = null,
  } = {}) => {
    if (!(selectEl instanceof HTMLSelectElement)) {
      return "";
    }

    const selectedValue = String(selectEl.value || "").trim();
    if (!selectedValue) {
      return "";
    }

    if (selectedValue === TASK_TITLE_TAG_CUSTOM_VALUE) {
      if (!(customInputEl instanceof HTMLInputElement)) {
        return "";
      }

      return normalizeTaskTitleTagValue(customInputEl.value || "");
    }

    return normalizeTaskTitleTagValue(selectedValue);
  };

  const syncTaskTitleTagBadge = (taskItem, titleTag) => {
    if (!(taskItem instanceof HTMLElement)) return;
    const normalizedTag = normalizeTaskTitleTagValue(titleTag);
    const field = taskItem.querySelector("[data-task-title-tag]");
    if (field instanceof HTMLInputElement) {
      field.value = normalizedTag;
    }

    const badge = taskItem.querySelector("[data-task-title-tag-badge]");
    if (!(badge instanceof HTMLElement)) return;
    if (!normalizedTag) {
      badge.hidden = true;
      badge.textContent = "";
      return;
    }

    badge.hidden = false;
    badge.textContent = normalizedTag;
  };

  const syncTaskDetailViewTitleTag = (titleTag) => {
    if (!(taskDetailViewTitleTag instanceof HTMLElement)) return;
    const normalizedTag = normalizeTaskTitleTagValue(titleTag);
    if (!normalizedTag) {
      taskDetailViewTitleTag.hidden = true;
      taskDetailViewTitleTag.textContent = "";
      return;
    }

    taskDetailViewTitleTag.hidden = false;
    taskDetailViewTitleTag.textContent = normalizedTag;
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
      taskDetailViewDescription.textContent = "Sem descricao.";
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
        ? `Descricao anterior ${index + 1} · ${summaryDate}${summaryActor}`
        : `Descricao anterior ${index + 1}`;

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

  const normalizeTaskDetailDescriptionEditorLists = () => {
    if (!(taskDetailEditDescriptionEditor instanceof HTMLElement)) return;
    taskDetailEditDescriptionEditor.querySelectorAll("ul").forEach((list) => {
      list.classList.add("task-detail-description-list");
    });
  };

  const syncTaskDetailDescriptionEditorFromTextarea = () => {
    if (
      !(taskDetailEditDescription instanceof HTMLTextAreaElement) ||
      !(taskDetailEditDescriptionEditor instanceof HTMLElement)
    ) {
      return;
    }

    const text = String(taskDetailEditDescription.value || "");
    if (!text.trim()) {
      taskDetailEditDescriptionEditor.innerHTML = "";
      return;
    }

    taskDetailEditDescriptionEditor.innerHTML = formatTaskDescriptionHtml(text);
    normalizeTaskDetailDescriptionEditorLists();
  };

  const taskDetailDescriptionInlineNodeToText = (node) => {
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
      .map((child) => taskDetailDescriptionInlineNodeToText(child))
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

  const taskDetailDescriptionBlockToLines = (node) => {
    if (node.nodeType === Node.TEXT_NODE) {
      return String(node.textContent || "").split("\n");
    }

    if (!(node instanceof HTMLElement)) {
      return [];
    }

    if (node.tagName === "UL" || node.tagName === "OL") {
      return Array.from(node.children)
        .filter((child) => child instanceof HTMLElement && child.tagName === "LI")
        .map((item) => {
          const value = taskDetailDescriptionInlineNodeToText(item).replace(/\s+/g, " ").trim();
          return value ? `- ${value}` : "";
        })
        .filter(Boolean);
    }

    return taskDetailDescriptionInlineNodeToText(node)
      .split("\n")
      .map((line) => line.trimEnd());
  };

  const taskDetailDescriptionTextFromEditor = () => {
    if (!(taskDetailEditDescriptionEditor instanceof HTMLElement)) {
      return "";
    }

    normalizeTaskDetailDescriptionEditorLists();

    const rawLines = [];
    Array.from(taskDetailEditDescriptionEditor.childNodes).forEach((node) => {
      rawLines.push(
        ...taskDetailDescriptionBlockToLines(node).map((line) => line.replace(/\u00a0/g, " "))
      );
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

  const syncTaskDetailDescriptionTextareaFromEditor = () => {
    if (!(taskDetailEditDescription instanceof HTMLTextAreaElement)) {
      return;
    }

    taskDetailEditDescription.value = taskDetailDescriptionTextFromEditor();
  };

  const getTaskDetailDescriptionSelectionRange = () => {
    if (!(taskDetailEditDescriptionEditor instanceof HTMLElement)) {
      return null;
    }

    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
      return null;
    }

    const range = selection.getRangeAt(0);
    if (
      !taskDetailEditDescriptionEditor.contains(range.startContainer) ||
      !taskDetailEditDescriptionEditor.contains(range.endContainer)
    ) {
      return null;
    }

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

  const collapseTaskDetailSelectionAtPoint = (clientX, clientY) => {
    if (!(taskDetailEditDescriptionEditor instanceof HTMLElement)) return;

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
    if (!taskDetailEditDescriptionEditor.contains(nextRange.startContainer)) return;

    selection.removeAllRanges();
    selection.addRange(nextRange);
  };

  const positionTaskDetailDescriptionToolbar = (range) => {
    if (
      !(taskDetailEditDescriptionToolbar instanceof HTMLElement) ||
      !(taskDetailEditDescriptionWrap instanceof HTMLElement)
    ) {
      return;
    }

    const selectionRect = range.getBoundingClientRect();
    if (selectionRect.width <= 0 && selectionRect.height <= 0) {
      return;
    }

    const wrapRect = taskDetailEditDescriptionWrap.getBoundingClientRect();
    const toolbarRect = taskDetailEditDescriptionToolbar.getBoundingClientRect();
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

    taskDetailEditDescriptionToolbar.style.left = `${Math.round(left)}px`;
    taskDetailEditDescriptionToolbar.style.top = `${Math.round(top)}px`;
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

  const syncTaskDetailDescriptionToolbar = () => {
    if (!(taskDetailEditDescriptionToolbar instanceof HTMLElement)) {
      return;
    }

    const range = getTaskDetailDescriptionSelectionRange();
    const show =
      Boolean(range && !range.collapsed) &&
      Boolean(taskDetailModal && !taskDetailModal.hidden && taskDetailModal.classList.contains("is-editing"));

    taskDetailEditDescriptionToolbar.hidden = !show;
    if (!show || !range) {
      return;
    }

    positionTaskDetailDescriptionToolbar(range);
  };

  const applyTaskDetailDescriptionFormat = (format) => {
    if (!(taskDetailEditDescriptionEditor instanceof HTMLElement)) return;
    const range = getTaskDetailDescriptionSelectionRange();
    if (!range) return;

    const command = format === "italic" ? "italic" : "bold";
    taskDetailEditDescriptionEditor.focus();
    document.execCommand(command, false);
    normalizeTaskDetailDescriptionEditorLists();
    syncTaskDetailDescriptionTextareaFromEditor();
    syncTaskDetailDescriptionToolbar();
  };

  const convertDashLineToListInTaskDetailEditor = () => {
    if (!(taskDetailEditDescriptionEditor instanceof HTMLElement)) {
      return false;
    }

    const range = getTaskDetailDescriptionSelectionRange();
    if (!range || !range.collapsed) {
      return false;
    }

    let block =
      range.startContainer instanceof HTMLElement
        ? range.startContainer
        : range.startContainer.parentElement;

    while (
      block &&
      block !== taskDetailEditDescriptionEditor &&
      !["P", "DIV", "LI"].includes(block.tagName)
    ) {
      block = block.parentElement;
    }

    const blockText = block
      ? (block.textContent || "").replace(/\u00a0/g, " ").trim()
      : (taskDetailEditDescriptionEditor.textContent || "").replace(/\u00a0/g, " ").trim();

    if (blockText !== "-") {
      return false;
    }

    if (block && block.tagName === "LI") {
      return false;
    }

    taskDetailEditDescriptionEditor.focus();
    document.execCommand("insertUnorderedList", false);
    normalizeTaskDetailDescriptionEditorLists();

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

    syncTaskDetailDescriptionTextareaFromEditor();
    return true;
  };

  const maxReferenceItems = 20;
  const maxReferenceImageChars = 2_000_000;

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

  const parseReferenceImageItems = (value, maxItems = maxReferenceItems) => {
    const seen = new Set();
    const result = [];

    parseReferenceRawList(value).forEach((item) => {
      if (result.length >= maxItems) return;
      const normalized = normalizeImageReference(item);
      if (!normalized || seen.has(normalized)) return;
      seen.add(normalized);
      result.push(normalized);
    });

    return result;
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

  const parseTaskSubtaskList = (value, maxItems = 40) => {
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

    let unlockNext = true;
    normalized.forEach((item) => {
      if (!unlockNext) {
        item.done = false;
      }
      if (!item.done) {
        unlockNext = false;
      }
    });

    return normalized;
  };

  const taskSubtasksProgressMeta = (subtasks) => {
    const normalized = parseTaskSubtaskList(subtasks || []);
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

  const readTaskSubtasksField = (field) => {
    if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) {
      return [];
    }
    return parseTaskSubtaskList(field.value || "[]");
  };

  const writeTaskSubtasksField = (field, subtasks) => {
    if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement)) {
      return;
    }
    field.value = JSON.stringify(parseTaskSubtaskList(subtasks || []));
  };

  const formatHistoryDate = (value) => {
    const raw = (value || "").trim();
    if (!raw) return "Sem prazo";
    const parsed = new Date(`${raw}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return raw;
    return parsed.toLocaleDateString("pt-BR");
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
        return "Solicitacao de ajuste na descricao";
      case "revision_removed":
        return "Solicitacao de ajuste removida";
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
      title.textContent = `Em atraso ha ${Math.max(0, Number(overdueDays) || 0)} dia(s)`;
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
    const safeImages = parseReferenceImageItems(images || []);

    if (taskDetailViewLinks instanceof HTMLElement) {
      taskDetailViewLinks.innerHTML = "";
      safeLinks.forEach((url) => {
        const a = document.createElement("a");
        a.href = url;
        a.target = "_blank";
        a.rel = "noreferrer noopener";
        a.className = "task-detail-ref-link";
        a.textContent = url;
        taskDetailViewLinks.append(a);
      });
    }
    if (taskDetailViewLinksWrap instanceof HTMLElement) {
      taskDetailViewLinksWrap.hidden = safeLinks.length === 0;
    }

    if (taskDetailViewImages instanceof HTMLElement) {
      taskDetailViewImages.innerHTML = "";
      safeImages.forEach((url) => {
        const trigger = document.createElement("button");
        trigger.type = "button";
        trigger.className = "task-detail-ref-image-link";
        trigger.dataset.taskRefImagePreview = url;
        trigger.setAttribute("aria-label", "Ampliar imagem de referencia");

        const img = document.createElement("img");
        img.src = url;
        img.alt = "Referencia da tarefa";
        img.loading = "lazy";
        img.className = "task-detail-ref-image";

        trigger.append(img);
        taskDetailViewImages.append(trigger);
      });
    }
    if (taskDetailViewImagesWrap instanceof HTMLElement) {
      taskDetailViewImagesWrap.hidden = safeImages.length === 0;
    }

    if (taskDetailViewReferences instanceof HTMLElement) {
      taskDetailViewReferences.hidden = safeLinks.length === 0 && safeImages.length === 0;
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

  document.querySelectorAll("[data-due-date-input]").forEach((input) => {
    syncDueDateDisplay(input);
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

  document.querySelectorAll(".assignee-picker").forEach((details) => {
    updateAssigneePickerSummary(details);
  });

  document
    .querySelectorAll("[data-inline-select-source]")
    .forEach((select) => syncInlineSelectPicker(select));

  document
    .querySelectorAll('.assignee-picker, [data-inline-select-picker]')
    .forEach((details) => {
      if (!(details instanceof HTMLDetailsElement)) return;
      details.addEventListener("toggle", () => {
        if (details.open) {
          closeSiblingTaskOverlays(details);
        }
        syncTaskItemOverlayState(details);
      });
    });

  document.addEventListener("mousedown", (event) => {
    const target = event.target;
    if (!(target instanceof Node)) return;
    closeOpenDropdownDetails(target);
  });

  document.addEventListener("change", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;
    const checkbox = target.closest('.assignee-picker input[type="checkbox"]');
    if (!checkbox) return;
    const picker = checkbox.closest(".assignee-picker");
    if (picker) updateAssigneePickerSummary(picker);
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
    updateBoardCountText("[data-board-visible-count]", "visiveis", visible);
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

  const refreshTaskGroupSection = (groupSection) => {
    if (!(groupSection instanceof HTMLElement)) return;
    const dropzone = groupSection.querySelector("[data-task-dropzone]");
    if (!(dropzone instanceof HTMLElement)) return;

    const taskCount = dropzone.querySelectorAll("[data-task-item]").length;
    const countEl = groupSection.querySelector(".task-group-count");
    if (countEl) countEl.textContent = String(taskCount);

    const emptyRow = dropzone.querySelector(".task-group-empty-row");
    const groupName = (groupSection.dataset.groupName || "Geral").trim() || "Geral";

    if (taskCount === 0) {
      if (!emptyRow) dropzone.append(createEmptyGroupRow(groupName));
    } else if (emptyRow) {
      emptyRow.remove();
    }

    sortGroupTaskItemsByStatus(dropzone);
    syncGroupStatusDividers(dropzone);
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

  const resolveInitialGroupCollapsedState = (scope, groupSection) => {
    if (!(groupSection instanceof HTMLElement)) return false;
    const storedState = getStoredGroupCollapsedState(scope, groupSection.dataset.groupName || "");
    if (storedState !== null) {
      return storedState;
    }
    return groupSection.classList.contains("is-collapsed");
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
    if (!(taskItem instanceof HTMLElement)) return false;
    const nextGroup = (groupName || "").trim() || "Geral";
    const targetDropzone = document.querySelector(
      `[data-task-dropzone][data-group-name="${CSS.escape(nextGroup)}"]`
    );
    if (!(targetDropzone instanceof HTMLElement)) return false;

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

  const postFormJson = async (form) => {
    const response = await fetch(form.getAttribute("action") || window.location.href, {
      method: "POST",
      body: new FormData(form),
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      credentials: "same-origin",
    });

    let data = null;
    try {
      data = await response.json();
    } catch (e) {
      data = null;
    }

    if (!response.ok || !data || data.ok !== true) {
      const message =
        (data && (data.error || data.message)) ||
        "Nao foi possivel concluir a operacao.";
      throw new Error(message);
    }

    return data;
  };

  const autosaveTimers = new WeakMap();
  const submitTaskAutosave = async (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.autosaveSubmitting === "1") return false;

    form.dataset.autosaveSubmitting = "1";
    form.classList.add("is-saving");
    let success = false;

    try {
      const data = await postFormJson(form);
      const task = data.task || {};
      const taskItem = form.closest("[data-task-item]");

      if (typeof task.reference_links_json === "string") {
        const linksField = form.querySelector("[data-task-reference-links-json]");
        if (linksField instanceof HTMLInputElement) {
          linksField.value = task.reference_links_json;
        }
      }
      if (typeof task.reference_images_json === "string") {
        const imagesField = form.querySelector("[data-task-reference-images-json]");
        if (imagesField instanceof HTMLInputElement) {
          imagesField.value = task.reference_images_json;
        }
      }
      if (typeof task.subtasks_json === "string") {
        const subtasksField = form.querySelector("[data-task-subtasks-json]");
        if (subtasksField instanceof HTMLInputElement) {
          subtasksField.value = task.subtasks_json;
          const subtasks = readTaskSubtasksField(subtasksField);
          writeTaskSubtasksField(subtasksField, subtasks);
          if (taskItem instanceof HTMLElement) {
            renderTaskRowSubtasksProgress(taskItem, subtasks);
          }
        }
      }
      if (Object.prototype.hasOwnProperty.call(task, "title_tag")) {
        const titleTagField = form.querySelector("[data-task-title-tag]");
        const normalizedTag = normalizeTaskTitleTagValue(task.title_tag || "");
        if (titleTagField instanceof HTMLInputElement) {
          titleTagField.value = normalizedTag;
        }
        if (taskItem instanceof HTMLElement) {
          syncTaskTitleTagBadge(taskItem, normalizedTag);
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
        const historyField = form.querySelector("[data-task-history-json]");
        if (historyField instanceof HTMLInputElement) {
          writeTaskHistoryField(historyField, task.history);
        }
      }

      if (taskItem instanceof HTMLElement && typeof task.group_name === "string") {
        moveTaskItemToGroupDom(taskItem, task.group_name);
      }

      refreshTaskUpdatedAtMeta(form, task.updated_at_label || "");
      renderDashboardSummary(data.dashboard);
      if (taskDetailContext && taskDetailContext.form === form && taskDetailModal && !taskDetailModal.hidden) {
        populateTaskDetailModalFromRow(taskDetailContext);
      }
      delete form.dataset.autosaveError;
      success = true;
    } catch (error) {
      form.dataset.autosaveError = "1";
      showClientFlash("error", error instanceof Error ? error.message : "Falha ao salvar tarefa.");
    } finally {
      form.classList.remove("is-saving");
      delete form.dataset.autosaveSubmitting;
      if (form.dataset.autosavePending === "1") {
        delete form.dataset.autosavePending;
        scheduleTaskAutosave(form, 80);
      }
    }
    return success;
  };

  const scheduleTaskAutosave = (form, delay = 180) => {
    if (!(form instanceof HTMLFormElement)) return;

    if (form.dataset.autosaveSubmitting === "1") {
      form.dataset.autosavePending = "1";
      return;
    }

    const previousTimer = autosaveTimers.get(form);
    if (previousTimer) window.clearTimeout(previousTimer);

    const nextTimer = window.setTimeout(() => {
      if (typeof form.reportValidity === "function" && !form.reportValidity()) {
        return;
      }
      submitTaskAutosave(form);
    }, delay);

    autosaveTimers.set(form, nextTimer);
  };

  document.addEventListener("change", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (target instanceof HTMLInputElement && target.matches(uppercaseRequiredInputSelector)) {
      applyFirstLetterUppercaseToInput(target);
    }

    if (target.matches("[data-permission-all-checkbox]")) {
      const permissionModal = target.closest("[data-group-permissions-modal]");
      if (permissionModal instanceof HTMLElement && target instanceof HTMLInputElement) {
        permissionModal.querySelectorAll("[data-permission-enabled-checkbox]").forEach((checkbox) => {
          if (!(checkbox instanceof HTMLInputElement)) return;
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
    if (!form) return;

    if (target.matches('.row-assignee-picker input[type="checkbox"]')) {
      form.dataset.assigneeDirty = "1";
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

  document.querySelectorAll(".row-assignee-picker").forEach((picker) => {
    picker.addEventListener("toggle", () => {
      if (picker.open) return;
      const form = picker.closest("[data-task-autosave-form]");
      if (!form || form.dataset.assigneeDirty !== "1") return;
      delete form.dataset.assigneeDirty;
      scheduleTaskAutosave(form, 120);
    });
  });

  document.querySelectorAll("[data-task-autosave-form]").forEach((form) => {
    syncTaskOverdueBadge(form);
    syncTaskRowSubtasksFromField(form);
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      submitTaskAutosave(form);
    });
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
        if (typeof input.showPicker === "function") {
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

  const setFabMenuOpen = (open) => {
    if (!fabWrap || !fabToggleButton || !fabMenu) return;
    fabWrap.classList.toggle("is-open", open);
    fabToggleButton.setAttribute("aria-expanded", open ? "true" : "false");
    fabMenu.setAttribute("aria-hidden", open ? "false" : "true");
  };

  const normalizeDashboardViewCandidate = (value) => {
    const normalized = String(value || "").trim().toLowerCase();
    return normalized === "tasks" ||
      normalized === "vault" ||
      normalized === "dues" ||
      normalized === "inventory" ||
      normalized === "users"
      ? normalized
      : "";
  };

  const dashboardViews = new Set();
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
  if (!dashboardViews.has("tasks")) {
    dashboardViews.add("tasks");
  }

  const normalizeDashboardView = (value) => {
    const normalized = normalizeDashboardViewCandidate(value);
    return normalized && dashboardViews.has(normalized) ? normalized : "tasks";
  };

  const dashboardViewFromHash = () => {
    const rawHash = String(window.location.hash || "").replace(/^#/, "");
    return normalizeDashboardView(rawHash);
  };

  const setDashboardView = (nextView, { updateHash = false } = {}) => {
    if (!dashboardViewPanels.length) return;

    const view = normalizeDashboardView(nextView);
    dashboardViewPanels.forEach((panel) => {
      if (!(panel instanceof HTMLElement)) return;
      const panelView = normalizeDashboardView(panel.dataset.dashboardViewPanel || "");
      panel.hidden = panelView !== view;
    });

    dashboardViewToggleButtons.forEach((button) => {
      if (!(button instanceof HTMLElement)) return;
      const buttonView = normalizeDashboardView(button.dataset.view || "");
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

    if (updateHash) {
      const targetHash = `#${view}`;
      if (window.location.hash !== targetHash) {
        window.history.replaceState(null, "", targetHash);
      }
    }
  };

  if (dashboardViewPanels.length) {
    setDashboardView(dashboardViewFromHash(), { updateHash: false });
    window.addEventListener("hashchange", () => {
      setDashboardView(dashboardViewFromHash(), { updateHash: false });
    });
  }

  const createTaskModal = document.querySelector("[data-create-modal]");
  const createTaskGroupInput = document.querySelector("[data-create-task-group-input]");
  const createTaskTitleComposer = document.querySelector("[data-create-task-title-composer]");
  const createTaskTitleTagPicker = document.querySelector("[data-create-task-title-tag-picker]");
  const createTaskTitleTagTrigger = document.querySelector("[data-create-task-title-tag-trigger]");
  const createTaskTitleTagMenu = document.querySelector("[data-create-task-title-tag-menu]");
  const createTaskTitleInput = document.querySelector("[data-create-task-title-input]");
  const createTaskTitleTagCustom = document.querySelector("[data-create-task-title-tag-custom]");
  const createTaskTitleTagInput = document.querySelector("[data-create-task-title-tag-input]");
  const taskTitleTagOptionsDataElement = document.querySelector("#task-title-tag-options-data");
  const createTaskForm = document.querySelector("[data-create-task-form]");
  const createTaskLinksField = document.querySelector("[data-create-task-links]");
  const createTaskImagesField = document.querySelector("[data-create-task-images]");
  const createTaskSubtasksField = document.querySelector("[data-create-task-subtasks]");
  const createTaskSubtasksList = document.querySelector("[data-create-task-subtasks-list]");
  const createTaskSubtaskInput = document.querySelector("[data-create-task-subtask-input]");
  const createTaskSubtaskAddButton = document.querySelector("[data-create-task-subtask-add]");
  const createTaskImagePicker = document.querySelector("[data-create-task-image-picker]");
  const createTaskImageInput = document.querySelector("[data-create-task-image-input]");
  const createTaskImageAddButton = document.querySelector("[data-create-task-image-add]");
  const createTaskImageList = document.querySelector("[data-create-task-image-list]");
  const workspaceCreateModal = document.querySelector("[data-workspace-create-modal]");
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
  const taskImagePreviewImage = document.querySelector("[data-task-image-preview-img]");
  const taskDetailViewHistory = document.querySelector("[data-task-detail-view-history]");
  const taskDetailViewCreatedBy = document.querySelector("[data-task-detail-view-created-by]");
  const taskDetailViewUpdatedAt = document.querySelector("[data-task-detail-view-updated-at]");
  const taskDetailEditTitle = document.querySelector("[data-task-detail-edit-title]");
  const taskDetailEditTitleTagSelect = document.querySelector(
    "[data-task-detail-edit-title-tag-select]"
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
  const taskDetailEditSubtaskInput = document.querySelector("[data-task-detail-edit-subtask-input]");
  const taskDetailEditSubtaskAddButton = document.querySelector("[data-task-detail-edit-subtask-add]");
  const taskDetailEditLinks = document.querySelector("[data-task-detail-edit-links]");
  const taskDetailEditImages = document.querySelector("[data-task-detail-edit-images]");
  const taskDetailImagePicker = document.querySelector("[data-task-detail-image-picker]");
  const taskDetailImageInput = document.querySelector("[data-task-detail-image-input]");
  const taskDetailImageAddButton = document.querySelector("[data-task-detail-image-add]");
  const taskDetailImageList = document.querySelector("[data-task-detail-image-list]");
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
  const groupPermissionModals = Array.from(
    document.querySelectorAll("[data-group-permissions-modal]")
  );
  let confirmModalAction = null;
  let taskDetailContext = null;
  let taskDetailEditImageItems = [];
  let taskDetailEditSubtaskItems = [];
  let createTaskImageItems = [];
  let createTaskSubtaskItems = [];
  let createTaskTitleTagOptions = [];
  let createTaskCurrentTitleTag = "";
  let createTaskTitleTagIsCreating = false;

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

  const readTaskTitleTagOptionsFromData = () => {
    if (!(taskTitleTagOptionsDataElement instanceof HTMLScriptElement)) {
      return [];
    }

    try {
      const parsed = JSON.parse(taskTitleTagOptionsDataElement.textContent || "[]");
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  };

  const closeCreateTaskTitleTagMenu = () => {
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

    if (createTaskTitleTagInput instanceof HTMLInputElement) {
      createTaskTitleTagInput.value = normalizedTag;
    }

    if (!(createTaskTitleTagTrigger instanceof HTMLButtonElement)) return;

    createTaskTitleTagTrigger.textContent = normalizedTag || "tag";
    createTaskTitleTagTrigger.classList.toggle("is-empty", !normalizedTag);
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

      row.append(optionButton, removeButton);
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

  const setCreateTaskTitleTagValue = (value = "") => {
    createTaskCurrentTitleTag = normalizeTaskTitleTagValue(value);
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
      }
    }
    stopCreateTaskTitleTagCreation();
    renderCreateTaskTitleTagMenu();
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

  const resetCreateTaskTitleTagPicker = () => {
    closeCreateTaskTitleTagMenu();
    stopCreateTaskTitleTagCreation();
    setCreateTaskTitleTagValue("");
  };

  createTaskTitleTagOptions = normalizeTaskTitleTagCollection(readTaskTitleTagOptionsFromData());
  resetCreateTaskTitleTagPicker();

  function renderTaskRowSubtasksProgress(taskItem, subtasks) {
    if (!(taskItem instanceof HTMLElement)) return;
    const progressWrap = taskItem.querySelector("[data-task-subtasks-progress]");
    if (!(progressWrap instanceof HTMLElement)) return;

    const stepsWrap = progressWrap.querySelector("[data-task-subtasks-progress-steps]");
    const textEl = progressWrap.querySelector("[data-task-subtasks-progress-text]");
    const progress = taskSubtasksProgressMeta(subtasks);
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

    items.forEach((item, index) => {
      const dot = document.createElement("span");
      dot.className = "task-subtasks-progress-step";
      if (item.done) {
        dot.classList.add("is-done");
      } else if (index > 0 && !items[index - 1]?.done) {
        dot.classList.add("is-locked");
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

    const subtasks = readTaskSubtasksField(subtasksField);
    writeTaskSubtasksField(subtasksField, subtasks);
    renderTaskRowSubtasksProgress(taskItem, subtasks);
  }

  const renderTaskSubtasksViewList = ({ subtasks = [], readOnly = false, editable = true } = {}) => {
    if (!(taskDetailViewSubtasks instanceof HTMLElement)) return;

    const normalized = parseTaskSubtaskList(subtasks || []);
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
      const isUnlocked = index === 0 || Boolean(normalized[index - 1]?.done);
      checkbox.disabled = readOnly || !editable || (!isUnlocked && !item.done);
      if (!isUnlocked && !item.done) {
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

  const setTaskDetailEditSubtasks = (subtasks) => {
    taskDetailEditSubtaskItems = parseTaskSubtaskList(subtasks || []);
    if (taskDetailEditSubtasksField instanceof HTMLTextAreaElement) {
      writeTaskSubtasksField(taskDetailEditSubtasksField, taskDetailEditSubtaskItems);
    }
  };

  const setCreateTaskSubtasks = (subtasks) => {
    createTaskSubtaskItems = parseTaskSubtaskList(subtasks || []);
    if (createTaskSubtasksField instanceof HTMLTextAreaElement) {
      writeTaskSubtasksField(createTaskSubtasksField, createTaskSubtaskItems);
    }
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
      check.disabled = index > 0 && !taskDetailEditSubtaskItems[index - 1]?.done && !item.done;

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
      remove.textContent = "x";

      row.append(check, title, remove);
      taskDetailEditSubtasksList.append(row);
    });

    if (taskDetailEditSubtasksField instanceof HTMLTextAreaElement) {
      writeTaskSubtasksField(taskDetailEditSubtasksField, taskDetailEditSubtaskItems);
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
      check.disabled = index > 0 && !createTaskSubtaskItems[index - 1]?.done && !item.done;

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
      remove.textContent = "x";

      row.append(check, title, remove);
      createTaskSubtasksList.append(row);
    });

    if (createTaskSubtasksField instanceof HTMLTextAreaElement) {
      writeTaskSubtasksField(createTaskSubtasksField, createTaskSubtaskItems);
    }
  };

  const closeTaskImagePreview = () => {
    if (!(taskImagePreviewModal instanceof HTMLElement)) return;
    taskImagePreviewModal.hidden = true;
    if (taskImagePreviewImage instanceof HTMLImageElement) {
      taskImagePreviewImage.removeAttribute("src");
    }
    syncBodyModalLock();
  };

  const openTaskImagePreview = (src) => {
    const imageSrc = String(src || "").trim();
    if (!(taskImagePreviewModal instanceof HTMLElement)) return;
    if (!(taskImagePreviewImage instanceof HTMLImageElement)) return;
    if (!imageSrc) return;

    taskImagePreviewImage.src = imageSrc;
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
    if (taskDetailEditImages instanceof HTMLTextAreaElement) {
      taskDetailEditImages.value = taskDetailEditImageItems.join("\n");
    }
  };

  const syncCreateTaskImageHiddenField = () => {
    if (createTaskImagesField instanceof HTMLTextAreaElement) {
      createTaskImagesField.value = createTaskImageItems.join("\n");
    }
  };

  const renderTaskDetailImageList = () => {
    if (!(taskDetailImageList instanceof HTMLElement)) return;

    taskDetailImageList.innerHTML = "";
    if (!taskDetailEditImageItems.length) return;

    taskDetailEditImageItems.forEach((imageValue, index) => {
      const item = document.createElement("div");
      item.className = "task-detail-edit-image-item";

      const image = document.createElement("img");
      image.src = imageValue;
      image.alt = "Imagem de referencia";
      image.className = "task-detail-edit-image-preview";
      image.loading = "lazy";

      const removeButton = document.createElement("button");
      removeButton.type = "button";
      removeButton.className = "task-detail-edit-image-remove";
      removeButton.dataset.taskDetailImageRemove = String(index);
      removeButton.setAttribute("aria-label", "Remover imagem de referencia");
      removeButton.textContent = "x";

      item.append(image, removeButton);
      taskDetailImageList.append(item);
    });
  };

  const renderCreateTaskImageList = () => {
    if (!(createTaskImageList instanceof HTMLElement)) return;

    createTaskImageList.innerHTML = "";
    if (!createTaskImageItems.length) return;

    createTaskImageItems.forEach((imageValue, index) => {
      const item = document.createElement("div");
      item.className = "task-detail-edit-image-item";

      const image = document.createElement("img");
      image.src = imageValue;
      image.alt = "Imagem de referencia";
      image.className = "task-detail-edit-image-preview";
      image.loading = "lazy";

      const removeButton = document.createElement("button");
      removeButton.type = "button";
      removeButton.className = "task-detail-edit-image-remove";
      removeButton.dataset.createTaskImageRemove = String(index);
      removeButton.setAttribute("aria-label", "Remover imagem de referencia");
      removeButton.textContent = "x";

      item.append(image, removeButton);
      createTaskImageList.append(item);
    });
  };

  const setTaskDetailEditImageItems = (items) => {
    taskDetailEditImageItems = parseReferenceImageItems(items || []);
    syncTaskDetailImageHiddenField();
    renderTaskDetailImageList();
  };

  const mergeTaskDetailEditImageItems = (items) => {
    const merged = parseReferenceImageItems([...(taskDetailEditImageItems || []), ...(items || [])]);
    taskDetailEditImageItems = merged;
    syncTaskDetailImageHiddenField();
    renderTaskDetailImageList();
  };

  const setCreateTaskImageItems = (items) => {
    createTaskImageItems = parseReferenceImageItems(items || []);
    syncCreateTaskImageHiddenField();
    renderCreateTaskImageList();
  };

  const mergeCreateTaskImageItems = (items) => {
    const merged = parseReferenceImageItems([...(createTaskImageItems || []), ...(items || [])]);
    createTaskImageItems = merged;
    syncCreateTaskImageHiddenField();
    renderCreateTaskImageList();
  };

  const readFileAsDataUrl = (file) =>
    new Promise((resolve, reject) => {
      if (!(file instanceof File)) {
        reject(new Error("Arquivo invalido."));
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
          nextValues.push(normalized);
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
          nextValues.push(normalized);
        }
      } catch (_error) {
        // Ignore invalid files and keep processing remaining images.
      }
    }

    if (nextValues.length) {
      mergeCreateTaskImageItems(nextValues);
    }
  };

  if (taskDetailImageAddButton instanceof HTMLButtonElement && taskDetailImageInput instanceof HTMLInputElement) {
    taskDetailImageAddButton.addEventListener("click", () => {
      taskDetailImageInput.click();
    });
  }

  if (createTaskImageAddButton instanceof HTMLButtonElement && createTaskImageInput instanceof HTMLInputElement) {
    createTaskImageAddButton.addEventListener("click", () => {
      createTaskImageInput.click();
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
      void addCreateTaskImagesFromFiles(files);
    });
  }

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

  const addTaskDetailSubtaskFromInput = () => {
    if (!(taskDetailEditSubtaskInput instanceof HTMLInputElement)) return;
    const title = (taskDetailEditSubtaskInput.value || "").trim();
    if (!title) return;
    taskDetailEditSubtaskItems = parseTaskSubtaskList([
      ...taskDetailEditSubtaskItems,
      { title, done: false },
    ]);
    taskDetailEditSubtaskInput.value = "";
    renderTaskDetailSubtasksEditList();
    taskDetailEditSubtaskInput.focus();
  };

  const addCreateTaskSubtaskFromInput = () => {
    if (!(createTaskSubtaskInput instanceof HTMLInputElement)) return;
    const title = (createTaskSubtaskInput.value || "").trim();
    if (!title) return;
    createTaskSubtaskItems = parseTaskSubtaskList([
      ...createTaskSubtaskItems,
      { title, done: false },
    ]);
    createTaskSubtaskInput.value = "";
    renderCreateTaskSubtasksEditList();
    createTaskSubtaskInput.focus();
  };

  if (taskDetailEditSubtaskAddButton instanceof HTMLButtonElement) {
    taskDetailEditSubtaskAddButton.addEventListener("click", addTaskDetailSubtaskFromInput);
  }
  if (createTaskSubtaskAddButton instanceof HTMLButtonElement) {
    createTaskSubtaskAddButton.addEventListener("click", addCreateTaskSubtaskFromInput);
  }

  if (taskDetailEditSubtaskInput instanceof HTMLInputElement) {
    taskDetailEditSubtaskInput.addEventListener("keydown", (event) => {
      if (event.key !== "Enter") return;
      event.preventDefault();
      addTaskDetailSubtaskFromInput();
    });
  }
  if (createTaskSubtaskInput instanceof HTMLInputElement) {
    createTaskSubtaskInput.addEventListener("keydown", (event) => {
      if (event.key !== "Enter") return;
      event.preventDefault();
      addCreateTaskSubtaskFromInput();
    });
  }

  document.addEventListener("click", (event) => {
    const target = getEventTargetElement(event);
    if (!(target instanceof Element)) return;

    const removeDetailSubtask = target.closest("[data-task-detail-edit-subtask-remove]");
    if (removeDetailSubtask instanceof HTMLButtonElement) {
      const index = Number.parseInt(removeDetailSubtask.dataset.taskDetailEditSubtaskRemove || "-1", 10);
      if (!Number.isFinite(index) || index < 0) return;
      taskDetailEditSubtaskItems = taskDetailEditSubtaskItems.filter(
        (_item, itemIndex) => itemIndex !== index
      );
      taskDetailEditSubtaskItems = parseTaskSubtaskList(taskDetailEditSubtaskItems);
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
      createTaskSubtaskItems = parseTaskSubtaskList(createTaskSubtaskItems);
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
        writeTaskSubtasksField(taskDetailEditSubtasksField, taskDetailEditSubtaskItems);
      }
      return;
    }

    const createTitleInput = target.closest("[data-create-task-subtask-title]");
    if (createTitleInput instanceof HTMLInputElement) {
      const index = Number.parseInt(createTitleInput.dataset.createTaskSubtaskTitle || "-1", 10);
      if (!Number.isFinite(index) || index < 0 || index >= createTaskSubtaskItems.length) return;
      createTaskSubtaskItems[index].title = String(createTitleInput.value || "").slice(0, 120);
      if (createTaskSubtasksField instanceof HTMLTextAreaElement) {
        writeTaskSubtasksField(createTaskSubtasksField, createTaskSubtaskItems);
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
      taskDetailEditSubtaskItems = parseTaskSubtaskList(taskDetailEditSubtaskItems);
      renderTaskDetailSubtasksEditList();
      return;
    }

    const createCheck = target.closest("[data-create-task-subtask-done]");
    if (createCheck instanceof HTMLInputElement) {
      const index = Number.parseInt(createCheck.dataset.createTaskSubtaskDone || "-1", 10);
      if (!Number.isFinite(index) || index < 0 || index >= createTaskSubtaskItems.length) return;
      createTaskSubtaskItems[index].done = createCheck.checked;
      createTaskSubtaskItems = parseTaskSubtaskList(createTaskSubtaskItems);
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

    const current = readTaskSubtasksField(taskDetailContext.subtasksField);
    if (index >= current.length) return;

    current[index].done = subtaskToggle.checked;
    const normalized = parseTaskSubtaskList(current);
    writeTaskSubtasksField(taskDetailContext.subtasksField, normalized);
    renderTaskRowSubtasksProgress(taskDetailContext.taskItem, normalized);
    renderTaskSubtasksViewList({
      subtasks: normalized,
      readOnly: Boolean(taskDetailContext.readOnly),
      editable: true,
    });
    scheduleTaskAutosave(taskDetailContext.form, 60);
  });

  const syncTaskDetailRevisionActionButtons = ({ isEditing = false } = {}) => {
    const hasRequestButton = taskDetailRequestRevisionButton instanceof HTMLButtonElement;
    const hasRemoveButton = taskDetailRemoveRevisionButton instanceof HTMLButtonElement;
    if (!hasRequestButton && !hasRemoveButton) return;

    const statusValue = String(taskDetailContext?.statusSelect?.value || "").trim();
    const canUseRevisionActions =
      !isEditing &&
      Boolean(taskDetailContext) &&
      !Boolean(taskDetailContext?.readOnly) &&
      statusValue === "review";

    const currentDescription = String(taskDetailContext?.descriptionField?.value || "").trim();
    const history = readTaskHistoryField(taskDetailContext?.historyField);
    const hasActiveRevision = hasActiveTaskRevisionRequest({
      description: currentDescription,
      history,
    });

    if (hasRequestButton) {
      taskDetailRequestRevisionButton.hidden = !canUseRevisionActions;
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

    if (isEditing) {
      window.setTimeout(() => {
        syncTaskDetailDescriptionEditorFromTextarea();
        syncReferenceTextareaLayout(taskDetailEditLinks);
        renderTaskDetailImageList();
        if (taskDetailEditDescriptionToolbar instanceof HTMLElement) {
          taskDetailEditDescriptionToolbar.hidden = true;
        }
        taskDetailEditTitle?.focus();
      }, 20);
    } else if (taskDetailEditDescriptionToolbar instanceof HTMLElement) {
      taskDetailEditDescriptionToolbar.hidden = true;
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
    const referenceLinksField = form.querySelector('[data-task-reference-links-json]');
    const referenceImagesField = form.querySelector('[data-task-reference-images-json]');
    const subtasksField = form.querySelector("[data-task-subtasks-json]");
    const overdueFlagField = form.querySelector("[data-task-overdue-flag]");
    const overdueSinceDateField = form.querySelector("[data-task-overdue-since-date]");
    const overdueDaysField = form.querySelector("[data-task-overdue-days]");
    const historyField = form.querySelector("[data-task-history-json]");
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
      referenceLinksField: referenceLinksField instanceof HTMLInputElement ? referenceLinksField : null,
      referenceImagesField: referenceImagesField instanceof HTMLInputElement ? referenceImagesField : null,
      subtasksField: subtasksField instanceof HTMLInputElement ? subtasksField : null,
      overdueFlagField: overdueFlagField instanceof HTMLInputElement ? overdueFlagField : null,
      overdueSinceDateField:
        overdueSinceDateField instanceof HTMLInputElement ? overdueSinceDateField : null,
      overdueDaysField: overdueDaysField instanceof HTMLInputElement ? overdueDaysField : null,
      historyField: historyField instanceof HTMLInputElement ? historyField : null,
      metaRow,
      readOnly,
    };
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

    updateAssigneePickerSummary(taskDetailEditAssignees);
  };

  const getCheckedAssigneeNames = (picker) => {
    if (!(picker instanceof HTMLElement)) return [];
    return Array.from(picker.querySelectorAll('input[type="checkbox"]:checked'))
      .map((checkbox) => checkbox.closest("label")?.querySelector("span")?.textContent?.trim())
      .filter(Boolean);
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

  const syncTaskDetailViewStatusTag = (statusValue, statusLabel) => {
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
    taskDetailViewStatus.classList.add(`status-${normalizedStatus}`);
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
      referenceLinksField,
      referenceImagesField,
      subtasksField,
      overdueFlagField,
      overdueSinceDateField,
      overdueDaysField,
      historyField,
      metaRow,
    } = context;

    const titleValue = (titleInput.value || "").trim() || "Tarefa";
    const statusLabel =
      statusSelect.options[statusSelect.selectedIndex]?.textContent?.trim() || statusSelect.value || "Status";
    const groupLabel =
      groupSelect.options[groupSelect.selectedIndex]?.textContent?.trim() || groupSelect.value || "Geral";
    const dueMeta = dueDateMeta(dueDateInput.value || "");
    const titleTag = normalizeTaskTitleTagValue(titleTagField?.value || "");
    const assigneeNames = getCheckedAssigneeNames(rowAssigneePicker);
    const description = (descriptionField.value || "").trim();
    const referenceLinks = readJsonUrlListField(referenceLinksField, parseReferenceUrlLines);
    const referenceImages = readJsonUrlListField(referenceImagesField, parseReferenceImageItems);
    const subtasks = readTaskSubtasksField(subtasksField);
    const overdueFlag =
      overdueFlagField instanceof HTMLInputElement && overdueFlagField.value === "1" ? 1 : 0;
    const overdueSinceDate =
      overdueSinceDateField instanceof HTMLInputElement ? overdueSinceDateField.value || "" : "";
    const overdueDays =
      overdueDaysField instanceof HTMLInputElement
        ? Math.max(0, Number.parseInt(overdueDaysField.value || "0", 10) || 0)
        : 0;
    const history = readTaskHistoryField(historyField);
    const metaSpans = metaRow ? Array.from(metaRow.querySelectorAll("span")) : [];
    const createdByText = metaSpans[0]?.textContent?.trim() || "";
    const updatedAtText = metaRow?.querySelector("[data-task-updated-at]")?.textContent?.trim() || "";

    if (taskDetailTitle) taskDetailTitle.textContent = titleValue;
    syncTaskTitleTagBadge(context.taskItem, titleTag);
    syncTaskDetailViewTitleTag(titleTag);
    syncTaskDetailViewStatusTag(statusSelect.value || "todo", statusLabel);
    syncTaskDetailViewPriorityTag(prioritySelect.value || "medium");
    if (taskDetailViewGroup) taskDetailViewGroup.textContent = groupLabel;
    if (taskDetailViewDue) taskDetailViewDue.textContent = dueMeta.display;
    if (taskDetailViewAssignees) {
      taskDetailViewAssignees.textContent = assigneeNames.length
        ? `Responsaveis: ${assigneeNames.join(", ")}`
        : "Sem responsavel";
    }
    renderTaskDetailDescriptionView({ description, history });
    renderTaskSubtasksViewList({
      subtasks,
      readOnly: Boolean(context.readOnly),
      editable: true,
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
    syncTaskTitleTagEditorControls({
      selectEl: taskDetailEditTitleTagSelect,
      customInputEl: taskDetailEditTitleTagCustom,
      currentTag: titleTag,
    });
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
    }
    if (taskDetailEditDueDate instanceof HTMLInputElement) {
      taskDetailEditDueDate.value = dueDateInput.value || "";
    }
    if (taskDetailEditDescription instanceof HTMLTextAreaElement) {
      taskDetailEditDescription.value = descriptionField.value || "";
      syncTaskDetailDescriptionEditorFromTextarea();
    }
    if (taskDetailEditLinks instanceof HTMLTextAreaElement) {
      taskDetailEditLinks.value = referenceLinks.join("\n");
      syncReferenceTextareaLayout(taskDetailEditLinks);
    }
    setTaskDetailEditSubtasks(subtasks);
    renderTaskDetailSubtasksEditList();
    setTaskDetailEditImageItems(referenceImages);
    copyAssigneesToTaskDetailModal(rowAssigneePicker);
    syncTaskDetailRevisionActionButtons({
      isEditing: Boolean(taskDetailModal?.classList.contains("is-editing")),
    });
  };

  const openTaskDetailModal = (taskItem) => {
    if (!taskDetailModal) return;
    const bindings = getTaskDetailBindings(taskItem);
    if (!bindings) return;

    taskDetailContext = bindings;
    populateTaskDetailModalFromRow(bindings);
    setTaskDetailEditMode(false);
    taskDetailModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      const closeButton = taskDetailModal.querySelector(".modal-close-button[data-close-task-detail-modal]");
      if (closeButton instanceof HTMLElement) closeButton.focus();
    }, 20);
  };

  const closeTaskDetailModal = () => {
    if (!taskDetailModal) return;
    closeTaskReviewModal();
    closeTaskImagePreview();
    taskDetailModal.hidden = true;
    taskDetailContext = null;
    taskDetailEditSubtaskItems = [];
    setTaskDetailEditMode(false);
    if (taskDetailSaveButton instanceof HTMLButtonElement) {
      taskDetailSaveButton.disabled = false;
      taskDetailSaveButton.classList.remove("is-loading");
      taskDetailSaveButton.textContent = "Salvar";
    }
    syncBodyModalLock();
  };

  const copyTaskDetailModalToRow = (context = taskDetailContext) => {
    if (!context) return false;
    if (context.readOnly) {
      showClientFlash("error", "Voce nao possui acesso para editar tarefas deste grupo.");
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

    const nextTitleTag = resolveTaskTitleTagFromEditor({
      selectEl: taskDetailEditTitleTagSelect,
      customInputEl: taskDetailEditTitleTagCustom,
    });
    if (
      taskDetailEditTitleTagSelect instanceof HTMLSelectElement &&
      taskDetailEditTitleTagSelect.value === TASK_TITLE_TAG_CUSTOM_VALUE &&
      taskDetailEditTitleTagCustom instanceof HTMLInputElement
    ) {
      taskDetailEditTitleTagCustom.value = nextTitleTag;
    }

    context.titleInput.value = taskDetailEditTitle.value;
    if (context.titleTagField instanceof HTMLInputElement) {
      context.titleTagField.value = nextTitleTag;
    }
    syncTaskTitleTagBadge(context.taskItem, nextTitleTag);
    context.statusSelect.value = taskDetailEditStatus.value;
    context.prioritySelect.value = taskDetailEditPriority.value;
    context.dueDateInput.value = taskDetailEditDueDate.value;
    context.descriptionField.value = taskDetailEditDescription.value;
    if (context.referenceLinksField instanceof HTMLInputElement && taskDetailEditLinks instanceof HTMLTextAreaElement) {
      writeJsonUrlListField(
        context.referenceLinksField,
        parseReferenceUrlLines(taskDetailEditLinks.value),
        parseReferenceUrlLines
      );
    }
    if (context.referenceImagesField instanceof HTMLInputElement) {
      const referenceImages = parseReferenceImageItems(taskDetailEditImageItems);
      writeJsonUrlListField(context.referenceImagesField, referenceImages, parseReferenceImageItems);
    }
    if (context.subtasksField instanceof HTMLInputElement) {
      const normalizedSubtasks = parseTaskSubtaskList(taskDetailEditSubtaskItems);
      writeTaskSubtasksField(context.subtasksField, normalizedSubtasks);
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
    updateAssigneePickerSummary(context.rowAssigneePicker);

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
      showClientFlash("error", "Voce nao possui acesso para solicitar ajuste nesta tarefa.");
      return;
    }

    const statusValue = String(taskDetailContext.statusSelect?.value || "").trim();
    if (statusValue !== "review") {
      showClientFlash("error", "A solicitacao de ajuste so esta disponivel para tarefas em revisao.");
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
      if (Array.isArray(task.history) && taskDetailContext.historyField instanceof HTMLInputElement) {
        writeTaskHistoryField(taskDetailContext.historyField, task.history);
      }
      if (typeof task.updated_at_label === "string") {
        refreshTaskUpdatedAtMeta(taskDetailContext.form, task.updated_at_label);
      }
      if (typeof task.status === "string" && taskDetailContext.statusSelect instanceof HTMLSelectElement) {
        taskDetailContext.statusSelect.value = task.status;
        syncSelectColor(taskDetailContext.statusSelect);
      }

      renderDashboardSummary(data.dashboard);
      populateTaskDetailModalFromRow(taskDetailContext);
      setTaskDetailEditMode(false);
      closeTaskReviewModal();
      showClientFlash("success", "Ajuste solicitado na tarefa.");
    } catch (error) {
      showClientFlash(
        "error",
        error instanceof Error ? error.message : "Nao foi possivel solicitar ajuste na tarefa."
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
    if (!(taskDetailContext?.form instanceof HTMLFormElement)) return;
    if (Boolean(taskDetailContext.readOnly)) {
      showClientFlash("error", "Voce nao possui acesso para remover ajuste desta tarefa.");
      return;
    }

    const taskIdField = taskDetailContext.form.querySelector('input[name="task_id"]');
    const csrfField = taskDetailContext.form.querySelector('input[name="csrf_token"]');
    if (!(taskIdField instanceof HTMLInputElement) || !(csrfField instanceof HTMLInputElement)) {
      return;
    }

    taskRemoveRevisionTaskIdInput.value = taskIdField.value;
    const removeCsrfField = taskRemoveRevisionForm.querySelector('input[name="csrf_token"]');
    if (removeCsrfField instanceof HTMLInputElement) {
      removeCsrfField.value = csrfField.value;
    }

    if (taskDetailContext.form.dataset.autosaveSubmitting === "1") {
      const idle = await waitForFormAutosaveIdle(taskDetailContext.form);
      if (!idle) {
        showClientFlash("error", "Aguarde a tarefa terminar de salvar para remover o ajuste.");
        return;
      }
    }

    if (taskDetailRemoveRevisionButton instanceof HTMLButtonElement) {
      taskDetailRemoveRevisionButton.disabled = true;
      taskDetailRemoveRevisionButton.classList.add("is-loading");
      taskDetailRemoveRevisionButton.setAttribute("aria-busy", "true");
    }

    try {
      const data = await postFormJson(taskRemoveRevisionForm);
      const task = data.task || {};

      if (typeof task.description === "string") {
        taskDetailContext.descriptionField.value = task.description;
      }
      if (Array.isArray(task.history) && taskDetailContext.historyField instanceof HTMLInputElement) {
        writeTaskHistoryField(taskDetailContext.historyField, task.history);
      }
      if (typeof task.updated_at_label === "string") {
        refreshTaskUpdatedAtMeta(taskDetailContext.form, task.updated_at_label);
      }
      if (typeof task.status === "string" && taskDetailContext.statusSelect instanceof HTMLSelectElement) {
        taskDetailContext.statusSelect.value = task.status;
        syncSelectColor(taskDetailContext.statusSelect);
      }

      renderDashboardSummary(data.dashboard);
      populateTaskDetailModalFromRow(taskDetailContext);
      setTaskDetailEditMode(false);
      showClientFlash("success", "Solicitacao de ajuste removida.");
    } catch (error) {
      showClientFlash(
        "error",
        error instanceof Error ? error.message : "Nao foi possivel remover a solicitacao de ajuste."
      );
    } finally {
      if (taskDetailRemoveRevisionButton instanceof HTMLButtonElement) {
        taskDetailRemoveRevisionButton.disabled = false;
        taskDetailRemoveRevisionButton.classList.remove("is-loading");
        taskDetailRemoveRevisionButton.removeAttribute("aria-busy");
      }
    }
  };

  const saveTaskDetailModal = async () => {
    if (!taskDetailContext) return;
    if (!copyTaskDetailModalToRow(taskDetailContext)) return;
    setTaskDetailEditMode(false);

    if (taskDetailSaveButton instanceof HTMLButtonElement) {
      taskDetailSaveButton.disabled = true;
      taskDetailSaveButton.classList.add("is-loading");
      taskDetailSaveButton.textContent = "Salvando";
    }

    if (taskDetailContext.form.dataset.autosaveSubmitting === "1") {
      const idle = await waitForFormAutosaveIdle(taskDetailContext.form);
      if (!idle) {
        if (taskDetailSaveButton instanceof HTMLButtonElement) {
          taskDetailSaveButton.disabled = false;
          taskDetailSaveButton.classList.remove("is-loading");
          taskDetailSaveButton.textContent = "Salvar";
        }
        setTaskDetailEditMode(true);
        return;
      }
    }

    const ok = await submitTaskAutosave(taskDetailContext.form);

    if (taskDetailSaveButton instanceof HTMLButtonElement) {
      taskDetailSaveButton.disabled = false;
      taskDetailSaveButton.classList.remove("is-loading");
      taskDetailSaveButton.textContent = "Salvar";
    }

    if (!ok) {
      setTaskDetailEditMode(true);
      return;
    }

    populateTaskDetailModalFromRow(taskDetailContext);
    setTaskDetailEditMode(false);
  };

  const syncBodyModalLock = () => {
    const hasOpenModal = [
      createTaskModal,
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
    const titleTagValue =
      titleTagField instanceof HTMLInputElement ? titleTagField.value || "" : "";
    syncTaskTitleTagBadge(taskItem, titleTagValue);
  });

  const openCreateModal = (groupName) => {
    if (!createTaskModal) return;
    setFabMenuOpen(false);
    syncTaskGroupInputs();
    if (createTaskGroupInput instanceof HTMLSelectElement && createTaskGroupInput.disabled) {
      return;
    }
    if (createTaskForm) {
      createTaskForm.reset();
      setCreateTaskImageItems([]);
      setCreateTaskSubtasks([]);
      renderCreateTaskSubtasksEditList();
      if (createTaskLinksField instanceof HTMLTextAreaElement) {
        createTaskLinksField.value = "";
      }
      createTaskForm
        .querySelectorAll(".assignee-picker")
        .forEach(updateAssigneePickerSummary);
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
    }
    createTaskModal.hidden = false;
    syncBodyModalLock();
    window.setTimeout(() => {
      createTaskTitleInput?.focus();
    }, 20);
  };

  const closeCreateModal = () => {
    if (!createTaskModal) return;
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

  const openCreateGroupModal = () => {
    if (!createGroupModal) return;
    setFabMenuOpen(false);
    if (createGroupForm) {
      createGroupForm.reset();
    }
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
      dueEntryFixedDateField.value = todayIsoDate();
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
      dueEntryEditFixedDateField.value = dueDate;
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

    const dashboardViewToggle = target.closest("[data-dashboard-view-toggle]");
    if (dashboardViewToggle instanceof HTMLElement) {
      const targetView = normalizeDashboardView(dashboardViewToggle.dataset.view || "tasks");
      setDashboardView(targetView, { updateHash: true });
      return;
    }

    const openWorkspaceCreateTrigger = target.closest("[data-open-workspace-create-modal]");
    if (openWorkspaceCreateTrigger) {
      openWorkspaceCreateModal();
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
            deleteForm.submit();
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
            deleteForm.submit();
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
            deleteForm.submit();
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
      openTaskImagePreview(previewImageTrigger.dataset.taskRefImagePreview || "");
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
        populateTaskDetailModalFromRow(taskDetailContext);
        setTaskDetailEditMode(true);
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
        message: "Remover a solicitacao de ajuste atual e restaurar a descricao anterior?",
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

    if (event.key !== "Escape") return;

    if (fabWrap?.classList.contains("is-open")) {
      setFabMenuOpen(false);
    }
    if (createTaskModal && !createTaskModal.hidden) {
      closeCreateModal();
    }
    if (workspaceCreateModal && !workspaceCreateModal.hidden) {
      closeWorkspaceCreateModal();
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

      const removeTagTrigger = target.closest("[data-create-task-title-tag-remove]");
      if (removeTagTrigger instanceof HTMLButtonElement) {
        const removedTag = normalizeTaskTitleTagValue(
          removeTagTrigger.dataset.createTaskTitleTagRemove || ""
        );
        if (!removedTag) return;

        const removedKey = removedTag.toLocaleLowerCase("pt-BR");
        createTaskTitleTagOptions = createTaskTitleTagOptions.filter(
          (tag) => normalizeTaskTitleTagValue(tag).toLocaleLowerCase("pt-BR") !== removedKey
        );
        if (createTaskCurrentTitleTag.toLocaleLowerCase("pt-BR") === removedKey) {
          createTaskCurrentTitleTag = "";
        }
        syncCreateTaskTitleTagTrigger();
        renderCreateTaskTitleTagMenu();
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

  if (taskDetailEditTitleTagSelect instanceof HTMLSelectElement) {
    taskDetailEditTitleTagSelect.addEventListener("change", () => {
      const isCustom = taskDetailEditTitleTagSelect.value === TASK_TITLE_TAG_CUSTOM_VALUE;
      if (taskDetailEditTitleTagCustom instanceof HTMLInputElement) {
        taskDetailEditTitleTagCustom.hidden = !isCustom;
        if (!isCustom) {
          taskDetailEditTitleTagCustom.value = "";
        } else {
          taskDetailEditTitleTagCustom.focus();
        }
      }
    });
  }

  if (createTaskForm) {
    createTaskForm.addEventListener("submit", () => {
      if (createTaskTitleInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(createTaskTitleInput);
      }
      const createTitleTag = createTaskTitleTagIsCreating
        ? commitCreateTaskTitleTagCreation()
        : normalizeTaskTitleTagValue(createTaskTitleTagInput?.value || createTaskCurrentTitleTag);
      setCreateTaskTitleTagValue(createTitleTag);

      if (createTaskLinksField instanceof HTMLTextAreaElement) {
        createTaskLinksField.value = JSON.stringify(
          parseReferenceUrlLines(createTaskLinksField.value || "")
        );
      }

      if (createTaskImagesField instanceof HTMLTextAreaElement) {
        createTaskImagesField.value = JSON.stringify(
          parseReferenceImageItems(createTaskImageItems || [])
        );
      }
      if (createTaskSubtasksField instanceof HTMLTextAreaElement) {
        createTaskSubtasksField.value = JSON.stringify(
          parseTaskSubtaskList(createTaskSubtaskItems || [])
        );
      }

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
    const params = new URLSearchParams();
    const groupField = form.querySelector('select[name="group"]');
    const assigneeField = form.querySelector('select[name="assignee"]');

    if (groupField instanceof HTMLSelectElement && (groupField.value || "").trim() !== "") {
      params.set("group", groupField.value.trim());
    }
    if (assigneeField instanceof HTMLSelectElement && (assigneeField.value || "").trim() !== "") {
      params.set("assignee", assigneeField.value.trim());
    }

    const query = params.toString();
    const target = query ? `index.php?${query}#tasks` : "index.php#tasks";
    window.location.assign(target);
  };

  if (taskFilterForm instanceof HTMLFormElement) {
    taskFilterForm.addEventListener("submit", (event) => {
      event.preventDefault();
      applyTaskFilterForm(taskFilterForm);
    });

    taskFilterForm.addEventListener("change", (event) => {
      const target = event.target;
      if (!(target instanceof Element)) return;
      const select = target.closest('select[name="group"], select[name="assignee"]');
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
    vaultGroupForm.addEventListener("submit", () => {
      if (vaultGroupNameInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(vaultGroupNameInput);
      }
      syncBodyModalLock();
    });
  }

  if (dueGroupForm instanceof HTMLFormElement) {
    dueGroupForm.addEventListener("submit", () => {
      if (dueGroupNameInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(dueGroupNameInput);
      }
      syncBodyModalLock();
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
    vaultEntryForm.addEventListener("submit", () => {
      if (vaultEntryLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(vaultEntryLabelField);
      }
      syncBodyModalLock();
    });
  }

  if (dueEntryForm instanceof HTMLFormElement) {
    dueEntryForm.addEventListener("submit", () => {
      if (dueEntryLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(dueEntryLabelField);
      }
      syncDueCreateRecurrenceFields();
      if (dueEntryMonthlyDayField instanceof HTMLInputElement) {
        dueEntryMonthlyDayField.value = normalizeDueMonthlyDayInput(dueEntryMonthlyDayField.value);
      }
      syncBodyModalLock();
    });
  }

  if (inventoryEntryForm instanceof HTMLFormElement) {
    inventoryEntryForm.addEventListener("submit", () => {
      if (inventoryEntryLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(inventoryEntryLabelField);
      }
      if (inventoryEntryQuantityField instanceof HTMLInputElement) {
        const normalizedQuantity = normalizeInventoryIntegerInput(inventoryEntryQuantityField.value);
        if (normalizedQuantity !== null) {
          inventoryEntryQuantityField.value = normalizedQuantity;
        }
      }
      if (inventoryEntryMinQuantityField instanceof HTMLInputElement) {
        const normalizedMinQuantity = normalizeInventoryIntegerInput(
          inventoryEntryMinQuantityField.value,
          { allowEmpty: true }
        );
        if (normalizedMinQuantity !== null) {
          inventoryEntryMinQuantityField.value = normalizedMinQuantity;
        }
      }
      if (inventoryEntryUnitField instanceof HTMLInputElement) {
        const normalizedUnit = String(inventoryEntryUnitField.value || "").trim().toLowerCase();
        inventoryEntryUnitField.value = normalizedUnit || "un";
      }
      syncBodyModalLock();
    });
  }

  if (vaultEntryEditForm instanceof HTMLFormElement) {
    vaultEntryEditForm.addEventListener("submit", () => {
      if (vaultEntryEditLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(vaultEntryEditLabelField);
      }
      syncBodyModalLock();
    });
  }

  if (dueEntryEditForm instanceof HTMLFormElement) {
    dueEntryEditForm.addEventListener("submit", () => {
      if (dueEntryEditLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(dueEntryEditLabelField);
      }
      syncDueEditRecurrenceFields();
      if (dueEntryEditMonthlyDayField instanceof HTMLInputElement) {
        dueEntryEditMonthlyDayField.value = normalizeDueMonthlyDayInput(
          dueEntryEditMonthlyDayField.value
        );
      }
      syncBodyModalLock();
    });
  }

  if (inventoryEntryEditForm instanceof HTMLFormElement) {
    inventoryEntryEditForm.addEventListener("submit", () => {
      if (inventoryEntryEditLabelField instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(inventoryEntryEditLabelField);
      }
      if (inventoryEntryEditQuantityField instanceof HTMLInputElement) {
        const normalizedQuantity = normalizeInventoryIntegerInput(inventoryEntryEditQuantityField.value);
        if (normalizedQuantity !== null) {
          inventoryEntryEditQuantityField.value = normalizedQuantity;
        }
      }
      if (inventoryEntryEditMinQuantityField instanceof HTMLInputElement) {
        const normalizedMinQuantity = normalizeInventoryIntegerInput(
          inventoryEntryEditMinQuantityField.value,
          { allowEmpty: true }
        );
        if (normalizedMinQuantity !== null) {
          inventoryEntryEditMinQuantityField.value = normalizedMinQuantity;
        }
      }
      if (inventoryEntryEditUnitField instanceof HTMLInputElement) {
        const normalizedUnit = String(inventoryEntryEditUnitField.value || "").trim().toLowerCase();
        inventoryEntryEditUnitField.value = normalizedUnit || "un";
      }
      syncBodyModalLock();
    });
  }

  document.querySelectorAll("[data-vault-entry-name-form]").forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      void submitVaultEntryNameForm(form);
    });
  });
});

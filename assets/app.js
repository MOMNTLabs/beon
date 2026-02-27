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

  const uppercaseRequiredInputSelector = [
    ".task-title-input",
    "[data-create-task-title-input]",
    "[data-task-detail-edit-title]",
    "[data-group-name-input]",
    "[data-create-group-name-input]",
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

  document.addEventListener("change", (event) => {
    const select = event.target.closest(".status-select, .priority-select");
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
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
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
    const checkbox = event.target.closest('.assignee-picker input[type="checkbox"]');
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

  const setTaskGroupCollapsed = (groupSection, collapsed) => {
    if (!(groupSection instanceof HTMLElement)) return;
    const dropzone = groupSection.querySelector("[data-task-dropzone]");
    const toggleButton = groupSection.querySelector("[data-group-toggle]");
    const shouldCollapse = Boolean(collapsed);

    groupSection.classList.toggle("is-collapsed", shouldCollapse);
    if (dropzone instanceof HTMLElement) {
      dropzone.hidden = shouldCollapse;
    }
    if (toggleButton instanceof HTMLButtonElement) {
      toggleButton.setAttribute("aria-expanded", shouldCollapse ? "false" : "true");
      toggleButton.setAttribute(
        "aria-label",
        shouldCollapse ? "Expandir grupo" : "Retrair grupo"
      );
    }
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

  const clearDropzoneHighlight = () => {
    document
      .querySelectorAll(".task-list-rows.is-drop-target")
      .forEach((zone) => zone.classList.remove("is-drop-target"));
    activeDropzone = null;
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
    if (draggedTaskItem) {
      draggedTaskItem.classList.remove("is-dragging", "drag-ghost");
    }
    draggedTaskItem = null;
    clearDropzoneHighlight();
  });

  document.addEventListener("dragover", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement) || !draggedTaskItem) return;

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
    if (!(target instanceof HTMLElement) || !draggedTaskItem) return;

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
    const statusStepButton = event.target.closest("[data-status-step]");
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

    const inlineSelectOption = event.target.closest("[data-inline-select-option]");
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

    const dueDisplay = event.target.closest("[data-due-date-display]");
    const overdueBadgeTrigger = event.target.closest("[data-task-overdue-badge]");
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

    const deleteButton = event.target.closest(".task-row-delete");
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

    const groupDeleteButton = event.target.closest("[data-group-delete]");
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
          ? `Remover o grupo ${groupName}? As tarefas serao movidas para ${getDefaultGroupName()}.`
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

    const groupToggleButton = event.target.closest("[data-group-toggle]");
    if (groupToggleButton) {
      const groupSection = groupToggleButton.closest("[data-task-group]");
      if (groupSection instanceof HTMLElement) {
        const isExpanded = groupToggleButton.getAttribute("aria-expanded") !== "false";
        setTaskGroupCollapsed(groupSection, isExpanded);
      }
      return;
    }

    const taskItem = event.target.closest("[data-task-item]");
    if (!(taskItem instanceof HTMLElement)) return;

    const toggleButton = event.target.closest("[data-task-expand]");
    if (toggleButton) {
      openTaskDetailModal(taskItem);
      return;
    }

    const interactiveTarget = event.target.closest(
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

    if (interactiveTarget && taskItem.contains(interactiveTarget)) {
      return;
    }

    openTaskDetailModal(taskItem);
  });

  const fabWrap = document.querySelector("[data-task-fab-wrap]");
  const fabToggleButton = document.querySelector("[data-task-fab-toggle]");
  const fabMenu = document.querySelector("[data-task-fab-menu]");
  const taskGroupsDatalist = document.querySelector("#task-group-options");
  const taskFilterForm = document.querySelector("[data-task-filter-form]");

  const setFabMenuOpen = (open) => {
    if (!fabWrap || !fabToggleButton || !fabMenu) return;
    fabWrap.classList.toggle("is-open", open);
    fabToggleButton.setAttribute("aria-expanded", open ? "true" : "false");
    fabMenu.setAttribute("aria-hidden", open ? "false" : "true");
  };

  const createTaskModal = document.querySelector("[data-create-modal]");
  const createTaskGroupInput = document.querySelector("[data-create-task-group-input]");
  const createTaskTitleInput = document.querySelector("[data-create-task-title-input]");
  const createTaskForm = document.querySelector("[data-create-task-form]");
  const createGroupModal = document.querySelector("[data-create-group-modal]");
  const createGroupNameInput = document.querySelector("[data-create-group-name-input]");
  const createGroupForm = document.querySelector("[data-create-group-form]");
  const taskDetailModal = document.querySelector("[data-task-detail-modal]");
  const taskDetailTitle = document.querySelector("[data-task-detail-title]");
  const taskDetailViewPanel = document.querySelector("[data-task-detail-view]");
  const taskDetailEditPanel = document.querySelector("[data-task-detail-edit-panel]");
  const taskDetailViewTitle = document.querySelector("[data-task-detail-view-title]");
  const taskDetailViewStatus = document.querySelector("[data-task-detail-view-status]");
  const taskDetailViewPriority = document.querySelector("[data-task-detail-view-priority]");
  const taskDetailViewGroup = document.querySelector("[data-task-detail-view-group]");
  const taskDetailViewDue = document.querySelector("[data-task-detail-view-due]");
  const taskDetailViewAssignees = document.querySelector("[data-task-detail-view-assignees]");
  const taskDetailViewDescription = document.querySelector("[data-task-detail-view-description]");
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
  const taskDetailEditLinks = document.querySelector("[data-task-detail-edit-links]");
  const taskDetailEditImages = document.querySelector("[data-task-detail-edit-images]");
  const taskDetailImagePicker = document.querySelector("[data-task-detail-image-picker]");
  const taskDetailImageInput = document.querySelector("[data-task-detail-image-input]");
  const taskDetailImageAddButton = document.querySelector("[data-task-detail-image-add]");
  const taskDetailImageList = document.querySelector("[data-task-detail-image-list]");
  const taskDetailEditAssignees = document.querySelector("[data-task-detail-edit-assignees]");
  const taskDetailEditAssigneesMenu = document.querySelector("[data-task-detail-edit-assignees-menu]");
  const taskDetailEditButton = document.querySelector("[data-task-detail-edit]");
  const taskDetailSaveButton = document.querySelector("[data-task-detail-save]");
  const taskDetailDeleteButton = document.querySelector("[data-task-detail-delete]");
  const taskDetailCancelEditButton = document.querySelector("[data-task-detail-cancel-edit]");
  const confirmModal = document.querySelector("[data-confirm-modal]");
  const confirmModalTitle = document.querySelector("#confirm-modal-title");
  const confirmModalMessage = document.querySelector("[data-confirm-modal-message]");
  const confirmModalSubmit = document.querySelector("[data-confirm-modal-submit]");
  let confirmModalAction = null;
  let taskDetailContext = null;
  let taskDetailEditImageItems = [];

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

    const firstGroupSection = document.querySelector("[data-task-group]");
    const firstGroupName = firstGroupSection?.dataset?.groupName?.trim();
    if (firstGroupName) return firstGroupName;

    if (createTaskGroupInput instanceof HTMLSelectElement && createTaskGroupInput.options.length > 0) {
      const optionName = createTaskGroupInput.options[0]?.value?.trim();
      if (optionName) return optionName;
    }

    return "Geral";
  };

  const syncTaskDetailImageHiddenField = () => {
    if (taskDetailEditImages instanceof HTMLTextAreaElement) {
      taskDetailEditImages.value = taskDetailEditImageItems.join("\n");
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

  if (taskDetailImageAddButton instanceof HTMLButtonElement && taskDetailImageInput instanceof HTMLInputElement) {
    taskDetailImageAddButton.addEventListener("click", () => {
      taskDetailImageInput.click();
    });
  }

  if (taskDetailImageInput instanceof HTMLInputElement) {
    taskDetailImageInput.addEventListener("change", () => {
      const files = Array.from(taskDetailImageInput.files || []);
      taskDetailImageInput.value = "";
      void addTaskDetailImagesFromFiles(files);
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

  document.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
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

  const setTaskDetailEditMode = (editing) => {
    if (!taskDetailModal) return;
    const isEditing = Boolean(editing);
    taskDetailModal.classList.toggle("is-editing", isEditing);
    if (taskDetailViewPanel instanceof HTMLElement) {
      taskDetailViewPanel.hidden = isEditing;
    }
    if (taskDetailEditPanel instanceof HTMLElement) {
      taskDetailEditPanel.hidden = !isEditing;
    }
    if (taskDetailEditButton instanceof HTMLButtonElement) {
      taskDetailEditButton.hidden = isEditing;
    }
    if (taskDetailSaveButton instanceof HTMLButtonElement) {
      taskDetailSaveButton.hidden = !isEditing;
    }
    if (taskDetailCancelEditButton instanceof HTMLButtonElement) {
      taskDetailCancelEditButton.hidden = !isEditing;
    }

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
    const referenceLinksField = form.querySelector('[data-task-reference-links-json]');
    const referenceImagesField = form.querySelector('[data-task-reference-images-json]');
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
      referenceLinksField: referenceLinksField instanceof HTMLInputElement ? referenceLinksField : null,
      referenceImagesField: referenceImagesField instanceof HTMLInputElement ? referenceImagesField : null,
      overdueFlagField: overdueFlagField instanceof HTMLInputElement ? overdueFlagField : null,
      overdueSinceDateField:
        overdueSinceDateField instanceof HTMLInputElement ? overdueSinceDateField : null,
      overdueDaysField: overdueDaysField instanceof HTMLInputElement ? overdueDaysField : null,
      historyField: historyField instanceof HTMLInputElement ? historyField : null,
      metaRow,
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
      referenceLinksField,
      referenceImagesField,
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
    const assigneeNames = getCheckedAssigneeNames(rowAssigneePicker);
    const description = (descriptionField.value || "").trim();
    const referenceLinks = readJsonUrlListField(referenceLinksField, parseReferenceUrlLines);
    const referenceImages = readJsonUrlListField(referenceImagesField, parseReferenceImageItems);
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
    syncTaskDetailViewStatusTag(statusSelect.value || "todo", statusLabel);
    syncTaskDetailViewPriorityTag(prioritySelect.value || "medium");
    if (taskDetailViewGroup) taskDetailViewGroup.textContent = groupLabel;
    if (taskDetailViewDue) taskDetailViewDue.textContent = dueMeta.display;
    if (taskDetailViewAssignees) {
      taskDetailViewAssignees.textContent = assigneeNames.length
        ? `Responsaveis: ${assigneeNames.join(", ")}`
        : "Sem responsavel";
    }
    if (taskDetailViewDescription) {
      if (description) {
        taskDetailViewDescription.innerHTML = formatTaskDescriptionHtml(description);
      } else {
        taskDetailViewDescription.textContent = "Sem descricao.";
      }
      taskDetailViewDescription.classList.toggle("is-empty", !description);
    }
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
    setTaskDetailEditImageItems(referenceImages);
    copyAssigneesToTaskDetailModal(rowAssigneePicker);
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
    closeTaskImagePreview();
    taskDetailModal.hidden = true;
    taskDetailContext = null;
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
    syncTaskDetailDescriptionTextareaFromEditor();

    context.titleInput.value = taskDetailEditTitle.value;
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
      createGroupModal,
      taskDetailModal,
      taskImagePreviewModal,
      confirmModal,
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
      const movedTaskCount = Number.parseInt(data?.moved_task_count, 10) || 0;
      const movedToGroup = (data?.moved_to_group || "Geral").trim() || "Geral";

      if (groupSection instanceof HTMLElement && movedTaskCount > 0) {
        const sourceDropzone = groupSection.querySelector("[data-task-dropzone]");
        const targetDropzone = document.querySelector(
          `[data-task-dropzone][data-group-name="${CSS.escape(movedToGroup)}"]`
        );

        if (
          sourceDropzone instanceof HTMLElement &&
          targetDropzone instanceof HTMLElement &&
          targetDropzone !== sourceDropzone
        ) {
          const movedItems = Array.from(
            sourceDropzone.querySelectorAll("[data-task-item]")
          );

          movedItems.forEach((taskItem) => {
            if (!(taskItem instanceof HTMLElement)) return;

            taskItem.dataset.groupName = movedToGroup;
            const binding = getTaskGroupField(taskItem);
            if (binding?.field instanceof HTMLSelectElement) {
              if (!Array.from(binding.field.options).some((opt) => opt.value === movedToGroup)) {
                const option = document.createElement("option");
                option.value = movedToGroup;
                option.textContent = movedToGroup;
                binding.field.append(option);
              }
              binding.field.value = movedToGroup;
            } else if (binding?.field instanceof HTMLInputElement) {
              binding.field.value = movedToGroup;
            }

            targetDropzone.append(taskItem);
          });

          const targetSection = targetDropzone.closest("[data-task-group]");
          refreshTaskGroupSection(targetSection);
        } else if (movedTaskCount > 0) {
          window.location.reload();
          return;
        }
      }

      if (groupSection instanceof HTMLElement) {
        groupSection.remove();
      }

      if (typeof syncTaskGroupInputs === "function") {
        syncTaskGroupInputs();
      }
      showClientFlash(
        "success",
        movedTaskCount > 0
          ? `Grupo ${groupName} removido. ${movedTaskCount} tarefa(s) movida(s) para ${movedToGroup}.`
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
    const names = new Set();

    document.querySelectorAll("[data-task-group]").forEach((section) => {
      const text = section?.dataset?.groupName?.trim();
      if (text) names.add(text);
    });

    if (
      createTaskGroupInput &&
      createTaskGroupInput instanceof HTMLSelectElement
    ) {
      Array.from(createTaskGroupInput.options).forEach((option) => {
        const text = option.value?.trim();
        if (text) names.add(text);
      });
    }

    return Array.from(names).sort((a, b) =>
      a.localeCompare(b, "pt-BR", { sensitivity: "base" })
    );
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

  syncTaskGroupInputs();
  document.querySelectorAll("[data-task-group]").forEach((section) => {
    setTaskGroupCollapsed(section, section.classList.contains("is-collapsed"));
  });

  const openCreateModal = (groupName) => {
    if (!createTaskModal) return;
    setFabMenuOpen(false);
    syncTaskGroupInputs();
    if (createTaskForm) {
      createTaskForm.reset();
      createTaskForm
        .querySelectorAll(".assignee-picker")
        .forEach(updateAssigneePickerSummary);
      createTaskForm
        .querySelectorAll(".status-select, .priority-select")
        .forEach(syncSelectColor);
    }
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

  document.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

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

    const openTaskDetailEditTrigger = target.closest("[data-task-detail-edit]");
    if (openTaskDetailEditTrigger) {
      if (taskDetailContext) {
        populateTaskDetailModalFromRow(taskDetailContext);
        setTaskDetailEditMode(true);
      }
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

    const closeGroupTrigger = target.closest("[data-close-create-group-modal]");
    if (closeGroupTrigger) {
      closeCreateGroupModal();
      return;
    }

    const closeTaskDetailTrigger = target.closest("[data-close-task-detail-modal]");
    if (closeTaskDetailTrigger) {
      closeTaskDetailModal();
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
    if (event.key === "Enter" && target instanceof HTMLElement && target.matches("[data-group-name-input]")) {
      event.preventDefault();
      target.blur();
      return;
    }

    if (event.key !== "Escape") return;

    if (fabWrap?.classList.contains("is-open")) {
      setFabMenuOpen(false);
    }
    if (createTaskModal && !createTaskModal.hidden) {
      closeCreateModal();
    }
    if (createGroupModal && !createGroupModal.hidden) {
      closeCreateGroupModal();
    }
    if (taskImagePreviewModal && !taskImagePreviewModal.hidden) {
      closeTaskImagePreview();
      return;
    }
    if (taskDetailModal && !taskDetailModal.hidden) {
      closeTaskDetailModal();
    }
    if (confirmModal && !confirmModal.hidden) {
      closeConfirmModal();
    }
  });

  if (createTaskForm) {
    createTaskForm.addEventListener("submit", () => {
      if (createTaskTitleInput instanceof HTMLInputElement) {
        applyFirstLetterUppercaseToInput(createTaskTitleInput);
      }
      syncBodyModalLock();
    });
  }

  const applyTaskFilterForm = (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const params = new URLSearchParams();
    const statusField = form.querySelector('select[name="status"]');
    const assigneeField = form.querySelector('select[name="assignee"]');

    if (statusField instanceof HTMLSelectElement && (statusField.value || "").trim() !== "") {
      params.set("status", statusField.value.trim());
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
      const select = target.closest('select[name="status"], select[name="assignee"]');
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
});

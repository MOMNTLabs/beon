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
    if (!(target instanceof HTMLTextAreaElement)) return;
    if (
      target.matches("[data-task-detail-edit-description]") ||
      target.matches("[data-task-detail-edit-links]") ||
      target.matches("[data-task-detail-edit-images]")
    ) {
      autoResizeTextarea(target);
    }
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

  const parseReferenceLines = (value) => {
    const seen = new Set();
    return String(value || "")
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter((line) => {
        if (!line) return false;
        try {
          const url = new URL(line);
          if (!["http:", "https:"].includes(url.protocol)) return false;
          const normalized = url.toString();
          if (seen.has(normalized)) return false;
          seen.add(normalized);
          return true;
        } catch (_error) {
          return false;
        }
      })
      .map((line) => {
        try {
          return new URL(line).toString();
        } catch (_error) {
          return line;
        }
      });
  };

  const readJsonUrlListField = (field) => {
    if (!(field instanceof HTMLInputElement)) return [];
    const raw = (field.value || "").trim();
    if (!raw) return [];
    try {
      const decoded = JSON.parse(raw);
      if (!Array.isArray(decoded)) return [];
      return parseReferenceLines(decoded.join("\n"));
    } catch (_error) {
      return parseReferenceLines(raw);
    }
  };

  const writeJsonUrlListField = (field, urls) => {
    if (!(field instanceof HTMLInputElement)) return;
    field.value = JSON.stringify(parseReferenceLines((urls || []).join("\n")));
  };

  const renderTaskDetailReferencesView = ({ links = [], images = [] } = {}) => {
    const safeLinks = parseReferenceLines((links || []).join("\n"));
    const safeImages = parseReferenceLines((images || []).join("\n"));

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
        const a = document.createElement("a");
        a.href = url;
        a.target = "_blank";
        a.rel = "noreferrer noopener";
        a.className = "task-detail-ref-image-link";

        const img = document.createElement("img");
        img.src = url;
        img.alt = "Referencia da tarefa";
        img.loading = "lazy";
        img.className = "task-detail-ref-image";

        a.append(img);
        taskDetailViewImages.append(a);
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

    const dueDisplay = event.target.closest("[data-due-date-display]");
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

    const toggleButton = event.target.closest("[data-task-expand]");
    if (!toggleButton) return;

    const taskItem = toggleButton.closest("[data-task-item]");
    if (!(taskItem instanceof HTMLElement)) return;
    openTaskDetailModal(taskItem);
  });

  const fabWrap = document.querySelector("[data-task-fab-wrap]");
  const fabToggleButton = document.querySelector("[data-task-fab-toggle]");
  const fabMenu = document.querySelector("[data-task-fab-menu]");
  const taskGroupsDatalist = document.querySelector("#task-group-options");

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
  const taskDetailViewCreatedBy = document.querySelector("[data-task-detail-view-created-by]");
  const taskDetailViewUpdatedAt = document.querySelector("[data-task-detail-view-updated-at]");
  const taskDetailEditTitle = document.querySelector("[data-task-detail-edit-title]");
  const taskDetailEditStatus = document.querySelector("[data-task-detail-edit-status]");
  const taskDetailEditPriority = document.querySelector("[data-task-detail-edit-priority]");
  const taskDetailEditGroup = document.querySelector("[data-task-detail-edit-group]");
  const taskDetailEditDueDate = document.querySelector("[data-task-detail-edit-due-date]");
  const taskDetailEditDescription = document.querySelector("[data-task-detail-edit-description]");
  const taskDetailEditLinks = document.querySelector("[data-task-detail-edit-links]");
  const taskDetailEditImages = document.querySelector("[data-task-detail-edit-images]");
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
        [taskDetailEditDescription, taskDetailEditLinks, taskDetailEditImages].forEach(autoResizeTextarea);
        taskDetailEditTitle?.focus();
      }, 20);
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
    const referenceLinks = readJsonUrlListField(referenceLinksField);
    const referenceImages = readJsonUrlListField(referenceImagesField);
    const metaSpans = metaRow ? Array.from(metaRow.querySelectorAll("span")) : [];
    const createdByText = metaSpans[0]?.textContent?.trim() || "";
    const updatedAtText = metaRow?.querySelector("[data-task-updated-at]")?.textContent?.trim() || "";

    if (taskDetailTitle) taskDetailTitle.textContent = titleValue;
    if (taskDetailViewTitle) taskDetailViewTitle.textContent = titleValue;
    if (taskDetailViewStatus) taskDetailViewStatus.textContent = statusLabel;
    syncTaskDetailViewPriorityTag(prioritySelect.value || "medium");
    if (taskDetailViewGroup) taskDetailViewGroup.textContent = groupLabel;
    if (taskDetailViewDue) taskDetailViewDue.textContent = dueMeta.display;
    if (taskDetailViewAssignees) {
      taskDetailViewAssignees.textContent = assigneeNames.length
        ? `Responsaveis: ${assigneeNames.join(", ")}`
        : "Sem responsavel";
    }
    if (taskDetailViewDescription) {
      taskDetailViewDescription.textContent = description || "Sem descricao.";
      taskDetailViewDescription.classList.toggle("is-empty", !description);
    }
    renderTaskDetailReferencesView({ links: referenceLinks, images: referenceImages });
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
      autoResizeTextarea(taskDetailEditDescription);
    }
    if (taskDetailEditLinks instanceof HTMLTextAreaElement) {
      taskDetailEditLinks.value = referenceLinks.join("\n");
      autoResizeTextarea(taskDetailEditLinks);
    }
    if (taskDetailEditImages instanceof HTMLTextAreaElement) {
      taskDetailEditImages.value = referenceImages.join("\n");
      autoResizeTextarea(taskDetailEditImages);
    }
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

    context.titleInput.value = taskDetailEditTitle.value;
    context.statusSelect.value = taskDetailEditStatus.value;
    context.prioritySelect.value = taskDetailEditPriority.value;
    context.dueDateInput.value = taskDetailEditDueDate.value;
    context.descriptionField.value = taskDetailEditDescription.value;
    if (context.referenceLinksField instanceof HTMLInputElement && taskDetailEditLinks instanceof HTMLTextAreaElement) {
      writeJsonUrlListField(context.referenceLinksField, parseReferenceLines(taskDetailEditLinks.value));
    }
    if (context.referenceImagesField instanceof HTMLInputElement && taskDetailEditImages instanceof HTMLTextAreaElement) {
      writeJsonUrlListField(context.referenceImagesField, parseReferenceLines(taskDetailEditImages.value));
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

  const saveTaskDetailModal = async () => {
    if (!taskDetailContext) return;
    if (!copyTaskDetailModalToRow(taskDetailContext)) return;

    if (taskDetailSaveButton instanceof HTMLButtonElement) {
      taskDetailSaveButton.disabled = true;
      taskDetailSaveButton.classList.add("is-loading");
      taskDetailSaveButton.textContent = "Salvando";
    }

    const ok = await submitTaskAutosave(taskDetailContext.form);

    if (taskDetailSaveButton instanceof HTMLButtonElement) {
      taskDetailSaveButton.disabled = false;
      taskDetailSaveButton.classList.remove("is-loading");
      taskDetailSaveButton.textContent = "Salvar";
    }

    if (!ok) return;

    populateTaskDetailModalFromRow(taskDetailContext);
    setTaskDetailEditMode(false);
  };

  const syncBodyModalLock = () => {
    const hasOpenModal = [createTaskModal, createGroupModal, taskDetailModal, confirmModal].some(
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
    if (taskDetailModal && !taskDetailModal.hidden) {
      closeTaskDetailModal();
    }
    if (confirmModal && !confirmModal.hidden) {
      closeConfirmModal();
    }
  });

  if (createTaskForm) {
    createTaskForm.addEventListener("submit", () => {
      syncBodyModalLock();
    });
  }

  if (createGroupForm) {
    createGroupForm.addEventListener("submit", () => {
      syncBodyModalLock();
    });
  }
});

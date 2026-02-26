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

  const autosaveTimers = new WeakMap();
  const scheduleTaskAutosave = (form, delay = 180) => {
    if (!form || form.dataset.autosaveSubmitting === "1") return;

    const previousTimer = autosaveTimers.get(form);
    if (previousTimer) window.clearTimeout(previousTimer);

    const nextTimer = window.setTimeout(() => {
      if (typeof form.reportValidity === "function" && !form.reportValidity()) {
        return;
      }
      form.dataset.autosaveSubmitting = "1";
      if (typeof form.requestSubmit === "function") {
        form.requestSubmit();
      } else {
        form.submit();
      }
    }, delay);

    autosaveTimers.set(form, nextTimer);
  };

  document.addEventListener("change", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

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
      dropzone.append(draggedTaskItem);
      scheduleTaskAutosave(form, 60);
    }
  });

  document.addEventListener("click", (event) => {
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

    const toggleButton = event.target.closest("[data-task-expand]");
    if (!toggleButton) return;

    const taskItem = toggleButton.closest("[data-task-item]");
    const details = taskItem?.querySelector(".task-line-details");
    if (!details) return;

    const isOpen = !details.hidden;
    details.hidden = isOpen;
    toggleButton.setAttribute("aria-expanded", isOpen ? "false" : "true");
    toggleButton.setAttribute(
      "aria-label",
      isOpen ? "Expandir detalhes" : "Recolher detalhes"
    );
    toggleButton.setAttribute(
      "title",
      isOpen ? "Expandir detalhes" : "Recolher detalhes"
    );
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

  const collectGroupNames = () => {
    const names = new Set(["Geral"]);

    document.querySelectorAll(".task-group-head h3").forEach((heading) => {
      const text = heading.textContent?.trim();
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
        createTaskGroupInput.value = "Geral";
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
    }
    if (createTaskGroupInput) {
      const nextGroup = (groupName || "").trim() || "Geral";
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
    document.body.classList.add("modal-open");
    window.setTimeout(() => {
      createTaskTitleInput?.focus();
    }, 20);
  };

  const closeCreateModal = () => {
    if (!createTaskModal) return;
    createTaskModal.hidden = true;
    document.body.classList.remove("modal-open");
  };

  const openCreateGroupModal = () => {
    if (!createGroupModal) return;
    setFabMenuOpen(false);
    if (createGroupForm) {
      createGroupForm.reset();
    }
    createGroupModal.hidden = false;
    document.body.classList.add("modal-open");
    window.setTimeout(() => {
      createGroupNameInput?.focus();
    }, 20);
  };

  const closeCreateGroupModal = () => {
    if (!createGroupModal) return;
    createGroupModal.hidden = true;
    if (!createTaskModal || createTaskModal.hidden) {
      document.body.classList.remove("modal-open");
    }
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
      openCreateModal(openTaskTrigger.dataset.createGroup || "Geral");
      return;
    }

    const openGroupTrigger = target.closest("[data-open-create-group-modal]");
    if (openGroupTrigger) {
      openCreateGroupModal();
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
    }
  });

  document.addEventListener("keydown", (event) => {
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
  });

  if (createTaskForm) {
    createTaskForm.addEventListener("submit", () => {
      document.body.classList.remove("modal-open");
    });
  }

  if (createGroupForm) {
    createGroupForm.addEventListener("submit", () => {
      document.body.classList.remove("modal-open");
    });
  }
});

<?php
declare(strict_types=1);

function buildGlobalDashboardOverview(?array $currentUser, array $userWorkspaces): array
{
    $globalDashboardOverview = [
        'workspace_count' => 0,
        'due_window_days' => 7,
        'accounting_period_label' => accountingMonthLabel(normalizeAccountingPeriodKey((new DateTimeImmutable('today'))->format('Y-m'))),
        'tasks_today_total' => 0,
        'tasks_tomorrow_total' => 0,
        'urgent_tasks_today_total' => 0,
        'priority_tasks_today_total' => 0,
        'due_soon_total' => 0,
        'due_today_total' => 0,
        'due_tomorrow_total' => 0,
        'low_stock_total' => 0,
        'critical_workspace_total' => 0,
        'attention_workspace_total' => 0,
        'executive_focus_total' => 0,
        'executive_status_tone' => 'stable',
        'executive_status_label' => 'Operação estável',
        'executive_status_note' => 'Nenhum ponto critico no curto prazo.',
        'balance_total_cents' => 0,
        'balance_month_movement_cents' => 0,
        'balance_total_display' => dueAmountLabelFromSignedCents(0),
        'balance_month_movement_display' => dueAmountLabelFromSignedCents(0),
        'tasks_today' => [],
        'tasks_tomorrow' => [],
        'due_soon' => [],
        'low_stock' => [],
        'workspace_summaries' => [],
    ];

    if (!$currentUser) {
        return $globalDashboardOverview;
    }

    $overviewUserId = (int) ($currentUser['id'] ?? 0);
    if ($overviewUserId <= 0) {
        return $globalDashboardOverview;
    }

    $overviewToday = new DateTimeImmutable('today');
    $overviewTodayIso = $overviewToday->format('Y-m-d');
    $overviewTomorrowIso = $overviewToday->modify('+1 day')->format('Y-m-d');
    $overviewDueWindowDays = (int) ($globalDashboardOverview['due_window_days'] ?? 7);
    $overviewDueLimitIso = $overviewToday->modify('+' . $overviewDueWindowDays . ' days')->format('Y-m-d');
    $overviewAccountingPeriod = normalizeAccountingPeriodKey($overviewToday->format('Y-m'));
    $globalDashboardOverview['accounting_period_label'] = accountingMonthLabel($overviewAccountingPeriod);
    $globalDashboardOverview['workspace_count'] = count($userWorkspaces);

    $priorityLabels = taskPriorities();
    $workspaceRolesMap = workspaceRoles();

    foreach ($userWorkspaces as $workspaceOption) {
        $workspaceOptionId = (int) ($workspaceOption['id'] ?? 0);
        if ($workspaceOptionId <= 0) {
            continue;
        }

        $workspaceOptionName = (string) ($workspaceOption['name'] ?? 'Workspace');
        $workspaceOptionRole = normalizeWorkspaceRole((string) ($workspaceOption['member_role'] ?? 'member'));
        $workspaceOptionRoleLabel = $workspaceRolesMap[$workspaceOptionRole] ?? 'Usuário';

        $taskViewPermissionsByGroup = [];
        $workspaceTasks = [];
        foreach (allTasks($workspaceOptionId) as $workspaceTask) {
            $groupName = normalizeTaskGroupName((string) ($workspaceTask['group_name'] ?? 'Geral'));
            $groupKey = mb_strtolower($groupName);
            if (!array_key_exists($groupKey, $taskViewPermissionsByGroup)) {
                $taskViewPermissionsByGroup[$groupKey] = userCanViewTaskGroup($overviewUserId, $workspaceOptionId, $groupName);
            }

            if (!$taskViewPermissionsByGroup[$groupKey]) {
                continue;
            }

            $workspaceTasks[] = $workspaceTask;
        }

        $workspaceTasksToday = [];
        $workspaceTasksTomorrow = [];
        $workspaceUrgentTasksTodayCount = 0;
        $workspacePriorityTasksTodayCount = 0;
        foreach ($workspaceTasks as $workspaceTask) {
            $dueDate = dueDateForStorage((string) ($workspaceTask['due_date'] ?? ''));
            if ($dueDate !== $overviewTodayIso && $dueDate !== $overviewTomorrowIso) {
                continue;
            }

            $statusKey = normalizeTaskStatus((string) ($workspaceTask['status'] ?? 'todo'), $workspaceOptionId);
            if (taskStatusKind($statusKey, $workspaceOptionId) === 'done') {
                continue;
            }

            $taskAssigneeIds = is_array($workspaceTask['assignee_ids'] ?? null)
                ? $workspaceTask['assignee_ids']
                : [];
            $isAssignedToCurrentUser = in_array($overviewUserId, $taskAssigneeIds, true);
            $isOwnedByCurrentUserWithoutAssignee = !$taskAssigneeIds
                && (int) ($workspaceTask['created_by'] ?? 0) === $overviewUserId;
            if (!$isAssignedToCurrentUser && !$isOwnedByCurrentUserWithoutAssignee) {
                continue;
            }

            $priorityKey = normalizeTaskPriority((string) ($workspaceTask['priority'] ?? 'medium'));
            $taskOverviewItem = [
                'workspace_id' => $workspaceOptionId,
                'workspace_name' => $workspaceOptionName,
                'task_id' => (int) ($workspaceTask['id'] ?? 0),
                'title' => (string) ($workspaceTask['title'] ?? ''),
                'group_name' => normalizeTaskGroupName((string) ($workspaceTask['group_name'] ?? 'Geral')),
                'priority' => $priorityKey,
                'priority_label' => $priorityLabels[$priorityKey] ?? 'Media',
            ];

            if ($dueDate === $overviewTodayIso) {
                if ($priorityKey === 'urgent') {
                    $workspaceUrgentTasksTodayCount++;
                }
                if ($priorityKey === 'urgent' || $priorityKey === 'high') {
                    $workspacePriorityTasksTodayCount++;
                }
                $workspaceTasksToday[] = $taskOverviewItem;
                continue;
            }

            $workspaceTasksTomorrow[] = $taskOverviewItem;
        }

        $dueViewPermissionsByGroup = [];
        $workspaceDueEntries = [];
        foreach (workspaceDueEntriesList($workspaceOptionId) as $workspaceDueEntry) {
            $groupName = normalizeDueGroupName((string) ($workspaceDueEntry['group_name'] ?? 'Geral'));
            $groupKey = mb_strtolower($groupName);
            if (!array_key_exists($groupKey, $dueViewPermissionsByGroup)) {
                $dueViewPermissionsByGroup[$groupKey] = userCanViewDueGroup($overviewUserId, $workspaceOptionId, $groupName);
            }

            if (!$dueViewPermissionsByGroup[$groupKey]) {
                continue;
            }

            $workspaceDueEntries[] = $workspaceDueEntry;
        }

        $workspaceDueSoon = [];
        $workspaceDueTodayCount = 0;
        $workspaceDueTomorrowCount = 0;
        foreach ($workspaceDueEntries as $workspaceDueEntry) {
            $nextDueDateIso = dueDateForStorage((string) ($workspaceDueEntry['next_due_date'] ?? ''));
            if ($nextDueDateIso === null) {
                continue;
            }
            if ($nextDueDateIso < $overviewTodayIso || $nextDueDateIso > $overviewDueLimitIso) {
                continue;
            }

            $daysUntil = null;
            $daysUntilLabel = '';
            try {
                $daysUntil = (int) $overviewToday->diff(new DateTimeImmutable($nextDueDateIso))->format('%a');
            } catch (Throwable $e) {
                $daysUntil = null;
            }

            if ($daysUntil === 0) {
                $daysUntilLabel = 'Hoje';
                $workspaceDueTodayCount++;
            } elseif ($daysUntil === 1) {
                $daysUntilLabel = 'Amanh?';
                $workspaceDueTomorrowCount++;
            } elseif ($daysUntil !== null) {
                $daysUntilLabel = 'Em ' . $daysUntil . ' dias';
            } else {
                $daysUntilLabel = (new DateTimeImmutable($nextDueDateIso))->format('d/m');
            }

            $workspaceDueSoon[] = [
                'workspace_id' => $workspaceOptionId,
                'workspace_name' => $workspaceOptionName,
                'label' => (string) ($workspaceDueEntry['label'] ?? ''),
                'group_name' => normalizeDueGroupName((string) ($workspaceDueEntry['group_name'] ?? 'Geral')),
                'amount_cents' => (int) ($workspaceDueEntry['amount_cents'] ?? 0),
                'amount_display' => (string) ($workspaceDueEntry['amount_display'] ?? dueAmountLabelFromCents(0)),
                'next_due_date' => $nextDueDateIso,
                'next_due_display' => (new DateTimeImmutable($nextDueDateIso))->format('d/m'),
                'days_until' => $daysUntil,
                'days_until_label' => $daysUntilLabel,
            ];
        }

        $workspaceInventoryEntries = workspaceInventoryEntriesList($workspaceOptionId);
        $workspaceLowStockEntries = [];
        foreach ($workspaceInventoryEntries as $inventoryEntry) {
            if (((int) ($inventoryEntry['is_low_stock'] ?? 0)) !== 1) {
                continue;
            }

            $quantityValue = (float) ($inventoryEntry['quantity_value'] ?? 0);
            $minQuantityValue = $inventoryEntry['min_quantity_value'] !== null
                ? (float) $inventoryEntry['min_quantity_value']
                : null;
            $deficitQuantity = 0.0;
            if ($minQuantityValue !== null && $quantityValue <= $minQuantityValue) {
                $deficitQuantity = $minQuantityValue - $quantityValue;
            }

            $workspaceLowStockEntries[] = [
                'workspace_id' => $workspaceOptionId,
                'workspace_name' => $workspaceOptionName,
                'label' => (string) ($inventoryEntry['label'] ?? ''),
                'group_name' => normalizeInventoryGroupName((string) ($inventoryEntry['group_name'] ?? 'Geral')),
                'quantity_display' => (string) ($inventoryEntry['quantity_display'] ?? '0'),
                'min_quantity_display' => (string) ($inventoryEntry['min_quantity_display'] ?? '0'),
                'unit_label' => normalizeInventoryUnitLabel((string) ($inventoryEntry['unit_label'] ?? 'un')),
                'deficit_quantity' => $deficitQuantity,
            ];
        }

        $workspaceAccountingEntries = workspaceAccountingEntriesList($workspaceOptionId, $overviewAccountingPeriod);
        $workspaceAccountingOpeningBalance = workspaceAccountingOpeningBalanceCents(
            $workspaceOptionId,
            $overviewAccountingPeriod
        );
        $workspaceAccountingSummary = accountingSummary($workspaceAccountingEntries, $workspaceAccountingOpeningBalance);
        $workspaceCurrentBalanceCents = (int) ($workspaceAccountingSummary['current_balance_cents'] ?? 0);
        $workspaceFinalBalanceCents = (int) ($workspaceAccountingSummary['final_balance_cents'] ?? 0);

        $globalDashboardOverview['tasks_today'] = array_merge(
            $globalDashboardOverview['tasks_today'],
            $workspaceTasksToday
        );
        $globalDashboardOverview['tasks_tomorrow'] = array_merge(
            $globalDashboardOverview['tasks_tomorrow'],
            $workspaceTasksTomorrow
        );
        $globalDashboardOverview['due_soon'] = array_merge(
            $globalDashboardOverview['due_soon'],
            $workspaceDueSoon
        );
        $globalDashboardOverview['low_stock'] = array_merge(
            $globalDashboardOverview['low_stock'],
            $workspaceLowStockEntries
        );
        $globalDashboardOverview['balance_month_movement_cents'] += $workspaceCurrentBalanceCents;
        $globalDashboardOverview['balance_total_cents'] += $workspaceFinalBalanceCents;

        $workspaceAttentionScore = ($workspaceUrgentTasksTodayCount * 4)
            + ($workspaceDueTodayCount * 4)
            + ($workspacePriorityTasksTodayCount * 2)
            + ($workspaceDueTomorrowCount * 2)
            + count($workspaceLowStockEntries);
        $workspaceAttentionTone = 'stable';
        $workspaceAttentionLabel = 'Estável';
        $workspaceAttentionNote = 'Sem pendências imediatas.';

        if ($workspaceUrgentTasksTodayCount > 0 || $workspaceDueTodayCount > 0) {
            $workspaceAttentionTone = 'critical';
            $workspaceAttentionLabel = 'Foco imediato';
            if ($workspaceUrgentTasksTodayCount > 0 && $workspaceDueTodayCount > 0) {
                $workspaceAttentionNote = 'Urgências e vencimentos hoje.';
            } elseif ($workspaceUrgentTasksTodayCount > 0) {
                $workspaceAttentionNote = 'Tarefas urgentes para hoje.';
            } else {
                $workspaceAttentionNote = 'Contas vencem hoje.';
            }
        } elseif ($workspacePriorityTasksTodayCount > 0 || $workspaceDueTomorrowCount > 0 || count($workspaceLowStockEntries) > 0) {
            $workspaceAttentionTone = 'attention';
            $workspaceAttentionLabel = 'Monitorar';
            if ($workspaceDueTomorrowCount > 0) {
                $workspaceAttentionNote = 'Há vencimentos previstos para amanh?.';
            } elseif (count($workspaceLowStockEntries) > 0) {
                $workspaceAttentionNote = 'Itens abaixo do mínimo precisam reposição.';
            } else {
                $workspaceAttentionNote = 'Tarefas de alta prioridade para hoje.';
            }
        }

        $globalDashboardOverview['workspace_summaries'][] = [
            'workspace_id' => $workspaceOptionId,
            'workspace_name' => $workspaceOptionName,
            'workspace_role' => $workspaceOptionRole,
            'workspace_role_label' => $workspaceOptionRoleLabel,
            'tasks_today_count' => count($workspaceTasksToday),
            'urgent_tasks_today_count' => $workspaceUrgentTasksTodayCount,
            'priority_tasks_today_count' => $workspacePriorityTasksTodayCount,
            'due_soon_count' => count($workspaceDueSoon),
            'due_today_count' => $workspaceDueTodayCount,
            'due_tomorrow_count' => $workspaceDueTomorrowCount,
            'low_stock_count' => count($workspaceLowStockEntries),
            'attention_score' => $workspaceAttentionScore,
            'attention_tone' => $workspaceAttentionTone,
            'attention_label' => $workspaceAttentionLabel,
            'attention_note' => $workspaceAttentionNote,
            'month_movement_cents' => $workspaceCurrentBalanceCents,
            'month_movement_display' => dueAmountLabelFromSignedCents($workspaceCurrentBalanceCents),
            'balance_total_cents' => $workspaceFinalBalanceCents,
            'balance_total_display' => dueAmountLabelFromSignedCents($workspaceFinalBalanceCents),
        ];
    }

    $priorityOrder = [
        'urgent' => 0,
        'high' => 1,
        'medium' => 2,
        'low' => 3,
    ];
    usort(
        $globalDashboardOverview['tasks_today'],
        static function (array $a, array $b) use ($priorityOrder): int {
            $priorityA = $priorityOrder[normalizeTaskPriority((string) ($a['priority'] ?? 'medium'))] ?? 99;
            $priorityB = $priorityOrder[normalizeTaskPriority((string) ($b['priority'] ?? 'medium'))] ?? 99;
            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            $workspaceCompare = strcasecmp(
                (string) ($a['workspace_name'] ?? ''),
                (string) ($b['workspace_name'] ?? '')
            );
            if ($workspaceCompare !== 0) {
                return $workspaceCompare;
            }

            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        }
    );

    usort(
        $globalDashboardOverview['due_soon'],
        static function (array $a, array $b): int {
            $dueA = dueDateForStorage((string) ($a['next_due_date'] ?? '')) ?? '9999-12-31';
            $dueB = dueDateForStorage((string) ($b['next_due_date'] ?? '')) ?? '9999-12-31';
            if ($dueA !== $dueB) {
                return strcmp($dueA, $dueB);
            }

            $amountA = (int) ($a['amount_cents'] ?? 0);
            $amountB = (int) ($b['amount_cents'] ?? 0);
            if ($amountA !== $amountB) {
                return $amountB <=> $amountA;
            }

            return strcasecmp((string) ($a['workspace_name'] ?? ''), (string) ($b['workspace_name'] ?? ''));
        }
    );

    usort(
        $globalDashboardOverview['low_stock'],
        static function (array $a, array $b): int {
            $deficitA = (float) ($a['deficit_quantity'] ?? 0);
            $deficitB = (float) ($b['deficit_quantity'] ?? 0);
            if ($deficitA !== $deficitB) {
                return $deficitB <=> $deficitA;
            }

            $workspaceCompare = strcasecmp(
                (string) ($a['workspace_name'] ?? ''),
                (string) ($b['workspace_name'] ?? '')
            );
            if ($workspaceCompare !== 0) {
                return $workspaceCompare;
            }

            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        }
    );

    usort(
        $globalDashboardOverview['workspace_summaries'],
        static function (array $a, array $b): int {
            $scoreA = (int) ($a['attention_score'] ?? 0);
            $scoreB = (int) ($b['attention_score'] ?? 0);
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            $balanceA = abs((int) ($a['balance_total_cents'] ?? 0));
            $balanceB = abs((int) ($b['balance_total_cents'] ?? 0));
            if ($balanceA !== $balanceB) {
                return $balanceB <=> $balanceA;
            }

            return strcasecmp((string) ($a['workspace_name'] ?? ''), (string) ($b['workspace_name'] ?? ''));
        }
    );

    usort(
        $globalDashboardOverview['tasks_tomorrow'],
        static function (array $a, array $b) use ($priorityOrder): int {
            $priorityA = $priorityOrder[normalizeTaskPriority((string) ($a['priority'] ?? 'medium'))] ?? 99;
            $priorityB = $priorityOrder[normalizeTaskPriority((string) ($b['priority'] ?? 'medium'))] ?? 99;
            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            $workspaceCompare = strcasecmp(
                (string) ($a['workspace_name'] ?? ''),
                (string) ($b['workspace_name'] ?? '')
            );
            if ($workspaceCompare !== 0) {
                return $workspaceCompare;
            }

            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        }
    );

    $globalDashboardOverview['tasks_today_total'] = count($globalDashboardOverview['tasks_today']);
    $globalDashboardOverview['tasks_tomorrow_total'] = count($globalDashboardOverview['tasks_tomorrow']);
    $globalDashboardOverview['urgent_tasks_today_total'] = count(array_filter(
        $globalDashboardOverview['tasks_today'],
        static function (array $taskToday): bool {
            return normalizeTaskPriority((string) ($taskToday['priority'] ?? 'medium')) === 'urgent';
        }
    ));
    $globalDashboardOverview['priority_tasks_today_total'] = count(array_filter(
        $globalDashboardOverview['tasks_today'],
        static function (array $taskToday): bool {
            $priority = normalizeTaskPriority((string) ($taskToday['priority'] ?? 'medium'));
            return $priority === 'urgent' || $priority === 'high';
        }
    ));
    $globalDashboardOverview['due_soon_total'] = count($globalDashboardOverview['due_soon']);
    $globalDashboardOverview['due_today_total'] = count(array_filter(
        $globalDashboardOverview['due_soon'],
        static function (array $dueSoon): bool {
            return (int) ($dueSoon['days_until'] ?? -1) === 0;
        }
    ));
    $globalDashboardOverview['due_tomorrow_total'] = count(array_filter(
        $globalDashboardOverview['due_soon'],
        static function (array $dueSoon): bool {
            return (int) ($dueSoon['days_until'] ?? -1) === 1;
        }
    ));
    $globalDashboardOverview['low_stock_total'] = count($globalDashboardOverview['low_stock']);
    $globalDashboardOverview['critical_workspace_total'] = count(array_filter(
        $globalDashboardOverview['workspace_summaries'],
        static function (array $workspaceSummary): bool {
            return (string) ($workspaceSummary['attention_tone'] ?? 'stable') === 'critical';
        }
    ));
    $globalDashboardOverview['attention_workspace_total'] = count(array_filter(
        $globalDashboardOverview['workspace_summaries'],
        static function (array $workspaceSummary): bool {
            return (string) ($workspaceSummary['attention_tone'] ?? 'stable') === 'attention';
        }
    ));
    $globalDashboardOverview['executive_focus_total'] = (int) (
        $globalDashboardOverview['urgent_tasks_today_total']
        + $globalDashboardOverview['due_today_total']
        + $globalDashboardOverview['critical_workspace_total']
    );
    if ($globalDashboardOverview['executive_focus_total'] > 0) {
        $globalDashboardOverview['executive_status_tone'] = 'critical';
        $globalDashboardOverview['executive_status_label'] = 'Foco imediato';
        $globalDashboardOverview['executive_status_note'] = 'Urgências do dia e vencimentos de hoje merecem decisão primeiro.';
    } elseif (
        (int) ($globalDashboardOverview['priority_tasks_today_total'] ?? 0) > 0
        || (int) ($globalDashboardOverview['due_tomorrow_total'] ?? 0) > 0
        || (int) ($globalDashboardOverview['low_stock_total'] ?? 0) > 0
        || (int) ($globalDashboardOverview['attention_workspace_total'] ?? 0) > 0
    ) {
        $globalDashboardOverview['executive_status_tone'] = 'attention';
        $globalDashboardOverview['executive_status_label'] = 'Monitoramento';
        $globalDashboardOverview['executive_status_note'] = 'A operação esta controlada, mas ha itens próximos exigindo acompanhamento.';
    }
    $globalDashboardOverview['balance_total_display'] = dueAmountLabelFromSignedCents(
        (int) $globalDashboardOverview['balance_total_cents']
    );
    $globalDashboardOverview['balance_month_movement_display'] = dueAmountLabelFromSignedCents(
        (int) $globalDashboardOverview['balance_month_movement_cents']
    );

    $globalDashboardOverview['tasks_today'] = array_slice($globalDashboardOverview['tasks_today'], 0, 8);
    $globalDashboardOverview['tasks_tomorrow'] = array_slice($globalDashboardOverview['tasks_tomorrow'], 0, 8);
    $globalDashboardOverview['due_soon'] = array_slice($globalDashboardOverview['due_soon'], 0, 8);
    $globalDashboardOverview['low_stock'] = array_slice($globalDashboardOverview['low_stock'], 0, 8);

    return $globalDashboardOverview;
}

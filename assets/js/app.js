document.addEventListener('DOMContentLoaded', () => {
    const dashboardRangeSegments = [
        { value: 'all', label: 'ALL', fullLabel: 'All tickets' },
        { value: '7d', label: '7D', fullLabel: 'Last 7 days' },
        { value: '30d', label: '30D', fullLabel: 'Last 30 days' },
        { value: '3m', label: '3M', fullLabel: 'Last 3 months' },
        { value: '6m', label: '6M', fullLabel: 'Last 6 months' },
        { value: '1y', label: '1Y', fullLabel: 'Last 1 year' },
    ];
    const trendRangeSegments = dashboardRangeSegments.filter((segment) => segment.value !== 'all');

    const buildRangeSegments = (form, picker, fieldName) => {
        const menu = picker.querySelector('[data-filter-menu]');
        if (!menu) return null;

        const existing = new Map(
            Array.from(menu.querySelectorAll('.filter-option')).map((option) => [
                option.dataset.filterValue || option.textContent.trim().toLowerCase(),
                option,
            ]),
        );
        const input = form.querySelector(`[name="${fieldName}"]`);
        const activeValue = input?.value || '';
        const control = document.createElement('div');
        control.className = 'filter-segment-control';
        control.dataset.filterSegmentControl = '';
        control.setAttribute('role', 'tablist');
        control.setAttribute('aria-label', 'Date range');

        const rangeSegments = fieldName === 'dashboard_range' ? dashboardRangeSegments : trendRangeSegments;
        rangeSegments.forEach((segment) => {
            const option = existing.get(segment.value) || document.createElement('button');
            option.type = 'button';
            option.className = 'filter-segment';
            option.dataset.filterTarget = fieldName;
            option.dataset.filterValue = segment.value;
            option.dataset.filterLabel = segment.fullLabel;
            option.setAttribute('role', 'tab');
            option.setAttribute('aria-label', segment.fullLabel);
            option.innerHTML = `<span>${segment.label}</span>`;
            const fallbackValue = fieldName === 'dashboard_range' ? 'all' : '6m';
            option.classList.toggle('is-selected', (activeValue || fallbackValue) === segment.value);
            control.appendChild(option);
        });

        picker.replaceWith(control);
        return control;
    };

    document.querySelectorAll('.dashboard-filter-form').forEach((form) => {
        const picker = form.querySelector('[data-filter-picker]');
        if (!picker) return;
        const fieldName = form.classList.contains('trend-filter-form') ? 'trend_range' : 'dashboard_range';
        const control = buildRangeSegments(form, picker, fieldName);
        if (!control) return;

        control.querySelectorAll('.filter-segment').forEach((option) => {
            option.addEventListener('click', () => {
                control.querySelectorAll('.filter-segment').forEach((item) => {
                    item.classList.remove('is-selected');
                    item.setAttribute('aria-selected', 'false');
                });
                option.classList.add('is-selected');
                option.setAttribute('aria-selected', 'true');
                const input = form.querySelector(`[name="${fieldName}"]`);
                if (input) input.value = option.dataset.filterValue || '';
                if (form.classList.contains('trend-filter-form')) {
                    const grainInput = form.querySelector('[name="trend_grain"]');
                    if (grainInput && ['7d', '30d'].includes(option.dataset.filterValue || '')) grainInput.value = 'daily';
                }
                if (form.dataset.preview !== 'true') form.submit();
            });
        });
        const selected = control.querySelector('.filter-segment.is-selected');
        if (selected) selected.setAttribute('aria-selected', 'true');
    });

    const ticketFilterNames = ['dashboard_view', 'status', 'priority', 'department', 'category', 'subcategory', 'search', 'date_from', 'date_to'];
    const syncFilterActions = (form) => {
        const actions = form?.querySelector('[data-filter-actions]');
        if (!form || !actions) return;
        const hasActiveFilter = ticketFilterNames.some((name) => {
            const field = form.querySelector(`[name="${name}"]`);
            return Boolean(field?.value?.trim());
        });
        actions.hidden = !hasActiveFilter;
    };

    document.querySelectorAll('[data-filter-form]').forEach((form) => {
        syncFilterActions(form);
        form.addEventListener('input', () => syncFilterActions(form));
        form.addEventListener('change', () => syncFilterActions(form));
        const searchInput = form.querySelector('[name="search"]');
        let searchSubmitTimer;
        searchInput?.addEventListener('input', () => {
            clearTimeout(searchSubmitTimer);
            if (form.dataset.preview === 'true') return;
            searchSubmitTimer = setTimeout(() => form.submit(), 450);
        });
    });

    const parseDateValue = (value) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
        const [year, month, day] = value.split('-').map(Number);
        return new Date(year, month - 1, day);
    };

    const dateValue = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const dateLabel = (value) => {
        const date = parseDateValue(value);
        return date ? new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(date) : 'Any date';
    };

    const closeDatePicker = (picker) => {
        const trigger = picker.querySelector('[data-date-trigger]');
        const menu = picker.querySelector('[data-date-menu]');
        if (!trigger || !menu) return;
        picker.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
    };

    const datePickers = Array.from(document.querySelectorAll('[data-date-picker]'));
    datePickers.forEach((picker) => {
        const trigger = picker.querySelector('[data-date-trigger]');
        const menu = picker.querySelector('[data-date-menu]');
        const input = picker.querySelector('[data-date-input]');
        const label = picker.querySelector('[data-date-label]');
        if (!trigger || !menu || !input || !label) return;

        const emptyLabel = picker.dataset.emptyDateLabel || 'Any date';
        let cursor = parseDateValue(input.value) || new Date();
        const renderCalendar = () => {
            const year = cursor.getFullYear();
            const month = cursor.getMonth();
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const monthTitle = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(cursor);
            const weekdayLabels = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
            const cells = [];

            for (let index = 0; index < firstDay; index += 1) cells.push('<span class="date-picker-day is-empty" aria-hidden="true"></span>');
            for (let day = 1; day <= daysInMonth; day += 1) {
                const value = dateValue(new Date(year, month, day));
                const selected = value === input.value ? ' is-selected' : '';
                cells.push(`<button type="button" class="date-picker-day${selected}" data-date-value="${value}">${day}</button>`);
            }

            menu.innerHTML = `<div class="date-picker-menu-head"><strong>${monthTitle}</strong><span><button type="button" class="date-picker-nav" data-date-action="prev" aria-label="Previous month">‹</button><button type="button" class="date-picker-nav" data-date-action="next" aria-label="Next month">›</button></span></div><div class="date-picker-weekdays">${weekdayLabels.map((day) => `<span>${day}</span>`).join('')}</div><div class="date-picker-days">${cells.join('')}</div><div class="date-picker-menu-foot"><span>${input.value ? dateLabel(input.value) : 'No date selected'}</span><button type="button" class="date-picker-clear" data-date-action="clear">Clear</button></div>`;

            menu.querySelector('[data-date-action="prev"]').addEventListener('click', () => {
                cursor = new Date(year, month - 1, 1);
                renderCalendar();
            });
            menu.querySelector('[data-date-action="next"]').addEventListener('click', () => {
                cursor = new Date(year, month + 1, 1);
                renderCalendar();
            });
            menu.querySelector('[data-date-action="clear"]').addEventListener('click', () => {
                input.value = '';
                label.textContent = emptyLabel;
                renderCalendar();
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            menu.querySelectorAll('[data-date-value]').forEach((dayButton) => {
                dayButton.addEventListener('click', () => {
                    input.value = dayButton.dataset.dateValue || '';
                    label.textContent = dateLabel(input.value);
                    renderCalendar();
                    closeDatePicker(picker);
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        };

        renderCalendar();
        trigger.addEventListener('click', () => {
            const isOpen = picker.classList.contains('is-open');
            datePickers.forEach((item) => closeDatePicker(item));
            if (isOpen) return;
            cursor = parseDateValue(input.value) || cursor;
            renderCalendar();
            picker.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            menu.hidden = false;
        });
        input.addEventListener('change', () => {
            const selectedDate = parseDateValue(input.value);
            if (selectedDate) cursor = selectedDate;
            label.textContent = input.value ? dateLabel(input.value) : emptyLabel;
            renderCalendar();
        });
    });

    const formatDateRangeLabel = (from, to) => {
        if (from && to) return `${dateLabel(from)} - ${dateLabel(to)}`;
        if (from) return `From ${dateLabel(from)}`;
        if (to) return `Until ${dateLabel(to)}`;
        return 'Any date range';
    };

    const closeDateRangePicker = (picker) => {
        const trigger = picker.querySelector('[data-date-range-trigger]');
        const menu = picker.querySelector('[data-date-range-menu]');
        if (!trigger || !menu) return;
        picker.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
    };

    const dateRangePickers = Array.from(document.querySelectorAll('[data-date-range-picker]'));
    dateRangePickers.forEach((picker) => {
        const trigger = picker.querySelector('[data-date-range-trigger]');
        const menu = picker.querySelector('[data-date-range-menu]');
        const fromInput = picker.querySelector('[data-date-from]');
        const toInput = picker.querySelector('[data-date-to]');
        const label = picker.querySelector('[data-date-range-label]');
        if (!trigger || !menu || !fromInput || !toInput || !label) return;

        const today = new Date();
        let cursor = parseDateValue(fromInput.value) || parseDateValue(toInput.value) || today;
        cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        const weekdayLabels = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        menu.addEventListener('click', (event) => event.stopPropagation());

        const renderMonth = (year, month, from, to) => {
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const monthDate = new Date(year, month, 1);
            const monthTitle = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(monthDate);
            const cells = [];

            for (let index = 0; index < firstDay; index += 1) cells.push('<span class="date-range-day is-empty" aria-hidden="true"></span>');
            for (let day = 1; day <= daysInMonth; day += 1) {
                const value = dateValue(new Date(year, month, day));
                const selected = value === from || value === to ? ' is-selected' : '';
                const inRange = from && to && value > from && value < to ? ' is-in-range' : '';
                cells.push(`<button type="button" class="date-range-day${selected}${inRange}" data-date-value="${value}" aria-label="${dateLabel(value)}">${day}</button>`);
            }

            return `<section class="date-range-calendar" aria-label="${monthTitle}"><strong class="date-range-month">${monthTitle}</strong><div class="date-range-weekdays">${weekdayLabels.map((day) => `<span>${day}</span>`).join('')}</div><div class="date-range-days">${cells.join('')}</div></section>`;
        };

        const renderCalendar = () => {
            const from = fromInput.value;
            const to = toInput.value;
            const rightMonth = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);
            label.textContent = formatDateRangeLabel(from, to);
            menu.innerHTML = `<div class="date-range-menu-head"><button type="button" class="date-range-nav" data-date-range-action="prev" aria-label="Previous month">&#8249;</button><div class="date-range-months"><strong>${new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(cursor)}</strong><strong>${new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(rightMonth)}</strong></div><button type="button" class="date-range-nav" data-date-range-action="next" aria-label="Next month">&#8250;</button></div><div class="date-range-calendars">${renderMonth(cursor.getFullYear(), cursor.getMonth(), from, to)}${renderMonth(rightMonth.getFullYear(), rightMonth.getMonth(), from, to)}</div><div class="date-range-menu-foot"><span>${from || to ? formatDateRangeLabel(from, to) : 'Select a start and end date'}</span><button type="button" class="date-range-clear" data-date-range-action="clear">Clear</button></div>`;

            menu.querySelector('[data-date-range-action="prev"]').addEventListener('click', () => {
                cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1);
                renderCalendar();
            });
            menu.querySelector('[data-date-range-action="next"]').addEventListener('click', () => {
                cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);
                renderCalendar();
            });
            menu.querySelector('[data-date-range-action="clear"]').addEventListener('click', () => {
                fromInput.value = '';
                toInput.value = '';
                renderCalendar();
                const form = picker.closest('form');
                syncFilterActions(form);
                if (form && form.dataset.preview !== 'true') form.submit();
            });
            menu.querySelectorAll('[data-date-value]').forEach((dayButton) => {
                dayButton.addEventListener('click', () => {
                    const selected = dayButton.dataset.dateValue || '';
                    if (!fromInput.value || toInput.value) {
                        fromInput.value = selected;
                        toInput.value = '';
                        renderCalendar();
                        syncFilterActions(picker.closest('form'));
                        return;
                    }

                    if (selected < fromInput.value) {
                        toInput.value = fromInput.value;
                        fromInput.value = selected;
                    } else {
                        toInput.value = selected;
                    }
                    renderCalendar();
                    const form = picker.closest('form');
                    syncFilterActions(form);
                    closeDateRangePicker(picker);
                    if (form && form.dataset.preview !== 'true') form.submit();
                });
            });
        };

        renderCalendar();
        trigger.addEventListener('click', () => {
            const isOpen = picker.classList.contains('is-open');
            dateRangePickers.forEach((item) => closeDateRangePicker(item));
            if (isOpen) return;
            picker.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            menu.hidden = false;
            renderCalendar();
        });
    });

    const pickers = Array.from(document.querySelectorAll('[data-filter-picker]'));

    const closePicker = (picker) => {
        const trigger = picker.querySelector('[data-filter-trigger]');
        const menu = picker.querySelector('[data-filter-menu]');
        if (!trigger || !menu) return;
        picker.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
    };

    const closeOtherPickers = (current) => {
        pickers.forEach((picker) => {
            if (picker !== current) closePicker(picker);
        });
    };

    pickers.forEach((picker) => {
        const trigger = picker.querySelector('[data-filter-trigger]');
        const menu = picker.querySelector('[data-filter-menu]');
        const label = picker.querySelector('[data-filter-label]');
        if (!trigger || !menu) return;
        const associatedForm = () => picker.closest('form') || (picker.dataset.accessForm ? document.getElementById(picker.dataset.accessForm) : null);

        trigger.addEventListener('click', () => {
            const isOpen = picker.classList.contains('is-open');
            dateRangePickers.forEach(closeDateRangePicker);
            closeOtherPickers(picker);
            if (isOpen) {
                closePicker(picker);
                return;
            }
            picker.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            menu.hidden = false;
        });

        menu.querySelectorAll('.filter-option').forEach((option) => {
            option.addEventListener('click', () => {
                const nextLabel = option.dataset.filterLabel || option.textContent.trim();
                if (label) label.textContent = nextLabel;
                const summary = picker.querySelector('[data-filter-summary]');
                if (summary) summary.textContent = nextLabel;
                menu.querySelectorAll('.filter-option').forEach((item) => item.classList.remove('is-selected'));
                option.classList.add('is-selected');

                const form = associatedForm();
                const target = option.dataset.filterTarget;
                if (form && target) {
                    const input = form.querySelector(`[name="${target}"]`);
                    if (input) input.value = option.dataset.filterValue || '';
                }

                syncFilterActions(form);
                closePicker(picker);
                if (form && form.dataset.submitOnChange !== 'false' && form.dataset.preview !== 'true') form.submit();
            });
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-filter-picker]')) pickers.forEach(closePicker);
        if (!event.target.closest('[data-date-picker]')) datePickers.forEach(closeDatePicker);
        if (!event.target.closest('[data-date-range-picker]')) dateRangePickers.forEach(closeDateRangePicker);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') pickers.forEach(closePicker);
        if (event.key === 'Escape') datePickers.forEach(closeDatePicker);
        if (event.key === 'Escape') dateRangePickers.forEach(closeDateRangePicker);
    });

    document.querySelectorAll('[data-access-switch]').forEach((button) => {
        const form = button.closest('form');
        const input = form?.querySelector('[data-access-active-input]');
        const label = button.querySelector('[data-access-switch-label]');
        if (!form || !input || !label) return;

        button.addEventListener('click', () => {
            if (button.disabled) return;
            const isActive = button.getAttribute('aria-pressed') !== 'true';
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            button.classList.toggle('is-on', isActive);
            label.textContent = isActive ? 'Enabled' : 'Disabled';
            if (isActive) input.name = 'is_active';
            else input.removeAttribute('name');
        });
    });

    const accessRoleButtons = Array.from(document.querySelectorAll('[data-access-role-target]'));
    const accessRolePanes = Array.from(document.querySelectorAll('[data-access-role-pane]'));
    if (accessRoleButtons.length && accessRolePanes.length) {
        const selectAccessRole = (target) => {
            accessRoleButtons.forEach((button) => {
                const selected = button.dataset.accessRoleTarget === target;
                button.classList.toggle('is-selected', selected);
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            accessRolePanes.forEach((pane) => {
                pane.hidden = pane.dataset.accessRolePane !== target;
            });
        };

        accessRoleButtons.forEach((button) => button.addEventListener('click', () => selectAccessRole(button.dataset.accessRoleTarget || '')));
    }

    const departmentPickers = Array.from(document.querySelectorAll('[data-department-picker]'));

    const departmentSelection = (picker) => Array.from(picker.querySelectorAll('[data-department-option][aria-pressed="true"]'))
        .map((option) => option.dataset.departmentId || '')
        .filter(Boolean)
        .sort((left, right) => Number(left) - Number(right));

    const positionDepartmentPicker = (picker) => {
        const trigger = picker.querySelector('[data-department-trigger]');
        const menu = picker.querySelector('[data-department-menu]');
        if (!trigger || !menu || menu.hidden) return;
        const triggerRect = trigger.getBoundingClientRect();
        const menuWidth = menu.offsetWidth || 260;
        const menuHeight = menu.offsetHeight || 320;
        const left = Math.max(12, Math.min(triggerRect.right - menuWidth, window.innerWidth - menuWidth - 12));
        let top = triggerRect.bottom + 6;
        if (top + menuHeight > window.innerHeight - 12) top = Math.max(12, triggerRect.top - menuHeight - 6);
        menu.style.left = `${Math.round(left)}px`;
        menu.style.top = `${Math.round(top)}px`;
    };

    const closeDepartmentPicker = (picker, returnFocus = false) => {
        const trigger = picker.querySelector('[data-department-trigger]');
        const menu = picker.querySelector('[data-department-menu]');
        if (!trigger || !menu) return;
        picker.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
        if (returnFocus) trigger.focus();
    };

    const syncDepartmentPicker = (picker) => {
        const form = picker.closest('[data-department-form]');
        const inputContainer = form?.querySelector('[data-department-inputs]');
        const triggerLabel = picker.querySelector('[data-department-trigger-label]');
        const summary = picker.querySelector('[data-department-selection-summary]');
        const saveButton = picker.querySelector('[data-department-save]');
        const currentId = picker.dataset.currentDepartment || '';

        picker.querySelectorAll('[data-department-option]').forEach((option) => {
            if (option.dataset.departmentId === currentId) option.setAttribute('aria-pressed', 'true');
            const isSelected = option.getAttribute('aria-pressed') === 'true';
            option.classList.toggle('is-selected', isSelected);
        });

        const selectedIds = departmentSelection(picker);
        if (inputContainer) {
            inputContainer.replaceChildren();
            selectedIds.forEach((departmentId) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'department_ids[]';
                input.value = departmentId;
                inputContainer.appendChild(input);
            });
        }
        const label = `${selectedIds.length} selected`;
        if (triggerLabel) triggerLabel.textContent = label;
        if (summary) summary.textContent = label;
        if (saveButton) saveButton.disabled = selectedIds.join(',') === (picker.dataset.initialSelection || '');
    };

    departmentPickers.forEach((picker) => {
        const trigger = picker.querySelector('[data-department-trigger]');
        const menu = picker.querySelector('[data-department-menu]');
        const closeButton = picker.querySelector('[data-department-close]');
        const form = picker.closest('[data-department-form]');
        if (!trigger || !menu || !form) return;

        picker.dataset.initialSelection = Array.from(form.querySelectorAll('[data-department-inputs] input[name="department_ids[]"]'))
            .map((input) => input.value)
            .filter(Boolean)
            .sort((left, right) => Number(left) - Number(right))
            .join(',');
        syncDepartmentPicker(picker);

        const openPicker = (focusFirst = false) => {
            departmentPickers.forEach((item) => {
                if (item !== picker) closeDepartmentPicker(item);
            });
            pickers.forEach(closePicker);
            dateRangePickers.forEach(closeDateRangePicker);
            picker.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            menu.hidden = false;
            positionDepartmentPicker(picker);
            if (focusFirst) picker.querySelector('[data-department-option]')?.focus();
        };

        trigger.addEventListener('click', () => {
            if (picker.classList.contains('is-open')) {
                closeDepartmentPicker(picker);
                return;
            }
            openPicker();
        });
        trigger.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowDown') return;
            event.preventDefault();
            openPicker(true);
        });
        closeButton?.addEventListener('click', () => closeDepartmentPicker(picker, true));

        const options = Array.from(picker.querySelectorAll('[data-department-option]'));
        options.forEach((option) => {
            option.addEventListener('click', () => {
                if (option.hasAttribute('data-department-locked')) return;
                option.setAttribute('aria-pressed', option.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
                syncDepartmentPicker(picker);
            });
            option.addEventListener('keydown', (event) => {
                if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
                event.preventDefault();
                const index = options.indexOf(option);
                const offset = event.key === 'ArrowDown' ? 1 : -1;
                options[(index + offset + options.length) % options.length]?.focus();
            });
        });

        form.addEventListener('submit', (event) => {
            if (form.dataset.preview !== 'true') return;
            event.preventDefault();
            picker.dataset.initialSelection = departmentSelection(picker).join(',');
            syncDepartmentPicker(picker);
            closeDepartmentPicker(picker, true);
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-department-picker]')) departmentPickers.forEach(closeDepartmentPicker);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        departmentPickers.forEach((picker) => {
            if (picker.classList.contains('is-open')) closeDepartmentPicker(picker, true);
        });
    });
    window.addEventListener('resize', () => departmentPickers.forEach(positionDepartmentPicker));
    document.addEventListener('scroll', (event) => {
        if (event.target instanceof Element && event.target.closest('[data-department-menu]')) return;
        departmentPickers.forEach((picker) => {
            if (picker.classList.contains('is-open')) positionDepartmentPicker(picker);
        });
    }, true);

    document.querySelectorAll('[data-auto-date-submit]').forEach((form) => {
        const input = form.querySelector('[data-date-input]');
        if (!input) return;
        input.addEventListener('change', () => {
            if (input.value) form.requestSubmit();
        });
    });

    const uiSelects = Array.from(document.querySelectorAll('select:not([data-native-select])'));
    const closeUiSelect = (select, restoreFocus = false) => {
        const control = select.nextElementSibling;
        if (!(control instanceof HTMLElement) || !control.matches('[data-ui-select-control]')) return;
        control.classList.remove('is-open');
        const trigger = control.querySelector('[data-ui-select-trigger]');
        const menu = control.querySelector('[data-ui-select-menu]');
        trigger?.setAttribute('aria-expanded', 'false');
        if (menu instanceof HTMLElement) menu.hidden = true;
        if (restoreFocus && trigger instanceof HTMLElement) trigger.focus();
    };

    uiSelects.forEach((select, selectIndex) => {
        select.classList.add('ui-select-native');
        const control = document.createElement('div');
        control.className = 'ui-select-control';
        control.dataset.uiSelectControl = '';
        const menuId = `ui-select-menu-${selectIndex}`;
        control.innerHTML = `<button type="button" class="ui-select-trigger" data-ui-select-trigger aria-haspopup="listbox" aria-expanded="false" aria-controls="${menuId}"><span data-ui-select-label></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="ui-select-menu" id="${menuId}" data-ui-select-menu role="listbox"${select.multiple ? ' aria-multiselectable="true"' : ''} hidden></div>`;
        select.insertAdjacentElement('afterend', control);
        const trigger = control.querySelector('[data-ui-select-trigger]');
        const label = control.querySelector('[data-ui-select-label]');
        const menu = control.querySelector('[data-ui-select-menu]');
        if (!(trigger instanceof HTMLButtonElement) || !(label instanceof HTMLElement) || !(menu instanceof HTMLElement)) return;

        const updateLabel = () => {
            const selected = Array.from(select.selectedOptions).filter((option) => option.value !== '');
            const placeholder = select.dataset.uiPlaceholder || select.options[0]?.textContent || 'Select option';
            if (!selected.length) label.textContent = placeholder;
            else if (!select.multiple || selected.length === 1) label.textContent = selected[0].textContent || selected[0].value;
            else label.textContent = `${selected[0].textContent} +${selected.length - 1}`;
        };

        const renderOptions = () => {
            menu.innerHTML = '';
            Array.from(select.options).forEach((option) => {
                const optionButton = document.createElement('button');
                optionButton.type = 'button';
                optionButton.className = `ui-select-option${option.selected ? ' is-selected' : ''}`;
                optionButton.dataset.uiSelectValue = option.value;
                optionButton.setAttribute('role', 'option');
                optionButton.setAttribute('aria-selected', option.selected ? 'true' : 'false');
                optionButton.disabled = option.disabled;
                optionButton.innerHTML = `<span></span><i aria-hidden="true">✓</i>`;
                const optionLabel = optionButton.querySelector('span');
                if (optionLabel) optionLabel.textContent = option.textContent || option.value;
                optionButton.addEventListener('click', () => {
                    if (select.multiple) option.selected = !option.selected;
                    else {
                        select.value = option.value;
                        Array.from(select.options).forEach((item) => { item.selected = item === option; });
                    }
                    updateLabel();
                    renderOptions();
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    if (!select.multiple) closeUiSelect(select, true);
                });
                menu.append(optionButton);
            });
            updateLabel();
        };

        renderOptions();
        trigger.addEventListener('click', () => {
            const shouldOpen = !control.classList.contains('is-open');
            uiSelects.forEach((item) => closeUiSelect(item));
            if (!shouldOpen) return;
            renderOptions();
            control.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            menu.hidden = false;
        });
        select.addEventListener('change', updateLabel);
        select.addEventListener('ui-options-changed', renderOptions);
        select.addEventListener('invalid', () => trigger.focus());
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-ui-select-control]')) uiSelects.forEach((select) => closeUiSelect(select));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') uiSelects.forEach((select) => closeUiSelect(select, true));
    });

    document.querySelectorAll('input[type="file"]').forEach((input) => {
        input.classList.add('ui-file-native');
        const control = document.createElement('div');
        control.className = 'ui-file-control';
        control.innerHTML = '<button type="button" class="ui-file-button">Choose file</button><span class="ui-file-name">No file selected</span>';
        input.insertAdjacentElement('afterend', control);
        const button = control.querySelector('.ui-file-button');
        const fileName = control.querySelector('.ui-file-name');
        button?.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            if (fileName) fileName.textContent = input.files?.[0]?.name || 'No file selected';
        });
        input.addEventListener('invalid', () => button?.focus());
    });

    document.querySelectorAll('[data-ticket-href]').forEach((row) => {
        const ticketLink = row.querySelector('.ticket-id a[href], .subject a[href]');
        const href = row.dataset.ticketHref || ticketLink?.getAttribute('href');
        if (!href) return;
        row.dataset.ticketHref = href;
        if (!row.hasAttribute('tabindex')) row.tabIndex = 0;
        if (!row.hasAttribute('aria-label')) row.setAttribute('aria-label', `Open ${ticketLink?.textContent?.trim() || 'ticket'}`);

        const openRow = () => window.location.assign(href);
        row.addEventListener('click', (event) => {
            if (event.target.closest('a, button, input, textarea, select')) return;
            openRow();
        });
        row.addEventListener('keydown', (event) => {
            if (event.target.closest('a, button, input, textarea, select')) return;
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            openRow();
        });
    });

    const bulkActions = document.querySelector('[data-bulk-actions]');
    if (bulkActions) {
        const syncBulkSelectionMode = () => {
            document.body.classList.toggle('bulk-selection-mode', bulkActions.open);
        };
        const selectedInputs = bulkActions.querySelector('[data-bulk-selected-inputs]');
        const selectionCount = bulkActions.querySelector('[data-bulk-selection-count]');
        const selectVisible = bulkActions.querySelector('[data-bulk-select-visible]');
        const clearSelection = bulkActions.querySelector('[data-bulk-clear-selection]');
        const ticketCheckboxes = Array.from(document.querySelectorAll('[data-bulk-ticket-checkbox]'));
        const syncSelectedInputs = (selected) => {
            if (!selectedInputs) return;
            selectedInputs.replaceChildren(...selected.map((checkbox) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ticket_ids[]';
                input.value = checkbox.value;
                return input;
            }));
        };
        const updateSelectionState = (message = '') => {
            const selected = ticketCheckboxes.filter((checkbox) => checkbox.checked);
            syncSelectedInputs(selected);
            if (selectionCount) {
                selectionCount.textContent = message || `${selected.length} selected`;
                selectionCount.classList.toggle('is-error', Boolean(message));
            }
            if (selectVisible) selectVisible.disabled = !ticketCheckboxes.length;
            if (clearSelection) clearSelection.disabled = !selected.length;
        };

        bulkActions.addEventListener('toggle', syncBulkSelectionMode);
        syncBulkSelectionMode();
        ticketCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', () => updateSelectionState()));
        selectVisible?.addEventListener('click', () => {
            ticketCheckboxes.forEach((checkbox) => { checkbox.checked = true; });
            updateSelectionState();
        });
        clearSelection?.addEventListener('click', () => {
            ticketCheckboxes.forEach((checkbox) => { checkbox.checked = false; });
            updateSelectionState();
        });
        updateSelectionState();
    }

    const accountMenu = document.querySelector('[data-account-menu]');
    const accountMenuTrigger = document.querySelector('[data-account-menu-trigger]');
    if (accountMenu && accountMenuTrigger) {
        const accountMenuItems = accountMenu.querySelectorAll('[role="menuitem"]');
        const closeAccountMenu = (returnFocus = false) => {
            accountMenu.hidden = true;
            accountMenuTrigger.setAttribute('aria-expanded', 'false');
            if (returnFocus) accountMenuTrigger.focus();
        };

        accountMenuTrigger.addEventListener('click', () => {
            const isOpen = accountMenuTrigger.getAttribute('aria-expanded') === 'true';
            if (isOpen) {
                closeAccountMenu(true);
                return;
            }
            accountMenu.hidden = false;
            accountMenuTrigger.setAttribute('aria-expanded', 'true');
            accountMenuItems[0]?.focus();
        });
        accountMenuItems.forEach((item) => item.addEventListener('click', () => closeAccountMenu()));
        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-account-menu-trigger], [data-account-menu]')) closeAccountMenu();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && accountMenuTrigger.getAttribute('aria-expanded') === 'true') {
                event.preventDefault();
                closeAccountMenu(true);
            }
        });
    }

    const themeChoices = document.querySelectorAll('[data-theme-choice]');
    const themeStatus = document.querySelector('[data-theme-status]');
    const themeStorageKey = 'cng-ticketing-theme';
    let savedTheme = 'light';
    try {
        savedTheme = window.localStorage.getItem(themeStorageKey) === 'dark' ? 'dark' : 'light';
    } catch (error) {
        savedTheme = 'light';
    }

    const applyTheme = (theme, persist = true) => {
        const nextTheme = theme === 'dark' ? 'dark' : 'light';
        document.documentElement.dataset.theme = nextTheme;
        themeChoices.forEach((choice) => {
            const isSelected = choice.dataset.themeChoice === nextTheme;
            choice.classList.toggle('is-selected', isSelected);
            choice.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
        if (themeStatus) themeStatus.textContent = `${nextTheme === 'dark' ? 'Dark' : 'Light'} theme is active.`;
        if (persist) {
            try {
                window.localStorage.setItem(themeStorageKey, nextTheme);
            } catch (error) {
                // Continue with the selected theme for this page when storage is unavailable.
            }
        }
    };

    applyTheme(savedTheme, false);
    themeChoices.forEach((choice) => choice.addEventListener('click', () => applyTheme(choice.dataset.themeChoice)));

    const notificationPopover = document.querySelector('[data-notification-popover]');
    const notificationTrigger = document.querySelector('[data-notification-popover-trigger]');
    if (notificationPopover && notificationTrigger) {
        const closeButton = notificationPopover.querySelector('[data-notification-popover-close]');
        let notificationReturnFocus = null;

        const closeNotificationPopover = (restoreFocus = true) => {
            notificationPopover.hidden = true;
            notificationTrigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus) notificationReturnFocus?.focus();
        };

        const openNotificationPopover = () => {
            notificationReturnFocus = document.activeElement;
            notificationPopover.hidden = false;
            notificationTrigger.setAttribute('aria-expanded', 'true');
            closeButton?.focus();
        };

        notificationTrigger.addEventListener('click', (event) => {
            event.preventDefault();
            if (notificationPopover.hidden) openNotificationPopover();
            else closeNotificationPopover();
        });
        closeButton?.addEventListener('click', () => closeNotificationPopover());
        document.addEventListener('click', (event) => {
            if (!notificationPopover.hidden && !event.target.closest('[data-notification-popover], [data-notification-popover-trigger]')) closeNotificationPopover(false);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !notificationPopover.hidden) {
                event.preventDefault();
                closeNotificationPopover();
            }
        });
    }

    const userDrawer = document.querySelector('[data-user-drawer]');
    const userDrawerTrigger = document.querySelector('[data-user-drawer-trigger]');
    const userDrawerScrim = document.querySelector('[data-user-drawer-scrim]');
    if (userDrawer && userDrawerTrigger) {
        const userDrawerCloseButtons = userDrawer.querySelectorAll('[data-user-drawer-close]');
        let userDrawerReturnFocus = null;

        const closeUserDrawer = () => {
            userDrawer.hidden = true;
            if (userDrawerScrim) userDrawerScrim.hidden = true;
            document.body.classList.remove('user-drawer-open');
            userDrawerTrigger.setAttribute('aria-expanded', 'false');
            userDrawerReturnFocus?.focus();
        };

        const openUserDrawer = () => {
            userDrawerReturnFocus = document.activeElement;
            userDrawer.hidden = false;
            if (userDrawerScrim) userDrawerScrim.hidden = false;
            document.body.classList.add('user-drawer-open');
            userDrawerTrigger.setAttribute('aria-expanded', 'true');
            userDrawer.querySelector('input[name="full_name"]')?.focus();
        };

        userDrawerTrigger.addEventListener('click', openUserDrawer);
        userDrawerScrim?.addEventListener('click', closeUserDrawer);
        userDrawerCloseButtons.forEach((button) => button.addEventListener('click', closeUserDrawer));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !userDrawer.hidden) closeUserDrawer();
        });
    }
});

/* Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/public/js/app.js
   Cleaned and consolidated version with improved error handling and code organization
*/

const { jsPDF } = window.jspdf;

$(document).ready(function () {

    // ==================== CONFIGURATION ====================
    const API_URL = '/api/v1';

    // ==================== GLOBAL STATE ====================
    let allTasks = [];
    let sortableInstance = null;
    let currentTeams = [];
    let currentSelectedTeamId = null;
    let currentUserId = null;
    let currentCategory = 'All';
    let searchQuery = '';
    let lastDeletedTask = null;
    let undoTimeout = null;
    let calendarInstance = null;
    let chartInstances = {};
    let notificationInterval = null;

    // ==================== UI ELEMENTS ====================
    // Auth Elements
    const $appContainer = $('#app-container');
    const $authContainer = $('#auth-container');
    const $loginCard = $('#login-card');
    const $registerCard = $('#register-card');
    const $loginForm = $('#login-form');
    const $registerForm = $('#register-form');
    const $loginAlert = $('#login-alert');
    const $registerAlert = $('#register-alert');
    const $showRegisterLink = $('#show-register-link');
    const $showLoginLink = $('#show-login-link');
    const $logoutButton = $('#btn-logout');
    const $userDisplayName = $('#user-display-name');

    // Task List Elements
    const $taskListLoading = $('#task-list-loading');
    const $taskListContainer = $('#task-list-container');
    const $taskListEmpty = $('#task-list-empty');

    // Add Task Modal
    const $btnShowAddTaskModal = $('#btn-show-add-task-modal');
    const $addTaskModalElement = document.getElementById('add-task-modal');
    const $addTaskModal = $addTaskModalElement ? new bootstrap.Modal($addTaskModalElement) : null;
    const $addTaskForm = $('#add-task-form');
    const $addTaskAlert = $('#add-task-alert');

    // Edit Task Modal
    const $editTaskModalElement = document.getElementById('edit-task-modal');
    const $editTaskModal = $editTaskModalElement ? new bootstrap.Modal($editTaskModalElement) : null;
    const $editTaskForm = $('#edit-task-form');
    const $editTaskAlert = $('#edit-task-alert');

    // AI Buttons
    const $btnAiSuggest = $('#btn-ai-suggest');
    const $btnEditAiSuggest = $('#btn-edit-ai-suggest');

    // Export Buttons
    const $btnExportCsv = $('#btn-export-csv');
    const $btnExportPdf = $('#btn-export-pdf');

    // Teams Modal
    const $btnManageTeams = $('#btn-manage-teams');
    const $teamsModalElement = document.getElementById('teams-modal');
    const $teamsModal = $teamsModalElement ? new bootstrap.Modal($teamsModalElement) : null;
    const $teamList = $('#team-list');
    const $createTeamForm = $('#create-team-form');
    const $createTeamAlert = $('#create-team-alert');
    const $teamDetailsContent = $('#team-details-content');
    const $teamDetailsLoading = $('#team-details-loading');
    const $teamDetailsPlaceholder = $('#team-details-placeholder');
    const $selectedTeamName = $('#selected-team-name');
    const $teamMemberList = $('#team-member-list');
    const $addMemberForm = $('#add-member-form');
    const $addMemberAlert = $('#add-member-alert');

    // Share Modal
    const $shareTaskModalElement = document.getElementById('share-task-modal');
    const $shareTaskModal = $shareTaskModalElement ? new bootstrap.Modal($shareTaskModalElement) : null;
    const $shareTaskTitle = $('#share-task-title');
    const $shareTaskId = $('#share-task-id');
    const $shareTaskForm = $('#share-task-form');
    const $shareTeamSelect = $('#share-team-select');
    const $shareTaskAlert = $('#share-task-alert');
    const $shareListLoading = $('#share-list-loading');
    const $taskShareList = $('#task-share-list');

    // Notification Elements
    const $notificationBell = $('#notification-bell');
    const $notificationCount = $('#notification-count');
    const $notificationList = $('#notification-list');
    const $btnMarkAllRead = $('#btn-mark-all-read');

    // Comment Elements
    const $commentList = $('#comment-list');
    const $commentListLoading = $('#comment-list-loading');
    const $commentForm = $('#comment-form');
    const $commentText = $('#comment-text');
    const $commentAlert = $('#comment-alert');

    // Search & Filter
    const $taskSearch = $('#task-search');
    const $categoryNav = $('#category-nav');

    // Undo Delete Toast
    const $undoToastEl = document.getElementById('undo-toast');
    const undoToast = $undoToastEl ? new bootstrap.Toast($undoToastEl) : null;

    // Calendar
    const $calendarContainer = $('#calendar-container');
    const $btnCalendarView = $('#btn-calendar-view');
    const $btnListView = $('#btn-list-view');

    // Analytics
    const $btnShowAnalytics = $('#btn-show-analytics');
    const $analyticsModalEl = document.getElementById('analytics-modal');
    const $analyticsModal = $analyticsModalEl ? new bootstrap.Modal($analyticsModalEl) : null;

    // Subtasks
    const $subtaskList = $('#subtask-list');
    const $subtaskForm = $('#subtask-form');
    const $subtaskTitle = $('#subtask-title');


    // ==================== ERROR HANDLING ====================
    function getErrorMessage(xhr, defaultMessage) {
        if (xhr.responseJSON && xhr.responseJSON.error) {
            return xhr.responseJSON.error;
        }
        if (xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }
        return defaultMessage;
    }

    // ==================== TINYMCE INIT ====================
    const tinymceSettings = {
        plugins: 'lists link autoresize image',
        toolbar: 'undo redo | bold italic | bullist numlist | link image',
        menubar: false,
        height: 200,
        autoresize_bottom_margin: 15,
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }',
        images_upload_url: '/api/v1/uploads',
        automatic_uploads: true,
        file_picker_types: 'image',
        images_upload_base_path: '/',
        // Note: For production, you'd replace '1ss0z6pjai0mykkq561ite7i9s26jnkevlloa0gu6cratvsz' with your own API key
    };

    if (window.tinymce) {
        try {
            tinymce.init({ selector: '#task-description', ...tinymceSettings });
            tinymce.init({ selector: '#edit-task-description', ...tinymceSettings });
        } catch (e) {
            console.warn('TinyMCE init failed. Using textareas.', e);
        }
    }

    // ==================== INDEXEDDB HELPERS ====================
    const idbAvailable = typeof idb !== 'undefined' && idb && typeof idb.initDb === 'function';

    function ensureIdbInit() {
        if (!idbAvailable) return Promise.resolve(false);
        try {
            return idb.initDb();
        } catch (e) {
            console.warn('idb.initDb error', e);
            return Promise.resolve(false);
        }
    }

    // ==================== SANITIZATION ====================
    function sanitizeHtml(dirtyHtml) {
        // Basic sanitizer to prevent XSS. For production, consider DOMPurify.
        if (!dirtyHtml) return '';
        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(dirtyHtml, 'text/html');
            doc.querySelectorAll('script, style, link, meta').forEach(el => el.remove());
            const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_ELEMENT, null, false);
            while (walker.nextNode()) {
                const el = walker.currentNode;
                const attrs = Array.from(el.attributes || []);
                attrs.forEach(attr => {
                    const name = attr.name.toLowerCase();
                    const val = (attr.value || '').toString().toLowerCase();
                    if (name.startsWith('on') || val.includes('javascript:')) {
                        el.removeAttribute(attr.name);
                    }
                });
            }
            return doc.body.innerHTML;
        } catch (e) {
            return '';
        }
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // ==================== TASK RENDERING ====================
    function renderTask(task) {
        const isChecked = task.is_completed == 1 || task.is_completed === true ? 'checked' : '';
        const titleClass = (task.is_completed == 1 || task.is_completed === true) ? 'text-decoration-line-through text-muted' : 'fw-bold';

        let tagsHtml = '';
        if (task.tags) {
            try {
                const tags = typeof task.tags === 'string' ? JSON.parse(task.tags) : task.tags;
                if (Array.isArray(tags) && tags.length) {
                    tagsHtml = tags.map(tag => `<span class="badge bg-secondary me-1">${escapeHtml(tag)}</span>`).join(' ');
                }
            } catch (e) { }
        }

        let commentBadge = '';
        if (task.comment_count > 0) {
            commentBadge = `
                <span class="badge bg-secondary ms-2" data-action="edit" data-id="${task.id}" style="cursor: pointer;" title="View Comments">
                    <i class="bi bi-chat-dots-fill"></i> ${task.comment_count}
                </span>`;
        }

        const safeDescriptionHtml = sanitizeHtml(task.description || '');

        let buttons = '';
        if (typeof currentUserId !== 'undefined' && task.user_id == currentUserId) {
            buttons = `
                <button class="btn btn-sm btn-outline-primary me-1" data-action="share" data-id="${task.id}" title="Share Task">
                    <i class="bi bi-person-plus-fill"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary me-1" data-action="edit" data-id="${task.id}" title="Edit Task">
                    <i class="bi bi-pencil-fill"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${task.id}" title="Delete Task">
                    <i class="bi bi-trash-fill"></i>
                </button>`;
        } else {
            buttons = `<span class="badge bg-info" title="Shared by another user">Shared</span>`;
        }

        return `
            <div class="card mb-2 task-card" data-task-id="${task.id}">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <input type="checkbox" class="form-check-input me-2 task-complete-check" ${isChecked} data-id="${task.id}">
                        <span class="${titleClass}">${escapeHtml(task.title)}</span>
                        ${commentBadge}
                        <div class="mb-1 text-muted small task-description-html">${safeDescriptionHtml}</div>
                        <div class="mt-2">${tagsHtml}</div>
                    </div>
                    <div class="flex-shrink-0 ms-3 text-end">
                        <span class="badge bg-primary d-block mb-2">${escapeHtml(task.priority || '')}</span>
                        ${buttons}
                    </div>
                </div>
            </div>`;
    }

    function getFilteredTasks() {
        let filtered = allTasks;
        if (currentCategory && currentCategory !== 'All') {
            filtered = filtered.filter(t => t.category === currentCategory);
        }
        if (searchQuery.trim()) {
            const q = searchQuery.toLowerCase();
            filtered = filtered.filter(t => {
                const titleMatch = (t.title || '').toLowerCase().includes(q);
                const descMatch = (t.description || '').toLowerCase().includes(q);
                let tagMatch = false;
                try {
                    const tags = typeof t.tags === 'string' ? JSON.parse(t.tags) : (t.tags || []);
                    tagMatch = tags.some(tag => tag.toLowerCase().includes(q));
                } catch (e) { }
                return titleMatch || descMatch || tagMatch;
            });
        }
        return filtered;
    }

    function renderTaskList() {
        $taskListLoading.addClass('d-none');
        $taskListContainer.find('.task-card').remove();

        const filtered = getFilteredTasks();

        if (!Array.isArray(filtered) || filtered.length === 0) {
            $taskListEmpty.removeClass('d-none');
            initSortable();
            return;
        }

        $taskListEmpty.addClass('d-none');
        filtered.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
        filtered.forEach(task => $taskListContainer.append(renderTask(task)));
        initSortable();
    }

    // ==================== CATEGORY FILTER ====================
    $categoryNav.on('click', 'a[data-category]', function (e) {
        e.preventDefault();
        $categoryNav.find('a').removeClass('active');
        $(this).addClass('active');
        currentCategory = $(this).data('category');
        const title = currentCategory === 'All' ? 'All Tasks' : currentCategory;
        $('#current-category-title').text(title);
        renderTaskList();
    });

    // ==================== SEARCH ====================
    let searchTimeout = null;
    $taskSearch.on('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchQuery = $(this).val();
            renderTaskList();
        }, 300);
    });

    // ==================== SORTABLEJS ====================
    function initSortable() {
        if (typeof Sortable === 'undefined') return;
        if (sortableInstance) {
            try { sortableInstance.destroy(); } catch (e) { }
            sortableInstance = null;
        }
        sortableInstance = new Sortable($taskListContainer[0], {
            animation: 150,
            handle: '.task-card',
            draggable: '.task-card',
            onEnd: saveTaskOrder
        });
    }

    function saveTaskOrder() {
        const orderedIds = [];
        $taskListContainer.find('.task-card').each(function () {
            orderedIds.push(parseInt($(this).data('task-id'), 10));
        });

        let order = 0;
        allTasks.sort((a, b) => orderedIds.indexOf(a.id) - orderedIds.indexOf(b.id));
        allTasks.forEach(task => {
            task.sort_order = order++;
            if (idbAvailable && typeof idb.putTask === 'function') {
                try { idb.putTask(task); } catch (e) { console.warn('idb.putTask error', e); }
            }
        });

        $.ajax({
            url: `${API_URL}/tasks/reorder`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ ordered_ids: orderedIds }),
            error: () => alert('Error saving new task order. Please refresh the page.')
        });
    }

    // ==================== DATA SYNC ====================
    async function loadTasks() {
        $taskListLoading.removeClass('d-none');
        $taskListEmpty.addClass('d-none');

        if (idbAvailable && typeof idb.getTasks === 'function') {
            try {
                const cached = await idb.getTasks();
                if (Array.isArray(cached) && cached.length) {
                    allTasks = cached.slice();
                    renderTaskList();
                }
            } catch (e) { console.warn('idb.getTasks failed', e); }
        }

        $.ajax({
            url: `${API_URL}/tasks`,
            method: 'GET',
            success: async function (tasks) {
                if (!Array.isArray(tasks)) tasks = [];
                allTasks = tasks.slice();

                if (idbAvailable && typeof idb.putAllTasks === 'function') {
                    try { await idb.putAllTasks(tasks); }
                    catch (e) { console.warn('idb.putAllTasks failed', e); }
                }
                renderTaskList(); // Render network truth
            },
            error: function (xhr) {
                $taskListLoading.addClass('d-none');
                if (xhr.status === 401) showAuthScreen();
                else if (!allTasks.length) {
                    $taskListContainer.prepend('<p class="text-center text-danger">Error loading tasks. (Offline?)</p>');
                }
                initSortable();
            }
        });
    }

    // ==================== AUTH FLOW ====================
    function checkSession() {
        $.ajax({
            url: `${API_URL}/auth/me`,
            method: 'GET',
            success: function (response) {
                if (response.isLoggedIn) {
                    currentUserId = response.user.id;
                    initializeApp(response.user);
                } else {
                    showAuthScreen();
                }
            },
            error: showAuthScreen
        });
    }

    // initializeApp is defined below in INITIALIZE section

    function showAuthScreen() {
        $appContainer.addClass('d-none');
        $authContainer.removeClass('d-none');
        showLoginCard();
    }

    function showLoginCard() {
        $registerCard.addClass('d-none');
        $loginCard.removeClass('d-none');
        $loginAlert.addClass('d-none');
    }

    function showRegisterCard() {
        $loginCard.addClass('d-none');
        $registerCard.removeClass('d-none');
        $registerAlert.addClass('d-none');
    }

    $showRegisterLink.on('click', (e) => { e.preventDefault(); showRegisterCard(); });
    $showLoginLink.on('click', (e) => { e.preventDefault(); showLoginCard(); });

    $loginForm.on('submit', function (e) {
        e.preventDefault();
        const payload = {
            email: $('#login-email').val(),
            password: $('#login-password').val()
        };
        $.ajax({
            url: `${API_URL}/auth/login`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: checkSession,
            error: xhr => $loginAlert.text(getErrorMessage(xhr, 'Login failed.')).removeClass('d-none')
        });
    });

    $registerForm.on('submit', function (e) {
        e.preventDefault();
        const payload = {
            username: $('#register-username').val(),
            email: $('#register-email').val(),
            password: $('#register-password').val()
        };
        $.ajax({
            url: `${API_URL}/auth/register`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: () => {
                showLoginCard();
                $loginAlert.text('Account created — please log in.').removeClass('d-none');
            },
            error: xhr => $registerAlert.text(getErrorMessage(xhr, 'Registration failed.')).removeClass('d-none')
        });
    });

    $logoutButton.on('click', function (e) {
        e.preventDefault();
        const clearPromise = (idbAvailable && typeof idb.putAllTasks === 'function')
            ? idb.putAllTasks([]).catch(e => console.warn('idb.clear on logout failed', e))
            : Promise.resolve();

        clearPromise.finally(() => {
            $.ajax({
                url: `${API_URL}/auth/logout`,
                method: 'POST',
                success: showAuthScreen,
                error: showAuthScreen
            });
        });
    });

    // ==================== TASK CRUD FLOW ====================
    $btnShowAddTaskModal.on('click', function () {
        $addTaskAlert.addClass('d-none');
        $addTaskForm[0].reset();
        if (window.tinymce && tinymce.get('task-description')) tinymce.get('task-description').setContent('');
        else $('#task-description').val('');
        if ($addTaskModal) $addTaskModal.show();
    });

    $addTaskForm.on('submit', function (e) {
        e.preventDefault();
        const tags = ($('#task-tags').val() || '').split(',').map(t => t.trim()).filter(t => t.length > 0);
        const description = (window.tinymce && tinymce.get('task-description'))
            ? tinymce.get('task-description').getContent()
            : $('#task-description').val();

        const taskData = {
            title: $('#task-title').val(),
            description: description,
            priority: $('#task-priority').val(),
            category: $('#task-category').val(),
            due_date: $('#task-due-date').val() || null,
            tags: tags
        };

        $.ajax({
            url: `${API_URL}/tasks`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(taskData),
            success: async function (newTask) {
                if ($addTaskModal) $addTaskModal.hide();

                if (idbAvailable && typeof idb.putTask === 'function') {
                    try { await idb.putTask(newTask); }
                    catch (err) { console.warn('idb.putTask on create failed', err); }
                }
                allTasks.push(newTask);
                renderTaskList();
            },
            error: xhr => $addTaskAlert.text(getErrorMessage(xhr, 'Failed to create task.')).removeClass('d-none')
        });
    });

    $taskListContainer.on('click', '[data-action="edit"]', function () {
        handleEditTask($(this).data('id'));
    });

    $taskListContainer.on('click', 'button[data-action="delete"]', function () {
        handleDeleteTask($(this).data('id'));
    });

    $taskListContainer.on('click', 'input.task-complete-check', function () {
        const $checkbox = $(this);
        const taskId = $checkbox.data('id');
        const isCompleted = $checkbox.is(':checked');

        const task = allTasks.find(t => t.id == taskId);
        if (task) {
            task.is_completed = isCompleted;
            renderTaskList();
            if (idbAvailable && typeof idb.putTask === 'function') {
                try { idb.putTask(task); } catch (e) { console.warn('idb.putTask error', e); }
            }
        }

        $.ajax({
            url: `${API_URL}/tasks/${taskId}`,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ is_completed: isCompleted }),
            success: async function (updatedTask) {
                if (idbAvailable && typeof idb.putTask === 'function') {
                    try { await idb.putTask(updatedTask); }
                    catch (e) { console.warn('idb.putTask error on server confirm', e); }
                }
                allTasks = allTasks.map(t => t.id == updatedTask.id ? updatedTask : t);
                renderTaskList();
            },
            error: () => {
                alert('Error syncing task status. Change may be lost on refresh.');
                if (task) {
                    task.is_completed = !isCompleted;
                    renderTaskList();
                    if (idbAvailable && typeof idb.putTask === 'function') try { idb.putTask(task); } catch (e) { }
                }
            }
        });
    });

    function handleEditTask(taskId) {
        const task = allTasks.find(t => t.id == taskId);
        if (!task) return;

        $editTaskAlert.addClass('d-none');
        $('#edit-task-id').val(task.id);
        $('#edit-task-title').val(task.title);
        $('#edit-task-priority').val(task.priority);
        $('#edit-task-category').val(task.category);
        $('#edit-task-completed').prop('checked', task.is_completed == 1 || task.is_completed === true);

        if (window.tinymce && tinymce.get('edit-task-description')) {
            tinymce.get('edit-task-description').setContent(task.description || '');
        } else {
            $('#edit-task-description').val(task.description || '');
        }

        let dueDate = "";
        if (task.due_date) {
            try {
                const d = new Date(task.due_date);
                d.setSeconds(0, 0);
                dueDate = d.toISOString().slice(0, 16);
            } catch (e) { dueDate = ''; }
        }
        $('#edit-task-due-date').val(dueDate);

        let tags = "";
        if (task.tags) {
            try {
                const t = typeof task.tags === 'string' ? JSON.parse(task.tags) : task.tags;
                if (Array.isArray(t)) tags = t.join(', ');
            } catch (e) { }
        }
        $('#edit-task-tags').val(tags);

        if ($editTaskModal) $editTaskModal.show();
        loadComments(taskId);
        loadSubtasks(taskId);
    }

    $editTaskForm.on('submit', function (e) {
        e.preventDefault();
        const taskId = $('#edit-task-id').val();
        const tags = ($('#edit-task-tags').val() || '').split(',').map(t => t.trim()).filter(t => t.length > 0);
        const description = (window.tinymce && tinymce.get('edit-task-description'))
            ? tinymce.get('edit-task-description').getContent()
            : $('#edit-task-description').val();

        const taskData = {
            title: $('#edit-task-title').val(),
            description: description,
            priority: $('#edit-task-priority').val(),
            category: $('#edit-task-category').val(),
            is_completed: $('#edit-task-completed').is(':checked'),
            due_date: $('#edit-task-due-date').val() || null,
            tags: tags
        };

        $.ajax({
            url: `${API_URL}/tasks/${taskId}`,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(taskData),
            success: async function (updatedTask) {
                if ($editTaskModal) $editTaskModal.hide();

                if (idbAvailable && typeof idb.putTask === 'function') {
                    try { await idb.putTask(updatedTask); }
                    catch (e) { console.warn('idb.putTask on update failed', e); }
                }
                allTasks = allTasks.map(t => t.id == updatedTask.id ? updatedTask : t);
                renderTaskList();
            },
            error: xhr => $editTaskAlert.text(getErrorMessage(xhr, 'Failed to update task.')).removeClass('d-none')
        });
    });

    function handleDeleteTask(taskId) {
        if (!confirm('Are you sure you want to delete this task?')) return;

        lastDeletedTask = allTasks.find(t => t.id == taskId);
        allTasks = allTasks.filter(t => t.id != taskId);
        renderTaskList();

        if (undoToast) undoToast.show();
        clearTimeout(undoTimeout);
        undoTimeout = setTimeout(() => {
            // Actually delete on server after undo window expires
            $.ajax({
                url: `${API_URL}/tasks/${taskId}`,
                method: 'DELETE',
                success: () => {
                    if (idbAvailable && typeof idb.deleteTask === 'function') {
                        try { idb.deleteTask(taskId); } catch (e) { }
                    }
                },
                error: () => { loadTasks(); }
            });
            lastDeletedTask = null;
        }, 5000);
    }

    // Undo delete handler
    $('#btn-undo-delete').on('click', function () {
        if (lastDeletedTask) {
            clearTimeout(undoTimeout);
            allTasks.push(lastDeletedTask);
            renderTaskList();
            lastDeletedTask = null;
            if (undoToast) undoToast.hide();
        }
    });

    // ==================== AI SUGGESTIONS ====================
    function getAiSuggestion(title, $button, editorId) {
        if (!title) {
            alert('Please enter a task title first.');
            return;
        }

        $button.prop('disabled', true).find('.spinner-border').removeClass('d-none');
        $button.find('.bi-robot').addClass('d-none');

        $.ajax({
            url: `${API_URL}/ai/suggest`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ title: title }),
            success: function (response) {
                if (window.tinymce && tinymce.get(editorId)) {
                    tinymce.get(editorId).setContent(response.suggestion.replace(/\n/g, '<br>'));
                }
            },
            error: xhr => alert(getErrorMessage(xhr, 'Failed to get AI suggestion.')),
            complete: () => {
                $button.prop('disabled', false).find('.spinner-border').addClass('d-none');
                $button.find('.bi-robot').removeClass('d-none');
            }
        });
    }
    $btnAiSuggest.on('click', function () { getAiSuggestion($('#task-title').val(), $(this), 'task-description'); });
    $btnEditAiSuggest.on('click', function () { getAiSuggestion($('#edit-task-title').val(), $(this), 'edit-task-description'); });

    // ==================== EXPORT ====================
    function stripHtml(html) {
        let doc = new DOMParser().parseFromString(html, 'text/html');
        return doc.body.textContent || "";
    }

    function exportToCSV() {
        if (allTasks.length === 0) return alert('No tasks to export.');

        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "ID,Title,Description,Category,Priority,Status,DueDate,Tags\r\n";

        allTasks.forEach(task => {
            const row = [
                task.id,
                `"${task.title.replace(/"/g, '""')}"`,
                `"${stripHtml(task.description || '').replace(/"/g, '""')}"`,
                task.category,
                task.priority,
                task.is_completed ? "Completed" : "Pending",
                task.due_date ? new Date(task.due_date).toLocaleString() : "N/A",
                `"${(JSON.parse(task.tags || '[]')).join(', ')}"`
            ].join(',');
            csvContent += row + "\r\n";
        });

        const link = document.createElement("a");
        link.setAttribute("href", encodeURI(csvContent));
        link.setAttribute("download", "tasks_export.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function exportToPDF() {
        if (allTasks.length === 0) return alert('No tasks to export.');

        const doc = new jsPDF();
        doc.setFontSize(18);
        doc.text("Task Export", 14, 22);
        doc.setFontSize(11);
        doc.setTextColor(100);
        doc.text(`Exported on: ${new Date().toLocaleString()}`, 14, 28);

        let y = 40;

        allTasks.forEach(task => {
            if (y > 270) { doc.addPage(); y = 20; }

            const status = task.is_completed ? '[DONE]' : '[PENDING]';
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.text(`${status} ${task.title}`, 14, y);

            y += 6;
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(80);
            doc.text(`Priority: ${task.priority} | Category: ${task.category}`, 16, y);
            y += 5;

            if (task.description) {
                const desc = `Description: ${stripHtml(task.description)}`;
                const splitDesc = doc.splitTextToSize(desc, 180);
                doc.text(splitDesc, 16, y);
                y += (splitDesc.length * 4);
            }
            if (task.due_date) {
                doc.text(`Due: ${new Date(task.due_date).toLocaleString()}`, 16, y);
                y += 5;
            }
            y += 4;
            doc.setDrawColor(200);
            doc.line(14, y, 196, y);
            y += 6;
        });
        doc.save('tasks_export.pdf');
    }
    $btnExportCsv.on('click', exportToCSV);
    $btnExportPdf.on('click', exportToPDF);

    // ==================== TEAMS ====================
    function handleShowTeamsModal() {
        if ($teamsModal) $teamsModal.show();
        $teamDetailsContent.addClass('d-none');
        $teamDetailsPlaceholder.removeClass('d-none');
        $createTeamAlert.addClass('d-none');
        $addMemberAlert.addClass('d-none');

        $.ajax({
            url: `${API_URL}/teams`,
            method: 'GET',
            success: (teams) => { currentTeams = teams; renderTeamList(); },
            error: () => $createTeamAlert.text('Failed to load teams.').removeClass('d-none')
        });
    }

    function renderTeamList() {
        $teamList.empty();
        if (currentTeams.length === 0) {
            $teamList.html('<p class="text-muted">You are not in any teams yet.</p>');
            return;
        }
        currentTeams.forEach(team => {
            $teamList.append(`
                <a href="#" class="list-group-item list-group-item-action" data-team-id="${team.id}">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${escapeHtml(team.team_name)}</h6>
                        <small class="badge bg-primary rounded-pill">${escapeHtml(team.role)}</small>
                    </div>
                </a>`);
        });
    }

    function handleSelectTeam(teamId) {
        currentSelectedTeamId = teamId;
        $teamDetailsPlaceholder.addClass('d-none');
        $teamDetailsContent.addClass('d-none');
        $teamDetailsLoading.removeClass('d-none');
        $addMemberAlert.addClass('d-none');

        $.ajax({
            url: `${API_URL}/teams/${teamId}`,
            method: 'GET',
            success: function (team) {
                $selectedTeamName.text(team.team_name);
                const myRole = team.members.find(m => m.id == currentUserId);

                if (myRole && (myRole.role === 'Owner' || myRole.role === 'Admin')) {
                    $addMemberForm.removeClass('d-none');
                } else {
                    $addMemberForm.addClass('d-none');
                }

                renderTeamMemberList(team.members);
                $teamDetailsLoading.addClass('d-none');
                $teamDetailsContent.removeClass('d-none');
            },
            error: () => {
                $teamDetailsLoading.addClass('d-none');
                $teamDetailsPlaceholder.text('Error loading team details.').removeClass('d-none');
            }
        });
    }

    function renderTeamMemberList(members) {
        $teamMemberList.empty();
        members.forEach(member => {
            const removeButton = member.role === 'Owner' ? '' :
                `<button class="btn btn-sm btn-outline-danger" data-action="remove-member" data-user-id="${member.id}">
                    <i class="bi bi-person-x-fill"></i>
                 </button>`;

            $teamMemberList.append(`
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${escapeHtml(member.username)}</strong>
                        <small class="text-muted d-block">${escapeHtml(member.email)}</small>
                    </div>
                    <div>
                        <span class="badge bg-secondary me-2">${escapeHtml(member.role)}</span>
                        ${removeButton}
                    </div>
                </li>`);
        });
    }

    $btnManageTeams.on('click', handleShowTeamsModal);

    $teamList.on('click', 'a[data-team-id]', function (e) {
        e.preventDefault();
        $teamList.find('a').removeClass('active');
        $(this).addClass('active');
        handleSelectTeam($(this).data('team-id'));
    });

    $createTeamForm.on('submit', function (e) {
        e.preventDefault();
        const teamName = $('#create-team-name').val();
        $.ajax({
            url: `${API_URL}/teams`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ team_name: teamName }),
            success: function (newTeam) {
                currentTeams.push(newTeam);
                renderTeamList();
                $createTeamForm[0].reset();
                $createTeamAlert.addClass('d-none');
            },
            error: xhr => $createTeamAlert.text(getErrorMessage(xhr, 'Failed to create team.')).removeClass('d-none')
        });
    });

    $addMemberForm.on('submit', function (e) {
        e.preventDefault();
        if (!currentSelectedTeamId) return;
        const payload = {
            email: $('#add-member-email').val(),
            role: $('#add-member-role').val()
        };
        $.ajax({
            url: `${API_URL}/teams/${currentSelectedTeamId}/members`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: (members) => {
                renderTeamMemberList(members);
                $addMemberForm[0].reset();
                $addMemberAlert.addClass('d-none');
            },
            error: xhr => $addMemberAlert.text(getErrorMessage(xhr, 'Failed to add member.')).removeClass('d-none')
        });
    });

    $teamMemberList.on('click', 'button[data-action="remove-member"]', function () {
        if (!currentSelectedTeamId) return;
        const userIdToRemove = $(this).data('user-id');
        if (!confirm('Are you sure you want to remove this member?')) return;

        $.ajax({
            url: `${API_URL}/teams/${currentSelectedTeamId}/members/${userIdToRemove}`,
            method: 'DELETE',
            success: renderTeamMemberList,
            error: xhr => alert(getErrorMessage(xhr, 'Failed to remove member.'))
        });
    });

    // ==================== TASK SHARING ====================
    $taskListContainer.on('click', 'button[data-action="share"]', function () {
        handleShowShareModal($(this).data('id'));
    });

    function handleShowShareModal(taskId) {
        const task = allTasks.find(t => t.id == taskId);
        if (!task) return;

        if ($shareTaskModal) $shareTaskModal.show();
        $shareTaskTitle.text(task.title);
        $shareTaskId.val(task.id);
        $shareTaskAlert.addClass('d-none');
        $taskShareList.empty();
        $shareListLoading.removeClass('d-none');
        $shareTeamSelect.empty().append('<option value="">Loading teams...</option>');

        const sharesPromise = $.ajax({ url: `${API_URL}/tasks/${taskId}/shares`, method: 'GET' });
        const teamsPromise = $.ajax({ url: `${API_URL}/teams`, method: 'GET' });

        Promise.all([sharesPromise, teamsPromise]).then(([sharedTeams, myTeams]) => {
            $shareListLoading.addClass('d-none');

            if (!Array.isArray(sharedTeams) || !Array.isArray(myTeams)) {
                $shareTaskAlert.text('Failed to load sharing data.').removeClass('d-none');
                return;
            }

            renderTaskShareList(sharedTeams);

            $shareTeamSelect.empty();
            const sharedTeamIds = sharedTeams.map(st => st.id);
            const availableTeams = myTeams.filter(myTeam =>
                (myTeam.role === 'Owner' || myTeam.role === 'Admin') &&
                !sharedTeamIds.includes(myTeam.id)
            );

            if (availableTeams.length > 0) {
                $shareTeamSelect.append('<option value="">Select a team to share with...</option>');
                availableTeams.forEach(team => {
                    $shareTeamSelect.append(`<option value="${team.id}">${escapeHtml(team.team_name)}</option>`);
                });
            } else {
                $shareTeamSelect.append('<option value="">No available teams to share with</option>');
            }
        }).catch(() => {
            $shareListLoading.addClass('d-none');
            $shareTaskAlert.text('Error fetching sharing data.').removeClass('d-none');
        });
    }

    function renderTaskShareList(sharedTeams) {
        $taskShareList.empty();
        if (sharedTeams.length === 0) {
            $taskShareList.html('<li class="list-group-item text-muted">Not shared with any teams yet.</li>');
            return;
        }
        sharedTeams.forEach(team => {
            $taskShareList.append(`
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    ${escapeHtml(team.team_name)}
                    <button class="btn btn-sm btn-outline-danger" data-action="unshare" data-team-id="${team.id}">
                        Unshare
                    </button>
                </li>`);
        });
    }

    $shareTaskForm.on('submit', function (e) {
        e.preventDefault();
        const taskId = $shareTaskId.val();
        const teamId = $shareTeamSelect.val();

        if (!teamId) return $shareTaskAlert.text('Please select a team.').removeClass('d-none');

        $.ajax({
            url: `${API_URL}/tasks/${taskId}/shares`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ team_id: teamId, permission: 'View' }),
            success: () => handleShowShareModal(taskId),
            error: xhr => $shareTaskAlert.text(getErrorMessage(xhr, 'Failed to share task.')).removeClass('d-none')
        });
    });

    $taskShareList.on('click', 'button[data-action="unshare"]', function () {
        const taskId = $shareTaskId.val();
        const teamId = $(this).data('team-id');

        if (!confirm('Are you sure you want to unshare this task?')) return;

        $.ajax({
            url: `${API_URL}/tasks/${taskId}/shares/${teamId}`,
            method: 'DELETE',
            success: () => handleShowShareModal(taskId),
            error: xhr => alert(getErrorMessage(xhr, 'Failed to unshare task.'))
        });
    });

    // ==================== COMMENTS ====================
    function renderComment(comment) {
        return `
            <div class="d-flex mb-2">
                <div class="flex-shrink-0 me-2">
                    <i class="bi bi-person-circle fs-5"></i>
                </div>
                <div class="flex-grow-1 bg-light rounded p-2">
                    <strong class="d-block">${escapeHtml(comment.username)}</strong>
                    ${escapeHtml(comment.comment)}
                    <small class="text-muted d-block mt-1">
                        ${new Date(comment.created_at).toLocaleString()}
                    </small>
                </div>
            </div>`;
    }

    function loadComments(taskId) {
        $commentList.empty();
        $commentAlert.addClass('d-none');
        $commentListLoading.removeClass('d-none');

        $.ajax({
            url: `${API_URL}/tasks/${taskId}/comments`,
            method: 'GET',
            success: function (comments) {
                $commentListLoading.addClass('d-none');
                if (comments.length === 0) {
                    $commentList.html('<p class="text-muted text-center small">No comments yet.</p>');
                } else {
                    comments.forEach(comment => $commentList.append(renderComment(comment)));
                    $commentList.scrollTop($commentList[0].scrollHeight);
                }
            },
            error: xhr => {
                $commentListLoading.addClass('d-none');
                $commentAlert.text(getErrorMessage(xhr, 'Failed to load comments.')).removeClass('d-none');
            }
        });
    }

    $commentForm.on('submit', function (e) {
        e.preventDefault();

        const commentText = $commentText.val();
        const taskId = $('#edit-task-id').val();

        if (!commentText.trim()) return;

        $commentAlert.addClass('d-none');

        $.ajax({
            url: `${API_URL}/tasks/${taskId}/comments`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ comment: commentText }),
            success: function (newComment) {
                if ($commentList.find('p').length > 0) {
                    $commentList.empty();
                }

                $commentList.append(renderComment(newComment));
                $commentText.val('');
                $commentList.scrollTop($commentList[0].scrollHeight);
            },
            error: xhr => {
                $commentAlert.text(getErrorMessage(xhr, 'Failed to post comment.')).removeClass('d-none');
            }
        });
    });

    // ==================== NOTIFICATIONS ====================
    function fetchNotifications() {
        $.ajax({
            url: `${API_URL}/notifications`,
            method: 'GET',
            success: function (data) {
                const notifs = data.notifications || [];
                const unread = data.unread_count || 0;

                if (unread > 0) {
                    $notificationCount.text(unread > 99 ? '99+' : unread).removeClass('d-none');
                } else {
                    $notificationCount.addClass('d-none');
                }

                $notificationList.empty();
                if (notifs.length === 0) {
                    $notificationList.html('<li class="text-center text-muted p-3"><small>No notifications</small></li>');
                    return;
                }

                notifs.forEach(n => {
                    const timeAgo = getTimeAgo(n.created_at);
                    const icon = getNotifIcon(n.action);
                    const readClass = n.is_read == 1 ? 'opacity-50' : '';
                    const msg = formatNotifMessage(n);
                    $notificationList.append(`
                        <li class="px-3 py-2 border-bottom ${readClass}" style="font-size: 0.85rem;">
                            <i class="bi ${icon} me-1"></i> ${msg}
                            <small class="text-muted d-block">${timeAgo}</small>
                        </li>`);
                });
            },
            error: () => { }
        });
    }

    function getNotifIcon(action) {
        const icons = {
            'new_comment': 'bi-chat-dots-fill text-info',
            'shared_task': 'bi-share-fill text-primary',
            'added_member': 'bi-person-plus-fill text-success',
            'completed_task': 'bi-check-circle-fill text-success',
            'created_task': 'bi-plus-circle-fill text-primary'
        };
        return icons[action] || 'bi-bell-fill';
    }

    function formatNotifMessage(n) {
        const actor = escapeHtml(n.actor_username || 'Someone');
        const task = escapeHtml(n.task_title || 'a task');
        const msgs = {
            'new_comment': `<strong>${actor}</strong> commented on "${task}"`,
            'shared_task': `<strong>${actor}</strong> shared "${task}" with your team`,
            'added_member': `<strong>${actor}</strong> added you to a team`,
            'completed_task': `<strong>${actor}</strong> completed "${task}"`,
            'created_task': `<strong>${actor}</strong> created "${task}"`
        };
        return msgs[n.action] || `<strong>${actor}</strong> performed an action`;
    }

    function getTimeAgo(dateStr) {
        const now = new Date();
        const date = new Date(dateStr);
        const diff = Math.floor((now - date) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    $btnMarkAllRead.on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $.ajax({
            url: `${API_URL}/notifications/mark-read`,
            method: 'POST',
            success: () => {
                $notificationCount.addClass('d-none');
                $notificationList.find('li').addClass('opacity-50');
            }
        });
    });

    // ==================== KEYBOARD SHORTCUTS ====================
    $(document).on('keydown', function (e) {
        // Don't trigger in input fields
        if ($(e.target).is('input, textarea, select, [contenteditable]')) return;

        if (e.key === 'n' || e.key === 'N') {
            e.preventDefault();
            $btnShowAddTaskModal.click();
        } else if (e.key === '/') {
            e.preventDefault();
            $taskSearch.focus();
        } else if (e.key === 'Escape') {
            // Close any open modal
            $('.modal.show').each(function () {
                bootstrap.Modal.getInstance(this)?.hide();
            });
        }
    });

    // ==================== CALENDAR VIEW ====================
    function initCalendar() {
        if (typeof FullCalendar === 'undefined') return;
        const calEl = document.getElementById('fullcalendar');
        if (!calEl) return;

        calendarInstance = new FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek'
            },
            events: allTasks.filter(t => t.due_date).map(t => ({
                id: t.id,
                title: t.title,
                start: t.due_date,
                color: t.is_completed ? '#2ecc71' : getPriorityColor(t.priority),
                extendedProps: { task: t }
            })),
            eventClick: function (info) {
                handleEditTask(info.event.id);
            },
            height: 'auto'
        });
        calendarInstance.render();
    }

    function getPriorityColor(priority) {
        const colors = { 'Critical': '#e74c3c', 'High': '#f39c12', 'Medium': '#667eea', 'Low': '#95a5a6' };
        return colors[priority] || '#667eea';
    }

    function refreshCalendar() {
        if (!calendarInstance) return;
        calendarInstance.removeAllEvents();
        allTasks.filter(t => t.due_date).forEach(t => {
            calendarInstance.addEvent({
                id: t.id,
                title: t.title,
                start: t.due_date,
                color: t.is_completed ? '#2ecc71' : getPriorityColor(t.priority),
                extendedProps: { task: t }
            });
        });
    }

    $btnCalendarView.on('click', function (e) {
        e.preventDefault();
        $calendarContainer.removeClass('d-none');
        $taskListContainer.addClass('d-none');
        $taskListEmpty.addClass('d-none');
        if (!calendarInstance) initCalendar();
        else refreshCalendar();
    });

    $btnListView.on('click', function () {
        $calendarContainer.addClass('d-none');
        $taskListContainer.removeClass('d-none');
        renderTaskList();
    });

    // ==================== ANALYTICS ====================
    $btnShowAnalytics.on('click', function (e) {
        e.preventDefault();
        if ($analyticsModal) $analyticsModal.show();
        renderAnalytics();
    });

    function renderAnalytics() {
        const total = allTasks.length;
        const completed = allTasks.filter(t => t.is_completed == 1).length;
        const pending = total - completed;
        const now = new Date();
        const overdue = allTasks.filter(t => !t.is_completed && t.due_date && new Date(t.due_date) < now).length;

        $('#stat-total').text(total);
        $('#stat-completed').text(completed);
        $('#stat-pending').text(pending);
        $('#stat-overdue').text(overdue);

        // Category chart
        const categories = ['Urgent', 'Today', 'Weekly', 'Monthly', 'Long-Term', 'Completed', 'Uncompleted'];
        const catCounts = categories.map(c => allTasks.filter(t => t.category === c).length);
        const catColors = ['#e74c3c', '#f39c12', '#2ecc71', '#17a2b8', '#6c757d', '#28a745', '#adb5bd'];

        if (chartInstances.category) chartInstances.category.destroy();
        const catCtx = document.getElementById('chart-category');
        if (catCtx) {
            chartInstances.category = new Chart(catCtx, {
                type: 'doughnut',
                data: { labels: categories, datasets: [{ data: catCounts, backgroundColor: catColors }] },
                options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#fff' } } } }
            });
        }

        // Priority chart
        const priorities = ['Critical', 'High', 'Medium', 'Low'];
        const priCounts = priorities.map(p => allTasks.filter(t => t.priority === p).length);
        const priColors = ['#e74c3c', '#f39c12', '#667eea', '#95a5a6'];

        if (chartInstances.priority) chartInstances.priority.destroy();
        const priCtx = document.getElementById('chart-priority');
        if (priCtx) {
            chartInstances.priority = new Chart(priCtx, {
                type: 'bar',
                data: { labels: priorities, datasets: [{ label: 'Tasks', data: priCounts, backgroundColor: priColors }] },
                options: {
                    responsive: true, scales: { y: { beginAtZero: true, ticks: { color: '#fff' } }, x: { ticks: { color: '#fff' } } },
                    plugins: { legend: { labels: { color: '#fff' } } }
                }
            });
        }

        // Timeline chart
        const last7 = [];
        for (let i = 6; i >= 0; i--) {
            const d = new Date(); d.setDate(d.getDate() - i);
            last7.push(d.toISOString().split('T')[0]);
        }
        const completedPerDay = last7.map(day => allTasks.filter(t => t.completed_at && t.completed_at.startsWith(day)).length);
        const createdPerDay = last7.map(day => allTasks.filter(t => t.created_at && t.created_at.startsWith(day)).length);

        if (chartInstances.timeline) chartInstances.timeline.destroy();
        const tlCtx = document.getElementById('chart-timeline');
        if (tlCtx) {
            chartInstances.timeline = new Chart(tlCtx, {
                type: 'line',
                data: {
                    labels: last7.map(d => new Date(d).toLocaleDateString('en', { weekday: 'short' })),
                    datasets: [
                        { label: 'Created', data: createdPerDay, borderColor: '#667eea', tension: 0.4, fill: false },
                        { label: 'Completed', data: completedPerDay, borderColor: '#2ecc71', tension: 0.4, fill: false }
                    ]
                },
                options: {
                    responsive: true, scales: { y: { beginAtZero: true, ticks: { color: '#fff' } }, x: { ticks: { color: '#fff' } } },
                    plugins: { legend: { labels: { color: '#fff' } } }
                }
            });
        }
    }

    // ==================== SUBTASKS ====================
    function loadSubtasks(parentTaskId) {
        $subtaskList.empty();
        $.ajax({
            url: `${API_URL}/tasks/${parentTaskId}/subtasks`,
            method: 'GET',
            success: function (subtasks) {
                if (!Array.isArray(subtasks) || subtasks.length === 0) {
                    $subtaskList.html('<p class="text-muted small text-center">No subtasks yet.</p>');
                    return;
                }
                subtasks.forEach(st => {
                    const checked = st.is_completed == 1 ? 'checked' : '';
                    const textClass = st.is_completed == 1 ? 'text-decoration-line-through text-muted' : '';
                    $subtaskList.append(`
                        <div class="d-flex align-items-center mb-1">
                            <input type="checkbox" class="form-check-input me-2 subtask-check" data-id="${st.id}" ${checked}>
                            <span class="flex-grow-1 ${textClass}">${escapeHtml(st.title)}</span>
                            <button class="btn btn-sm btn-outline-danger btn-subtask-delete" data-id="${st.id}"><i class="bi bi-x"></i></button>
                        </div>`);
                });
            },
            error: () => $subtaskList.html('<p class="text-muted small">Could not load subtasks.</p>')
        });
    }

    $subtaskForm.on('submit', function (e) {
        e.preventDefault();
        const parentId = $('#edit-task-id').val();
        const title = $subtaskTitle.val().trim();
        if (!title || !parentId) return;

        $.ajax({
            url: `${API_URL}/tasks/${parentId}/subtasks`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ title: title }),
            success: function () {
                $subtaskTitle.val('');
                loadSubtasks(parentId);
            },
            error: xhr => alert(getErrorMessage(xhr, 'Failed to add subtask.'))
        });
    });

    $subtaskList.on('change', '.subtask-check', function () {
        const subtaskId = $(this).data('id');
        const isCompleted = $(this).is(':checked');
        $.ajax({
            url: `${API_URL}/tasks/${subtaskId}`,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ is_completed: isCompleted }),
            success: () => loadSubtasks($('#edit-task-id').val())
        });
    });

    $subtaskList.on('click', '.btn-subtask-delete', function () {
        const subtaskId = $(this).data('id');
        $.ajax({
            url: `${API_URL}/tasks/${subtaskId}`,
            method: 'DELETE',
            success: () => loadSubtasks($('#edit-task-id').val())
        });
    });

    // ==================== INITIALIZE ====================
    function initializeApp(user) {
        $userDisplayName.text(user.username);
        $authContainer.addClass('d-none');
        $appContainer.removeClass('d-none');
        ensureIdbInit().then(loadTasks);
        initSortable();
        fetchNotifications();
        notificationInterval = setInterval(fetchNotifications, 30000);
    }

    checkSession();

});
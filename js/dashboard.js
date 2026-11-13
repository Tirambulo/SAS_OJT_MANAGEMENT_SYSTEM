(function () {
    'use strict';

    const root = '../../';
    const apiRoot = root + 'backend/';
    const body = document.body;
    const section = body.dataset.role;
    const page = body.dataset.page;
    const token = sessionStorage.getItem('sasOjtCsrfToken') || '';
    const api = {
        adminStats: 'administrator/system-controller.php?action=overview',
        activity: 'administrator/system-controller.php?action=activity',
        settings: 'administrator/system-controller.php?action=settings',
        users: 'administrator/user-controller.php',
        companies: 'administrator/company-controller.php',
        students: 'sas-staff/student-controller.php',
        verification: 'sas-staff/verification-controller.php',
        assignments: 'coordinator/assignment-controller.php',
        monitoring: 'coordinator/monitoring-controller.php',
        reports: 'shared/report-controller.php',
        performance: 'company/performance-controller.php',
        attendance: 'company/attendance-controller.php',
        feedback: 'company/feedback-controller.php',
        company: 'company/portal-controller.php',
        jobs: 'company/job-controller.php',
        student: 'student/student-controller.php',
        journal: 'student/journal-controller.php',
        requirements: 'student/requirement-controller.php',
        evaluation: 'student/evaluation-controller.php',
        notifications: 'shared/notification-controller.php'
    };

    const pageInfo = {
        'administrator/dashboard': ['Administrator dashboard', 'A current overview of the OJT program.', api.adminStats],
        'administrator/company-accounts': ['Company accounts', 'Manage company supervisor accounts.', api.users + '?search='],
        'administrator/company-list': ['Company directory', 'Review registered partner companies.', api.companies],
        'administrator/company-registration': ['Register a company', 'Add a partner company to the system.', api.companies],
        'administrator/faculty-accounts': ['Coordinator accounts', 'Manage faculty coordinator access.', api.users],
        'administrator/role-management': ['Role management', 'View account roles and access status.', api.users],
        'administrator/staff-accounts': ['Staff accounts', 'Manage administrator and SAS staff access.', api.users],
        'administrator/student-accounts': ['Student accounts', 'Review student records and OJT progress.', api.students],
        'administrator/system-monitoring': ['System activity', 'Review recent system actions.', api.activity],
        'administrator/system-settings': ['System settings', 'Manage application settings.', api.settings],
        'administrator/user-management': ['User management', 'Create accounts and manage account access.', api.users],
        'company/assigned-students': ['Assigned students', 'View students placed with your company.', api.company + '?action=assigned'],
        'company/attendance-monitoring': ['Attendance', 'Record student hours and review attendance.', api.attendance],
        'company/company-profile': ['Company profile', 'Keep company contact details current.', api.company + '?action=profile'],
        'company/dashboard': ['Company dashboard', 'Monitor active students and pending work.', api.company + '?action=dashboard'],
        'company/employer-feedback': ['Evaluations', 'Review and submit student performance evaluations.', api.feedback],
        'company/job-descriptions': ['Job descriptions', 'Publish and manage OJT opportunities.', api.jobs],
        'company/student-evaluation': ['Student evaluations', 'Submit a performance review for a placed student.', api.feedback],
        'company/student-hours': ['Student hours', 'Review placement hours and progress.', api.company + '?action=hours'],
        'coordinator/company-assignment': ['Company assignment', 'Place eligible students with partner companies.', api.assignments + '?action=options'],
        'coordinator/company-list': ['Company directory', 'Review partner companies.', api.companies],
        'coordinator/dashboard': ['Coordinator dashboard', 'Monitor placements and student progress.', api.monitoring],
        'coordinator/deployment-records': ['Deployment records', 'Review and update placement records.', api.assignments],
        'coordinator/evaluations': ['Evaluations', 'Review student performance evaluations.', api.performance],
        'coordinator/ojt-monitoring': ['OJT monitoring', 'Track active placements and rendered hours.', api.monitoring],
        'coordinator/reports': ['OJT reports', 'Review and download student placement data.', api.reports],
        'coordinator/students': ['Students', 'Search students assigned to your department.', api.students],
        'sas-staff/compliance-reports': ['Compliance reports', 'Review student requirements and OJT compliance.', api.reports],
        'sas-staff/dashboard': ['SAS staff dashboard', 'Review records requiring Student Affairs attention.', api.students],
        'sas-staff/document-verification': ['Document verification', 'Approve or return student-submitted documents.', api.verification],
        'sas-staff/insurance-verification': ['Insurance verification', 'Review submitted insurance-related requirements.', api.verification],
        'sas-staff/moa-verification': ['MOA verification', 'Review training agreements and MOA submissions.', api.verification],
        'sas-staff/student-records': ['Student records', 'Search student profiles and OJT status.', api.students],
        'sas-staff/submitted-requirements': ['Submitted requirements', 'Review pending student documents.', api.verification],
        'sas-staff/waiver-verification': ['Waiver verification', 'Review consent and waiver submissions.', api.verification],
        'student/attendance': ['My attendance', 'View attendance entries and approved hours.', api.student + '?action=attendance'],
        'student/company-evaluation': ['Company evaluation', 'Share feedback about your OJT placement.', api.evaluation],
        'student/daily-journal': ['Daily journal', 'Record OJT activities and learning.', api.journal],
        'student/dashboard': ['Student dashboard', 'Review OJT progress and next steps.', api.student],
        'student/my-company': ['My company', 'View your current OJT placement.', api.student],
        'student/ojt-hours': ['OJT hours', 'Track approved hours against your requirement.', api.student + '?action=hours'],
        'student/profile': ['My profile', 'Keep your contact details up to date.', api.student],
        'student/ptr': ['OJT requirements', 'View the documents needed for your placement.', api.requirements],
        'student/requirement-status': ['Requirement status', 'Track document reviews and feedback.', api.requirements],
        'student/requirements': ['Submit requirements', 'Upload documents for review.', api.requirements]
    };

    const nav = {
        administrator: [
            ['Overview', 'dashboard'], ['Users', 'user-management'], ['Students', 'student-accounts'],
            ['Companies', 'company-list'], ['Register company', 'company-registration'],
            ['Roles', 'role-management'], ['Staff accounts', 'staff-accounts'],
            ['Coordinator accounts', 'faculty-accounts'], ['Company accounts', 'company-accounts'],
            ['System activity', 'system-monitoring'], ['Settings', 'system-settings']
        ],
        coordinator: [
            ['Overview', 'dashboard'], ['Monitor OJT', 'ojt-monitoring'], ['Students', 'students'],
            ['Assign company', 'company-assignment'], ['Placements', 'deployment-records'],
            ['Companies', 'company-list'], ['Evaluations', 'evaluations'], ['Reports', 'reports']
        ],
        'sas-staff': [
            ['Overview', 'dashboard'], ['Student records', 'student-records'],
            ['Submitted requirements', 'submitted-requirements'], ['Document verification', 'document-verification'],
            ['MOA verification', 'moa-verification'], ['Waiver verification', 'waiver-verification'],
            ['Insurance verification', 'insurance-verification'], ['Compliance reports', 'compliance-reports']
        ],
        company: [
            ['Overview', 'dashboard'], ['Assigned students', 'assigned-students'],
            ['Attendance', 'attendance-monitoring'], ['Student hours', 'student-hours'],
            ['Evaluations', 'student-evaluation'], ['Job descriptions', 'job-descriptions'],
            ['Company profile', 'company-profile']
        ],
        student: [
            ['Overview', 'dashboard'], ['My company', 'my-company'], ['OJT hours', 'ojt-hours'],
            ['Attendance', 'attendance'], ['Daily journal', 'daily-journal'],
            ['Requirements', 'requirements'], ['Requirement status', 'requirement-status'],
            ['Company evaluation', 'company-evaluation'], ['My profile', 'profile']
        ]
    };

    const route = section + '/' + page;
    const info = pageInfo[route];
    if (!info) {
        window.location.assign(root + 'index.html');
        return;
    }

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function message(target, text, isError) {
        target.textContent = text;
        target.className = isError ? 'alert alert-error' : 'alert alert-success';
        target.hidden = !text;
    }

    async function request(url, options) {
        const settings = Object.assign({ credentials: 'same-origin', headers: { Accept: 'application/json' } }, options || {});
        const headers = new Headers(settings.headers);
        if (settings.method && settings.method !== 'GET') {
            headers.set('X-CSRF-Token', token);
        }
        settings.headers = headers;
        const response = await fetch(apiRoot + url, settings);
        const type = response.headers.get('content-type') || '';
        const result = type.includes('application/json') ? await response.json() : null;
        if (response.status === 401) {
            sessionStorage.removeItem('sasOjtCsrfToken');
            window.location.assign(root + 'index.html');
            throw new Error('Your session has ended. Please sign in again.');
        }
        if (!response.ok || !result || result.success !== true) {
            throw new Error(result && result.message ? result.message : 'The request could not be completed.');
        }
        return result;
    }

    function format(value) {
        if (value === null || value === undefined || value === '') return '—';
        if (typeof value === 'boolean' || value === 0 || value === 1) {
            if (value === 1 || value === true) return 'Yes';
            if (value === 0 || value === false) return 'No';
        }
        return String(value).replace(/_/g, ' ');
    }

    function renderTable(target, rows, actionType) {
        if (!Array.isArray(rows) || !rows.length) {
            target.appendChild(element('p', 'empty-state', 'No records to show yet.'));
            return;
        }
        const blocked = new Set(['password', 'file_path', 'ip_address', 'user_agent', 'middle_name']);
        const keys = Object.keys(rows[0]).filter(key => !blocked.has(key) && !key.endsWith('_id')).slice(0, 9);
        const wrapper = element('div', 'table-wrap');
        const table = element('table', 'data-table');
        const head = document.createElement('thead');
        const heading = document.createElement('tr');
        keys.forEach(key => heading.appendChild(element('th', '', key.replace(/_/g, ' '))));
        if (actionType) heading.appendChild(element('th', '', 'Actions'));
        head.appendChild(heading);
        table.appendChild(head);
        const bodyRows = document.createElement('tbody');
        rows.forEach(row => {
            const tr = document.createElement('tr');
            keys.forEach(key => {
                const td = document.createElement('td');
                td.textContent = format(row[key]);
                tr.appendChild(td);
            });
            if (actionType) {
                const td = document.createElement('td');
                const action = actionButtons(actionType, row);
                if (action) td.appendChild(action);
                tr.appendChild(td);
            }
            bodyRows.appendChild(tr);
        });
        table.appendChild(bodyRows);
        wrapper.appendChild(table);
        target.appendChild(wrapper);
    }

    function actionButtons(type, row) {
        const group = element('div', 'button-group');
        if (row.file_name && row.id) {
            const link = element('a', 'btn btn-small', 'View file');
            link.href = apiRoot + (type === 'student-download'
                ? api.requirements + '?action=download&id='
                : api.verification + '?action=download&id=') + encodeURIComponent(row.id);
            link.target = '_blank';
            link.rel = 'noopener';
            group.appendChild(link);
        }
        const actions = type === 'verification'
            ? [['Approve', 'approved'], ['Return', 'rejected']]
            : type === 'attendance'
                ? [['Approve', 'approved'], ['Reject', 'rejected']]
                : type === 'journal'
                    ? [['Approve', 'approved'], ['Return', 'rejected']]
                    : type === 'placement'
                        ? [['Approve', 'approved'], ['Start', 'ongoing'], ['Complete', 'completed']]
                        : type === 'user'
                            ? [['Activate', 'active'], ['Deactivate', 'inactive']]
                            : type === 'job'
                                ? [['Close', 'closed'], ['Reopen', 'active']]
                            : [];
        actions.forEach(([label, value]) => {
            const button = element('button', 'btn btn-small', label);
            button.type = 'button';
            button.addEventListener('click', async () => {
                button.disabled = true;
                try {
                    if (type === 'verification') {
                        await request(api.verification, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: row.id, status: value }) });
                    } else if (type === 'attendance') {
                        await request(api.attendance, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: row.id, supervisor_approval: value }) });
                    } else if (type === 'journal') {
                        await request(api.journal, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: row.id, status: value }) });
                    } else if (type === 'placement') {
                        await request(api.assignments, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'status', id: row.id, status: value }) });
                    } else if (type === 'user') {
                        await request(api.users, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'status', id: row.id, status: value }) });
                    } else if (type === 'job') {
                        await request(api.jobs, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'status', id: row.id, status: value }) });
                    }
                    window.location.reload();
                } catch (error) {
                    button.disabled = false;
                    window.alert(error.message);
                }
            });
            group.appendChild(button);
        });
        if (type === 'user' && row.id) {
            const reset = element('button', 'btn btn-small', 'Reset password');
            reset.type = 'button';
            reset.addEventListener('click', async () => {
                const password = window.prompt('Enter a new password with at least 10 characters.');
                if (password === null) return;
                if (password.length < 10) {
                    window.alert('Password must be at least 10 characters.');
                    return;
                }
                reset.disabled = true;
                try {
                    await request(api.users, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'reset-password', id: row.id, password: password })
                    });
                    window.alert('Password updated.');
                } catch (error) {
                    window.alert(error.message);
                } finally {
                    reset.disabled = false;
                }
            });
            group.appendChild(reset);
        }
        return group.childElementCount ? group : null;
    }

    function addMetricCards(target, data) {
        const grid = element('div', 'metric-grid');
        const labels = {
            students: 'Students', companies: 'Active companies', placements: 'Active placements',
            pending_requirements: 'Pending requirements', pending_users: 'Pending accounts',
            ongoing: 'Ongoing placements', pending_attendance: 'Pending attendance',
            hours_required: 'Required hours', hours_rendered: 'Approved hours',
            approved_requirements: 'Approved requirements', submitted_requirements: 'Submitted requirements'
        };
        Object.keys(data || {}).forEach(key => {
            if (!(key in labels) || data[key] === null) return;
            const card = element('article', 'metric-card');
            card.appendChild(element('span', 'metric-label', labels[key]));
            card.appendChild(element('strong', 'metric-value', format(data[key])));
            grid.appendChild(card);
        });
        if (grid.childElementCount) target.appendChild(grid);
    }

    function field(name, label, type, options) {
        return { name: name, label: label, type: type || 'text', options: options || null };
    }

    function addForm(target, title, fields, submitText, submitUrl, extra) {
        const details = element('details', 'form-panel');
        const summary = element('summary', '', title);
        details.appendChild(summary);
        const form = element('form', 'data-form');
        const notice = element('p', 'alert');
        notice.hidden = true;
        form.appendChild(notice);
        const grid = element('div', 'form-grid');
        fields.forEach(spec => {
            const group = element('div', 'form-group');
            const label = element('label', '', spec.label);
            label.htmlFor = 'field-' + spec.name;
            group.appendChild(label);
            let input;
            if (spec.type === 'select' || spec.options) {
                input = document.createElement('select');
                input.appendChild(new Option('Choose…', ''));
                (spec.options || []).forEach(option => input.appendChild(new Option(option.label, option.value)));
            } else if (spec.type === 'textarea') {
                input = document.createElement('textarea');
                input.rows = 3;
            } else {
                input = document.createElement('input');
                input.type = spec.type;
            }
            input.id = label.htmlFor;
            input.name = spec.name;
            if (spec.required) input.required = true;
            if (spec.minLength) input.minLength = spec.minLength;
            if (spec.maxLength) input.maxLength = spec.maxLength;
            if (spec.min !== undefined) input.min = spec.min;
            if (spec.max !== undefined) input.max = spec.max;
            if (spec.step) input.step = spec.step;
            group.appendChild(input);
            grid.appendChild(group);
        });
        form.appendChild(grid);
        const submit = element('button', 'btn btn-primary', submitText || 'Save');
        submit.type = 'submit';
        form.appendChild(submit);
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            submit.disabled = true;
            try {
                const formData = new FormData(form);
                let body;
                let headers = {};
                if (fields.some(spec => spec.type === 'file')) {
                    if (extra) Object.keys(extra).forEach(key => formData.append(key, extra[key]));
                    body = formData;
                } else {
                    const data = {};
                    formData.forEach((value, key) => { data[key] = value; });
                    if (extra) Object.assign(data, extra);
                    body = JSON.stringify(data);
                    headers['Content-Type'] = 'application/json';
                }
                const result = await request(submitUrl, {
                    method: 'POST',
                    headers: headers,
                    body: body
                });
                message(notice, result.message || 'Saved.', false);
                form.reset();
                if (result.data) renderTable(target, Array.isArray(result.data) ? result.data : [result.data]);
            } catch (error) {
                message(notice, error.message, true);
            } finally {
                submit.disabled = false;
            }
        });
        details.appendChild(form);
        target.appendChild(details);
        return form;
    }

    async function addOptionsForm(target, title, fields, optionsUrl, submitUrl, submitText) {
        const result = await request(optionsUrl);
        fields.forEach(spec => {
            const items = result[spec.optionsKey] || [];
            spec.options = items.map(item => ({
                value: item[spec.valueKey || 'id'],
                label: spec.labelKey
                    ? item[spec.labelKey]
                    : item.name || (item.student_number + ' · ' + item.name)
            }));
        });
        return addForm(target, title, fields, submitText, submitUrl);
    }

    function rowData(result) {
        if (Array.isArray(result.data)) return result.data;
        if (result.data && typeof result.data === 'object') return [result.data];
        return [];
    }

    async function renderPage(profile) {
        const title = document.getElementById('page-title');
        const description = document.getElementById('page-description');
        const content = document.getElementById('page-content');
        title.textContent = info[0];
        description.textContent = info[1];
        document.title = info[0] + ' · SAS OJT';

        const componentTargets = [
            ['app-sidebar', 'sidebar.html'],
            ['app-navbar', 'navbar.html'],
            ['app-footer', 'footer.html'],
            ['app-notifications', 'notifications.html']
        ];
        await Promise.all(componentTargets.map(async ([id, file]) => {
            const response = await fetch(root + 'components/' + file);
            if (!response.ok) throw new Error('Unable to load shared navigation.');
            document.getElementById(id).innerHTML = await response.text();
        }));

        const user = profile.user;
        document.querySelectorAll('[data-user-name]').forEach(node => { node.textContent = user.first_name + ' ' + user.last_name; });
        document.querySelectorAll('[data-user-role]').forEach(node => { node.textContent = format(user.role); });
        const logout = document.getElementById('logout-link');
        logout.href = apiRoot + 'authentication/logout.php';
        const links = document.getElementById('sidebar-links');
        const navRole = section === 'company' ? 'company' : section;
        (nav[navRole] || []).forEach(([label, slug]) => {
            const anchor = element('a', 'sidebar-link', label);
            anchor.href = slug === 'dashboard'
                ? slug + '.html'
                : slug + '.html';
            anchor.classList.toggle('active', slug === page);
            links.appendChild(anchor);
        });
        const mobileMenu = document.getElementById('menu-toggle');
        mobileMenu.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
        document.getElementById('app-sidebar').addEventListener('click', event => {
            if (event.target.closest('a')) document.body.classList.remove('sidebar-open');
        });

        const pageToolbar = document.getElementById('page-actions');
        if (route.endsWith('/reports') || route.endsWith('/compliance-reports')) {
            const exportLink = element('a', 'btn btn-secondary', 'Download CSV');
            exportLink.href = apiRoot + api.reports + '?format=csv';
            pageToolbar.appendChild(exportLink);
        }

        if (route === 'administrator/company-registration') {
            addForm(content, 'Company details', [
                Object.assign(field('name', 'Company name'), { required: true }),
                field('industry', 'Industry'),
                Object.assign(field('address', 'Business address', 'textarea'), { required: true }),
                field('contact_person', 'Contact person'),
                field('contact_email', 'Contact email', 'email'),
                field('contact_phone', 'Contact phone', 'tel'),
                field('website', 'Website', 'url')
            ], 'Register company', api.companies);
        }
        if (route === 'administrator/user-management' || route === 'administrator/company-accounts' ||
            route === 'administrator/faculty-accounts' || route === 'administrator/staff-accounts' ||
            route === 'administrator/role-management') {
            const roleOptions = [
                { label: 'Student', value: 'student' }, { label: 'Coordinator', value: 'coordinator' },
                { label: 'Company supervisor', value: 'supervisor' }, { label: 'SAS staff', value: 'sas_staff' },
                { label: 'Administrator', value: 'admin' }
            ];
            addForm(content, 'Create account', [
                Object.assign(field('first_name', 'First name'), { required: true }),
                Object.assign(field('last_name', 'Last name'), { required: true }),
                Object.assign(field('username', 'Username'), { required: true }),
                Object.assign(field('email', 'Email', 'email'), { required: true }),
                Object.assign(field('password', 'Temporary password', 'password'), { required: true, minLength: 10 }),
                Object.assign(field('role', 'Role', 'select', roleOptions), { required: true }),
                field('student_number', 'Student number'),
                field('course', 'Course / program'),
                field('year_level', 'Year level', 'number'),
                field('employee_id', 'Coordinator employee ID'),
                field('department', 'Department'),
                field('company_id', 'Company ID', 'number'),
                field('position', 'Supervisor position')
            ], 'Create account', api.users, { action: 'create' });
        }
        if (route === 'coordinator/company-assignment') {
            await addOptionsForm(content, 'Assign student to a company', [
                Object.assign(field('student_id', 'Student', 'select'), { optionsKey: 'students', required: true }),
                Object.assign(field('company_id', 'Company', 'select'), { optionsKey: 'companies', required: true }),
                Object.assign(field('supervisor_id', 'Company supervisor', 'select'), { optionsKey: 'supervisors', required: true }),
                field('start_date', 'Start date', 'date'),
                field('end_date', 'End date', 'date'),
                Object.assign(field('required_hours', 'Required hours', 'number'), { min: 1, max: 5000 })
            ], api.assignments + '?action=options', api.assignments, 'Create placement');
        }
        if (route === 'company/attendance-monitoring') {
            const options = await request(api.company + '?action=assigned');
            const students = options.data.map(item => ({ label: item.student_number + ' · ' + item.student_name, value: item.student_id }));
            addForm(content, 'Record attendance', [
                Object.assign(field('student_id', 'Student', 'select', students), { required: true }),
                Object.assign(field('date', 'Date', 'date'), { required: true }),
                Object.assign(field('time_in', 'Time in', 'time'), { required: true }),
                Object.assign(field('time_out', 'Time out', 'time'), { required: true }),
                field('remarks', 'Remarks', 'textarea')
            ], 'Save attendance', api.attendance);
        }
        if (route === 'company/job-descriptions') {
            addForm(content, 'Publish a job description', [
                Object.assign(field('title', 'Job title'), { required: true }),
                Object.assign(field('description', 'Description', 'textarea'), { required: true }),
                field('qualifications', 'Qualifications', 'textarea'),
                field('location', 'Location')
            ], 'Publish job', api.jobs);
        }
        if (route === 'company/company-profile') {
            const result = await request(api.company + '?action=profile');
            const form = addForm(content, 'Company details', [
                Object.assign(field('name', 'Company name'), { required: true }),
                field('industry', 'Industry'),
                Object.assign(field('address', 'Address', 'textarea'), { required: true }),
                field('contact_person', 'Contact person'),
                field('contact_email', 'Contact email', 'email'),
                field('contact_phone', 'Phone', 'tel'),
                field('website', 'Website', 'url')
            ], 'Save profile', api.company + '?action=profile');
            Object.keys(result.data || {}).forEach(key => {
                const input = form.elements.namedItem(key);
                if (input) input.value = result.data[key] || '';
            });
        }
        if (route === 'student/requirements' || route === 'student/ptr' || route === 'student/requirement-status') {
            addForm(content, 'Upload a requirement', [
                Object.assign(field('requirement_type_id', 'Requirement', 'select'), { optionsKey: 'requirements', required: true }),
                Object.assign(field('file', 'Document (PDF, image, DOC/DOCX; max 5 MB)', 'file'), { required: true })
            ], 'Upload document', api.requirements);
            const result = await request(api.requirements);
            const select = content.querySelector('[name="requirement_type_id"]');
            result.data.forEach(item => select.appendChild(new Option(item.title, item.requirement_type_id)));
        }
        if (route === 'student/daily-journal') {
            addForm(content, 'Add a daily journal entry', [
                Object.assign(field('journal_date', 'Date', 'date'), { required: true }),
                Object.assign(field('title', 'Entry title'), { required: true }),
                Object.assign(field('activities', 'Activities', 'textarea'), { required: true }),
                field('learnings', 'Learning and reflections', 'textarea'),
                Object.assign(field('hours', 'Hours', 'number'), { min: 0, max: 24, step: '0.25', required: true })
            ], 'Submit journal', api.journal);
        }
        if (route === 'student/company-evaluation') {
            addForm(content, 'Rate your OJT experience', [
                ...['performance_rating', 'communication_rating', 'punctuality_rating', 'work_quality_rating'].map(name =>
                    Object.assign(field(name, name.replace(/_rating/g, '').replace(/_/g, ' '), 'number'), { min: 1, max: 5, required: true })),
                field('comments', 'Comments', 'textarea')
            ], 'Submit feedback', api.evaluation);
        }
        if (route === 'company/student-evaluation' || route === 'company/employer-feedback') {
            await addOptionsForm(content, 'Evaluate a student', [
                Object.assign(field('placement_id', 'Student placement', 'select'), {
                    optionsKey: 'data',
                    valueKey: 'placement_id',
                    labelKey: 'student_number',
                    required: true
                }),
                Object.assign(field('performance_rating', 'Performance (1–5)', 'number'), { min: 1, max: 5, required: true }),
                Object.assign(field('communication_rating', 'Communication (1–5)', 'number'), { min: 1, max: 5, required: true }),
                Object.assign(field('punctuality_rating', 'Punctuality (1–5)', 'number'), { min: 1, max: 5, required: true }),
                Object.assign(field('work_quality_rating', 'Work quality (1–5)', 'number'), { min: 1, max: 5, required: true }),
                field('period', 'Evaluation period'),
                field('comments', 'Comments', 'textarea')
            ], api.feedback + '?action=options', api.feedback, 'Submit evaluation');
        }
        if (route === 'administrator/system-settings') {
            addForm(content, 'Update a setting', [
                Object.assign(field('key', 'Setting key'), { required: true }),
                Object.assign(field('value', 'Setting value', 'textarea'), { required: true })
            ], 'Save setting', 'administrator/system-controller.php?action=save-setting');
        }
        if (route === 'administrator/system-settings') {
            const result = await request(api.settings);
            renderTable(content, result.data);
        } else {
            const url = info[2];
            const result = await request(url);
            let rows = rowData(result);
            if (result.data && !Array.isArray(result.data) &&
                (route.endsWith('/dashboard') || route === 'student/ojt-hours')) {
                addMetricCards(content, result.data);
            }
            if (result.summary) addMetricCards(content, result.summary);
            if (route === 'administrator/company-accounts') rows = rows.filter(row => ['company', 'supervisor'].includes(row.role));
            if (route === 'administrator/faculty-accounts') rows = rows.filter(row => row.role === 'coordinator');
            if (route === 'administrator/staff-accounts') rows = rows.filter(row => ['admin', 'sas_staff'].includes(row.role));
            if (route.startsWith('sas-staff/') && route.includes('verification')) {
                rows = rows.filter(row => route.includes('moa') ? /moa|agreement/i.test(row.title)
                    : route.includes('waiver') ? /waiver|consent/i.test(row.title)
                        : route.includes('insurance') ? /insurance/i.test(row.title) : true);
            }
            const actionType = route.startsWith('sas-staff/') && (route.includes('verification') || route.includes('submitted-requirements')) ? 'verification'
                : route.startsWith('student/') && ['requirements', 'ptr', 'requirement-status'].includes(page) ? 'student-download'
                    : route === 'company/job-descriptions' ? 'job'
                        : route.includes('deployment-records') ? 'placement'
                            : route.startsWith('administrator/') && route !== 'administrator/student-accounts' &&
                                (route.includes('user-management') || route.includes('accounts') || route.includes('role-management')) ? 'user' : null;
            renderTable(content, rows, actionType);
        }

        if (route === 'administrator/system-monitoring') {
            const result = await request(api.activity);
            renderTable(content, result.data);
        }
        if (route === 'student/profile') {
            const result =             await request('authentication/profile.php');
            addForm(content, 'Update contact details', [
                Object.assign(field('first_name', 'First name'), { required: true }),
                Object.assign(field('last_name', 'Last name'), { required: true }),
                Object.assign(field('email', 'Email', 'email'), { required: true }),
                field('contact_number', 'Contact number', 'tel')
            ], 'Save profile', api.student);
            const form = content.querySelector('.data-form');
            Object.keys(result.user || {}).forEach(key => {
                if (form.elements.namedItem(key)) form.elements.namedItem(key).value = result.user[key] || '';
            });
        }
        if (route === 'coordinator/ojt-monitoring') {
            const result = await request(api.monitoring + '?action=attendance');
            const sectionTitle = element('h2', '', 'Attendance awaiting review');
            content.appendChild(sectionTitle);
            renderTable(content, result.data, 'attendance');
        }
        if (route === 'company/assigned-students') {
            const result = await request(api.journal);
            content.appendChild(element('h2', '', 'Student journal entries'));
            renderTable(content, result.data, 'journal');
        }
        const notificationButton = document.getElementById('notification-button');
        notificationButton.addEventListener('click', async () => {
            const panel = document.getElementById('notification-list');
            panel.hidden = !panel.hidden;
            if (!panel.hidden) {
                try {
                    const result = await request(api.notifications);
                    panel.replaceChildren();
                    result.data.forEach(item => {
                        const entry = element('button', Number(item.is_read) === 0 ? 'notification-entry unread' : 'notification-entry', item.title + ': ' + item.message);
                        entry.type = 'button';
                        entry.addEventListener('click', async () => {
                            if (Number(item.is_read) === 0) {
                                await request(api.notifications, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ action: 'read', id: item.id })
                                });
                                item.is_read = 1;
                                entry.classList.remove('unread');
                                if (window.SASOjtNotifications) window.SASOjtNotifications.refreshBadge();
                            }
                            if (item.link) {
                                const target = new URL(item.link, window.location.href);
                                if (target.origin === window.location.origin) window.location.assign(target.href);
                            }
                        });
                        panel.appendChild(entry);
                    });
                    if (!result.data.length) panel.appendChild(element('p', '', 'No notifications.'));
                } catch (error) {
                    panel.replaceChildren(element('p', 'form-error', error.message));
                }
            }
        });
        if (window.SASOjtNotifications) window.SASOjtNotifications.refreshBadge();
        if (window.SASOjtCharts) window.SASOjtCharts.decorate(content);
    }

    async function start() {
        try {
            const profile = await request('authentication/profile.php');
            const roleSection = {
                admin: 'administrator', sas_staff: 'sas-staff', coordinator: 'coordinator',
                company: 'company', supervisor: 'company', student: 'student'
            }[profile.user.role];
            if (roleSection !== section) {
                window.location.assign(root + 'dashboards/' + roleSection + '/dashboard.html');
                return;
            }
            await renderPage(profile);
        } catch (error) {
            const target = document.getElementById('page-error');
            if (target) message(target, error.message, true);
        }
    }

    start();
}());

/* globals CSRF_TOKEN, bootstrap */
'use strict';

// ── State ─────────────────────────────────────────────────────────────────────
const state = {
    bookmarks: [],
    tags: {},
    search: '',
    activeTag: '',
    editingId: null,
};

// ── DOM refs ──────────────────────────────────────────────────────────────────
const searchInput     = document.getElementById('searchInput');
const clearSearch     = document.getElementById('clearSearch');
const bookmarkGrid    = document.getElementById('bookmarkGrid');
const emptyState      = document.getElementById('emptyState');
const noResults       = document.getElementById('noResults');
const tagList         = document.getElementById('tagList');
const statusBar       = document.getElementById('statusBar');
const activeFilters   = document.getElementById('activeFilters');
const addBtn          = document.getElementById('addBtn');
const bmModalEl       = document.getElementById('bmModal');
const bmModal         = new bootstrap.Modal(bmModalEl);
const bmForm          = document.getElementById('bmForm');
const bmId            = document.getElementById('bmId');
const bmUrl           = document.getElementById('bmUrl');
const bmTitle         = document.getElementById('bmTitle');
const bmDesc          = document.getElementById('bmDesc');
const bmTags          = document.getElementById('bmTags');
const bmSaveBtn       = document.getElementById('bmSaveBtn');
const bmFormError     = document.getElementById('bmFormError');
const fetchTitleBtn   = document.getElementById('fetchTitleBtn');
const deleteModalEl   = document.getElementById('deleteModal');
const deleteModal     = new bootstrap.Modal(deleteModalEl);
const deleteTitle     = document.getElementById('deleteTitle');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const toastEl         = document.getElementById('toast');
const toastMsg        = document.getElementById('toastMsg');
const toastBS         = new bootstrap.Toast(toastEl, { delay: 3000 });

// ── API helpers ───────────────────────────────────────────────────────────────
async function apiFetch(action, opts = {}) {
    const url = `api.php?action=${action}`;
    const res  = await fetch(url, {
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        ...opts,
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Unknown error');
    return json.data;
}

async function apiGet(action, params = {}) {
    const qs  = new URLSearchParams({ action, ...params }).toString();
    const res = await fetch(`api.php?${qs}`);
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Unknown error');
    return json.data;
}

async function apiPost(action, data = {}) {
    return apiFetch(action, {
        method: 'POST',
        body: JSON.stringify({ ...data, csrf_token: CSRF_TOKEN }),
    });
}

// ── Toast ─────────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    toastMsg.textContent = msg;
    toastEl.className = `toast align-items-center border-0 text-white bg-${type}`;
    toastBS.show();
}

// ── Data loading ──────────────────────────────────────────────────────────────
async function loadData() {
    const [bookmarks, tags] = await Promise.all([
        apiGet('list', buildQueryParams()),
        apiGet('tags'),
    ]);
    state.bookmarks = bookmarks;
    state.tags      = tags;
    renderTags();
    renderBookmarks();
}

async function refreshBookmarks() {
    state.bookmarks = await apiGet('list', buildQueryParams());
    renderBookmarks();
}

async function refreshTags() {
    state.tags = await apiGet('tags');
    renderTags();
}

function buildQueryParams() {
    const p = {};
    if (state.search)    p.q   = state.search;
    if (state.activeTag) p.tag = state.activeTag;
    return p;
}

// ── Render tags ───────────────────────────────────────────────────────────────
function renderTags() {
    const tags = state.tags;
    if (Object.keys(tags).length === 0) {
        tagList.innerHTML = '<div class="text-muted small">No tags yet.</div>';
        return;
    }
    const total = Object.values(tags).reduce((a, b) => a + b, 0);
    let html = `<button class="bm-tag-btn ${state.activeTag === '' ? 'active' : ''}" data-tag="">
        <span><i class="bi bi-bookmark-fill me-1 opacity-50"></i>All</span>
        <span class="badge rounded-pill">${total}</span>
    </button>`;
    for (const [tag, count] of Object.entries(tags)) {
        const active = state.activeTag === tag ? 'active' : '';
        html += `<button class="bm-tag-btn ${active}" data-tag="${escHtml(tag)}">
            <span><i class="bi bi-tag me-1 opacity-50"></i>${escHtml(tag)}</span>
            <span class="badge rounded-pill">${count}</span>
        </button>`;
    }
    tagList.innerHTML = html;

    tagList.querySelectorAll('.bm-tag-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            state.activeTag = btn.dataset.tag;
            refreshAll();
        });
    });
}

// ── Render bookmarks ──────────────────────────────────────────────────────────
function renderBookmarks() {
    const bms = state.bookmarks;

    updateStatus(bms.length);
    renderActiveFilters();

    if (bms.length === 0) {
        bookmarkGrid.innerHTML = '';
        if (!state.search && !state.activeTag) {
            emptyState.classList.remove('d-none');
            noResults.classList.add('d-none');
        } else {
            noResults.classList.remove('d-none');
            emptyState.classList.add('d-none');
        }
        return;
    }

    emptyState.classList.add('d-none');
    noResults.classList.add('d-none');

    bookmarkGrid.innerHTML = bms.map(renderCard).join('');

    bookmarkGrid.querySelectorAll('[data-edit]').forEach(btn => {
        btn.addEventListener('click', () => openEditModal(btn.dataset.edit));
    });
    bookmarkGrid.querySelectorAll('[data-delete]').forEach(btn => {
        btn.addEventListener('click', () => openDeleteModal(btn.dataset.delete, btn.dataset.title));
    });
    bookmarkGrid.querySelectorAll('.bm-tag-chip').forEach(chip => {
        chip.addEventListener('click', e => {
            e.preventDefault();
            state.activeTag = chip.dataset.tag;
            refreshAll();
        });
    });
}

function renderCard(bm) {
    const favicon = `https://www.google.com/s2/favicons?domain=${encodeURIComponent(new URL(bm.url).hostname)}&sz=32`;
    const tags = (bm.tags || []).map(t =>
        `<a href="#" class="bm-tag-chip me-1 mb-1" data-tag="${escHtml(t)}">${escHtml(t)}</a>`
    ).join('');
    const date = bm.created_at ? formatDate(bm.created_at) : '';

    return `<div class="col">
      <div class="bm-card">
        <div class="bm-card-body">
          <div class="d-flex align-items-start gap-2 mb-1">
            <img class="bm-favicon mt-1" src="${escHtml(favicon)}" alt="" loading="lazy"
                 onerror="this.style.display='none'">
            <div class="flex-grow-1 overflow-hidden">
              <a href="${escHtml(bm.url)}" target="_blank" rel="noopener noreferrer"
                 class="bm-card-title" title="${escHtml(bm.title)}">${escHtml(bm.title)}</a>
              <span class="bm-card-url">${escHtml(bm.url)}</span>
            </div>
          </div>
          ${bm.description ? `<p class="bm-card-desc">${escHtml(bm.description)}</p>` : ''}
          ${tags ? `<div class="mt-2">${tags}</div>` : ''}
        </div>
        <div class="bm-card-footer">
          <span class="bm-date" title="${escHtml(bm.created_at || '')}">${date}</span>
          <div class="bm-card-actions">
            <button class="bm-btn-icon" data-edit="${escHtml(bm.id)}" title="Edit">
              <i class="bi bi-pencil-fill"></i>
            </button>
            <button class="bm-btn-icon danger" data-delete="${escHtml(bm.id)}"
                    data-title="${escHtml(bm.title)}" title="Delete">
              <i class="bi bi-trash-fill"></i>
            </button>
          </div>
        </div>
      </div>
    </div>`;
}

// ── Status / filter display ───────────────────────────────────────────────────
function updateStatus(count) {
    statusBar.textContent = count === 1 ? '1 bookmark' : `${count} bookmarks`;
}

function renderActiveFilters() {
    let html = '';
    if (state.activeTag) {
        html += `<button class="bm-filter-badge" id="clearTagFilter">
            <i class="bi bi-tag-fill"></i>${escHtml(state.activeTag)}
            <i class="bi bi-x"></i>
        </button>`;
    }
    activeFilters.innerHTML = html;
    const clearTagBtn = document.getElementById('clearTagFilter');
    if (clearTagBtn) {
        clearTagBtn.addEventListener('click', () => {
            state.activeTag = '';
            refreshAll();
        });
    }
}

// ── Add / Edit modal ──────────────────────────────────────────────────────────
function openAddModal() {
    state.editingId = null;
    bmForm.classList.remove('was-validated');
    bmId.value    = '';
    bmUrl.value   = '';
    bmTitle.value = '';
    bmDesc.value  = '';
    bmTags.value  = '';
    hideBmError();
    document.getElementById('bmModalLabel').textContent = 'Add Bookmark';
    bmModal.show();
    setTimeout(() => bmUrl.focus(), 300);
}

function openEditModal(id) {
    const bm = state.bookmarks.find(b => b.id === id);
    if (!bm) return;
    state.editingId = id;
    bmForm.classList.remove('was-validated');
    bmId.value    = bm.id;
    bmUrl.value   = bm.url;
    bmTitle.value = bm.title;
    bmDesc.value  = bm.description || '';
    bmTags.value  = (bm.tags || []).join(', ');
    hideBmError();
    document.getElementById('bmModalLabel').textContent = 'Edit Bookmark';
    bmModal.show();
}

async function saveBookmark() {
    bmForm.classList.add('was-validated');
    if (!bmForm.checkValidity()) return;

    const data = {
        id:          bmId.value,
        title:       bmTitle.value.trim(),
        url:         bmUrl.value.trim(),
        description: bmDesc.value.trim(),
        tags:        bmTags.value.split(',').map(t => t.trim()).filter(Boolean),
    };

    if (!data.title) { showBmError('Title is required.'); return; }
    if (!data.url)   { showBmError('URL is required.'); return; }

    bmSaveBtn.disabled = true;
    try {
        const action = state.editingId ? 'update' : 'add';
        await apiPost(action, data);
        bmModal.hide();
        showToast(state.editingId ? 'Bookmark updated.' : 'Bookmark added.');
        await Promise.all([refreshBookmarks(), refreshTags()]);
    } catch (err) {
        showBmError(err.message);
    } finally {
        bmSaveBtn.disabled = false;
    }
}

function showBmError(msg) {
    bmFormError.textContent = msg;
    bmFormError.classList.remove('d-none');
}

function hideBmError() {
    bmFormError.textContent = '';
    bmFormError.classList.add('d-none');
}

// ── Delete modal ──────────────────────────────────────────────────────────────
let pendingDeleteId = null;

function openDeleteModal(id, title) {
    pendingDeleteId = id;
    deleteTitle.textContent = title;
    deleteModal.show();
}

async function deleteBookmark() {
    if (!pendingDeleteId) return;
    confirmDeleteBtn.disabled = true;
    try {
        await apiPost('delete', { id: pendingDeleteId });
        deleteModal.hide();
        showToast('Bookmark deleted.', 'danger');
        await Promise.all([refreshBookmarks(), refreshTags()]);
    } catch (err) {
        showToast(err.message, 'danger');
    } finally {
        confirmDeleteBtn.disabled = false;
        pendingDeleteId = null;
    }
}

// ── Fetch title from URL ──────────────────────────────────────────────────────
fetchTitleBtn.addEventListener('click', async () => {
    const url = bmUrl.value.trim();
    if (!url) { bmUrl.focus(); return; }
    fetchTitleBtn.disabled = true;
    fetchTitleBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Fetching…';
    try {
        const data = await apiPost('fetch_title', { url });
        if (data && data.title) {
            bmTitle.value = data.title;
        } else {
            showToast('Could not fetch title.', 'danger');
        }
    } catch {
        showToast('Could not fetch title.', 'danger');
    } finally {
        fetchTitleBtn.disabled = false;
        fetchTitleBtn.innerHTML = '<i class="bi bi-magic me-1"></i>Fetch title';
    }
});

// ── Search ────────────────────────────────────────────────────────────────────
let searchTimer = null;

searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        state.search = searchInput.value.trim();
        refreshAll();
    }, 300);
});

clearSearch.addEventListener('click', () => {
    searchInput.value = '';
    state.search = '';
    refreshAll();
});

// ── Misc event listeners ──────────────────────────────────────────────────────
addBtn.addEventListener('click', openAddModal);
bmSaveBtn.addEventListener('click', saveBookmark);
confirmDeleteBtn.addEventListener('click', deleteBookmark);

bmModalEl.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        saveBookmark();
    }
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#x27;');
}

function formatDate(dateStr) {
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

async function refreshAll() {
    await loadData();
}

// ── Init ──────────────────────────────────────────────────────────────────────
loadData();

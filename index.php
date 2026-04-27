<?php
// ─── Main Page ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/auth.php';
require_login();

$csrf = csrf_token();
?><!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookmarks</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔖</text></svg>">
    <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bm-bg">

<!-- ── Navbar ────────────────────────────────────────────────────────────── -->
<nav class="navbar navbar-dark bm-navbar sticky-top shadow-sm">
    <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand fw-bold me-3" href="index.php">
            🔖 <span class="d-none d-sm-inline">Bookmarks</span>
        </a>

        <!-- Search -->
        <div class="flex-grow-1 me-3" style="max-width: 480px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bm-search-icon"><i class="bi bi-search"></i></span>
                <input type="search" id="searchInput" class="form-control bm-search"
                       placeholder="Search bookmarks…" autocomplete="off">
                <button class="btn btn-outline-secondary" id="clearSearch" title="Clear search" type="button">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-primary btn-sm" id="addBtn" title="Add bookmark">
                <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Add</span>
            </button>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm" title="Logout">
                <i class="bi bi-box-arrow-right"></i><span class="d-none d-md-inline ms-1">Logout</span>
            </a>
        </div>
    </div>
</nav>

<!-- ── Layout ────────────────────────────────────────────────────────────── -->
<div class="container-fluid px-3 px-md-4 py-3">
    <div class="row g-3">

        <!-- ── Sidebar ─────────────────────────────────────────────────────── -->
        <div class="col-12 col-lg-2">
            <div class="bm-sidebar p-3 rounded">
                <h6 class="text-uppercase text-muted small fw-semibold mb-3 letter-spacing-1">
                    <i class="bi bi-tags-fill me-1"></i>Tags
                </h6>
                <div id="tagList">
                    <div class="text-muted small">Loading…</div>
                </div>
            </div>
        </div>

        <!-- ── Main content ────────────────────────────────────────────────── -->
        <div class="col-12 col-lg-10">
            <!-- Status bar -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div id="statusBar" class="text-muted small"></div>
                <div id="activeFilters" class="d-flex gap-2 flex-wrap"></div>
            </div>

            <!-- Bookmark grid -->
            <div id="bookmarkGrid" class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                <!-- cards injected by JS -->
            </div>

            <!-- Empty state -->
            <div id="emptyState" class="text-center py-5 d-none">
                <div class="display-1 mb-3">🔖</div>
                <h4 class="text-muted">No bookmarks yet</h4>
                <p class="text-muted">Click <strong>Add</strong> to save your first bookmark.</p>
            </div>

            <!-- No results -->
            <div id="noResults" class="text-center py-5 d-none">
                <div class="display-1 mb-3">🔍</div>
                <h4 class="text-muted">No results found</h4>
                <p class="text-muted">Try a different search or tag filter.</p>
            </div>
        </div>
    </div>
</div>

<!-- ── Add / Edit Modal ───────────────────────────────────────────────────── -->
<div class="modal fade" id="bmModal" tabindex="-1" aria-labelledby="bmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bm-modal">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title" id="bmModalLabel">Add Bookmark</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bmForm" novalidate>
                    <input type="hidden" id="bmId">
                    <div class="mb-3">
                        <label for="bmUrl" class="form-label">URL <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                            <input type="url" class="form-control" id="bmUrl" placeholder="https://example.com" required>
                        </div>
                        <div class="invalid-feedback">Please enter a valid URL.</div>
                    </div>
                    <div class="mb-3">
                        <label for="bmTitle" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="bmTitle" placeholder="Page title" required>
                        <div class="invalid-feedback">Title is required.</div>
                        <div class="d-flex justify-content-end mt-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchTitleBtn">
                                <i class="bi bi-magic me-1"></i>Fetch title
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="bmDesc" class="form-label">Description</label>
                        <textarea class="form-control" id="bmDesc" rows="3"
                                  placeholder="Optional notes about this bookmark"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="bmTags" class="form-label">Tags</label>
                        <input type="text" class="form-control" id="bmTags"
                               placeholder="php, tools, reference  (comma-separated)">
                        <div class="form-text">Separate tags with commas.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top border-secondary">
                <div id="bmFormError" class="text-danger small me-auto d-none"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="bmSaveBtn">
                    <i class="bi bi-floppy-fill me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Delete Confirm Modal ───────────────────────────────────────────────── -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content bm-modal">
            <div class="modal-body text-center py-4">
                <div class="fs-1 mb-3">🗑️</div>
                <h5 class="mb-1">Delete bookmark?</h5>
                <p class="text-muted small mb-4" id="deleteTitle"></p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">
                        <i class="bi bi-trash-fill me-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Toast ──────────────────────────────────────────────────────────────── -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
    <div id="toast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>const CSRF_TOKEN = <?= json_encode($csrf) ?>;</script>
<script src="assets/vendor/bootstrap.bundle.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>

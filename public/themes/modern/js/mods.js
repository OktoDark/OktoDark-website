// =====================================================
//  MODS PAGE — FULL ES5 JAVASCRIPT
//  Includes:
//  - AJAX modal loader
//  - Banner dropzone + preview
//  - Gallery dropzone + preview
//  - Collection fields (tags + compatible)
//  - Gallery delete
//  - Gallery reorder (SortableJS)
//  - AJAX form submit
//  - Toast notifications
//  - Card refresh
//  - Category manager handlers
// =====================================================


// =====================================================
//  UTILITY FUNCTIONS
// =====================================================
function findParent(el, cls) {
    while (el && el !== document) {
        if ((' ' + el.className + ' ').indexOf(' ' + cls + ' ') > -1) return el;
        el = el.parentNode;
    }
    return null;
}


// =====================================================
//  INIT FORM (called after modal loads)
// =====================================================
function initModForm() {

    // Get the form
    var form = document.getElementById('ajax-form');
    if (!form) return;

    // Force IMAGES tab active BEFORE FormData is created
    form.addEventListener('submit', function () {
        var imagesTab = document.getElementById('images-tab');
        if (imagesTab) imagesTab.click();
    });

    // Initialize dynamic collections, gallery events, and AJAX submit handler
    initCollection('tags-wrapper', 'add-tag');
    initCollection('compatible-wrapper', 'add-compatible');
    initGalleryDelete();
    initGallerySortable();
    initAjaxFormSubmit();

    // -----------------------------
    // BANNER
    // -----------------------------
    var bannerInput   = document.getElementById('mods_bannerFile');
    var bannerDrop    = document.getElementById('banner-dropzone');
    var bannerPreview = document.getElementById('banner-preview');

    if (bannerDrop && bannerInput && bannerPreview) {

        bannerDrop.addEventListener('click', function () {
            bannerInput.click();
        });

        bannerInput.addEventListener('change', function () {
            if (!bannerInput.files || !bannerInput.files[0]) return;

            var reader = new FileReader();
            reader.onload = function (e) {
                bannerPreview.src = e.target.result;
            };
            reader.readAsDataURL(bannerInput.files[0]);
        });

        bannerDrop.addEventListener('dragover', function (e) {
            e.preventDefault();
            bannerDrop.classList.add('border-primary');
        });

        bannerDrop.addEventListener('dragleave', function () {
            bannerDrop.classList.remove('border-primary');
        });

        bannerDrop.addEventListener('drop', function (e) {
            e.preventDefault();
            bannerDrop.classList.remove('border-primary');

            if (!e.dataTransfer.files.length) return;

            bannerInput.files = e.dataTransfer.files;

            var reader = new FileReader();
            reader.onload = function (ev) {
                bannerPreview.src = ev.target.result;
            };
            reader.readAsDataURL(e.dataTransfer.files[0]);
        });
    }


    // -----------------------------
    // GALLERY
    // -----------------------------
    var galleryInput = document.getElementById('mods_galleryFiles');
    var galleryDrop  = document.getElementById('gallery-dropzone');
    var galleryWrap  = document.getElementById('gallery-preview');

    if (galleryDrop && galleryInput && galleryWrap) {

        galleryDrop.addEventListener('click', function () {
            galleryInput.click();
        });

        galleryInput.addEventListener('change', function () {
            if (!galleryInput.files.length) return;

            var i;
            for (i = 0; i < galleryInput.files.length; i++) {
                (function (file) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        var col = document.createElement('div');
                        col.className = 'col-4 gallery-item new-upload';
                        col.innerHTML =
                            '<div class="position-relative">' +
                            '<img src="' + e.target.result + '" class="img-fluid rounded" alt="Gallery Image">' +
                            '</div>';
                        galleryWrap.appendChild(col);
                    };
                    reader.readAsDataURL(file);
                })(galleryInput.files[i]);
            }
        });

        galleryDrop.addEventListener('dragover', function (e) {
            e.preventDefault();
            galleryDrop.classList.add('border-primary');
        });

        galleryDrop.addEventListener('dragleave', function () {
            galleryDrop.classList.remove('border-primary');
        });

        galleryDrop.addEventListener('drop', function (e) {
            e.preventDefault();
            galleryDrop.classList.remove('border-primary');

            if (!e.dataTransfer.files.length) return;

            galleryInput.files = e.dataTransfer.files;

            var j;
            for (j = 0; j < e.dataTransfer.files.length; j++) {
                (function (file) {
                    var reader = new FileReader();
                    reader.onload = function (ev) {
                        var col = document.createElement('div');
                        col.className = 'col-4 gallery-item new-upload';
                        col.innerHTML =
                            '<div class="position-relative">' +
                            '<img src="' + ev.target.result + '" class="img-fluid rounded" alt="Gallery Image">' +
                            '</div>';
                        galleryWrap.appendChild(col);
                    };
                    reader.readAsDataURL(file);
                })(e.dataTransfer.files[j]);
            }
        });
    }
}

// =====================================================
//  COLLECTION FIELDS
// =====================================================
function initCollection(wrapperId, addButtonId) {
    var wrapper = document.getElementById(wrapperId);
    var addBtn = document.getElementById(addButtonId);

    if (!wrapper || !addBtn) return;

    addBtn.addEventListener('click', function () {
        var prototype = wrapper.getAttribute('data-prototype');
        var index = wrapper.children.length;

        var newField = prototype.replace(/__name__/g, index);

        var div = document.createElement('div');
        div.className = 'input-group mb-2';
        div.innerHTML = newField +
            '<button type="button" class="btn btn-danger remove-item">×</button>';

        wrapper.appendChild(div);
    });

    wrapper.addEventListener('click', function (e) {
        var target = e.target || e.srcElement;
        if (target.classList.contains('remove-item')) {
            if (target.parentNode && target.parentNode.parentNode) {
                target.parentNode.parentNode.removeChild(target.parentNode);
            }
        }
    });
}



// =====================================================
//  GALLERY DELETE
// =====================================================
function initGalleryDelete() {
    var container = document.getElementById('gallery-preview');
    if (!container) return;

    var modsCards = document.getElementById('mods-cards');
    if (!modsCards) return;

    // Helper function for ES5 compatibility
    function findParentByClass(el, cls) {
        while (el && el !== document) {
            if ((' ' + el.className + ' ').indexOf(' ' + cls + ' ') > -1) return el;
            el = el.parentNode;
        }
        return null;
    }

    var galleryDeleteUrl = modsCards.getAttribute('data-gallery-delete-url');

    container.addEventListener('click', function (e) {
        var target = e.target || e.srcElement;
        if (!target.classList.contains('delete-gallery')) return;

        var btn = target;
        var path = btn.getAttribute('data-path');
        var filename = btn.getAttribute('data-filename');
        var form = document.getElementById('ajax-form');
        var modId = form ? form.getAttribute('data-mod-id') : null;

        if (!confirm('Are you sure you want to delete this gallery image?')) return;

        fetch(galleryDeleteUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path: path, modId: modId, filename: filename })
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    var galleryItem = findParentByClass(btn, 'gallery-item');
                    if (galleryItem && galleryItem.parentNode) {
                        galleryItem.parentNode.removeChild(galleryItem);
                    }
                } else {
                    alert(data.message || 'Failed to delete gallery image.');
                }
            })
            .catch(function () {
                alert('An error occurred while deleting the gallery image.');
            });
    });
}



// =====================================================
//  GALLERY SORTABLE
// =====================================================
function initGallerySortable() {
    var container = document.getElementById('gallery-preview');
    if (!container || typeof Sortable === 'undefined') return;

    var modsCards = document.getElementById('mods-cards');
    if (!modsCards) return;

    var galleryReorderUrl = modsCards.getAttribute('data-gallery-reorder-url');

    Sortable.create(container, {
        animation: 150,
        onEnd: function () {
            var items = container.querySelectorAll('.gallery-item');
            var order = [];
            var i;

            for (i = 0; i < items.length; i++) {
                var filename = items[i].getAttribute('data-filename');
                if (filename) order.push(filename);
            }

            var form = document.getElementById('ajax-form');
            if (!form) return;
            var modId = form.getAttribute('data-mod-id');
            if (!modId) return; // Prevent order updates if the mod hasn't been saved yet (creation mode)

            fetch(galleryReorderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modId: modId, order: order })
            });
        }
    });
}



// =====================================================
//  AJAX FORM SUBMIT
// =====================================================
function displayFormErrors(errors) {
    var form = document.getElementById('ajax-form');
    if (!form) return;

    // Clear previous errors
    var invalidInputs = form.querySelectorAll('.is-invalid');
    var i;
    for (i = 0; i < invalidInputs.length; i++) {
        invalidInputs[i].classList.remove('is-invalid');
    }

    var feedbackEls = form.querySelectorAll('.invalid-feedback');
    for (i = 0; i < feedbackEls.length; i++) {
        if (feedbackEls[i] && feedbackEls[i].parentNode) {
            feedbackEls[i].parentNode.removeChild(feedbackEls[i]);
        }
    }

    var globalAlerts = form.querySelectorAll('.alert-danger-global');
    for (i = 0; i < globalAlerts.length; i++) {
        if (globalAlerts[i] && globalAlerts[i].parentNode) {
            globalAlerts[i].parentNode.removeChild(globalAlerts[i]);
        }
    }

    // Show global errors
    if (errors._global && errors._global.length > 0) {
        var alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-danger-global mb-3';
        alertDiv.innerHTML = errors._global.join('<br>');
        form.insertBefore(alertDiv, form.firstChild);
    }

    // Show field errors
    if (errors.fields) {
        for (var path in errors.fields) {
            if (errors.fields.hasOwnProperty(path)) {
                var elementId = 'mods_' + path;
                var input = document.getElementById(elementId);

                if (input) {
                    input.classList.add('is-invalid');
                    var messages = errors.fields[path];

                    var feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback d-block';
                    feedback.innerHTML = messages.join('<br>');

                    if (input.parentNode.classList.contains('input-group')) {
                        input.parentNode.parentNode.appendChild(feedback);
                    } else {
                        input.parentNode.appendChild(feedback);
                    }
                } else {
                    // Fallback to global error
                    var fallbackAlert = document.createElement('div');
                    fallbackAlert.className = 'alert alert-danger alert-danger-global mb-3';
                    fallbackAlert.innerHTML = '<strong>' + path + ':</strong> ' + errors.fields[path].join('<br>');
                    form.insertBefore(fallbackAlert, form.firstChild);
                }
            }
        }
    }
}

function initAjaxFormSubmit() {
    var form = document.getElementById('ajax-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var url = form.getAttribute('action');
        var formData = new FormData(form);

        fetch(url, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) {
                return res.json().catch(function() {
                    throw new Error('An unexpected server error occurred.');
                });
            })
            .then(function (data) {
                if (data && data.success) {
                    var modalEl = document.getElementById('ajax-modal');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();

                    showSuccessToast(data.message);
                    refreshModsCards();
                } else {
                    if (data && data.errors) {
                        displayFormErrors(data.errors);
                    } else {
                        alert(data.message || 'Validation failed.');
                    }
                }
            })
            .catch(function (err) {
                alert(err.message || 'An error occurred during submission.');
            });
    });
}



// =====================================================
//  REFRESH CARDS
// =====================================================
function refreshModsCards() {
    var currentCards = document.getElementById('mods-cards');
    if (!currentCards) return;

    var url = currentCards.getAttribute('data-refresh-url') || '/admin/mods/';

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (res) { return res.text(); })
        .then(function (html) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');

            var newCards = doc.querySelector('#mods-cards');

            if (newCards && currentCards) {
                currentCards.innerHTML = newCards.innerHTML;
            }
        })
        .catch(function () {
            // Silently fail if refresh is not available (e.g., public page)
        });
}

// =====================================================
//  TOAST
// =====================================================
function showSuccessToast(message) {
    var toastEl = document.getElementById('toast-success');
    if (!toastEl) return;
    var body = toastEl.querySelector('.toast-body');
    if (body) {
        body.textContent = message;
    }

    var toast = new bootstrap.Toast(toastEl);
    toast.show();
}

// =====================================================
//  AJAX MODAL LOADER
// =====================================================
document.addEventListener('click', function (e) {
     var target = e.target || e.srcElement;
     var btn = findParent(target, 'ajax-modal-trigger');

     if (!btn) return;

     e.preventDefault();

     var url = btn.getAttribute('data-url');
     var title = btn.getAttribute('data-title');

     var modalEl = document.getElementById('ajax-modal');
     if (!modalEl) return;
     var modalContent = modalEl.querySelector('.modal-content');
     if (!modalContent) return;

     modalContent.innerHTML =
         '<div class="p-5 text-center">' +
         '<div class="spinner-border text-light"></div>' +
         '</div>';

     var modal = new bootstrap.Modal(modalEl);
     modal.show();

     fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
         .then(function (res) { return res.text(); })
         .then(function (html) {
             modalContent.innerHTML =
                 '<div class="modal-header">' +
                 '<h5 class="modal-title">' + title + '</h5>' +
                 '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
                 '</div>' +
                 '<div class="modal-body">' + html + '</div>';

             // 1. Init main Mod dynamic layouts
             initModForm();

             // 2. Direct setup execution for Categories
             if (typeof initCategoryManagerHandlers === 'function') {
                 initCategoryManagerHandlers();
             }

             // 3. Dispatch global event custom tracking hook safely
             var event = new CustomEvent('ajaxModalLoaded');
             document.dispatchEvent(event);
         });
 });

// =====================================================
//  CATEGORY MANAGER HANDLERS
// =====================================================
function initCategoryManagerHandlers() {
    var listContainer = document.getElementById('category-list');
    var createBtn = document.getElementById('create-category-btn');

    // Helper to extract locale or construct base route path correctly
    var currentPath = window.location.pathname; // e.g., "/en/admin/mods/"
    var isLocalized = currentPath.split('/')[1].length === 2; // checks if first chunk is 'en', 'ro', etc.
    var baseAdminUrl = isLocalized ? '/' + currentPath.split('/')[1] + '/admin/mods' : '/admin/mods';

    function hasClass(el, cls) {
        return el && (' ' + el.className + ' ').indexOf(' ' + cls + ' ') > -1;
    }

    // ==========================================
    //  CREATE CATEGORY
    // ==========================================
    if (createBtn) {
        createBtn.onclick = null;
        createBtn.onclick = function () {

            var nameInput = document.getElementById('new-category-name');
            var imageInput = document.getElementById('new-category-image'); // ADD THIS INPUT IN HTML

            if (!nameInput) return;
            var name = nameInput.value.trim();
            if (!name) return;

            var formData = new FormData();
            formData.append('name', name);

            if (imageInput && imageInput.files.length > 0) {
                formData.append('image', imageInput.files[0]);
            }

            fetch(baseAdminUrl + '/categories/create', {
                method: 'POST',
                body: formData // IMPORTANT: no headers, no URLSearchParams
            })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('Server responded with HTTP status ' + r.status);
                    }
                    return r.json();
                })
                .then(function (data) {
                    if (!data || !data.success) {
                        alert(data.message || 'Could not save category.');
                        return;
                    }

                    // Add new category to list
                    if (!listContainer) return;

                    var item = document.createElement('div');
                    item.className = 'list-group-item d-flex justify-content-between align-items-center';
                    item.setAttribute('data-id', data.id);

                    item.innerHTML =
                        '<span class="cat-name">' + data.name + '</span>' +
                        '<div class="d-flex gap-2">' +
                        '<button class="btn btn-sm btn-outline-warning edit-category-btn"><i class="fas fa-edit"></i></button>' +
                        '<button class="btn btn-sm btn-outline-danger delete-category-btn"><i class="fas fa-trash"></i></button>' +
                        '</div>';

                    listContainer.appendChild(item);

                    nameInput.value = '';
                    if (imageInput) imageInput.value = '';
                })
                .catch(function(err) {
                    console.error('Error handling create request:', err);
                    alert('An error occurred. Check Symfony logs or DevTools Network Response.');
                });
        };
    }

    // ==========================================
    //  DELEGATED ACTIONS (EDIT / DELETE)
    // ==========================================
    if (listContainer) {
        listContainer.onclick = function (e) {
            var target = e.target || e.srcElement;
            if (target.tagName.toLowerCase() === 'i') {
                target = target.parentNode;
            }

            // DELETE CATEGORY
             if (hasClass(target, 'delete-category-btn')) {
                 var item = findParent(target, 'list-group-item');
                 if (!item) return;
                 var id = item.getAttribute('data-id');

                 if (!confirm('Are you sure you want to delete this category?')) return;

                 fetch(baseAdminUrl + '/categories/' + id + '/delete', {
                     method: 'POST',
                     headers: { 'X-Requested-With': 'XMLHttpRequest' }
                 })
                     .then(function (r) { return r.json(); })
                     .then(function (data) {
                         if (data && data.success) {
                             if (item && item.parentNode) {
                                 item.parentNode.removeChild(item);
                             }
                         }
                     })
                     .catch(function (err) { console.error('Delete error:', err); });
                 return;
             }

            // ENTER EDIT MODE
             if (hasClass(target, 'edit-category-btn')) {
                 var item = findParent(target, 'list-group-item');
                 if (!item) return;
                 var id = item.getAttribute('data-id');

                 var nameSpan = item.querySelector('.cat-name');
                 var oldName = nameSpan ? (nameSpan.textContent || nameSpan.innerText || '').trim() : '';

                 var oldImageEl = item.querySelector('img');
                 var oldImage = oldImageEl ? oldImageEl.src : null;

                 // Build edit UI
                 var oldImageHtml = oldImage ? '<img src="' + oldImage + '" class="rounded mt-1" style="width: 60px; height: 60px; object-fit: cover;" alt="Category Image">' : '';
                 item.innerHTML =
                     '<div class="flex-grow-1">' +
                         '<input type="text" class="form-control form-control-sm edit-input mb-2" value="' + oldName + '">' +
                         '<input type="file" class="form-control form-control-sm edit-image-input mb-2" accept="image/*">' +
                         oldImageHtml +
                     '</div>' +
                     '<div class="d-flex gap-2">' +
                         '<button class="btn btn-sm btn-success save-category-btn"><i class="fas fa-check"></i></button>' +
                         '<button class="btn btn-sm btn-secondary cancel-category-btn"><i class="fas fa-times"></i></button>' +
                     '</div>';

                 return;
             }

            // CANCEL EDIT
            if (hasClass(target, 'cancel-category-btn')) {
                location.reload();
                return;
            }

            // SAVE EDITED CATEGORY (NAME + IMAGE)
             if (hasClass(target, 'save-category-btn')) {
                 var item = findParent(target, 'list-group-item');
                 if (!item) return;
                 var id = item.getAttribute('data-id');

                 var nameInput = item.querySelector('.edit-input');
                 var imageInput = item.querySelector('.edit-image-input');

                 var newName = nameInput ? nameInput.value.trim() : '';
                 if (!newName) return;

                var formData = new FormData();
                formData.append('name', newName);

                if (imageInput && imageInput.files.length > 0) {
                    formData.append('image', imageInput.files[0]);
                }

                fetch(baseAdminUrl + '/categories/' + id + '/edit', {
                    method: 'POST',
                    body: formData
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.success) {
                            alert(data.message || 'Failed to update category.');
                            return;
                        }

                        // Rebuild item UI
                        var imgHtml = data.image
                            ? '<img src="/uploads/categories/' + data.image + '" class="rounded" style="width: 40px; height: 40px; object-fit: cover;" alt="Category Image">'
                            : '<div class="rounded bg-secondary" style="width: 40px; height: 40px; opacity: .2;"></div>';

                        item.innerHTML =
                            '<div class="d-flex align-items-center gap-3">' +
                                imgHtml +
                                '<span class="cat-name">' + data.name + '</span>' +
                            '</div>' +
                            '<div class="d-flex gap-2">' +
                                '<button class="btn btn-sm btn-outline-warning edit-category-btn"><i class="fas fa-edit"></i></button>' +
                                '<button class="btn btn-sm btn-outline-danger delete-category-btn"><i class="fas fa-trash"></i></button>' +
                            '</div>';
                    })
                    .catch(function (err) { console.error('Edit save error:', err); });
                // return
            }
        };
    }
}

document.addEventListener('ajaxModalLoaded', initCategoryManagerHandlers);

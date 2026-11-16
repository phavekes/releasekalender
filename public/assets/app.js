/* Theme switcher: Auto (follow OS) / Light / Dark. The class on <html> is
   already set by an inline snippet in <head> to avoid a flash. */
(function () {
  var modes = ['auto', 'light', 'dark'];
  var labels = { auto: 'Auto', light: 'Light', dark: 'Dark' };
  var root = document.documentElement;
  var btn = document.getElementById('theme-toggle');
  var media = window.matchMedia('(prefers-color-scheme: dark)');

  var ICON_AUTO = '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="8" cy="8" r="4"/><path d="M8 2v2M8 12v2M2 8h2M12 8h2"/><path d="m5.9 5.9 1.4 1.4M9.7 9.7 11.1 11.1M5.9 10.1 7.3 8.7M9.7 5.9 11.1 7.3"/></svg>';
  var ICON_LIGHT = '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="8" cy="8" r="3.5"/><path d="M8 2v1M8 14v1M2 8h1M14 8h1m-4.2-4.2 1.4 1.4M6.8 6.8 5.4 8.2M10.6 10.6 9.2 9.2M10.6 5.4 9.2 6.8"/></svg>';
  var ICON_DARK = '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M9 12.75a4.5 4.5 0 0 0 4.84-5.25A8.02 8.02 0 0 1 8 2a8 8 0 0 1-8 8 8 8 0 0 1 8 8c.75 0 1.48-.12 2.16-.36a4.5 4.5 0 0 0 4.84-5.25"/></svg>';

  function apply() {
    var mode = localStorage.getItem('theme') || 'auto';
    root.classList.toggle('dark', mode === 'dark' || (mode === 'auto' && media.matches));
    if (btn) {
      var icons = { auto: ICON_AUTO, light: ICON_LIGHT, dark: ICON_DARK };
      btn.innerHTML = icons[mode] || ICON_AUTO;
      btn.title = 'Color theme: ' + labels[mode] + ' (click to change)';
    }
  }

  if (btn) {
    btn.addEventListener('click', function () {
      var mode = localStorage.getItem('theme') || 'auto';
      localStorage.setItem('theme', modes[(modes.indexOf(mode) + 1) % modes.length]);
      apply();
    });
  }
  media.addEventListener('change', apply);
  apply();
})();

/* New-release form: the repo tags default to the release number. They keep
   following the release number until the user edits them by hand. */
(function () {
  var nrInput = document.getElementById('release_nr');
  var tags = nrInput
    ? document.querySelectorAll('input[data-default-from="release_nr"]')
    : [];
  if (!nrInput || tags.length === 0) return;

  tags.forEach(function (tag) {
    tag.addEventListener('input', function () {
      tag.dataset.userEdited = '1';
    });
  });

  nrInput.addEventListener('input', function () {
    tags.forEach(function (tag) {
      if (!tag.dataset.userEdited) {
        tag.value = nrInput.value;
      }
    });
  });
})();

/* Copy buttons: the icon swaps to a check mark after copying. Delegated,
   so it also covers rows added later (workflow preview in the add form). */
(function () {
  var ICON_COPY = '<svg viewBox="0 0 16 16" width="14" height="16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="5.5" y="5.5" width="8" height="8" rx="1.5"/><path d="M10.5 5.5V3.8c0-.7-.6-1.3-1.3-1.3H3.8c-.7 0-1.3.6-1.3 1.3v5.4c0 .7.6 1.3 1.3 1.3h1.4"/></svg>';
  var ICON_CHECK = '<svg viewBox="0 0 16 16" width="14" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 8.5l3.2 3.2L13 5"/></svg>';

  document.addEventListener('click', function (event) {
    var btn = event.target.closest('button.btn-copy');
    if (!btn) return;
    navigator.clipboard.writeText(btn.dataset.command || '').then(function () {
      btn.innerHTML = ICON_CHECK;
      setTimeout(function () { btn.innerHTML = ICON_COPY; }, 1500);
    });
  });
})();

/* New-release form: show the standard workflow once an application is
   selected. Updates when the release number or tags change. */
(function () {
  var box = document.getElementById('workflow-steps');
  var hint = document.getElementById('workflow-hint');
  if (!box) return;

  var appSelect = document.getElementById('application');
  var nrInput = document.getElementById('release_nr');
  var deployTag = document.getElementById('deploy_tag');
  var envTag = document.getElementById('environments_tag');

  function render(steps) {
    box.innerHTML = '';
    if (!steps.length) {
      hint.hidden = false;
      return;
    }
    hint.hidden = true;
    var ol = document.createElement('ol');
    ol.className = 'workflow';
    steps.forEach(function (step) {
      var li = document.createElement('li');
      li.className = 'workflow-step';
      var title = document.createElement('div');
      title.className = 'step-title';
      title.textContent = step.title;
      li.appendChild(title);
      step.commands.forEach(function (cmd) {
        var row = document.createElement('div');
        row.className = 'command-row';
        var code = document.createElement('code');
        code.className = 'command';
        code.textContent = cmd;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-ghost btn-copy';
        btn.dataset.command = cmd;
        btn.title = 'Copy command';
        btn.setAttribute('aria-label', 'Copy command');
        btn.innerHTML = '<svg viewBox="0 0 16 16" width="14" height="16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="5.5" y="5.5" width="8" height="8" rx="1.5"/><path d="M10.5 5.5V3.8c0-.7-.6-1.3-1.3-1.3H3.8c-.7 0-1.3.6-1.3 1.3v5.4c0 .7.6 1.3 1.3 1.3h1.4"/></svg>';
        row.appendChild(code);
        row.appendChild(btn);
        li.appendChild(row);
      });
      ol.appendChild(li);
    });
    box.appendChild(ol);
  }

  function load() {
    if (!appSelect || !appSelect.value) {
      box.innerHTML = '';
      hint.hidden = false;
      return;
    }
    var params = new URLSearchParams({
      app: appSelect.value,
      release_nr: nrInput ? nrInput.value : '',
      deploy_tag: deployTag ? deployTag.value : '',
      environments_tag: envTag ? envTag.value : ''
    });
    fetch('/api/workflow?' + params.toString())
      .then(function (r) { return r.json(); })
      .then(function (data) { render(data.steps || []); })
      .catch(function () {
        box.innerHTML = '';
        hint.textContent = 'Could not load the workflow.';
        hint.hidden = false;
      });
  }

  if (appSelect) appSelect.addEventListener('change', load);
  [nrInput, deployTag, envTag].forEach(function (el) {
    if (el) el.addEventListener('input', load);
  });

  if (appSelect && appSelect.value) load();
})();

/* Auto-save and drag-and-drop for planned-date items on release detail page. */
(function () {
  var form = document.querySelector('.release-form');
  var list = document.getElementById('plan-items');
  var addBtn = document.getElementById('item-add');
  
  if (!form || !list || !addBtn) return;

  // Create flash message element if it doesn't exist
  var flash = document.querySelector('.flash');
  if (!flash) {
    flash = document.createElement('p');
    flash.className = 'flash';
    flash.style.display = 'none';
    var pageHead = document.querySelector('.page-head');
    if (pageHead) {
      pageHead.appendChild(flash);
    } else {
      form.insertBefore(flash, form.firstChild);
    }
  }

  var X_ICON = '<svg viewBox="0 0 16 16" width="12" height="12" stroke="currentColor" stroke-width="1.8" fill="none" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>';
  var DRAG_ICON = '<svg viewBox="0 0 16 16" width="12" height="12" stroke="currentColor" stroke-width="1.5" fill="none" aria-hidden="true"><path d="M5 4h1M5 8h7M5 12h7"/></svg>';

  // Debounce function to prevent too many rapid saves
  var saveTimeout = null;
  var isSaving = false;

  function triggerSave() {
    if (isSaving) return;
    
    isSaving = true;
    clearTimeout(saveTimeout);
    
    // Collect form data
    var formData = new FormData(form);
    
    // Submit via AJAX
    fetch(form.action, {
      method: 'POST',
      body: formData
    })
      .then(function (response) {
        if (response.ok) {
          // Show a flash message
          flash.textContent = 'Changes saved.';
          flash.style.display = 'block';
          setTimeout(function () {
            flash.style.display = 'none';
          }, 3000);
        } else {
          console.error('Failed to save changes');
        }
      })
      .catch(function (error) {
        console.error('Error:', error);
      })
      .finally(function () {
        isSaving = false;
      });
  }

  // Auto-save on change for items (checkboxes, dates, names)
  form.addEventListener('change', function (event) {
    var target = event.target;
    // Auto-save for all item fields: installed checkbox, name text, planned_date
    if (target.name && target.name.match(/items\[\d+\]\[(installed|name|planned_date)\]/)) {
      // Use debounce to prevent rapid saves
      clearTimeout(saveTimeout);
      saveTimeout = setTimeout(triggerSave, 500);
    }
  });

  // Drag and drop reordering
  list.addEventListener('dragstart', function (event) {
    // Check if dragging started from a drag handle (either span or svg inside it)
    var dragHandle = event.target.closest('.drag-handle');
    var dragged = event.target.closest('.plan-item');
    if (!dragHandle || !dragged) return;
    
    dragged.classList.add('dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', dragged.dataset.index);
  });

  list.addEventListener('dragend', function (event) {
    var dragged = event.target.closest('.plan-item');
    if (!dragged) return;
    dragged.classList.remove('dragging');
    // Trigger save after reordering
    triggerSave();
  });

  list.addEventListener('dragenter', function (event) {
    event.preventDefault();
    var target = event.target.closest('.plan-item');
    if (!target || target.classList.contains('dragging')) return;
    target.classList.add('drag-over');
  });

  list.addEventListener('dragover', function (event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    var target = event.target.closest('.plan-item');
    if (!target || target.classList.contains('dragging')) return;
    target.classList.add('drag-over');
  });

  list.addEventListener('dragleave', function (event) {
    var target = event.target.closest('.plan-item');
    if (target) target.classList.remove('drag-over');
  });

  list.addEventListener('drop', function (event) {
    event.preventDefault();
    
    // Remove all drag-over classes
    list.querySelectorAll('.plan-item').forEach(function (item) {
      item.classList.remove('drag-over');
    });
    
    var dragged = list.querySelector('.plan-item.dragging');
    if (!dragged) return;
    
    // Find the drop position
    var target = event.target.closest('.plan-item');
    var rects = Array.from(list.querySelectorAll('.plan-item:not(.dragging)')).map(function (item) {
      return item.getBoundingClientRect();
    });
    
    var y = event.clientY;
    var dropIndex = 0;
    
    // Find which item to drop before/after
    for (var i = 0; i < rects.length; i++) {
      var rect = rects[i];
      if (y < rect.top + rect.height / 2) {
        dropIndex = i;
        break;
      }
      dropIndex = i + 1;
    }
    
    // Remove dragging class before reinserting
    dragged.classList.remove('dragging');
    
    // Reinsert at the new position
    var allItems = Array.from(list.querySelectorAll('.plan-item'));
    var draggedIndex = allItems.indexOf(dragged);
    
    if (dropIndex > draggedIndex) {
      dropIndex--; // Adjust because we're removing the dragged item
    }
    
    // Insert at the drop position
    if (dropIndex >= allItems.length) {
      list.appendChild(dragged);
    } else {
      list.insertBefore(dragged, allItems[dropIndex]);
    }
    
    // Update the data-index attributes and input names to match the new order
    reindexItems();
  });

  function reindexItems() {
    var allItems = list.querySelectorAll('.plan-item');
    allItems.forEach(function (item, index) {
      item.dataset.index = index;
      // Update the input names to use the new index
      var inputs = item.querySelectorAll('input');
      inputs.forEach(function (input) {
        var name = input.name;
        if (name && name.match(/items\[\d+\]/)) {
          input.name = name.replace(/items\[\d+\]/, 'items[' + index + ']');
        }
      });
    });
  }

  addBtn.addEventListener('click', function () {
    var maxIndex = list.querySelectorAll('.plan-item').length;
    var row = document.createElement('div');
    row.className = 'env-row env-row-edit plan-item';
    row.dataset.index = maxIndex;
    row.draggable = true;
    row.innerHTML =
      '<span class="drag-handle" title="Drag to reorder">' + DRAG_ICON + '</span>' +
      '<input type="checkbox" name="items[' + maxIndex + '][installed]" value="1" title="Installed on this environment">' +
      '<input type="text" class="item-name" name="items[' + maxIndex + '][name]" placeholder="item">' +
      '<input type="date" name="items[' + maxIndex + '][planned_date]" title="Planned date">' +
      '<button type="button" class="btn btn-ghost item-delete" title="Remove item">' + X_ICON + '</button>';
    list.appendChild(row);
    row.querySelector('.item-name').focus();
  });

  list.addEventListener('click', function (event) {
    var btn = event.target.closest('button.item-delete');
    if (!btn) return;
    btn.closest('.plan-item').remove();
    // After deletion, reindex and save
    reindexItems();
    triggerSave();
  });
})();

/* After selecting the application, the version field gets suggestions from
   the GitHub releases that are not in the calendar yet. Picking one fetches
   the release changes into the changelog textarea. */
(function () {
  var appSelect = document.getElementById('application');
  var versionInput = document.getElementById('version');
  if (!appSelect || !versionInput) return;

  var datalist = document.getElementById('version-list');
  var changelogInput = document.getElementById('changelog');
  var status = document.getElementById('github-status');
  var link = document.getElementById('gh-link');
  var byVersion = {};

  function setStatus(text) {
    if (status) status.textContent = text;
  }

  function clearReleases() {
    byVersion = {};
    if (datalist) datalist.innerHTML = '';
    if (link) link.hidden = true;
  }

  function load(app) {
    clearReleases();
    if (!app) {
      setStatus('');
      return;
    }
    setStatus('Loading releases from GitHub...');
    fetch('/api/github-releases?app=' + encodeURIComponent(app))
      .then(function (r) {
        if (!r.ok) {
          // Try to get error message from response
          return r.text().then(function (text) {
            throw new Error(text || 'HTTP ' + r.status);
          });
        }
        return r.json();
      })
      .then(function (data) {
        if (app !== appSelect.value) return;
        render(data);
      })
      .catch(function (error) {
        if (app !== appSelect.value) return;
        console.error('Failed to load GitHub releases:', error);
        setStatus('Could not load GitHub releases.');
      });
  }

  function render(data) {
    var releases = (data && data.releases) || [];
    if (releases.length === 0) {
      var error = (data && data.error) || '';
      setStatus(error === ''
        ? 'No new GitHub releases found (all are already in the calendar).'
        : (error.indexOf('configured') !== -1
          ? 'No GitHub releases URL configured for this application.'
          : error));
      return;
    }
    releases.forEach(function (rel) {
      byVersion[rel.tag] = rel;
      if (datalist) {
        var opt = document.createElement('option');
        opt.value = rel.tag;
        if (rel.name && rel.name !== rel.tag) opt.label = rel.name;
        datalist.appendChild(opt);
      }
    });
    setStatus('Pick a version to fetch its changes from GitHub.');
  }

  versionInput.addEventListener('change', function () {
    var rel = byVersion[versionInput.value];
    if (!rel) {
      if (link) link.hidden = true;
      return;
    }
    if (changelogInput) changelogInput.value = rel.body || '';
    if (link) {
      link.href = rel.url;
      link.hidden = false;
    }
    setStatus(versionInput.value +
      (rel.published_at ? ' - released ' + rel.published_at.slice(0, 10) : ''));
  });

  appSelect.addEventListener('change', function () {
    load(appSelect.value);
  });

  if (appSelect.value) load(appSelect.value);
})();

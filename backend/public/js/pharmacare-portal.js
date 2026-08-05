document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const updateSidebarButtons = () => {
    const mobile = window.innerWidth <= 780;
    const expanded = mobile
      ? body.classList.contains('sidebar-open')
      : !body.classList.contains('sidebar-collapsed');
    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
      button.setAttribute('aria-expanded', String(expanded));
      button.setAttribute(
        'aria-label',
        expanded ? 'Réduire la barre latérale' : 'Agrandir la barre latérale',
      );
    });
  };
  document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      if (window.innerWidth <= 780) {
        body.classList.toggle('sidebar-open');
      } else {
        body.classList.toggle('sidebar-collapsed');
        localStorage.setItem(
          'pharmacare-sidebar',
          body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded',
        );
      }
      updateSidebarButtons();
    });
  });
  if (window.innerWidth > 780 && localStorage.getItem('pharmacare-sidebar') === 'collapsed') {
    body.classList.add('sidebar-collapsed');
  }
  updateSidebarButtons();

  const logoInput = document.querySelector('[data-logo-input]');
  const logoPreview = document.querySelector('[data-logo-preview]');
  logoInput?.addEventListener('change', () => {
    const file = logoInput.files?.[0];
    if (!file || !logoPreview) return;
    const image = document.createElement('img');
    image.alt = 'Aperçu du logo';
    image.src = URL.createObjectURL(file);
    logoPreview.replaceChildren(image);
  });

  document.querySelectorAll('[data-loading-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!form.checkValidity()) {
        event.preventDefault();
        form.reportValidity();
        return;
      }
      const submitter = event.submitter;
      form.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = true;
      });
      if (submitter) {
        submitter.classList.add('loading');
        submitter.textContent = 'Enregistrement…';
      }
    });
  });

  const validationSummary = document.querySelector('.validation-summary');
  if (validationSummary) {
    validationSummary.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  const workflow = document.querySelector('[data-workflow-draft-url]');
  const workflowForm = workflow?.querySelector('form.organization-form');
  const draftStatus = workflow?.querySelector('[data-draft-status]');
  if (workflow && workflowForm) {
    let draftTimer;
    let draftController;
    const draftPayload = () => {
      const values = {};
      new FormData(workflowForm).forEach((value, key) => {
        if (value instanceof File || ['_token', '_method'].includes(key)) return;
        const normalizedKey = key.endsWith('[]') ? key.slice(0, -2) : key;
        if (key.endsWith('[]')) {
          values[normalizedKey] ??= [];
          values[normalizedKey].push(value);
        } else {
          values[normalizedKey] = value;
        }
      });
      return values;
    };
    const saveDraft = async () => {
      draftController?.abort();
      draftController = new AbortController();
      if (draftStatus) draftStatus.textContent = 'Enregistrement du brouillon…';
      try {
        const response = await fetch(workflow.dataset.workflowDraftUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
          },
          body: JSON.stringify({
            step: Number(workflow.dataset.workflowStep),
            payload: draftPayload(),
          }),
          signal: draftController.signal,
        });
        if (!response.ok) throw new Error('draft_failed');
        if (draftStatus) draftStatus.textContent = 'Brouillon enregistré';
      } catch (error) {
        if (error.name !== 'AbortError' && draftStatus) {
          draftStatus.textContent = 'Brouillon non enregistré';
        }
      }
    };
    workflowForm.addEventListener('input', () => {
      window.clearTimeout(draftTimer);
      if (draftStatus) draftStatus.textContent = 'Modifications en attente…';
      draftTimer = window.setTimeout(saveDraft, 800);
    });
    workflowForm.addEventListener('change', () => {
      window.clearTimeout(draftTimer);
      draftTimer = window.setTimeout(saveDraft, 300);
    });
  }
});

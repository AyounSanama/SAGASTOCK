/* Shared dialog behavior, independent of the page's business submission. */
document.addEventListener('DOMContentLoaded', () => {
  const snapshots = new WeakMap();
  const signature = sheet => JSON.stringify([...sheet.querySelectorAll('input,select,textarea')]
    .filter(field => !['_token', '_method'].includes(field.name))
    .map(field => [field.name, field.type === 'checkbox' || field.type === 'radio'
      ? field.checked : field.multiple ? [...field.selectedOptions].map(option => option.value) : field.value]));
  const close = sheet => {
    if (sheet.dataset.submitting === 'true') return;
    if (snapshots.has(sheet) && snapshots.get(sheet) !== signature(sheet)
        && !window.confirm('Abandonner les modifications ?')) return;
    sheet.close();
  };
  document.querySelectorAll('.form-sheet').forEach(sheet => {
    // Move legacy actions outside the scrolling body, retaining form ownership.
    const panel = sheet.querySelector('.form-sheet-panel');
    const actions = sheet.querySelector('.sheet-actions, .form-sheet-actions, .wizard-footer');
    if (actions && !actions.closest('.form-sheet-footer')) {
      const form = actions.closest('form');
      if (form) {
        form.id ||= `${sheet.id}-form`;
        actions.querySelectorAll('button,input,select,textarea').forEach(control => control.setAttribute('form', form.id));
      }
      const footer = actions.tagName === 'FOOTER' ? actions : document.createElement('footer');
      footer.classList.add('form-sheet-footer');
      if (footer !== actions) footer.append(actions);
      panel.append(footer);
    }
    sheet.querySelectorAll('form[data-facility-workflow]').forEach(form => {
      const grid = form.querySelector('.form-grid');
      if (!grid) return;
      const labels = ['Informations', 'Classification', 'Localisation', 'Résumé'];
      const stepper = document.createElement('ol');
      stepper.className = 'form-stepper';
      stepper.setAttribute('aria-label', 'Étapes du formulaire');
      const steps = labels.map((label, index) => {
        const section = document.createElement('section');
        section.dataset.formStep = String(index);
        section.setAttribute('aria-label', label);
        section.className = index === 3 ? '' : 'form-grid';
        return section;
      });
      [...grid.children].forEach(field => {
        const name = field.querySelector('[name]')?.name;
        const step = ['facility_type', 'care_level'].includes(name) ? 1
          : ['phone', 'email', 'address', 'region', 'district', 'locality', 'latitude', 'longitude'].includes(name) ? 2 : 0;
        steps[step].append(field);
      });
      grid.replaceWith(stepper, ...steps);
      const footer = sheet.querySelector('.form-sheet-footer > div');
      if (!footer) return;
      const submit = [...footer.querySelectorAll('button')].find(button => button.type === 'submit');
      const previous = document.createElement('button');
      previous.type = 'button'; previous.className = 'btn btn-outline'; previous.textContent = 'Précédent';
      const next = document.createElement('button');
      next.type = 'button'; next.className = 'btn btn-primary'; next.textContent = 'Continuer';
      footer.append(previous, next);
      let current = 0;
      const valid = index => {
        const invalid = [...steps[index].querySelectorAll('input,select,textarea')].find(field => !field.checkValidity());
        if (invalid) { invalid.reportValidity(); return false; }
        return true;
      };
      const render = () => {
        steps.forEach((step, index) => { step.hidden = index !== current; });
        [...stepper.querySelectorAll('button')].forEach((button, index) => {
          if (index === current) button.setAttribute('aria-current', 'step');
          else button.removeAttribute('aria-current');
        });
        previous.hidden = current === 0; next.hidden = current === 3;
        if (submit) submit.hidden = current !== 3;
        if (current === 3) {
          const summary = document.createElement('dl'); summary.className = 'form-summary';
          steps.slice(0, 3).forEach(step => step.querySelectorAll('label.field,fieldset.field').forEach(label => {
            if (label.tagName === 'FIELDSET') {
              const term = document.createElement('dt');
              term.textContent = label.querySelector('legend')?.textContent || 'Sélection';
              const value = document.createElement('dd');
              value.textContent = [...label.querySelectorAll('input:checked')]
                .map(input => input.closest('label')?.textContent.trim()).filter(Boolean).join(', ') || '—';
              summary.append(term, value);
              return;
            }
            const field = label.querySelector('input,select,textarea');
            if (!field || field.type === 'hidden') return;
            const term = document.createElement('dt');
            term.textContent = label.firstChild?.textContent.trim() || field.name;
            const value = document.createElement('dd');
            value.textContent = field.type === 'checkbox' ? (field.checked ? 'Oui' : 'Non')
              : field.tagName === 'SELECT' ? [...field.selectedOptions].map(option => option.textContent).join(', ') || '—'
              : field.value || '—';
            summary.append(term, value);
          }));
          steps[3].replaceChildren(summary);
        }
      };
      const go = target => {
        if (target > current) {
          for (let index = current; index < target; index++) {
            current = index; render();
            if (!valid(index)) return;
          }
        }
        current = target; render();
      };
      labels.forEach((label, index) => {
        const item = document.createElement('li'); const button = document.createElement('button');
        button.type = 'button'; button.textContent = `${index + 1}. ${label}`;
        button.addEventListener('click', () => go(index)); item.append(button); stepper.append(item);
      });
      previous.addEventListener('click', () => go(current - 1));
      next.addEventListener('click', () => go(current + 1));
      form.noValidate = true;
      form.addEventListener('submit', event => {
        if (current !== 3) { event.preventDefault(); go(current + 1); return; }
        for (let index = 0; index < 3; index++) {
          current = index; render();
          if (!valid(index)) { event.preventDefault(); return; }
        }
        current = 3; render();
      });
      render();
    });
    snapshots.set(sheet, signature(sheet));
    if (sheet.hasAttribute('data-sheet-auto-open') && !sheet.open) sheet.showModal();
    sheet.addEventListener('cancel', event => { event.preventDefault(); close(sheet); });
    sheet.addEventListener('click', event => { if (event.target === sheet) close(sheet); });
    sheet.querySelectorAll('[data-sheet-close]').forEach(button => button.addEventListener('click', () => close(sheet)));
    sheet.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
      // Defer until page handlers have had the opportunity to cancel submission.
      queueMicrotask(() => {
        if (!event.defaultPrevented) sheet.dataset.submitting = 'true';
      });
    }));
  });
  document.querySelectorAll('[data-sheet-open]').forEach(button => button.addEventListener('click', () => {
    const sheet = document.getElementById(button.dataset.sheetOpen);
    if (sheet && !sheet.open) {
      sheet.dataset.submitting = 'false';
      sheet.showModal();
    }
  }));
});

(() => {
  'use strict';
  document.querySelectorAll('[data-htecl-print]').forEach(button => {
    button.addEventListener('click', () => window.print());
  });
  const container = document.querySelector('[data-htecl-fields]');
  const template = document.querySelector('[data-htecl-template]');
  const button = document.querySelector('[data-htecl-add]');
  if (container && template && button) {
    let next = container.children.length;
    button.disabled = next >= 25;
    button.addEventListener('click', () => {
      if (next >= 25) return;
      const field = template.content.cloneNode(true);
      field.querySelectorAll('[name]').forEach(control => {
        control.name = control.name.replace('__index__', String(next));
      });
      field.querySelectorAll('select[id]').forEach(control => {
        const label = field.querySelector('label[for="' + control.id + '"]');
        control.id += '-' + String(next);
        if (label) label.htmlFor = control.id;
      });
      container.append(field);
      container.lastElementChild.querySelector('input:not([type=hidden])')?.focus();
      button.disabled = ++next >= 25;
    });
  }
})();

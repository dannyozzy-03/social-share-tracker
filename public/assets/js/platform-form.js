(() => {
  'use strict';

  const form = document.getElementById('platform-form');
  const script = document.getElementById('platform-form-script');
  if (!form || !script) {
    return;
  }

  const fields = {
    name: form.elements.name,
    template: form.elements.share_url_template,
    icon: form.elements.icon,
    color: form.elements.color,
    picker: document.getElementById('color-picker'),
  };
  const preview = document.getElementById('preview');
  const previewIcon = document.getElementById('preview-icon');
  const previewName = document.getElementById('preview-name');
  const previewLink = document.getElementById('preview-link');
  const sample = {
    url: script.dataset.sampleUrl,
    title: script.dataset.sampleTitle,
    description: 'Stories worth passing on',
  };

  // Mirrors on_stage() in PHP.
  function onStage(hex) {
    const channel = (i) => {
      const c = parseInt(hex.slice(i, i + 2), 16) / 255;
      return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    const luminance = 0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5);
    return luminance < 0.03 ? '#E9E7F5' : hex;
  }

  function update() {
    const color = /^#[0-9a-f]{6}$/i.test(fields.color.value) ? fields.color.value : '#6C757D';
    preview.style.setProperty('--gel-day', color);
    preview.style.setProperty('--gel-night', onStage(color));
    previewName.textContent = fields.name.value.trim() || 'Platform';
    if (/^bi-[a-z0-9]+(-[a-z0-9]+)*$/.test(fields.icon.value.trim())) {
      previewIcon.className = `bi ${fields.icon.value.trim()}`;
    }
    previewLink.textContent = fields.template.value
      .replaceAll('{url}', encodeURIComponent(sample.url))
      .replaceAll('{title}', encodeURIComponent(sample.title))
      .replaceAll('{description}', encodeURIComponent(sample.description));
  }

  fields.picker.addEventListener('input', () => {
    fields.color.value = fields.picker.value.toUpperCase();
    update();
  });
  fields.color.addEventListener('input', () => {
    if (/^#[0-9a-f]{6}$/i.test(fields.color.value)) {
      fields.picker.value = fields.color.value;
    }
    update();
  });
  [fields.name, fields.template, fields.icon].forEach((field) => field.addEventListener('input', update));

  update();
})();

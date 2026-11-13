(function () {
  var form = document.getElementById('job');
  var input = document.getElementById('file-input');
  var drop = document.getElementById('drop');
  var queueEl = document.getElementById('queue');
  var results = document.getElementById('results');
  var feedback = document.getElementById('feedback');
  var submitBtn = document.getElementById('process-btn');
  var quality = document.getElementById('quality');
  var qualityOut = document.getElementById('quality-out');
  var limits = window.APP || { maxFiles: 8, maxBytes: 8 * 1024 * 1024 };
  var queue = [];
  var resultUrls = [];
  var tool = 'remove_bg';

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }

  function sizeLabel(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }

  function extensionOf(name) {
    var parts = String(name).toLowerCase().split('.');
    return parts.length > 1 ? parts.pop() : '';
  }

  function accepted(file) {
    var allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/x-ms-bmp'];
    if (allowed.indexOf(file.type) !== -1) return true;
    return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'].indexOf(extensionOf(file.name)) !== -1;
  }

  function showError(message) {
    feedback.textContent = message;
  }

  function clearError() {
    feedback.textContent = '';
  }

  function updateButton() {
    var count = queue.length;
    submitBtn.disabled = false;
    if (count === 0) submitBtn.textContent = 'Procesar';
    else if (count === 1) submitBtn.textContent = 'Procesar 1 imagen';
    else submitBtn.textContent = 'Procesar ' + count + ' imágenes';
  }

  function renderQueue() {
    queueEl.replaceChildren();
    queue.forEach(function (item, index) {
      var li = el('li');
      var img = el('img');
      img.src = item.url;
      img.alt = '';
      var copy = el('div');
      copy.append(el('p', null, item.file.name));
      copy.append(el('small', null, sizeLabel(item.file.size)));
      var remove = el('button', 'ghost', 'Quitar');
      remove.type = 'button';
      remove.addEventListener('click', function () {
        URL.revokeObjectURL(item.url);
        queue.splice(index, 1);
        renderQueue();
        updateButton();
      });
      li.append(img, copy, remove);
      queueEl.append(li);
    });
  }

  function addFiles(list) {
    var rejected = [];
    Array.prototype.forEach.call(list, function (file) {
      if (queue.length >= limits.maxFiles) {
        rejected.push('Máximo ' + limits.maxFiles + ' imágenes por vez.');
        return;
      }
      if (!accepted(file)) {
        rejected.push(file.name + ' no es PNG, JPG, WEBP, GIF o BMP.');
        return;
      }
      if (file.size > limits.maxBytes) {
        rejected.push(file.name + ' supera el tamaño permitido.');
        return;
      }
      queue.push({ file: file, url: URL.createObjectURL(file) });
    });
    renderQueue();
    updateButton();
    if (rejected.length) showError(rejected[0]);
    else clearError();
  }

  function setTool(next) {
    tool = next;
    document.getElementById('tool').value = next;
    Array.prototype.forEach.call(document.querySelectorAll('.tool'), function (button) {
      button.setAttribute('aria-pressed', button.getAttribute('data-tool') === next ? 'true' : 'false');
    });
    var local = next === 'convert' || next === 'crop' || next === 'flip';
    document.getElementById('panel-keep').hidden = next !== 'remove_bg';
    document.getElementById('panel-scale').hidden = next !== 'upscale';
    document.getElementById('panel-format').hidden = next !== 'convert';
    document.getElementById('panel-bg-mode').hidden = next !== 'remove_bg';
    document.getElementById('panel-color').hidden = next === 'upscale' || next === 'crop' || next === 'flip';
    document.getElementById('panel-flip').hidden = next !== 'flip';
    document.getElementById('panel-crop-note').hidden = next !== 'crop';
    document.getElementById('panel-crop-option').hidden = next === 'crop' || next === 'flip';
    document.getElementById('crop-hint').hidden = next === 'crop' || next === 'flip';
    document.getElementById('fill-hint').hidden = next !== 'convert';
    document.getElementById('ai-hint').hidden = local;
    syncColor();
  }

  function syncColor() {
    var solid = document.querySelector('input[name="background"][value="color"]').checked;
    var muted = tool === 'remove_bg' && !solid;
    document.getElementById('color-field').classList.toggle('is-muted', muted);
  }

  function revokeResults() {
    resultUrls.forEach(function (url) { URL.revokeObjectURL(url); });
    resultUrls = [];
  }

  function b64ToUrl(b64, mime) {
    var binary = atob(b64);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    var url = URL.createObjectURL(new Blob([bytes], { type: mime }));
    resultUrls.push(url);
    return url;
  }

  function downloadUrl(url, name) {
    var link = el('a');
    link.href = url;
    link.download = name;
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  function mountCompare(host, beforeUrl, afterUrl) {
    var box = el('div', 'compare');
    var after = el('img', 'after');
    after.alt = 'Resultado';
    after.src = afterUrl;
    var clip = el('div', 'before-clip');
    var before = el('img');
    before.alt = 'Original';
    before.src = beforeUrl;
    clip.append(before);
    var line = el('div', 'split');
    var range = document.createElement('input');
    range.type = 'range';
    range.min = '0';
    range.max = '100';
    range.value = '56';
    range.setAttribute('aria-label', 'Comparar antes y después');
    function paint() {
      var value = Number(range.value);
      clip.style.clipPath = 'inset(0 ' + (100 - value) + '% 0 0)';
      line.style.left = value + '%';
    }
    range.addEventListener('input', paint);
    box.append(after, clip, line, range);
    host.append(box);
    paint();
  }

  function renderResults(data, submitted) {
    revokeResults();
    results.replaceChildren();

    if (data.notices && data.notices.length) {
      var notes = el('div', 'notices');
      data.notices.forEach(function (text) {
        notes.append(el('p', null, text));
      });
      results.append(notes);
    }

    if (data.zip) {
      var zipUrl = b64ToUrl(data.zip.base64, data.zip.mime);
      var zipBtn = el('button', 'secondary', 'Descargar ZIP');
      zipBtn.type = 'button';
      zipBtn.addEventListener('click', function () {
        downloadUrl(zipUrl, data.zip.name);
      });
      results.append(zipBtn);
    }

    var picker = el('div', 'picker');
    var stage = el('div', 'stage');
    if (data.files.length > 1) results.append(picker);
    results.append(stage);

    data.files.forEach(function (file, index) {
      var afterUrl = b64ToUrl(file.base64, file.mime);
      var beforeUrl = '';
      var source = submitted[file.source_index];
      if (source) {
        beforeUrl = URL.createObjectURL(source.file);
        resultUrls.push(beforeUrl);
      }
      var select = el('button', 'pick', file.name);
      select.type = 'button';
      function show() {
        Array.prototype.forEach.call(picker.children, function (child) {
          child.setAttribute('aria-pressed', 'false');
        });
        select.setAttribute('aria-pressed', 'true');
        stage.replaceChildren();
        if (beforeUrl) mountCompare(stage, beforeUrl, afterUrl);
        else {
          var only = el('img', 'after');
          only.alt = 'Resultado';
          only.src = afterUrl;
          stage.append(only);
        }
        var meta = el('div', 'meta');
        meta.append(el('span', null, file.width + ' × ' + file.height + ' · ' + sizeLabel(file.bytes)));
        var save = el('button', 'primary', 'Descargar');
        save.type = 'button';
        save.addEventListener('click', function () { downloadUrl(afterUrl, file.name); });
        meta.append(save);
        stage.append(meta);
      }
      select.addEventListener('click', show);
      picker.append(select);
      if (index === 0) show();
    });

    results.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  Array.prototype.forEach.call(document.querySelectorAll('.tool'), function (button) {
    button.addEventListener('click', function () {
      setTool(button.getAttribute('data-tool'));
    });
  });

  document.querySelectorAll('input[name="background"]').forEach(function (radio) {
    radio.addEventListener('change', syncColor);
  });

  quality.addEventListener('input', function () {
    qualityOut.textContent = quality.value;
  });

  var keep = document.getElementById('keep');
  var keepOut = document.getElementById('keep-out');
  function keepLabel(value) {
    var level = Number(value);
    if (level < 34) return 'Solo el motivo';
    if (level < 67) return 'Equilibrado';
    return 'Todo el diseño';
  }
  keep.addEventListener('input', function () {
    keepOut.textContent = keepLabel(keep.value);
  });

  document.getElementById('preset').addEventListener('click', function () {
    document.getElementById('crop').checked = true;
    document.getElementById('dpi').value = '300';
    feedback.style.color = 'var(--muted)';
    feedback.textContent = 'Preset de producción: recorte al contenido y 300 DPI.';
  });

  input.addEventListener('change', function () {
    addFiles(input.files);
    input.value = '';
  });

  ['dragenter', 'dragover'].forEach(function (name) {
    drop.addEventListener(name, function (event) {
      event.preventDefault();
      drop.classList.add('is-over');
    });
  });
  ['dragleave', 'drop'].forEach(function (name) {
    drop.addEventListener(name, function (event) {
      event.preventDefault();
      drop.classList.remove('is-over');
    });
  });
  drop.addEventListener('drop', function (event) {
    addFiles(event.dataTransfer.files);
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!queue.length) {
      showError('Subí al menos una imagen.');
      return;
    }

    var data = new FormData();
    data.set('tool', tool);
    data.set('scale', document.querySelector('input[name="scale"]:checked').value);
    data.set('format', document.getElementById('format').value);
    data.set('quality', quality.value);
    data.set('background', document.querySelector('input[name="background"]:checked').value);
    data.set('background_color', document.getElementById('background-color').value);
    data.set('crop', document.getElementById('crop').checked ? '1' : '0');
    data.set('dpi', document.getElementById('dpi').value);
    data.set('keep', keep.value);
    var flip = document.querySelector('input[name="flip"]:checked');
    data.set('flip', flip ? flip.value : 'horizontal');
    queue.forEach(function (item) { data.append('files[]', item.file, item.file.name); });

    var submitted = queue.slice();
    submitBtn.disabled = true;
    submitBtn.textContent = 'Procesando…';
    feedback.style.color = 'var(--muted)';
    feedback.textContent = tool === 'convert'
      ? 'Convirtiendo en el servidor…'
      : (tool === 'crop'
        ? 'Recortando en el servidor…'
        : (tool === 'flip'
          ? 'Espejando en el servidor…'
          : (limits.aiRemote
            ? (tool === 'remove_bg'
              ? 'Quitando el fondo en Replicate. Suele tardar unos segundos.'
              : 'Escalando en Replicate. Suele tardar unos segundos.')
            : (tool === 'remove_bg'
              ? 'Quitando el fondo en esta PC. Puede tardar unos minutos.'
              : 'Escalando en esta PC. Puede tardar un momento.'))));

    fetch('api/process.php', { method: 'POST', body: data })
      .then(function (response) {
        return response.text().then(function (text) {
          var payload;
          try { payload = JSON.parse(text); }
          catch (err) { throw new Error('El servidor no respondió bien.'); }
          if (!payload.ok) throw new Error(payload.error || 'No se pudo procesar.');
          return payload;
        });
      })
      .then(function (payload) {
        feedback.textContent = '';
        renderResults(payload, submitted);
      })
      .catch(function (err) {
        feedback.style.color = '';
        showError(err.message || 'No se pudo hablar con el servidor.');
      })
      .then(function () {
        updateButton();
      });
  });

  var requestedTool = new URLSearchParams(window.location.search).get('tool');
  if (!requestedTool || ['remove_bg', 'upscale', 'convert', 'crop', 'flip'].indexOf(requestedTool) === -1) {
    requestedTool = 'remove_bg';
  }
  setTool(requestedTool);
  updateButton();
})();

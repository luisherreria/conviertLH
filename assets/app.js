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
  var limits = window.APP || { maxFiles: 1, aiMaxFiles: 3, maxBytes: 8 * 1024 * 1024 };
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

  function fileLimit() {
    if (tool === 'remove_bg' || tool === 'upscale') return limits.aiMaxFiles || 3;
    return 1;
  }

  function singleFileMessage() {
    return tool === 'mockup'
      ? 'Crear mockup usa una sola imagen.'
      : 'Esta herramienta usa una sola imagen.';
  }

  function addFiles(list) {
    var rejected = [];
    if (fileLimit() === 1 && list.length) {
      queue.forEach(function (item) { URL.revokeObjectURL(item.url); });
      queue = [];
      list = [list[0]];
    }
    Array.prototype.forEach.call(list, function (file) {
      if (queue.length >= fileLimit()) {
        rejected.push(fileLimit() === 1 ? singleFileMessage() : 'Máximo ' + fileLimit() + ' imágenes por vez.');
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
    var local = next === 'convert' || next === 'crop' || next === 'flip' || next === 'halftone' || next === 'mockup';
    document.getElementById('panel-keep').hidden = next !== 'remove_bg';
    document.getElementById('panel-scale').hidden = next !== 'upscale';
    document.getElementById('panel-format').hidden = next !== 'convert';
    document.getElementById('panel-bg-mode').hidden = next !== 'remove_bg';
    document.getElementById('panel-color').hidden = next === 'upscale' || next === 'crop' || next === 'flip' || next === 'halftone' || next === 'mockup';
    document.getElementById('panel-flip').hidden = next !== 'flip';
    document.getElementById('panel-halftone').hidden = next !== 'halftone';
    document.getElementById('panel-mockup').hidden = next !== 'mockup';
    syncRibbon();
    document.getElementById('panel-crop-note').hidden = next !== 'crop';
    document.getElementById('panel-crop-option').hidden = next === 'crop' || next === 'flip' || next === 'halftone' || next === 'mockup';
    document.getElementById('crop-hint').hidden = next === 'crop' || next === 'flip' || next === 'halftone' || next === 'mockup';
    document.getElementById('fill-hint').hidden = next !== 'convert';
    document.getElementById('ai-hint').hidden = local;
    var input = document.getElementById('file-input');
    var note = document.getElementById('drop-note');
    var mb = Math.round(limits.maxBytes / (1024 * 1024));
    var cap = fileLimit();
    if (cap === 1) {
      input.removeAttribute('multiple');
      note.textContent = 'PNG, JPG, WEBP, GIF o BMP · una imagen · ' + mb + ' MB';
    } else {
      input.setAttribute('multiple', 'multiple');
      note.textContent = 'PNG, JPG, WEBP, GIF o BMP · hasta ' + cap + ' archivos · ' + mb + ' MB cada uno';
    }
    if (queue.length > cap) {
      queue.splice(cap).forEach(function (item) { URL.revokeObjectURL(item.url); });
      renderQueue();
      updateButton();
      showError(cap === 1
        ? singleFileMessage() + ' Saqué las demás.'
        : 'Esta herramienta acepta hasta ' + cap + ' imágenes, de ' + mb + ' MB cada una. Saqué las que sobraban.');
    } else {
      clearError();
    }
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
      var zipUrl = data.zip.blob
        ? URL.createObjectURL(data.zip.blob)
        : b64ToUrl(data.zip.base64, data.zip.mime);
      if (data.zip.blob) resultUrls.push(zipUrl);
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

  var halftoneCap = document.getElementById('halftone-cap');
  var halftoneCapOut = document.getElementById('halftone-cap-out');
  halftoneCap.addEventListener('input', function () {
    halftoneCapOut.textContent = halftoneCap.value + '%';
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

  function jobData(item) {
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
    var halftone = document.querySelector('input[name="halftone"]:checked');
    data.set('halftone', halftone ? halftone.value : 'photo');
    var lpi = document.querySelector('input[name="lpi"]:checked');
    data.set('lpi', lpi ? lpi.value : '45');
    data.set('halftone_cap', document.getElementById('halftone-cap').value);
    var ink = document.querySelector('input[name="halftone_ink"]:checked');
    data.set('halftone_ink', ink ? ink.value : 'black');
    data.set('mockup', document.getElementById('mockup').value);
    var ribbon = document.querySelector('input[name="ribbon"]:checked');
    data.set('ribbon', ribbon ? ribbon.value : 'azul');
    if (item) data.append('files[]', item.file, item.file.name);
    else queue.forEach(function (entry) { data.append('files[]', entry.file, entry.file.name); });
    return data;
  }

  function readPayload(response) {
    return response.text().then(function (text) {
      var payload;
      try { payload = JSON.parse(text); }
      catch (err) { throw new Error('El servidor no respondió bien.'); }
      if (!payload.ok) throw new Error(payload.error || 'No se pudo procesar.');
      return payload;
    });
  }

  var CRC_TABLE = (function () {
    var table = new Uint32Array(256);
    var n;
    var k;
    var c;
    for (n = 0; n < 256; n++) {
      c = n;
      for (k = 0; k < 8; k++) c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1);
      table[n] = c >>> 0;
    }
    return table;
  }());

  function crc32(bytes) {
    var c = 0xffffffff;
    var i;
    for (i = 0; i < bytes.length; i++) c = CRC_TABLE[(c ^ bytes[i]) & 0xff] ^ (c >>> 8);
    return (c ^ 0xffffffff) >>> 0;
  }

  function zipName(name, used) {
    var base = name || 'imagen.png';
    if (!used[base]) {
      used[base] = true;
      return base;
    }
    var dot = base.lastIndexOf('.');
    var stem = dot > 0 ? base.slice(0, dot) : base;
    var ext = dot > 0 ? base.slice(dot) : '';
    var n = 2;
    var next = stem + '-' + n + ext;
    while (used[next]) {
      n += 1;
      next = stem + '-' + n + ext;
    }
    used[next] = true;
    return next;
  }

  function storeZip(files) {
    var parts = [];
    var central = [];
    var offset = 0;
    var used = {};
    files.forEach(function (file) {
      var raw = atob(file.base64);
      var data = new Uint8Array(raw.length);
      var i;
      for (i = 0; i < raw.length; i++) data[i] = raw.charCodeAt(i) & 0xff;
      var nameBytes = new TextEncoder().encode(zipName(file.name, used));
      var crc = crc32(data);
      var local = new Uint8Array(30 + nameBytes.length);
      var view = new DataView(local.buffer);
      view.setUint32(0, 0x04034b50, true);
      view.setUint16(4, 20, true);
      view.setUint16(6, 0x0800, true);
      view.setUint16(8, 0, true);
      view.setUint16(10, 0, true);
      view.setUint16(12, 0, true);
      view.setUint32(14, crc, true);
      view.setUint32(18, data.length, true);
      view.setUint32(22, data.length, true);
      view.setUint16(26, nameBytes.length, true);
      view.setUint16(28, 0, true);
      local.set(nameBytes, 30);
      parts.push(local, data);
      var cen = new Uint8Array(46 + nameBytes.length);
      var cv = new DataView(cen.buffer);
      cv.setUint32(0, 0x02014b50, true);
      cv.setUint16(4, 20, true);
      cv.setUint16(6, 20, true);
      cv.setUint16(8, 0x0800, true);
      cv.setUint16(10, 0, true);
      cv.setUint16(12, 0, true);
      cv.setUint16(14, 0, true);
      cv.setUint32(16, crc, true);
      cv.setUint32(20, data.length, true);
      cv.setUint32(24, data.length, true);
      cv.setUint16(28, nameBytes.length, true);
      cv.setUint16(30, 0, true);
      cv.setUint16(32, 0, true);
      cv.setUint16(34, 0, true);
      cv.setUint16(36, 0, true);
      cv.setUint32(38, 0, true);
      cv.setUint32(42, offset, true);
      cen.set(nameBytes, 46);
      central.push(cen);
      offset += local.length + data.length;
    });
    var centralSize = 0;
    central.forEach(function (part) { centralSize += part.length; });
    var end = new Uint8Array(22);
    var ev = new DataView(end.buffer);
    ev.setUint32(0, 0x06054b50, true);
    ev.setUint16(4, 0, true);
    ev.setUint16(6, 0, true);
    ev.setUint16(8, files.length, true);
    ev.setUint16(10, files.length, true);
    ev.setUint32(12, centralSize, true);
    ev.setUint32(16, offset, true);
    ev.setUint16(20, 0, true);
    return new Blob(parts.concat(central, [end]), { type: 'application/zip' });
  }

  function busyLine(index, total) {
    if (tool === 'convert') return 'Convirtiendo en el servidor…';
    if (tool === 'crop') return 'Recortando en el servidor…';
    if (tool === 'flip') return 'Espejando en el servidor…';
    if (tool === 'halftone') return 'Armando el semitono en el servidor…';
    if (tool === 'mockup') return 'Armando el mockup en el servidor…';
    var step = total > 1 ? 'Imagen ' + (index + 1) + ' de ' + total + '. ' : '';
    if (tool === 'remove_bg') {
      return limits.aiRemote
        ? step + 'Quitando el fondo en Replicate…'
        : step + 'Quitando el fondo en esta PC…';
    }
    return limits.aiRemote
      ? step + 'Escalando en Replicate…'
      : step + 'Escalando en esta PC…';
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!queue.length) {
      showError('Subí al menos una imagen.');
      return;
    }

    var submitted = queue.slice();
    var split = (tool === 'remove_bg' || tool === 'upscale') && submitted.length > 1;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Procesando…';
    feedback.style.color = 'var(--muted)';
    feedback.textContent = busyLine(0, submitted.length);

    var task;
    if (!split) {
      task = fetch('api/process.php', { method: 'POST', body: jobData(null) })
        .then(readPayload)
        .then(function (payload) {
          feedback.textContent = '';
          renderResults(payload, submitted);
        });
    } else {
      var collected = { files: [], notices: [], errors: [] };
      var chain = Promise.resolve();
      submitted.forEach(function (item, index) {
        chain = chain.then(function () {
          feedback.textContent = busyLine(index, submitted.length);
          return fetch('api/process.php', { method: 'POST', body: jobData(item) })
            .then(readPayload)
            .then(function (payload) {
              payload.files.forEach(function (file) {
                file.source_index = index;
                collected.files.push(file);
              });
              (payload.notices || []).forEach(function (text) {
                if (collected.notices.indexOf(text) === -1) collected.notices.push(text);
              });
            })
            .catch(function (err) {
              collected.errors.push((item.file.name || 'Imagen') + ': ' + (err.message || 'No se pudo procesar.'));
            });
        });
      });
      task = chain.then(function () {
        if (!collected.files.length) {
          throw new Error(collected.errors.join(' ') || 'No se pudo procesar.');
        }
        if (collected.errors.length) {
          collected.notices.push('Algunas imágenes no se procesaron: ' + collected.errors.join(' '));
        }
        var payload = { files: collected.files, notices: collected.notices };
        if (collected.files.length > 1) {
          payload.zip = {
            name: 'subliedit-resultado.zip',
            mime: 'application/zip',
            blob: storeZip(collected.files)
          };
        }
        feedback.textContent = '';
        renderResults(payload, submitted);
      });
    }

    task.catch(function (err) {
      feedback.style.color = '';
      showError(err.message || 'No se pudo hablar con el servidor.');
    }).then(function () {
      updateButton();
    });
  });

  function syncRibbon() {
    var panel = document.getElementById('panel-ribbon');
    if (!panel) return;
    var current = document.getElementById('mockup');
    panel.hidden = tool !== 'mockup' || !current || current.value !== 'medalla';
  }

  var mockupPick = document.querySelector('.mockup-pick');
  if (mockupPick) {
    var mockupValue = document.getElementById('mockup');
    var mockupButton = mockupPick.querySelector('.mockup-current');
    var mockupMenu = mockupPick.querySelector('.mockup-menu');
    var mockupPreview = mockupPick.querySelector('.mockup-preview');
    var mockupChoices = mockupPick.querySelectorAll('.mockup-options button');

    function showMockup(choice) {
      mockupChoices.forEach(function (item) { item.classList.remove('is-on'); });
      choice.classList.add('is-on');
      mockupPreview.src = choice.getAttribute('data-src');
      mockupPreview.alt = choice.textContent;
    }

    function closeMockup() {
      mockupMenu.hidden = true;
      mockupButton.setAttribute('aria-expanded', 'false');
    }

    mockupButton.addEventListener('click', function () {
      var open = mockupMenu.hidden;
      mockupMenu.hidden = !open;
      mockupButton.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        var current = mockupPick.querySelector('[data-value="' + mockupValue.value + '"]');
        if (current) showMockup(current);
      }
    });

    mockupChoices.forEach(function (choice) {
      choice.addEventListener('mouseenter', function () { showMockup(choice); });
      choice.addEventListener('focus', function () { showMockup(choice); });
      choice.addEventListener('click', function () {
        mockupValue.value = choice.getAttribute('data-value');
        mockupButton.querySelector('span').textContent = choice.textContent;
        mockupChoices.forEach(function (item) {
          item.setAttribute('aria-selected', item === choice ? 'true' : 'false');
        });
        syncRibbon();
        closeMockup();
      });
    });

    document.addEventListener('click', function (event) {
      if (!mockupPick.contains(event.target)) closeMockup();
    });

    mockupButton.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        mockupMenu.hidden = false;
        mockupButton.setAttribute('aria-expanded', 'true');
        mockupChoices[0].focus();
      }
    });
  }

  var requestedTool = new URLSearchParams(window.location.search).get('tool');
  if (!requestedTool || ['remove_bg', 'upscale', 'convert', 'crop', 'flip', 'halftone', 'mockup'].indexOf(requestedTool) === -1) {
    requestedTool = 'remove_bg';
  }
  setTool(requestedTool);
  updateButton();
})();

/* ============================================================
   anhaenge.js — Text/Code Snippet Anhänge
   ============================================================ */

// SPRACHEN ist in config.js definiert

function sprachOptionen(aktiv='plaintext') {
  return SPRACHEN.map(s =>
    `<option value="${s.val}" ${aktiv===s.val?'selected':''}>${s.label}</option>`
  ).join('');
}

function formAnhang(typ, refId) {
  return `
    <div class="mb-3"><label class="form-label">Titel *</label>
      <input class="form-control" id="anh-titel" placeholder="z.B. Rollen-Konfiguration"></div>
    <div class="mb-3"><label class="form-label">Sprache</label>
      <select class="form-select" id="anh-sprache">${sprachOptionen()}</select></div>
    <div class="mb-1"><label class="form-label">Inhalt *</label></div>
    <textarea class="form-control font-mono" id="anh-inhalt" rows="12"
      style="font-family:monospace;font-size:.82rem;resize:vertical"
      placeholder="Code oder Text hier einfügen …"></textarea>`;
}

async function speichernAnhang(typ, refId) {
  const titel   = document.getElementById('anh-titel').value.trim();
  const sprache = document.getElementById('anh-sprache').value;
  const inhalt  = document.getElementById('anh-inhalt').value.trim();
  if (!titel)  return notify('Bitte Titel eingeben','error');
  if (!inhalt) return notify('Bitte Inhalt eingeben','error');
  await api('anhang_erstellen', { typ, referenz_id: refId, titel, sprache, inhalt });
  notify('Anhang gespeichert');
  // Zurück zum Eintrag-Detail oder Schritt
  if (typ === 'eintrag') openEintragDetail(refId);
  else schliesseModal();
}

async function loeschenAnhang(id, typ, refId) {
  if (!confirm('Anhang löschen?')) return;
  await api('anhang_loeschen', { id });
  notify('Anhang gelöscht');
  if (typ === 'eintrag') openEintragDetail(refId);
  else schliesseModal();
}

function renderAnhaenge(anhaenge, typ, refId) {
  if (!anhaenge.length) return '';
  return `
    <div class="anhaenge-wrap mt-3">
      <div class="anh-section-title"><i class="bi bi-paperclip me-1"></i>Anhänge</div>
      ${anhaenge.map(a => `
        <div class="anh-card">
          <div class="anh-card-head">
            <span class="anh-titel">${esc(a.titel)}</span>
            <span class="anh-lang-badge">${SPRACHEN.find(s=>s.val===a.sprache)?.label||a.sprache}</span>
            ${a.erstellt_von_name?`<span class="anh-autor"><i class="bi bi-person-fill"></i> ${esc(vorname(a.erstellt_von_name))}</span>`:''}
            <div class="ms-auto d-flex gap-1">
              <button class="btn-copy" onclick="kopiereAnhang(${a.id})" title="Kopieren">
                <i class="bi bi-clipboard"></i>
              </button>
              ${hatRecht('verwalten')?`<button class="btn-copy text-danger-soft" onclick="loeschenAnhang(${a.id},'${typ}',${refId})" title="Löschen">
                <i class="bi bi-trash"></i>
              </button>`:''}
            </div>
          </div>
          <pre class="anh-code" id="anh-code-${a.id}"><code>${esc(a.inhalt)}</code></pre>
        </div>`).join('')}
    </div>`;
}

function kopiereAnhang(id) {
  const el = document.getElementById(`anh-code-${id}`);
  if (!el) return;
  navigator.clipboard.writeText(el.innerText).then(() => notify('In Zwischenablage kopiert'));
}
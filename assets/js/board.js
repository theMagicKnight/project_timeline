/* ============================================================
   board.js — Eigenständiges Diskussions-Board
   Reddit-Style Baumstruktur, projektübergreifend
   ============================================================ */

// ============================================================
//  Board-Übersicht (eigenständig, kein Projekt nötig)
// ============================================================
async function renderBoard() {
  const content = document.getElementById('content');
  content.innerHTML = `<div class="board-loading"><i class="bi bi-arrow-repeat spin me-2"></i>Lade Board…</div>`;

  // Projekt-Filter: aktives Projekt oder alle
  const pid  = aktivProjekt?.id ?? null;
  const res  = await api('board_themen_liste', null, pid ? `&id=${pid}` : '');
  const themen = res.themen || [];

  // aktivesRecht setzen
  if (res.mein_recht && !aktivesRecht) aktivesRecht = IST_ADMIN ? 'admin' : res.mein_recht;

  // Sidebar-Badge aktualisieren
  boardBadgeSetzen(res.ungelesen || 0);

  // Projekte für Filter-Dropdown laden
  const projekte = await api('projekte_liste');

  let html = `<div class="board-wrap">
    <div class="board-toolbar">
      <div class="board-filter">
        <select class="board-filter-select" onchange="boardFilterProjekt(this.value)">
          <option value="">🌐 Alle Themen</option>
          ${projekte.map(p => `<option value="${p.id}" ${pid==p.id?'selected':''}>${esc(p.name)}</option>`).join('')}
        </select>
      </div>
      ${hatRecht('schreiben','board') ? `
      <button class="btn btn-accent btn-sm" onclick="boardNeuesThema()">
        <i class="bi bi-plus-lg me-1"></i> Neues Thema
      </button>` : ''}
    </div>`;

  if (!themen.length) {
    html += `<div class="empty-state"><span class="icon">💬</span>Noch keine Themen. Starte die erste Diskussion!</div>`;
  } else {
    html += `<div class="board-liste">`;
    themen.forEach(t => {
      const datum  = fmtDate(t.erstellt_am?.slice(0,10));
      const autor  = t.erstellt_von_name ? vorname(t.erstellt_von_name) : '?';
      const hatEnt = t.entscheidung_count > 0;
      html += `
        <div class="board-thema-item ${hatEnt?'hat-entscheidung':''}" onclick="boardThemaOeffnen(${t.id})">
          <div class="board-thema-main">
            <div class="board-thema-titel">
              ${hatEnt?'<i class="bi bi-check-circle-fill me-1" style="color:var(--green)"></i>':'<i class="bi bi-chat-dots me-1" style="color:var(--text3)"></i>'}
              ${esc(t.titel)}
            </div>
            <div class="board-thema-meta">
              <span><i class="bi bi-person-fill me-1"></i>${esc(autor)}</span>
              <span><i class="bi bi-clock me-1"></i>${datum}</span>
              ${t.projekt_name?`<span class="board-projekt-badge" style="background:${t.projekt_farbe}22;color:${t.projekt_farbe}"><i class="bi bi-folder-fill me-1"></i>${esc(t.projekt_name)}</span>`:'<span class="board-frei-badge"><i class="bi bi-globe me-1"></i>Allgemein</span>'}
              ${t.ref_titel?`<span class="board-ref-badge"><i class="bi bi-link-45deg me-1"></i>${esc(t.ref_titel)}</span>`:''}
              ${t.rubrik_name?`<span class="board-rubrik-badge"><i class="bi bi-folder-fill me-1"></i>${esc(t.rubrik_name)}</span>`:''}
            </div>
          </div>
          <div class="board-thema-stats">
            <span class="board-antworten"><i class="bi bi-chat me-1"></i>${t.antwort_count}</span>
            ${t.neu_count > 0 ? `<span class="board-neu-badge">${t.neu_count} neu</span>` : ''}
            ${hatEnt?`<span class="board-entscheidung-badge"><i class="bi bi-check-circle-fill me-1"></i>${t.entscheidung_count}</span>`:''}
          </div>
        </div>`;
    });
    html += `</div>`;
  }
  html += `</div>`;
  content.innerHTML = html;
}

function boardFilterProjekt(pid) {
  // Aktives Projekt wechseln oder deaktivieren
  if (pid) {
    api('projekt_detail', null, `&id=${pid}`).then(d => {
      aktivProjekt       = d.projekt;
      aktivesRecht       = IST_ADMIN ? 'admin' : d.mein_recht;
      aktivesBoardRecht  = IST_ADMIN ? 'verwalten' : d.alle_rechte?.board_recht  ?? aktivesRecht;
      aktivesRubrikRecht = IST_ADMIN ? 'verwalten' : d.alle_rechte?.rubrik_recht ?? aktivesRecht;
      renderBoard();
    });
  } else {
    aktivProjekt       = null;
    // Projektloses Board: Admin = verwalten, normale Benutzer = lesen
    aktivesRecht       = IST_ADMIN ? 'admin'     : 'lesen';
    aktivesBoardRecht  = IST_ADMIN ? 'verwalten' : 'lesen';
    aktivesRubrikRecht = IST_ADMIN ? 'verwalten' : 'lesen';
    renderBoard();
  }
}

// ============================================================
//  Thema öffnen — Detailansicht
// ============================================================
async function boardThemaOeffnen(themaId) {
  const content = document.getElementById('content');
  content.innerHTML = `<div class="board-loading"><i class="bi bi-arrow-repeat spin me-2"></i>Lade Diskussion…</div>`;

  const data = await api('board_thema_detail', null, `&id=${themaId}`);
  const { thema, kommentare } = data;

  // Recht setzen
  if (data.mein_recht) aktivesRecht = IST_ADMIN ? 'admin' : data.mein_recht;

  // Sidebar-Badge aktualisieren (Thema wurde als gelesen markiert)
  if (typeof data.ungelesen_gesamt !== 'undefined') {
    boardBadgeSetzen(data.ungelesen_gesamt);
  }

  const baum        = baueBaum(kommentare);
  const hatEnt      = kommentare.some(k => k.ist_entscheidung == 1);
  const projekte    = await api('projekte_liste');

  let html = `<div class="board-detail-wrap">
    <div class="board-detail-header">
      <button class="btn-zurueck" onclick="renderBoard()">
        <i class="bi bi-arrow-left me-1"></i> Board
      </button>
      <div class="board-detail-titel">${esc(thema.titel)}</div>
    </div>`;

  // Verknüpfungen
  if (thema.projekt_name || thema.ref_titel || thema.rubrik_name) {
    html += `<div class="board-verknuepfungen">`;
    if (thema.projekt_name) html += `<span class="board-projekt-badge gross" style="background:${thema.projekt_farbe}22;color:${thema.projekt_farbe}"><i class="bi bi-folder-fill me-1"></i>${esc(thema.projekt_name)}</span>`;
    if (thema.ref_titel)    html += `<span class="board-ref-badge gross"><i class="bi bi-link-45deg me-1"></i>Verknüpft: ${esc(thema.ref_titel)}</span>`;
    if (thema.rubrik_name)  html += `<span class="board-rubrik-badge gross"><i class="bi bi-folder me-1"></i>Rubrik: ${esc(thema.rubrik_name)}</span>`;
    html += `</div>`;
  }

  // Projekt zuweisen falls noch keins verknüpft
  if (!thema.projekt_id && projekte.length > 0) {
    html += `<div class="board-projekt-zuweisen-box">
      <i class="bi bi-globe me-2" style="color:var(--text3)"></i>
      <span>Kein Projekt verknüpft</span>
      <select class="board-filter-select ms-auto" id="projekt-zuweisen-select" style="max-width:180px">
        <option value="">Projekt zuweisen…</option>
        ${projekte.map(p=>`<option value="${p.id}">${esc(p.name)}</option>`).join('')}
      </select>
      <button class="btn btn-outline-secondary btn-sm" onclick="boardProjektZuweisen(${themaId})">
        <i class="bi bi-link-45deg"></i>
      </button>
    </div>`;
  }

  // Rubrik aus Entscheidung erstellen
  if (hatEnt && !thema.rubrik_id && thema.projekt_id && hatRecht('schreiben','board')) {
    html += `<div class="board-rubrik-erstellen-box">
      <i class="bi bi-check-circle-fill me-2" style="color:var(--green)"></i>
      <span>Entscheidung gefallen — Rubrik erstellen?</span>
      <button class="btn btn-accent btn-sm ms-auto" onclick="boardRubrikErstellen(${themaId},'${esc(thema.titel).replace(/'/g,"\\'")}')">
        <i class="bi bi-folder-plus me-1"></i> Rubrik erstellen
      </button>
    </div>`;
  }

  // Kommentar-Baum
  html += `<div class="board-kommentar-baum" id="board-baum-${themaId}">
    ${!baum.length ? `<div class="kommentar-leer">Noch keine Beiträge. Sei der Erste!</div>` : renderBaumKnoten(baum, themaId, 0)}
  </div>`;

  // Top-Level Formular — nur für Benutzer mit Board-Schreibrecht
  if (hatRecht('schreiben','board')) {
    html += `<div class="board-antwort-form" id="board-form-top-${themaId}">
      <div class="kommentar-input-wrap">
        <div class="user-avatar-sm">${(AKTUELLER_BENUTZER.name||'?')[0].toUpperCase()}</div>
        <textarea class="kommentar-input" id="board-input-top-${themaId}"
          placeholder="Dein Beitrag… (Enter = Senden, Shift+Enter = Zeilenumbruch)"
          rows="3" onkeydown="boardKommentarKeyDown(event,${themaId},null)"></textarea>
      </div>
      <div class="d-flex justify-content-end mt-1">
        <button class="btn btn-accent btn-sm" onclick="boardKommentarSenden(${themaId},null)">
          <i class="bi bi-send me-1"></i> Beitrag senden
        </button>
      </div>
    </div>`;
  }

  html += `</div>`;
  content.innerHTML = html;
}

// ============================================================
//  Baum aufbauen (flat → tree)
// ============================================================
function baueBaum(kommentare) {
  const map = {};
  const roots = [];
  kommentare.forEach(k => { map[k.id] = { ...k, kinder: [] }; });
  kommentare.forEach(k => {
    if (k.eltern_id && map[k.eltern_id]) map[k.eltern_id].kinder.push(map[k.id]);
    else roots.push(map[k.id]);
  });
  return roots;
}

// ============================================================
//  Baum rendern (rekursiv, Reddit-Style)
// ============================================================
function renderBaumKnoten(knoten, themaId, tiefe) {
  if (!knoten.length) return '';
  return knoten.map(k => {
    const datum = new Date(k.erstellt_am).toLocaleDateString('de-DE',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
    const autorVorname = k.autor_name ? vorname(k.autor_name) : '?';
    const initial = autorVorname[0].toUpperCase();
    const istEnt  = k.ist_entscheidung == 1;
    return `
      <div class="rd-knoten ${istEnt?'rd-entscheidung':''}" id="bknoten-${k.id}">
        <div class="rd-links">
          <div class="rd-avatar">${initial}</div>
          ${k.kinder.length?`<div class="rd-linie" onclick="rdEinklappen(${k.id})"></div>`:'<div class="rd-linie-leer"></div>'}
        </div>
        <div class="rd-rechts">
          <div class="rd-meta">
            <span class="rd-autor">${esc(autorVorname)}</span>
            <span class="rd-datum">${datum}</span>
            ${istEnt?`<span class="rd-entscheidung-badge"><i class="bi bi-check-circle-fill me-1"></i>Entscheidung</span>`:''}
            ${hatRecht('verwalten','board')?`
            <button class="rd-entscheidung-btn ${istEnt?'aktiv':''}"
              onclick="boardToggleEntscheidung(${k.id},${themaId})"
              title="${istEnt?'Entscheidung aufheben':'Als Entscheidung markieren'}">
              <i class="bi bi-check-circle${istEnt?'-fill':''}"></i>
            </button>`:''}
          </div>
          <div class="rd-text">${esc(k.inhalt).replace(/\n/g,'<br>')}</div>
          <div class="rd-aktionen">
            ${REAKTION_TYPEN.map(r=>`
              <button class="rd-reaktion ${k.meine_reaktion===r?'aktiv':''}"
                onclick="boardReaktion(${k.id},'${r}',${themaId})">
                ${r} <span>${k['r_'+reaktionKey(r)]||0}</span>
              </button>`).join('')}
            <button class="rd-antworten" onclick="rdAntwortToggle(${k.id},${themaId})">
              <i class="bi bi-reply me-1"></i>Antworten
            </button>
          </div>
          <div class="rd-form" id="rd-form-${k.id}" style="display:none">
            <textarea class="rd-textarea" id="rd-input-${k.id}"
              placeholder="Antwort auf ${esc(autorVorname)}…" rows="2"
              onkeydown="boardKommentarKeyDown(event,${themaId},${k.id})"></textarea>
            <div class="rd-form-actions">
              <button class="rd-btn-cancel" onclick="rdAntwortToggle(${k.id},${themaId})">Abbrechen</button>
              <button class="rd-btn-send" onclick="boardKommentarSenden(${themaId},${k.id})">
                <i class="bi bi-reply me-1"></i>Antworten
              </button>
            </div>
          </div>
          <div class="rd-kinder" id="rd-kinder-${k.id}">
            ${k.kinder.length?renderBaumKnoten(k.kinder,themaId,tiefe+1):''}
          </div>
        </div>
      </div>`;
  }).join('');
}

// ============================================================
//  Interaktionen
// ============================================================
function rdAntwortToggle(kommentarId, themaId) {
  const form = document.getElementById(`rd-form-${kommentarId}`);
  if (!form) return;
  const vis = form.style.display !== 'none';
  form.style.display = vis ? 'none' : 'block';
  if (!vis) document.getElementById(`rd-input-${kommentarId}`)?.focus();
}

function boardKommentarKeyDown(e, themaId, elternId) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    boardKommentarSenden(themaId, elternId);
  }
}

async function boardKommentarSenden(themaId, elternId) {
  const inputId = elternId ? `rd-input-${elternId}` : `board-input-top-${themaId}`;
  const ta = document.getElementById(inputId);
  if (!ta) { notify('Eingabefeld nicht gefunden', 'error'); return; }
  const inhalt = ta.value.trim();
  if (!inhalt) return;
  ta.disabled = true;
  await api('board_kommentar_erstellen', { thema_id: themaId, eltern_id: elternId, inhalt });
  ta.value = ''; ta.disabled = false;
  if (elternId) {
    const form = document.getElementById(`rd-form-${elternId}`);
    if (form) form.style.display = 'none';
  }
  notify('Beitrag gespeichert');
  boardThemaOeffnen(themaId);
}

async function boardReaktion(kommentarId, typ, themaId) {
  await api('reaktion_setzen', { kommentar_id: kommentarId, typ });
  boardThemaOeffnen(themaId);
}

async function boardToggleEntscheidung(kommentarId, themaId) {
  await api('board_entscheidung', { id: kommentarId });
  boardThemaOeffnen(themaId);
}

function rdEinklappen(kommentarId) {
  const kinder = document.getElementById(`rd-kinder-${kommentarId}`);
  if (!kinder) return;
  kinder.style.display = kinder.style.display !== 'none' ? 'none' : 'block';
}

// ============================================================
//  Neues Thema Modal
// ============================================================
function boardNeuesThema() {
  const projekte_cache = [];
  api('projekte_liste').then(projekte => {
    const T = document.getElementById('modal-title');
    const B = document.getElementById('modal-body');
    const F = document.getElementById('modal-footer');
    setModalSize('normal');
    T.textContent = 'Neues Board-Thema';
    B.innerHTML = `
      <div class="mb-3">
        <label class="form-label">Titel *</label>
        <input class="form-control" id="board-neues-titel" placeholder="Worum geht es?" autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Projekt zuweisen <span style="color:var(--text3)">(optional)</span></label>
        <select class="form-select" id="board-neues-projekt">
          <option value="">Kein Projekt — allgemeines Thema</option>
          ${projekte.map(p=>`<option value="${p.id}" ${aktivProjekt?.id==p.id?'selected':''}>${esc(p.name)}</option>`).join('')}
        </select>
      </div>
      <div class="mb-1">
        <label class="form-label">Erster Beitrag <span style="color:var(--text3)">(optional)</span></label>
        <textarea class="form-control" id="board-neuer-inhalt" rows="4"
          placeholder="Beschreibe das Thema, stelle eine Frage…"></textarea>
      </div>`;
    F.innerHTML = `
      <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-accent" onclick="boardNeuesThemaSpeichern()">
        <i class="bi bi-plus-lg me-1"></i> Thema erstellen
      </button>`;
    oeffneModal();
  });
}

async function boardNeuesThemaSpeichern() {
  const titel    = document.getElementById('board-neues-titel').value.trim();
  const inhalt   = document.getElementById('board-neuer-inhalt').value.trim();
  const projektId = document.getElementById('board-neues-projekt').value || null;
  if (!titel) return notify('Bitte einen Titel eingeben', 'error');
  const res = await api('board_thema_erstellen', { projekt_id: projektId, titel });
  if (inhalt) await api('board_kommentar_erstellen', { thema_id: res.id, eltern_id: null, inhalt });
  schliesseModal();
  notify('Thema erstellt');
  boardThemaOeffnen(res.id);
}

// ============================================================
//  Projekt zuweisen
// ============================================================
async function boardProjektZuweisen(themaId) {
  const pid = document.getElementById('projekt-zuweisen-select')?.value;
  if (!pid) return notify('Bitte ein Projekt wählen', 'error');
  await api('board_projekt_zuweisen', { thema_id: themaId, projekt_id: pid });
  notify('Projekt verknüpft!');
  boardThemaOeffnen(themaId);
}

// ============================================================
//  Rubrik aus Entscheidung erstellen
// ============================================================
async function boardRubrikErstellen(themaId, vorschlag) {
  const T = document.getElementById('modal-title');
  const B = document.getElementById('modal-body');
  const F = document.getElementById('modal-footer');
  setModalSize('normal');
  T.textContent = 'Rubrik aus Entscheidung erstellen';
  B.innerHTML = `
    <div class="status-box status-ok mb-3" style="background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);color:var(--green);border-radius:8px;padding:10px 14px;font-size:.85rem;display:flex;align-items:center;gap:8px">
      <i class="bi bi-check-circle-fill"></i> Die Rubrik wird mit diesem Board-Thema verknüpft.
    </div>
    <div class="mb-3">
      <label class="form-label">Rubrik-Name *</label>
      <input class="form-control" id="rubrik-aus-board-name" value="${esc(vorschlag)}">
    </div>`;
  F.innerHTML = `
    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
    <button class="btn btn-accent" onclick="boardRubrikErstellenSpeichern(${themaId})">
      <i class="bi bi-folder-plus me-1"></i> Rubrik anlegen
    </button>`;
  oeffneModal();
}

async function boardRubrikErstellenSpeichern(themaId) {
  const name = document.getElementById('rubrik-aus-board-name').value.trim();
  if (!name) return notify('Bitte einen Namen eingeben', 'error');
  const res = await api('board_rubrik_erstellen', { thema_id: themaId, name });
  schliesseModal();
  notify('Rubrik angelegt und verknüpft!');
  if (aktivProjekt) await ladeProjekt(aktivProjekt.id);
  boardThemaOeffnen(themaId);
}

// ============================================================
//  Von Eintrag/Schritt → Board-Thema erstellen oder öffnen
// ============================================================
async function boardThemaVonRef(refTyp, refId, titel) {
  const pid = aktivProjekt?.id ?? null;
  const res = await api('board_thema_von_ref', { ref_typ: refTyp, ref_id: refId, projekt_id: pid, titel });
  if (res.existed) notify('Board-Thema bereits vorhanden — öffne es');
  else notify('Board-Thema erstellt und verknüpft!');
  schliesseModal();
  // Zum Board-Tab wechseln
  document.querySelectorAll('#projektTabs .nav-link').forEach(t => t.classList.remove('active'));
  document.querySelector('#projektTabs [data-tab="board"]')?.classList.add('active');
  aktiverTab = 'board';
  boardThemaOeffnen(res.id);
}

// ============================================================
//  Hilfsfunktionen
// ============================================================
function reaktionKey(typ) {
  return {'👍':'gut','👎':'nein','❤️':'herz','🤔':'denk'}[typ] || 'gut';
}

function boardBadgeSetzen(anzahl) {
  // Badge im Board-Tab (Navigation)
  const tabs = document.querySelectorAll('[data-tab="board"]');
  tabs.forEach(tab => {
    let badge = tab.querySelector('.board-tab-badge');
    if (anzahl > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'board-tab-badge';
        tab.appendChild(badge);
      }
      badge.textContent = anzahl > 99 ? '99+' : anzahl;
    } else if (badge) {
      badge.remove();
    }
  });
}
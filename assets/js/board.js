/* ============================================================
   board.js — Diskussions-Board mit Baumstruktur
   ============================================================ */

// ============================================================
//  Board-Übersicht
// ============================================================
async function renderBoard() {
  const content = document.getElementById('content');
  content.innerHTML = `<div class="board-loading"><i class="bi bi-arrow-repeat spin me-2"></i>Lade Board…</div>`;

  const themen = await api('board_themen_liste', null, `&id=${aktivProjekt.id}`);

  let html = `
    <div class="board-wrap">
      <div class="board-toolbar">
        ${hatRecht('schreiben') ? `
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
        <div class="board-thema-item ${hatEnt ? 'hat-entscheidung' : ''}" onclick="boardThemaOeffnen(${t.id})">
          <div class="board-thema-main">
            <div class="board-thema-titel">
              ${hatEnt ? '<i class="bi bi-check-circle-fill text-success me-1"></i>' : '<i class="bi bi-chat-dots me-1" style="color:var(--text3)"></i>'}
              ${esc(t.titel)}
            </div>
            <div class="board-thema-meta">
              <span><i class="bi bi-person-fill me-1"></i>${esc(autor)}</span>
              <span><i class="bi bi-clock me-1"></i>${datum}</span>
              ${t.ref_titel ? `<span class="board-ref-badge"><i class="bi bi-link-45deg me-1"></i>${esc(t.ref_titel)}</span>` : ''}
              ${t.rubrik_name ? `<span class="board-rubrik-badge"><i class="bi bi-folder-fill me-1"></i>${esc(t.rubrik_name)}</span>` : ''}
            </div>
          </div>
          <div class="board-thema-stats">
            <span class="board-antworten"><i class="bi bi-chat me-1"></i>${t.antwort_count}</span>
            ${hatEnt ? `<span class="board-entscheidung-badge"><i class="bi bi-check-circle-fill me-1"></i>${t.entscheidung_count}</span>` : ''}
          </div>
        </div>`;
    });
    html += `</div>`;
  }

  html += `</div>`;
  content.innerHTML = html;
}

// ============================================================
//  Thema öffnen — Detailansicht mit Baumstruktur
// ============================================================
async function boardThemaOeffnen(themaId) {
  const content = document.getElementById('content');
  content.innerHTML = `<div class="board-loading"><i class="bi bi-arrow-repeat spin me-2"></i>Lade Diskussion…</div>`;

  const data = await api('board_thema_detail', null, `&id=${themaId}`);
  const { thema, kommentare } = data;

  // Baum aufbauen
  const baum = baueBaum(kommentare);
  const hatEntscheidung = kommentare.some(k => k.ist_entscheidung == 1);

  let html = `
    <div class="board-detail-wrap">
      <div class="board-detail-header">
        <button class="btn-zurueck" onclick="renderBoard()">
          <i class="bi bi-arrow-left me-1"></i> Board
        </button>
        <div class="board-detail-titel">${esc(thema.titel)}</div>
      </div>`;

  // Verknüpfungen anzeigen
  if (thema.ref_titel || thema.rubrik_name) {
    html += `<div class="board-verknuepfungen">`;
    if (thema.ref_titel) {
      html += `<span class="board-ref-badge gross">
        <i class="bi bi-link-45deg me-1"></i>
        Verknüpft mit: ${esc(thema.ref_titel)}
      </span>`;
    }
    if (thema.rubrik_name) {
      html += `<span class="board-rubrik-badge gross">
        <i class="bi bi-folder-fill me-1"></i>
        Rubrik: ${esc(thema.rubrik_name)}
      </span>`;
    }
    html += `</div>`;
  }

  // Aus Entscheidung Rubrik erstellen (nur wenn Entscheidung vorhanden und noch keine Rubrik)
  if (hatEntscheidung && !thema.rubrik_id && hatRecht('schreiben')) {
    html += `
      <div class="board-rubrik-erstellen-box">
        <i class="bi bi-check-circle-fill me-2" style="color:var(--green)"></i>
        <span>Entscheidung gefallen — Rubrik erstellen?</span>
        <button class="btn btn-accent btn-sm ms-auto" onclick="boardRubrikErstellen(${themaId},'${esc(thema.titel)}')">
          <i class="bi bi-folder-plus me-1"></i> Rubrik erstellen
        </button>
      </div>`;
  }

  // Kommentar-Baum
  html += `<div class="board-kommentar-baum" id="board-baum-${themaId}">`;
  if (!baum.length) {
    html += `<div class="kommentar-leer">Noch keine Beiträge. Sei der Erste!</div>`;
  } else {
    html += renderBaumKnoten(baum, themaId, 0);
  }
  html += `</div>`;

  // Neuer Top-Level Kommentar
  if (hatRecht('schreiben')) {
    html += `
      <div class="board-antwort-form" id="board-form-top-${themaId}">
        <div class="kommentar-input-wrap">
          <div class="user-avatar-sm">${(AKTUELLER_BENUTZER.name||'?')[0].toUpperCase()}</div>
          <textarea
            class="kommentar-input"
            id="board-input-top-${themaId}"
            placeholder="Dein Beitrag… (Enter = Senden, Shift+Enter = Zeilenumbruch)"
            rows="3"
            onkeydown="boardKommentarKeyDown(event,${themaId},null)"
          ></textarea>
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
    if (k.eltern_id && map[k.eltern_id]) {
      map[k.eltern_id].kinder.push(map[k.id]);
    } else {
      roots.push(map[k.id]);
    }
  });
  return roots;
}

// ============================================================
//  Baum rendern (rekursiv)
// ============================================================
function renderBaumKnoten(knoten, themaId, tiefe) {
  if (!knoten.length) return '';
  const maxEinrueck = 6; // Max-Einrückung in rem
  const einrueck = Math.min(tiefe * 1.5, maxEinrueck);

  return knoten.map(k => {
    const datum = new Date(k.erstellt_am).toLocaleDateString('de-DE', {
      day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'
    });
    const autorVorname = k.autor_name ? vorname(k.autor_name) : '?';
    const initial = autorVorname[0].toUpperCase();
    const istEntscheidung = k.ist_entscheidung == 1;

    return `
      <div class="board-knoten ${istEntscheidung ? 'ist-entscheidung' : ''}"
           style="margin-left:${einrueck}rem"
           id="bknoten-${k.id}">
        ${tiefe > 0 ? `<div class="board-baum-linie"></div>` : ''}
        ${istEntscheidung ? `<div class="entscheidung-banner"><i class="bi bi-check-circle-fill me-1"></i>Als Entscheidung markiert</div>` : ''}
        <div class="kommentar-kopf">
          <div class="user-avatar-sm">${initial}</div>
          <div class="kommentar-meta">
            <span class="kommentar-autor">${esc(autorVorname)}</span>
            <span class="kommentar-datum">${datum}</span>
          </div>
          ${hatRecht('verwalten') ? `
          <button class="kommentar-entscheidung-btn ${istEntscheidung ? 'aktiv' : ''}"
            onclick="boardToggleEntscheidung(${k.id},${themaId})"
            title="${istEntscheidung ? 'Entscheidung aufheben' : 'Als Entscheidung markieren'}">
            <i class="bi bi-check-circle${istEntscheidung ? '-fill' : ''}"></i>
          </button>` : ''}
        </div>
        <div class="kommentar-text">${esc(k.inhalt).replace(/\n/g,'<br>')}</div>
        <div class="reaktionen-zeile">
          ${REAKTION_TYPEN.map(r => `
            <button class="reaktion-btn ${k.meine_reaktion === r ? 'aktiv' : ''}"
              onclick="boardReaktion(${k.id},'${r}',${themaId})">${r}
              <span class="reaktion-count">${k['r_' + reaktionKey(r)] || 0}</span>
            </button>`).join('')}
          ${hatRecht('schreiben') ? `
          <button class="reaktion-btn antworten-btn" onclick="boardAntwortFormToggle(${k.id},${themaId})">
            <i class="bi bi-reply me-1"></i> Antworten
          </button>` : ''}
        </div>

        <!-- Antwort-Formular (versteckt) -->
        ${hatRecht('schreiben') ? `
        <div class="board-antwort-form" id="board-form-${k.id}" style="display:none;margin-top:10px">
          <div class="kommentar-input-wrap">
            <div class="user-avatar-sm">${(AKTUELLER_BENUTZER.name||'?')[0].toUpperCase()}</div>
            <textarea class="kommentar-input"
              id="board-input-${k.id}"
              placeholder="Antwort auf ${esc(autorVorname)}…"
              rows="2"
              onkeydown="boardKommentarKeyDown(event,${themaId},${k.id})"
            ></textarea>
          </div>
          <div class="d-flex gap-2 justify-content-end mt-1">
            <button class="btn btn-outline-secondary btn-sm" onclick="boardAntwortFormToggle(${k.id},${themaId})">Abbrechen</button>
            <button class="btn btn-accent btn-sm" onclick="boardKommentarSenden(${themaId},${k.id})">
              <i class="bi bi-reply me-1"></i> Antworten
            </button>
          </div>
        </div>` : ''}

        <!-- Kinder rekursiv -->
        ${k.kinder.length ? renderBaumKnoten(k.kinder, themaId, tiefe + 1) : ''}
      </div>`;
  }).join('');
}

// ============================================================
//  Interaktionen
// ============================================================
function boardAntwortFormToggle(kommentarId, themaId) {
  const form = document.getElementById(`board-form-${kommentarId}`);
  if (!form) return;
  const sichtbar = form.style.display !== 'none';
  form.style.display = sichtbar ? 'none' : 'block';
  if (!sichtbar) document.getElementById(`board-input-${kommentarId}`)?.focus();
}

function boardKommentarKeyDown(e, themaId, elternId) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    boardKommentarSenden(themaId, elternId);
  }
}

async function boardKommentarSenden(themaId, elternId) {
  const inputId = elternId ? `board-input-${elternId}` : `board-input-top-${themaId}`;
  const ta = document.getElementById(inputId);
  if (!ta) return;
  const inhalt = ta.value.trim();
  if (!inhalt) return;
  ta.disabled = true;
  await api('board_kommentar_erstellen', { thema_id: themaId, eltern_id: elternId, inhalt });
  ta.value = '';
  ta.disabled = false;
  if (elternId) {
    const form = document.getElementById(`board-form-${elternId}`);
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
  await api('kommentar_entscheidung', { id: kommentarId });
  boardThemaOeffnen(themaId);
}

// ============================================================
//  Neues Thema Modal
// ============================================================
function boardNeuesThema() {
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
      <label class="form-label">Erster Beitrag (optional)</label>
      <textarea class="form-control" id="board-neuer-inhalt" rows="4"
        placeholder="Beschreibe das Thema, stelle eine Frage…"></textarea>
    </div>`;
  F.innerHTML = `
    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
    <button class="btn btn-accent" onclick="boardNeuesThemaSpeichern()">
      <i class="bi bi-plus-lg me-1"></i> Thema erstellen
    </button>`;
  oeffneModal();
}

async function boardNeuesThemaSpeichern() {
  const titel  = document.getElementById('board-neues-titel').value.trim();
  const inhalt = document.getElementById('board-neuer-inhalt').value.trim();
  if (!titel) return notify('Bitte einen Titel eingeben', 'error');
  const res = await api('board_thema_erstellen', { projekt_id: aktivProjekt.id, titel });
  if (inhalt) {
    await api('board_kommentar_erstellen', { thema_id: res.id, eltern_id: null, inhalt });
  }
  schliesseModal();
  notify('Thema erstellt');
  boardThemaOeffnen(res.id);
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
      <i class="bi bi-check-circle-fill"></i>
      Die Rubrik wird mit diesem Board-Thema verknüpft.
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
  await api('board_rubrik_erstellen', { thema_id: themaId, name });
  schliesseModal();
  notify('Rubrik angelegt und verknüpft!');
  await ladeProjekt(aktivProjekt.id);
  // zurück zum Thema damit man die Verknüpfung sieht
  aktiverTab = 'board';
  boardThemaOeffnen(themaId);
}

// ============================================================
//  Von Eintrag/Schritt → Board-Thema erstellen
// ============================================================
async function boardThemaVonRef(refTyp, refId, titel) {
  const res = await api('board_thema_von_ref', {
    ref_typ:    refTyp,
    ref_id:     refId,
    projekt_id: aktivProjekt.id,
    titel:      titel,
  });
  if (res.existed) {
    notify('Board-Thema bereits vorhanden — öffne es');
  } else {
    notify('Board-Thema erstellt und verknüpft!');
  }
  schliesseModal();
  // Zum Board wechseln
  document.querySelectorAll('#projektTabs .nav-link').forEach(t => t.classList.remove('active'));
  document.querySelector('#projektTabs [data-tab="board"]')?.classList.add('active');
  aktiverTab = 'board';
  boardThemaOeffnen(res.id);
}

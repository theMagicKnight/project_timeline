/* ============================================================
   diskussion.js — Kommentare, Reaktionen, Entscheidungen
   ============================================================ */

const REAKTION_TYPEN = ['👍','👎','❤️','🤔'];

function renderDiskussion(kommentare, typ, refId) {
  const anzahl = kommentare.length;
  const entscheidungen = kommentare.filter(k => k.ist_entscheidung == 1).length;

  return `
    <div class="diskussion-wrap mt-3">
      <div class="diskussion-header">
        <span class="anh-section-title">
          <i class="bi bi-chat-dots me-1"></i>Diskussion
          ${anzahl > 0 ? `<span class="diskussion-count">${anzahl}</span>` : ''}
        </span>
        ${entscheidungen > 0 ? `<span class="entscheidung-badge"><i class="bi bi-check-circle-fill me-1"></i>${entscheidungen} Entscheidung${entscheidungen > 1 ? 'en' : ''}</span>` : ''}
      </div>

      <div class="kommentar-liste" id="kommentar-liste-${typ}-${refId}">
        ${anzahl === 0
          ? `<div class="kommentar-leer">Noch keine Kommentare. Sei der Erste!</div>`
          : kommentare.map(k => renderKommentar(k, typ, refId)).join('')
        }
      </div>

      ${hatRecht('schreiben') ? `
      <div class="kommentar-form" id="kommentar-form-${typ}-${refId}">
        <div class="kommentar-input-wrap">
          <div class="user-avatar-sm">${(AKTUELLER_BENUTZER.name||'?')[0].toUpperCase()}</div>
          <textarea
            class="kommentar-input"
            id="kommentar-text-${typ}-${refId}"
            placeholder="Kommentar schreiben… (Enter = Senden, Shift+Enter = Zeilenumbruch)"
            rows="2"
            onkeydown="kommentarKeyDown(event,'${typ}',${refId})"
          ></textarea>
        </div>
        <div class="d-flex justify-content-end mt-1">
          <button class="btn btn-accent btn-sm" onclick="kommentarSenden('${typ}',${refId})">
            <i class="bi bi-send me-1"></i> Senden
          </button>
        </div>
      </div>` : ''}
    </div>`;
}

function renderKommentar(k, typ, refId) {
  const datum = new Date(k.erstellt_am).toLocaleDateString('de-DE', {
    day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'
  });
  const istEntscheidung = k.ist_entscheidung == 1;
  const vornamAutor = k.autor_name ? k.autor_name.trim().split(' ')[0] : '?';
  const initial = vornamAutor[0].toUpperCase();

  return `
    <div class="kommentar-item ${istEntscheidung ? 'ist-entscheidung' : ''}" id="kommentar-${k.id}">
      ${istEntscheidung ? `<div class="entscheidung-banner"><i class="bi bi-check-circle-fill me-1"></i>Als Entscheidung markiert</div>` : ''}
      <div class="kommentar-kopf">
        <div class="user-avatar-sm">${initial}</div>
        <div class="kommentar-meta">
          <span class="kommentar-autor">${esc(vornamAutor)}</span>
          <span class="kommentar-datum">${datum}</span>
        </div>
        ${hatRecht('verwalten') ? `
        <button
          class="kommentar-entscheidung-btn ${istEntscheidung ? 'aktiv' : ''}"
          onclick="toggleEntscheidung(${k.id},'${typ}',${refId})"
          title="${istEntscheidung ? 'Entscheidung aufheben' : 'Als Entscheidung markieren'}"
        >
          <i class="bi bi-check-circle${istEntscheidung ? '-fill' : ''}"></i>
        </button>` : ''}
      </div>
      <div class="kommentar-text">${esc(k.inhalt).replace(/\n/g,'<br>')}</div>
      <div class="reaktionen-zeile">
        ${REAKTION_TYPEN.map(r => `
          <button
            class="reaktion-btn ${k.meine_reaktion === r ? 'aktiv' : ''}"
            onclick="reaktionSetzen(${k.id},'${r}','${typ}',${refId})"
            title="${r}"
          >${r} <span class="reaktion-count">${k['r_' + reaktionKey(r)] || 0}</span></button>
        `).join('')}
      </div>
    </div>`;
}

function reaktionKey(typ) {
  return {'👍':'gut','👎':'nein','❤️':'herz','🤔':'denk'}[typ] || 'gut';
}

async function kommentarSenden(typ, refId) {
  const ta = document.getElementById(`kommentar-text-${typ}-${refId}`);
  const inhalt = ta.value.trim();
  if (!inhalt) return;
  ta.disabled = true;
  await api('kommentar_erstellen', { typ, referenz_id: refId, inhalt });
  ta.value = '';
  ta.disabled = false;
  await aktualisiereKommentare(typ, refId);
  notify('Kommentar gespeichert');
}

function kommentarKeyDown(e, typ, refId) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    kommentarSenden(typ, refId);
  }
}

async function reaktionSetzen(kommentarId, reaktionTyp, typ, refId) {
  await api('reaktion_setzen', { kommentar_id: kommentarId, typ: reaktionTyp });
  await aktualisiereKommentare(typ, refId);
}

async function toggleEntscheidung(kommentarId, typ, refId) {
  await api('kommentar_entscheidung', { id: kommentarId });
  await aktualisiereKommentare(typ, refId);
}

async function aktualisiereKommentare(typ, refId) {
  const kommentare = await api('kommentare_laden', { typ, referenz_id: refId });
  const liste = document.getElementById(`kommentar-liste-${typ}-${refId}`);
  if (!liste) return;
  if (!kommentare.length) {
    liste.innerHTML = `<div class="kommentar-leer">Noch keine Kommentare. Sei der Erste!</div>`;
    return;
  }
  liste.innerHTML = kommentare.map(k => renderKommentar(k, typ, refId)).join('');
  // Header-Zähler aktualisieren
  const wrap = liste.closest('.diskussion-wrap');
  if (wrap) {
    const countEl = wrap.querySelector('.diskussion-count');
    if (countEl) countEl.textContent = kommentare.length;
    const entscheidungen = kommentare.filter(k => k.ist_entscheidung == 1).length;
    let badge = wrap.querySelector('.entscheidung-badge');
    if (entscheidungen > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'entscheidung-badge';
        wrap.querySelector('.diskussion-header').appendChild(badge);
      }
      badge.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>${entscheidungen} Entscheidung${entscheidungen > 1 ? 'en' : ''}`;
    } else if (badge) {
      badge.remove();
    }
  }
}
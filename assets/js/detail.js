/* ============================================================
   detail.js — Eintrag-Detail, Schritt & Anhang im Modal
   ============================================================ */

async function openEintragDetail(id){
  const [data, anhaenge, kommentare] = await Promise.all([
    api('projekt_detail', null, `&id=${aktivProjekt.id}`),
    api('anhang_laden', {typ:'eintrag', referenz_id:id}),
    api('kommentare_laden', {typ:'eintrag', referenz_id:id})
  ]);
  let e=null;
  data.rubriken.forEach(r=>r.eintraege.forEach(x=>{if(x.id==id)e=x;}));
  if(!e)return;
  const steps=e.schritte||[];
  setModalSize('lg');
  document.getElementById('modal-title').textContent=e.titel;
  document.getElementById('modal-body').innerHTML=`
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
      <span class="phase-badge phase-${e.phase}">${PHASEN[e.phase].icon} ${PHASEN[e.phase].label}</span>
      ${e.phase_datum?`<span style="font-size:.76rem;color:var(--text3)">${fmtDate(e.phase_datum)}</span>`:''}
    </div>
    ${e.beschreibung?`<p style="color:var(--text2);font-size:.86rem;line-height:1.6">${esc(e.beschreibung)}</p>`:''}
    <div class="matrix-title mb-2">Timeline-Schritte</div>
    <div class="tl-track"><div class="tl-line"></div>
      ${steps.map(s=>`<div class="tl-step">
        <div class="tl-dot phase-${s.phase}" style="flex-shrink:0;margin-top:2px"></div>
        <div><div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="phase-badge phase-${s.phase}">${PHASEN[s.phase].label}</span>
          <span style="font-size:.86rem;font-weight:500">${esc(s.titel)}</span>
          ${s.datum?`<span style="font-size:.73rem;color:var(--text3)">${fmtDate(s.datum)}</span>`:''}
        </div>
        ${s.beschreibung?`<div style="font-size:.78rem;color:var(--text3);margin-top:2px">${esc(s.beschreibung)}</div>`:''}
        </div></div>`).join('')}
    </div>
    ${renderAnhaenge(anhaenge,'eintrag',e.id)}
    ${renderDiskussion(kommentare,'eintrag',e.id)}`;
  document.getElementById('modal-footer').innerHTML=`
    <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schließen</button>
    ${hatRecht('schreiben')?`
      <button class="btn btn-outline-secondary btn-sm" onclick="zeigeAnhangFormImModal('eintrag',${e.id})">
        <i class="bi bi-paperclip"></i> Anhang
      </button>
      <button class="btn btn-accent btn-sm" onclick="zeigeSchrittFormImModal(${e.id})">
        <i class="bi bi-plus-lg"></i> Schritt
      </button>`:''}`;
  oeffneModal();
}

function zeigeSchrittFormImModal(eintragId) {
  document.getElementById('modal-title').textContent='Entwicklungsschritt hinzufügen';
  document.getElementById('modal-body').innerHTML=formSchritt();
  document.getElementById('modal-footer').innerHTML=`
    <button class="btn btn-outline-secondary" onclick="openEintragDetail(${eintragId})">
      <i class="bi bi-arrow-left me-1"></i> Zurück
    </button>
    <button class="btn btn-accent" onclick="speichernSchrittImModal(${eintragId})">Hinzufügen</button>`;
}

async function speichernSchrittImModal(eintragId) {
  const t=document.getElementById('f-titel').value.trim();
  if(!t)return notify('Bitte einen Titel eingeben','error');
  await api('schritt_erstellen',{
    eintrag_id:   eintragId,
    titel:        t,
    beschreibung: document.getElementById('f-desc').value,
    phase:        document.getElementById('f-phase').value,
    datum:        document.getElementById('f-datum').value,
  });
  notify('Schritt hinzugefügt');
  await ladeProjekt(aktivProjekt.id);
  openEintragDetail(eintragId);
}

function zeigeAnhangFormImModal(typ, refId) {
  document.getElementById('modal-title').textContent='Anhang hinzufügen';
  document.getElementById('modal-body').innerHTML=formAnhang(typ, refId);
  document.getElementById('modal-footer').innerHTML=`
    <button class="btn btn-outline-secondary" onclick="openEintragDetail(${refId})">
      <i class="bi bi-arrow-left me-1"></i> Zurück
    </button>
    <button class="btn btn-accent" onclick="speichernAnhang('${typ}',${refId})">
      <i class="bi bi-paperclip me-1"></i> Speichern
    </button>`;
}
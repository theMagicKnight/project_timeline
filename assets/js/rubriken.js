/* ============================================================
   rubriken.js — Rubriken & Einträge Ansicht
   ============================================================ */

function renderRubriken(rubriken){
  const c=document.getElementById('content');
  if(!rubriken.length){c.innerHTML=`<div class="empty-state"><span class="icon">📁</span>Noch keine Rubriken.</div>`;return;}
  c.innerHTML=rubriken.map(r=>rubrikCard(r)).join('');
}

function rubrikCard(r){
  return `<div class="rubrik-card">
    <div class="rubrik-head">
      <div class="rubrik-icon" style="background:${aktivProjekt.farbe}">${r.name[0].toUpperCase()}</div>
      <div class="flex-grow-1 min-w-0">
        <div class="rubrik-name">${esc(r.name)}</div>
        ${r.beschreibung?`<div class="rubrik-desc">${esc(r.beschreibung)}</div>`:''}
        ${r.erstellt_von_name?`<div class="rubrik-creator"><i class="bi bi-person-fill"></i> ${esc(vorname(r.erstellt_von_name))}</div>`:''}
      </div>
      <div class="d-flex gap-1 flex-shrink-0">
        ${hatRecht('schreiben')?`<button class="btn btn-outline-secondary btn-sm" onclick="openModal('eintrag',{rubrik_id:${r.id}})"><i class="bi bi-plus-lg"></i></button>`:''}
        ${hatRecht('schreiben')?`<button class="btn btn-outline-secondary btn-sm" onclick="openModal('rubrik_edit',${J(r)})"><i class="bi bi-pencil"></i></button>`:''}
        ${hatRecht('verwalten')?`<button class="btn btn-outline-danger btn-sm" onclick="loeschenRubrik(${r.id})"><i class="bi bi-trash"></i></button>`:''}
      </div>
    </div>
    <div>
      ${r.eintraege?.length
        ?r.eintraege.map(e=>eintragRow(e)).join('')
        :`<div class="empty-state" style="padding:18px"><span class="icon" style="font-size:1.2rem">✦</span>Noch keine Einträge</div>`}
    </div>
  </div>`;
}

function eintragRow(e){
  const pips=['idee','start','entwicklung','abschluss'].map(p=>`<span class="step-pip ${e.phase===p||phaseOrder(e.phase)>phaseOrder(p)?'done':''}"></span>`).join('');
  return `<div class="eintrag-row" onclick="openEintragDetail(${e.id})">
    <div class="eintrag-color" style="background:${e.farbe}"></div>
    <div class="flex-grow-1 min-w-0">
      <div class="eintrag-titel">${esc(e.titel)}</div>
      <div class="eintrag-meta">
        <span class="phase-badge phase-${e.phase}">${PHASEN[e.phase].icon} ${PHASEN[e.phase].label}</span>
        ${e.phase_datum?`<span style="font-size:.73rem;color:var(--text3)">${fmtDate(e.phase_datum)}</span>`:''}
        ${e.erstellt_von_name?`<span class="eintrag-creator"><i class="bi bi-person-fill"></i> ${esc(vorname(e.erstellt_von_name))}</span>`:''}
        ${e.anhang_count>0?`<span class="anh-indicator" title="${e.anhang_count} Anhang${e.anhang_count>1?'änge':''}"><i class="bi bi-paperclip"></i> ${e.anhang_count}</span>`:''}
        ${e.kommentar_count>0?`<span class="kommentar-indicator" title="${e.kommentar_count} Kommentar${e.kommentar_count>1?'e':''}"><i class="bi bi-chat-dots"></i> ${e.kommentar_count}</span>`:''}
        <div class="d-flex gap-1">${pips}</div>
      </div>
    </div>
    <div class="d-flex gap-1 flex-shrink-0">
      ${hatRecht('schreiben')?`<button class="btn btn-outline-secondary btn-sm" onclick="event.stopPropagation();openModal('eintrag_edit',${J(e)})"><i class="bi bi-pencil"></i></button>`:''}
      ${hatRecht('verwalten')?`<button class="btn btn-outline-danger btn-sm" onclick="event.stopPropagation();loeschenEintrag(${e.id})"><i class="bi bi-trash"></i></button>`:''}
    </div>
  </div>`;
}
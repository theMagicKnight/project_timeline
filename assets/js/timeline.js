/* ============================================================
   timeline.js — Timeline-Ansicht
   ============================================================ */

function renderTimeline(rubriken){
  const c=document.getElementById('content');
  if(!rubriken.length){c.innerHTML=`<div class="empty-state"><span class="icon">⏱</span>Keine Rubriken.</div>`;return;}
  let html='';
  rubriken.forEach(r=>{
    if(!r.eintraege?.length)return;
    html+=`<div class="mb-4"><div class="tl-rubrik-title">${esc(r.name)}</div>`;
    r.eintraege.forEach(e=>{
      html+=`<div class="mb-4">
        <div class="tl-entry-title" style="color:${e.farbe}">${esc(e.titel)}</div>
        <div class="tl-track"><div class="tl-line"></div>`;
      (e.schritte||[]).forEach(s=>{
        html+=`<div class="tl-step"><div class="tl-dot phase-${s.phase}"></div><div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="phase-badge phase-${s.phase}">${PHASEN[s.phase].label}</span>
            <span style="font-size:.86rem;font-weight:500">${esc(s.titel)}</span>
            ${s.datum?`<span style="font-size:.73rem;color:var(--text3)">${fmtDate(s.datum)}</span>`:''}
            ${s.erstellt_von_name?`<span class="schritt-creator"><i class="bi bi-person-fill"></i> ${esc(vorname(s.erstellt_von_name))}</span>`:''}
            ${hatRecht('verwalten')?`<div class="tl-step-actions"><button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="loeschenSchritt(${s.id})"><i class="bi bi-x"></i></button></div>`:''}
          </div>
          ${s.beschreibung?`<div style="font-size:.78rem;color:var(--text3);margin-top:2px;line-height:1.5">${esc(s.beschreibung)}</div>`:''}
        </div></div>`;
      });
      html+=`</div>
        ${hatRecht('schreiben')?`<button class="tl-add-step" onclick="openModal('schritt',{eintrag_id:${e.id}})"><i class="bi bi-plus-lg"></i> Schritt hinzufügen</button>`:''}
      </div>`;
    });
    html+='</div>';
  });
  c.innerHTML=html||`<div class="empty-state"><span class="icon">⏱</span>Keine Einträge.</div>`;
}
/* ============================================================
   Projekt-Timeline — app.js
   Bootstrap 5 + Auth + Rechte + Hell/Dunkel
   ============================================================ */

const FARBEN = ['#7c6af7','#f0b429','#34d399','#f87171','#38bdf8','#fb923c','#e879f9','#a3e635','#94a3b8'];
const PHASEN = {
  idee:        { label:'Idee',        icon:'💡' },
  start:       { label:'Start',       icon:'🚀' },
  entwicklung: { label:'Entwicklung', icon:'⚙️' },
  abschluss:   { label:'Abschluss',   icon:'✅' },
};
const RECHTE_STUFEN = { lesen:1, schreiben:2, verwalten:3, admin:4 };

let aktivProjekt  = null;
let aktiverTab    = 'matrix';
let aktivesRecht  = null;   // Recht des eingeloggten Users auf das aktuelle Projekt
let bsModal       = null;
let aktuellesTheme = AKTUELLER_BENUTZER.theme;

// ============================================================
//  Rechte-Helper
// ============================================================
function hatRecht(mindest) {
  const ist  = RECHTE_STUFEN[aktivesRecht] ?? 0;
  const mind = RECHTE_STUFEN[mindest] ?? 99;
  return ist >= mind || IST_ADMIN;
}

// ============================================================
//  Hilfsfunktionen
// ============================================================
function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function J(o) { return JSON.stringify(o).replace(/"/g,"'"); }
function fmtDate(d) {
  if (!d) return '';
  return new Date(d+'T00:00:00').toLocaleDateString('de-DE',{day:'2-digit',month:'short',year:'numeric'});
}
function phaseOrder(p) { return ['idee','start','entwicklung','abschluss'].indexOf(p); }

// ============================================================
//  API
// ============================================================
async function api(action, data=null, params='') {
  const url  = `api.php?api=${action}${params}`;
  const opts = data
    ? {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}
    : {method:'GET'};
  const r = await fetch(url, opts);
  if (r.status === 401) { window.location.href = 'login.php'; return; }
  const j = await r.json();
  if (j.error) throw new Error(j.error);
  return j;
}

// ============================================================
//  Benachrichtigungen
// ============================================================
function notify(msg, type='success') {
  const icon = type==='success'
    ? '<i class="bi bi-check-lg text-success"></i>'
    : '<i class="bi bi-exclamation-triangle text-danger"></i>';
  const n = document.createElement('div');
  n.className = `app-toast ${type}`;
  n.innerHTML = `${icon} ${esc(msg)}`;
  document.body.appendChild(n);
  setTimeout(()=>n.remove(), 3200);
}

// ============================================================
//  Logout
// ============================================================
async function logout() {
  await api('logout');
  window.location.href = 'login.php';
}

// ============================================================
//  Hell / Dunkel Toggle
// ============================================================
async function wechselTheme() {
  aktuellesTheme = aktuellesTheme === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-bs-theme', aktuellesTheme);
  // Icons aktualisieren
  const isDark = aktuellesTheme === 'dark';
  document.querySelectorAll('#theme-toggle i, #theme-icon-mobile').forEach(el => {
    el.className = `bi bi-${isDark ? 'sun' : 'moon'}-fill`;
  });
  // In DB speichern
  await api('theme_aendern', {theme: aktuellesTheme});
}

// ============================================================
//  Sidebar Mobile
// ============================================================
function toggleSidebar() {
  document.querySelector('.app-sidebar').classList.toggle('open');
  document.getElementById('sidebar-overlay').classList.toggle('open');
}
function schliesseSidebar() {
  document.querySelector('.app-sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('open');
}

// ============================================================
//  Sidebar laden
// ============================================================
async function ladeSidebar() {
  const projekte = await api('projekte_liste');
  const list = document.getElementById('proj-list');
  if (!projekte.length) {
    list.innerHTML = '<div class="px-3 py-2" style="color:var(--text3);font-size:.82rem">Keine Projekte zugewiesen</div>';
    return;
  }
  list.innerHTML = projekte.map(p => `
    <div class="proj-item ${aktivProjekt?.id==p.id?'active':''}" onclick="ladeProjekt(${p.id})">
      <span class="proj-dot" style="background:${p.farbe}"></span>
      <span class="proj-name">${esc(p.name)}</span>
      ${p.recht ? `<span class="recht-badge recht-${p.recht}" style="font-size:.58rem;margin-left:auto">${p.recht}</span>` : ''}
    </div>`).join('');
}

// ============================================================
//  Projekt laden
// ============================================================
async function ladeProjekt(id) {
  schliesseSidebar();
  const data   = await api('projekt_detail', null, `&id=${id}`);
  aktivProjekt = data.projekt;
  aktivesRecht = IST_ADMIN ? 'admin' : data.mein_recht;
  aktiverTab   = 'matrix';
  await ladeSidebar();
  renderMain(data.rubriken);
}

function renderMain(rubriken) {
  const main = document.getElementById('main');
  const f    = aktivProjekt.farbe;

  main.innerHTML = `
    <div class="app-topbar">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <h1 class="topbar-title mb-0">${esc(aktivProjekt.name)}</h1>
        <span class="proj-badge" style="background:${f}22;color:${f}">Projekt</span>
        <span class="recht-badge recht-${aktivesRecht}">${aktivesRecht}</span>
      </div>
      ${aktivProjekt.beschreibung?`<p class="topbar-desc mt-1 mb-0">${esc(aktivProjekt.beschreibung)}</p>`:''}
      <div class="d-flex gap-2 mt-2 flex-wrap">
        ${hatRecht('schreiben') ? `<button class="btn btn-outline-secondary btn-sm" onclick="openModal('rubrik',{projekt_id:${aktivProjekt.id}})"><i class="bi bi-plus-lg"></i> Rubrik</button>` : ''}
        ${hatRecht('verwalten') ? `<button class="btn btn-outline-secondary btn-sm" onclick="openModal('projekt_edit')"><i class="bi bi-pencil"></i> Bearbeiten</button>` : ''}
        ${IST_ADMIN ? `<button class="btn btn-outline-secondary btn-sm" onclick="openModal('projekt_zugang')"><i class="bi bi-people"></i> Zugang</button>` : ''}
        ${IST_ADMIN ? `<button class="btn btn-outline-danger btn-sm" onclick="loeschenProjekt(${aktivProjekt.id})"><i class="bi bi-trash"></i></button>` : ''}
      </div>
    </div>
    <ul class="nav app-tabs" id="projektTabs">
      <li class="nav-item">
        <button class="nav-link ${aktiverTab==='matrix'?'active':''}" onclick="switchTab('matrix',this)">
          <i class="bi bi-grid-3x3-gap me-1"></i><span class="d-none d-sm-inline">Aktivität</span>
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link ${aktiverTab==='rubriken'?'active':''}" onclick="switchTab('rubriken',this)">
          <i class="bi bi-folder me-1"></i><span class="d-none d-sm-inline">Rubriken</span>
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link ${aktiverTab==='timeline'?'active':''}" onclick="switchTab('timeline',this)">
          <i class="bi bi-clock-history me-1"></i><span class="d-none d-sm-inline">Timeline</span>
        </button>
      </li>
    </ul>
    <div class="content" id="content"></div>`;

  showTab(aktiverTab, rubriken);
}

function switchTab(tab, el) {
  document.querySelectorAll('#projektTabs .nav-link').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  aktiverTab = tab;
  api('projekt_detail', null, `&id=${aktivProjekt.id}`).then(d => showTab(tab, d.rubriken));
}
function showTab(tab, rubriken) {
  if      (tab==='matrix')   renderMatrix(rubriken);
  else if (tab==='rubriken') renderRubriken(rubriken);
  else                       renderTimeline(rubriken);
}

// ============================================================
//  Aktivitäts-Matrix
// ============================================================
function renderMatrix(rubriken) {
  const content = document.getElementById('content');
  const rawMap  = {};
  function addDay(ds) { if (!ds) return; const d=ds.slice(0,10); rawMap[d]=(rawMap[d]||0)+1; }
  (rubriken||[]).forEach(r=>(r.eintraege||[]).forEach(e=>{
    addDay(e.erstellt_am);
    (e.schritte||[]).forEach(s=>addDay(s.datum||s.erstellt_am));
  }));

  const today=new Date(); today.setHours(0,0,0,0);
  const start=new Date(today); start.setDate(start.getDate()-start.getDay()-52*7);
  const vals=Object.values(rawMap).map(Number);
  const maxVal=vals.length?Math.max(...vals):1;
  function lv(n){if(!n)return 0;if(n<=maxVal*.25)return 1;if(n<=maxVal*.5)return 2;if(n<=maxVal*.75)return 3;return 4;}

  const cols=[],monthLabels=[];
  let prevMonth=-1,cur=new Date(start);
  while(cur<=today){
    const col=[];
    for(let wd=0;wd<7;wd++){
      const d=new Date(cur);d.setDate(d.getDate()+wd);
      if(d>today){col.push(null);continue;}
      const key=d.toISOString().slice(0,10);
      col.push({date:key,count:rawMap[key]||0,lv:lv(rawMap[key]||0)});
      if(wd===0&&d.getMonth()!==prevMonth){monthLabels.push({col:cols.length,label:d.toLocaleDateString('de-DE',{month:'short'})});prevMonth=d.getMonth();}
    }
    cols.push(col);cur.setDate(cur.getDate()+7);
  }

  const totalAkt=vals.reduce((a,b)=>a+b,0);
  const aktivTage=vals.filter(v=>v>0).length;
  const CW=11,GAP=3;
  const monthHTML=monthLabels.map(m=>`<div class="matrix-month-label" style="left:${m.col*(CW+GAP)}px">${m.label}</div>`).join('');
  const gridHTML=cols.map(col=>`<div class="matrix-col">${col.map(cell=>cell===null?`<div class="mc" data-l="0" style="opacity:0"></div>`:`<div class="mc" data-l="${cell.lv}" data-date="${cell.date}" data-count="${cell.count}" onmouseenter="showTip(event,this)" onmouseleave="hideTip()"></div>`).join('')}</div>`).join('');
  const wdLabels=['So','Mo','','Mi','','Fr',''].map(d=>`<div class="matrix-wd">${d}</div>`).join('');

  const counts={idee:0,start:0,entwicklung:0,abschluss:0};
  (rubriken||[]).forEach(r=>(r.eintraege||[]).forEach(e=>counts[e.phase]++));
  const pColors={idee:'var(--gold)',start:'var(--accent)',entwicklung:'var(--cyan)',abschluss:'var(--green)'};
  const phasebar=Object.entries(counts).map(([p,n])=>`<div style="flex:${n||0.3};background:${pColors[p]}"></div>`).join('');
  const phaseLegend=Object.entries(counts).map(([p,n])=>`<div class="phasebar-item"><span style="width:7px;height:7px;border-radius:50%;background:${pColors[p]};display:inline-block;flex-shrink:0"></span>${PHASEN[p].icon} ${PHASEN[p].label} <span style="color:var(--text3)">${n}</span></div>`).join('');

  content.innerHTML=`
    <div class="matrix-wrap">
      <div class="matrix-header">
        <div class="matrix-title"><i class="bi bi-grid-3x3-gap me-1"></i>Aktivität · letzte 12 Monate</div>
        <div class="matrix-stats">${totalAkt} Aktivitäten &middot; ${aktivTage} aktive Tage</div>
      </div>
      <div class="matrix-scroll"><div class="matrix-outer">
        <div class="matrix-weekdays">${wdLabels}</div>
        <div class="matrix-inner">
          <div class="matrix-month-row" style="min-width:${cols.length*(CW+GAP)}px">${monthHTML}</div>
          <div class="matrix-cols">${gridHTML}</div>
        </div>
      </div></div>
      <div class="matrix-legend"><span>Weniger</span>${[0,1,2,3,4].map(l=>`<div class="mc" data-l="${l}" style="cursor:default"></div>`).join('')}<span>Mehr</span></div>
    </div>
    <div class="stat-grid">
      ${statCard('Aktive Tage',aktivTage,'bi-calendar-check')}
      ${statCard('Aktivitäten',totalAkt,'bi-lightning')}
      ${statCard('Rubriken',(rubriken||[]).length,'bi-folder')}
      ${statCard('Einträge',(rubriken||[]).reduce((s,r)=>s+(r.eintraege?.length||0),0),'bi-card-list')}
    </div>
    <div class="mb-3">
      <div class="matrix-title mb-2">Phasen-Übersicht</div>
      <div class="phasebar">${phasebar}</div>
      <div class="phasebar-legend mt-2">${phaseLegend}</div>
    </div>`;
}

function statCard(label,val,icon=''){
  return `<div class="stat-card"><div class="stat-label">${icon?`<i class="bi ${icon} me-1"></i>`:''}${label}</div><div class="stat-val">${val}</div></div>`;
}
function showTip(e,el){
  const tip=document.getElementById('mtt');
  const n=parseInt(el.dataset.count)||0;
  const d=new Date(el.dataset.date+'T00:00:00');
  const label=d.toLocaleDateString('de-DE',{weekday:'short',day:'numeric',month:'short',year:'numeric'});
  tip.textContent=n?`${n} Aktivität${n>1?'en':''} · ${label}`:`Keine Aktivität · ${label}`;
  tip.style.left=(e.clientX+14)+'px';tip.style.top=(e.clientY-36)+'px';tip.classList.add('vis');
}
function hideTip(){document.getElementById('mtt').classList.remove('vis');}

// ============================================================
//  Rubriken-Ansicht
// ============================================================
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
        <div class="d-flex gap-1">${pips}</div>
      </div>
    </div>
    <div class="d-flex gap-1 flex-shrink-0">
      ${hatRecht('schreiben')?`<button class="btn btn-outline-secondary btn-sm" onclick="event.stopPropagation();openModal('eintrag_edit',${J(e)})"><i class="bi bi-pencil"></i></button>`:''}
      ${hatRecht('verwalten')?`<button class="btn btn-outline-danger btn-sm" onclick="event.stopPropagation();loeschenEintrag(${e.id})"><i class="bi bi-trash"></i></button>`:''}
    </div>
  </div>`;
}

// ============================================================
//  Timeline
// ============================================================
function renderTimeline(rubriken){
  const c=document.getElementById('content');
  if(!rubriken.length){c.innerHTML=`<div class="empty-state"><span class="icon">⏱</span>Keine Rubriken.</div>`;return;}
  let html='';
  rubriken.forEach(r=>{
    if(!r.eintraege?.length)return;
    html+=`<div class="mb-4"><div class="tl-rubrik-title">${esc(r.name)}</div>`;
    r.eintraege.forEach(e=>{
      html+=`<div class="mb-4"><div class="tl-entry-title" style="color:${e.farbe}">${esc(e.titel)}</div><div class="tl-track"><div class="tl-line"></div>`;
      (e.schritte||[]).forEach(s=>{
        html+=`<div class="tl-step"><div class="tl-dot phase-${s.phase}"></div><div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="phase-badge phase-${s.phase}">${PHASEN[s.phase].label}</span>
            <span style="font-size:.86rem;font-weight:500">${esc(s.titel)}</span>
            ${s.datum?`<span style="font-size:.73rem;color:var(--text3)">${fmtDate(s.datum)}</span>`:''}
            ${hatRecht('verwalten')?`<div class="tl-step-actions"><button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="loeschenSchritt(${s.id})"><i class="bi bi-x"></i></button></div>`:''}
          </div>
          ${s.beschreibung?`<div style="font-size:.78rem;color:var(--text3);margin-top:2px;line-height:1.5">${esc(s.beschreibung)}</div>`:''}
        </div></div>`;
      });
      html+=`</div>${hatRecht('schreiben')?`<button class="tl-add-step" onclick="openModal('schritt',{eintrag_id:${e.id}})"><i class="bi bi-plus-lg"></i> Schritt hinzufügen</button>`:''}</div>`;
    });
    html+='</div>';
  });
  c.innerHTML=html||`<div class="empty-state"><span class="icon">⏱</span>Keine Einträge.</div>`;
}

// ============================================================
//  Eintrag Detail
// ============================================================
async function openEintragDetail(id){
  const data=await api('projekt_detail',null,`&id=${aktivProjekt.id}`);
  let e=null;
  data.rubriken.forEach(r=>r.eintraege.forEach(x=>{if(x.id==id)e=x;}));
  if(!e)return;
  const steps=e.schritte||[];
  setModalSize('normal');
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
    </div>`;
  document.getElementById('modal-footer').innerHTML=`
    <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schließen</button>
    ${hatRecht('schreiben')?`<button class="btn btn-accent btn-sm" onclick="zeigeSchrittFormImModal(${e.id})"><i class="bi bi-plus-lg"></i> Schritt</button>`:''}`;
  oeffneModal();
}

// Schritt-Formular direkt im Modal anzeigen (kein Modal-Wechsel)
function zeigeSchrittFormImModal(eintragId) {
  document.getElementById('modal-title').textContent='Entwicklungsschritt hinzufügen';
  document.getElementById('modal-body').innerHTML=formSchritt();
  document.getElementById('modal-footer').innerHTML=`
    <button class="btn btn-outline-secondary" onclick="openEintragDetail(${eintragId})"><i class="bi bi-arrow-left me-1"></i> Zurück</button>
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

// ============================================================
//  Modals
// ============================================================
function oeffneModal(){
  if(!bsModal)bsModal=new bootstrap.Modal(document.getElementById('appModal'));
  bsModal.show();
}
function schliesseModal(){bsModal?.hide();}
function setModalSize(size){
  const d=document.getElementById('modal-dialog');
  d.className='modal-dialog modal-dialog-centered modal-dialog-scrollable';
  if(size==='lg')d.classList.add('modal-lg-custom');
}

function openModal(type,data={}){
  const T=document.getElementById('modal-title');
  const B=document.getElementById('modal-body');
  const F=document.getElementById('modal-footer');
  const foot=(fn,label='Erstellen')=>`
    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
    <button class="btn btn-accent" onclick="${fn}">${label}</button>`;

  setModalSize('normal');

  if(type==='projekt'){
    T.textContent='Neues Projekt';B.innerHTML=formProjekt();F.innerHTML=foot('speichernProjekt()');
  }else if(type==='projekt_edit'){
    T.textContent='Projekt bearbeiten';B.innerHTML=formProjekt(aktivProjekt);F.innerHTML=foot(`speichernProjekt(${aktivProjekt.id})`,'Speichern');
  }else if(type==='rubrik'){
    T.textContent='Neue Rubrik';B.innerHTML=formRubrik();F.innerHTML=foot(`speichernRubrik(${data.projekt_id})`);
  }else if(type==='rubrik_edit'){
    T.textContent='Rubrik bearbeiten';B.innerHTML=formRubrik(data);F.innerHTML=foot(`speichernRubrikEdit(${data.id})`,'Speichern');
  }else if(type==='eintrag'){
    T.textContent='Neuer Eintrag';B.innerHTML=formEintrag();F.innerHTML=foot(`speichernEintrag(${data.rubrik_id})`);
  }else if(type==='eintrag_edit'){
    T.textContent='Eintrag bearbeiten';B.innerHTML=formEintrag(data);F.innerHTML=foot(`speichernEintragEdit(${data.id})`,'Speichern');
  }else if(type==='schritt'){
    T.textContent='Entwicklungsschritt';B.innerHTML=formSchritt();F.innerHTML=foot(`speichernSchritt(${data.eintrag_id})`,'Hinzufügen');
  }else if(type==='profil'){
    T.textContent='Mein Profil';B.innerHTML=formProfil();F.innerHTML=foot('speichernProfil()','Speichern');
  }else if(type==='benutzer_verwaltung'){
    setModalSize('lg');
    T.textContent='Benutzerverwaltung';
    B.innerHTML='<div class="text-center py-3" style="color:var(--text3)"><i class="bi bi-arrow-repeat"></i> Lade …</div>';
    F.innerHTML=`<button class="btn btn-outline-secondary" data-bs-dismiss="modal">Schließen</button>
      <button class="btn btn-accent" onclick="zeigeBenutzerNeuForm()"><i class="bi bi-person-plus me-1"></i> Benutzer anlegen</button>`;
    oeffneModal();
    ladeBenutzerListe();return;
  }else if(type==='benutzer_neu'){
    setModalSize('lg');
    T.textContent='Neuer Benutzer';B.innerHTML=formBenutzerNeu();
    F.innerHTML=`<button class="btn btn-outline-secondary" onclick="zurueckZuBenutzerListe()"><i class="bi bi-arrow-left me-1"></i> Zurück</button>
      <button class="btn btn-accent" onclick="speichernBenutzer()">Erstellen</button>`;
    oeffneModal();return;
  }else if(type==='benutzer_edit'){
    setModalSize('lg');
    T.textContent='Benutzer bearbeiten';B.innerHTML=formBenutzerEdit(data);
    F.innerHTML=`<button class="btn btn-outline-secondary" onclick="zurueckZuBenutzerListe()"><i class="bi bi-arrow-left me-1"></i> Zurück</button>
      <button class="btn btn-accent" onclick="speichernBenutzerEdit(${data.id})">Speichern</button>`;
    oeffneModal();return;
  }else if(type==='projekt_zugang'){
    setModalSize('lg');
    T.textContent='Projektzugang verwalten';
    B.innerHTML='<div class="text-center py-3" style="color:var(--text3)"><i class="bi bi-arrow-repeat"></i> Lade …</div>';
    F.innerHTML=`<button class="btn btn-outline-secondary" data-bs-dismiss="modal">Schließen</button>`;
    oeffneModal();
    ladeProjektZugang();return;
  }
  oeffneModal();
}

// ---- Formular-Templates ----
function phaseOpts(aktiv='idee'){
  return Object.entries(PHASEN).map(([k,v])=>`<option value="${k}" ${aktiv===k?'selected':''}>${v.icon} ${v.label}</option>`).join('');
}
function farbSwatches(aktiv='#7c6af7'){
  return `<div class="color-picker-row mb-1">${FARBEN.map(c=>`<div class="color-swatch ${aktiv===c?'selected':''}" style="background:${c}" data-color="${c}" onclick="selectColor(this)"></div>`).join('')}</div><input type="hidden" id="f-farbe" value="${aktiv}">`;
}
function formProjekt(p={}){
  return `<div class="mb-3"><label class="form-label">Name *</label><input class="form-control" id="f-name" value="${esc(p.name||'')}" placeholder="Mein Projekt …"></div>
    <div class="mb-3"><label class="form-label">Beschreibung</label><textarea class="form-control" id="f-desc" rows="3">${esc(p.beschreibung||'')}</textarea></div>
    <div class="mb-1"><label class="form-label">Farbe</label></div>${farbSwatches(p.farbe)}`;
}
function formRubrik(r={}){
  return `<div class="mb-3"><label class="form-label">Name *</label><input class="form-control" id="f-name" value="${esc(r.name||'')}" placeholder="z.B. Marketing …"></div>
    <div class="mb-3"><label class="form-label">Beschreibung</label><textarea class="form-control" id="f-desc" rows="3">${esc(r.beschreibung||'')}</textarea></div>`;
}
function formEintrag(e={}){
  return `<div class="mb-3"><label class="form-label">Titel *</label><input class="form-control" id="f-titel" value="${esc(e.titel||'')}" placeholder="Was wird umgesetzt?"></div>
    <div class="mb-3"><label class="form-label">Beschreibung</label><textarea class="form-control" id="f-desc" rows="2">${esc(e.beschreibung||'')}</textarea></div>
    <div class="row g-2 mb-3">
      <div class="col-12 col-sm-6"><label class="form-label">Phase</label><select class="form-select" id="f-phase">${phaseOpts(e.phase)}</select></div>
      <div class="col-12 col-sm-6"><label class="form-label">Datum</label><input class="form-control" type="date" id="f-datum" value="${e.phase_datum||''}"></div>
    </div>
    <div class="mb-1"><label class="form-label">Farbe</label></div>${farbSwatches(e.farbe)}`;
}
function formSchritt(){
  return `<div class="mb-3"><label class="form-label">Titel *</label><input class="form-control" id="f-titel" placeholder="Was wurde erreicht?"></div>
    <div class="mb-3"><label class="form-label">Beschreibung</label><textarea class="form-control" id="f-desc" rows="2"></textarea></div>
    <div class="row g-2">
      <div class="col-12 col-sm-6"><label class="form-label">Phase</label><select class="form-select" id="f-phase">${phaseOpts()}</select></div>
      <div class="col-12 col-sm-6"><label class="form-label">Datum</label><input class="form-control" type="date" id="f-datum" value="${new Date().toISOString().slice(0,10)}"></div>
    </div>`;
}
function formProfil(){
  return `<div class="mb-3"><label class="form-label">Anzeigename</label>
    <input class="form-control" id="f-name" value="${esc(AKTUELLER_BENUTZER.name)}"></div>
    <hr style="border-color:var(--border)">
    <div class="mb-1" style="font-size:.8rem;font-weight:500;color:var(--text3)">Passwort ändern</div>
    <div class="mb-3"><label class="form-label">Aktuelles Passwort</label>
      <input class="form-control" type="password" id="f-pass-alt" placeholder="••••••••"></div>
    <div class="row g-2">
      <div class="col-12 col-sm-6"><label class="form-label">Neues Passwort</label>
        <input class="form-control" type="password" id="f-pass-neu" placeholder="mind. 6 Zeichen"></div>
      <div class="col-12 col-sm-6"><label class="form-label">Wiederholen</label>
        <input class="form-control" type="password" id="f-pass-wdh" placeholder="••••••••"></div>
    </div>`;
}
function formBenutzerNeu(){
  return `<div class="row g-2 mb-3">
    <div class="col-12 col-sm-6"><label class="form-label">Name *</label><input class="form-control" id="f-name" placeholder="Max Mustermann"></div>
    <div class="col-12 col-sm-6"><label class="form-label">E-Mail *</label><input class="form-control" type="email" id="f-email" placeholder="max@example.com"></div>
    </div>
    <div class="row g-2 mb-3">
      <div class="col-12 col-sm-6"><label class="form-label">Passwort *</label><input class="form-control" type="password" id="f-pass" placeholder="mind. 6 Zeichen"></div>
      <div class="col-12 col-sm-6"><label class="form-label">Rolle</label>
        <select class="form-select" id="f-rolle">
          <option value="benutzer">Benutzer</option>
          <option value="admin">Admin</option>
        </select>
      </div>
    </div>`;
}
function formBenutzerEdit(b){
  return `<div class="row g-2 mb-3">
    <div class="col-12 col-sm-6"><label class="form-label">Name</label><input class="form-control" id="f-name" value="${esc(b.name)}"></div>
    <div class="col-12 col-sm-6"><label class="form-label">Rolle</label>
      <select class="form-select" id="f-rolle">
        <option value="benutzer" ${b.rolle==='benutzer'?'selected':''}>Benutzer</option>
        <option value="admin"    ${b.rolle==='admin'?'selected':''}>Admin</option>
      </select>
    </div></div>
    <div class="mb-3"><label class="form-label">Aktiv</label>
      <select class="form-select" id="f-aktiv">
        <option value="1" ${b.aktiv?'selected':''}>Ja</option>
        <option value="0" ${!b.aktiv?'selected':''}>Nein (gesperrt)</option>
      </select>
    </div>
    <hr style="border-color:var(--border)">
    <div class="mb-1" style="font-size:.8rem;color:var(--text3)">Neues Passwort setzen (optional)</div>
    <input class="form-control" type="password" id="f-pass-neu" placeholder="leer = unveränderter">`;
}

function selectColor(el){
  document.querySelectorAll('.color-swatch').forEach(s=>s.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('f-farbe').value=el.dataset.color;
}

// ============================================================
//  Benutzerverwaltung — In-Modal Navigation
// ============================================================
function zeigeBenutzerNeuForm(){
  const T=document.getElementById('modal-title');
  const B=document.getElementById('modal-body');
  const F=document.getElementById('modal-footer');
  T.textContent='Neuer Benutzer';
  B.innerHTML=formBenutzerNeu();
  F.innerHTML=`<button class="btn btn-outline-secondary" onclick="zurueckZuBenutzerListe()"><i class="bi bi-arrow-left me-1"></i> Zurück</button>
    <button class="btn btn-accent" onclick="speichernBenutzer()">Erstellen</button>`;
}

function zeigeBenutzerEditForm(b){
  const T=document.getElementById('modal-title');
  const B=document.getElementById('modal-body');
  const F=document.getElementById('modal-footer');
  T.textContent='Benutzer bearbeiten';
  B.innerHTML=formBenutzerEdit(b);
  F.innerHTML=`<button class="btn btn-outline-secondary" onclick="zurueckZuBenutzerListe()"><i class="bi bi-arrow-left me-1"></i> Zurück</button>
    <button class="btn btn-accent" onclick="speichernBenutzerEdit(${b.id})">Speichern</button>`;
}

function zurueckZuBenutzerListe(){
  const T=document.getElementById('modal-title');
  const F=document.getElementById('modal-footer');
  T.textContent='Benutzerverwaltung';
  F.innerHTML=`<button class="btn btn-outline-secondary" data-bs-dismiss="modal">Schließen</button>
    <button class="btn btn-accent" onclick="zeigeBenutzerNeuForm()"><i class="bi bi-person-plus me-1"></i> Benutzer anlegen</button>`;
  ladeBenutzerListe();
}

async function ladeBenutzerListe(){
  const liste=await api('benutzer_liste');
  document.getElementById('modal-body').innerHTML=`
    <div class="table-responsive">
    <table class="user-table">
      <thead><tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Status</th><th></th></tr></thead>
      <tbody>
        ${liste.map(b=>`<tr>
          <td><strong>${esc(b.name)}</strong></td>
          <td style="color:var(--text2);font-size:.82rem">${esc(b.email)}</td>
          <td><span class="rolle-badge rolle-${b.rolle}">${b.rolle}</span></td>
          <td><span style="font-size:.75rem;color:${b.aktiv?'var(--green)':'var(--red)'}">${b.aktiv?'✓ Aktiv':'✕ Gesperrt'}</span></td>
          <td class="text-end">
            <button class="btn btn-outline-secondary btn-sm me-1" onclick="zeigeBenutzerEditForm(${J(b)})"><i class="bi bi-pencil"></i></button>
            ${b.id!==AKTUELLER_BENUTZER.id?`<button class="btn btn-outline-danger btn-sm" onclick="loeschenBenutzer(${b.id})"><i class="bi bi-trash"></i></button>`:''}
          </td>
        </tr>`).join('')}
      </tbody>
    </table>
    </div>`;
}

async function ladeProjektZugang(){
  const [zugang, alleBN] = await Promise.all([
    api('projekt_benutzer_liste', null, `&id=${aktivProjekt.id}`),
    api('benutzer_liste')
  ]);
  const zugeordneteIds = zugang.map(z=>z.benutzer_id);
  const nichtZugeordnet = alleBN.filter(b=>!zugeordneteIds.includes(b.id) && b.rolle!=='admin');

  document.getElementById('modal-body').innerHTML=`
    <div class="mb-3" style="font-size:.78rem;font-weight:500;color:var(--text3);text-transform:uppercase;letter-spacing:1px">
      Aktueller Zugang — ${esc(aktivProjekt.name)}
    </div>
    <div class="table-responsive mb-4">
      <table class="user-table">
        <thead><tr><th>Name</th><th>E-Mail</th><th>Recht</th><th></th></tr></thead>
        <tbody>
          ${zugang.length ? zugang.map(z=>`<tr>
            <td>${esc(z.name)}</td>
            <td style="color:var(--text2);font-size:.82rem">${esc(z.email)}</td>
            <td>
              <select class="form-select form-select-sm" style="width:130px"
                onchange="rechtAendern(${z.benutzer_id},this.value)">
                ${['lesen','schreiben','verwalten'].map(r=>`<option value="${r}" ${z.recht===r?'selected':''}>${r}</option>`).join('')}
              </select>
            </td>
            <td class="text-end">
              <button class="btn btn-outline-danger btn-sm" onclick="zuganEntfernen(${z.benutzer_id})"><i class="bi bi-person-dash"></i></button>
            </td>
          </tr>`).join('') : '<tr><td colspan="4" class="text-center" style="color:var(--text3)">Kein Benutzer zugewiesen</td></tr>'}
        </tbody>
      </table>
    </div>
    ${nichtZugeordnet.length ? `
    <div class="mb-2" style="font-size:.78rem;font-weight:500;color:var(--text3);text-transform:uppercase;letter-spacing:1px">Benutzer hinzufügen</div>
    <div class="d-flex gap-2 flex-wrap align-items-end">
      <div class="flex-grow-1"><label class="form-label">Benutzer</label>
        <select class="form-select" id="zug-benutzer">
          ${nichtZugeordnet.map(b=>`<option value="${b.id}">${esc(b.name)} (${esc(b.email)})</option>`).join('')}
        </select>
      </div>
      <div><label class="form-label">Recht</label>
        <select class="form-select" id="zug-recht">
          <option value="lesen">Lesen</option>
          <option value="schreiben">Schreiben</option>
          <option value="verwalten">Verwalten</option>
        </select>
      </div>
      <button class="btn btn-accent" onclick="zuganHinzufuegen()"><i class="bi bi-person-plus"></i> Hinzufügen</button>
    </div>` : '<div style="color:var(--text3);font-size:.84rem">Alle Benutzer haben bereits Zugang.</div>'}`;
}

async function rechtAendern(bid, recht){
  await api('projekt_benutzer_setzen',{projekt_id:aktivProjekt.id,benutzer_id:bid,recht});
  notify('Recht aktualisiert');
}
async function zuganEntfernen(bid){
  if(!confirm('Zugang entfernen?'))return;
  await api('projekt_benutzer_entfernen',{projekt_id:aktivProjekt.id,benutzer_id:bid});
  notify('Zugang entfernt');
  ladeProjektZugang();
}
async function zuganHinzufuegen(){
  const bid=document.getElementById('zug-benutzer').value;
  const recht=document.getElementById('zug-recht').value;
  await api('projekt_benutzer_setzen',{projekt_id:aktivProjekt.id,benutzer_id:parseInt(bid),recht});
  notify('Zugang vergeben');
  ladeProjektZugang();
}

// ============================================================
//  CRUD — Projekte
// ============================================================
async function speichernProjekt(id=null){
  const name=document.getElementById('f-name').value.trim();
  if(!name)return notify('Bitte einen Namen eingeben','error');
  const pl={name,beschreibung:document.getElementById('f-desc').value,farbe:document.getElementById('f-farbe').value};
  if(id){pl.id=id;await api('projekt_aktualisieren',pl);notify('Projekt gespeichert');}
  else{await api('projekt_erstellen',pl);notify('Projekt erstellt');}
  schliesseModal();await ladeSidebar();if(id)ladeProjekt(id);
}
async function loeschenProjekt(id){
  if(!confirm('Projekt und alle Inhalte wirklich löschen?'))return;
  await api('projekt_loeschen',{id});
  aktivProjekt=null;aktivesRecht=null;
  document.getElementById('main').innerHTML=`<div class="welcome h-100 d-flex flex-column align-items-center justify-content-center text-center p-4"><div class="welcome-icon">◎</div><h2>Projekt gelöscht</h2><p>Wähle ein anderes Projekt.</p></div>`;
  notify('Projekt gelöscht');await ladeSidebar();
}

// ============================================================
//  CRUD — Rubriken
// ============================================================
async function speichernRubrik(pid){
  const n=document.getElementById('f-name').value.trim();
  if(!n)return notify('Name eingeben','error');
  await api('rubrik_erstellen',{projekt_id:pid,name:n,beschreibung:document.getElementById('f-desc').value});
  schliesseModal();notify('Rubrik erstellt');ladeProjekt(pid);
}
async function speichernRubrikEdit(id){
  const n=document.getElementById('f-name').value.trim();
  if(!n)return notify('Name eingeben','error');
  await api('rubrik_aktualisieren',{id,name:n,beschreibung:document.getElementById('f-desc').value});
  schliesseModal();notify('Rubrik gespeichert');ladeProjekt(aktivProjekt.id);
}
async function loeschenRubrik(id){
  if(!confirm('Rubrik löschen?'))return;
  await api('rubrik_loeschen',{id});notify('Rubrik gelöscht');ladeProjekt(aktivProjekt.id);
}

// ============================================================
//  CRUD — Einträge
// ============================================================
async function speichernEintrag(rid){
  const t=document.getElementById('f-titel').value.trim();
  if(!t)return notify('Titel eingeben','error');
  await api('eintrag_erstellen',{rubrik_id:rid,titel:t,beschreibung:document.getElementById('f-desc').value,phase:document.getElementById('f-phase').value,phase_datum:document.getElementById('f-datum').value,farbe:document.getElementById('f-farbe').value});
  schliesseModal();notify('Eintrag erstellt');ladeProjekt(aktivProjekt.id);
}
async function speichernEintragEdit(id){
  const t=document.getElementById('f-titel').value.trim();
  if(!t)return notify('Titel eingeben','error');
  await api('eintrag_aktualisieren',{id,titel:t,beschreibung:document.getElementById('f-desc').value,phase:document.getElementById('f-phase').value,phase_datum:document.getElementById('f-datum').value,farbe:document.getElementById('f-farbe').value});
  schliesseModal();notify('Eintrag gespeichert');ladeProjekt(aktivProjekt.id);
}
async function loeschenEintrag(id){
  if(!confirm('Eintrag löschen?'))return;
  await api('eintrag_loeschen',{id});notify('Eintrag gelöscht');ladeProjekt(aktivProjekt.id);
}

// ============================================================
//  CRUD — Schritte
// ============================================================
async function speichernSchritt(eid){
  const t=document.getElementById('f-titel').value.trim();
  if(!t)return notify('Titel eingeben','error');
  await api('schritt_erstellen',{eintrag_id:eid,titel:t,beschreibung:document.getElementById('f-desc').value,phase:document.getElementById('f-phase').value,datum:document.getElementById('f-datum').value});
  schliesseModal();notify('Schritt hinzugefügt');ladeProjekt(aktivProjekt.id);
}
async function loeschenSchritt(id){
  if(!confirm('Schritt löschen?'))return;
  await api('schritt_loeschen',{id});notify('Schritt gelöscht');ladeProjekt(aktivProjekt.id);
}

// ============================================================
//  CRUD — Profil & Benutzer
// ============================================================
async function speichernProfil(){
  const pl={name:document.getElementById('f-name').value.trim()};
  const alt=document.getElementById('f-pass-alt').value;
  const neu=document.getElementById('f-pass-neu').value;
  const wdh=document.getElementById('f-pass-wdh').value;
  if(alt||neu||wdh){
    if(!alt)return notify('Bitte aktuelles Passwort eingeben','error');
    if(neu.length<6)return notify('Neues Passwort mind. 6 Zeichen','error');
    if(neu!==wdh)return notify('Passwörter stimmen nicht überein','error');
    pl.passwort_alt=alt;pl.passwort_neu=neu;
  }
  await api('profil_aendern',pl);
  if(pl.name)AKTUELLER_BENUTZER.name=pl.name;
  schliesseModal();notify('Profil gespeichert');
}
async function speichernBenutzer(){
  const name=document.getElementById('f-name').value.trim();
  const email=document.getElementById('f-email').value.trim();
  const pass=document.getElementById('f-pass').value;
  const rolle=document.getElementById('f-rolle').value;
  if(!name||!email||!pass)return notify('Alle Felder ausfüllen','error');
  await api('benutzer_erstellen',{name,email,passwort:pass,rolle});
  notify('Benutzer angelegt');
  zurueckZuBenutzerListe();
}
async function speichernBenutzerEdit(id){
  const pl={id,name:document.getElementById('f-name').value.trim(),rolle:document.getElementById('f-rolle').value,aktiv:parseInt(document.getElementById('f-aktiv').value)};
  const neu=document.getElementById('f-pass-neu').value;
  if(neu){if(neu.length<6)return notify('Passwort mind. 6 Zeichen','error');pl.passwort_neu=neu;}
  await api('benutzer_aktualisieren',pl);
  notify('Benutzer gespeichert');
  zurueckZuBenutzerListe();
}
async function loeschenBenutzer(id){
  if(!confirm('Benutzer wirklich löschen?'))return;
  await api('benutzer_loeschen',{id});
  notify('Benutzer gelöscht');
  zurueckZuBenutzerListe();
}

// ============================================================
//  Start
// ============================================================
ladeSidebar();

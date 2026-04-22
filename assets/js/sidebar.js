/* ============================================================
   sidebar.js — Sidebar, Projekt laden, Tab-Steuerung
   ============================================================ */

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
        ${hatRecht('schreiben')?`<button class="btn btn-outline-secondary btn-sm" onclick="openModal('rubrik',{projekt_id:${aktivProjekt.id}})"><i class="bi bi-plus-lg"></i> Rubrik</button>`:''}
        ${hatRecht('verwalten')?`<button class="btn btn-outline-secondary btn-sm" onclick="openModal('projekt_edit')"><i class="bi bi-pencil"></i> Bearbeiten</button>`:''}
        ${IST_ADMIN?`<button class="btn btn-outline-secondary btn-sm" onclick="openModal('projekt_zugang')"><i class="bi bi-people"></i> Zugang</button>`:''}
        ${IST_ADMIN?`<button class="btn btn-outline-danger btn-sm" onclick="loeschenProjekt(${aktivProjekt.id})"><i class="bi bi-trash"></i></button>`:''}
      </div>
    </div>
    <ul class="nav app-tabs" id="projektTabs">
      <li class="nav-item">
        <button class="nav-link ${aktiverTab==='matrix'?'active':''}" onclick="switchTab('matrix',this)">
          <i class="bi bi-grid-3x3-gap me-1"></i><span class="d-none d-sm-inline">Aktivität</span>
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link ${aktiverTab==='rubriken'?'active':''}" data-tab="rubriken" onclick="switchTab('rubriken',this)">
          <i class="bi bi-folder me-1"></i><span class="d-none d-sm-inline">Rubriken</span>
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link ${aktiverTab==='board'?'active':''}" data-tab="board" onclick="switchTab('board',this)">
          <i class="bi bi-chat-dots me-1"></i><span class="d-none d-sm-inline">Board</span>
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link ${aktiverTab==='timeline'?'active':''}" data-tab="timeline" onclick="switchTab('timeline',this)">
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
  else if (tab==='board')    renderBoard();
  else                       renderTimeline(rubriken);
}
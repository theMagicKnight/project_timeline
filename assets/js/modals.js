/* ============================================================
   modals.js — Modal-Steuerung, Formulare, Benutzerverwaltung
   ============================================================ */

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
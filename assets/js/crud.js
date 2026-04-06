/* ============================================================
   crud.js — Alle Speichern & Löschen Funktionen
   ============================================================ */

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
/* ============================================================
   config.js — Konstanten & globaler State
   ============================================================ */

const FARBEN = ['#7c6af7','#f0b429','#34d399','#f87171','#38bdf8','#fb923c','#e879f9','#a3e635','#94a3b8'];

const PHASEN = {
  idee:        { label: 'Idee',        icon: '💡' },
  start:       { label: 'Start',       icon: '🚀' },
  entwicklung: { label: 'Entwicklung', icon: '⚙️' },
  abschluss:   { label: 'Abschluss',   icon: '✅' },
};

const SPRACHEN = [
  { val:'plaintext', label:'Text'       },
  { val:'php',       label:'PHP'        },
  { val:'javascript',label:'JavaScript' },
  { val:'html',      label:'HTML'       },
  { val:'css',       label:'CSS'        },
  { val:'sql',       label:'SQL'        },
  { val:'json',      label:'JSON'       },
  { val:'bash',      label:'Bash'       },
  { val:'python',    label:'Python'     },
];

const RECHTE_STUFEN = { lesen:1, schreiben:2, verwalten:3, admin:4 };

// Globaler App-State
let aktivProjekt  = null;
let aktiverTab    = 'matrix';
let aktivesRecht  = null;
let bsModal       = null;
let aktuellesTheme = AKTUELLER_BENUTZER.theme;
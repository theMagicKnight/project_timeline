/* ============================================================
   matrix.js — Aktivitäts-Matrix (GitHub-Style)
   ============================================================ */

function matrixWochen() {
  const w = window.innerWidth;
  if (w < 576)  return 26;   // Smartphone   → 6 Monate
  if (w < 992)  return 52;   // Tablet       → 12 Monate
  if (w < 1400) return 52;   // Desktop      → 12 Monate
  return 104;                // Großer Monitor → 24 Monate
}

function renderMatrix(rubriken) {
  const content = document.getElementById('content');
  const rawMap  = {};
  function addDay(ds) { if (!ds) return; const d=ds.slice(0,10); rawMap[d]=(rawMap[d]||0)+1; }
  (rubriken||[]).forEach(r=>(r.eintraege||[]).forEach(e=>{
    addDay(e.erstellt_am);
    (e.schritte||[]).forEach(s=>addDay(s.datum||s.erstellt_am));
  }));

  const wochen = matrixWochen();
  const today=new Date(); today.setHours(0,0,0,0);
  const start=new Date(today); start.setDate(start.getDate()-start.getDay()-wochen*7);
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

  const totalAkt  = vals.reduce((a,b)=>a+b,0);
  const aktivTage = vals.filter(v=>v>0).length;
  const CW=11,GAP=3;
  const monthHTML = monthLabels.map(m=>`<div class="matrix-month-label" style="left:${m.col*(CW+GAP)}px">${m.label}</div>`).join('');
  const gridHTML  = cols.map(col=>`<div class="matrix-col">${col.map(cell=>cell===null?`<div class="mc" data-l="0" style="opacity:0"></div>`:`<div class="mc" data-l="${cell.lv}" data-date="${cell.date}" data-count="${cell.count}" onmouseenter="showTip(event,this)" onmouseleave="hideTip()"></div>`).join('')}</div>`).join('');
  const wdLabels  = ['So','Mo','','Mi','','Fr',''].map(d=>`<div class="matrix-wd">${d}</div>`).join('');

  const counts={idee:0,start:0,entwicklung:0,abschluss:0};
  (rubriken||[]).forEach(r=>(r.eintraege||[]).forEach(e=>counts[e.phase]++));
  const pColors={idee:'var(--gold)',start:'var(--accent)',entwicklung:'var(--cyan)',abschluss:'var(--green)'};
  const phasebar     = Object.entries(counts).map(([p,n])=>`<div style="flex:${n||0.3};background:${pColors[p]}"></div>`).join('');
  const phaseLegend  = Object.entries(counts).map(([p,n])=>`<div class="phasebar-item"><span style="width:7px;height:7px;border-radius:50%;background:${pColors[p]};display:inline-block;flex-shrink:0"></span>${PHASEN[p].icon} ${PHASEN[p].label} <span style="color:var(--text3)">${n}</span></div>`).join('');
  const matrixTitel  = wochen <= 26 ? '6 Monate' : wochen <= 52 ? '12 Monate' : '24 Monate';

  content.innerHTML=`
    <div class="matrix-wrap">
      <div class="matrix-header">
        <div class="matrix-title"><i class="bi bi-grid-3x3-gap me-1"></i>Aktivität · letzte ${matrixTitel}</div>
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
function hideTip(){ document.getElementById('mtt').classList.remove('vis'); }
/* QCX eMag configurable tables — adapted from Time-Mobility-Tracker's
   ConfigurableDataTableScreen behavior, without its React/Dataverse dependencies. */
'use strict';
const QcxTable = (() => {
  const text = v => String(v ?? '');
  const escape = v => text(v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const blank = v => v == null || text(v).trim() === '';
  const clone = v => JSON.parse(JSON.stringify(v));
  function number(v) {
    if (blank(v)) return null;
    if (typeof v === 'number') return Number.isFinite(v) ? v : null;
    let s = text(v).trim().replace(/\s/g, '');
    if (!/^[+-]?[\d.,]+$/.test(s)) return null;
    if (s.includes(',') && s.includes('.')) {
      const dec = s.lastIndexOf(',') > s.lastIndexOf('.') ? ',' : '.';
      s = s.split(dec === ',' ? '.' : ',').join('').replace(',', '.');
    } else if (s.includes(',')) s = s.replace(',', '.');
    const n = Number(s);
    return Number.isFinite(n) ? n : null;
  }
  function date(v) {
    if (blank(v)) return null;
    const s = text(v), m = s.match(/^(\d{4})-(\d{2})-(\d{2})/) || s.match(/^(\d{2})[./](\d{2})[./](\d{4})$/);
    if (!m) return null;
    const [y, mo, d] = m[1].length === 4 ? [m[1], m[2], m[3]] : [m[3], m[2], m[1]];
    const t = Date.UTC(+y, +mo - 1, +d), dt = new Date(t);
    return dt.getUTCFullYear() === +y && dt.getUTCMonth() === +mo - 1 && dt.getUTCDate() === +d ? t : null;
  }
  function typed(v, c) { return c.type === 'number' ? number(v) : c.type === 'date' ? date(v) : blank(v) ? null : text(v); }
  function compare(a, b, c) {
    const x = typed(a, c), y = typed(b, c);
    if (x === null) return y === null ? 0 : 1;
    if (y === null) return -1;
    return typeof x === 'number' && typeof y === 'number' ? x - y : text(x).localeCompare(text(y), 'ro', {numeric:true});
  }
  function matches(value, c) {
    if (!c.op) return true;
    if (c.op === 'empty') return blank(value);
    if (c.op === 'filled') return !blank(value);
    if (c.op === 'contains') return text(value).toLocaleLowerCase('ro').includes(text(c.filter).toLocaleLowerCase('ro'));
    if (c.op === 'starts') return text(value).toLocaleLowerCase('ro').startsWith(text(c.filter).toLocaleLowerCase('ro'));
    const x = typed(value, c), y = typed(c.filter, c);
    if (x === null || y === null) return false;
    const d = compare(x, y, {...c,type:c.type === 'date' ? 'number' : c.type});
    return ({eq:d===0, ne:d!==0, gt:d>0, ge:d>=0, lt:d<0, le:d<=0})[c.op] || false;
  }
  function format(v, c) {
    if (blank(v)) return '';
    if (c.type === 'number') {
      const n = number(v);
      return n === null ? text(v) : new Intl.NumberFormat('ro-RO', {minimumFractionDigits:c.decimals ?? 2,maximumFractionDigits:c.decimals ?? 2}).format(n);
    }
    if (c.type === 'date') { const t = typeof v === 'number' && Number.isFinite(v) ? v : date(v); return t === null ? text(v) : new Intl.DateTimeFormat('ro-RO', {timeZone:'UTC'}).format(t); }
    return text(v);
  }
  function aggregate(rows, c, get) {
    const vals = rows.map(r => typed(get(r, c), c)).filter(v => v !== null);
    if (c.total === 'count') return vals.length;
    if (!vals.length) return null;
    if (c.total === 'min') return vals.reduce((a,b)=>a<b?a:b);
    if (c.total === 'max') return vals.reduce((a,b)=>a>b?a:b);
    if (c.type !== 'number') return null;
    const sum = vals.reduce((a,b)=>a+b,0);
    return c.total === 'avg' ? sum / vals.length : sum;
  }
  function modal(title, html, trigger) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay show qdt-overlay';
    overlay.innerHTML = `<section class="modal modal-xl" role="dialog" aria-modal="true" aria-label="${escape(title)}" tabindex="-1"><div class="modal-head"><h3>${escape(title)}</h3><button type="button" class="modal-close" aria-label="Închide">×</button></div><div class="modal-body">${html}</div></section>`;
    document.body.append(overlay);
    const close = () => { document.removeEventListener('keydown', key); overlay.remove(); if(trigger?.isConnected)trigger.focus(); };
    const key = e => {
      if(e.key === 'Escape') { e.stopImmediatePropagation();close(); }
      if(e.key === 'Tab') {
        const els=[...overlay.querySelectorAll('button,input,select,textarea,[tabindex="0"]')].filter(el=>!el.disabled);
        const first=els[0],last=els[els.length-1];
        if(e.shiftKey && document.activeElement===first) {e.preventDefault();last?.focus();}
        else if(!e.shiftKey && document.activeElement===last) {e.preventDefault();first?.focus();}
      }
    };
    document.addEventListener('keydown',key);
    overlay.querySelector('.modal-close').onclick = close;
    overlay.addEventListener('click',e=>{if(e.target===overlay)close();});
    overlay.querySelector('.modal-close').focus();
    return {overlay,close};
  }
  function create({body,columns,key,title,onChange,identify,currency,paginate=false}) {
    const tbody=document.getElementById(body), table=tbody.closest('table'), wrap=table.parentElement;
    const originalHeads=[...table.tHead.rows[table.tHead.rows.length-1].cells].map(c=>c.cloneNode(true));
    const actionIndex=columns.length < originalHeads.length ? columns.length : -1;
    const numbering=table.tHead.rows.length>1;
    const base=columns.map((c,i)=>({type:'text',visible:true,width:Math.max(110,originalHeads[i]?.offsetWidth||0),decimals:2,align:c.type==='number'?'right':'left',...c,label:c.label||originalHeads[i]?.textContent.trim(),index:i,total:c.total||'',op:'',filter:'',sort:'',pin:false}));
    const user=typeof _currentUser !== 'undefined' ? _currentUser?.id : 'anonymous';
    const storageKey=`qcx.table.v1.${user}.${key}`;
    const defaults=()=>({columns:clone(base),group:'',compact:true,pageSize:50});
    let state=defaults(),saved=[],selected=new Set(),lastRows=[],page=0;
    const rowId=identify || (r=>r.id ?? JSON.stringify(r));
    function reconcile(input) {
      const s=defaults(),seen=new Set();
      if(input && Array.isArray(input.columns)) {
        s.columns=[];
        input.columns.slice(0,100).forEach(sc=>{
          const b=base.find(c=>c.key===sc.key);
          if(!b || seen.has(b.key))return;
          seen.add(b.key);
          s.columns.push({...b,visible:sc.visible!==false,width:Math.min(600,Math.max(80,number(sc.width)||b.width)),decimals:Math.min(6,Math.max(0,number(sc.decimals)??b.decimals)),type:['text','number','date'].includes(sc.type)?sc.type:b.type,align:['left','center','right'].includes(sc.align)?sc.align:b.align,total:['sum','avg','min','max','count'].includes(sc.total)?sc.total:'',op:['contains','starts','eq','ne','gt','ge','lt','le','empty','filled'].includes(sc.op)?sc.op:'',filter:text(sc.filter),sort:['asc','desc'].includes(sc.sort)?sc.sort:'',pin:!!sc.pin});
        });
        base.forEach(c=>{if(!seen.has(c.key))s.columns.push(clone(c));});
        if(!s.columns.some(c=>c.visible))s.columns[0].visible=true;
        s.group=base.some(c=>c.key===input.group)?input.group:'';
        s.compact=input.compact!==false;
        s.pageSize=[25,50,100,250].includes(input.pageSize)?input.pageSize:50;
      }
      return s;
    }
    try { const rec=JSON.parse(localStorage.getItem(storageKey)||'null'); if(rec){state=reconcile(rec.state);saved=Array.isArray(rec.saved)?rec.saved.slice(0,50):[];} } catch(_) { /* Invalid/unavailable storage leaves the original view intact. */ }
    function persist() {try {localStorage.setItem(storageKey,JSON.stringify({state,saved}));}catch(_){toast('Configurația nu poate fi salvată în acest browser.','error');}}
    const get=(r,c)=>{const source=base.find(b=>b.key===c.key);return source?.get?source.get(r):r[c.key];};
    const visible=()=>state.columns.filter(c=>c.visible);
    function apply(rows) {
      lastRows=rows.filter(r=>state.columns.every(c=>matches(get(r,c),c)));
      const sorts=state.columns.filter(c=>c.sort && c.key!==state.group);
      const group=state.columns.find(c=>c.key===state.group);
      lastRows=lastRows.map((r,i)=>({r,i})).sort((a,b)=>{
        if(group){const d=compare(get(a.r,group),get(b.r,group),group);if(d)return d;}
        for(const c of sorts){const d=compare(get(a.r,c),get(b.r,c),c)*(c.sort==='desc'?-1:1);if(d)return d;}
        return a.i-b.i;
      }).map(x=>x.r);
      return lastRows;
    }
    const toolbar=document.createElement('div');
    toolbar.className='qdt-toolbar';
    toolbar.innerHTML=`<button class="btn" data-qdt="config">⚙️ Coloane și filtre</button><select data-qdt="saved" aria-label="Configurație tabel"><option value="">Configurații salvate</option></select><button class="btn-icon" data-qdt="save" title="Salvează configurația" aria-label="Salvează configurația">💾</button><button class="btn-icon" data-qdt="delete" title="Șterge configurația salvată" aria-label="Șterge configurația salvată">🗑️</button><button class="btn-icon" data-qdt="reset" title="Resetează vizualizarea" aria-label="Resetează vizualizarea">↺</button><button class="btn" data-qdt="excel">Excel</button><button class="btn" data-qdt="csv">CSV</button><button class="btn" data-qdt="print">Print / PDF</button><button class="btn" data-qdt="chart">📊 Grafic</button><button class="btn-icon" data-qdt="help" aria-label="Informații tabel" title="Informații tabel">i</button><span class="hint" data-qdt="count"></span><button class="btn-icon" data-qdt="unselect" aria-label="Anulează selecția" title="Anulează selecția">☐</button>`;
    wrap.before(toolbar);
    table.classList.add('qdt-table');
    function configs() {
      const sel=toolbar.querySelector('[data-qdt="saved"]');
      sel.innerHTML='<option value="">Configurații salvate</option>'+saved.map((c,i)=>`<option value="${i}">${escape(c.name)}</option>`).join('');
    }
    configs();
    const pager = paginate ? document.createElement('div') : null;
    if(pager) {
      pager.className='pagination';
      pager.innerHTML='<button type="button" data-prev aria-label="Pagina anterioară">‹</button><span data-page></span><button type="button" data-next aria-label="Pagina următoare">›</button><select aria-label="Rânduri pe pagină"><option value="25">25/pag</option><option value="50">50/pag</option><option value="100">100/pag</option><option value="250">250/pag</option></select>';
      wrap.after(pager);pager.querySelector('select').value=String(state.pageSize);
      pager.querySelector('[data-prev]').onclick=()=>{page=Math.max(0,page-1);onChange();};
      pager.querySelector('[data-next]').onclick=()=>{page++;onChange();};
      pager.querySelector('select').onchange=e=>{state.pageSize=Number(e.target.value);changed();};
    }
    function pageRows(rows) {
      if(!pager)return rows;
      const pages=Math.max(1,Math.ceil(rows.length/state.pageSize));page=Math.min(page,pages-1);
      pager.querySelector('[data-page]').textContent=`Pagina ${page+1} / ${pages}`;
      pager.querySelector('[data-prev]').disabled=page===0;pager.querySelector('[data-next]').disabled=page>=pages-1;
      pager.querySelector('select').value=String(state.pageSize);
      return rows.slice(page*state.pageSize,(page+1)*state.pageSize);
    }
    function changed() {page=0;persist();onChange();}
    const scope=()=>selected.size?lastRows.filter(r=>selected.has(text(rowId(r)))):lastRows;
    function summary(rows,c) {
      if(!c.total)return '';
      if(c.currency && currency) {
        const groups=new Map();
        rows.forEach(r=>{const k=text(currency(r));if(!groups.has(k))groups.set(k,[]);groups.get(k).push(r);});
        return [...groups].map(([k,rs])=>`${format(aggregate(rs,c,get),{...c,type:'number'})} ${k}`).join(' · ');
      }
      const v=aggregate(rows,c,get);
      return v===null?'':c.total==='count'?text(v):format(v,c);
    }
    function rendered(rows) {
      const cols=visible();
      const colgroup=table.querySelector('colgroup');if(colgroup)colgroup.remove();
      table.classList.toggle('compact',state.compact);
      const header=document.createElement('tr');
      header.innerHTML='<th class="qdt-select"><input type="checkbox" aria-label="Selectează pagina"/></th>';
      let left=36;
      cols.forEach(c=>{
        const th=originalHeads[c.index].cloneNode(true);th.removeAttribute('colspan');th.style.width=c.width+'px';th.style.minWidth=c.width+'px';
        th.style.textAlign=c.align;th.setAttribute('aria-sort',c.sort==='asc'?'ascending':c.sort==='desc'?'descending':'none');
        th.innerHTML=`<button class="qdt-sort" title="Sortează ${escape(c.label)}" aria-label="Sortează ${escape(c.label)}">${escape(c.label)} ${c.sort==='asc'?'↑':c.sort==='desc'?'↓':''}${c.op?' ⏷':''}</button><span class="qdt-resize" role="separator" tabindex="0" aria-label="Lățime ${escape(c.label)}" aria-orientation="vertical"></span>`;
        if(c.pin){th.classList.add('qdt-pin');th.style.left=left+'px';left+=c.width;}
        th.querySelector('button').onclick=e=>{
          if(!e.shiftKey)state.columns.forEach(x=>{if(x!==c)x.sort='';});
          c.sort=c.sort===''?'asc':c.sort==='asc'?'desc':'';changed();
        };
        const resize=th.querySelector('.qdt-resize');
        resize.onkeydown=e=>{if(e.key==='ArrowLeft'||e.key==='ArrowRight'){e.preventDefault();c.width=Math.min(600,Math.max(80,c.width+(e.key==='ArrowLeft'?-10:10)));changed();}};
        resize.onpointerdown=e=>{
          e.preventDefault();const start=e.clientX,old=c.width;resize.setPointerCapture(e.pointerId);
          resize.onpointermove=ev=>{c.width=Math.min(600,Math.max(80,old+ev.clientX-start));th.style.width=c.width+'px';th.style.minWidth=c.width+'px';};
          resize.onpointerup=()=>{resize.onpointermove=null;resize.onpointerup=null;changed();};
        };
        header.append(th);
      });
      if(actionIndex>=0)header.append(originalHeads[actionIndex].cloneNode(true));
      table.tHead.replaceChildren(header);
      if(numbering){const nums=document.createElement('tr');nums.className='excel-colnum';nums.innerHTML=`<th>№</th>${cols.map((_,i)=>`<th>${i+1}</th>`).join('')}${actionIndex>=0?'<th>⚙️</th>':''}`;table.tHead.prepend(nums);}
      const domRows=[...tbody.rows];
      domRows.forEach((tr,i)=>{
        if(!rows[i]){tr.cells[0]?.setAttribute('colspan',text(cols.length+1+(actionIndex>=0?1:0)));return;}
        const row=rows[i],id=text(rowId(row)),cells=[...tr.cells];
        const select=document.createElement('td');select.className='qdt-select';
        select.innerHTML=`<input type="checkbox" aria-label="Selectează rândul ${i+1}" ${selected.has(id)?'checked':''}/><button class="btn-icon qdt-detail" aria-label="Detalii rând ${i+1}" title="Detalii">i</button>`;
        select.querySelector('input').onchange=e=>{e.target.checked?selected.add(id):selected.delete(id);counts();};
        select.querySelector('button').onclick=e=>details(row,e.currentTarget);
        let offset=36;tr.replaceChildren(select);
        cols.forEach(c=>{
          const td=cells[c.index];td.style.textAlign=c.align;td.style.minWidth=c.width+'px';td.style.width=c.width+'px';
          if(c.type!==base[c.index].type||c.decimals!==base[c.index].decimals)td.textContent=format(get(row,c),c);
          if(c.pin){td.classList.add('qdt-pin');td.style.left=offset+'px';offset+=c.width;}
          tr.append(td);
        });
        if(actionIndex>=0)tr.append(cells[actionIndex]);
      });
      header.querySelector('input').checked=rows.length>0&&rows.every(r=>selected.has(text(rowId(r))));
      header.querySelector('input').onchange=e=>{rows.forEach(r=>e.target.checked?selected.add(text(rowId(r))):selected.delete(text(rowId(r))));renderedSelection(rows);};
      // Group summaries use all filtered rows, including those on other pages.
      const group=state.columns.find(c=>c.key===state.group);
      if(group){
        const groups=new Map();lastRows.forEach(r=>{const k=text(get(r,group));if(!groups.has(k))groups.set(k,[]);groups.get(k).push(r);});
        let prev=null;
        domRows.forEach((tr,i)=>{if(!rows[i])return;const k=text(get(rows[i],group));if(k===prev)return;prev=k;const gr=document.createElement('tr');gr.className='qdt-group';gr.innerHTML=`<td colspan="${cols.length+1+(actionIndex>=0?1:0)}"><b>${escape(group.label)}: ${escape(k||'—')}</b> · ${groups.get(k).length} rânduri ${cols.filter(c=>c.total).map(c=>` · ${escape(c.label)} (${escape(c.total)}): ${escape(summary(groups.get(k),c))}`).join('')}</td>`;tr.before(gr);});
      }
      let foot=table.querySelector('tfoot.qdt-footer');if(!foot){foot=document.createElement('tfoot');foot.className='qdt-footer';table.append(foot);}
      foot.innerHTML=cols.some(c=>c.total)?`<tr><td>Σ</td>${cols.map(c=>`<td style="text-align:${c.align}" title="${escape(c.total)}">${escape(summary(lastRows,c))}</td>`).join('')}${actionIndex>=0?'<td></td>':''}</tr>`:'';
      table.querySelectorAll('tfoot:not(.qdt-footer)').forEach(f=>{f.hidden=cols.some(c=>c.total);});
      counts();
    }
    function renderedSelection(rows) {const checks=tbody.querySelectorAll('td.qdt-select input');checks.forEach((el,i)=>{el.checked=selected.has(text(rowId(rows[i])));});counts();}
    function counts(){const n=scope().length;toolbar.querySelector('[data-qdt="count"]').textContent=`${lastRows.length} rânduri${selected.size?` · ${n} selectate în rezultat`:''}`;toolbar.querySelector('[data-qdt="unselect"]').disabled=!selected.size;}
    function details(row,trigger){modal(`${title} · Detalii`, `<dl class="qdt-details">${state.columns.map(c=>`<dt>${escape(c.label)}</dt><dd>${escape(format(get(row,c),c))||'—'}</dd>`).join('')}</dl>`,trigger);}
    function settings(trigger) {
      const draft=clone(state);
      const dlg=modal(`${title} · Coloane și filtre`, `<form class="qdt-settings"><div class="qdt-toolbar"><label>Grupare <select name="group"><option value="">Fără grupare</option>${state.columns.map(c=>`<option value="${escape(c.key)}" ${c.key===state.group?'selected':''}>${escape(c.label)}</option>`).join('')}</select></label><label><input name="compact" type="checkbox" ${state.compact?'checked':''}/> Compact</label><input type="search" class="filter" name="columnSearch" placeholder="Caută coloană" aria-label="Caută coloană"/><button class="btn btn-primary" type="submit">Aplică</button></div><div class="table-wrap qdt-settings-wrap"><table class="data-table compact"><thead><tr><th>Coloană</th><th>Afișare</th><th>Fixă</th><th>Ordine</th><th>Tip</th><th>Aliniere</th><th>Lățime</th><th>Zecimale</th><th>Agregare</th><th>Filtru</th><th>Valoare</th></tr></thead><tbody>${draft.columns.map(c=>`<tr data-key="${escape(c.key)}"><td>${escape(c.label)}</td><td><input type="checkbox" data-field="visible" aria-label="Afișare ${escape(c.label)}" ${c.visible?'checked':''}/></td><td><input type="checkbox" data-field="pin" aria-label="Fixă ${escape(c.label)}" ${c.pin?'checked':''}/></td><td><button type="button" data-move="-1" aria-label="Mută în sus ${escape(c.label)}">↑</button><button type="button" data-move="1" aria-label="Mută în jos ${escape(c.label)}">↓</button></td><td><select data-field="type" aria-label="Tip ${escape(c.label)}">${[['text','Text'],['number','Număr'],['date','Dată']].map(([v,l])=>`<option value="${v}" ${v===c.type?'selected':''}>${l}</option>`).join('')}</select></td><td><select data-field="align" aria-label="Aliniere ${escape(c.label)}">${[['left','Stânga'],['center','Centru'],['right','Dreapta']].map(([v,l])=>`<option value="${v}" ${v===c.align?'selected':''}>${l}</option>`).join('')}</select></td><td><input type="number" min="80" max="600" value="${c.width}" data-field="width" aria-label="Lățime ${escape(c.label)}"/></td><td><input type="number" min="0" max="6" value="${c.decimals}" data-field="decimals" aria-label="Zecimale ${escape(c.label)}"/></td><td><select data-field="total" aria-label="Agregare ${escape(c.label)}">${[['','—'],['sum','Sumă'],['avg','Medie'],['min','Minim'],['max','Maxim'],['count','Număr']].map(([v,l])=>`<option value="${v}" ${v===c.total?'selected':''}>${l}</option>`).join('')}</select></td><td><select data-field="op" aria-label="Filtru ${escape(c.label)}">${[['','Fără filtru'],['contains','Conține'],['starts','Începe cu'],['eq','='],['ne','≠'],['gt','>'],['ge','≥'],['lt','<'],['le','≤'],['empty','Gol'],['filled','Completat']].map(([v,l])=>`<option value="${v}" ${v===c.op?'selected':''}>${l}</option>`).join('')}</select></td><td><input data-field="filter" value="${escape(c.filter)}" aria-label="Valoare filtru ${escape(c.label)}"/></td></tr>`).join('')}</tbody></table></div></form>`,trigger);
      const form=dlg.overlay.querySelector('form');
      form.oninput=e=>{if(e.target.name==='columnSearch'){const q=e.target.value.toLocaleLowerCase('ro');form.querySelectorAll('tr[data-key]').forEach(tr=>tr.hidden=!tr.cells[0].textContent.toLocaleLowerCase('ro').includes(q));}};
      form.onclick=e=>{const b=e.target.closest('[data-move]');if(!b)return;const tr=b.closest('tr'),delta=+b.dataset.move,target=delta<0?tr.previousElementSibling:tr.nextElementSibling;if(target){delta<0?target.before(tr):target.after(tr);}};
      form.onsubmit=e=>{
        e.preventDefault();draft.columns=[...form.querySelectorAll('tr[data-key]')].map(tr=>{const c=draft.columns.find(c=>c.key===tr.dataset.key);tr.querySelectorAll('[data-field]').forEach(el=>{c[el.dataset.field]=el.type==='checkbox'?el.checked:el.type==='number'?+el.value:el.value;});return c;});
        if(!draft.columns.some(c=>c.visible)){toast('Păstrează cel puțin o coloană vizibilă.','error');return;}
        const invalid=draft.columns.find(c=>c.total && ['sum','avg'].includes(c.total) && c.type!=='number');
        if(invalid){toast(`Sumă / medie necesită tip Număr: ${invalid.label}.`,'error');return;}
        draft.group=form.elements.group.value;draft.compact=form.elements.compact.checked;state=reconcile(draft);dlg.close();changed();
      };
    }
    function exportMatrix(){const cols=visible();return [cols.map(c=>c.label),...scope().map(r=>cols.map(c=>{const v=get(r,c);return c.type==='number'?number(v):c.type==='date'?format(v,c):text(v);} ))];}
    function download(data,name,type){const a=document.createElement('a'),url=URL.createObjectURL(new Blob([data],{type}));a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
    toolbar.onclick=async e=>{
      const b=e.target.closest('[data-qdt]');if(!b || b.tagName==='SELECT')return;
      const action=b.dataset.qdt;
      if(action==='config')settings(b);
      if(action==='help')modal('Tabel configurabil', '<p>Sortați o coloană prin clic pe antet; Shift + clic păstrează sortările anterioare. Reglați lățimea prin marginea antetului sau săgețile tastaturii.</p><p>Coloane și filtre controlează afișarea, ordinea, tipul, formatul, gruparea și agregările. Totalurile folosesc toate rândurile filtrate; sumele în valută sunt separate pe monede.</p><p>Excel, CSV, graficul și Print / PDF folosesc rândurile filtrate sau selecția din rezultat și coloanele vizibile, inclusiv rândurile de pe alte pagini. Acțiunile nu modifică datele contabile.</p><p>Configurațiile se păstrează în acest browser, separat pentru fiecare utilizator și tabel.</p>',b);
      if(action==='unselect'){selected.clear();onChange();}
      if(action==='reset'){state=defaults();selected.clear();changed();}
      if(action==='save'){
        const dlg=modal('Salvează configurația', '<form><label>Nume <input name="name" required maxlength="80"/></label><button type="submit" class="btn btn-primary">Salvează</button></form>',b);
        dlg.overlay.querySelector('form').onsubmit=async ev=>{ev.preventDefault();const name=ev.target.elements.name.value.trim();if(!name)return;const exists=saved.find(c=>c.name===name);if(exists && !await confirmDialog(`Suprascrii configurația <b>${escape(name)}</b>?`))return;if(exists)exists.state=clone(state);else saved.push({name,state:clone(state)});persist();configs();dlg.close();};
      }
      if(action==='delete'){const sel=toolbar.querySelector('[data-qdt="saved"]');if(sel.value==='')return;const i=+sel.value;if(await confirmDialog(`Ștergi configurația <b>${escape(saved[i].name)}</b>?`)){saved.splice(i,1);persist();configs();}}
      if(['excel','csv','print','chart'].includes(action) && !scope().length){toast('Niciun rând în selecția curentă.','info');return;}
      if(action==='excel'){if(typeof XLSX==='undefined'){toast('Biblioteca Excel nu este încărcată.','error');return;}const wb=XLSX.utils.book_new(),ws=XLSX.utils.aoa_to_sheet(exportMatrix());XLSX.utils.book_append_sheet(wb,ws,'Date');XLSX.writeFile(wb,`${key}_${new Date().toISOString().slice(0,10)}.xlsx`);}
      if(action==='csv'){const matrix=exportMatrix(),safe=v=>{const s=text(v);return /^[=+@\-\t\r]/.test(s) && typeof v!=='number'?"'"+s:s;};download('\ufeff'+matrix.map(r=>r.map(v=>'"'+safe(v).replace(/"/g,'""')+'"').join(';')).join('\r\n'),`${key}.csv`,'text/csv;charset=utf-8');}
      if(action==='print'){
        const cols=visible(),rows=scope();const dlg=modal(`${title} · Print / PDF`, `<button class="btn" data-print>Printează / Salvează PDF</button><div class="qdt-print-content"><h2>${escape(title)}</h2><p>${rows.length} rânduri · ${new Date().toLocaleDateString('ro-RO')}</p><table><thead><tr>${cols.map(c=>`<th>${escape(c.label)}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${cols.map(c=>`<td>${escape(format(get(r,c),c))}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`,b);
        dlg.overlay.querySelector('[data-print]').onclick=()=>{const done=()=>{document.body.classList.remove('qdt-print-mode');window.removeEventListener('afterprint',done);};document.body.classList.add('qdt-print-mode');window.addEventListener('afterprint',done);try{window.print();}catch(_){done();}};
      }
      if(action==='chart')chart(b);
    };
    toolbar.querySelector('[data-qdt="saved"]').onchange=e=>{if(e.target.value==='')return;state=reconcile(saved[+e.target.value].state);changed();};
    function chart(trigger){
      if(typeof Chart==='undefined'){toast('Biblioteca de grafice nu este încărcată.','error');return;}
      const cols=state.columns,nums=cols.filter(c=>c.type==='number');
      const dlg=modal(`${title} · Grafic`, `<div class="qdt-toolbar"><label>Grupare <select data-group>${cols.map(c=>`<option value="${escape(c.key)}" ${c.key===state.group?'selected':''}>${escape(c.label)}</option>`).join('')}</select></label><label>Valoare <select data-value><option value="">Număr rânduri</option>${nums.map(c=>`<option value="${escape(c.key)}">${escape(c.label)}</option>`).join('')}</select></label><label>Agregare <select data-metric><option value="sum">Sumă</option><option value="avg">Medie</option><option value="min">Minim</option><option value="max">Maxim</option></select></label></div><div class="qdt-chart"><canvas></canvas></div><div data-kpi class="totals"></div>`,trigger);
      let chartObject=null;
      const draw=()=>{const g=cols.find(c=>c.key===dlg.overlay.querySelector('[data-group]').value),v=cols.find(c=>c.key===dlg.overlay.querySelector('[data-value]').value),metric=dlg.overlay.querySelector('[data-metric]').value,groups=new Map();scope().forEach(r=>{const k=text(get(r,g))+(v?.currency&&currency?' · '+text(currency(r)):'');if(!groups.has(k))groups.set(k,[]);groups.get(k).push(r);});const entries=[...groups].map(([label,rs])=>({label,value:v?aggregate(rs,{...v,total:metric},get):rs.length})).sort((a,b)=>(b.value??0)-(a.value??0));chartObject?.destroy();chartObject=new Chart(dlg.overlay.querySelector('canvas'),{type:'bar',data:{labels:entries.slice(0,30).map(e=>e.label||'—'),datasets:[{label:v?v.label:'Număr rânduri',data:entries.slice(0,30).map(e=>e.value),backgroundColor:'#168b9a'}]},options:{responsive:true,maintainAspectRatio:false}});dlg.overlay.querySelector('[data-kpi]').textContent=`${scope().length} rânduri · ${groups.size} grupuri${entries.length>30?' · primele 30 grupuri afișate':''}`;};
      dlg.overlay.querySelectorAll('select').forEach(s=>s.onchange=draw);draw();
      const observer=new MutationObserver(()=>{if(!dlg.overlay.isConnected){chartObject?.destroy();observer.disconnect();}});observer.observe(document.body,{childList:true});
    }
    return {apply,rendered,pageRows,exportMatrix,getState:()=>clone(state),setState:s=>{state=reconcile(s);changed();},getPageSize:()=>state.pageSize,setPageSize:n=>{state.pageSize=n;persist();}};
  }
  return {create,number,date,compare,matches,aggregate};
})();

const fs=require('node:fs'),assert=require('node:assert/strict'),path=require('node:path'),vm=require('node:vm');
const {JSDOM}=require(require.resolve('jsdom',{paths:[process.env.QCX_TEST_NODE_MODULES || path.resolve(__dirname,'..')]}));
const root=path.resolve(__dirname,'..'),php=fs.readFileSync(path.join(root,'public_mkpemag/index.php'),'utf8');
const scripts=[...php.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)].map(m=>m[1]).filter(s=>s.trim() && !s.includes('<?php') && !s.includes('<?='));
for(const code of scripts)new vm.Script(code);
const source=fs.readFileSync(path.join(root,'public_mkpemag/configurable-data-table.js'),'utf8');new vm.Script(source);
const dom=new JSDOM('<html><body><main id="content"></main><div id="toasts"></div></body></html>',{url:'https://qcx.test/',runScripts:'outside-only',pretendToBeVisual:true});
const w=dom.window;
w.eval(source+'\n'+scripts.map(s=>s.replace("window.addEventListener('DOMContentLoaded', init);",'')).join('\n')+'\nObject.assign(window,{QcxTable,setCurrentUser,renderRawData,renderDataSet,renderNomenclator,renderMarketplaces,renderCursValutar,renderJurnal,renderRaportDinamic});window.useStores=stores=>{dbGetAll=async s=>stores[s]||[];dbCount=async s=>(stores[s]||[]).length;};');
w.eval("setCurrentUser({id:1,is_admin:true});");
const fixtureRow={id:1,Canal:'RO',IDSeller:1441,Moneda:'RON',Flux:'Cumparare | Intrare',CodTipFactura:'FC',TipFactura:'FC',DataEmitere:'2026-10-01',DataScadenta:'2026-10-31',SerieNumar:'ABCDEF-123',Referinta:'ABC123',Valoare:100,ValoareFaraTVA:100,ValoareTVA:21,ValoareCuTVA:121,CotaTVA:21,Ron_Val:100,Ron_TVA:21,An:2026,Luna:10,Articol:'Comision marketplace',ContTert:401,ContTz:622,CursValutar:1,TipTranzactie:'1_Recunoastere',NaturaEconomica:'Comision',TipTaxare:'Taxare normala'};
const stores={rawData:Array.from({length:120},(_,i)=>({...fixtureRow,id:i+1,ValoareFaraTVA:i+1})),dataSet:Array.from({length:120},(_,i)=>({...fixtureRow,id:i+1,Ron_Val:i+1})),marketplaces:[{IdSeller:1441,Marketplace:'RO',Moneda:'RON',AF:'RO',CotaTVA:21,Demultiplicator:1,Tara:'România',Localitate:'București'}],nomenclator:[{Cod:'FC',Tranzactie:'Comision',Articol:'Comision',TipTVA:1,Status:'Activ','Cont factura':401,'Cont articol':622}],cursValutar:[{Data:'2026-10-01',Valuta:'EUR',Demultiplicator:1,Curs:5.1,Sursa:'BNR'}],settings:[]};
w.useStores(stores);
const rootEl=w.document.getElementById('content');
function click(el){assert.ok(el,'control exists');el.click();}
(async()=>{
  const Q=w.QcxTable;
  assert.equal(Q.number('1.234,56'),1234.56);assert.equal(Q.number('12abc'),null);assert.equal(Q.number(0),0);
  assert.equal(Q.date('31.02.2026'),null);assert.equal(Q.date('04.10.2026'),Date.UTC(2026,9,4));
  assert.ok(Q.matches('2026-10-04',{type:'date',op:'ge',filter:'2026-10-01'}));
  assert.ok(Q.matches(0,{type:'number',op:'eq',filter:'0'}));assert.ok(!Q.matches('',{type:'number',op:'eq',filter:'0'}));
  await w.renderRawData(rootEl);
  assert.equal(rootEl.querySelectorAll('#rawBody tr').length,50);
  assert.equal(rootEl.querySelectorAll('#rawBody tr')[0].cells.length,28);
  click(rootEl.querySelector('button[aria-label="Sortează Valoare fara TVA"]'));
  assert.match(rootEl.querySelector('#rawBody tr').textContent,/1,00/);
  click(rootEl.querySelector('button[aria-label="Sortează Valoare fara TVA"]'));
  assert.match(rootEl.querySelector('#rawBody tr').textContent,/120,00/);
  click(rootEl.querySelector('#rawBody input'));
  assert.match(rootEl.querySelector('[data-qdt=count]').textContent,/1 selectate/);
  click(rootEl.querySelector('[data-qdt=config]'));
  const modal=w.document.querySelector('.qdt-overlay');
  const rate=modal.querySelector('[data-key="ValoareFaraTVA"]');
  rate.querySelector('[data-field=op]').value='ge';rate.querySelector('[data-field=filter]').value='100';
  modal.querySelector('[data-key="Seller"] [data-field=visible]').checked=false;
  modal.querySelector('[data-key="Canal"] [data-field=pin]').checked=true;
  modal.querySelector('form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  assert.equal(rootEl.querySelectorAll('#rawBody tr').length,21);
  assert.equal(rootEl.querySelector('#rawBody tr').cells.length,27);
  assert.ok(rootEl.querySelector('#rawBody .qdt-pin'));
  assert.equal(rootEl.querySelector('#rawBody [data-act=edit]').title,'Editează');
  await w.renderRawData(rootEl);assert.equal(rootEl.querySelectorAll('#rawBody tr').length,21,'config persists on rerender');
  click(rootEl.querySelector('.qdt-detail'));assert.match(w.document.querySelector('.qdt-details').textContent,/Canal/);
  click(w.document.querySelector('.qdt-overlay .modal-close'));
  click(rootEl.querySelector('#rawBody [data-act=edit]'));assert.ok(w.document.querySelector('.modal-form'),'original editor retained');
  click(w.document.querySelector('.modal-close'));
  await w.renderDataSet(rootEl);assert.equal(rootEl.querySelectorAll('#dsBody tr').length,50);
  let dsSize=rootEl.querySelector('#dsPageSize');dsSize.value='100';dsSize.dispatchEvent(new w.Event('change'));assert.equal(rootEl.querySelectorAll('#dsBody tr').length,100);
  await w.renderNomenclator(rootEl);assert.match(rootEl.querySelector('#nomBody').textContent,/FC/);assert.ok(rootEl.querySelector('#nomBody [data-act=edit]'));
  await w.renderMarketplaces(rootEl);assert.match(rootEl.querySelector('#mpBody').textContent,/București/);
  // Getter survives persistence/reconciliation, so derived-country filters work.
  click(rootEl.querySelector('[data-qdt=config]'));const country=w.document.querySelector('[data-key="Tara"]');country.querySelector('[data-field=op]').value='contains';country.querySelector('[data-field=filter]').value='București';w.document.querySelector('.qdt-overlay form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));assert.match(rootEl.querySelector('#mpBody').textContent,/București/);
  stores.cursValutar=Array.from({length:60},(_,i)=>({Data:'2026-10-01',Valuta:'EUR',Demultiplicator:1,Curs:5+i/100,Sursa:'BNR'}));
  await w.renderCursValutar(rootEl);assert.equal(rootEl.querySelectorAll('#cursBody tr').length,50);click(rootEl.querySelector('[data-next]'));assert.equal(rootEl.querySelectorAll('#cursBody tr').length,10);assert.match(rootEl.querySelector('#cursBody').textContent,/EUR/);
  await w.renderJurnal(rootEl);assert.ok(rootEl.querySelectorAll('#jNoteBody tr').length>0);assert.equal(rootEl.querySelector('#jGrandTotal').textContent,'9.780,00');
  const group=rootEl.querySelector('select[aria-label="Adaugă coloană de grupare"]');group.value='5';group.dispatchEvent(new w.Event('change'));assert.ok(rootEl.querySelector('.qdt-group'));
  // Configurations round-trip through the save/apply UI.
  click(rootEl.querySelector('[data-qdt=save]'));
  let saveForm=w.document.querySelector('.qdt-overlay form');saveForm.elements.name.value='Jurnal grupat';
  saveForm.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  assert.match(rootEl.querySelector('[data-qdt=saved]').textContent,/Jurnal grupat/);
  click(rootEl.querySelector('[data-qdt=reset]'));assert.equal(rootEl.querySelector('.qdt-group'),null);
  const cfg=rootEl.querySelector('[data-qdt=saved]');cfg.value='0';cfg.dispatchEvent(new w.Event('change'));assert.ok(rootEl.querySelector('.qdt-group'));
  // Empty results must leave a usable table and no stale currency totals.
  await w.renderRawData(rootEl);const search=rootEl.querySelector('#rawFilter');search.value='no-such-invoice';search.dispatchEvent(new w.Event('input'));assert.match(rootEl.querySelector('#rawBody').textContent,/Niciun rezultat/);
  click(rootEl.querySelector('[data-qdt=help]'));w.document.dispatchEvent(new w.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));assert.equal(w.document.querySelector('.qdt-overlay'),null);
  // User isolation: the raw filter stored for user 1 must not affect user 2.
  w.eval('setCurrentUser({id:2,is_admin:false});');await w.renderRawData(rootEl);assert.equal(rootEl.querySelectorAll('#rawBody tr').length,50);
  // Pure engine: export uses all filtered rows and selection, never just a page.
  rootEl.innerHTML='<div><table><thead><tr><th>Valeur</th><th>Devise</th></tr></thead><tbody id="testBody"></tbody></table></div>';
  let view;const data=[{id:1,n:2,c:'RON'},{id:2,n:10,c:'EUR'},{id:3,n:0,c:'RON'}];
  function render(){const rows=view.apply(data);rootEl.querySelector('tbody').innerHTML=rows.slice(0,2).map(r=>`<tr><td>${r.n}</td><td>${r.c}</td></tr>`).join('');view.rendered(rows.slice(0,2));}
  view=Q.create({body:'testBody',key:'test',title:'Test',columns:[{key:'n',type:'number',total:'sum',currency:true},{key:'c'}],currency:r=>r.c,onChange:render});render();
  assert.equal(view.exportMatrix().length,4);
  const total=rootEl.querySelector('.qdt-footer').textContent;assert.match(total,/RON/);assert.match(total,/EUR/);
  click(rootEl.querySelector('#testBody input'));assert.equal(view.exportMatrix().length,2);assert.equal(view.exportMatrix()[1][0],2);
  // XLSX export preserves numeric zero and typed values without mutating sources.
  let exported;w.XLSX={utils:{book_new:()=>({}),aoa_to_sheet:m=>(exported=m),book_append_sheet:()=>{}},writeFile:()=>{}};
  click(rootEl.querySelector('[data-qdt=excel]'));assert.equal(exported.length,2);assert.equal(exported[1][0],2);
  click(rootEl.querySelector('[data-qdt=unselect]'));click(rootEl.querySelector('[data-qdt=print]'));
  assert.equal(w.document.querySelectorAll('.qdt-print-content tbody tr').length,3,'Print includes all rows across pages');
  click(w.document.querySelector('.qdt-overlay .modal-close'));
  // Grouping hides grouped columns only in the table, preserving them in exports.
  view.setState({...view.getState(),groups:['c','n']});
  assert.equal(rootEl.querySelectorAll('thead tr:not(.qdt-grouping-row) th').length,1);
  assert.equal(rootEl.querySelectorAll('.qdt-group[data-level="0"]').length,2);
  assert.equal(rootEl.querySelectorAll('.qdt-group[data-level="1"]').length,2);
  assert.equal(rootEl.querySelector('.qdt-group[data-level="0"] .qdt-group-label').style.paddingLeft,'0pt');
  assert.equal(rootEl.querySelector('.qdt-group[data-level="1"] .qdt-group-label').style.paddingLeft,'5pt');
  assert.equal(rootEl.querySelector('#testBody tr:not(.qdt-group)').style.getPropertyValue('--qdt-row-level'),'2');
  assert.match(rootEl.querySelector('.qdt-group').textContent,/EUR/);
  assert.equal(view.exportMatrix()[0].join(','),'Devise,Valeur');
  click(rootEl.querySelector('[data-qdt=config]'));
  assert.equal(w.document.querySelector('select[name=group]'),null,'grouping selector is outside the modal');
  w.document.querySelector('.qdt-overlay form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  assert.equal(view.getState().groups.join(','),'c,n','applying column settings retains groups');
  click(rootEl.querySelector('[data-collapse-all]'));
  assert.equal(rootEl.querySelectorAll('#testBody tr:not(.qdt-group):not([hidden])').length,0,'collapse all hides records');
  assert.equal(rootEl.querySelectorAll('.qdt-group[data-level="1"]').length,0,'collapsed parents hide child headers');
  click(rootEl.querySelector('[data-collapse-all]'));
  assert.equal(rootEl.querySelectorAll('#testBody tr:not(.qdt-group):not([hidden])').length,2);
  click(rootEl.querySelector('.qdt-group-toggle'));
  assert.equal(rootEl.querySelector('.qdt-group-toggle').getAttribute('aria-expanded'),'false');
  click(rootEl.querySelector('.qdt-group-toggle'));
  assert.equal(rootEl.querySelector('.qdt-group-toggle').getAttribute('aria-expanded'),'true');
  // Older single-column saved grouping is migrated.
  view.setState({...view.getState(),groups:undefined,group:'c'});
  assert.ok(rootEl.querySelector('#testBody .qdt-row-indent'));
  assert.equal(rootEl.querySelector('#testBody tr:not(.qdt-group)').style.getPropertyValue('--qdt-row-level'),'1');
  assert.equal(view.getState().groups.join(','),'c');
  view.setState({...view.getState(),groups:[]});
  assert.equal(rootEl.querySelector('#testBody .qdt-row-indent'),null,'ungrouped records have no indentation');
  click(rootEl.querySelector('[data-qdt=config]'));
  const pinForm=w.document.querySelector('.qdt-settings');
  for(const key of ['c','n']){
    const box=pinForm.querySelector(`[data-key="${key}"] [data-field=pin]`);
    box.checked=true;box.dispatchEvent(new w.Event('change',{bubbles:true}));
  }
  assert.equal([...pinForm.querySelectorAll('tr[data-key]')].map(r=>r.dataset.key).join(','),'c,n');
  pinForm.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  assert.equal(view.getState().columns.map(c=>c.key).join(','),'c,n');
  assert.equal(rootEl.querySelectorAll('thead .qdt-pin').length,2);
  // Zero width survives reconciliation and keyboard resizing, with recovery in settings.
  view.setState({...view.getState(),columns:view.getState().columns.map(c=>({...c,width:c.key==='n'?0:c.width}))});
  assert.equal(view.getState().columns.find(c=>c.key==='n').width,0);
  assert.equal(rootEl.querySelector('button[aria-label="Sortează Valeur"]'),null);
  view.setState({...view.getState(),columns:view.getState().columns.map(c=>({...c,width:c.key==='n'?5:c.width}))});
  rootEl.querySelector('[aria-label="Lățime Valeur"]').dispatchEvent(new w.KeyboardEvent('keydown',{key:'ArrowLeft',bubbles:true}));
  assert.equal(rootEl.querySelector('button[aria-label="Sortează Valeur"]'),null);
  // Calendar columns are computed, persisted and exported without changing imported rows.
  await w.renderRawData(rootEl);
  click(rootEl.querySelector('[data-qdt=config]'));
  let virtualForm=w.document.querySelector('.qdt-settings');
  virtualForm.elements.virtualSource.value='DataEmitere';virtualForm.elements.virtualPart.value='both';
  click(virtualForm.querySelector('[data-add-virtual]'));click(virtualForm.querySelector('[data-add-virtual]'));
  assert.equal(virtualForm.querySelectorAll('tr[data-key^="__qdt_"]').length,2,'no duplicate virtual columns');
  virtualForm.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  assert.ok(rootEl.querySelector('button[aria-label="Sortează An (Data emitere)"]'));
  assert.equal(stores.rawData[0].__qdt_year_DataEmitere,undefined);
  await w.renderRawData(rootEl);
  click(rootEl.querySelector('[data-qdt=config]'));
  virtualForm=w.document.querySelector('.qdt-settings');
  assert.equal(virtualForm.querySelectorAll('tr[data-key^="__qdt_"]').length,2,'virtual definitions persist');
  virtualForm.querySelector('[data-key="__qdt_month_DataEmitere"] [data-field=op]').value='eq';
  virtualForm.querySelector('[data-key="__qdt_month_DataEmitere"] [data-field=filter]').value='10';
  virtualForm.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  click(rootEl.querySelector('[data-qdt=excel]'));
  assert.equal(exported[1][exported[0].indexOf('An (Data emitere)')],2026);
  assert.equal(exported[1][exported[0].indexOf('Lună (Data emitere)')],10);
  // Every detailed table exposes both its help modal and row details.
  for(const renderer of ['renderRawData','renderDataSet','renderNomenclator','renderMarketplaces','renderCursValutar','renderJurnal']){
    await w[renderer](rootEl);
    click(rootEl.querySelector('[data-qdt=help]'));
    assert.ok(w.document.querySelector('.qdt-overlay [role=dialog]'),renderer+' help');
    click(w.document.querySelector('.qdt-overlay .modal-close'));
    click(rootEl.querySelector('.qdt-detail'));
    assert.ok(w.document.querySelector('.qdt-overlay .qdt-details'),renderer+' details');
    click(w.document.querySelector('.qdt-overlay .modal-close'));
  }
  w.eval('dbGet=async()=>null;');
  await w.renderRaportDinamic(rootEl);
  rootEl.querySelector('#rVirtualPart').value='both';click(rootEl.querySelector('#rAddVirtual'));
  assert.ok(rootEl.querySelector('[data-key="__qdt_year_DataEmitere"]'));
  assert.equal(stores.dataSet[0].__qdt_year_DataEmitere,undefined,'report uses a copy');
  click(rootEl.querySelector('#rPageHelpBtn'));
  assert.match(w.document.querySelector('.qdt-overlay').textContent,/Cum folosești pagina/);
  click(w.document.querySelector('.qdt-overlay .modal-close'));
  click(rootEl.querySelector('#rSelectAll')); // Rebuilds page header and its buttons.
  click(rootEl.querySelector('#rPageHelpBtn'));
  assert.ok(w.document.querySelector('.qdt-overlay [role=dialog]'),'page help survives buildUI');
  click(w.document.querySelector('.qdt-overlay .modal-close'));
  const reportFilter=rootEl.querySelector('[data-prop=filter]');
  reportFilter.value=reportFilter.tagName==='SELECT'?reportFilter.options[reportFilter.options.length-1].value:'FC';
  reportFilter.dispatchEvent(new w.Event('change'));
  click(rootEl.querySelector('#rApply'));
  click(rootEl.querySelector('#rFiltersInfoBtn'));
  assert.ok(w.document.querySelector('.qdt-overlay .r-filter-chip-clear'));
  click(w.document.querySelector('.qdt-overlay [data-clear-all]'));
  assert.equal(rootEl.querySelector('#rFiltersInfoBtn'),null,'clear all still updates report');
  assert.equal(w.document.querySelector('.qdt-overlay'),null);
  console.log('PASS: syntax, typed filters, dates, sort, selection, all-result export, currency-safe totals, configuration restoration, derived fields, six screens, original CRUD modal, user isolation');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>dom.window.close());

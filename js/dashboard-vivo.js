(() => {
 const root=document.getElementById('dashboard-vivo');if(!root)return;
 const status=document.getElementById('live-status'),money=new Intl.NumberFormat('es-PE',{style:'currency',currency:'PEN'});
 let reading=false,running=false;
 const text=(id,value)=>{document.getElementById(id).textContent=value;};
 function rows(id,items){const box=document.getElementById(id);box.replaceChildren();for(const value of items){const row=document.createElement('div');row.className='live-row';row.textContent=value;box.append(row);}}
 async function read(){if(reading)return;reading=true;try{const r=await fetch('dashboard_datos.php',{cache:'no-store'});if(!r.ok)throw Error();const j=await r.json();if(!j.ok)throw Error();const d=j.datos;
  text('live-ventas',money.format(Number(d.dia.ventas)));text('live-pedidos',d.dia.pedidos);text('live-agotados',d.stock.agotados);text('live-bajos',d.stock.bajos);text('live-demoras',d.demoras.total);
  rows('live-traza',d.ultima?d.ultima.traza.map(t=>`${t.agente} → ${t.destino} · ${t.estado} · ${t.duracion_ms} ms`):['Aún no se ha ejecutado el coordinador.']);
  rows('live-recomendaciones',(d.ultima?.analisis.recomendaciones||[]).map(x=>`${x.prioridad}: ${x.texto} Fuente: ${x.fuente}`));
  const forecastBox=document.getElementById('live-proyeccion');forecastBox.replaceChildren();
  for(const [tipo,p] of Object.entries(d.proyeccion)){
   const card=document.createElement('div');card.className='live-forecast';const heading=document.createElement('strong');heading.textContent=`${tipo.toUpperCase()} · ${p.estado}`;card.append(heading);
   const info=document.createElement('div');info.className='live-muted';info.textContent=`Corte: ${p.fecha_corte||'sin datos'} · Generado: ${p.generado||'sin informe'}`;card.append(info);
   if(p.estado==='Vigente'){
    const ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');svg.setAttribute('viewBox','0 0 300 80');svg.setAttribute('role','img');svg.setAttribute('aria-label',`Tendencia estimada de ${tipo} durante siete días`);svg.classList.add('live-svg');
    const line=document.createElementNS(ns,'polyline'),max=Math.max(1,...p.valores.map(Number)),min=Math.min(0,...p.valores.map(Number));line.setAttribute('points',p.valores.map((v,i)=>`${10+i*280/Math.max(1,p.valores.length-1)},${70-(v-min)*60/(max-min||1)}`).join(' '));line.setAttribute('fill','none');line.setAttribute('stroke','#67e8f9');line.setAttribute('stroke-width','3');svg.append(line);card.append(svg);
    const table=document.createElement('table');table.className='live-forecast-table';const caption=document.createElement('caption');caption.textContent=tipo==='ventas'?'Importe estimado en soles':'Cantidad estimada de pedidos';table.append(caption);
    p.dias.forEach((dia,i)=>{const row=table.insertRow(),date=row.insertCell(),value=row.insertCell();date.textContent=dia;value.textContent=tipo==='ventas'?money.format(p.valores[i]):Number(p.valores[i]).toFixed(0);});card.append(table);
    const metrics=document.createElement('p');metrics.className='live-muted';metrics.textContent=`Modelo: ${p.metricas['Modelo recomendado']||'ver informe'} · MAE de prueba: ${p.metricas['MAE de prueba']??'sin métrica'} · Días dentro de ±20%: ${p.calidad?.porcentaje??'sin métrica'}%. No es una probabilidad futura.`;card.append(metrics);
   }else {const note=document.createElement('p');note.textContent='Entrena desde Analítica y proyección para disponer de un informe vigente.';card.append(note);}
   forecastBox.append(card);
  }
  rows('live-envios',d.envios.length?d.envios.map(x=>`${x.estado}: ${x.cantidad}`):['Todavía no hay avisos registrados.']);rows('live-avisos',d.avisos.map(a=>`${a.tipo} · #${a.entidad} · ${a.estado} · ${a.creada}`));
  rows('live-pedidos-recientes',d.pedidos_recientes.map(p=>`Pedido #${p.id_pedido} · ${p.estado} · ${money.format(p.total)} · ${p.fecha_pedido}`));
  rows('live-historial',d.ejecuciones.map(e=>`${e.inicio} · ${e.estado}${e.error?' · '+e.error:''}`));
  rows('live-evaluacion',d.ultima?.proyeccion.seguimiento_real?.length?d.ultima.proyeccion.seguimiento_real.map(e=>`${e.tipo}: ${e.dias} días evaluados · MAE ${Number(e.mae).toFixed(2)} · Referencia semanal ${Number(e.mae_referencia).toFixed(2)}`):['Evaluación prospectiva: se guardan pronósticos futuros y se comparan cuando terminan esos días. Aún no hay resultados cerrados.']);
  status.textContent=`Última lectura: ${new Date(d.fecha).toLocaleTimeString('es-PE')} · Agentes: ${d.ultima?.fecha||'sin ejecución'} · Demora: ${d.demoras.minutos} min`;status.classList.remove('live-error');
 }catch(e){status.textContent='No se pudieron actualizar los datos. Se conservan los últimos valores; comprueba tu sesión y MySQL.';status.classList.add('live-error');}finally{reading=false;}}
 async function agents(){if(running||document.hidden||!document.getElementById('live-agents').checked)return;running=true;try{const body=new URLSearchParams({csrf_token:root.dataset.token});const r=await fetch('agentes/automatico.php',{method:'POST',body});if(!r.ok)throw Error();const j=await r.json();if(!j.ok)throw Error();await read();}catch(e){status.textContent='Falló la ejecución de agentes. Revisa Agentes y alertas; los datos visibles no se borraron.';status.classList.add('live-error');}finally{running=false;}}
 document.getElementById('live-refresh').addEventListener('click',read);document.getElementById('live-agents').addEventListener('change',agents);
 read();agents();setInterval(()=>{if(!document.hidden)read();},30000);setInterval(agents,60000);document.addEventListener('visibilitychange',()=>{if(!document.hidden){read();agents();}});
})();

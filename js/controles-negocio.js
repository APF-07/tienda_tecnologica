document.querySelectorAll('.negocio-chart').forEach(container=>{
    const labels=JSON.parse(container.dataset.labels),values=JSON.parse(container.dataset.values),real=JSON.parse(container.dataset.real);
    const ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');
    svg.setAttribute('viewBox','0 0 500 180');svg.setAttribute('role','img');svg.setAttribute('aria-label',real.length?'Observado en violeta y estimado en turquesa; datos disponibles debajo.':'Pronóstico estimado en soles; datos disponibles debajo.');
    const max=Math.max(1,...values,...real),legend=document.createElement('p');legend.textContent=real.length?'Estimado: turquesa · Observado: violeta · Importe en S/':'Estimado: turquesa · Importe en S/';container.append(legend);
    [real,values].forEach((series,index)=>{if(!series.length)return;const line=document.createElementNS(ns,'polyline');line.setAttribute('points',series.map((n,i)=>`${25+i*450/Math.max(1,series.length-1)},${155-n/max*130}`).join(' '));line.setAttribute('fill','none');line.setAttribute('stroke',index?'#67e3e9':'#b698ff');line.setAttribute('stroke-width','3');svg.append(line);});
    [0,max/2,max].forEach(value=>{const text=document.createElementNS(ns,'text');text.setAttribute('x','25');text.setAttribute('y',String(153-value/max*130));text.setAttribute('fill','#a7bed7');text.setAttribute('font-size','10');text.textContent=value.toFixed(1);svg.prepend(text);});
    container.append(svg);
    const details=document.createElement('details'),summary=document.createElement('summary');summary.textContent='Ver cifras y fechas del gráfico';details.append(summary);
    const table=document.createElement('table');
    const header=table.createTHead().insertRow();['Fecha','Estimado S/',...(real.length?['Observado S/']:[])].forEach(text=>{const th=document.createElement('th');th.textContent=text;header.append(th);});
    const body=table.createTBody();labels.forEach((date,i)=>{const row=body.insertRow();[date,values[i],...(real.length?[real[i]]:[])].forEach(value=>{row.insertCell().textContent=String(value);});});details.append(table);container.append(details);
});
document.querySelectorAll('.negocio-confirmar').forEach(form=>form.addEventListener('submit',event=>{if(!window.confirm('¿Confirmas la categoría propuesta después de revisar el producto? El cambio quedará registrado.'))event.preventDefault();}));

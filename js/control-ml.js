async function aplicarControlML(form, guardar){
    const status=document.getElementById('control-estado'),body=new FormData(form),buttons=[...document.querySelectorAll('.control-ml button')];
    const inicio=Date.now(); let consultando=false;
    status.classList.add('control-running');status.setAttribute('aria-busy','true');
    const reloj=setInterval(async()=>{
        if(consultando)return;consultando=true;
        try{const response=await fetch('estado.php',{cache:'no-store'});if(response.ok){const data=await response.json();status.textContent=`${data.etapa} · ${Math.floor((Date.now()-inicio)/1000)} segundos. Espera la confirmación; no repitas el entrenamiento.`;}}catch(_){}finally{consultando=false;}
    },4000);
    buttons.forEach(button=>button.disabled=true);status.textContent=guardar?'Guardando la configuración elegida…':'Iniciando entrenamiento con la configuración guardada…';
    status.scrollIntoView({block:'nearest',behavior:'smooth'});
    try{
        if(guardar){const response=await fetch(form.dataset.url,{method:'POST',body}),data=await response.json();if(!response.ok||!data.ok)throw Error(data.mensaje||'No se pudo guardar.');}
        status.textContent='Configuración guardada. Comparando modelos y calculando el pronóstico… Puede tardar varios minutos.';
        const response=await fetch('entrenar.php',{method:'POST',body}),data=await response.json();
        if(!response.ok||!data.ok)throw Error(data.mensaje||'No se pudo entrenar.');
        sessionStorage.setItem('control-ml-completado',data.mensaje);window.location.replace('control.php#control-estado');
    }catch(error){status.textContent='No se pudo confirmar el resultado: '+error.message+'. Consulta el historial antes de reintentar.';buttons.forEach(button=>button.disabled=false);}
    finally{clearInterval(reloj);status.classList.remove('control-running');status.removeAttribute('aria-busy');}
}

const completadoML=sessionStorage.getItem('control-ml-completado');
if(completadoML&&document.getElementById('control-estado')){
    document.getElementById('control-estado').textContent='✓ '+completadoML+' Comprueba abajo la fecha publicada y los modelos utilizados.';
    document.getElementById('control-estado').classList.add('control-completed');sessionStorage.removeItem('control-ml-completado');
}
document.getElementById('control-config')?.addEventListener('submit',event=>{
    if(event.submitter?.value==='guardar')return;
    event.preventDefault();aplicarControlML(event.currentTarget,true);
});
document.getElementById('control-entrenar')?.addEventListener('submit',event=>{
    event.preventDefault();aplicarControlML(event.currentTarget,false);
});
document.querySelectorAll('#control-config select[aria-describedby]').forEach(select=>{
    select.addEventListener('change',()=>{
        const help=document.getElementById(select.getAttribute('aria-describedby'));
        if(help)help.textContent=select.selectedOptions[0]?.dataset.ayuda||'';
    });
});

@props(['tenantSlug', 'csrf'])

<style>
#dqa-overlay{position:fixed;z-index:10020;inset:0;display:none;place-items:center;padding:1rem;background:rgba(8,24,15,.55);backdrop-filter:blur(5px)}#dqa-overlay.open{display:grid}.dqa-box{width:min(100%,480px);overflow:hidden;border:1px solid var(--color-border,#dce7e0);border-radius:15px;background:#fff;box-shadow:0 24px 68px rgba(8,24,15,.24)}.dqa-head{display:grid;grid-template-columns:40px minmax(0,1fr) 36px;gap:.55rem;align-items:center;padding:.68rem;border-bottom:1px solid var(--color-border,#dce7e0);background:linear-gradient(180deg,#fff8f8,#fff)}.dqa-icon{display:grid;width:40px;height:40px;place-items:center;border-radius:10px;background:#fff0f0;color:#c83f3f}.dqa-copy strong,.dqa-copy span{display:block}.dqa-copy strong{font-size:.86rem}.dqa-copy span{margin-top:.08rem;color:#708077;font-size:.63rem}.dqa-close{display:grid;width:36px;height:36px;place-items:center;border:1px solid var(--color-border,#dce7e0);border-radius:9px;background:#fff;cursor:pointer}.dqa-body{display:grid;gap:.62rem;padding:.72rem}.dqa-summary{display:grid;grid-template-columns:1fr 1fr;gap:1px;overflow:hidden;border:1px solid var(--color-border,#dce7e0);border-radius:10px;background:var(--color-border,#dce7e0)}.dqa-summary div{padding:.48rem .55rem;background:#fafcfb}.dqa-summary span,.dqa-summary strong{display:block}.dqa-summary span{color:#708077;font-size:.58rem;text-transform:uppercase}.dqa-summary strong{margin-top:.08rem;font-size:.73rem}.dqa-field label{display:block;margin-bottom:.2rem;color:#52645a;font-size:.64rem;font-weight:780}.dqa-field input,.dqa-field textarea{width:100%;border:1px solid var(--color-border-strong,#c8d6cd);border-radius:9px;outline:0;background:#fff;color:#102018;font:inherit}.dqa-field input{min-height:43px;padding:.48rem .55rem}.dqa-field textarea{min-height:90px;padding:.52rem .55rem;resize:vertical}.dqa-field input:focus,.dqa-field textarea:focus{border-color:#cf3f3f;box-shadow:0 0 0 3px rgba(207,63,63,.08)}.dqa-error{padding:.42rem .5rem;border-radius:8px;background:#fff0f0;color:#a63232;font-size:.64rem}.dqa-error[hidden]{display:none}.dqa-foot{display:flex;gap:.4rem;justify-content:flex-end;padding:.58rem .72rem max(.58rem,env(safe-area-inset-bottom));border-top:1px solid var(--color-border,#dce7e0);background:#f8faf9}.dqa-btn{display:inline-flex;min-height:40px;align-items:center;justify-content:center;gap:.28rem;padding:.42rem .62rem;border:1px solid var(--color-border,#dce7e0);border-radius:9px;background:#fff;cursor:pointer;font:inherit;font-size:.68rem;font-weight:800}.dqa-btn.primary{border-color:rgba(207,63,63,.2);background:#fff0f0;color:#b53535}.dqa-btn:disabled{opacity:.5;cursor:wait}@media(max-width:600px){#dqa-overlay{align-items:end;padding:0}.dqa-box{width:100%;border-right:0;border-bottom:0;border-left:0;border-radius:17px 17px 0 0}.dqa-foot .dqa-btn{flex:1}}
</style>

<div id="dqa-overlay" aria-hidden="true">
    <form class="dqa-box" id="dqa-form">
        <header class="dqa-head">
            <span class="dqa-icon"><i class="ph-duotone ph-arrow-u-down-left"></i></span>
            <div class="dqa-copy"><strong id="dqa-title">Rejeitar entrega</strong><span id="dqa-subtitle"></span></div>
            <button class="dqa-close" type="button" aria-label="Fechar"><i class="ph ph-x"></i></button>
        </header>
        <div class="dqa-body">
            <div class="dqa-summary"><div><span>Quantidade aceita atual</span><strong id="dqa-current"></strong></div><div><span>Disponível para ajuste</span><strong id="dqa-available"></strong></div></div>
            <div class="dqa-field"><label for="dqa-quantity">Quantidade a rejeitar/devolver</label><input id="dqa-quantity" name="quantity" type="number" min="0.0001" step="0.0001" inputmode="decimal" required></div>
            <div class="dqa-field"><label for="dqa-reason">Motivo</label><textarea id="dqa-reason" name="reason" maxlength="1000" required placeholder="Informe o motivo para manter o histórico da conferência."></textarea></div>
            <div class="dqa-error" id="dqa-error" hidden></div>
        </div>
        <footer class="dqa-foot"><button class="dqa-btn" type="button" data-dqa-close>Cancelar</button><button class="dqa-btn primary" id="dqa-submit" type="submit"><i class="ph ph-check"></i><span>Confirmar</span></button></footer>
    </form>
</div>

<script>
(() => {
    'use strict';
    const tenant=@json($tenantSlug),csrf=@json($csrf),overlay=document.getElementById('dqa-overlay'),form=document.getElementById('dqa-form');
    if(!overlay||!form)return;
    let state=null;
    const qty=(value,unit)=>`${Number(value||0).toLocaleString('pt-BR',{maximumFractionDigits:4})}${unit?' '+unit:''}`;
    const close=()=>{overlay.classList.remove('open');overlay.setAttribute('aria-hidden','true');state=null;document.body.style.overflow='';};
    const open=button=>{
        const available=Number(button.dataset.available||0),current=Number(button.dataset.current||0),mode=button.dataset.mode==='return'?'return':'rejection';
        state={id:Number(button.dataset.id),mode,available};
        document.getElementById('dqa-title').textContent=mode==='return'?'Registrar devolução':'Rejeitar entrega';
        document.getElementById('dqa-subtitle').textContent=button.dataset.product||'Entrega';
        document.getElementById('dqa-current').textContent=qty(current,button.dataset.unit);
        document.getElementById('dqa-available').textContent=qty(available,button.dataset.unit);
        const input=document.getElementById('dqa-quantity');input.max=String(available);input.value=String(available);
        document.getElementById('dqa-reason').value='';document.getElementById('dqa-error').hidden=true;
        document.getElementById('dqa-submit').querySelector('span').textContent=mode==='return'?'Confirmar devolução':'Confirmar rejeição';
        overlay.classList.add('open');overlay.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';setTimeout(()=>input.focus(),40);
    };
    overlay.querySelectorAll('.dqa-close,[data-dqa-close]').forEach(button=>button.addEventListener('click',close));
    overlay.addEventListener('click',event=>{if(event.target===overlay)close();});
    document.addEventListener('click',event=>{const button=event.target.closest('.btn-return-delivery');if(button){event.preventDefault();open(button);}});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(!state)return;
        const quantity=Number(document.getElementById('dqa-quantity').value||0),reason=document.getElementById('dqa-reason').value.trim(),error=document.getElementById('dqa-error'),submit=document.getElementById('dqa-submit');
        if(quantity<=0||quantity>state.available+.00005||!reason){error.textContent=!reason?'Informe o motivo do ajuste.':'Revise a quantidade informada.';error.hidden=false;return;}
        submit.disabled=true;error.hidden=true;
        try{
            const action=state.mode==='return'?'return':'reject';
            const response=await fetch(`/${tenant}/delivery/deliveries/${state.id}/${action}`,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({quantity,reason})});
            const data=await response.json();
            if(!response.ok||!data.success)throw new Error(data.message||Object.values(data.errors||{}).flat()[0]||'Não foi possível salvar o ajuste.');
            close();document.dispatchEvent(new CustomEvent('delivery-quantity-adjusted',{detail:data}));
        }catch(exception){error.textContent=exception.message;error.hidden=false;}finally{submit.disabled=false;}
    });
})();
</script>

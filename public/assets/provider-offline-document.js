const number = value => {
    if (value == null || String(value).trim() === '') throw new Error('Preencha as medições para calcular.');
    const parsed = Number(String(value).replace(',', '.'));
    if (!Number.isFinite(parsed) || parsed < 0 || parsed > 999999999.99) throw new Error('Confira os valores numéricos.');
    return parsed;
};
const round = (value, places = 2) => Math.round((value + Number.EPSILON) * 10 ** places) / 10 ** places;
export const currency = value => new Intl.NumberFormat('pt-BR', {style:'currency',currency:'BRL'}).format(value);

export function estimate(order, values) {
    const catalog = order.catalog || {};
    const config = catalog.execution_config || {};
    const pair = catalog.meter_pair || [];
    const meter = (startKey, endKey) => {
        const start = number(values[startKey]), end = number(values[endKey]);
        let previous = start, work = 0;
        const pauses = JSON.parse(values['meter_pauses_'+startKey] || '[]');
        for (const pause of pauses) {
            const stop = number(pause.stop), resume = number(pause.resume);
            if (stop < previous || resume < stop || resume > end) throw new Error('Confira a sequência das pausas.');
            work += stop - previous;
            previous = resume;
        }
        if (end < previous) throw new Error('A medição final deve ser maior ou igual à inicial.');
        return work + end - previous;
    };
    const rateBased = (catalog.receivable_enabled && catalog.customer_pricing_method === 'quantity_x_rate')
        || (catalog.payable_enabled && (catalog.provider_compensation?.method || catalog.provider_pricing_method) === 'quantity_x_rate');
    const mode = config.quantity_mode || (pair[0] && pair[1] ? 'meter_difference' : config.quantity_field ? 'field' : 'legacy');
    const quantity = round(mode === 'meter_difference' ? meter(config.meter_start_field || pair[0], config.meter_end_field || pair[1])
        : mode === 'field' ? number(values[config.quantity_field]) : mode === 'fixed_one' ? 1 : number(order.quantity ?? (rateBased ? null : 1)), 4);
    const forDirection = direction => {
        const field = config[direction+'_quantity_field'];
        const calculation = config[direction+'_quantity_mode'] || (field ? 'field' : 'primary');
        if (calculation === 'field') return round(number(values[field]),4);
        if (calculation === 'fixed_one') return 1;
        if (calculation === 'meter_difference') return round(meter(config[direction+'_meter_start_field'],config[direction+'_meter_end_field']),4);
        return quantity;
    };
    const amount = (method, qty, rate, percentage, base) => {
        if (method === 'quantity_x_rate') return round(qty * rate);
        if (method?.startsWith('percent')) {
            if (percentage > 100) throw new Error('Confira o percentual.');
            return round(base * percentage / 100);
        }
        if (['fixed','fixed_addition','fixed_deduction'].includes(method)) return round(rate);
        throw new Error('A regra de cálculo precisa ser conferida online.');
    };
    const customerRate = number(catalog.customer_rate ?? 0);
    const providerRate = number(catalog.provider_compensation?.rate ?? catalog.default_provider_rate ?? 0);
    const bases = {
        receivable: catalog.receivable_enabled ? amount(catalog.customer_pricing_method,forDirection('customer'),customerRate,number(catalog.customer_percentage ?? 0),customerRate) : 0,
        payable: 0,
    };
    if (catalog.payable_enabled) bases.payable = amount(catalog.provider_compensation?.method ?? catalog.provider_pricing_method,
        forDirection('provider'),providerRate,number(catalog.provider_compensation?.percentage ?? catalog.provider_percentage ?? 0),bases.receivable);
    const totals = {...bases};
    for (const item of order.resources || []) {
        if (item.effect === 'deduct_from_receivable') totals.receivable -= number(item.amount);
        if (item.effect === 'add_to_receivable') totals.receivable += number(item.amount);
        if (item.effect === 'reimburse_provider') totals.payable += number(item.amount);
    }
    for (const rule of catalog.financial_config?.rules || []) {
        const input = rule.input_key, role = rule.input_role || 'quantity';
        if (input && !String(values[input] ?? '').trim() && rule.input_required === false) continue;
        const valueField = rule.value_field || (role === 'value' ? input : null);
        const qtyField = rule.quantity_field || (role === 'quantity' ? input : null);
        const value = number(valueField ? values[valueField] : rule.value ?? 0);
        const method = rule.method || 'fixed_addition', direction = rule.direction || 'receivable';
        const percentage = method.startsWith('percent') && valueField ? value : number(rule.percentage ?? 0);
        const delta = amount(method, qtyField ? number(values[qtyField]) : quantity, value, percentage, bases[direction]);
        totals[direction] += rule.effect === 'subtract' || method.endsWith('_deduction') ? -delta : delta;
    }
    if (totals.receivable < 0 || totals.payable < 0) throw new Error('Os descontos excedem o valor devido.');
    return {quantity, receivable: round(totals.receivable), payable: round(totals.payable)};
}

const pdfText = text => Array.from(String(text ?? '')).map(char => {
    if (/^[\x20-\x7e]$/.test(char)) return /[()\\]/.test(char) ? '\\'+char : char;
    const code = char.codePointAt(0);
    return code >= 160 && code <= 255 ? '\\'+code.toString(8).padStart(3,'0') : char === '–' || char === '—' ? '-' : ' ';
}).join('');

export function beneficiaryPdf({organization, logo, provider, order, status, totals, provisional}) {
    const lines = ['0.09 0.24 0.18 rg'];
    const text = (value,y,size=12,font='F1',x=36) => lines.push('BT /'+font+' '+size+' Tf '+x+' '+y+' Td ('+pdfText(value)+') Tj ET');
    const wrap = (value, max) => {
        const rows = [];
        let row = '';
        for (const word of String(value ?? '').split(/\s+/).flatMap(word => word.match(new RegExp('.{1,'+max+'}','gu')) || [])) {
            if ((row+' '+word).trim().length > max) {rows.push(row);row=word;} else row=(row+' '+word).trim();
        }
        if (row) rows.push(row);
        return rows;
    };
    if (logo) lines.push('q 42 0 0 42 36 746 cm /Logo Do Q');
    wrap(organization, 35).slice(0, 3).forEach((line, i) => text(line, 787 - i * 15, 12, 'F2', logo ? 88 : 36));
    text('Ordem de serviço', 787, 13, 'F2', 420);
    text('Via do beneficiário', 769, 10, 'F1', 420);
    text(order.number || 'Registro local', 753, 10, 'F2', 420);

    const block = (title, x, y, width) => {
        lines.push('0.91 0.95 0.92 rg', x+' '+(y-8)+' '+width+' 22 re f', '0.09 0.24 0.18 rg');
        text(title, y, 11, 'F2', x+8);
    };
    const field = (label, value, x, y, limit) => {
        text(label, y, 9, 'F2', x);
        wrap(value, limit).slice(0, 2).forEach((line,i) => text(line,y-16-i*13,11,'F1',x));
    };
    const formatDate = value => value?.slice(0,10).split('-').reverse().join('/');
    lines.push('36 733 523 2 re f');
    block('Identificação',36,708,523);
    field('Beneficiário',order.beneficiary,44,676,37);
    field('Prestador',provider,331,676,32);
    field('Local do serviço',order.location,44,626,37);
    field('Agendamento',order.scheduled ? [formatDate(order.scheduled),order.scheduled.slice(11,16)].filter(Boolean).join(' ') : '',331,626,32);

    block('Serviço e execução',36,573,523);
    field('Serviço',order.service,44,541,37);
    field('Data da execução',formatDate(order.executionDate),331,541,32);
    field('Quantidade',totals ? totals.quantity+' '+(order.unit || '') : '',44,491,37);
    field(provisional ? 'Estimativa do serviço' : 'Valor do serviço',totals?.receivable == null ? '' : currency(totals.receivable),331,491,32);
    if (provisional) field('Registro no aparelho',status,44,441,68);

    block('Assinaturas',36,355,523);
    lines.push('0.09 0.24 0.18 RG','44 304 m 270 304 l S','331 304 m 550 304 l S');
    text('Beneficiário',290,9,'F1',44);
    text('Prestador ou associação',290,9,'F1',331);
    text('Ordem de serviço. O recibo é emitido após o registro do pagamento.',59,9);
    if (provisional) text('Gerado no aparelho com os dados disponíveis neste momento.',42,9);

    const stream = lines.join('\n');
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >>'
            +(logo ? ' /XObject << /Logo 7 0 R >>' : '')+' >> /Contents 4 0 R >>',
        '<< /Length '+stream.length+' >>\nstream\n'+stream+'\nendstream',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
    ];
    if (logo) {
        const hex = Array.from(atob(logo), character => character.charCodeAt(0).toString(16).padStart(2,'0')).join('')+'>';
        objects.push('<< /Type /XObject /Subtype /Image /Width 72 /Height 72 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCIIHexDecode /DCTDecode] /Length '+hex.length+' >>\nstream\n'+hex+'\nendstream');
    }
    let pdf = '%PDF-1.4\n';
    const offsets = objects.map((object,i) => {const offset=pdf.length; pdf+=(i+1)+' 0 obj\n'+object+'\nendobj\n';return offset;});
    const xref = pdf.length;
    pdf+='xref\n0 '+(offsets.length+1)+'\n0000000000 65535 f \n'+offsets.map(offset=>String(offset).padStart(10,'0')+' 00000 n \n').join('')
        +'trailer\n<< /Size '+(offsets.length+1)+' /Root 1 0 R >>\nstartxref\n'+xref+'\n%%EOF';
    return new Blob([pdf],{type:'application/pdf'});
}

export async function sharePdf(blob, name) {
    if (window.SgcDocuments?.sharePdf) return window.SgcDocuments.sharePdf(blob,name);
    const native = window.Capacitor?.Plugins?.NativeDocument;
    if (window.Capacitor?.isNativePlatform?.() && native?.sharePdf) {
        const base64 = btoa(String.fromCharCode(...new Uint8Array(await blob.arrayBuffer())));
        return native.sharePdf({base64,fileName:name,title:'Via do beneficiário'});
    }
    const file = new File([blob], name, {type:'application/pdf'});
    if (navigator.canShare?.({files:[file]})) return navigator.share({files:[file],title:'Via do beneficiário'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = name;
    link.click();
    setTimeout(() => URL.revokeObjectURL(link.href),30000);
}


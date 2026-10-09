@if(!empty($reportAnnotations))
<div style="margin:10px 0 12px; page-break-inside:avoid; font-family:'DejaVu Sans',Arial,sans-serif;">
    <div style="margin-bottom:6px; padding:5px 8px; border-left:4px solid #2f855a; background:#f2f7f4; color:#244c35; font-size:8pt; font-weight:bold; text-transform:uppercase; letter-spacing:.25px;">Observações</div>
    <div style="padding:1px 9px 3px; border-left:1px solid #d5e2da;">
    @foreach($reportAnnotations as $annotation)
    <div style="font-size:7.5pt; line-height:1.45; padding:2px 0; color:#28362e;">
        @if($annotation['marker'])<strong style="color:#2f855a;">{{ $annotation['marker'] }}</strong>@else<span style="color:#2f855a;">•</span>@endif
        {{ $annotation['text'] }}
    </div>
    @endforeach
    </div>
</div>
@endif

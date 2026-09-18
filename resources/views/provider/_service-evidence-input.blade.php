@php($existing=$execution->evidences->where('field_key',$field['key'])->last())
@php($previewId='preview-'.preg_replace('/[^A-Za-z0-9_-]/','-',$field['key']))
<label style="display:grid;gap:.35rem;padding:.7rem;border:1px dashed #94a3b8;border-radius:.7rem;background:#f8fafc">
    <span style="font-weight:700">{{$field['label']}} @if($field['required']??false)<b style="color:#dc2626">*</b>@else<small style="font-weight:500;color:#64748b">(opcional)</small>@endif</span>
    <input class="svc-file svc-evidence-input" type="file" name="evidences[{{$field['key']}}]" accept="{{($field['type']??null)==='image'?'image/*':'image/*,application/pdf'}}" data-preview="{{$previewId}}" @if(($field['type']??null)==='image') capture="environment" @endif @required(($field['required']??false) && !$existing)>
    <small style="color:#64748b">Fotos são reduzidas e convertidas para WebP antes do envio.</small>
    <div id="{{$previewId}}" class="svc-file-preview">
        @if($existing)
            @if(str_starts_with($existing->document->mime_type,'image/'))
                <a href="{{route('provider.evidences.download',[request()->route('tenant')->slug,$existing])}}" target="_blank"><img src="{{route('provider.evidences.download',[request()->route('tenant')->slug,$existing])}}" alt="Prévia de {{$field['label']}}" style="max-width:100%;max-height:12rem;border-radius:.6rem"></a>
            @else
                <a href="{{route('provider.evidences.download',[request()->route('tenant')->slug,$existing])}}" target="_blank">Ver arquivo enviado</a>
            @endif
        @endif
    </div>
</label>

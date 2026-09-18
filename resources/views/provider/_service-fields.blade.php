@php
    $allFields = collect(data_get($execution->catalog_snapshot, 'fields', []));
    $canUse = fn (array $field) => ($operator ?? false)
        ? ($field['visible_to_management'] ?? true)
        : (($field['visible_to_provider'] ?? false) && ($field['editable_by_provider'] ?? false));
    $phaseFields = $allFields->where('phase', $phase)->filter($canUse);
    $evidenceTypes = ['image', 'file', 'signature'];
    $evidenceFields = $phaseFields->filter(fn (array $field) => in_array($field['type'] ?? null, $evidenceTypes, true));
    $dataFields = $phaseFields->reject(fn (array $field) => in_array($field['type'] ?? null, $evidenceTypes, true));
    $linkedEvidence = $evidenceFields->filter(fn (array $field) => filled($field['evidence_for_field'] ?? null))->keyBy('evidence_for_field');
    $inferredKeys = collect();
    foreach ($evidenceFields->filter(fn (array $field) => blank($field['evidence_for_field'] ?? null)) as $evidence) {
        $evidenceLabel = str($evidence['label'] ?? '')->ascii()->lower()->toString();
        $keyword = collect(['inicial', 'inicio', 'final', 'fim'])->first(fn (string $word) => str_contains($evidenceLabel, $word));
        $target = $keyword ? $dataFields->first(fn (array $field) => str_contains(str($field['label'] ?? '')->ascii()->lower()->toString(), $keyword)) : null;
        if ($target && ! $linkedEvidence->has($target['key'])) {
            $linkedEvidence->put($target['key'], $evidence);
            $inferredKeys->push($evidence['key']);
        }
    }
    $standaloneEvidence = $evidenceFields->filter(fn (array $field) => blank($field['evidence_for_field'] ?? null) && ! $inferredKeys->contains($field['key']));
@endphp

@foreach($dataFields as $field)
@php($value=data_get($execution->values,$field['key']))
@php($proof=$linkedEvidence->get($field['key']))
<div class="svc-field" data-field-key="{{$field['key']}}" style="display:grid;gap:.45rem">
<label style="display:grid;gap:.35rem"><span style="font-weight:700">{{$field['label']}} @if($field['required']??false)<b style="color:#dc2626">*</b>@endif @if($field['unit']??null)<small style="font-weight:500;color:#64748b">({{$field['unit']}})</small>@endif</span>
@if(($field['type']??'text')==='textarea')<textarea name="values[{{$field['key']}}]" @required($field['required']??false)>{{$value}}</textarea>
@elseif(($field['type']??'')==='boolean')<select name="values[{{$field['key']}}]"><option value="0">Não</option><option value="1" @selected($value)>Sim</option></select>
@elseif(($field['type']??'')==='select')<select name="values[{{$field['key']}}]" @required($field['required']??false)>@foreach(($field['options']??[]) as $key=>$option)<option value="{{is_int($key)?$option:$key}}" @selected($value===(is_int($key)?$option:$key))>{{$option}}</option>@endforeach</select>
@else<input type="{{in_array($field['type']??'', ['integer','decimal','money','quantity','meter'])?'number':(in_array($field['type']??'', ['date','datetime'])?($field['type']==='date'?'date':'datetime-local'):'text')}}" name="values[{{$field['key']}}]" value="{{$value}}" placeholder="{{$field['placeholder']??''}}" @if(isset($field['minimum'])) min="{{$field['minimum']}}" @endif @if(isset($field['maximum'])) max="{{$field['maximum']}}" @endif step="{{in_array($field['type']??'', ['decimal','money','quantity','meter'])?'any':'1'}}" @required($field['required']??false)>@endif
@if($field['help']??null)<small style="color:#64748b">{{$field['help']}}</small>@endif
</label>
@if($proof) @include('provider._service-evidence-input',['field'=>$proof,'execution'=>$execution]) @endif
</div>
@endforeach

@foreach($standaloneEvidence as $field)
<div class="svc-field" style="display:grid;gap:.45rem">@include('provider._service-evidence-input',['field'=>$field,'execution'=>$execution])</div>
@endforeach

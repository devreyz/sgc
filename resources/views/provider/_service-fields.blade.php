@foreach(collect(data_get($execution->catalog_snapshot,'fields',[]))->where('phase',$phase)->where('visible_to_provider',true) as $field)
@php($value=data_get($execution->values,$field['key']))
<label style="display:grid;gap:.35rem"><span>{{$field['label']}} @if($field['required']??false)*@endif</span>
@if(($field['type']??'text')==='textarea')<textarea name="values[{{$field['key']}}]" @required($field['required']??false)>{{$value}}</textarea>
@elseif(($field['type']??'')==='boolean')<select name="values[{{$field['key']}}]"><option value="0">Não</option><option value="1" @selected($value)>Sim</option></select>
@elseif(($field['type']??'')==='select')<select name="values[{{$field['key']}}]" @required($field['required']??false)>@foreach(($field['options']??[]) as $key=>$option)<option value="{{is_int($key)?$option:$key}}" @selected($value===(is_int($key)?$option:$key))>{{$option}}</option>@endforeach</select>
@elseif(in_array($field['type']??'', ['image','file','signature']))<span style="color:#64748b">Anexe em Evidências abaixo.</span>
@else<input type="{{in_array($field['type']??'', ['integer','decimal','money','quantity','meter'])?'number':(in_array($field['type']??'', ['date','datetime'])?($field['type']==='date'?'date':'datetime-local'):'text')}}" name="values[{{$field['key']}}]" value="{{$value}}" @if(isset($field['minimum'])) min="{{$field['minimum']}}" @endif @if(isset($field['maximum'])) max="{{$field['maximum']}}" @endif step="{{in_array($field['type']??'', ['decimal','money','quantity','meter'])?'any':'1'}}" @required($field['required']??false)>@endif
</label>
@endforeach

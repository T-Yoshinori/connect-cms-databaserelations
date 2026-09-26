@extends('core.cms_frame_base')
@section("plugin_contents_$frame->id")
<div class="card"><div class="card-header">関連設定</div><div class="card-body">
<div class="mb-3"><strong>{{$database->databases_name}}：</strong>{{$source_label}}</div>
<form action="{{url('/')}}/plugin/databaserelations/saveRecordRelations/{{$page->id}}/{{$frame_id}}/{{$source_input->id}}#frame-{{$frame->id}}" method="POST">{{csrf_field()}}
@foreach($relations as $relation)
@php($form=$relation_forms->get($relation->id))
<div class="form-group row"><label class="col-md-3 col-form-label">{{$form->name}} <small class="text-muted">({{$form->side==='one'?'複数選択可':'1件'}})</small></label><div class="col-md-9">
@if($form->side==='one')
<select name="relation_values[{{$relation->id}}][]" class="form-control" multiple size="{{min(max($form->options->count(),3),10)}}">
@foreach($form->options as $record_id=>$label)<option value="{{$record_id}}" @if(in_array($record_id,old('relation_values.'.$relation->id,$form->selected))) selected @endif>{{$label}}</option>@endforeach
</select><small class="form-text text-muted">単数件側のレコードなので、複数件側のレコードを複数関連付けできます。</small>
@else
<select name="relation_values[{{$relation->id}}]" class="form-control"><option value="">関連付けなし</option>
@foreach($form->options as $record_id=>$label)<option value="{{$record_id}}" @if((string)old('relation_values.'.$relation->id,($form->selected[0]??''))===(string)$record_id) selected @endif>{{$label}}</option>@endforeach
</select><small class="form-text text-muted">複数件側のレコードなので、単数件側のレコードは1件だけ選択できます。</small>
@endif
@if($errors->has('relation_values.'.$relation->id))<div class="text-danger">{{$errors->first('relation_values.'.$relation->id)}}</div>@endif
</div></div>
@endforeach
<div class="text-center"><a href="{{url('/')}}/plugin/databaserelations/index/{{$page->id}}/{{$frame_id}}#frame-{{$frame->id}}" class="btn btn-secondary mr-2">キャンセル</a><button class="btn btn-primary">保存</button></div>
</form></div></div>
@endsection

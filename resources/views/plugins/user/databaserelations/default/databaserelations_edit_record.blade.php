@extends('core.cms_frame_base')
@section("plugin_contents_$frame->id")
<div class="card"><div class="card-header">関連設定</div><div class="card-body">
<div class="mb-3"><strong>{{$database->databases_name}}：</strong>{{$source_label}}</div>
<form action="{{url('/')}}/plugin/databaserelations/saveRecordRelations/{{$page->id}}/{{$frame_id}}/{{$source_input->id}}#frame-{{$frame->id}}" method="POST">{{csrf_field()}}
@foreach($relations as $relation)
@php($form=$relation_forms->get($relation->id))
<div class="form-group row"><label class="col-md-3 col-form-label">{{$form->name}} <small class="text-muted">({{$form->multiple?'複数選択可':'1件'}})</small></label><div class="col-md-9">
@if($form->multiple)
<select name="relation_values[{{$relation->id}}][]" class="form-control" multiple size="{{min(max($form->options->count(),3),10)}}">
@foreach($form->options as $record_id=>$label)<option value="{{$record_id}}" @if(in_array($record_id,old('relation_values.'.$relation->id,$form->selected))) selected @endif>{{$label}}</option>@endforeach
</select><small class="form-text text-muted">このリレーションでは、関連先レコードを複数選択できます。</small>
@else
<select name="relation_values[{{$relation->id}}]" class="form-control"><option value="">関連付けなし</option>
@foreach($form->options as $record_id=>$label)<option value="{{$record_id}}" @if((string)old('relation_values.'.$relation->id,($form->selected[0]??''))===(string)$record_id) selected @endif>{{$label}}</option>@endforeach
</select><small class="form-text text-muted">このリレーションでは、関連先レコードを1件だけ選択できます。</small>
@endif
@if($errors->has('relation_values.'.$relation->id))<div class="text-danger">{{$errors->first('relation_values.'.$relation->id)}}</div>@endif
</div></div>
@endforeach
@if($entity_relations->isNotEmpty())
<hr>
<h5 class="mb-3">Connect-CMSとの関連</h5>
@foreach($entity_relations as $entity_relation)
<div class="form-group row">
 <label class="col-md-3 col-form-label">{{$entity_relation->relation_name}}
  @if($entity_relation->target_type==='user')<small class="text-muted">(任意・1件)</small>
  @elseif($entity_relation->target_type==='group')<small class="text-muted">(任意・複数選択可)</small>@endif
 </label>
 <div class="col-md-9">
  @if($entity_relation->target_type==='user')
   @php($selectedUsers=(array)$entity_selected->get($entity_relation->id,[]))
   @php($currentUserId=old('entity_relation_values.'.$entity_relation->id.'.0',($selectedUsers[0]??'')))
   <select name="entity_relation_values[{{$entity_relation->id}}][]" class="form-control">
    <option value="">関連付けなし</option>
    @foreach($user_options as $user)
    @php($is_current_user=(string)$currentUserId===(string)$user->id)
    <option value="{{$user->id}}" @if($is_current_user) selected @endif @if((int)$user->status!==\App\Enums\UserStatus::active && !$is_current_user) disabled @endif>{{$user->name}}@if(strlen((string)$user->userid))（{{$user->userid}}）@endif @if((int)$user->status!==\App\Enums\UserStatus::active)［利用停止等］@endif</option>
    @endforeach
   </select>
   <small class="form-text text-muted">CMSを利用しない参加者は「関連付けなし」のままで正常です。同じユーザーを別レコードへ重複して関連付けることはできません。</small>
  @elseif($entity_relation->target_type==='group')
   @php($selectedGroups=(array)old('entity_relation_values.'.$entity_relation->id,$entity_selected->get($entity_relation->id,[])))
   <select name="entity_relation_values[{{$entity_relation->id}}][]" class="form-control" multiple size="{{min(max($group_options->count(),3),10)}}">
    @foreach($group_options as $group)
    <option value="{{$group->id}}" @if(in_array($group->id,$selectedGroups)) selected @endif>{{$group->name}}</option>
    @endforeach
   </select>
   <small class="form-text text-muted">このレコードに関係するConnect-CMSユーザーグループを複数選択できます。同じグループを複数のレコードに関連付けることもできます。</small>
  @endif
  @if($errors->has('entity_relation_values.'.$entity_relation->id))<div class="text-danger">{{$errors->first('entity_relation_values.'.$entity_relation->id)}}</div>@endif
 </div>
</div>
@endforeach
@endif
<div class="text-center"><a href="{{url('/')}}/plugin/databaserelations/index/{{$page->id}}/{{$frame_id}}#frame-{{$frame->id}}" class="btn btn-secondary mr-2">キャンセル</a><button class="btn btn-primary">保存</button></div>
</form></div></div>
@endsection

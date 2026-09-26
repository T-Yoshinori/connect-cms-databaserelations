@extends('core.cms_frame_base_setting')

@section("core.cms_frame_edit_tab_$frame->id")
@include('plugins.user.databaserelations.databaserelations_frame_edit_tab')
@endsection

@section("plugin_setting_$frame->id")
<div class="card mb-3">
 <div class="card-header">このフレームで使用するDB</div>
 <div class="card-body">
  <form action="{{url('/')}}/plugin/databaserelations/saveFrameDatabase/{{$page->id}}/{{$frame_id}}#frame-{{$frame->id}}" method="POST">
   {{csrf_field()}}
   <div class="form-group row mb-0">
    <label class="{{$frame->getSettingLabelClass()}}">使用するDB <span class="badge badge-danger">必須</span></label>
    <div class="{{$frame->getSettingInputClass()}}">
     <div class="input-group">
      <select name="databases_id" class="form-control">
       <option value="">選択してください</option>
       @foreach($databases as $database)
       <option value="{{$database->id}}" @if($databases_id==$database->id) selected @endif>{{$database->databases_name}}</option>
       @endforeach
      </select>
      <div class="input-group-append"><button class="btn btn-primary">保存</button></div>
     </div>
     <small class="form-text text-muted">このDBが「単数件側」「複数件側」のどちらであっても、このDBを含むリレーションを設定・編集できます。</small>
    </div>
   </div>
  </form>
 </div>
</div>

<div class="card mb-3">
 <div class="card-header">@if($editing_relation->id) リレーション定義の編集 @else リレーション定義の追加 @endif</div>
 <div class="card-body">
 @if(!$databases_id)
  <div class="alert alert-info mb-0">先に「使用するDB」を設定してください。</div>
 @else
  <form action="{{url('/')}}/plugin/databaserelations/saveRelation/{{$page->id}}/{{$frame_id}}@if($editing_relation->id)/{{$editing_relation->id}}@endif#frame-{{$frame->id}}" method="POST">
   {{csrf_field()}}
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">単数件側のDB <span class="badge badge-danger">必須</span></label>
    <div class="{{$frame->getSettingInputClass()}}">
     <select name="one_database_id" id="relation_one_db_{{$frame_id}}" class="form-control">
      <option value="">選択してください</option>
      @foreach($databases as $database)<option value="{{$database->id}}" @if(old('one_database_id',$editing_relation->one_database_id)==$database->id) selected @endif>{{$database->databases_name}}</option>@endforeach
     </select>
     @if($errors->has('one_database_id'))<div class="text-danger">{{$errors->first('one_database_id')}}</div>@endif
    </div>
   </div>
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">単数件側での表示名 <span class="badge badge-danger">必須</span></label>
    <div class="{{$frame->getSettingInputClass()}}"><input name="one_relation_name" value="{{old('one_relation_name',$editing_relation->one_relation_name)}}" class="form-control"><small class="form-text text-muted">単数件側のレコードに表示する、複数件側との関係の名称です。</small></div>
   </div>
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">単数件側の表示項目</label>
    <div class="{{$frame->getSettingInputClass()}}"><select name="one_display_column_id" id="relation_one_col_{{$frame_id}}" class="form-control"><option value="">自動</option>@foreach($columns as $dbid=>$dbcols)@foreach($dbcols as $column)<option value="{{$column->id}}" data-database-id="{{$dbid}}" @if(old('one_display_column_id',$editing_relation->one_display_column_id)==$column->id) selected @endif>{{$column->column_name}}</option>@endforeach @endforeach</select></div>
   </div>
   <div class="form-group row" id="relation_one_frame_group_{{$frame_id}}">
    <label class="{{$frame->getSettingLabelClass()}}">単数件側の詳細表示先</label>
    <div class="{{$frame->getSettingInputClass()}}">
     <select name="one_detail_frame_id" id="relation_one_frame_{{$frame_id}}" class="form-control">
      <option value="">自動</option>
      @foreach($database_frames as $dbid=>$frames)@foreach($frames as $dbframe)
      <option value="{{$dbframe->id}}" data-database-id="{{$dbid}}" @if(old('one_detail_frame_id',$editing_relation->one_detail_frame_id)==$dbframe->id) selected @endif>{{$dbframe->page_name ?: 'ページID '.$dbframe->page_id}}（frame {{$dbframe->id}}）</option>
      @endforeach @endforeach
     </select>
     <small class="form-text text-muted">同じDBを複数のページで表示している場合だけ選択してください。1か所だけの場合は自動で決まります。</small>
     @if($errors->has('one_detail_frame_id'))<div class="text-danger">{{$errors->first('one_detail_frame_id')}}</div>@endif
    </div>
   </div>
   <hr>
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">複数件側のDB <span class="badge badge-danger">必須</span></label>
    <div class="{{$frame->getSettingInputClass()}}">
     <select name="many_database_id" id="relation_many_db_{{$frame_id}}" class="form-control">
      <option value="">選択してください</option>
      @foreach($databases as $database)<option value="{{$database->id}}" @if(old('many_database_id',$editing_relation->many_database_id)==$database->id) selected @endif>{{$database->databases_name}}</option>@endforeach
     </select>
     @if($errors->has('many_database_id'))<div class="text-danger">{{$errors->first('many_database_id')}}</div>@endif
    </div>
   </div>
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">複数件側での表示名 <span class="badge badge-danger">必須</span></label>
    <div class="{{$frame->getSettingInputClass()}}"><input name="many_relation_name" value="{{old('many_relation_name',$editing_relation->many_relation_name)}}" class="form-control"><small class="form-text text-muted">複数件側のレコードに表示する、単数件側との関係の名称です。</small></div>
   </div>
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">複数件側の表示項目</label>
    <div class="{{$frame->getSettingInputClass()}}"><select name="many_display_column_id" id="relation_many_col_{{$frame_id}}" class="form-control"><option value="">自動</option>@foreach($columns as $dbid=>$dbcols)@foreach($dbcols as $column)<option value="{{$column->id}}" data-database-id="{{$dbid}}" @if(old('many_display_column_id',$editing_relation->many_display_column_id)==$column->id) selected @endif>{{$column->column_name}}</option>@endforeach @endforeach</select></div>
   </div>
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">表示順</label>
    <div class="{{$frame->getSettingInputClass()}}"><input type="number" name="display_sequence" value="{{old('display_sequence',$editing_relation->display_sequence??0)}}" class="form-control col-sm-3"></div>
   </div>
   <div class="text-center">@if($editing_relation->id)<a href="{{url('/')}}/plugin/databaserelations/editRelations/{{$page->id}}/{{$frame_id}}#frame-{{$frame->id}}" class="btn btn-secondary mr-2">キャンセル</a>@endif<button class="btn btn-primary">@if($editing_relation->id) 更新 @else 追加 @endif</button></div>
  </form>
 @endif
 </div>
</div>

<div class="card">
 <div class="card-header">登録済みリレーション</div>
 <div class="card-body p-0">
 @if($relations->isEmpty())<div class="p-3 text-muted">リレーション定義はまだありません。</div>
 @else
 <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>単数件側のDB</th><th>単数件側での表示名</th><th>関係</th><th>複数件側のDB</th><th>複数件側での表示名</th><th></th></tr></thead><tbody>
 @foreach($relations as $relation)
 <tr>
  <td>{{optional($databases->firstWhere('id',$relation->one_database_id))->databases_name ?: '（DBなし）'}}</td>
  <td>{{$relation->one_relation_name}}</td><td class="text-nowrap">単数件 ─ 複数件</td>
  <td>{{optional($databases->firstWhere('id',$relation->many_database_id))->databases_name ?: '（DBなし）'}}</td>
  <td>{{$relation->many_relation_name}}</td>
  <td class="text-nowrap text-right"><a href="{{url('/')}}/plugin/databaserelations/editRelations/{{$page->id}}/{{$frame_id}}/{{$relation->id}}#frame-{{$frame->id}}" class="btn btn-sm btn-outline-primary">編集</a>
  <form action="{{url('/')}}/plugin/databaserelations/deleteRelation/{{$page->id}}/{{$frame_id}}/{{$relation->id}}#frame-{{$frame->id}}" method="POST" class="d-inline" onsubmit="return confirm('このリレーション定義と関連付けを削除します。よろしいですか？');">{{csrf_field()}}<button class="btn btn-sm btn-outline-danger">削除</button></form></td>
 </tr>
 @endforeach
 </tbody></table></div>
 @endif
 </div>
</div>

<div class="text-center mt-3">
 <a href="{{url('/')}}/plugin/databaserelations/index/{{$page->id}}/{{$frame_id}}#frame-{{$frame->id}}" class="btn btn-secondary">
  <i class="fas fa-check"></i> 設定終了
 </a>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 function bind(dbId,colId,frameId,groupId){var db=document.getElementById(dbId),col=document.getElementById(colId),frm=document.getElementById(frameId),grp=document.getElementById(groupId);if(!db||!col)return;function filter(){Array.prototype.forEach.call(col.options,function(o){o.hidden=!!o.value&&o.getAttribute('data-database-id')!==db.value;});if(col.options[col.selectedIndex]&&col.options[col.selectedIndex].hidden)col.value='';if(frm&&grp){var count=0;Array.prototype.forEach.call(frm.options,function(o){var match=!!o.value&&o.getAttribute('data-database-id')===db.value;o.hidden=!!o.value&&!match;if(match)count++;});if(frm.options[frm.selectedIndex]&&frm.options[frm.selectedIndex].hidden)frm.value='';grp.style.display=count>1?'':'none';}}db.addEventListener('change',filter);filter();}
 bind('relation_one_db_{{$frame_id}}','relation_one_col_{{$frame_id}}','relation_one_frame_{{$frame_id}}','relation_one_frame_group_{{$frame_id}}');bind('relation_many_db_{{$frame_id}}','relation_many_col_{{$frame_id}}','relation_many_frame_{{$frame_id}}','relation_many_frame_group_{{$frame_id}}');
});
</script>
@endsection

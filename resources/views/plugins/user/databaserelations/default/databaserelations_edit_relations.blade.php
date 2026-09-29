@extends('core.cms_frame_base_setting')

@section("core.cms_frame_edit_tab_$frame->id")
@include('plugins.user.databaserelations.databaserelations_frame_edit_tab')
@endsection

@section("plugin_setting_$frame->id")
@php($user_entity_relations=$entity_relations->where('target_type','user')->values())
@php($group_entity_relations=$entity_relations->where('target_type','group')->values())
<div class="card mb-3">
 <div class="card-header">この画面で管理するDB</div>
 <div class="card-body">
  <form action="{{url('/')}}/plugin/databaserelations/saveFrameDatabase/{{$page->id}}/{{$frame_id}}#frame-{{$frame->id}}" method="POST">
   {{csrf_field()}}
   <div class="form-group row mb-0">
    <label class="{{$frame->getSettingLabelClass()}}">この画面で管理するDB <span class="badge badge-danger">必須</span></label>
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
     <small class="form-text text-muted">このDatabaseRelations画面で、関連付けを確認・編集する基準となるDBです。例：担当者とタスクの関連を担当者側から管理する場合は「担当者DB」を選択します。</small>
    </div>
   </div>
  </form>
 </div>
</div>

<div class="card mb-3">
 <div class="card-header">@if($editing_relation->id) リレーション定義の編集 @else リレーション定義の追加 @endif</div>
 <div class="card-body">
 @if(!$databases_id)
  <div class="alert alert-info mb-0">先に「この画面で管理するDB」を設定してください。</div>
 @else
  <div class="alert alert-light border">
   <strong>DB A・DB Bについて</strong><br>
   「この画面で管理するDB」をDB AまたはDB Bのどちらかに指定し、もう一方に関連付けるDBを指定します。1:NではDB Aを1件側、DB Bを複数件側として扱います。N:NではDB A・DB Bの区別は関連付け数には影響せず、双方から複数件を関連付けられます。
  </div>
  <form action="{{url('/')}}/plugin/databaserelations/saveRelation/{{$page->id}}/{{$frame_id}}@if($editing_relation->id)/{{$editing_relation->id}}@endif#frame-{{$frame->id}}" method="POST">
   {{csrf_field()}}
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">DB A <span class="badge badge-danger">必須</span></label>
    <div class="{{$frame->getSettingInputClass()}}">
     <select name="one_database_id" id="relation_one_db_{{$frame_id}}" class="form-control">
      <option value="">選択してください</option>
      @foreach($databases as $database)<option value="{{$database->id}}" @if(old('one_database_id',$editing_relation->one_database_id)==$database->id) selected @endif>{{$database->databases_name}}</option>@endforeach
     </select>
     @if($errors->has('one_database_id'))<div class="text-danger">{{$errors->first('one_database_id')}}</div>@endif
    </div>
   </div>
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">DB Aでの表示名 <span class="badge badge-danger">必須</span></label>
    <div class="{{$frame->getSettingInputClass()}}"><input name="one_relation_name" value="{{old('one_relation_name',$editing_relation->one_relation_name)}}" class="form-control"><small class="form-text text-muted">DB AのレコードからDB Bとの関連を見るときに表示する名称です。</small></div>
   </div>
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">DB Aの表示項目</label>
    <div class="{{$frame->getSettingInputClass()}}"><select name="one_display_column_id" id="relation_one_col_{{$frame_id}}" class="form-control"><option value="">自動</option>@foreach($columns as $dbid=>$dbcols)@foreach($dbcols as $column)<option value="{{$column->id}}" data-database-id="{{$dbid}}" @if(old('one_display_column_id',$editing_relation->one_display_column_id)==$column->id) selected @endif>{{$column->column_name}}</option>@endforeach @endforeach</select></div>
   </div>
   <div class="form-group row" id="relation_one_frame_group_{{$frame_id}}">
    <label class="{{$frame->getSettingLabelClass()}}">DB Aの詳細表示先</label>
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
    <label class="{{$frame->getSettingLabelClass()}}">DB B <span class="badge badge-danger">必須</span></label>
    <div class="{{$frame->getSettingInputClass()}}">
     <select name="many_database_id" id="relation_many_db_{{$frame_id}}" class="form-control">
      <option value="">選択してください</option>
      @foreach($databases as $database)<option value="{{$database->id}}" @if(old('many_database_id',$editing_relation->many_database_id)==$database->id) selected @endif>{{$database->databases_name}}</option>@endforeach
     </select>
     @if($errors->has('many_database_id'))<div class="text-danger">{{$errors->first('many_database_id')}}</div>@endif
    </div>
   </div>
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">関連付け方 <span class="badge badge-danger">必須</span></label>
    <div class="{{$frame->getSettingInputClass()}}">
     @php($relationType=old('relation_type',$editing_relation->relation_type ?: 'one_to_many'))
     <div class="custom-control custom-radio">
      <input type="radio" id="relation_type_one_to_many_{{$frame_id}}" name="relation_type" value="one_to_many" class="custom-control-input relation-type-{{$frame_id}}" @if($relationType==='one_to_many') checked @endif>
      <label class="custom-control-label" for="relation_type_one_to_many_{{$frame_id}}">DB Aの1件に、DB Bの複数件を関連付ける（1:N）</label>
     </div>
     <div class="custom-control custom-radio">
      <input type="radio" id="relation_type_many_to_many_{{$frame_id}}" name="relation_type" value="many_to_many" class="custom-control-input relation-type-{{$frame_id}}" @if($relationType==='many_to_many') checked @endif>
      <label class="custom-control-label" for="relation_type_many_to_many_{{$frame_id}}">DB A・DB Bの双方から複数件を関連付ける（N:N）</label>
     </div>
     @if($errors->has('relation_type'))<div class="text-danger">{{$errors->first('relation_type')}}</div>@endif
    </div>
   </div>
   <div id="relation_description_{{$frame_id}}" class="alert alert-info"></div>
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">DB Bでの表示名 <span class="badge badge-danger">必須</span></label>
    <div class="{{$frame->getSettingInputClass()}}"><input name="many_relation_name" value="{{old('many_relation_name',$editing_relation->many_relation_name)}}" class="form-control"><small class="form-text text-muted">DB BのレコードからDB Aとの関連を見るときに表示する名称です。</small></div>
   </div>
   <div class="form-group row">
    <label class="{{$frame->getSettingLabelClass()}}">DB Bの表示項目</label>
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

<div class="card border-primary">
 <div class="card-header bg-primary text-white"><i class="fas fa-link"></i> 登録済みリレーション</div>
 <div class="card-body p-0">
 @if($relations->isEmpty())<div class="p-3 text-muted">リレーション定義はまだありません。</div>
 @else
 <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>DB A</th><th>DB Aでの表示名</th><th>関連付け方</th><th>DB B</th><th>DB Bでの表示名</th><th></th></tr></thead><tbody>
 @foreach($relations as $relation)
 <tr>
  <td>{{optional($databases->firstWhere('id',$relation->one_database_id))->databases_name ?: '（DBなし）'}}</td>
  <td>{{$relation->one_relation_name}}</td><td class="text-nowrap">{{$relation->isManyToMany() ? 'N:N（双方から複数件）' : '1:N（DB A 1件 ─ DB B 複数件）'}}</td>
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

<div class="card mt-4 @if($user_entity_relations->isNotEmpty()) border-success @endif">
 <div class="card-header @if($user_entity_relations->isNotEmpty()) bg-success text-white @endif">Connect-CMSユーザーとの関連 @if($user_entity_relations->isNotEmpty())<span class="badge badge-light ml-2">設定済み</span>@endif</div>
 <div class="card-body">
  @if(!$databases_id)
   <div class="text-muted">先に「この画面で管理するDB」を設定してください。</div>
  @elseif($user_entity_relations->isEmpty())
   <div class="alert alert-light border">
    この画面で管理するDBの各レコードと、Connect-CMSのユーザーを関連付けます。CMSを利用しないレコードは、ユーザーを関連付けないままでも構いません。
   </div>
   <form action="{{url('/')}}/plugin/databaserelations/saveEntityRelation/{{$page->id}}/{{$frame_id}}#frame-{{$frame->id}}" method="POST">
    {{csrf_field()}}
    <div class="form-group row">
     <label class="{{$frame->getSettingLabelClass()}}">表示名 <span class="badge badge-danger">必須</span></label>
     <div class="{{$frame->getSettingInputClass()}}">
      <input name="relation_name" value="{{old('relation_name','CMSユーザー')}}" class="form-control">
      @if($errors->entityRelation->has('relation_name'))<div class="text-danger">{{$errors->entityRelation->first('relation_name')}}</div>@endif
     </div>
    </div>
    <div class="form-group row">
     <label class="{{$frame->getSettingLabelClass()}}">関連付け</label>
     <div class="{{$frame->getSettingInputClass()}}"><div class="form-control-plaintext">任意・1レコード最大1ユーザー・同一ユーザー重複不可</div><small class="form-text text-muted">CMSを利用しない参加者は、ユーザーを関連付けなくても正常です。</small></div>
    </div>
    <div class="form-group row">
     <label class="{{$frame->getSettingLabelClass()}}">表示順</label>
     <div class="{{$frame->getSettingInputClass()}}"><input type="number" name="display_sequence" value="{{old('display_sequence',0)}}" class="form-control col-sm-3">@if($errors->entityRelation->has('display_sequence'))<div class="text-danger">{{$errors->entityRelation->first('display_sequence')}}</div>@endif</div>
    </div>
    <div class="text-center"><button class="btn btn-primary">User関連を追加</button></div>
   </form>
  @else
   <div class="alert alert-success"><i class="fas fa-check-circle"></i> Connect-CMSユーザーとの関連は設定済みです。追加設定は不要です。</div>
   <div class="card border-success mb-0">
    <div class="card-header"><strong>登録済みのUser関連</strong></div>
    <div class="card-body p-0">
     <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>表示名</th><th>関連先</th><th>条件</th><th></th></tr></thead><tbody>
     @foreach($user_entity_relations as $entity_relation)
      <tr><td>{{$entity_relation->relation_name}}</td><td>{{$entity_relation->target_type==='user'?'Connect-CMS User':$entity_relation->target_type}}</td><td>任意 / 最大1件 / 重複不可</td><td class="text-right"><form action="{{url('/')}}/plugin/databaserelations/deleteEntityRelation/{{$page->id}}/{{$frame_id}}/{{$entity_relation->id}}#frame-{{$frame->id}}" method="POST" onsubmit="return confirm('このUser関連定義と関連付けを削除します。よろしいですか？');">{{csrf_field()}}<button class="btn btn-sm btn-outline-danger">削除</button></form></td></tr>
     @endforeach
     </tbody></table></div>
    </div>
   </div>
  @endif
 </div>
</div>


<div class="card mt-4 @if($group_entity_relations->isNotEmpty()) border-success @endif">
 <div class="card-header @if($group_entity_relations->isNotEmpty()) bg-success text-white @endif">Connect-CMSユーザーグループとの関連 @if($group_entity_relations->isNotEmpty())<span class="badge badge-light ml-2">設定済み</span>@endif</div>
 <div class="card-body">
  @if(!$databases_id)
   <div class="text-muted">先に「この画面で管理するDB」を設定してください。</div>
  @elseif($group_entity_relations->isEmpty())
   <div class="alert alert-light border">
    この画面で管理するDBの各レコードと、Connect-CMSのユーザーグループを関連付けます。1つのレコードに複数グループを関連付けられ、同じグループを複数のレコードで利用できます。
   </div>
   <form action="{{url('/')}}/plugin/databaserelations/saveGroupEntityRelation/{{$page->id}}/{{$frame_id}}#frame-{{$frame->id}}" method="POST">
    {{csrf_field()}}
    <div class="form-group row">
     <label class="{{$frame->getSettingLabelClass()}}">表示名 <span class="badge badge-danger">必須</span></label>
     <div class="{{$frame->getSettingInputClass()}}">
      <input name="group_relation_name" value="{{old('group_relation_name','ユーザーグループ')}}" class="form-control">
      @if($errors->groupEntityRelation->has('group_relation_name'))<div class="text-danger">{{$errors->groupEntityRelation->first('group_relation_name')}}</div>@endif
     </div>
    </div>
    <div class="form-group row">
     <label class="{{$frame->getSettingLabelClass()}}">関連付け</label>
     <div class="{{$frame->getSettingInputClass()}}"><div class="form-control-plaintext">任意・複数グループ・同一グループを複数レコードで利用可</div></div>
    </div>
    <div class="form-group row">
     <label class="{{$frame->getSettingLabelClass()}}">表示順</label>
     <div class="{{$frame->getSettingInputClass()}}"><input type="number" name="group_display_sequence" value="{{old('group_display_sequence',0)}}" class="form-control col-sm-3">@if($errors->groupEntityRelation->has('group_display_sequence'))<div class="text-danger">{{$errors->groupEntityRelation->first('group_display_sequence')}}</div>@endif</div>
    </div>
    <div class="text-center"><button class="btn btn-primary">UserGroup関連を追加</button></div>
   </form>
  @else
   <div class="alert alert-success"><i class="fas fa-check-circle"></i> Connect-CMSユーザーグループとの関連は設定済みです。追加設定は不要です。</div>
   <div class="card border-success mb-0">
    <div class="card-header"><strong>登録済みのUserGroup関連</strong></div>
    <div class="card-body p-0">
     <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>表示名</th><th>関連先</th><th>条件</th><th></th></tr></thead><tbody>
     @foreach($group_entity_relations as $entity_relation)
      <tr><td>{{$entity_relation->relation_name}}</td><td>Connect-CMS UserGroup</td><td>任意 / 複数件 / グループ重複可</td><td class="text-right"><form action="{{url('/')}}/plugin/databaserelations/deleteEntityRelation/{{$page->id}}/{{$frame_id}}/{{$entity_relation->id}}#frame-{{$frame->id}}" method="POST" onsubmit="return confirm('このUserGroup関連定義と関連付けを削除します。よろしいですか？');">{{csrf_field()}}<button class="btn btn-sm btn-outline-danger">削除</button></form></td></tr>
     @endforeach
     </tbody></table></div>
    </div>
   </div>
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
 function dbName(select){return select&&select.options[select.selectedIndex]?select.options[select.selectedIndex].text:'';}
 function describe(){var related=document.getElementById('relation_one_db_{{$frame_id}}'),target=document.getElementById('relation_many_db_{{$frame_id}}'),box=document.getElementById('relation_description_{{$frame_id}}'),type=document.querySelector('input[name="relation_type"]:checked');if(!box)return;if(!related||!target||!related.value||!target.value){box.style.display='none';return;}var rn=dbName(related),tn=dbName(target);box.style.display='';if(type&&type.value==='many_to_many'){box.textContent='N:N：DB A「'+rn+'」とDB B「'+tn+'」の双方から複数件を関連付けられます。';}else{box.textContent='1:N：DB A「'+rn+'」の1件に、DB B「'+tn+'」の複数件を関連付けます。';}}
 ['relation_one_db_{{$frame_id}}','relation_many_db_{{$frame_id}}'].forEach(function(id){var el=document.getElementById(id);if(el)el.addEventListener('change',describe);});Array.prototype.forEach.call(document.querySelectorAll('.relation-type-{{$frame_id}}'),function(el){el.addEventListener('change',describe);});describe();
});
</script>
@endsection

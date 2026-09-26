@extends('core.cms_frame_base')
@section("plugin_contents_$frame->id")
@if(empty($database))
<div class="alert alert-info">使用するDBが設定されていません。</div>
@elseif($relations->isEmpty())
<div class="alert alert-info">このDBを含むリレーション定義はまだありません。</div>
@else
<div class="mb-3 d-flex justify-content-between align-items-center">
 <strong>使用するDB：{{$database->databases_name}}</strong>
 @if($database_frame)
 <a href="{{url('/')}}{{optional(\App\Models\Common\Page::find($database_frame->page_id))->getLinkUrl()}}?relation_return_page_id={{$page->id}}&relation_return_frame_id={{$frame_id}}#frame-{{$database_frame->id}}" class="btn btn-sm btn-outline-secondary">
  <i class="fas fa-list"></i> DB一覧を表示
 </a>
 @endif
</div>
<div class="table-responsive"><table class="table table-sm table-bordered table-hover"><thead><tr><th>レコード</th>@foreach($relations as $relation)<th>{{$relation_display->get($relation->id)->name}}</th>@endforeach @can('frames.edit',[[null,$frame->plugin_name,$buckets,$frame]])<th></th>@endcan</tr></thead><tbody>
@foreach($source_inputs as $input)<tr><td>{{$source_labels->get($input->id,'#'.$input->id)}}</td>
@foreach($relations as $relation)@php($labels=$relation_display->get($relation->id)->values->get($input->id,collect()))<td>@if($labels->isEmpty())－@else{!! $labels->map(function($v){return e($v);})->implode('<br>') !!}@endif</td>@endforeach
@can('frames.edit',[[null,$frame->plugin_name,$buckets,$frame]])<td class="text-right"><a href="{{url('/')}}/plugin/databaserelations/editRecordRelations/{{$page->id}}/{{$frame_id}}/{{$input->id}}#frame-{{$frame->id}}" class="btn btn-sm btn-outline-primary">関連設定</a></td>@endcan
</tr>@endforeach
</tbody></table></div>
@endif
@endsection

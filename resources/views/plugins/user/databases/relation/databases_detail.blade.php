{{--
 * 詳細表示画面テンプレート。
 *
 * @author 永原　篤 <nagahara@opensource-workshop.jp>
 * @author 井上 雅人 <inoue@opensource-workshop.jp / masamasamasato0216@gmail.com>
 * @author 牟田口 満 <mutaguchi@opensource-workshop.jp>
 * @copyright OpenSource-WorkShop Co.,Ltd. All Rights Reserved
 * @category データベース・プラグイン
--}}
@extends('core.cms_frame_base')

@section("plugin_contents_$frame->id")
@php
    $relation_return_page_id = (int) request('relation_return_page_id', 0);
    $relation_return_frame_id = (int) request('relation_return_frame_id', 0);
    $relation_return_frame = $relation_return_frame_id
        ? \App\Models\Common\Frame::where('id', $relation_return_frame_id)
            ->where('page_id', $relation_return_page_id)
            ->where('plugin_name', 'databaserelations')->first()
        : null;

    $relation_origin_page_id = (int) request('relation_origin_page_id', 0);
    $relation_origin_frame_id = (int) request('relation_origin_frame_id', 0);
    $relation_origin_record_id = (int) request('relation_origin_record_id', 0);
    $relation_origin_frame = $relation_origin_frame_id
        ? \App\Models\Common\Frame::where('id', $relation_origin_frame_id)
            ->where('page_id', $relation_origin_page_id)
            ->where('plugin_name', 'databases')->first()
        : null;
    $relation_origin_database = $relation_origin_frame
        ? \App\Models\User\Databases\Databases::where('bucket_id', $relation_origin_frame->bucket_id)->first()
        : null;
    $relation_origin_record = $relation_origin_database
        ? \App\Models\User\Databases\DatabasesInputs::where('id', $relation_origin_record_id)
            ->where('databases_id', $relation_origin_database->id)->first()
        : null;

    $relation_return_query = $relation_return_frame
        ? http_build_query([
            'relation_return_page_id' => $relation_return_page_id,
            'relation_return_frame_id' => $relation_return_frame_id,
        ])
        : '';
@endphp

<div class="container">
    {{-- 行グループ ループ --}}
    @php
        $row_group_count = 1;
        $column_group_count = 1;
    @endphp
    @foreach($group_rows_cols_columns as $group_row_cols_columns)
        <div class="row border-left border-right border-bottom @if($loop->first) border-top @endif {{ "row-group-" . $row_group_count++ }}">
        {{-- 列グループ ループ --}}
        @foreach($group_row_cols_columns as $group_col_columns)
            <div class="col-sm {{ "column-group-" . $column_group_count++ }}">
            {{-- カラム ループ --}}
            @foreach($group_col_columns as $column)
                <div class="row pt-2 pb-2">
                    <div class="col">
                        @if ($column->label_hide_flag == '0')
                            <small><b>{{$column->column_name}}</b></small><br>
                        @endif

                        <div class="{{$column->classname}}">
                            @include('plugins.user.databases.default.databases_include_detail_value')
                        </div>
                        <div class="small {{ $column->caption_list_detail_color }}">{!! nl2br($column->caption_list_detail) !!}</div>
                    </div>
                </div>
            @endforeach
            </div>
        @endforeach
        </div>
    @endforeach
</div>

@php
    $database_show_like_detail = FrameConfig::getConfigValueAndOld($frame_configs, DatabaseFrameConfig::database_show_like_detail, ShowType::show);
@endphp
<div class="row mt-2">
    <div class="col-12">
        {{-- いいねボタン --}}
        @include('plugins.common.like', [
            'use_like' => ($database->use_like && $database_show_like_detail),
            'like_button_name' => $database->like_button_name,
            'contents_id' => $inputs->id,
            'like_id' => $inputs->like_id,
            'like_count' => $inputs->like_count,
            'like_users_id' => $inputs->like_users_id,
        ])
    </div>
</div>

@can('role_update_or_approval', [[$inputs, $frame->plugin_name, $buckets]])
<div class="row mt-2">
    <div class="col-12 text-right mb-1">
        {{-- ステータス表示＋ボタン --}}
        @include('plugins.user.databases.default.databases_include_status_and_button', [
            'add_badge_class' => 'align-bottom',
            'input' => $inputs,
        ])
    </div>
</div>
@endcan


@php
    $relation_service = app(\App\Plugins\User\Databaserelations\Services\DatabaseRelationService::class);
    $related_records = $relation_service->getRelatedRecordsForDetail($database->id, $inputs->id);
@endphp

@if ($related_records->isNotEmpty())
<div class="card mt-4">
    <div class="card-header"><strong>関連データ</strong></div>
    <div class="card-body">
        @foreach ($related_records as $related_record)
            <div class="row @if(! $loop->last) mb-3 @endif">
                <div class="col-md-3">
                    <strong>{{ $related_record->name }}</strong>
                </div>
                <div class="col-md-9">
                    @if ($related_record->items->isNotEmpty())
                        @foreach ($related_record->items as $item)
                            <div>
                                @if ($item->detail_frame)
                                    @php
                                        $related_query = http_build_query(array_filter([
                                            'relation_origin_page_id' => $page->id,
                                            'relation_origin_frame_id' => $frame_id,
                                            'relation_origin_record_id' => $inputs->id,
                                            'relation_return_page_id' => $relation_return_frame ? $relation_return_page_id : null,
                                            'relation_return_frame_id' => $relation_return_frame ? $relation_return_frame_id : null,
                                        ], function ($value) {
                                            return !is_null($value);
                                        }));
                                    @endphp
                                    <a href="{{url('/')}}/plugin/databases/detail/{{$item->detail_frame->page_id}}/{{$item->detail_frame->id}}/{{$item->input->id}}?{{$related_query}}#frame-{{$item->detail_frame->id}}">
                                        {{ $item->label }}
                                    </a>
                                @else
                                    {{ $item->label }}
                                    <small class="text-muted">（詳細表示先を選択してください）</small>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <span class="text-muted">関連付けなし</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- 戻る --}}
<div class="row">
    <div class="col-12 text-center mt-3">
        @if($relation_origin_frame && $relation_origin_record)
            <a href="{{url('/')}}/plugin/databases/detail/{{$relation_origin_page_id}}/{{$relation_origin_frame_id}}/{{$relation_origin_record_id}}{{ $relation_return_query ? '?'.$relation_return_query : '' }}#frame-{{$relation_origin_frame_id}}">
                <span class="btn btn-info"><i class="fas fa-angle-left"></i> 元の詳細へ戻る</span>
            </a>
        @else
            @if(Session::has('page_no.'.$frame_id))
            <a href="{{url('/')}}{{$page->getLinkUrl()}}?{{http_build_query(array_filter(['frame_'.$frame_id.'_page' => Session::get('page_no.'.$frame_id), 'relation_return_page_id' => $relation_return_frame ? $relation_return_page_id : null, 'relation_return_frame_id' => $relation_return_frame ? $relation_return_frame_id : null], function ($value) { return !is_null($value); }))}}#frame-{{$frame_id}}">
            @else
            <a href="{{url('/')}}{{$page->getLinkUrl()}}?{{http_build_query(array_filter(['frame_'.$frame_id.'_page' => 1, 'relation_return_page_id' => $relation_return_frame ? $relation_return_page_id : null, 'relation_return_frame_id' => $relation_return_frame ? $relation_return_frame_id : null], function ($value) { return !is_null($value); }))}}#frame-{{$frame_id}}">
            @endif
                <span class="btn btn-info"><i class="fas fa-list"></i> <span class="d-none d-sm-inline">{{__('messages.to_list')}}</span></span>
            </a>
        @endif
    </div>
</div>

@endsection

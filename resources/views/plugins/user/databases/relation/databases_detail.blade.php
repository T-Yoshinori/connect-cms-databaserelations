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
    @foreach ($related_records as $related_record)
    <div class="card mt-4" id="relation-{{ $related_record->relation->id }}">
        <div class="card-header">
            <strong>{{ optional($related_record->database)->databases_name ?: $related_record->name }}</strong>
        </div>
        <div class="card-body">
            <form action="{{ request()->url() }}#relation-{{ $related_record->relation->id }}" method="GET" role="search" class="mb-3">
                @foreach (request()->query() as $query_key => $query_value)
                    @if (!in_array($query_key, [$related_record->search_key, $related_record->sort_key, $related_record->page_key]) && strpos($query_key, $related_record->filter_key_prefix) !== 0)
                        @if (is_array($query_value))
                            @foreach ($query_value as $query_item)
                                <input type="hidden" name="{{ $query_key }}[]" value="{{ $query_item }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $query_key }}" value="{{ $query_value }}">
                        @endif
                    @endif
                @endforeach
                <div class="form-row align-items-end">
                    <div class="col-sm mb-2 mb-sm-0">
                        <label class="sr-only" for="{{ $related_record->search_key }}">検索キーワード</label>
                        <div class="input-group">
                            <input type="text"
                                   class="form-control"
                                   id="{{ $related_record->search_key }}"
                                   name="{{ $related_record->search_key }}"
                                   value="{{ $related_record->search_keyword }}"
                                   placeholder="検索はキーワードを入力してください。">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary" title="検索">
                                    <i class="fas fa-search" role="presentation"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    @foreach ($related_record->select_columns as $select_column)
                        @php
                            $filter_key = $related_record->filter_key_prefix . $select_column->id;
                            $filter_values = (array) ($related_record->filter_values[$select_column->id] ?? []);
                            $filter_label = empty($filter_values)
                                ? $select_column->column_name
                                : $select_column->column_name . '：' . implode('、', $filter_values);
                        @endphp
                        <div class="col-sm mb-2 mb-sm-0">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle text-left w-100"
                                        type="button"
                                        id="{{ $filter_key }}_button"
                                        data-toggle="dropdown"
                                        aria-haspopup="true"
                                        aria-expanded="false">
                                    {{ $filter_label }}
                                </button>
                                <div class="dropdown-menu p-3" aria-labelledby="{{ $filter_key }}_button">
                                    @foreach ($related_record->columns_selects->where('databases_columns_id', $select_column->id) as $columns_select)
                                        @php
                                            $checkbox_id = $filter_key . '_' . $columns_select->id;
                                        @endphp
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox"
                                                   class="custom-control-input"
                                                   id="{{ $checkbox_id }}"
                                                   name="{{ $filter_key }}[]"
                                                   value="{{ $columns_select->value }}"
                                                   @if (in_array((string) $columns_select->value, $filter_values, true)) checked @endif>
                                            <label class="custom-control-label" for="{{ $checkbox_id }}">{{ $columns_select->value }}</label>
                                        </div>
                                    @endforeach
                                    <div class="mt-2 pt-2 border-top text-right">
                                        <button type="submit" class="btn btn-sm btn-primary">絞り込む</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @if ($related_record->sort_columns->isNotEmpty())
                        <div class="col-sm mb-2 mb-sm-0">
                            <label class="sr-only" for="{{ $related_record->sort_key }}">並べ替え</label>
                            <select class="form-control"
                                    id="{{ $related_record->sort_key }}"
                                    name="{{ $related_record->sort_key }}"
                                    onchange="this.form.submit()">
                                <option value="">並べ替え</option>
                                @foreach ($related_record->sort_columns as $sort_column)
                                    @if ((int) $sort_column->sort_flag === 1 || (int) $sort_column->sort_flag === 2)
                                        <option value="{{ $sort_column->id }}_asc" @if ($related_record->sort_value === $sort_column->id . '_asc') selected @endif>
                                            {{ $sort_column->column_name }}（昇順）
                                        </option>
                                    @endif
                                    @if ((int) $sort_column->sort_flag === 1 || (int) $sort_column->sort_flag === 3)
                                        <option value="{{ $sort_column->id }}_desc" @if ($related_record->sort_value === $sort_column->id . '_desc') selected @endif>
                                            {{ $sort_column->column_name }}（降順）
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if (strlen($related_record->search_keyword) || strlen($related_record->sort_value) || collect($related_record->filter_values)->flatten()->filter(function ($value) { return strlen((string) $value); })->isNotEmpty())
                        <div class="col-auto">
                            @php
                                $clear_query = request()->query();
                                unset($clear_query[$related_record->search_key], $clear_query[$related_record->sort_key], $clear_query[$related_record->page_key]);
                                foreach (array_keys($clear_query) as $clear_key) {
                                    if (strpos($clear_key, $related_record->filter_key_prefix) === 0) {
                                        unset($clear_query[$clear_key]);
                                    }
                                }
                            @endphp
                            <a class="btn btn-outline-secondary"
                               href="{{ request()->url() }}{{ $clear_query ? '?' . http_build_query($clear_query) : '' }}#relation-{{ $related_record->relation->id }}">
                                クリア
                            </a>
                        </div>
                    @endif
                </div>
            </form>

            <div class="small text-muted mb-2">
                @if ($related_record->items->total() > 0)
                    全{{ $related_record->items->total() }}件中 {{ $related_record->items->firstItem() }}～{{ $related_record->items->lastItem() }}件
                @else
                    0件
                @endif
            </div>

            @if ($related_record->items->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @foreach ($related_record->list_columns as $related_column)
                                    <th>{{ $related_column->column_name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($related_record->items as $item)
                                <tr>
                                    @foreach ($related_record->list_columns as $related_column)
                                        <td>
                                            @php
                                                $related_value = $item->column_values->get($related_column->id, '');
                                                $is_title_column = $related_record->display_column
                                                    && (int) $related_record->display_column->id === (int) $related_column->id;
                                            @endphp
                                            @if ($is_title_column && $item->detail_frame)
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
                                                    {{ strlen($related_value) ? $related_value : $item->label }}
                                                </a>
                                            @else
                                                {{ $related_value }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-muted">
                    @if (strlen($related_record->search_keyword) || collect($related_record->filter_values)->flatten()->filter(function ($value) { return strlen((string) $value); })->isNotEmpty())
                        検索・絞り込み条件に一致する関連レコードはありません。
                    @else
                        関連付けなし
                    @endif
                </div>
            @endif

            @if ($related_record->items->hasPages())
                <div class="mt-3">
                    {{ $related_record->items->links() }}
                </div>
            @endif
        </div>
    </div>
    @endforeach
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

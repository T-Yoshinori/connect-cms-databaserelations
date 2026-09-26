{{--
 * データベースリレーション 編集画面タブ
--}}
@if ($action == 'editRelations')
    <li role="presentation" class="nav-item">
        <span class="nav-link"><span class="active">リレーション設定</span></span>
    </li>
@else
    <li role="presentation" class="nav-item">
        <a href="{{url('/')}}/plugin/databaserelations/editRelations/{{$page->id}}/{{$frame->id}}#frame-{{$frame->id}}" class="nav-link">リレーション設定</a>
    </li>
@endif

# Connect-CMS DatabaseRelations

Connect-CMSの汎用データベースに、DB間およびConnect-CMSのUser / UserGroupとのリレーションを追加する非公式プラグインです。

> バージョン: 0.9.0-beta.3  
> 開発・提供: ゆうゆう企画  
> 状態: ベータ版

## 主な機能

- 2つの汎用データベース間に1対多または多対多のリレーションを定義
- 単数件側から複数の関連レコードを選択
- 複数件側から単数件側の関連レコードを選択
- どちら側から変更しても同じ関連付けを更新
- リレーションごとに表示名・表示項目・表示順を設定
- Databasesの「リレーション連携」テンプレートで関連データを詳細画面に表示
- 関連データを連携先DBの一覧表示項目による表形式で表示
- 関連データから相手側DBの詳細画面へ移動
- DatabaseRelations → DB一覧 → 詳細 → 関連先詳細 → 元詳細 → 一覧 → DatabaseRelations の戻り導線
- 1つのDBに複数の独立したリレーションを設定可能
- DBレコードとConnect-CMS Userを任意で関連付け（1レコード最大1User、同一User重複不可）
- DBレコードとConnect-CMS UserGroupを多対多で関連付け（複数Group選択、同一Groupの複数レコード利用可）

## 対応確認環境

- Connect-CMS 1.44.1
- PHP 8.2

本配布物は**新規インストール専用**です。開発途中の旧DatabaseRelationsからの更新用migrationは含みません。既存の開発版DatabaseRelations環境には上書き導入しないでください。

必ず検証環境で確認してから利用してください。本番環境へ導入する前に、ファイルとデータベースのバックアップを取得してください。

## ソースコード

- [プラグイン本体](app/Plugins/User/Databaserelations)
- [Model](app/Models/User/DatabaseRelations)
- [Migration](database/migrations)
- [DatabaseRelations画面](resources/views/plugins/user/databaserelations)
- [Databases用リレーション連携テンプレート](resources/views/plugins/user/databases/relation)

ルート直下の `app`、`database`、`resources` は、Connect-CMSへ重ねて配置するディレクトリ構成と同じです。

## ダウンロード

- [connect-cms-databaserelations-0.9.0-beta.3.zip](downloads/connect-cms-databaserelations-0.9.0-beta.3.zip)

## インストール

1. Connect-CMSのファイルとデータベースをバックアップします。
2. ZIPを展開します。
3. ZIP内の `app`、`database`、`resources` をConnect-CMSのルートへ重ねて配置します。
4. Connect-CMSのルートで `php artisan migrate` を実行します。
5. `php artisan view:clear` を実行します。
6. 管理画面で「データベースリレーション」フレームを配置します。
7. フレーム設定で管理するDBを選択し、必要なDB間リレーション、User関連、UserGroup関連を設定します。
8. 関連データをDatabases詳細画面に表示する場合は、対象Databasesフレームのテンプレートを「リレーション連携」に変更します。

## 基本構造

例: 案件DB 1 ─ N タスクDB

- 単数件側: 案件DB
- 複数件側: タスクDB
- 案件からは複数のタスクを関連付けできます。
- タスクからは1件の案件を関連付けできます。
- 同じ関連付けを双方から確認・変更できます。

多対多を選択した場合は、双方のDBレコードから複数件を直接関連付けできます。

## ベータ版の注意

- 評価・検証を目的としたベータ版です。
- 不具合時に復元できる環境で利用してください。
- Connect-CMS本体のDatabasesPlugin.phpやDatabases Modelは変更しません。
- Databasesとの画面連携は追加の「relation」テンプレートで行います。
- User / UserGroupとのEntity Relationに対応しています。Sectionsとの直接リレーションは含みません。

## ライセンス・免責

MIT Licenseで提供します。

本プラグインは、株式会社オープンソース・ワークショップが公式に提供、認定または保証するものではありません。Connect-CMS本体の著作権とライセンスは、配布元の条件に従います。

## 開発支援

設計、実装およびコードレビューにはChatGPT（OpenAI）による開発支援を利用しています。仕様決定、動作確認、公開および保守の責任は、開発・提供者が負います。

マイショップ サイト一式
============================

【フォルダ構成】
  index.html          … サイト本体（トップページ）
  submit.php          … 予約注文フォームの受信処理（PHP）
  config.sample.php   … 設定テンプレート → config.php にコピーして使う
  images/             … 写真置き場（README.txt にファイル名一覧）
  .gitignore          … config.php / orders.log などを除外

【公開方法】
  このフォルダの中身を、PHPが動くサーバーの公開フォルダ
  （public_html / www / htdocs など）にアップロードしてください。
  ※ 予約フォームを動かすには PHP が必要です。
    PHPが無い環境（GitHub Pages など）では、フォーム以外は表示されますが
    送信すると「お電話で」の案内が出ます。

【予約フォームを有効にする手順】
  1. config.sample.php を config.php という名前でコピー
  2. config.php に以下のどちらか（両方でも可）を設定
       ・LINE公式アカウント … line_token（長期アクセストークン）と line_to（userId）
       ・メール           … mail_to（通知を受け取るメールアドレス）
  3. config.php をサーバーの index.html と同じ場所にアップロード
  4. 以上。submit.php 側の修正は不要です。
  ※ config.php が無い / 未設定のうちは、フォーム送信は
     500 (server_not_configured) を返し、画面は電話注文を案内します。

【送信されたデータ】
  ・orders.log … 受信した注文が1行ずつJSONで追記されます（バックアップ）
  ・.rate/     … レート制限用の一時ファイル（自動生成）
  どちらも .gitignore 済み。サーバー上ではWebから見えない場所が理想です。

【メニューの価格・写真】
  ・価格はネット上の参考値。正式な価格は index.html の <script> 内 MENU を編集。
  ・写真は images/menu_<key>.jpg を置くと自動反映（images/README.txt 参照）。
  ・「単品・増し」タブは文字のみ表示です。

【注意】他店（ほっともっと等）やブログ・食べログの画像/文章は使用不可。

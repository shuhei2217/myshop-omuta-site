<?php
/**
 * このファイルを config.php にコピーして値を入れ、サーバーの公開フォルダ
 * （index.html と同じ場所）に置いてください。
 *
 *  cp config.sample.php config.php
 *
 * config.php は Git にもデプロイのミラーにも含めない想定です（.gitignore 済み）。
 * LINE・メールのどちらか片方でも設定すればフォームが有効になります。
 */
return [

  /* ===== LINE 公式アカウントに通知する場合 =====
   * LINE Developers コンソールで Messaging API チャネルを作成し、
   *  - チャネルアクセストークン（長期）
   *  - 通知を受け取るアカウントの userId（Basic settings → Your user ID）
   * を取得。公式アカウントを friend 追加しておくこと。
   */
  'line_token' => '',   // 例: 'AbCdEf....（長い文字列）'
  'line_to'    => '',   // 例: 'Uxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'

  /* ===== メールで通知する場合（LINE未設定でもOK。予備にもなる） ===== */
  'mail_to'    => '',   // 例: 'order@mystore.example'

  /* 同一IPあたり 10 分間の送信上限 */
  'rate_limit' => 5,
];

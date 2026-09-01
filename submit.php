<?php
declare(strict_types=1);
/**
 * マイショップ 予約注文フォーム 受信スクリプト
 * - config.php（gitignore対象）に LINE / メールの設定を入れて有効化します
 * - 未設定のうちは 500 server_not_configured を返し、画面は「お電話で」を案内します
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function out(int $code, array $body): void {
  http_response_code($code);
  echo json_encode($body, JSON_UNESCAPED_UNICODE);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  out(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

/* ---------- 設定 ---------- */
$cfgPath = __DIR__ . '/config.php';
if (!is_file($cfgPath)) out(500, ['ok' => false, 'error' => 'server_not_configured']);
$cfg = require $cfgPath;
$hasLine = is_array($cfg) && !empty($cfg['line_token']) && !empty($cfg['line_to']);
$hasMail = is_array($cfg) && !empty($cfg['mail_to']);
if (!$hasLine && !$hasMail) out(500, ['ok' => false, 'error' => 'server_not_configured']);

/* ---------- ハニーポット ---------- */
if (!empty($_POST['hp_url'])) out(200, ['ok' => true]);   // ボットには黙って成功を返す

/* ---------- レート制限（同一IP・直近10分） ---------- */
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$limit = (int)($cfg['rate_limit'] ?? 5);
$rlDir = __DIR__ . '/.rate';
@mkdir($rlDir, 0700, true);
$rlFile = $rlDir . '/' . md5($ip) . '.json';
$now  = time();
$hits = is_file($rlFile) ? (json_decode((string)file_get_contents($rlFile), true) ?: []) : [];
$hits = array_values(array_filter($hits, static fn($t) => $t > $now - 600));
if (count($hits) >= $limit) {
  out(429, ['ok' => false, 'error' => 'rate_limited',
            'message' => '短時間に送信が集中しています。少し時間をおいてお試しください。']);
}
$hits[] = $now;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

/* ---------- 入力チェック ---------- */
$shop = trim((string)($_POST['shop'] ?? ''));
$date = trim((string)($_POST['date'] ?? ''));
$time = trim((string)($_POST['time'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));
$tel  = trim((string)($_POST['tel']  ?? ''));
$note = trim((string)($_POST['note'] ?? ''));

$err = [];
if (!in_array($shop, ['大牟田店', '長洲店'], true))       $err[] = '受取店舗';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))          $err[] = '受取希望日';
if (!preg_match('/^\d{2}:\d{2}$/', $time))                $err[] = '受取希望時間';
if ($name === '' || mb_strlen($name) > 40)               $err[] = 'お名前';
if (!preg_match('/^[0-9+\-() 　]{9,20}$/u', $tel))        $err[] = 'お電話番号';
if (mb_strlen($note) > 500)                              $err[] = '備考';

$ts = strtotime($date . ' 00:00:00');
if ($ts === false || $ts < strtotime('today') || $ts > strtotime('+30 days')) $err[] = '受取希望日';

$items = json_decode((string)($_POST['items_json'] ?? '[]'), true);
$lines = [];
if (!is_array($items) || count($items) === 0 || count($items) > 40) {
  $err[] = 'ご注文内容';
} else {
  foreach ($items as $it) {
    $n = trim((string)($it['name'] ?? ''));
    $q = (int)($it['qty'] ?? 0);
    if ($n === '' || mb_strlen($n) > 40 || $q < 1 || $q > 99) { $err[] = 'ご注文内容'; break; }
    $lines[] = "・{$n} × {$q}";
  }
}

if ($err) {
  out(422, ['ok' => false, 'error' => 'invalid',
            'message' => '入力内容をご確認ください（' . implode('、', array_values(array_unique($err))) . '）']);
}

/* ---------- 通知メッセージ ---------- */
$msg  = "🍱 予約注文\n";
$msg .= "店舗: {$shop}\n";
$msg .= "受取: {$date} {$time}\n";
$msg .= "お名前: {$name} 様\n";
$msg .= "電話: {$tel}\n";
$msg .= "----\n" . implode("\n", $lines) . "\n";
if ($note !== '') $msg .= "----\n備考: {$note}\n";
$msg .= "(送信 " . date('Y-m-d H:i') . ")";

/* ---------- ログ ---------- */
@file_put_contents(
  __DIR__ . '/orders.log',
  json_encode([
    'at' => date('c'), 'ip' => $ip, 'shop' => $shop, 'date' => $date, 'time' => $time,
    'name' => $name, 'tel' => $tel, 'items' => $items, 'note' => $note,
  ], JSON_UNESCAPED_UNICODE) . "\n",
  FILE_APPEND | LOCK_EX
);

/* ---------- LINE 通知 ---------- */
$notified = false;
if ($hasLine && function_exists('curl_init')) {
  $ch = curl_init('https://api.line.me/v2/bot/message/push');
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $cfg['line_token'],
    ],
    CURLOPT_POSTFIELDS => json_encode([
      'to'       => $cfg['line_to'],
      'messages' => [['type' => 'text', 'text' => $msg]],
    ], JSON_UNESCAPED_UNICODE),
  ]);
  curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code >= 200 && $code < 300) $notified = true;
}

/* ---------- メール通知（予備 / 代替） ---------- */
if ($hasMail) {
  $subject = '【予約注文】' . $shop . ' ' . $date . ' ' . $time . ' / ' . $name;
  $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
  if (function_exists('mb_send_mail')) {
    if (@mb_send_mail($cfg['mail_to'], $subject, $msg, $headers)) $notified = true;
  } elseif (@mail($cfg['mail_to'], $subject, $msg, $headers)) {
    $notified = true;
  }
}

out(200, $notified ? ['ok' => true] : ['ok' => true, 'warning' => 'notify_failed']);

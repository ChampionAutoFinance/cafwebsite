<?php
/* Champion Auto Finance — lead intake mailer. Same-origin POST from marketing pages. */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit;
}
/* Honeypot: bots fill this; pretend success, send nothing. */
if (trim($_POST['company_website'] ?? '') !== '') { echo json_encode(['ok'=>true]); exit; }

$data = [];
foreach ($_POST as $k=>$v) { if (is_string($v)) $data[$k] = trim($v); }
$name  = $data['name']  ?? '';
$email = $data['email'] ?? '';

if ($name === '' || $email === '') {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Please add your name and email.']); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Please enter a valid email.']); exit;
}
/* Header-injection guard */
$safeName  = preg_replace('/[\r\n]+/', ' ', $name);
$safeEmail = preg_replace('/[\r\n]+/', '', $email);
if (!filter_var($safeEmail, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Please enter a valid email.']); exit;
}

$to = 'info@championautofinance.com';
$subject = 'Website lead — ' . $safeName;
$labels = ['name'=>'Name','email'=>'Email','phone'=>'Phone','topic'=>'Topic','message'=>'Message','_page'=>'Submitted from'];
$order = ['name','email','phone','topic','message','_page'];
$lines = [];
foreach ($order as $k) { if (!empty($data[$k])) $lines[] = $labels[$k].': '.$data[$k]; }
foreach ($data as $k=>$v) { if ($k==='company_website'||in_array($k,$order,true)||$v==='') continue; $lines[] = ucfirst($k).': '.$v; }
$body = "New inquiry from the Champion Auto Finance website:\n\n".implode("\n",$lines)."\n";

$fromAddr = 'no-reply@championautofinance.com';
$headers  = "From: Champion Auto Finance <$fromAddr>\r\n";
$headers .= "Reply-To: $safeName <$safeEmail>\r\n";
$headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\n";

$ok = @mail($to, $subject, $body, $headers, '-f'.$fromAddr);
if ($ok) { echo json_encode(['ok'=>true]); }
else { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'We could not send that right now. Please call (732) 618-2036 or email info@championautofinance.com.']); }

<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PBB MapServer Installer</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 860px; margin: 40px auto; padding: 0 20px; line-height: 1.5; }
    code, pre { background: #f3f5f7; border-radius: 4px; }
    code { padding: 2px 4px; }
    pre { padding: 16px; overflow: auto; }
  </style>
</head>
<body>
  <h1>PBB MapServer Installer</h1>
  <p>This first installer release is CLI/unattended-first for PBB Kit Setup orchestration.</p>
  <pre>C:\wamp64\bin\php\php8.2.29\php.exe installer\install-run.php --config C:\pbb\kit-runs\mapserver.json --report C:\pbb\kit-runs\mapserver-report.json</pre>
  <p>Machine-readable status is available from <code>installer/status.php</code> after installation.</p>
</body>
</html>

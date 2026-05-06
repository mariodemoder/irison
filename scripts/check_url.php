<?php
$url = $argv[1] ?? 'http://127.0.0.1:8000/billing/thankyou';
echo "Fetching: $url\n---\n";
$c = file_get_contents($url);
if ($c === false) { echo "Failed to fetch\n"; exit(1); }
echo substr($c,0,1200);

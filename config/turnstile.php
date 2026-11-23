<?php
// Environment values override the ignored local configuration.
$local=is_file(__DIR__.'/turnstile.local.php')?require __DIR__.'/turnstile.local.php':[];
$value=static fn(string $key,string $default='')=>getenv($key)!==false?getenv($key):($local[$key] ?? $default);
return [
    'site_key'=>$value('TURNSTILE_SITE_KEY','0x4AAAAAAF_ZcH9odxIriGrf'),
    'secret_key'=>$value('TURNSTILE_SECRET_KEY'),
];

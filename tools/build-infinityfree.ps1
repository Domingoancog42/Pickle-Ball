param([string]$OutputDirectory = (Join-Path ([Environment]::GetFolderPath('UserProfile')) 'Downloads'))
$ErrorActionPreference='Stop'
$projectRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$outputRoot=[IO.Path]::GetFullPath($OutputDirectory)
if($outputRoot.StartsWith($projectRoot+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase) -or $outputRoot -eq $projectRoot){throw 'Choose an output directory outside the website; the SQL contains private customer data.'}
$bundle=Join-Path $outputRoot ('pikolcastle-infinityfree-'+(Get-Date -Format 'yyyyMMdd-HHmmss'))
$web=Join-Path $bundle 'htdocs'
New-Item -ItemType Directory -Path $web -Force | Out-Null
foreach($folder in @('admin','api','assets','config','css','customer','html','js','storage','uploads','vendor')){
    $source=Join-Path $projectRoot $folder
    Get-ChildItem -LiteralPath $source -File -Recurse -Force | ForEach-Object {
        $relative=$_.FullName.Substring($projectRoot.Length+1)
        if($relative -match '^config[\\/].*\.local\.php$' -or $_.Extension -in @('.log','.sql','.zip','.bak','.tmp')){return}
        $target=Join-Path $web $relative
        New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
        Copy-Item -LiteralPath $_.FullName -Destination $target
    }
}
foreach($file in @('index.html','booking.html','change-password.php','.htaccess')){Copy-Item -LiteralPath (Join-Path $projectRoot $file) -Destination (Join-Path $web $file)}
Copy-Item -LiteralPath (Join-Path $projectRoot 'config/database.example.php') -Destination (Join-Path $web 'config/database.local.php')
Copy-Item -LiteralPath (Join-Path $projectRoot 'config/mail.example.php') -Destination (Join-Path $web 'config/mail.local.php')
Copy-Item -LiteralPath (Join-Path $projectRoot 'config/turnstile.example.php') -Destination (Join-Path $web 'config/turnstile.local.php')
[IO.File]::WriteAllText((Join-Path $web 'config/hosting.local.php'),"<?php`nreturn ['portable'=>true];`n",(New-Object Text.UTF8Encoding $false))
Copy-Item -LiteralPath (Join-Path $projectRoot 'docs/INFINITYFREE.md') -Destination (Join-Path $bundle 'START-HERE.md')
$php=Join-Path (Split-Path -Parent (Split-Path -Parent $projectRoot)) 'php/php.exe'
if(!(Test-Path -LiteralPath $php)){$php='php'}
& $php (Join-Path $PSScriptRoot 'export-infinityfree.php') (Join-Path $bundle 'IMPORT-DATABASE.sql')
if($LASTEXITCODE -ne 0){throw 'Database export failed; do not deploy this incomplete bundle.'}
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip=$bundle+'.zip'
[IO.Compression.ZipFile]::CreateFromDirectory($bundle,$zip,[IO.Compression.CompressionLevel]::Optimal,$true)
Write-Output "Deployment folder: $bundle"
Write-Output "Deployment ZIP: $zip"
Write-Output 'Edit htdocs/config/database.local.php, mail.local.php and turnstile.local.php before uploading htdocs contents.'

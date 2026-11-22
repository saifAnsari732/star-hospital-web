$phpDir = "$env:LOCALAPPDATA\Programs\php"
if (Test-Path $phpDir) {
    $env:Path = "$phpDir;$env:Path"
}

Write-Host "Starting Star Hospital at http://127.0.0.1:8000 ..." -ForegroundColor Cyan
Write-Host "Login: admin / 123456" -ForegroundColor Green
php artisan serve --host=127.0.0.1 --port=8000

---
name: PHP Smoke Test
description: Cómo hacer pruebas HTTP y de sintaxis del proyecto M-Core en XAMPP
---

# Smoke tests M-Core

## Lint
```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & "C:\xampp\php\php.exe" -l $_.FullName }
```

## Login (PowerShell)
```powershell
$body = @{ accion="login"; email="admin@m-core.local"; password="CambiaEstaClave123" } | ConvertTo-Json
Invoke-WebRequest -Uri "http://localhost/room/api/auth.php" -Method POST -Body $body -ContentType "application/json" -SessionVariable s
```

## Whoami
```powershell
Invoke-WebRequest -Uri "http://localhost/room/api/auth.php" -WebSession $s
```

## Endpoint protegido (cookie)
Usar `-WebSession $s` en las peticiones posteriores.

## Endpoint protegido (token)
Guardar `token` de la respuesta de login y enviar header `Authorization: Bearer <token>`.

## Verificar extensión zip
`C:\xampp\php\php.exe -m | Select-String zip`

## Reiniciar Apache
```powershell
Stop-Process -Id (Get-NetTCPConnection -LocalPort 80 -State Listen).OwningProcess -Force
Start-Process "C:\xampp\apache\bin\httpd.exe" -WorkingDirectory "C:\xampp\apache"
```

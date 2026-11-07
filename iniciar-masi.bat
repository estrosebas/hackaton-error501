@echo off
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start-local.ps1" -Terminals
if errorlevel 1 (
    echo No se pudo iniciar masi. Revisa el error anterior.
    pause
    exit /b 1
)
echo.
echo masi: http://127.0.0.1:5173/iniciar-sesion
echo Para detener los servicios ejecuta detener-masi.bat.
echo Si estaban iniciados, se reutilizan. Para abrir sus terminales, detenlos primero.

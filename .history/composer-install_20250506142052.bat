@echo off
chcp 65001 >nul

echo ========================================
echo ✅ Forçando execução com PHP do XAMPP...
echo ========================================
cd /d %~dp0

E:\xampp\php\php.exe -d curl.cainfo="E:\xampp\php\extras\ssl\cacert.pem" ^
 -d openssl.cafile="E:\xampp\php\extras\ssl\cacert.pem" ^
 E:\xampp\php\composer.phar install

pause

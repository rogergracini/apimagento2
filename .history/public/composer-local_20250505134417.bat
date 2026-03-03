@echo off
chcp 65001 >nul

set PHP_PATH=E:\xampp\php\php.exe
set COMPOSER=E:\xampp\php\composer.phar

%PHP_PATH% -d allow_url_fopen=1 -d openssl.cafile= -d curl.cainfo= %COMPOSER% install --prefer-dist -vvv

pause

@echo off
setlocal
title CompanyOS - Launcher
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
  echo [X] PHP not found. Install PHP 8.1+ and add it to PATH.
  pause
  exit /b 1
)
where node >nul 2>nul
if errorlevel 1 (
  echo [X] Node.js not found. Install Node 20+ from https://nodejs.org
  pause
  exit /b 1
)

echo [1/3] database (SQLite file: apps\php-api\database\app.db)...
if not exist "apps\php-api\database\app.db" (
  call php apps\php-api\database\migrate.php --fresh
  call php apps\php-api\database\seed.php
)

echo [2/3] starting PHP API on :4100 ...
start "CompanyOS-API" cmd /k php -S 127.0.0.1:4100 -t apps\php-api\public apps\php-api\server.php

echo [3/3] starting WEB on :3100 ...
if not exist "apps\web\node_modules" (
  cd apps\web
  call npm install --no-audit --no-fund
  cd ..\..
)
start "CompanyOS-WEB" /d "%~dp0apps\web" cmd /k npm run dev

echo.
echo =====================================================
echo  API   : http://localhost:4100/api/health
echo  WEB   : http://localhost:3100
echo  Login : founder1@example.test / ChangeMe123!
echo  Stop  : run STOP.BAT
echo =====================================================
pause

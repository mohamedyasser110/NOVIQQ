@echo off
echo Stopping CompanyOS windows...
taskkill /F /FI "WINDOWTITLE eq CompanyOS-API*" /T 2>nul
taskkill /F /FI "WINDOWTITLE eq CompanyOS-WEB*" /T 2>nul
echo Done. (If a server still runs, close its window manually.)
pause

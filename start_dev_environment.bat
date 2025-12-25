@echo off

REM Set the path to your XAMPP installation directory
set XAMPP_PATH="C:\xampp"

REM Start Apache server
start "" %XAMPP_PATH%\apache_start.bat

REM Start MySQL server
start "" %XAMPP_PATH%\mysql_start.bat

REM Simuleer de online omgeving door de hostnaam te forceren
set HTTP_HOST=office.abcbrandbeveiliging.nl

REM Open de applicatie in de standaardbrowser
start "" "http://localhost"

REM Wacht op gebruikersinvoer om het script te sluiten
pause
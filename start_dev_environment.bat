@echo off

REM Set the path to your XAMPP installation directory
set XAMPP_PATH="C:\xampp"

REM Ensure Apache serves the correct site
set APACHE_CONF=%XAMPP_PATH%\apache\conf\httpd.conf

REM Update DocumentRoot to point to the office folder
powershell -Command "(Get-Content %APACHE_CONF%) -replace 'DocumentRoot \".*?\"', 'DocumentRoot \"C:/Users/rvand/OneDrive/Bureaublad/htdocs/office\"' | Set-Content %APACHE_CONF%"
powershell -Command "(Get-Content %APACHE_CONF%) -replace '<Directory \".*?\">', '<Directory \"C:/Users/rvand/OneDrive/Bureaublad/htdocs/office\">' | Set-Content %APACHE_CONF%"

REM Start Apache server
start "" %XAMPP_PATH%\apache_start.bat

REM Simuleer de online omgeving door de hostnaam te forceren
set HTTP_HOST=office.abcbrandbeveiliging.nl

REM Open de applicatie in de standaardbrowser
start "" "http://localhost/index.php"

REM Wacht op gebruikersinvoer om het script te sluiten
pause
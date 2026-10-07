@echo off
title Congo Events - Demarrage MySQL XAMPP
echo.
echo === Demarrage MySQL (XAMPP) ===
echo.

REM Eviter le conflit avec MySQL96 (si possible)
net stop MySQL96 >nul 2>&1

REM Si mysqld tourne deja, ne rien faire
netstat -ano | findstr ":3306" | findstr "LISTENING" >nul
if %ERRORLEVEL%==0 (
  echo MySQL ecoute deja sur le port 3306.
  goto :ok
)

echo Lancement de mysqld...
start "" /B "C:\xamppss\mysql\bin\mysqld.exe" --defaults-file="C:\xamppss\mysql\bin\my.ini"

timeout /t 6 /nobreak >nul

netstat -ano | findstr ":3306" | findstr "LISTENING" >nul
if %ERRORLEVEL%==0 (
  echo OK - MySQL est demarre.
  goto :ok
)

echo ECHEC - MySQL n'a pas demarre. Ouvre XAMPP Control Panel en administrateur
echo et clique Start sur MySQL. Verifie aussi que MySQL96 est arrete.
pause
exit /b 1

:ok
echo.
echo Tu peux ouvrir : http://localhost/focus/
echo.
pause

echo off
cls
title OktoDark Studios Website
echo The site is starting soon ...
echo Please Wait!
TIMEOUT 5 >nul
echo Loading ...
TIMEOUT 5 >nul
echo Loading ... Complete
php bin/console server:run
pause
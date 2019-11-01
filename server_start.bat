echo off
cls
title OktoDark Studios Website
echo The site is starting soon ...
echo Please Wait!
TIMEOUT 5 >nul
echo Loading ...
TIMEOUT 5 >nul
echo Loading ... Complete
php -S 127.0.0.1:8000 -t public/
pause
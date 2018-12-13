echo off
cls
title OktoDark Studios Website
echo The website update to latest vendors ...
echo .
echo Please Wait!
TIMEOUT 5 >nul
echo Loading ...
TIMEOUT 5 >nul
echo Loading ... Complete
echo .
echo Composer Update
composer update
echo .
echo Yarn Update
yarn --update
echo .
echo Update Finished ... auto closing in
TIMEOUT 10

:End
PAUSE>nul
@echo off
REM EmailListVerify PHP SDK Test Runner Script for Windows

echo ================================
echo EmailListVerify SDK Test Runner
echo ================================
echo.

REM Check if PHP is installed
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: PHP is not installed or not in PATH
    echo Please install PHP 7.2 or higher
    exit /b 1
)

REM Check if Composer is installed
where composer >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: Composer is not installed or not in PATH
    echo Please install Composer from https://getcomposer.org/
    exit /b 1
)

REM Install dependencies if vendor directory doesn't exist
if not exist "vendor" (
    echo Installing dependencies...
    call composer install
    echo.
)

REM Run PHPUnit tests
echo Running tests...
echo ----------------

if exist "vendor\bin\phpunit" (
    call vendor\bin\phpunit --colors=always
) else if exist "vendor\bin\phpunit.bat" (
    call vendor\bin\phpunit.bat --colors=always
) else (
    echo Error: PHPUnit not found. Please run 'composer install' first.
    exit /b 1
)

echo.
echo ================================
echo Test run completed!
echo ================================
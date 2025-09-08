@echo off
setlocal ENABLEEXTENSIONS

REM ===== Git push to main via SSH (ASCII-only, robust) =====

REM 0) Ensure we are inside a Git repo
git rev-parse --is-inside-work-tree >nul 2>&1 || (
  echo Not inside a Git repository. Please run this in your project root.
  goto :END
)

REM 1) Make sure Windows OpenSSH agent service is enabled and running
sc query ssh-agent | find /I "RUNNING" >nul 2>&1
if errorlevel 1 (
  sc config ssh-agent start= auto >nul 2>&1
  sc start  ssh-agent >nul 2>&1
)

REM 2) Add your key to the agent (ignore errors if already added)
ssh-add "%USERPROFILE%\.ssh\id_rsa" >nul 2>&1

REM 3) Force origin to SSH URL (safe if repeated)
git remote set-url origin git@github.com:luohao0705-lang/fpjinlin.git

REM 4) Optional: show brief status
git status -s

REM 5) Commit and push
git add .
git commit -m "Auto update on %date% %time%" >nul 2>&1
git push origin main

echo.
echo ===== Done. Press any key to exit. =====
:END
pause >nul

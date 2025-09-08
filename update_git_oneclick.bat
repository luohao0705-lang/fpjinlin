@echo off
REM ===== One-click Git add/commit/push (SSH via 443) =====
REM This script assumes your repo remote is GitHub.
REM It will enforce the remote to SSH over 443 for reliability, then commit and push to main.

REM 0) Ensure we are in a Git repo
git rev-parse --is-inside-work-tree >nul 2>&1 || (
  echo Not inside a Git repository. Place this .bat in your project root.
  pause >nul
  exit /b 1
)

REM 1) Force remote to SSH over 443 (safe to run multiple times)
git remote set-url origin ssh://git@ssh.github.com:443/luohao0705-lang/fpjinlin.git

REM 2) Add, commit, push
git add .
git commit -m "Auto update on %DATE% %TIME%" >nul 2>&1
git push origin main

echo.
echo Done. Press any key to exit.
pause >nul

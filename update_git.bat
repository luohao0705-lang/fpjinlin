@echo off
REM 自动提交并推送到 main 分支

git add .
git commit -m "Auto update on %date% %time%"
git push origin main

pause

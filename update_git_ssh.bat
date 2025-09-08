@echo off
REM ===== Git 推送到 main（SSH 方式，适合国内网络） =====
REM 1) 启动并配置 ssh-agent（需要 Windows 自带 OpenSSH 客户端）
powershell -NoProfile -ExecutionPolicy Bypass -Command "Try { Get-Service ssh-agent | Set-Service -StartupType Automatic; Start-Service ssh-agent } Catch { }"

REM 2) 将私钥加载到 agent（如果未加载会提示输入 passphrase；已加载会报 'already exists' 无视即可）
ssh-add "%USERPROFILE%\.ssh\id_rsa" 2>nul

REM 3) 将 origin 改为 SSH 地址（重复执行也没问题）
git remote set-url origin git@github.com:luohao0705-lang/fpjinlin.git

REM 4) 显示分支和状态（可选）
git status -sb

REM 5) 提交并推送
git add .
git commit -m "Auto update on %date% %time%" || echo No changes to commit.
git push origin main

echo.
echo ===== All done. Press any key to exit. =====
pause >nul

-- 修复管理员密码哈希问题
-- 这个SQL脚本用于修复admin账户的密码哈希
-- 将密码从错误的Laravel测试哈希修复为正确的admin123哈希

-- 更新admin账户的密码哈希为正确的admin123对应的哈希
UPDATE admins SET password_hash = '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm' WHERE username = 'admin';

-- 验证更新结果
SELECT username, password_hash, status, role, created_at FROM admins WHERE username = 'admin';
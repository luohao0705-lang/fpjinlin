<?php
/**
 * 逐步调试启动分析功能
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>逐步调试启动分析功能</h2>";

$orderId = 24;

echo "<h3>步骤1: 基础环境检查</h3>";
echo "PHP版本: " . PHP_VERSION . "<br>";
echo "当前目录: " . getcwd() . "<br>";

echo "<h3>步骤2: 加载配置文件</h3>";
try {
    require_once 'config/config.php';
    echo "✅ config.php 加载成功<br>";
} catch (Exception $e) {
    echo "❌ config.php 加载失败: " . $e->getMessage() . "<br>";
    exit;
} catch (Error $e) {
    echo "❌ config.php 加载失败(致命错误): " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>步骤3: 加载数据库类</h3>";
try {
    require_once 'config/database.php';
    echo "✅ database.php 加载成功<br>";
} catch (Exception $e) {
    echo "❌ database.php 加载失败: " . $e->getMessage() . "<br>";
    exit;
} catch (Error $e) {
    echo "❌ database.php 加载失败(致命错误): " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>步骤4: 创建数据库连接</h3>";
try {
    $db = new Database();
    echo "✅ 数据库连接成功<br>";
} catch (Exception $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "<br>";
    exit;
} catch (Error $e) {
    echo "❌ 数据库连接失败(致命错误): " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>步骤5: 检查订单</h3>";
try {
    $order = $db->fetchOne("SELECT * FROM video_analysis_orders WHERE id = ?", [$orderId]);
    if (!$order) {
        echo "❌ 订单不存在<br>";
        exit;
    }
    echo "✅ 订单存在: " . $order['title'] . "<br>";
    echo "订单状态: " . $order['status'] . "<br>";
} catch (Exception $e) {
    echo "❌ 查询订单失败: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>步骤6: 检查视频文件</h3>";
try {
    $videoFiles = $db->fetchAll("SELECT * FROM video_files WHERE order_id = ?", [$orderId]);
    echo "✅ 视频文件数量: " . count($videoFiles) . "<br>";
    foreach ($videoFiles as $file) {
        echo "- 文件ID: {$file['id']}, 类型: {$file['video_type']}, FLV地址: " . ($file['flv_url'] ? '已设置' : '未设置') . "<br>";
    }
} catch (Exception $e) {
    echo "❌ 查询视频文件失败: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>步骤7: 加载VideoAnalysisOrder类</h3>";
try {
    require_once 'includes/classes/VideoAnalysisOrder.php';
    echo "✅ VideoAnalysisOrder类加载成功<br>";
} catch (Exception $e) {
    echo "❌ VideoAnalysisOrder类加载失败: " . $e->getMessage() . "<br>";
    exit;
} catch (Error $e) {
    echo "❌ VideoAnalysisOrder类加载失败(致命错误): " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>步骤8: 创建VideoAnalysisOrder实例</h3>";
try {
    $videoAnalysisOrder = new VideoAnalysisOrder();
    echo "✅ VideoAnalysisOrder实例创建成功<br>";
} catch (Exception $e) {
    echo "❌ VideoAnalysisOrder实例创建失败: " . $e->getMessage() . "<br>";
    exit;
} catch (Error $e) {
    echo "❌ VideoAnalysisOrder实例创建失败(致命错误): " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>步骤9: 调用startAnalysis方法</h3>";
try {
    $result = $videoAnalysisOrder->startAnalysis($orderId);
    echo "✅ startAnalysis执行成功<br>";
    echo "结果: " . json_encode($result) . "<br>";
} catch (Exception $e) {
    echo "❌ startAnalysis执行失败: " . $e->getMessage() . "<br>";
    echo "文件: " . $e->getFile() . "<br>";
    echo "行号: " . $e->getLine() . "<br>";
    echo "堆栈: <pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "❌ startAnalysis执行失败(致命错误): " . $e->getMessage() . "<br>";
    echo "文件: " . $e->getFile() . "<br>";
    echo "行号: " . $e->getLine() . "<br>";
    echo "堆栈: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h3>调试完成</h3>";
?>

<?php
// 开启会话
session_start();

// 检查是否已安装
if (!file_exists('includes/installed.lock')) {
    header('Location: install.php');
    exit;
}

// 引入必要的文件
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// 如果已经登录，跳转到首页
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// 处理注册请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // 表单验证
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = '所有字段都必须填写';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '用户名长度必须在3-20个字符之间';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } elseif (strlen($password) < 6) {
        $error = '密码长度不能少于6个字符';
    } elseif ($password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // 检查用户名是否已存在
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = '用户名已存在';
            } else {
                // 检查邮箱是否已存在
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = '邮箱已被注册';
                } else {
                    // 创建新用户
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'subscriber', 1)");
                    $stmt->execute([$username, $email, $password_hash]);
                    
                    $success = '注册成功！您现在可以登录了。';
                }
            }
        } catch (Exception $e) {
            $error = '注册失败：' . $e->getMessage();
        }
    }
}

// 获取网站设置
$site_title = get_setting('site_title', '我的博客');
$site_url = get_setting('site_url', '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册 - <?php echo htmlspecialchars($site_title); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $site_url; ?>assets/css/auth.css">
</head>
<body>
    <!-- 宏伟背景 -->
    <div class="cosmic-background">
        <div class="stars"></div>
        <div class="nebula"></div>
        <div class="cosmic-particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
    </div>
    
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-container">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1 class="auth-title">用户注册</h1>
                <p class="auth-subtitle">创建您的账户，开始您的博客之旅</p>
            </div>
            
            <form method="post" action="" id="registerForm">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-user form-icon"></i>
                        <input type="text" class="form-control" name="username" 
                               placeholder="用户名" required maxlength="20" autocomplete="username"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-envelope form-icon"></i>
                        <input type="email" class="form-control" name="email" 
                               placeholder="邮箱地址" required autocomplete="email"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-lock form-icon"></i>
                        <input type="password" class="form-control" name="password" 
                               placeholder="密码" required minlength="6" autocomplete="new-password">
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-lock form-icon"></i>
                        <input type="password" class="form-control" name="confirm_password" 
                               placeholder="确认密码" required minlength="6" autocomplete="new-password">
                    </div>
                </div>
                
                <button type="submit" class="btn-auth" id="registerBtn">
                    <div class="btn-content">
                        <span class="btn-text">立即注册</span>
                        <div class="loading-spinner" id="loadingSpinner"></div>
                    </div>
                </button>
            </form>
            
            <div class="auth-links">
                <p>已有账户？<a href="<?php echo $site_url; ?>login.php">立即登录</a></p>
                <p><a href="<?php echo $site_url; ?>">返回首页</a></p>
            </div>
            
            <p class="footer-text">
                &copy; <?php echo date('Y'); ?> 
                HM博客程序
                <br>
                <small>Powered by HM CMS</small>
            </p>
        </div>
    </div>
    
    <script src="assets/js/auth.js"></script>
</body>
</html> 
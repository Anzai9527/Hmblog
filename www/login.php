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

// 处理权限错误消息
if (isset($_GET['error']) && $_GET['error'] === 'permission_denied') {
    $error = '抱歉，您的账户权限不足，无法访问后台管理系统。只有管理员和编辑才能访问后台。';
}

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // 查找用户（支持用户名或邮箱登录）
            $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 1 LIMIT 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // 登录成功
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_email'] = $user['email'];
                
                // 更新最后登录时间
                $stmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // 重定向到请求的页面或首页
                $redirect = $_GET['redirect'] ?? 'index.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        } catch (Exception $e) {
            $error = '登录失败：' . $e->getMessage();
        }
    }
}

// 获取网站设置
$site_title = get_setting('site_title', '我的博客');
$site_url = get_setting('site_url', '');
$site_url = rtrim($site_url, '/') . '/';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户登录 - <?php echo htmlspecialchars($site_title); ?></title>
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
                    <i class="fas fa-user-circle"></i>
                </div>
                <h1 class="auth-title">用户登录</h1>
                <p class="auth-subtitle">欢迎回来，请登录您的账户</p>
            </div>
            
            <form method="post" action="" id="loginForm">
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
                               placeholder="用户名或邮箱" required autocomplete="username"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-lock form-icon"></i>
                        <input type="password" class="form-control" name="password" 
                               placeholder="密码" required autocomplete="current-password">
                    </div>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">记住我</label>
                </div>
                
                <button type="submit" class="btn-auth" id="loginBtn">
                    <div class="btn-content">
                        <span class="btn-text">立即登录</span>
                        <div class="loading-spinner" id="loadingSpinner"></div>
                    </div>
                </button>
            </form>
            
            <div class="auth-links">
                <p>还没有账户？<a href="<?php echo $site_url; ?>register.php">立即注册</a></p>
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
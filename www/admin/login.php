<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// 如果已经登录，跳转到后台首页
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } else {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // 检查用户是否有后台访问权限
            if ($user['role'] !== 'admin' && $user['role'] !== 'editor') {
                $error = '抱歉，只有管理员和编辑才能访问后台管理系统';
            } else {
                // 登录成功
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                
                // 更新最后登录时间
                $stmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                header('Location: index.php');
                exit;
            }
        } else {
            $error = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台登录 - <?php echo escape(get_setting('site_title', '我的博客')); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #667eea 50%, #764ba2 75%, #f093fb 100%);
            background-size: 400% 400%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            25% { background-position: 100% 50%; }
            50% { background-position: 100% 100%; }
            75% { background-position: 0% 100%; }
        }

        /* 宏伟背景效果 */
        .cosmic-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .stars {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(2px 2px at 20px 30px, #eee, transparent),
                radial-gradient(2px 2px at 40px 70px, rgba(255,255,255,0.8), transparent),
                radial-gradient(1px 1px at 90px 40px, #fff, transparent),
                radial-gradient(1px 1px at 130px 80px, rgba(255,255,255,0.6), transparent),
                radial-gradient(2px 2px at 160px 30px, #ddd, transparent);
            background-repeat: repeat;
            background-size: 200px 100px;
            animation: twinkle 4s ease-in-out infinite;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.8; }
            50% { opacity: 1; }
        }

        .nebula {
            position: absolute;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(ellipse at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(ellipse at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 50%);
            animation: nebulaFloat 20s ease-in-out infinite;
        }

        @keyframes nebulaFloat {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(1deg); }
        }

        .cosmic-particles {
            position: absolute;
            width: 100%;
            height: 100%;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            animation: cosmicFloat 25s infinite linear;
        }

        .particle:nth-child(1) {
            width: 4px;
            height: 4px;
            left: 10%;
            animation-delay: 0s;
            animation-duration: 30s;
        }

        .particle:nth-child(2) {
            width: 6px;
            height: 6px;
            right: 15%;
            animation-delay: 5s;
            animation-duration: 35s;
        }

        .particle:nth-child(3) {
            width: 3px;
            height: 3px;
            left: 30%;
            top: 60%;
            animation-delay: 10s;
            animation-duration: 25s;
        }

        .particle:nth-child(4) {
            width: 5px;
            height: 5px;
            right: 25%;
            top: 30%;
            animation-delay: 15s;
            animation-duration: 40s;
        }

        .particle:nth-child(5) {
            width: 2px;
            height: 2px;
            left: 60%;
            top: 20%;
            animation-delay: 20s;
            animation-duration: 20s;
        }

        @keyframes cosmicFloat {
            0% {
                transform: translateY(100vh) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) translateX(100px) rotate(360deg);
                opacity: 0;
            }
        }

        .admin-login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 500px;
            padding: 40px 20px;
        }

        .admin-login-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            box-shadow: 
                0 40px 80px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            padding: 60px 50px;
            text-align: center;
            animation: cardAppear 1.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            overflow: hidden;
        }

        .admin-login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #667eea);
            background-size: 300% 100%;
            animation: rainbowShimmer 4s ease-in-out infinite;
        }

        @keyframes rainbowShimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(80px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .admin-login-header {
            margin-bottom: 50px;
        }

        .admin-logo-container {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 
                0 30px 60px rgba(102, 126, 234, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            animation: logoGlow 3s ease-in-out infinite;
        }

        @keyframes logoGlow {
            0%, 100% { 
                box-shadow: 
                    0 30px 60px rgba(102, 126, 234, 0.4),
                    0 0 0 1px rgba(255, 255, 255, 0.3),
                    inset 0 1px 0 rgba(255, 255, 255, 0.2);
            }
            50% { 
                box-shadow: 
                    0 30px 60px rgba(102, 126, 234, 0.6),
                    0 0 30px rgba(102, 126, 234, 0.3),
                    0 0 0 1px rgba(255, 255, 255, 0.3),
                    inset 0 1px 0 rgba(255, 255, 255, 0.2);
            }
        }

        .admin-logo-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: rotate(45deg);
            animation: cosmicShine 4s ease-in-out infinite;
        }

        @keyframes cosmicShine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
            100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        }

        .admin-logo-container i {
            font-size: 48px;
            color: white;
            z-index: 2;
            position: relative;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .admin-login-title {
            color: #ffffff;
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -1px;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .admin-login-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 18px;
            font-weight: 400;
            line-height: 1.6;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .admin-form-group {
            margin-bottom: 30px;
            position: relative;
        }

        .admin-input-wrapper {
            position: relative;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .admin-input-wrapper:focus-within {
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 
                0 0 0 6px rgba(255, 255, 255, 0.1),
                0 20px 40px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
        }

        .admin-form-control {
            width: 100%;
            border: none;
            background: transparent;
            padding: 22px 25px 22px 65px;
            font-size: 18px;
            font-weight: 500;
            color: #ffffff;
            outline: none;
            transition: all 0.3s ease;
        }

        .admin-form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
            font-weight: 400;
        }

        .admin-form-icon {
            position: absolute;
            left: 25px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            font-size: 20px;
            transition: all 0.3s ease;
            z-index: 2;
        }

        .admin-input-wrapper:focus-within .admin-form-icon {
            color: #ffffff;
            transform: translateY(-50%) scale(1.2);
        }

        .admin-btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            border: none;
            border-radius: 20px;
            padding: 22px 40px;
            font-size: 18px;
            font-weight: 700;
            color: white;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 
                0 20px 40px rgba(102, 126, 234, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.1);
        }

        .admin-btn-login:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 30px 60px rgba(102, 126, 234, 0.6),
                0 0 0 1px rgba(255, 255, 255, 0.2);
        }

        .admin-btn-login:active {
            transform: translateY(-2px);
        }

        .admin-btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.8s ease;
        }

        .admin-btn-login:hover::before {
            left: 100%;
        }

        .admin-btn-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .admin-loading-spinner {
            display: none;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .admin-alert {
            border-radius: 20px;
            border: none;
            padding: 20px 25px;
            margin-bottom: 30px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: alertSlide 0.5s ease-out;
            backdrop-filter: blur(10px);
        }

        @keyframes alertSlide {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .admin-alert-danger {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.9) 0%, rgba(238, 90, 82, 0.9) 100%);
            color: white;
            box-shadow: 
                0 15px 35px rgba(255, 107, 107, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.1);
        }

        .admin-footer-text {
            margin-top: 40px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
            font-weight: 400;
            line-height: 1.6;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .admin-footer-text a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
        }

        .admin-footer-text a:hover {
            text-decoration: underline;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .admin-login-container {
                padding: 20px;
            }
            
            .admin-login-card {
                padding: 50px 40px;
                border-radius: 25px;
            }
            
            .admin-login-title {
                font-size: 36px;
            }
            
            .admin-logo-container {
                width: 100px;
                height: 100px;
            }
            
            .admin-logo-container i {
                font-size: 40px;
            }
            
            .admin-form-control {
                padding: 20px 22px 20px 60px;
                font-size: 16px;
            }
            
            .admin-btn-login {
                padding: 20px 35px;
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .admin-login-card {
                padding: 40px 30px;
                border-radius: 20px;
            }
            
            .admin-login-title {
                font-size: 30px;
            }
            
            .admin-logo-container {
                width: 90px;
                height: 90px;
            }
            
            .admin-logo-container i {
                font-size: 36px;
            }
            
            .admin-form-control {
                padding: 18px 20px 18px 55px;
                font-size: 15px;
            }
            
            .admin-btn-login {
                padding: 18px 30px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body class="admin-login-body">
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
    
    <div class="admin-login-container">
        <div class="admin-login-card">
            <div class="admin-login-header">
                <div class="admin-logo-container">
                    <i class="fas fa-crown"></i>
                </div>
                <h1 class="admin-login-title">管理后台</h1>
                <p class="admin-login-subtitle">欢迎来到管理中心，请登录您的账户</p>
            </div>
            
            <form method="post" action="" id="loginForm">
                <?php if ($error): ?>
                <div class="admin-alert admin-alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo escape($error); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="admin-form-group">
                    <div class="admin-input-wrapper">
                        <i class="fas fa-user admin-form-icon"></i>
                        <input type="text" class="admin-form-control" id="username" name="username" 
                               placeholder="请输入用户名" required autocomplete="username">
                    </div>
                </div>
                
                <div class="admin-form-group">
                    <div class="admin-input-wrapper">
                        <i class="fas fa-lock admin-form-icon"></i>
                        <input type="password" class="admin-form-control" id="password" name="password" 
                               placeholder="请输入密码" required autocomplete="current-password">
                    </div>
                </div>
                
                <button type="submit" class="admin-btn-login" id="loginBtn">
                    <div class="admin-btn-content">
                        <span class="btn-text">立即登录</span>
                        <div class="admin-loading-spinner" id="loadingSpinner"></div>
                    </div>
                </button>
            </form>
            
            <p class="admin-footer-text">
                &copy; <?php echo date('Y'); ?> 
                HM博客程序
                <br>
                <small>Powered by HM CMS</small>
            </p>
        </div>
    </div>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html> 
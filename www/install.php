<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查PHP版本
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('需要PHP 7.4或更高版本。当前版本：' . PHP_VERSION);
}

// 检查必要的PHP扩展
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        die("缺少必要的PHP扩展：$ext");
    }
}

// 检查目录权限
function check_directory_permissions() {
    $base_dirs = [
        'includes',
        'content',
        'assets'
    ];
    
    $errors = [];
    
    // 首先检查基础目录
    foreach ($base_dirs as $dir) {
        if (!file_exists($dir)) {
            if (!is_writable('.')) {
                $errors[] = "根目录不可写，无法创建 '$dir' 目录";
                $errors[] = "当前目录权限：" . substr(sprintf('%o', fileperms('.')), -4);
                $errors[] = "当前目录所有者：" . get_current_user() . ":" . get_current_group();
            }
        } else if (!is_writable($dir)) {
            $errors[] = "目录 '$dir' 不可写";
            $errors[] = "目录 '$dir' 权限：" . substr(sprintf('%o', fileperms($dir)), -4);
            $errors[] = "目录 '$dir' 所有者：" . get_current_user() . ":" . get_current_group();
        }
    }
    
    return $errors;
}

// 获取当前组名
function get_current_group() {
    if (function_exists('posix_getgrgid') && function_exists('posix_geteuid')) {
        $group = posix_getgrgid(posix_getegid());
        return $group['name'];
    }
    return 'unknown';
}

// 创建目录函数
function create_directories() {
    $directories = [
        'includes',
        'content',
        'content/articles',
        'content/feeds',
        'assets',
        'assets/uploads',
        'assets/uploads/images',
        'assets/uploads/files'
    ];
    
    $errors = [];
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            try {
                if (!mkdir($dir, 0755, true)) {
                    $current_perms = is_writable(dirname($dir)) ? 
                        substr(sprintf('%o', fileperms(dirname($dir))), -4) : 'unknown';
                    $errors[] = "无法创建目录：$dir";
                    $errors[] = "父目录权限：$current_perms";
                    $errors[] = "当前用户：" . get_current_user();
                } else {
                    // 确保新创建的目录有正确的权限
                    chmod($dir, 0755);
                }
            } catch (Exception $e) {
                $errors[] = "创建目录 $dir 时出错：" . $e->getMessage();
            }
        }
    }
    
    return $errors;
}

// 如果已经安装过，显示错误信息
if (file_exists('includes/installed.lock')) {
    die('博客系统已经安装。如果需要重新安装，请删除 includes/installed.lock 文件。');
}

// 定义安装步骤
$steps = ['welcome', 'requirements', 'database', 'site', 'admin', 'finish'];
$current_step = isset($_GET['step']) ? $_GET['step'] : 'welcome';

// 如果是POST请求，处理表单数据
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['database'])) {
        try {
            // 验证并保存数据库配置
            $db_host = trim($_POST['db_host']);
            $db_name = trim($_POST['db_name']);
            $db_user = trim($_POST['db_user']);
            $db_pass = $_POST['db_pass'];

            // 尝试连接数据库
            try {
                $dsn = "mysql:host={$db_host};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                // 创建数据库
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                
                // 选择数据库
                $pdo->exec("USE `{$db_name}`");

                // 保存数据库配置
                $_SESSION['install_db'] = [
                    'host' => $db_host,
                    'name' => $db_name,
                    'user' => $db_user,
                    'pass' => $db_pass
                ];

                // 生成配置文件内容
                $config_content = "<?php\n";
                $config_content .= "// 数据库配置\n";
                $config_content .= "define('DB_HOST', '{$db_host}');\n";
                $config_content .= "define('DB_NAME', '{$db_name}');\n";
                $config_content .= "define('DB_USER', '{$db_user}');\n";
                $config_content .= "define('DB_PASS', '{$db_pass}');\n";
                $config_content .= "define('DB_CHARSET', 'utf8mb4');\n\n";
                $config_content .= "// 其他配置将在安装过程中添加\n";
                
                // 保存配置文件
                if (!is_dir('includes')) {
                    mkdir('includes', 0755, true);
                }
                file_put_contents('includes/config.php', $config_content);

                header('Location: install.php?step=site');
                exit;
            } catch (PDOException $e) {
                $error_message = "数据库连接失败: " . $e->getMessage();
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    } elseif (isset($_POST['site'])) {
        // 保存站点信息
        $_SESSION['install_site'] = [
            'title' => $_POST['site_title'],
            'description' => $_POST['site_description'],
            'author' => $_POST['site_author'],
            'keywords' => $_POST['site_keywords'],
            'url' => $_POST['site_url']
        ];
        header('Location: install.php?step=admin');
        exit;
    } elseif (isset($_POST['admin'])) {
        // 保存管理员信息
        $_SESSION['install_admin'] = [
            'username' => $_POST['admin_username'],
            'password' => password_hash($_POST['admin_password'], PASSWORD_DEFAULT),
            'email' => $_POST['admin_email']
        ];
        header('Location: install.php?step=finish');
        exit;
    }
}

// 在welcome步骤中添加系统检查
if ($current_step === 'welcome') {
    $permission_errors = check_directory_permissions();
}

// 安装完成步骤
if ($current_step === 'finish' && isset($_SESSION['install_db'], $_SESSION['install_site'], $_SESSION['install_admin'])) {
    try {
        // 创建必要的目录
        $directory_errors = create_directories();
        if (!empty($directory_errors)) {
            throw new Exception("创建目录时出错：\n" . implode("\n", $directory_errors));
        }

        $db = new PDO(
            "mysql:host={$_SESSION['install_db']['host']};dbname={$_SESSION['install_db']['name']};charset=utf8mb4",
            $_SESSION['install_db']['user'],
            $_SESSION['install_db']['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        
        // 禁用外键检查
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");

        // 先删除所有现有的表
        $tables = [
            'comment_likes',
            'post_tags',
            'post_categories',
            'comments',
            'tags',
            'navigation',
            'settings',
            'posts',
            'categories',
            'users'
        ];
        
        foreach ($tables as $table) {
            $db->exec("DROP TABLE IF EXISTS `$table`");
        }
        
        // 创建用户表
        $db->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `email` varchar(100) NOT NULL,
            `role` enum('admin','editor','subscriber') DEFAULT 'subscriber',
            `status` tinyint(1) DEFAULT 1,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `last_login` datetime DEFAULT NULL,
            `avatar` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 创建分类表
        $db->exec("CREATE TABLE IF NOT EXISTS `categories` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(50) NOT NULL,
            `slug` varchar(50) NOT NULL,
            `description` varchar(255) DEFAULT NULL,
            `parent_id` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 创建标签表
        $db->exec("CREATE TABLE IF NOT EXISTS `tags` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(50) NOT NULL,
            `slug` varchar(50) NOT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 创建文章表
        $db->exec("CREATE TABLE IF NOT EXISTS `posts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `slug` varchar(255) DEFAULT NULL,
            `content` text,
            `cover_image` varchar(255) DEFAULT NULL COMMENT '文章封面图片路径',
            `excerpt` text,
            `status` enum('publish','draft','pending','private') DEFAULT 'draft',
            `author_id` int(11) NOT NULL,
            `category_id` int(11) DEFAULT NULL,
            `views` int(11) DEFAULT 0,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `likes` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `slug` (`slug`),
            KEY `author_id` (`author_id`),
            KEY `category_id` (`category_id`),
            FULLTEXT KEY `title_content` (`title`,`content`),
            CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `posts_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 创建文章标签关联表
        $db->exec("CREATE TABLE IF NOT EXISTS `post_tags` (
            `post_id` int(11) NOT NULL,
            `tag_id` int(11) NOT NULL,
            PRIMARY KEY (`post_id`,`tag_id`),
            KEY `tag_id` (`tag_id`),
            CONSTRAINT `post_tags_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
            CONSTRAINT `post_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 创建文章分类关联表
        $db->exec("CREATE TABLE IF NOT EXISTS `post_categories` (
            `post_id` int(11) NOT NULL,
            `category_id` int(11) NOT NULL,
            PRIMARY KEY (`post_id`,`category_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 创建评论表
        $db->exec("CREATE TABLE IF NOT EXISTS `comments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `post_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `parent_id` int(11) DEFAULT NULL,
            `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `status` enum('approved','pending','spam','deleted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
            `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `user_agent` text COLLATE utf8mb4_unicode_ci,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `post_id` (`post_id`),
            KEY `user_id` (`user_id`),
            KEY `parent_id` (`parent_id`),
            KEY `status` (`status`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 创建评论点赞表
        $db->exec("CREATE TABLE IF NOT EXISTS `comment_likes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `comment_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_like` (`comment_id`,`user_id`),
            KEY `comment_id` (`comment_id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 创建导航表
        $db->exec("CREATE TABLE IF NOT EXISTS `navigation` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(50) NOT NULL,
            `url` varchar(255) NOT NULL,
            `order` int(11) DEFAULT 0,
            `parent_id` int(11) DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 创建设置表
        $db->exec("CREATE TABLE IF NOT EXISTS `settings` (
            `name` varchar(50) NOT NULL,
            `value` text,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 启用外键检查
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");

        // 插入默认数据
        // 创建管理员账户
        try {
            $stmt = $db->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([
                $_SESSION['install_admin']['username'],
                $_SESSION['install_admin']['password'],
                $_SESSION['install_admin']['email']
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // 如果是重复键错误
                throw new Exception("创建管理员账户失败：用户名或邮箱已存在。请尝试使用不同的用户名和邮箱。");
            }
            throw $e;
        }

        // 创建默认分类
        $stmt = $db->prepare("INSERT INTO categories (name, slug, description, parent_id) VALUES (?, ?, ?, ?)");
        $stmt->execute(['默认分类', 'default', '默认分类描述', null]);

        // 插入网站设置
        $settings = [
            ['site_title', $_SESSION['install_site']['title']],
            ['site_description', $_SESSION['install_site']['description']],
            ['site_url', $_SESSION['install_site']['url']],
            ['site_author', $_SESSION['install_site']['author']],
            ['site_keywords', $_SESSION['install_site']['keywords']],
            ['posts_per_page', '10'],
            ['default_category', '1'],
            ['allow_comments', '1'],
            ['allow_comment_replies', '1'],
            ['comment_approval_required', '1'],
            ['comment_max_length', '1000'],
            ['comment_per_page', '10'],
            ['comments_per_page', '20'],
            ['comment_allow_html', '0'],
            ['comment_close_days', '30'],
            ['comment_notification_email', ''],
            ['moderate_comments', '0'],
            ['auto_generate_sitemap', '0'],
            ['sitemap_update_frequency', 'daily'],
            ['banner_image', '']
        ];

        $stmt = $db->prepare("INSERT INTO settings (name, value) VALUES (?, ?)");
        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }

        // 创建安装锁定文件
        file_put_contents('includes/installed.lock', date('Y-m-d H:i:s'));
        
        // 清除安装会话数据
        session_destroy();
        
    } catch (Exception $e) {
        die("安装失败：" . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>博客系统安装向导</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-radius: 12px;
            --box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
        }

        .install-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .step-indicator {
            margin-bottom: 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }

        .step-indicator .step {
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            margin: 0 5px;
            color: white;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .step-indicator .step::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: var(--transition);
        }

        .step-indicator .step:hover::before {
            left: 100%;
        }

        .step-indicator .step.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .step-indicator .step.completed {
            background: var(--success-color);
            color: white;
        }

        .main-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: var(--transition);
        }

        .main-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px 30px;
            border: none;
        }

        .card-header h2 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .card-body {
            padding: 30px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 8px;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.8);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }

        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .btn {
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: var(--transition);
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8, #6a4190);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #218838, #1ea085);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: currentColor;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border-left: 4px solid var(--warning-color);
        }

        .alert-info {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
            border-left: 4px solid var(--info-color);
        }

        .system-check {
            margin-bottom: 30px;
        }

        .system-check h4 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 600;
        }

        .check-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
            transition: var(--transition);
        }

        .check-item:hover {
            background: rgba(102, 126, 234, 0.05);
            padding-left: 10px;
            border-radius: 6px;
        }

        .check-item:last-child {
            border-bottom: none;
        }

        .check-status {
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        .check-status.success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .check-status.error {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .progress-bar {
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list li::before {
            content: '✓';
            color: var(--success-color);
            font-weight: bold;
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .welcome-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .step-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }

        @media (max-width: 768px) {
            .install-container {
                margin: 15px auto;
                padding: 15px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .step-indicator .step {
                margin: 5px;
                padding: 10px 15px;
                font-size: 0.9rem;
            }

            .card-body {
                padding: 20px;
            }
        }

        /* 动画效果 */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* 加载动画 */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="header fade-in-up">
            <div class="welcome-icon">
                <i class="fas fa-rocket"></i>
            </div>
            <h1>博客系统安装向导</h1>
            <p>快速、安全、专业的博客系统安装</p>
        </div>
        
        <div class="step-indicator d-flex justify-content-between mb-4 fade-in-up">
            <?php foreach ($steps as $index => $step): ?>
            <div class="step <?php echo $current_step === $step ? 'active' : ''; ?> <?php echo array_search($current_step, $steps) > $index ? 'completed' : ''; ?>">
                <i class="step-icon fas <?php
                switch ($step) {
                    case 'welcome': echo 'fa-home'; break;
                    case 'requirements': echo 'fa-check-circle'; break;
                    case 'database': echo 'fa-database'; break;
                    case 'site': echo 'fa-cog'; break;
                    case 'admin': echo 'fa-user-shield'; break;
                    case 'finish': echo 'fa-flag-checkered'; break;
                }
                ?>"></i>
                <?php
                switch ($step) {
                    case 'welcome': echo '欢迎'; break;
                    case 'requirements': echo '环境检查'; break;
                    case 'database': echo '数据库配置'; break;
                    case 'site': echo '站点信息'; break;
                    case 'admin': echo '管理员账户'; break;
                    case 'finish': echo '完成安装'; break;
                }
                ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="main-card fade-in-up">
            <div class="card-header">
                <h2>
                    <i class="fas <?php
                    switch ($current_step) {
                        case 'welcome': echo 'fa-home'; break;
                        case 'requirements': echo 'fa-check-circle'; break;
                        case 'database': echo 'fa-database'; break;
                        case 'site': echo 'fa-cog'; break;
                        case 'admin': echo 'fa-user-shield'; break;
                        case 'finish': echo 'fa-flag-checkered'; break;
                    }
                    ?> me-2"></i>
                    <?php
                    switch ($current_step) {
                        case 'welcome': echo '欢迎使用博客系统'; break;
                        case 'requirements': echo '系统环境检查'; break;
                        case 'database': echo '数据库配置'; break;
                        case 'site': echo '站点信息配置'; break;
                        case 'admin': echo '管理员账户设置'; break;
                        case 'finish': echo '安装完成'; break;
                    }
                    ?>
                </h2>
            </div>
            <div class="card-body">
                <?php if ($current_step === 'welcome'): ?>
                <div class="text-center mb-4">
                    <h3 class="text-primary mb-3">欢迎使用现代化的博客系统</h3>
                    <p class="text-muted">这个向导将帮助您完成博客系统的安装。在开始之前，请确保您的服务器环境满足以下要求：</p>
                </div>
                
                <div class="system-check">
                    <h4><i class="fas fa-server me-2"></i>系统环境检查</h4>
                    
                    <div class="check-item">
                        <span><i class="fas fa-code me-2"></i>PHP版本 (需要 >= 7.4)</span>
                        <span class="check-status <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'success' : 'error'; ?>">
                            <i class="fas <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'fa-check' : 'fa-times'; ?> me-1"></i>
                            <?php echo PHP_VERSION; ?>
                        </span>
                    </div>
                    
                    <?php foreach ($required_extensions as $ext): ?>
                    <div class="check-item">
                        <span><i class="fas fa-puzzle-piece me-2"></i>PHP扩展：<?php echo $ext; ?></span>
                        <span class="check-status <?php echo extension_loaded($ext) ? 'success' : 'error'; ?>">
                            <i class="fas <?php echo extension_loaded($ext) ? 'fa-check' : 'fa-times'; ?> me-1"></i>
                            <?php echo extension_loaded($ext) ? '已安装' : '未安装'; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (!empty($permission_errors)): ?>
                    <div class="alert alert-danger mt-4">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>目录权限问题：</h5>
                        <ul class="mb-3">
                            <?php foreach ($permission_errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i>解决方案：</h6>
                            <ol>
                                <li>
                                    <strong>方法1：设置目录权限</strong>
                                    <pre class="bg-light p-2 rounded mt-2"><code>chmod -R 755 .</code></pre>
                                </li>
                                <li>
                                    <strong>方法2：修改目录所有者</strong>
                                    <pre class="bg-light p-2 rounded mt-2"><code># 如果使用 Nginx：
chown -R nginx:nginx .

# 如果使用 Apache：
chown -R apache:apache .
# 或
chown -R www-data:www-data .</code></pre>
                                </li>
                                <li>
                                    <strong>方法3：为特定目录单独设置权限</strong>
                                    <pre class="bg-light p-2 rounded mt-2"><code>mkdir -p assets/uploads content/articles content/feeds
chmod -R 755 assets content includes</code></pre>
                                </li>
                            </ol>
                            <div class="mt-3">
                                <strong><i class="fas fa-info-circle me-2"></i>提示：</strong>
                                <ul class="mb-0">
                                    <li>当前运行的PHP用户：<code><?php echo get_current_user(); ?></code></li>
                                    <li>当前组：<code><?php echo get_current_group(); ?></code></li>
                                    <li>如果您使用的是共享主机，请联系主机提供商获取正确的权限设置方法。</li>
                                    <li>某些主机面板（如 cPanel）提供文件管理器，可以通过界面设置权限。</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="text-primary mb-3"><i class="fas fa-star me-2"></i>系统特性</h5>
                        <ul class="feature-list">
                            <li>现代化的响应式设计</li>
                            <li>完整的评论系统</li>
                            <li>标签和分类管理</li>
                            <li>SEO优化支持</li>
                            <li>文件上传功能</li>
                            <li>用户权限管理</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-primary mb-3"><i class="fas fa-shield-alt me-2"></i>安全特性</h5>
                        <ul class="feature-list">
                            <li>密码加密存储</li>
                            <li>SQL注入防护</li>
                            <li>XSS攻击防护</li>
                            <li>CSRF保护</li>
                            <li>文件上传安全</li>
                            <li>会话管理</li>
                        </ul>
                    </div>
                </div>
                
                <?php if (empty($permission_errors)): ?>
                <div class="text-center mt-4">
                    <a href="?step=database" class="btn btn-primary btn-lg pulse">
                        <i class="fas fa-arrow-right me-2"></i>开始安装
                    </a>
                </div>
                <?php else: ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    请先解决以上问题，然后刷新页面继续安装。
                </div>
                <?php endif; ?>

                <?php elseif ($current_step === 'database'): ?>
                <div class="text-center mb-4">
                    <h3 class="text-primary mb-3"><i class="fas fa-database me-2"></i>数据库配置</h3>
                    <p class="text-muted">请提供您的数据库连接信息。如果数据库不存在，系统会尝试创建它。</p>
                </div>
                
                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo nl2br(htmlspecialchars($error_message)); ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="" id="dbForm" onsubmit="return validateForm()">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-server me-2"></i>数据库主机</label>
                                <input type="text" class="form-control" name="db_host" id="db_host" 
                                       value="<?php echo isset($_POST['db_host']) ? htmlspecialchars($_POST['db_host']) : 'localhost'; ?>" required>
                                <div class="form-text">通常是 localhost 或 127.0.0.1</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-database me-2"></i>数据库名称</label>
                                <input type="text" class="form-control" name="db_name" id="db_name" 
                                       value="<?php echo isset($_POST['db_name']) ? htmlspecialchars($_POST['db_name']) : ''; ?>" required>
                                <div class="form-text">如果数据库不存在，系统会尝试创建它</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-user me-2"></i>数据库用户名</label>
                                <input type="text" class="form-control" name="db_user" id="db_user" 
                                       value="<?php echo isset($_POST['db_user']) ? htmlspecialchars($_POST['db_user']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-lock me-2"></i>数据库密码</label>
                                <input type="password" class="form-control" name="db_pass" id="db_pass" required>
                                <div class="form-text text-danger">注意：密码字段为必填项</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="showPassword">
                            <label class="form-check-label" for="showPassword">
                                <i class="fas fa-eye me-2"></i>显示密码
                            </label>
                        </div>
                    </div>
                    
                    <div id="formData" class="alert alert-info" style="display: none;"></div>
                    
                    <div class="text-center">
                        <button type="submit" name="database" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>下一步
                        </button>
                    </div>
                </form>

                <script>
                function validateForm() {
                    var host = document.getElementById('db_host').value;
                    var name = document.getElementById('db_name').value;
                    var user = document.getElementById('db_user').value;
                    var pass = document.getElementById('db_pass').value;

                    if (!pass) {
                        alert('请输入数据库密码！');
                        return false;
                    }

                    // 显示表单数据（不包括密码的具体内容）
                    var formDataDiv = document.getElementById('formData');
                    formDataDiv.innerHTML = '<i class="fas fa-info-circle me-2"></i>即将提交的数据：<br>' +
                        '<strong>主机:</strong> ' + host + '<br>' +
                        '<strong>数据库:</strong> ' + name + '<br>' +
                        '<strong>用户名:</strong> ' + user + '<br>' +
                        '<strong>密码是否已填写:</strong> ' + (pass ? '是' : '否');
                    formDataDiv.style.display = 'block';

                    return true;
                }

                // 显示/隐藏密码
                document.getElementById('showPassword').addEventListener('change', function() {
                    var passField = document.getElementById('db_pass');
                    passField.type = this.checked ? 'text' : 'password';
                });
                </script>

                <?php elseif ($current_step === 'site'): ?>
                <div class="text-center mb-4">
                    <h3 class="text-primary mb-3"><i class="fas fa-cog me-2"></i>站点信息配置</h3>
                    <p class="text-muted">请配置您网站的基本信息，这些信息将用于SEO和网站显示。</p>
                </div>
                
                <form method="post" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-heading me-2"></i>站点标题</label>
                                <input type="text" class="form-control" name="site_title" required>
                                <div class="form-text">网站的主要标题，将显示在浏览器标签页和页面标题中</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-user me-2"></i>站点作者</label>
                                <input type="text" class="form-control" name="site_author" 
                                       value="<?php echo isset($_POST['site_author']) ? htmlspecialchars($_POST['site_author']) : ''; ?>">
                                <div class="form-text">网站作者或管理员名称</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-align-left me-2"></i>站点描述</label>
                        <textarea class="form-control" name="site_description" rows="3"></textarea>
                        <div class="form-text">网站的简短描述，用于SEO和社交媒体分享</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-tags me-2"></i>站点关键词</label>
                        <input type="text" class="form-control" name="site_keywords" 
                               value="<?php echo isset($_POST['site_keywords']) ? htmlspecialchars($_POST['site_keywords']) : ''; ?>">
                        <div class="form-text">网站关键词，用逗号分隔，用于SEO</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-link me-2"></i>站点URL</label>
                        <input type="url" class="form-control" name="site_url" 
                               value="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']); ?>" required>
                        <div class="form-text">网站的完整URL地址</div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="site" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>下一步
                        </button>
                    </div>
                </form>

                <?php elseif ($current_step === 'admin'): ?>
                <div class="text-center mb-4">
                    <h3 class="text-primary mb-3"><i class="fas fa-user-shield me-2"></i>管理员账户设置</h3>
                    <p class="text-muted">创建您的管理员账户，用于登录后台管理系统。</p>
                </div>
                
                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo nl2br(htmlspecialchars($error_message)); ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="" id="adminForm" onsubmit="return validateAdminForm()">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-user me-2"></i>用户名</label>
                                <input type="text" class="form-control" name="admin_username" id="admin_username" 
                                       value="<?php echo isset($_POST['admin_username']) ? htmlspecialchars($_POST['admin_username']) : ''; ?>"
                                       pattern="[a-zA-Z0-9_-]{4,20}" required>
                                <div class="form-text">用户名只能包含字母、数字、下划线和连字符，长度4-20个字符</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-envelope me-2"></i>电子邮箱</label>
                                <input type="email" class="form-control" name="admin_email" id="admin_email" 
                                       value="<?php echo isset($_POST['admin_email']) ? htmlspecialchars($_POST['admin_email']) : ''; ?>"
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-lock me-2"></i>密码</label>
                                <input type="password" class="form-control" name="admin_password" id="admin_password" 
                                       minlength="8" required>
                                <div class="form-text">密码长度至少8个字符</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-check-circle me-2"></i>确认密码</label>
                                <input type="password" class="form-control" id="confirm_password" 
                                       minlength="8" required>
                                <div class="form-text text-danger" id="password_match_msg" style="display: none;">
                                    <i class="fas fa-times me-1"></i>两次输入的密码不匹配
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="showAdminPassword">
                            <label class="form-check-label" for="showAdminPassword">
                                <i class="fas fa-eye me-2"></i>显示密码
                            </label>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="admin" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>下一步
                        </button>
                    </div>
                </form>

                <script>
                function validateAdminForm() {
                    var username = document.getElementById('admin_username').value;
                    var password = document.getElementById('admin_password').value;
                    var confirmPassword = document.getElementById('confirm_password').value;
                    var email = document.getElementById('admin_email').value;
                    var passwordMatchMsg = document.getElementById('password_match_msg');

                    // 验证用户名格式
                    if (!username.match(/^[a-zA-Z0-9_-]{4,20}$/)) {
                        alert('用户名格式不正确！只能包含字母、数字、下划线和连字符，长度4-20个字符');
                        return false;
                    }

                    // 验证密码长度
                    if (password.length < 8) {
                        alert('密码长度必须至少8个字符！');
                        return false;
                    }

                    // 验证密码匹配
                    if (password !== confirmPassword) {
                        passwordMatchMsg.style.display = 'block';
                        return false;
                    } else {
                        passwordMatchMsg.style.display = 'none';
                    }

                    // 验证邮箱格式
                    if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                        alert('请输入有效的电子邮箱地址！');
                        return false;
                    }

                    return true;
                }

                // 显示/隐藏密码
                document.getElementById('showAdminPassword').addEventListener('change', function() {
                    var passField = document.getElementById('admin_password');
                    var confirmField = document.getElementById('confirm_password');
                    var type = this.checked ? 'text' : 'password';
                    passField.type = type;
                    confirmField.type = type;
                });

                // 实时检查密码匹配
                document.getElementById('confirm_password').addEventListener('input', function() {
                    var password = document.getElementById('admin_password').value;
                    var confirmPassword = this.value;
                    var passwordMatchMsg = document.getElementById('password_match_msg');
                    
                    if (password !== confirmPassword) {
                        passwordMatchMsg.style.display = 'block';
                    } else {
                        passwordMatchMsg.style.display = 'none';
                    }
                });
                </script>

                <?php elseif ($current_step === 'finish'): ?>
                <div class="text-center">
                    <div class="welcome-icon text-success mb-4">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="text-success mb-3">恭喜！博客系统安装成功</h3>
                    <p class="text-muted mb-4">您的博客系统已经成功安装并配置完成。现在您可以开始使用这个强大的博客平台了！</p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="fas fa-tachometer-alt fa-3x text-success mb-3"></i>
                                <h5>管理后台</h5>
                                <p class="text-muted">登录后台管理系统，管理您的博客内容</p>
                                <a href="admin/login.php" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt me-2"></i>登录后台
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-home fa-3x text-primary mb-3"></i>
                                <h5>访问首页</h5>
                                <p class="text-muted">查看您的博客首页，体验用户界面</p>
                                <a href="index.php" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt me-2"></i>访问首页
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-4">
                    <i class="fas fa-shield-alt me-2"></i>
                    <strong>安全提醒：</strong>为了安全起见，请删除 install.php 文件。
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>下一步建议：</strong>
                    <ul class="mb-0 mt-2">
                        <li>登录后台，创建您的第一篇博客文章</li>
                        <li>配置网站主题和个性化设置</li>
                        <li>添加分类和标签</li>
                        <li>配置评论系统</li>
                        <li>备份您的数据库</li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // 添加页面加载动画
        document.addEventListener('DOMContentLoaded', function() {
            // 为所有输入框添加焦点效果
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
            
            // 为按钮添加点击效果
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });
    </script>
</body>
</html> 
</html> 
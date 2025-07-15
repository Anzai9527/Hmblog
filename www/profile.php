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

// 检查登录状态
if (!is_logged_in()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// 获取数据库连接
$db = Database::getInstance()->getConnection();

// 获取用户信息
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 用户名和邮箱不允许修改，直接使用现有值
    $username = $user['username'];
    $email = $user['email'];
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // 由于用户名和邮箱不允许修改，跳过基本信息验证
    try {
            
            // 检查密码修改
            if (!$error && !empty($new_password)) {
                if (empty($current_password)) {
                    $error = '请输入当前密码';
                } elseif (!password_verify($current_password, $user['password'])) {
                    $error = '当前密码错误';
                } elseif (strlen($new_password) < 6) {
                    $error = '新密码长度不能少于6个字符';
                } elseif ($new_password !== $confirm_password) {
                    $error = '两次输入的新密码不一致';
                }
            }
            
            // 更新用户信息
            if (!$error) {
                if (!empty($new_password)) {
                    // 只更新密码
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$password_hash, $user['id']]);
                    
                    // 重新获取用户信息
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $user = $stmt->fetch();
                    
                    $success = '密码修改成功';
                } else {
                    $success = '没有需要更新的信息';
                }
            }
        } catch (Exception $e) {
            $error = '更新失败：' . $e->getMessage();
        }
    }

// 获取用户发表的文章统计
$stmt = $db->prepare("SELECT COUNT(*) as count FROM posts WHERE author_id = ?");
$stmt->execute([$user['id']]);
$user_posts = $stmt->fetch()['count'];

// 获取用户最近的文章
$stmt = $db->prepare("SELECT id, title, created_at, status FROM posts WHERE author_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$recent_posts = $stmt->fetchAll();

// 获取网站设置
$site_title = get_setting('site_title', '我的博客');
$site_url = get_setting('site_url', '');
$site_url = rtrim($site_url, '/') . '/';

// 获取分类列表
$categories = get_categories_with_count();

// 获取标签云
$tags = get_all_tags();

// 获取归档列表
$archives = get_archives();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人中心 - <?php echo htmlspecialchars($site_title); ?></title>
    <link rel="stylesheet" href="<?php echo $site_url; ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo $site_url; ?>assets/css/profile.css">
</head>
<body>
    <script>
    document.addEventListener('contextmenu', function(e) {
      e.preventDefault();
    });
    </script>
    <div class="profile-container">
        <!-- 左侧边栏 -->
        <aside class="profile-sidebar">
            <!-- 用户信息 -->
            <div class="user-profile">
                <?php if (is_logged_in()): ?>
                    <img src="<?php echo get_user_avatar($_SESSION['user_id']); ?>" alt="用户头像" class="avatar">
                    <div class="user-info">
                        <span class="name">欢迎，<?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="count"><a href="<?php echo $site_url; ?>profile.php" style="color: #667eea;">个人中心</a></span>
                    </div>
                    <div class="user-info">
                        <span class="name">文章</span>
                        <span class="count"><?php echo get_total_posts(); ?></span>
                    </div>
                    <div class="user-info">
                        <span class="name">浏览</span>
                        <span class="count"><?php echo get_total_views(); ?></span>
                    </div>
                    <div class="user-info">
                        <span class="name">点赞</span>
                        <span class="count"><?php echo get_total_likes(); ?></span>
                    </div>
                    <div class="user-info">
                        <span class="name"><a href="<?php echo $site_url; ?>logout.php" style="color: #dc3545;">退出登录</a></span>
                        <span class="count"></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 导航菜单 -->
            <nav class="main-nav">
                <a href="<?php echo $site_url; ?>" class="nav-item">
                    <i class="icon">🏠</i>
                    <span>首页</span>
                </a>
                <a href="<?php echo $site_url; ?>index/tags.html" class="nav-item">
                    <i class="icon">🏷️</i>
                    <span>标签搜索</span>
                </a>
                
                <!-- 分类菜单 -->
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                    <a href="<?php echo $site_url; ?>index/category/<?php echo urlencode($category['name']); ?>.html" class="nav-item">
                        <i class="icon">📰</i>
                        <span><?php echo htmlspecialchars($category['name']); ?></span>
                        <span class="count">(<?php echo $category['post_count']; ?>)</span>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </nav>

            <!-- 标签云 -->
            <div class="tags-section">
                <?php foreach ($tags as $tag): ?>
                <a href="<?php echo $site_url; ?>index/tag/<?php echo urlencode($tag['name']); ?>.html" 
                   class="tag-item"
                   style="background-color: <?php echo generate_tag_color($tag['name']); ?>">
                    <?php echo htmlspecialchars($tag['name']); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- 归档 -->
            <div class="archive-section">
                <?php foreach ($archives as $archive): ?>
                <a href="<?php echo $site_url; ?>index/archive/<?php echo urlencode($archive['date']); ?>.html" class="archive-item">
                    <span class="date"><?php echo htmlspecialchars($archive['date']); ?></span>
                    <span class="count"><?php echo $archive['count']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="profile-main-content">
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="<?php echo get_user_avatar($user['id']); ?>" alt="用户头像">
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $user_posts; ?></span>
                        <span class="stat-label">发表文章</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo ucfirst($user['role']); ?></span>
                        <span class="stat-label">用户角色</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></span>
                        <span class="stat-label">注册时间</span>
                    </div>
                </div>
            </div>
            <div class="profile-content-row">
                <div class="profile-card">
                    <h2 class="card-title">修改密码</h2>
                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        ⚠️ <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        ✅ <?php echo htmlspecialchars($success); ?>
                    </div>
                    <?php endif; ?>
                    <form method="post" action="">
                        <div class="form-group">
                            <label class="form-label">当前密码（修改密码时需要）</label>
                            <input type="password" class="form-control" name="current_password">
                        </div>
                        <div class="form-group">
                            <label class="form-label">新密码（留空则不修改）</label>
                            <input type="password" class="form-control" name="new_password" minlength="6">
                        </div>
                        <div class="form-group">
                            <label class="form-label">确认新密码</label>
                            <input type="password" class="form-control" name="confirm_password" minlength="6">
                        </div>
                        <button type="submit" class="btn-primary">修改密码</button>
                    </form>
                </div>
                <div class="profile-card">
                    <h2 class="card-title">我的文章</h2>
                    <?php if (empty($recent_posts)): ?>
                    <p style="text-align: center; color: #666; margin: 40px 0;">
                        您还没有发表任何文章
                    </p>
                    <?php else: ?>
                    <div class="posts-list">
                        <?php foreach ($recent_posts as $post): ?>
                        <div class="post-item">
                            <div>
                                <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                                <div class="post-meta"><?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></div>
                            </div>
                            <span class="post-status <?php echo $post['status']; ?>">
                                <?php echo $post['status'] === 'publish' ? '已发布' : '草稿'; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'admin' || $user['role'] === 'editor'): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="admin/index.php" class="btn-primary" style="text-decoration: none;">
                            🛠️ 管理后台
                        </a>
                    </div>
                    <div style="text-align: center; margin-top: 10px; font-size: 12px; color: #666;">
                        <?php echo $user['role'] === 'admin' ? '管理员权限：可访问所有后台功能' : '编辑权限：可管理文章和分类'; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 
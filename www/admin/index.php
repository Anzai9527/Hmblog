<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once 'includes/auth.php';

// 后台首页需要编辑以上权限
require_admin_permission('editor');

// 获取数据库连接
$db = Database::getInstance()->getConnection();

// 获取统计数据
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'publish' THEN 1 ELSE 0 END) as published,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
    FROM posts");
$post_stats = $stmt->fetch();

// 分类统计
$stmt = $db->query("SELECT COUNT(*) FROM categories");
$category_count = $stmt->fetchColumn();

// 用户统计
$stmt = $db->query("SELECT COUNT(*) FROM users");
$user_count = $stmt->fetchColumn();

// 最近文章
$stmt = $db->query("SELECT p.*, u.username as author_name 
                    FROM posts p 
                    LEFT JOIN users u ON p.author_id = u.id 
                    ORDER BY p.created_at DESC 
                    LIMIT 5");
$recent_posts = $stmt->fetchAll();

// 最近登录用户
$stmt = $db->query("SELECT username, last_login 
                    FROM users 
                    WHERE last_login IS NOT NULL 
                    ORDER BY last_login DESC 
                    LIMIT 5");
$recent_users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - <?php echo escape(get_setting('site_title', '我的博客')); ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/sidebar.css" rel="stylesheet">
    <link href="assets/dashboard.css" rel="stylesheet">
</head>
<body>
    
    <!-- 侧边栏 -->
    <nav class="sidebar" id="sidebar">
        <a href="index.php" class="sidebar-brand">
            <i class="fas fa-blog"></i>
            HM管理系统
        </a>
        <ul class="nav nav-tabs flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="index.php">
                    <i class="fas fa-home"></i> 仪表盘
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="posts.php">
                    <i class="fas fa-file-alt"></i> 文章管理
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="categories.php">
                    <i class="fas fa-folder"></i> 分类管理
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="comments.php">
                    <i class="fas fa-comments"></i> 评论管理
                </a>
            </li>
            <?php if (is_admin()): ?>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users"></i> 用户管理
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog"></i> 系统设置
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> 退出登录
                </a>
            </li>
        </ul>
    </nav>

    <!-- 主内容区域 -->
    <main class="main-content">
        <div class="page-header animate-fade-in mb-4">
            <h1 class="page-title">仪表盘</h1>
            
            <?php if (isset($_GET['error']) && $_GET['error'] === 'permission_denied'): ?>
            <div class="mt-3">
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <div>权限不足，无法访问请求的页面。</div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-3">
                <div class="alert alert-info d-flex align-items-center">
                    <i class="fas fa-info-circle me-2"></i>
                    <div>
                        <strong>欢迎，<?php echo escape($_SESSION['username'] ?? ''); ?>！</strong>
                        您当前的角色是：
                        <span class="badge <?php echo get_current_user_role() === 'admin' ? 'bg-danger' : 'bg-warning'; ?>">
                            <?php echo get_current_user_role() === 'admin' ? '管理员' : '编辑'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 统计卡片 -->
        <div class="row g-4 animate-fade-in">
            <div class="col-md-4">
                <div class="stat-card bg-gradient-primary">
                    <div class="stat-card-body">
                        <div class="stat-card-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-card-info">
                            <div class="stat-card-number"><?php echo $post_stats['total']; ?></div>
                            <div class="stat-card-label">文章总数</div>
                            <div class="stat-card-detail">
                                <span class="badge bg-light text-dark">已发布: <?php echo $post_stats['published']; ?></span>
                                <span class="badge bg-light text-dark">草稿: <?php echo $post_stats['draft']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-gradient-success">
                    <div class="stat-card-body">
                        <div class="stat-card-icon">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="stat-card-info">
                            <div class="stat-card-number"><?php echo $category_count; ?></div>
                            <div class="stat-card-label">分类数量</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-gradient-info">
                    <div class="stat-card-body">
                        <div class="stat-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-card-info">
                            <div class="stat-card-number"><?php echo $user_count; ?></div>
                            <div class="stat-card-label">用户总数</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 最近文章和登录记录 -->
        <div class="row g-4 mt-2">
            <div class="col-md-8">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title">
                            <i class="fas fa-clock me-2"></i>最近文章
                        </h5>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>标题</th>
                                        <th>作者</th>
                                        <th>状态</th>
                                        <th>发布时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_posts)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">暂无文章</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($recent_posts as $post): ?>
                                    <tr>
                                        <td>
                                            <a href="posts.php?action=edit&id=<?php echo $post['id']; ?>" class="text-decoration-none text-reset">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-file-alt text-primary me-2"></i>
                                                    <?php echo escape($post['title']); ?>
                                                </div>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user text-muted me-1"></i>
                                                <?php echo escape($post['author_name']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($post['status'] === 'publish'): ?>
                                            <span class="status-badge published">已发布</span>
                                            <?php else: ?>
                                            <span class="status-badge draft">草稿</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center text-muted">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                <?php echo date('Y-m-d', strtotime($post['created_at'])); ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title">
                            <i class="fas fa-sign-in-alt me-2"></i>最近登录
                        </h5>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>用户名</th>
                                        <th>登录时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_users)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center py-4">暂无登录记录</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-circle text-primary me-2"></i>
                                                <?php echo escape($user['username']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('Y-m-d H:i', strtotime($user['last_login'])); ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    
    <!-- 移动端菜单JavaScript -->
    <script>
        // 移动端菜单切换
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (mobileMenuToggle && sidebar) {
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                mobileMenuToggle.classList.toggle('active');
            });
            
            // 点击侧边栏外部关闭菜单
            document.addEventListener('click', function(event) {
                if (!sidebar.contains(event.target) && !mobileMenuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                    mobileMenuToggle.classList.remove('active');
                }
            });
            
            // 窗口大小改变时重置菜单状态
            window.addEventListener('resize', function() {
                if (window.innerWidth > 992) {
                    sidebar.classList.remove('active');
                    mobileMenuToggle.classList.remove('active');
                }
            });
        }
        
        // 触摸设备优化
        if ('ontouchstart' in window) {
            document.body.classList.add('touch-device');
        }
        
        // 表格响应式处理
        const tables = document.querySelectorAll('.table-responsive');
        tables.forEach(table => {
            if (table.scrollWidth > table.clientWidth) {
                table.style.overflowX = 'auto';
            }
        });
    </script>
</body>
</html> 
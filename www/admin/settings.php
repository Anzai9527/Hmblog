<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once 'includes/auth.php';

// 系统设置只允许管理员访问
require_admin_permission('admin');

// 获取数据库连接
$db = Database::getInstance()->getConnection();

    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 处理XML地区生成
        if (isset($_POST['generate_xml_sitemap'])) {
            generate_xml_sitemap();
            header('Location: settings.php?message=XML网站地图生成成功');
            exit;
        }
        
        if (isset($_POST['generate_xml_rss'])) {
            generate_xml_rss();
            header('Location: settings.php?message=XML RSS订阅生成成功');
            exit;
        }
        
        $settings = [
            'site_title' => $_POST['site_title'],
            'site_description' => $_POST['site_description'],
            'site_keywords' => $_POST['site_keywords'],
            'site_author' => $_POST['site_author'],
            'site_url' => $_POST['site_url'],
            'posts_per_page' => (int)$_POST['posts_per_page'],
            'allow_comments' => isset($_POST['allow_comments']) ? 1 : 0,
            'moderate_comments' => isset($_POST['moderate_comments']) ? 1 : 0,
            'home_banner_title' => $_POST['home_banner_title'] ?? ''
        ];

    // 处理banner图片上传
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['banner_image']['name']);
        $file_name = 'banner_' . time() . '.' . $file_info['extension'];
        $upload_path = $upload_dir . $file_name;
        
        // 检查文件类型
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($file_info['extension']), $allowed_types)) {
            if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $upload_path)) {
                $settings['banner_image'] = 'uploads/images/' . $file_name;
                
                // 删除旧的banner图片
                $old_banner = get_setting('banner_image');
                if ($old_banner && file_exists('../' . $old_banner)) {
                    unlink('../' . $old_banner);
                }
            }
        }
    }

    foreach ($settings as $key => $value) {
        $stmt = $db->prepare("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$key, $value, $value]);
    }

    header('Location: settings.php?message=设置保存成功');
    exit;
}

// 获取当前设置
$settings = [];
$result = $db->query("SELECT name, value FROM settings");
while ($row = $result->fetch()) {
    $settings[$row['name']] = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - <?php echo escape(get_setting('site_title', '我的博客')); ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/sidebar.css" rel="stylesheet">
    <link href="assets/dashboard.css" rel="stylesheet">
</head>
<body>
    <!-- 侧边栏 -->
    <nav class="sidebar">
        <a href="index.php" class="sidebar-brand">
            <i class="fas fa-blog"></i>
            HM管理系统
        </a>
        <ul class="nav nav-tabs flex-column">
            <li class="nav-item">
                <a class="nav-link" href="index.php">
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
                <a class="nav-link active" href="settings.php">
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
        <div class="container-fluid">
            <div class="page-header mb-4">
                <h1 class="page-title">系统设置</h1>
            </div>

            <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success animate-fade-in">
                <?php echo escape($_GET['message']); ?>
            </div>
            <?php endif; ?>

            <div class="dashboard-card animate-fade-in">
                <div class="dashboard-card-header">
                    <h5 class="dashboard-card-title">
                        <i class="fas fa-cog me-2"></i>系统设置
                    </h5>
                </div>
                <div class="dashboard-card-body">
                    <form method="post" enctype="multipart/form-data">
                        <!-- SEO设置 -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-search me-2"></i>SEO优化设置
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">网站标题</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                        <input type="text" class="form-control" name="site_title" 
                                               value="<?php echo escape($settings['site_title'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">网站URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-link"></i></span>
                                        <input type="url" class="form-control" name="site_url" 
                                               value="<?php echo escape($settings['site_url'] ?? ''); ?>" 
                                               placeholder="https://example.com">
                                    </div>
                                    <div class="form-text">用于SEO优化的网站完整URL</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">网站作者</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" name="site_author" 
                                               value="<?php echo escape($settings['site_author'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">网站关键词</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-tags"></i></span>
                                        <input type="text" class="form-control" name="site_keywords" 
                                               value="<?php echo escape($settings['site_keywords'] ?? ''); ?>" 
                                               placeholder="用逗号分隔关键词">
                                    </div>
                                    <div class="form-text">用于SEO优化的网站关键词，用逗号分隔</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">网站描述</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-quote-left"></i></span>
                                        <textarea class="form-control" name="site_description" rows="3"><?php 
                                            echo escape($settings['site_description'] ?? ''); 
                                        ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 网站设置 -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-desktop me-2"></i>网站设置
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Banner图片</label>
                                    <?php if (!empty($settings['banner_image'])): ?>
                                    <div class="mb-2">
                                        <img src="../<?php echo escape($settings['banner_image']); ?>" 
                                             alt="当前Banner" class="img-thumbnail" style="max-width: 200px; max-height: 100px;">
                                        <div class="text-muted small">当前Banner图片</div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-image"></i></span>
                                        <input type="file" class="form-control" name="banner_image" 
                                               accept="image/*">
                                    </div>
                                    <div class="form-text">支持 JPG、PNG、GIF、WebP 格式，建议尺寸 1200x400px</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">首页Banner标题</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-heading"></i></span>
                                        <input type="text" class="form-control" name="home_banner_title" value="<?php echo escape($settings['home_banner_title'] ?? ''); ?>" placeholder="请输入首页Banner上的标题">
                                    </div>
                                    <div class="form-text">显示在首页Banner图片上的大标题</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">每页文章数</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-list"></i></span>
                                        <input type="number" class="form-control" name="posts_per_page" 
                                               value="<?php echo (int)($settings['posts_per_page'] ?? 10); ?>" 
                                               min="1" max="50" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 评论设置 -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-comments me-2"></i>评论设置
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="allow_comments" name="allow_comments" 
                                               <?php echo ($settings['allow_comments'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allow_comments">
                                            <i class="fas fa-comments me-1"></i>允许评论
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="moderate_comments" name="moderate_comments"
                                               <?php echo ($settings['moderate_comments'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="moderate_comments">
                                            <i class="fas fa-shield-alt me-1"></i>评论需要审核
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- XML生成设置 -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-code me-2"></i>XML文件生成
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card border-primary">
                                        <div class="card-body">
                                            <h6 class="card-title text-primary">
                                                <i class="fas fa-sitemap me-2"></i>网站地图 (Sitemap)
                                            </h6>
                                            <p class="card-text small text-muted">
                                                生成XML格式的网站地图，帮助搜索引擎更好地索引您的网站内容。
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge bg-info">
                                                    <i class="fas fa-file-code me-1"></i>sitemap.xml
                                                </span>
                                                <button type="submit" name="generate_xml_sitemap" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-download me-1"></i>生成
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-success">
                                        <div class="card-body">
                                            <h6 class="card-title text-success">
                                                <i class="fas fa-rss me-2"></i>RSS订阅
                                            </h6>
                                            <p class="card-text small text-muted">
                                                生成RSS 2.0格式的订阅源，让用户可以订阅您的博客更新。
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge bg-success">
                                                    <i class="fas fa-file-code me-1"></i>rss.xml
                                                </span>
                                                <button type="submit" name="generate_xml_rss" class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-download me-1"></i>生成
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 文件状态显示 -->
                            <div class="mt-3">
                                <h6 class="text-muted mb-2">
                                    <i class="fas fa-info-circle me-2"></i>文件状态
                                </h6>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-file-code me-2 text-primary"></i>
                                            <span class="me-2">sitemap.xml</span>
                                            <?php if (file_exists('../sitemap.xml')): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>已生成
                                                </span>
                                                <small class="text-muted ms-2">
                                                    <?php echo date('Y-m-d H:i', filemtime('../sitemap.xml')); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>未生成
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-file-code me-2 text-success"></i>
                                            <span class="me-2">rss.xml</span>
                                            <?php if (file_exists('../rss.xml')): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>已生成
                                                </span>
                                                <small class="text-muted ms-2">
                                                    <?php echo date('Y-m-d H:i', filemtime('../rss.xml')); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>未生成
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 使用说明 -->
                            <div class="mt-3 p-3 bg-light rounded">
                                <h6 class="text-muted mb-2">
                                    <i class="fas fa-question-circle me-2"></i>使用说明
                                </h6>
                                <ul class="small text-muted mb-0">
                                    <li><strong>网站地图 (sitemap.xml):</strong> 提交给搜索引擎，帮助爬虫发现和索引您的页面</li>
                                    <li><strong>RSS订阅 (rss.xml):</strong> 用户可以添加到RSS阅读器中订阅您的博客更新</li>
                                    <li><strong>伪静态支持:</strong> 生成的URL采用伪静态格式，如 /post/123.html、/index/category/分类名.html</li>
                                    <li>建议定期重新生成这些文件以保持内容的最新状态</li>
                                    <li>生成的文件将保存在网站根目录下</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>保存设置
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
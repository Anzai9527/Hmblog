<?php
// 开启会话
session_start();

// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查是否已安装
if (!file_exists('includes/installed.lock')) {
    header('Location: /install.php');
    exit;
}

// 引入必要的文件
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// 处理登出成功消息
$message = '';
if (isset($_GET['message']) && $_GET['message'] === 'logout_success') {
    $message = '您已成功退出登录';
}

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConnection();
    
    // 获取分页参数
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = (int)get_setting('posts_per_page', 10);
    $offset = ($page - 1) * $per_page;

    // 获取视图和过滤参数
    $view = isset($_GET['view']) ? $_GET['view'] : 'posts';
    $category_filter = isset($_GET['category']) ? $_GET['category'] : '';
    $tag_filter = isset($_GET['tag']) ? $_GET['tag'] : '';
    $archive_filter = isset($_GET['archive']) ? $_GET['archive'] : '';

    // 只在非标签视图时查询文章
    if ($view !== 'tags') {
        // 构建WHERE条件
        $where_conditions = ["p.status = 'publish'"];
        $params = [];

        if (!empty($category_filter)) {
            $where_conditions[] = "EXISTS (SELECT 1 FROM post_categories pc2 JOIN categories c2 ON pc2.category_id = c2.id WHERE pc2.post_id = p.id AND c2.name = ?)";
            $params[] = $category_filter;
        }

        if (!empty($tag_filter)) {
            $where_conditions[] = "EXISTS (SELECT 1 FROM post_tags pt JOIN tags t ON pt.tag_id = t.id WHERE pt.post_id = p.id AND t.name = ?)";
            $params[] = $tag_filter;
        }

        if (!empty($archive_filter)) {
            $where_conditions[] = "DATE_FORMAT(p.created_at, '%Y-%m') = ?";
            $params[] = $archive_filter;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // 获取文章总数并计算总页数
        $count_sql = "SELECT COUNT(*) as total FROM posts p
                      LEFT JOIN categories c ON p.category_id = c.id
                      WHERE " . $where_clause;
        $stmt = $db->prepare($count_sql);
        $stmt->execute($params);
        $total_posts = $stmt->fetch()['total'];
        $total_pages = ceil($total_posts / $per_page);

        // 获取文章列表
        $sql = "SELECT p.id, p.title, p.content, p.excerpt, p.cover_image, p.status, 
                       p.created_at, p.updated_at, p.views, p.likes, p.author_id, p.category_id,
                       u.username as author_name, c.name as category_name,
                       (SELECT GROUP_CONCAT(t.name) FROM post_tags pt 
                        JOIN tags t ON pt.tag_id = t.id 
                        WHERE pt.post_id = p.id) as tags
                FROM posts p
                LEFT JOIN users u ON p.author_id = u.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE " . $where_clause . "
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge($params, [$per_page, $offset]));
        $posts = $stmt->fetchAll();
        
        // 查询每篇文章的所有分类
        $post_ids = array_column($posts, 'id');
        $post_categories_map = [];
        if (!empty($post_ids)) {
            $in = str_repeat('?,', count($post_ids) - 1) . '?';
            $cat_stmt = $db->prepare("SELECT pc.post_id, c.id, c.name FROM post_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.post_id IN ($in)");
            $cat_stmt->execute($post_ids);
            foreach ($cat_stmt->fetchAll() as $row) {
                $post_categories_map[$row['post_id']][] = $row;
            }
        }
    } else {
        // 标签视图时设置默认值
        $posts = [];
        $total_posts = 0;
        $total_pages = 0;
    }

    // 获取分类列表
    $categories = get_categories_with_count();
    
    // 获取标签云
    $tags = get_all_tags();
    
    // 获取归档列表
    $archives = get_archives();

    // 构建分类树
    function build_category_tree($categories, $parent_id = null) {
        $tree = [];
        foreach ($categories as $cat) {
            if ($cat['parent_id'] == $parent_id) {
                $cat['children'] = build_category_tree($categories, $cat['id']);
                $tree[] = $cat;
            }
        }
        return $tree;
    }
    $category_tree = build_category_tree($categories);

    // 处理图片路径中的双斜杠问题
    function fix_image_url($url) {
        if (empty($url)) return $url;
        // 修复双斜杠问题，但保留协议部分的双斜杠
        $url = preg_replace('#(?<!:)//+#', '/', $url);
        // 确保使用绝对路径
        if (strpos($url, '/') !== 0 && strpos($url, 'http') !== 0) {
            $url = '/' . $url;
        }
        return $url;
    }

    // 获取网站设置
    $site_title = get_setting('site_title', '我的博客');
    $site_description = get_setting('site_description', '');
    $site_keywords = get_setting('site_keywords', '');
    $site_author = get_setting('site_author', '');
    $site_url = get_setting('site_url', '');
    
    // 动态生成页面标题和描述
    $page_title = $site_title;
    $page_description = $site_description;
    $page_keywords = $site_keywords;
    
    if (!empty($category_filter)) {
        $page_title = $category_filter . ' - ' . $site_title;
        $page_description = '查看' . $category_filter . '分类下的所有文章';
        $page_keywords = $category_filter . ',' . $site_keywords;
    } elseif (!empty($tag_filter)) {
        $page_title = $tag_filter . ' - ' . $site_title;
        $page_description = '查看标签为' . $tag_filter . '的所有文章';
        $page_keywords = $tag_filter . ',' . $site_keywords;
    } elseif (!empty($archive_filter)) {
        $page_title = $archive_filter . ' - ' . $site_title;
        $page_description = '查看' . $archive_filter . '的归档文章';
    } elseif ($view === 'tags') {
        $page_title = '标签搜索 - ' . $site_title;
        $page_description = '浏览所有标签，发现更多精彩内容';
    }
    
    // 获取当前页面URL
    $current_url = $site_url . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- 基础SEO标签 -->
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <?php if (!empty($page_keywords)): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>">
    <?php endif; ?>
    <?php if (!empty($site_author)): ?>
    <meta name="author" content="<?php echo htmlspecialchars($site_author); ?>">
    <?php endif; ?>
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    
    <!-- Canonical URL -->
    <?php if (!empty($site_url)): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($current_url); ?>">
    <?php endif; ?>
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <?php if (!empty($site_url)): ?>
    <meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($site_title); ?>">
    <?php endif; ?>
    <?php 
    $banner_image = get_setting('banner_image');
    if (!empty($banner_image)): 
    ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($site_url . fix_image_url($banner_image)); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endif; ?>
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <?php if (!empty($banner_image)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($site_url . fix_image_url($banner_image)); ?>">
    <?php endif; ?>
    
    <!-- 移动端优化 -->
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    
    <!-- 网站图标 -->
    <link rel="icon" type="image/x-icon" href="<?php echo $site_url; ?>/assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="<?php echo $site_url; ?>/assets/images/apple-touch-icon.png">
    
    <!-- 样式表 -->
    <link rel="stylesheet" href="<?php echo $site_url; ?>/assets/css/style.css">
    
    <!-- 结构化数据 -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "<?php echo htmlspecialchars($site_title); ?>",
        "description": "<?php echo htmlspecialchars($site_description); ?>",
        <?php if (!empty($site_url)): ?>
        "url": "<?php echo htmlspecialchars($site_url); ?>",
        <?php endif; ?>
        <?php if (!empty($site_author)): ?>
        "author": {
            "@type": "Person",
            "name": "<?php echo htmlspecialchars($site_author); ?>"
        },
        <?php endif; ?>
        "potentialAction": {
            "@type": "SearchAction",
            "target": "<?php echo htmlspecialchars($site_url); ?>/?tag={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
</head>
<body class="home-page">
    <!-- 移动端菜单按钮 -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <div class="hamburger"></div>
    </button>
    
    <div class="container">
        <!-- 左侧边栏 -->
        <aside class="sidebar" id="sidebar">
            <!-- 用户信息 -->
            <div class="user-profile">
                <?php if (is_logged_in()): ?>
                    <img src="<?php echo get_user_avatar($_SESSION['user_id']); ?>" alt="用户头像" class="avatar">
                    <div class="user-info">
                        <span class="name">欢迎，<?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="count"><a href="/profile.php" style="color: #667eea;">个人中心</a></span>
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
                        <span class="name"><a href="<?php echo $site_url; ?>/logout.php" style="color: #dc3545;">退出登录</a></span>
                        <span class="count"></span>
                    </div>
                <?php else: ?>
                    <img src="<?php echo get_user_avatar(); ?>" alt="游客头像" class="avatar">
                    <div class="user-info">
                        <span class="name">游客用户</span>
                        <span class="count"><a href="<?php echo $site_url; ?>/login.php" style="color: #667eea;">登录</a> | <a href="<?php echo $site_url; ?>/register.php" style="color: #28a745;">注册</a></span>
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
                <?php endif; ?>
            </div>

            <!-- 导航菜单 -->
            <nav class="main-nav">
                <a href="<?php echo $site_url; ?>/" class="nav-item">
                    <i class="icon">🏠</i>
                    <span>首页</span>
                </a>
                <a href="<?php echo $site_url; ?>/index/tags.html" class="nav-item">
                    <i class="icon">🏷️</i>
                    <span>标签搜索</span>
                </a>
                
                <!-- 分类菜单 -->
                <?php 
                // 分类菜单图标数组
                $category_icons = [
                    '📰', '📚', '💡', '🌱', '🎨', '💻', '📖', '🧩', '🛠️', '🎵', '🚀', '🍀', '📷', '🏆', '🌟', '🧠', '📈', '📝', '🌏', '🎬', '🧳', '🍔', '🏠', '⚡', '🎮', '🧸', '📺', '🧃', '🧪', '🧭', '🧹'
                ];
                function render_category_menu($tree, $site_url, $level = 0) {
                    global $category_icons;
                    static $icon_index = 0;
                    foreach ($tree as $cat) {
                        $icon = $category_icons[$icon_index % count($category_icons)];
                        $icon_index++;
                        echo '<a href="' . $site_url . '/index/category/' . urlencode($cat['name']) . '.html" class="nav-item level-' . $level . '" style="padding-left:' . (24 + $level*16) . 'px">';
                        echo '<i class="icon">' . $icon . '</i>';
                        echo '<span>' . htmlspecialchars($cat['name']) . '</span>';
                        echo '<span class="count">(' . $cat['post_count'] . ')</span>';
                        echo '</a>';
                        if (!empty($cat['children'])) render_category_menu($cat['children'], $site_url, $level + 1);
                    }
                }
                render_category_menu($category_tree, $site_url);
                ?>
            </nav>



            <!-- 标签云 -->
            <div class="tags-section">
                <?php foreach ($tags as $tag): ?>
                <a href="<?php echo $site_url; ?>/index/tag/<?php echo urlencode($tag['name']); ?>.html" 
                   class="tag-item"
                   style="background-color: <?php echo generate_tag_color($tag['name']); ?>">
                    <?php echo htmlspecialchars($tag['name']); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- 归档 -->
            <div class="archive-section">
                <?php foreach ($archives as $archive): ?>
                <a href="<?php echo $site_url; ?>/index/archive/<?php echo urlencode($archive['date']); ?>.html" class="archive-item">
                    <span class="date"><?php echo htmlspecialchars($archive['date']); ?></span>
                    <span class="count"><?php echo $archive['count']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="main-content">
            <?php if (!empty($message)): ?>
            <!-- 消息显示 -->
            <div class="alert alert-success" style="margin-bottom: 20px; padding: 15px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px;">
                ✅ <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php 
            // 获取banner图片设置
            $banner_image = get_setting('banner_image');
            $home_banner_title = get_setting('home_banner_title', '');
            if ($view !== 'tags' && !empty($banner_image)): 
            ?>
            <!-- Banner图片 -->
            <div class="banner-container" style="position:relative;">
                <img src="<?php echo htmlspecialchars(fix_image_url($banner_image)); ?>" alt="网站Banner" class="banner-image">
                <?php if (!empty($home_banner_title)): ?>
                <div class="banner-title-overlay" style="
                    position:absolute;left:0;top:0;width:100%;height:100%;
                    display:flex;align-items:center;justify-content:center;pointer-events:none;">
                    <h1 style="
                        color: #fff;
                        font-size: 3.6rem;
                        font-weight: 800;
                        letter-spacing: 3px;
                        line-height: 1.15;
                        text-shadow: 0 8px 36px rgba(0,0,0,0.75), 0 2px 8px #222, 0 0 8px #fff2;
                        background: linear-gradient(100deg, rgba(0,0,0,0.78) 0%, rgba(0,0,0,0.38) 100%), linear-gradient(90deg, #c0482e 0%, #e97d15 100%);
                        background-blend-mode: overlay;
                        padding: 1.2em 3em;
                        border-radius: 2.2em;
                        max-width: 92%;
                        text-align: center;
                        box-shadow: 0 12px 48px 0 rgba(0,0,0,0.28), 0 0 0 4px rgba(255,255,255,0.08) inset;
                        border: 2px solid rgba(255,255,255,0.13);
                        outline: 2px solid #c0482e;
                        outline-offset: 4px;
                        backdrop-filter: blur(8px) saturate(1.1);
                        margin: 0 auto;
                        filter: drop-shadow(0 0 16px #e97d1580);
                    ">
                        <?php echo htmlspecialchars($home_banner_title); ?>
                    </h1>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($view === 'tags'): ?>
                <!-- 标签搜索视图 -->
                <div class="tags-view">
                    <div class="tags-header">
                        <h2 class="section-title">标签搜索</h2>
                        <a href="<?php echo $site_url; ?>/" class="back-to-home">返回首页</a>
                    </div>
                    <div class="tags-grid">
                        <?php foreach ($tags as $tag): ?>
                        <a href="<?php echo $site_url; ?>/index/tag/<?php echo urlencode($tag['name']); ?>.html" 
                           class="tag-card" 
                           style="background-color: <?php echo generate_tag_color($tag['name']); ?>">
                            <span class="tag-name"><?php echo htmlspecialchars($tag['name']); ?></span>
                            <span class="tag-count"><?php echo $tag['post_count']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- 文章列表视图 -->
                <!-- 过滤状态显示 -->
                <?php if (!empty($category_filter) || !empty($tag_filter) || !empty($archive_filter)): ?>
                <div class="filter-status">
                    <?php if (!empty($category_filter)): ?>
                        <span class="filter-info">分类：<?php echo htmlspecialchars($category_filter); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($tag_filter)): ?>
                        <span class="filter-info">标签：<?php echo htmlspecialchars($tag_filter); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($archive_filter)): ?>
                        <span class="filter-info">归档：<?php echo htmlspecialchars($archive_filter); ?></span>
                    <?php endif; ?>
                    <a href="<?php echo $site_url; ?>/" class="clear-filter">清除过滤</a>
                </div>
                <?php endif; ?>

                                        <?php foreach ($posts as $post): ?>
            <article class="post-card" itemscope itemtype="https://schema.org/BlogPosting">
                <!-- 文章结构化数据 -->
                <meta itemprop="headline" content="<?php echo htmlspecialchars($post['title']); ?>">
                <meta itemprop="description" content="<?php echo htmlspecialchars($post['excerpt'] ?? ''); ?>">
                <meta itemprop="datePublished" content="<?php echo date('c', strtotime($post['created_at'])); ?>">
                <meta itemprop="dateModified" content="<?php echo date('c', strtotime($post['updated_at'])); ?>">
                <?php if (!empty($site_author)): ?>
                <meta itemprop="author" content="<?php echo htmlspecialchars($site_author); ?>">
                <?php endif; ?>
                <?php if (!empty($post['cover_image'])): ?>
                <meta itemprop="image" content="<?php echo htmlspecialchars($site_url . fix_image_url($post['cover_image'])); ?>">
                <?php endif; ?>
                
                <div class="post-cover">
                    <?php if (isset($post['cover_image']) && !empty($post['cover_image'])): ?>
                        <a href="<?php echo $site_url; ?>/post/<?php echo $post['id']; ?>.html">
                            <img src="<?php echo htmlspecialchars(fix_image_url($post['cover_image'])); ?>" alt="文章封面">
                        </a>
                    <?php endif; ?>
                    
                    <div class="post-content">
                        <div class="post-meta">
                            <?php if (isset($post['tags']) && !empty($post['tags'])): ?>
                            <div class="post-tags">
                                <span class="tag" style="background-color: #FF6B6B;">
                                    📅 <?php echo date('Y年m月d日', strtotime($post['created_at'])); ?>
                                </span>
                                <span class="tag" style="background-color: #FFA500;">
                                    👀 <?php echo $post['views']; ?> 浏览
                                </span>
                                <?php if (isset($post['likes']) && $post['likes'] > 0): ?>
                                <span class="tag" style="background-color: #FFD700;">
                                    ❤️ <?php echo $post['likes']; ?>
                                </span>
                                <?php endif; ?>
                                <?php 
                                $post_tags = explode(',', $post['tags']);
                                $tag_colors = ['#4ECDC4', '#45B7D1', '#96CEB4', '#9B59B6', '#F39C12'];
                                foreach ($post_tags as $index => $tag): 
                                    $tag = trim($tag);
                                    if (!empty($tag)):
                                        $color = $tag_colors[$index % count($tag_colors)];
                                ?>
                                <span class="tag" style="background-color: <?php echo $color; ?>">
                                    <?php echo htmlspecialchars($tag); ?>
                                </span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($post_categories_map[$post['id']])): ?>
                                <span class="tag" style="background-color: #e3eaff; color: #4a5fc1;">
                                    <?php 
                                    $cat_count = count($post_categories_map[$post['id']]);
                                    foreach ($post_categories_map[$post['id']] as $i => $cat): ?>
                                        <a href="<?php echo $site_url; ?>/index/category/<?php echo urlencode($cat['name']); ?>.html" class="post-category" style="color: #4a5fc1; text-decoration: none;<?php if ($i < $cat_count-1) echo ' margin-right: 8px;'; ?>">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <h2 class="post-title" itemprop="headline">
                            <a href="<?php echo $site_url; ?>/post/<?php echo $post['id']; ?>.html" itemprop="url">
                                <?php echo htmlspecialchars($post['title']); ?>
                            </a>
                        </h2>
                    </div>
                </div>
                
                <?php if (isset($post['excerpt']) && !empty($post['excerpt'])): ?>
                <div class="post-bottom">
                    <div class="post-excerpt" itemprop="description">
                        <?php echo htmlspecialchars($post['excerpt']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>

                <!-- 分页 -->
                <?php if ($view !== 'tags' && $total_pages > 1): ?>
                <div class="pagination">
                    <?php 
                    // 构建分页链接的URL
                    for ($i = 1; $i <= $total_pages; $i++): 
                        if (!empty($category_filter)) {
                            $pagination_url = $site_url . '/index/category/' . urlencode($category_filter) . '.html';
                        } elseif (!empty($tag_filter)) {
                            $pagination_url = $site_url . 'index/tag/' . urlencode($tag_filter) . '.html';
                        } elseif (!empty($archive_filter)) {
                            $pagination_url = $site_url . 'index/archive/' . urlencode($archive_filter) . '.html';
                        } else {
                            $pagination_url = $site_url;
                        }
                        
                        // 添加页码参数
                        if ($i > 1) {
                            $pagination_url .= ($pagination_url === $site_url ? '?' : '&') . 'page=' . $i;
                        }
                    ?>
                    <a href="<?php echo $pagination_url; ?>" 
                       class="page-num <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- 页面底部SEO优化 -->
    <?php if (!empty($site_url)): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "name": "首页",
                "item": "<?php echo htmlspecialchars($site_url); ?>"
            }
            <?php if (!empty($category_filter)): ?>
            ,{
                "@type": "ListItem",
                "position": 2,
                "name": "<?php echo htmlspecialchars($category_filter); ?>",
                "item": "<?php echo htmlspecialchars($site_url . '/index/category/' . urlencode($category_filter) . '.html'); ?>"
            }
            <?php elseif (!empty($tag_filter)): ?>
            ,{
                "@type": "ListItem",
                "position": 2,
                "name": "<?php echo htmlspecialchars($tag_filter); ?>",
                "item": "<?php echo htmlspecialchars($site_url . '/index/tag/' . urlencode($tag_filter) . '.html'); ?>"
            }
            <?php elseif ($view === 'tags'): ?>
            ,{
                "@type": "ListItem",
                "position": 2,
                "name": "标签搜索",
                "item": "<?php echo htmlspecialchars($site_url . '/index/tags.html'); ?>"
            }
            <?php endif; ?>
        ]
    }
    </script>
    <?php endif; ?>
    
    <!-- 页面性能优化 -->
    <script>
        // 预加载重要资源
        const preloadLinks = [
            '<?php echo $site_url; ?>/assets/css/style.css',
            '<?php echo $site_url; ?>/assets/images/default-avatar.png'
        ];
        
        preloadLinks.forEach(href => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = href.endsWith('.css') ? 'style' : 'image';
            link.href = href;
            document.head.appendChild(link);
        });
        
        // 懒加载图片
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
        
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
                if (window.innerWidth > 1024) {
                    sidebar.classList.remove('active');
                    mobileMenuToggle.classList.remove('active');
                }
            });
        }
        
        // 触摸设备优化
        if ('ontouchstart' in window) {
            document.body.classList.add('touch-device');
        }
        
        // 平滑滚动
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
    <script>
    document.addEventListener('contextmenu', function(e) {
      e.preventDefault();
    });
    </script>
</body>
</html>
<?php
} catch (Exception $e) {
    error_log($e->getMessage());
    echo '<div style="margin: 50px auto; max-width: 600px; text-align: center;">';
    echo '<h1>抱歉，出错了！</h1>';
    echo '<p>系统遇到了一些问题：</p>';
    echo '<pre style="text-align: left; background: #f5f5f5; padding: 15px; border-radius: 5px;">';
    echo htmlspecialchars($e->getMessage());
    echo '</pre>';
    echo '<p><a href="' . $site_url . '">返回首页</a></p>';
    echo '</div>';
}
?> 
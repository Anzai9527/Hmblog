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
require_once 'includes/Parsedown.php';

// 确保数据库字段存在
ensure_database_fields();

try {
    // 获取文章ID
    $post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($post_id <= 0) {
        header('HTTP/1.0 404 Not Found');
        include 'templates/frontend/error.php';
        exit;
    }
    
    // 获取数据库连接
    $db = Database::getInstance()->getConnection();
    
    // 获取文章详情
    $stmt = $db->prepare("
        SELECT p.*, u.username as author_name, c.name as category_name,
               (SELECT GROUP_CONCAT(t.name) FROM post_tags pt 
                JOIN tags t ON pt.tag_id = t.id 
                WHERE pt.post_id = p.id) as tags
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.status = 'publish'
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        header('HTTP/1.0 404 Not Found');
        include 'templates/frontend/error.php';
        exit;
    }
    
    // 更新浏览量
    increment_post_views($post_id);
    
    // 获取相关文章（同分类的其他文章）
    $related_posts = [];
    if ($post['category_id']) {
        $stmt = $db->prepare("
            SELECT id, title, cover_image, created_at, views, likes
            FROM posts 
            WHERE category_id = ? AND id != ? AND status = 'publish'
            ORDER BY created_at DESC 
            LIMIT 6
        ");
        $stmt->execute([$post['category_id'], $post_id]);
        $related_posts = $stmt->fetchAll();
    }
    
    // 获取分类列表
    $categories = get_categories_with_count();
    
    // 获取标签云
    $tags = get_all_tags();
    
    // 获取归档列表
    $archives = get_archives();
    
    // 获取评论
    $comments = get_comments_by_post($post_id);
    $total_comments = get_comments_count($post_id);
    
    // 获取文章所有分类
    $stmt = $db->prepare("SELECT c.id, c.name FROM post_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.post_id = ?");
    $stmt->execute([$post_id]);
    $post_categories = $stmt->fetchAll();
    
    // 获取网站设置
    $site_title = get_setting('site_title', '我的博客');
    $site_description = get_setting('site_description', '');
    $site_keywords = get_setting('site_keywords', '');
    $site_author = get_setting('site_author', '');
    $site_url = get_setting('site_url', '');
    $site_url = rtrim($site_url, '/') . '/';
    
    // 生成页面标题和描述
    $page_title = $post['title'] . ' - ' . $site_title;
    $page_description = $post['excerpt'] ?? mb_substr(strip_tags($post['content']), 0, 160) . '...';
    $page_keywords = '';
    if (!empty($post['tags'])) {
        $page_keywords = $post['tags'] . ',' . $site_keywords;
    } else {
        $page_keywords = $site_keywords;
    }
    
    // 获取当前页面URL
    $current_url = $site_url . '/post/' . $post_id . '.html';
    
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
    
    // 修复封面图片路径
    if (!empty($post['cover_image'])) {
        $post['cover_image'] = fix_image_url($post['cover_image']);
    }
    
    // 修复相关文章的封面图片路径
    foreach ($related_posts as &$related_post) {
        if (!empty($related_post['cover_image'])) {
            $related_post['cover_image'] = fix_image_url($related_post['cover_image']);
        }
    }
    
    // 处理文章内容中的图片路径 - 使用相对路径
    // $post['content'] = preg_replace('/src="(?!http)([^"]+)"/', 'src="' . $site_url . '/$1"', $post['content']);

    // 渲染Markdown内容
    $Parsedown = new Parsedown();
    // $Parsedown->setSafeMode(true); // 注释掉SafeMode，允许代码块HTML输出
    $post_content = $Parsedown->text($post['content']);

    // 判断当前用户是否已评论
    $user_commented = false;
    if (is_logged_in()) {
        foreach ($comments as $comment) {
            if ($comment['user_id'] == $_SESSION['user_id']) {
                $user_commented = true;
                break;
            }
            if (!empty($comment['replies'])) {
                foreach ($comment['replies'] as $reply) {
                    if ($reply['user_id'] == $_SESSION['user_id']) {
                        $user_commented = true;
                        break 2;
                    }
                }
            }
        }
    }

    // 替换所有下载链接为安全接口（支持带域名和相对路径）
    $post_content = preg_replace_callback(
        '/<a\s+href="(?:https?:\/\/[^\/]+)?\/uploads\/files\/([^"]+)"[^>]*>(.*?)<\/a>/i',
        function ($matches) use ($post_id) {
            return '<a href="/download.php?file=' . urlencode($matches[1]) . '&post=' . $post_id . '">' . $matches[2] . '</a>';
        },
        $post_content
    );

    // 未登录或未评论时禁用下载
    if (!is_logged_in() || !$user_commented) {
        $post_content = preg_replace_callback(
            '/<a\s+href="\/download.php\?file=([^&]+)&post=\d+"[^>]*>(.*?)<\/a>/i',
            function ($matches) {
                return '<span class="download-disabled" title="请登录并评论后下载">' . $matches[2] . '（需评论后下载）</span>';
            },
            $post_content
        );
    }
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
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo htmlspecialchars($post['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <?php if (!empty($site_url)): ?>
    <meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($site_title); ?>">
    <?php endif; ?>
    <?php if (!empty($post['cover_image'])): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($post['cover_image']); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endif; ?>
    <meta property="article:published_time" content="<?php echo date('c', strtotime($post['created_at'])); ?>">
    <meta property="article:modified_time" content="<?php echo date('c', strtotime($post['updated_at'])); ?>">
    <?php if (!empty($post['tags'])): ?>
    <?php foreach (explode(',', $post['tags']) as $tag): ?>
    <meta property="article:tag" content="<?php echo htmlspecialchars(trim($tag)); ?>">
    <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($post['title']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <?php if (!empty($post['cover_image'])): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($post['cover_image']); ?>">
    <?php endif; ?>
    
    <!-- 移动端优化 -->
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    
    <!-- 网站图标 -->
    <link rel="icon" type="image/x-icon" href="<?php echo $site_url; ?>assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="<?php echo $site_url; ?>assets/images/apple-touch-icon.png">
    
    <!-- 样式表 -->
    <link rel="stylesheet" href="<?php echo $site_url; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $site_url; ?>assets/css/post.css">
    <link rel="stylesheet" href="<?php echo $site_url; ?>assets/css/comments.css">
    
    <!-- 结构化数据 -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BlogPosting",
        "headline": "<?php echo htmlspecialchars($post['title']); ?>",
        "description": "<?php echo htmlspecialchars($page_description); ?>",
        "image": "<?php echo htmlspecialchars($post['cover_image'] ?: '/assets/images/default-cover.jpg'); ?>",
        "datePublished": "<?php echo date('c', strtotime($post['created_at'])); ?>",
        "dateModified": "<?php echo date('c', strtotime($post['updated_at'])); ?>",
        <?php if (!empty($site_author)): ?>
        "author": {
            "@type": "Person",
            "name": "<?php echo htmlspecialchars($site_author); ?>"
        },
        <?php endif; ?>
        "publisher": {
            "@type": "Organization",
            "name": "<?php echo htmlspecialchars($site_title); ?>"
            <?php if (!empty($site_url)): ?>
            ,"url": "<?php echo htmlspecialchars($site_url); ?>"
            <?php endif; ?>
        },
        <?php if (!empty($site_url)): ?>
        "url": "<?php echo htmlspecialchars($current_url); ?>",
        "mainEntityOfPage": {
            "@type": "WebPage",
            "@id": "<?php echo htmlspecialchars($current_url); ?>"
        },
        <?php endif; ?>
        "wordCount": <?php echo str_word_count(strip_tags($post['content'])); ?>,
        "keywords": "<?php echo htmlspecialchars($page_keywords); ?>",
        "articleSection": "<?php echo htmlspecialchars($post['category_name'] ?? ''); ?>",
        "interactionStatistic": [
            {
                "@type": "InteractionCounter",
                "interactionType": "https://schema.org/ReadAction",
                "userInteractionCount": <?php echo $post['views']; ?>
            },
            {
                "@type": "InteractionCounter",
                "interactionType": "https://schema.org/LikeAction",
                "userInteractionCount": <?php echo $post['likes']; ?>
            }
        ]
    }
    </script>
</head>
<body class="post-page">
    <div class="container">
        <!-- 左侧边栏 -->
        <aside class="sidebar">
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
                <?php else: ?>
                    <img src="<?php echo get_user_avatar(); ?>" alt="游客头像" class="avatar">
                    <div class="user-info">
                        <span class="name">游客用户</span>
                        <span class="count"><a href="<?php echo $site_url; ?>login.php" style="color: #667eea;">登录</a> | <a href="<?php echo $site_url; ?>register.php" style="color: #28a745;">注册</a></span>
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
        <main class="main-content">
            <!-- 文章详情 -->
            <article class="post-detail" itemscope itemtype="https://schema.org/BlogPosting">
                <div class="post-detail-header">
                    <?php if (!empty($post['cover_image'])): ?>
                    <img src="<?php echo htmlspecialchars($post['cover_image']); ?>" 
                         alt="<?php echo htmlspecialchars($post['title']); ?>" 
                         itemprop="image">
                    <?php endif; ?>
                    
                    <div class="post-detail-info">
                        <h1 class="post-detail-title" itemprop="headline">
                            <?php echo htmlspecialchars($post['title']); ?>
                        </h1>
                        
                        <div class="post-detail-meta">
                            <span class="tag" style="background-color: #FF6B6B;">
                                📅 <?php echo date('Y年m月d日', strtotime($post['created_at'])); ?>
                            </span>
                            <span class="tag" style="background-color: #FFA500;">
                                👀 <?php echo $post['views']; ?> 浏览
                            </span>
                            <?php if ($post['likes'] > 0): ?>
                            <span class="tag" style="background-color: #FFD700;">
                                ❤️ <?php echo $post['likes']; ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($post_categories)): ?>
                            <span class="tag" style="background-color: #e3eaff; color: #4a5fc1;">
                                <?php foreach ($post_categories as $cat): ?>
                                    <a href="<?php echo $site_url; ?>index/category/<?php echo urlencode($cat['name']); ?>.html" class="post-category" style="color: #4a5fc1; text-decoration: none; margin-right: 6px;">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($post['tags'])): ?>
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
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="post-detail-content" itemprop="articleBody">
                    <?php echo $post_content; ?>
                </div>
                
                <div class="post-actions">
                    <div class="post-stats">
                        <span>发布于 <?php echo date('Y年m月d日', strtotime($post['created_at'])); ?></span>
                        <?php if ($post['updated_at'] != $post['created_at']): ?>
                        <span>更新于 <?php echo date('Y年m月d日', strtotime($post['updated_at'])); ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?php echo $site_url; ?>" class="back-to-home">← 返回首页</a>
                </div>
                
                <!-- 隐藏的结构化数据 -->
                <meta itemprop="datePublished" content="<?php echo date('c', strtotime($post['created_at'])); ?>">
                <meta itemprop="dateModified" content="<?php echo date('c', strtotime($post['updated_at'])); ?>">
                <?php if (!empty($site_author)): ?>
                <meta itemprop="author" content="<?php echo htmlspecialchars($site_author); ?>">
                <?php endif; ?>
                <meta itemprop="description" content="<?php echo htmlspecialchars($page_description); ?>">
                <?php if (!empty($current_url)): ?>
                <meta itemprop="url" content="<?php echo htmlspecialchars($current_url); ?>">
                <?php endif; ?>
            </article>
            
            <!-- 相关文章 -->
            <?php if (!empty($related_posts)): ?>
            <div class="related-posts">
                <div class="related-posts-header">
                    <h3>相关文章</h3>
                </div>
                <div class="related-posts-grid">
                    <?php foreach ($related_posts as $related_post): ?>
                    <a href="<?php echo $site_url; ?>post/<?php echo $related_post['id']; ?>.html" class="related-post-item">
                        <?php if (!empty($related_post['cover_image'])): ?>
                        <img src="<?php echo htmlspecialchars($related_post['cover_image']); ?>" 
                             alt="<?php echo htmlspecialchars($related_post['title']); ?>" 
                             class="related-post-image">
                        <?php else: ?>
                        <div class="related-post-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                        <?php endif; ?>
                        <div class="related-post-info">
                            <div class="related-post-title">
                                <?php echo htmlspecialchars($related_post['title']); ?>
                            </div>
                            <div class="related-post-meta">
                                <span><?php echo date('Y-m-d', strtotime($related_post['created_at'])); ?></span>
                                <span>👀 <?php echo $related_post['views']; ?></span>
                                <?php if ($related_post['likes'] > 0): ?>
                                <span>❤️ <?php echo $related_post['likes']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 评论区域 -->
            <?php if (get_setting('allow_comments', 1)): ?>
            <div class="comments-section">
                <div class="comments-header">
                    <h3>评论 (<?php echo $total_comments; ?>)</h3>
                </div>
                
                <!-- 评论表单 -->
                <?php if (is_logged_in()): ?>
                <div class="comment-form">
                    <form id="commentForm" action="api/comments.php" method="post">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                        <input type="hidden" name="parent_id" id="parent_id" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="comment-form-avatar">
                            <img src="<?php echo get_user_avatar($_SESSION['user_id']); ?>" alt="用户头像">
                        </div>
                        
                        <div class="comment-form-content">
                            <div class="comment-form-header">
                                <span class="comment-form-username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                <span class="comment-form-reply-to" id="replyToText" style="display: none;"></span>
                                <button type="button" class="comment-form-cancel" id="cancelReply" style="display: none;">取消回复</button>
                            </div>
                            
                            <textarea name="content" id="commentContent" placeholder="说说你的想法..." required maxlength="<?php echo get_setting('comment_max_length', 1000); ?>"></textarea>
                            
                            <div class="comment-form-footer">
                                <div class="comment-form-tip">
                                    <span id="charCount">0</span>/<?php echo get_setting('comment_max_length', 1000); ?>
                                </div>
                                <button type="submit" class="comment-form-submit">发表评论</button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="comment-login-tip">
                    <p>请先 <a href="<?php echo $site_url; ?>login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">登录</a> 或 <a href="<?php echo $site_url; ?>register.php">注册</a> 后再发表评论</p>
                </div>
                <?php endif; ?>
                
                <!-- 评论列表 -->
                <div class="comments-list" id="commentsList">
                    <?php if (!empty($comments)): ?>
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment-item" data-comment-id="<?php echo $comment['id']; ?>">
                            <div class="comment-avatar">
                                <img src="<?php echo get_user_avatar($comment['user_id']); ?>" alt="<?php echo htmlspecialchars($comment['username']); ?>">
                            </div>
                            
                            <div class="comment-content">
                                <div class="comment-header">
                                    <span class="comment-author"><?php echo htmlspecialchars($comment['username']); ?></span>
                                    <span class="comment-time"><?php echo friendly_date($comment['created_at']); ?></span>
                                </div>
                                
                                <div class="comment-text">
                                    <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                </div>
                                
                                <div class="comment-actions">
                                    <?php if (is_logged_in()): ?>
                                    <button class="comment-like-btn <?php echo has_user_liked_comment($comment['id'], $_SESSION['user_id']) ? 'liked' : ''; ?>" 
                                            data-comment-id="<?php echo $comment['id']; ?>">
                                        <span class="like-icon">❤️</span>
                                        <span class="like-count"><?php echo $comment['likes_count']; ?></span>
                                    </button>
                                    
                                    <?php if (get_setting('allow_comment_replies', 1)): ?>
                                    <button class="comment-reply-btn" data-comment-id="<?php echo $comment['id']; ?>" 
                                            data-author="<?php echo htmlspecialchars($comment['username']); ?>">
                                        回复
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($comment['user_id'] == $_SESSION['user_id'] || is_admin()): ?>
                                    <button class="comment-delete-btn" data-comment-id="<?php echo $comment['id']; ?>">
                                        删除
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- 回复列表 -->
                                <?php if (!empty($comment['replies'])): ?>
                                <div class="comment-replies">
                                    <?php foreach ($comment['replies'] as $reply): ?>
                                    <div class="comment-reply" data-comment-id="<?php echo $reply['id']; ?>">
                                        <div class="comment-avatar">
                                            <img src="<?php echo get_user_avatar($reply['user_id']); ?>" alt="<?php echo htmlspecialchars($reply['username']); ?>">
                                        </div>
                                        
                                        <div class="comment-content">
                                            <div class="comment-header">
                                                <span class="comment-author"><?php echo htmlspecialchars($reply['username']); ?></span>
                                                <span class="comment-time"><?php echo friendly_date($reply['created_at']); ?></span>
                                            </div>
                                            
                                            <div class="comment-text">
                                                <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                                            </div>
                                            
                                            <div class="comment-actions">
                                                <?php if (is_logged_in()): ?>
                                                <button class="comment-like-btn <?php echo has_user_liked_comment($reply['id'], $_SESSION['user_id']) ? 'liked' : ''; ?>" 
                                                        data-comment-id="<?php echo $reply['id']; ?>">
                                                    <span class="like-icon">❤️</span>
                                                    <span class="like-count"><?php echo $reply['likes_count']; ?></span>
                                                </button>
                                                
                                                <?php if ($reply['user_id'] == $_SESSION['user_id'] || is_admin()): ?>
                                                <button class="comment-delete-btn" data-comment-id="<?php echo $reply['id']; ?>">
                                                    删除
                                                </button>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="comments-empty">
                            <p>暂无评论，快来发表第一条评论吧！</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 加载更多评论 -->
                <?php if ($total_comments > count($comments)): ?>
                <div class="comments-load-more">
                    <button id="loadMoreComments" class="load-more-btn" data-page="2">加载更多评论</button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- 面包屑导航结构化数据 -->
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
            },
            <?php if (!empty($post['category_name'])): ?>
            {
                "@type": "ListItem",
                "position": 2,
                "name": "<?php echo htmlspecialchars($post['category_name']); ?>",
                "item": "<?php echo htmlspecialchars($site_url . '/index/category/' . urlencode($post['category_name']) . '.html'); ?>"
            },
            <?php endif; ?>
            {
                "@type": "ListItem",
                "position": <?php echo !empty($post['category_name']) ? 3 : 2; ?>,
                "name": "<?php echo htmlspecialchars($post['title']); ?>",
                "item": "<?php echo htmlspecialchars($current_url); ?>"
            }
        ]
    }
    </script>
    <?php endif; ?>
    
    <!-- JavaScript 文件 -->
    <script src="<?php echo $site_url; ?>assets/js/post.js"></script>
    <script src="<?php echo $site_url; ?>assets/js/comments.js"></script>
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
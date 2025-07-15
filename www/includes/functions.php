<?php
require_once 'database.php';

/**
 * 安全过滤输出
 */
function escape($string) {
    if (is_array($string)) {
        return array_map('escape', $string);
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * 获取单篇文章
 */
function get_post_by_id($id) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT p.*, u.username as author_name, c.name as category_name,
               (SELECT GROUP_CONCAT(t.name) FROM post_tags pt 
                JOIN tags t ON pt.tag_id = t.id 
                WHERE pt.post_id = p.id) as tags
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * 获取文章列表
 */
function get_posts($status = 'publish', $limit = 10, $offset = 0) {
    $db = Database::getInstance()->getConnection();
    $sql = "
        SELECT p.*, u.username as author_name, c.name as category_name,
               (SELECT GROUP_CONCAT(t.name) FROM post_tags pt 
                JOIN tags t ON pt.tag_id = t.id 
                WHERE pt.post_id = p.id) as tags
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = ?
        ORDER BY p.created_at DESC
        LIMIT ?, ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$status, $offset, $limit]);
    return $stmt->fetchAll();
}

/**
 * 获取文章总数
 */
function get_total_posts($status = null) {
    $db = Database::getInstance()->getConnection();
    $sql = "SELECT COUNT(*) as count FROM posts";
    if ($status) {
        $sql .= " WHERE status = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$status]);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute();
    }
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * 获取评论总数
 */
function get_total_comments() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM comments WHERE status = 'approved'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * 获取总浏览量
 */
function get_total_views() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT SUM(views) as total FROM posts");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?: 0;
}

/**
 * 获取用户总数
 */
function get_total_users() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * 获取所有标签
 */
function get_all_tags() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT t.*, COUNT(pt.post_id) as post_count 
        FROM tags t
        LEFT JOIN post_tags pt ON t.id = pt.tag_id
        GROUP BY t.id
        ORDER BY post_count DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * 获取分类列表（带文章数）
 */
function get_categories_with_count() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT c.*, COUNT(p.id) as post_count
        FROM categories c
        LEFT JOIN post_categories pc ON c.id = pc.category_id
        LEFT JOIN posts p ON pc.post_id = p.id AND p.status = 'publish'
        GROUP BY c.id
        ORDER BY c.name
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * 获取文章归档
 */
function get_archives() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as date,
               COUNT(*) as count
        FROM posts
        WHERE status = 'publish'
        GROUP BY date
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * 获取网站设置
 */
function get_setting($name, $default = '') {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT value FROM settings WHERE name = ?");
    $stmt->execute([$name]);
    $result = $stmt->fetch();
    return $result ? $result['value'] : $default;
}

/**
 * 更新文章浏览量
 */
function increment_post_views($post_id) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE posts SET views = views + 1 WHERE id = ?");
    return $stmt->execute([$post_id]);
}

/**
 * 生成友好的时间格式
 */
function friendly_date($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return '刚刚';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . '分钟前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '小时前';
    } elseif ($diff < 2592000) {
        return floor($diff / 86400) . '天前';
    } else {
        return date('Y-m-d', $timestamp);
    }
}

/**
 * 生成分页HTML
 */
function generate_pagination($current_page, $total_pages, $url_pattern = '?page=%d') {
    if ($total_pages <= 1) return '';
    
    $html = '<ul class="pagination justify-content-center">';
    
    // 上一页
    if ($current_page > 1) {
        $html .= sprintf(
            '<li class="page-item"><a class="page-link" href="' . $url_pattern . '">上一页</a></li>',
            $current_page - 1
        );
    }
    
    // 页码
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= sprintf(
                '<li class="page-item"><a class="page-link" href="' . $url_pattern . '">' . $i . '</a></li>',
                $i
            );
        }
    }
    
    // 下一页
    if ($current_page < $total_pages) {
        $html .= sprintf(
            '<li class="page-item"><a class="page-link" href="' . $url_pattern . '">下一页</a></li>',
            $current_page + 1
        );
    }
    
    $html .= '</ul>';
    return $html;
}

/**
 * 截取文章摘要
 */
function get_excerpt($content, $length = 200) {
    $excerpt = strip_tags($content);
    $excerpt = trim($excerpt);
    if (mb_strlen($excerpt) > $length) {
        $excerpt = mb_substr($excerpt, 0, $length) . '...';
    }
    return $excerpt;
}

/**
 * 检查用户是否已登录
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * 检查当前用户是否是管理员
 */
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * 生成CSRF令牌
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证CSRF令牌
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
} 

/**
 * 获取用户头像
 */
function get_user_avatar($user_id = null) {
    if ($user_id) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            if ($result && !empty($result['avatar'])) {
                // 如果用户有自定义头像，确保使用绝对路径
                $avatar = $result['avatar'];
                if (strpos($avatar, '/') !== 0 && strpos($avatar, 'http') !== 0) {
                    $avatar = '/' . $avatar;
                }
                return $avatar;
            }
        } catch (Exception $e) {
            // 忽略错误，返回默认头像
        }
    }
    // 返回默认头像
    return '/assets/images/default-avatar.png';
}

/**
 * 检查并确保必要的数据库字段存在
 */
function ensure_database_fields() {
    try {
        $db = Database::getInstance()->getConnection();
        
        // 检查users表是否有last_login字段
        try {
            $db->query("SELECT last_login FROM users LIMIT 1");
        } catch (PDOException $e) {
            // 如果字段不存在，添加它
            $db->exec("ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL");
        }
        
        // 检查users表是否有avatar字段
        try {
            $db->query("SELECT avatar FROM users LIMIT 1");
        } catch (PDOException $e) {
            // 如果字段不存在，添加它
            $db->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
        }
    } catch (Exception $e) {
        // 忽略错误
    }
}

/**
 * 获取当前用户名
 */
function get_user_name() {
    // 如果用户已登录，返回用户名
    if (isset($_SESSION['user_id'])) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        if ($result) {
            return $result['username'];
        }
    }
    // 返回默认名称
    return '访客';
} 

/**
 * 获取总点赞数
 */
function get_total_likes() {
    // 暂时返回固定值，等待数据库字段添加后再修改
    return 0;
}

/**
 * 格式化日期
 */
function format_date($date) {
    return date('Y年m月d日', strtotime($date));
}

/**
 * 生成标签颜色
 */
function generate_tag_color($tag_name) {
    // 预定义的颜色数组 - 更多彩色选择
    $colors = [
        '#FF6B6B', // 红色
        '#4ECDC4', // 青色
        '#45B7D1', // 蓝色
        '#96CEB4', // 绿色
        '#FFEAA7', // 黄色
        '#DDA0DD', // 紫色
        '#98D8C8', // 薄荷绿
        '#F7DC6F', // 浅黄
        '#BB8FCE', // 薰衣草
        '#85C1E9', // 天蓝
        '#F8C471', // 橙色
        '#82E0AA', // 浅绿
        '#F1948A', // 珊瑚色
        '#85C1E9', // 浅蓝
        '#D7BDE2', // 浅紫
        '#A3E4D7', // 浅青
        '#F9E79F', // 浅金
        '#FADBD8', // 浅粉
        '#AED6F1', // 浅蓝
        '#ABEBC6', // 浅绿
        '#F8D7DA', // 浅红
        '#FCF3CF', // 浅黄
        '#EBDEF0', // 浅紫
        '#D5F4E6', // 浅薄荷
        '#FDF2E9', // 浅橙
        '#EAF2F8', // 浅天蓝
        '#E8F8F5', // 浅绿松石
        '#FEF9E7', // 浅金黄
        '#FADBD8', // 浅玫瑰
        '#E3F2FD'  // 浅蓝
    ];
    
    // 使用标签名的哈希值来选择颜色
    $hash = crc32($tag_name);
    $index = abs($hash % count($colors));
    
    return $colors[$index];
}

/**
 * 获取随机颜色
 */
function get_random_color() {
    $colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEEAD',
        '#D4A5A5', '#9B9B9B', '#A8E6CF', '#FFB6B9', '#957DAD',
        '#E7D3E4', '#F9D5E5', '#FFCB77', '#95E1D3', '#A8D8EA',
        '#AA96DA', '#C5FAD5', '#FFFFD2', '#F0A500', '#95B8D1'
    ];
    return $colors[array_rand($colors)];
}

/**
 * 安全过滤评论内容
 */
function sanitize_comment_content($content) {
    // 移除危险的HTML标签和脚本
    $content = strip_tags($content, '<p><br><b><i><u><strong><em><blockquote><code><pre><ul><ol><li><a>');
    
    // 过滤危险的属性
    $content = preg_replace('/<([^>]+?)(?:on\w+|javascript:|data:|vbscript:|expression\(|style\s*=)[^>]*>/i', '<$1>', $content);
    
    // 确保链接安全
    $content = preg_replace('/<a[^>]+href\s*=\s*["\']?(?!https?:\/\/|\/)[^"\'>\s]+["\']?[^>]*>/i', '', $content);
    
    // 转义HTML实体
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    
    return trim($content);
}

/**
 * 获取文章的评论列表
 */
function get_comments_by_post($post_id, $status = 'approved', $limit = 20, $offset = 0) {
    $db = Database::getInstance()->getConnection();
    $sql = "
        SELECT c.*, u.username, u.avatar,
               (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) as likes_count,
               (SELECT COUNT(*) FROM comments cc WHERE cc.parent_id = c.id AND cc.status = 'approved') as reply_count
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ? AND c.status = ? AND c.parent_id IS NULL
        ORDER BY c.created_at DESC
        LIMIT ?, ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$post_id, $status, $offset, $limit]);
    $comments = $stmt->fetchAll();
    
    // 获取每个评论的回复
    foreach ($comments as &$comment) {
        $comment['replies'] = get_comment_replies($comment['id']);
    }
    
    return $comments;
}

/**
 * 获取评论的回复列表
 */
function get_comment_replies($parent_id, $status = 'approved') {
    $db = Database::getInstance()->getConnection();
    $sql = "
        SELECT c.*, u.username, u.avatar,
               (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) as likes_count
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.parent_id = ? AND c.status = ?
        ORDER BY c.created_at ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$parent_id, $status]);
    return $stmt->fetchAll();
}

/**
 * 获取评论总数
 */
function get_comments_count($post_id, $status = 'approved') {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ? AND status = ?");
    $stmt->execute([$post_id, $status]);
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * 添加评论
 */
function add_comment($post_id, $user_id, $content, $parent_id = null) {
    $db = Database::getInstance()->getConnection();
    
    // 安全过滤内容
    $content = sanitize_comment_content($content);
    
    // 检查内容长度
    $max_length = get_setting('comment_max_length', 1000);
    if (mb_strlen($content) > $max_length) {
        throw new Exception("评论内容不能超过{$max_length}个字符");
    }
    
    // 检查评论是否允许
    if (!get_setting('allow_comments', 1)) {
        throw new Exception("评论功能已关闭");
    }
    
    // 检查是否允许回复
    if ($parent_id && !get_setting('allow_comment_replies', 1)) {
        throw new Exception("回复功能已关闭");
    }
    
    // 获取用户IP和User-Agent
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // 设置评论状态
    $status = get_setting('moderate_comments', 1) ? 'pending' : 'approved';
    
    $stmt = $db->prepare("
        INSERT INTO comments (post_id, user_id, parent_id, content, status, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$post_id, $user_id, $parent_id, $content, $status, $ip_address, $user_agent]);
}

/**
 * 检查用户是否已点赞评论
 */
function has_user_liked_comment($comment_id, $user_id) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$comment_id, $user_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * 切换评论点赞状态
 */
function toggle_comment_like($comment_id, $user_id) {
    $db = Database::getInstance()->getConnection();
    
    if (has_user_liked_comment($comment_id, $user_id)) {
        // 取消点赞
        $stmt = $db->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$comment_id, $user_id]);
        return false;
    } else {
        // 添加点赞
        $stmt = $db->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
        $stmt->execute([$comment_id, $user_id]);
        return true;
    }
}

/**
 * 获取评论统计信息
 */
function get_comment_stats() {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END) as spam,
        SUM(CASE WHEN status = 'deleted' THEN 1 ELSE 0 END) as deleted
        FROM comments");
    
    return $stmt->fetch();
}

/**
 * 生成XML网站地图
 */
function generate_xml_sitemap() {
    $db = Database::getInstance()->getConnection();
    $site_url = get_setting('site_url', 'http://localhost');
    
    // 创建XML文档
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    
    // 创建根元素
    $urlset = $xml->createElement('urlset');
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $xml->appendChild($urlset);
    
    // 添加首页
    $url = $xml->createElement('url');
    $loc = $xml->createElement('loc', $site_url);
    $lastmod = $xml->createElement('lastmod', date('Y-m-d'));
    $changefreq = $xml->createElement('changefreq', 'daily');
    $priority = $xml->createElement('priority', '1.0');
    
    $url->appendChild($loc);
    $url->appendChild($lastmod);
    $url->appendChild($changefreq);
    $url->appendChild($priority);
    $urlset->appendChild($url);
    
    // 添加文章页面
    $stmt = $db->prepare("
        SELECT id, title, updated_at, created_at 
        FROM posts 
        WHERE status = 'publish' 
        ORDER BY updated_at DESC
    ");
    $stmt->execute();
    $posts = $stmt->fetchAll();
    
    foreach ($posts as $post) {
        $url = $xml->createElement('url');
        $loc = $xml->createElement('loc', $site_url . '/post/' . $post['id'] . '.html');
        $lastmod = $xml->createElement('lastmod', date('Y-m-d', strtotime($post['updated_at'] ?: $post['created_at'])));
        $changefreq = $xml->createElement('changefreq', 'weekly');
        $priority = $xml->createElement('priority', '0.8');
        
        $url->appendChild($loc);
        $url->appendChild($lastmod);
        $url->appendChild($changefreq);
        $url->appendChild($priority);
        $urlset->appendChild($url);
    }
    
    // 添加分类页面
    $stmt = $db->prepare("SELECT id, name FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    foreach ($categories as $category) {
        $url = $xml->createElement('url');
        $loc = $xml->createElement('loc', $site_url . '/index/category/' . urlencode($category['name']) . '.html');
        $lastmod = $xml->createElement('lastmod', date('Y-m-d'));
        $changefreq = $xml->createElement('changefreq', 'weekly');
        $priority = $xml->createElement('priority', '0.6');
        
        $url->appendChild($loc);
        $url->appendChild($lastmod);
        $url->appendChild($changefreq);
        $url->appendChild($priority);
        $urlset->appendChild($url);
    }
    
    // 添加标签页面
    $stmt = $db->prepare("SELECT id, name FROM tags ORDER BY name");
    $stmt->execute();
    $tags = $stmt->fetchAll();
    
    foreach ($tags as $tag) {
        $url = $xml->createElement('url');
        $loc = $xml->createElement('loc', $site_url . '/index/tag/' . urlencode($tag['name']) . '.html');
        $lastmod = $xml->createElement('lastmod', date('Y-m-d'));
        $changefreq = $xml->createElement('changefreq', 'weekly');
        $priority = $xml->createElement('priority', '0.5');
        
        $url->appendChild($loc);
        $url->appendChild($lastmod);
        $url->appendChild($changefreq);
        $url->appendChild($priority);
        $urlset->appendChild($url);
    }
    
    // 添加标签列表页
    $url = $xml->createElement('url');
    $loc = $xml->createElement('loc', $site_url . '/index/tags.html');
    $lastmod = $xml->createElement('lastmod', date('Y-m-d'));
    $changefreq = $xml->createElement('changefreq', 'monthly');
    $priority = $xml->createElement('priority', '0.4');
    
    $url->appendChild($loc);
    $url->appendChild($lastmod);
    $url->appendChild($changefreq);
    $url->appendChild($priority);
    $urlset->appendChild($url);
    
    // 添加归档页面
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%Y年%m月') as archive_date
        FROM posts
        WHERE status = 'publish'
        GROUP BY archive_date
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $archives = $stmt->fetchAll();
    
    foreach ($archives as $archive) {
        $url = $xml->createElement('url');
        $loc = $xml->createElement('loc', $site_url . '/index/archive/' . urlencode($archive['archive_date']) . '.html');
        $lastmod = $xml->createElement('lastmod', date('Y-m-d'));
        $changefreq = $xml->createElement('changefreq', 'monthly');
        $priority = $xml->createElement('priority', '0.3');
        
        $url->appendChild($loc);
        $url->appendChild($lastmod);
        $url->appendChild($changefreq);
        $url->appendChild($priority);
        $urlset->appendChild($url);
    }
    
    // 保存文件
    $xml_content = $xml->saveXML();
    $file_path = '../sitemap.xml';
    file_put_contents($file_path, $xml_content);
    
    return true;
}

/**
 * 生成XML RSS订阅
 */
function generate_xml_rss() {
    $db = Database::getInstance()->getConnection();
    $site_url = get_setting('site_url', 'http://localhost');
    $site_title = get_setting('site_title', '我的博客');
    $site_description = get_setting('site_description', '');
    $site_author = get_setting('site_author', '');
    
    // 创建XML文档
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    
    // 创建根元素
    $rss = $xml->createElement('rss');
    $rss->setAttribute('version', '2.0');
    $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
    $xml->appendChild($rss);
    
    // 创建channel元素
    $channel = $xml->createElement('channel');
    $rss->appendChild($channel);
    
    // 添加频道信息
    $title = $xml->createElement('title', $site_title);
    $link = $xml->createElement('link', $site_url);
    $description = $xml->createElement('description', $site_description);
    $language = $xml->createElement('language', 'zh-CN');
    $lastBuildDate = $xml->createElement('lastBuildDate', date(DATE_RSS));
    $generator = $xml->createElement('generator', 'HM Blog System');
    
    $channel->appendChild($title);
    $channel->appendChild($link);
    $channel->appendChild($description);
    $channel->appendChild($language);
    $channel->appendChild($lastBuildDate);
    $channel->appendChild($generator);
    
    // 添加atom链接
    $atom_link = $xml->createElement('atom:link');
    $atom_link->setAttribute('href', $site_url . '/rss.xml');
    $atom_link->setAttribute('rel', 'self');
    $atom_link->setAttribute('type', 'application/rss+xml');
    $channel->appendChild($atom_link);
    
    // 添加文章
    $stmt = $db->prepare("
        SELECT p.*, u.username as author_name, c.name as category_name
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'publish'
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $posts = $stmt->fetchAll();
    
    foreach ($posts as $post) {
        $item = $xml->createElement('item');
        
        $item_title = $xml->createElement('title', $post['title']);
        $item_link = $xml->createElement('link', $site_url . '/post/' . $post['id'] . '.html');
        $item_guid = $xml->createElement('guid', $site_url . '/post/' . $post['id'] . '.html');
        $item_pubDate = $xml->createElement('pubDate', date(DATE_RSS, strtotime($post['created_at'])));
        $item_description = $xml->createElement('description', get_excerpt($post['content'], 300));
        
        $item->appendChild($item_title);
        $item->appendChild($item_link);
        $item->appendChild($item_guid);
        $item->appendChild($item_pubDate);
        $item->appendChild($item_description);
        
        // 添加作者
        if ($post['author_name']) {
            $item_author = $xml->createElement('author', $post['author_name']);
            $item->appendChild($item_author);
        }
        
        // 添加分类
        if ($post['category_name']) {
            $item_category = $xml->createElement('category', $post['category_name']);
            $item->appendChild($item_category);
        }
        
        $channel->appendChild($item);
    }
    
    // 保存文件
    $xml_content = $xml->saveXML();
    $file_path = '../rss.xml';
    file_put_contents($file_path, $xml_content);
    
    return true;
}
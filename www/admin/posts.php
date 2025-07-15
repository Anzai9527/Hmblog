<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once 'includes/auth.php';

// 文章管理需要编辑以上权限
require_admin_permission('editor');

// 获取数据库连接
$db = Database::getInstance()->getConnection();

// 处理图片上传
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/images/';
    
    // 确保上传目录存在
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // 获取文件信息
    $file_info = pathinfo($_FILES['image']['name']);
    $extension = strtolower($file_info['extension']);
    
    // 检查文件类型
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowed_types)) {
        die(json_encode(['error' => '只允许上传 JPG, JPEG, PNG, GIF 或 WebP 格式的图片']));
    }
    
    // 检查文件大小（限制为5MB）
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        die(json_encode(['error' => '图片大小不能超过5MB']));
    }
    
    // 生成唯一文件名
    $filename = uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // 移动上传的文件
    if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
        // 返回完整的URL路径，确保在编辑器预览中能正确显示
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $base_path = dirname(dirname($_SERVER['REQUEST_URI'])); // 获取项目根路径
        // 修复双斜杠问题
        $base_path = rtrim($base_path, '/');
        $image_url = $protocol . $host . $base_path . '/uploads/images/' . $filename;
        die(json_encode(['success' => true, 'url' => $image_url]));
    } else {
        die(json_encode(['error' => '图片上传失败']));
    }
}

// 处理附件上传
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/files/';
    
    // 确保上传目录存在
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // 获取文件信息
    $file_info = pathinfo($_FILES['file']['name']);
    $extension = strtolower($file_info['extension']);
    $original_name = $file_info['filename'];
    
    // 检查文件类型（可以根据需要添加更多允许的文件类型）
    $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', '7z', 'mp3', 'mp4', 'avi'];
    if (!in_array($extension, $allowed_types)) {
        die(json_encode(['error' => '不支持的文件类型。支持的格式：PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, 7Z, MP3, MP4, AVI']));
    }
    
    // 检查文件大小（限制为10MB）
    if ($_FILES['file']['size'] > 100 * 1024 * 1024) {
        die(json_encode(['error' => '文件大小不能超过100MB']));
    }
    
    // 生成唯一文件名，保留原始文件名
    $filename = $original_name . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // 移动上传的文件
    if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
        // 返回文件信息
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $base_path = dirname(dirname($_SERVER['REQUEST_URI'])); // 获取项目根路径
        // 修复双斜杠问题
        $base_path = rtrim($base_path, '/');
        $file_url = $protocol . $host . $base_path . '/uploads/files/' . $filename;
        die(json_encode([
            'success' => true, 
            'url' => $file_url,
            'filename' => $original_name . '.' . $extension
        ]));
    } else {
        die(json_encode(['error' => '文件上传失败']));
    }
}

// 处理文章删除
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        // 开始事务
        $db->beginTransaction();
        
        // 获取文章相关的评论ID，用于删除评论的点赞记录
        $stmt = $db->prepare("SELECT id FROM comments WHERE post_id = ?");
        $stmt->execute([$id]);
        $comment_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 删除评论的点赞记录（如果有评论点赞表）
        if (!empty($comment_ids)) {
            $comment_placeholders = str_repeat('?,', count($comment_ids) - 1) . '?';
            try {
                $stmt = $db->prepare("DELETE FROM comment_likes WHERE comment_id IN ($comment_placeholders)");
                $stmt->execute($comment_ids);
            } catch (PDOException $e) {
                // 如果comment_likes表不存在，忽略错误
            }
        }
        
        // 删除文章相关的评论
        $stmt = $db->prepare("DELETE FROM comments WHERE post_id = ?");
        $stmt->execute([$id]);
        $deleted_comments = $stmt->rowCount();
        
        // 删除文章相关的标签关联
        $stmt = $db->prepare("DELETE FROM post_tags WHERE post_id = ?");
        $stmt->execute([$id]);
        
        // 删除文章
        $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        
        // 提交事务
        $db->commit();
        
        $message = '文章已删除';
        if ($deleted_comments > 0) {
            $message .= "，同时删除了 {$deleted_comments} 条相关评论";
        }
        
        header('Location: posts.php?message=' . urlencode($message));
        exit;
    } catch (PDOException $e) {
        // 回滚事务
        $db->rollback();
        header('Location: posts.php?error=删除失败：' . $e->getMessage());
        exit;
    }
}

// 处理批量操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action'])) {
    $batch_action = $_POST['batch_action'];
    $selected_posts = $_POST['selected_posts'] ?? [];
    
    if (empty($selected_posts)) {
        header('Location: posts.php?error=请选择要操作的文章');
        exit;
    }
    
    // 验证所选文章ID
    $selected_posts = array_filter(array_map('intval', $selected_posts));
    if (empty($selected_posts)) {
        header('Location: posts.php?error=无效的文章选择');
        exit;
    }
    
    $placeholders = str_repeat('?,', count($selected_posts) - 1) . '?';
    
    try {
        switch ($batch_action) {
            case 'delete':
                // 批量删除
                $db->beginTransaction();
                
                try {
                    // 获取文章相关的评论ID，用于删除评论的点赞记录
                    $stmt = $db->prepare("SELECT id FROM comments WHERE post_id IN ($placeholders)");
                    $stmt->execute($selected_posts);
                    $comment_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // 删除评论的点赞记录（如果有评论点赞表）
                    if (!empty($comment_ids)) {
                        $comment_placeholders = str_repeat('?,', count($comment_ids) - 1) . '?';
                        try {
                            $stmt = $db->prepare("DELETE FROM comment_likes WHERE comment_id IN ($comment_placeholders)");
                            $stmt->execute($comment_ids);
                        } catch (PDOException $e) {
                            // 如果comment_likes表不存在，忽略错误
                        }
                    }
                    
                    // 删除文章相关的评论
                    $stmt = $db->prepare("DELETE FROM comments WHERE post_id IN ($placeholders)");
                    $stmt->execute($selected_posts);
                    $deleted_comments = $stmt->rowCount();
                    
                    // 删除文章相关的标签关联
                    $stmt = $db->prepare("DELETE FROM post_tags WHERE post_id IN ($placeholders)");
                    $stmt->execute($selected_posts);
                    
                    // 删除文章
                    $stmt = $db->prepare("DELETE FROM posts WHERE id IN ($placeholders)");
                    $stmt->execute($selected_posts);
                    $deleted_posts = $stmt->rowCount();
                    
                    // 提交事务
                    $db->commit();
                    
                    $message = "成功删除了 {$deleted_posts} 篇文章";
                    if ($deleted_comments > 0) {
                        $message .= "，同时删除了 {$deleted_comments} 条相关评论";
                    }
                    
                    header("Location: posts.php?message=" . urlencode($message));
                } catch (PDOException $e) {
                    // 回滚事务
                    $db->rollback();
                    throw $e;
                }
                break;
                
            case 'offline':
                // 批量下架（改为草稿状态）
                $stmt = $db->prepare("UPDATE posts SET status = 'draft' WHERE id IN ($placeholders)");
                $stmt->execute($selected_posts);
                $affected_rows = $stmt->rowCount();
                header("Location: posts.php?message=成功下架了 {$affected_rows} 篇文章");
                break;
                
            case 'publish':
                // 批量发布
                $stmt = $db->prepare("UPDATE posts SET status = 'publish' WHERE id IN ($placeholders)");
                $stmt->execute($selected_posts);
                $affected_rows = $stmt->rowCount();
                header("Location: posts.php?message=成功发布了 {$affected_rows} 篇文章");
                break;
                
            default:
                header('Location: posts.php?error=无效的批量操作');
                break;
        }
    } catch (PDOException $e) {
        header('Location: posts.php?error=批量操作失败：' . $e->getMessage());
    }
    exit;
}

// 生成友好的URL标识符（slug）
function generate_slug($title) {
    // 生成默认slug的函数
    $generateDefault = function() {
        return 'post-' . time() . '-' . uniqid();
    };
    
    // 多重检查确保输入不为空
    if (empty($title) || trim($title) === '' || $title === null) {
        $result = $generateDefault();
        error_log("Debug: generate_slug received empty title, returning: " . $result);
        return $result;
    }
    
    // 转换为小写并去除首尾空格
    $slug = strtolower(trim($title));
    
    // 如果转换后为空，立即返回默认值
    if (empty($slug)) {
        $result = $generateDefault();
        error_log("Debug: generate_slug title became empty after trim, returning: " . $result);
        return $result;
    }
    
    // 处理中文字符 - 保留中文、英文、数字
    $slug = preg_replace('/[^\x{4e00}-\x{9fa5}a-z0-9\s]/u', '', $slug);
    
    // 替换空格为短横线
    $slug = preg_replace('/\s+/', '-', $slug);
    
    // 删除多余的短横线
    $slug = preg_replace('/-+/', '-', $slug);
    
    // 去除首尾的短横线
    $slug = trim($slug, '-');
    
    // 确保slug不为空且有有效长度
    if (empty($slug) || strlen($slug) < 1 || $slug === null) {
        $result = $generateDefault();
        error_log("Debug: generate_slug became empty after processing, returning: " . $result);
        return $result;
    }
    
    // 限制长度
    if (strlen($slug) > 200) {
        $slug = substr($slug, 0, 200);
        $slug = trim($slug, '-');
        
        // 如果截断后为空，使用时间戳
        if (empty($slug)) {
            $result = $generateDefault();
            error_log("Debug: generate_slug became empty after truncation, returning: " . $result);
            return $result;
        }
    }
    
    // 最终检查，确保返回的slug不为空
    if (empty($slug) || trim($slug) === '' || $slug === null) {
        $result = $generateDefault();
        error_log("Debug: generate_slug failed final check, returning: " . $result);
        return $result;
    }
    
    error_log("Debug: generate_slug successfully generated: " . $slug);
    return $slug;
}

// 检查slug是否已存在，如果存在则添加数字
function get_unique_slug($db, $slug, $id = 0) {
    $generateDefault = function() use ($id) {
        return 'post-' . time() . '-' . uniqid() . '-' . $id;
    };
    
    // 多重检查确保slug不为空且有效
    if (empty($slug) || trim($slug) === '' || $slug === null) {
        $result = $generateDefault();
        error_log("Debug: get_unique_slug received empty slug, returning: " . $result);
        return $result;
    }
    
    // 去除首尾空格
    $slug = trim($slug);
    
    // 再次检查
    if ($slug === '' || $slug === null) {
        $result = $generateDefault();
        error_log("Debug: get_unique_slug slug became empty after trim, returning: " . $result);
        return $result;
    }
    
    $original_slug = $slug;
    $counter = 1;
    
    do {
        try {
            $sql = "SELECT COUNT(*) FROM posts WHERE slug = ? AND id != ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$slug, $id]);
            $exists = $stmt->fetchColumn();
            
            if ($exists) {
                $slug = $original_slug . '-' . $counter;
                $counter++;
                error_log("Debug: get_unique_slug found duplicate, trying: " . $slug);
            }
        } catch (PDOException $e) {
            // 如果查询失败，返回带时间戳的slug
            $result = $generateDefault();
            error_log("Debug: get_unique_slug database error, returning: " . $result);
            return $result;
        }
    } while ($exists);
    
    // 最终检查，确保返回的slug不为空
    if (empty($slug) || trim($slug) === '' || $slug === null) {
        $result = $generateDefault();
        error_log("Debug: get_unique_slug failed final check, returning: " . $result);
        return $result;
    }
    
    error_log("Debug: get_unique_slug successfully generated: " . $slug);
    return $slug;
}

// 处理文章保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $excerpt = trim($_POST['excerpt'] ?? '');
    $cover_image = trim($_POST['cover_image'] ?? '');
    $category_ids = $_POST['category_id'] ?? [];
    if (!is_array($category_ids)) $category_ids = [$category_ids];
    $status = $_POST['status'];
    $tags = trim($_POST['tags'] ?? '');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // 处理空值 - 将空字符串转换为NULL
    $excerpt = $excerpt === '' ? null : $excerpt;
    $cover_image = $cover_image === '' ? null : $cover_image;
    // 保存文章主表时 category_id 字段可设为 null 或第一个分类
    $category_id = !empty($category_ids) ? $category_ids[0] : null;
    
    // 确保status有有效值
    if (empty($status) || !in_array($status, ['publish', 'draft', 'pending', 'private'])) {
        $status = 'draft';
    }
    
    if (empty($title)) {
        $error = '标题不能为空';
    } elseif (empty($content)) {
        $error = '内容不能为空';
    } else {
        try {
            // 生成slug - 使用简单可靠的方法
            $slug = generate_slug($title);
            $slug = get_unique_slug($db, $slug, $id);
            
            // 绝对确保slug不为空 - 这是最后的防线
            if (empty($slug) || trim($slug) === '' || $slug === null || strlen(trim($slug)) === 0) {
                $slug = 'post-' . time() . '-' . uniqid();
            }
            
            // 调试信息
            error_log("Debug: Final slug value: " . $slug . " (length: " . strlen($slug) . ")");
            
            // 开始事务
            $db->beginTransaction();
            
            if ($id > 0) {
                // 最后检查：确保slug不为空
                if (empty($slug) || trim($slug) === '') {
                    $slug = 'emergency-update-' . time() . '-' . $id;
                    error_log("Emergency: Setting emergency slug for UPDATE: " . $slug);
                }
                
                // 更新文章
                $update_fields = ['title = ?', 'content = ?', 'slug = ?', 'excerpt = ?', 'cover_image = ?', 'category_id = ?', 'status = ?'];
                $update_values = [$title, $content, $slug, $excerpt, $cover_image, $category_id, $status, $id];
                
                $sql = "UPDATE posts SET " . implode(', ', $update_fields) . " WHERE id = ?";
                error_log("Debug: UPDATE SQL: " . $sql);
                error_log("Debug: UPDATE values: " . print_r($update_values, true));
                $stmt = $db->prepare($sql);
                $stmt->execute($update_values);
                $post_id = $id;
                $message = '文章更新成功';
            } else {
                // 最后检查：确保slug不为空
                if (empty($slug) || trim($slug) === '') {
                    $slug = 'emergency-insert-' . time() . '-' . uniqid();
                    error_log("Emergency: Setting emergency slug for INSERT: " . $slug);
                }
                
                // 创建新文章
                $insert_fields = ['title', 'content', 'slug', 'excerpt', 'cover_image', 'author_id', 'category_id', 'status'];
                $insert_values = [$title, $content, $slug, $excerpt, $cover_image, $_SESSION['user_id'], $category_id, $status];
                $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?'];
                
                $sql = "INSERT INTO posts (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                error_log("Debug: INSERT SQL: " . $sql);
                error_log("Debug: INSERT values: " . print_r($insert_values, true));
                $stmt = $db->prepare($sql);
                $stmt->execute($insert_values);
                $post_id = $db->lastInsertId();
                $message = '文章发布成功';
            }
            
            // 处理标签
            if (!empty($tags)) {
                // 先删除文章的现有标签关联
                $stmt = $db->prepare("DELETE FROM post_tags WHERE post_id = ?");
                $stmt->execute([$post_id]);
                
                // 解析标签（支持逗号和空格分隔）
                $tag_names = preg_split('/[,\s]+/', $tags, -1, PREG_SPLIT_NO_EMPTY);
                $tag_names = array_unique(array_map('trim', $tag_names));
                
                foreach ($tag_names as $tag_name) {
                    if (empty($tag_name)) continue;
                    
                    // 检查标签是否存在，不存在则创建
                    $stmt = $db->prepare("SELECT id FROM tags WHERE name = ?");
                    $stmt->execute([$tag_name]);
                    $tag = $stmt->fetch();
                    
                    if ($tag) {
                        $tag_id = $tag['id'];
                    } else {
                        // 创建新标签，需要生成slug
                        $tag_slug = generate_slug($tag_name);
                        
                        // 确保标签slug的唯一性
                        $original_tag_slug = $tag_slug;
                        $counter = 1;
                        do {
                            $check_stmt = $db->prepare("SELECT COUNT(*) FROM tags WHERE slug = ?");
                            $check_stmt->execute([$tag_slug]);
                            $slug_exists = $check_stmt->fetchColumn();
                            
                            if ($slug_exists) {
                                $tag_slug = $original_tag_slug . '-' . $counter;
                                $counter++;
                            }
                        } while ($slug_exists);
                        
                        // 最后确保标签slug不为空
                        if (empty($tag_slug) || trim($tag_slug) === '') {
                            $tag_slug = 'tag-' . time() . '-' . uniqid();
                        }
                        
                        $stmt = $db->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
                        $stmt->execute([$tag_name, $tag_slug]);
                        $tag_id = $db->lastInsertId();
                        
                        error_log("Debug: Created new tag - name: " . $tag_name . ", slug: " . $tag_slug);
                    }
                    
                    // 创建文章-标签关联
                    $stmt = $db->prepare("INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)");
                    $stmt->execute([$post_id, $tag_id]);
                }
            } else {
                // 如果没有标签，删除现有关联
                $stmt = $db->prepare("DELETE FROM post_tags WHERE post_id = ?");
                $stmt->execute([$post_id]);
            }

            // 保存后处理多分类
            if ($id > 0) {
                // 删除旧的分类关联
                $stmt = $db->prepare("DELETE FROM post_categories WHERE post_id = ?");
                $stmt->execute([$id]);
                // 插入新分类
                foreach ($category_ids as $cat_id) {
                    $stmt = $db->prepare("INSERT INTO post_categories (post_id, category_id) VALUES (?, ?)");
                    $stmt->execute([$id, $cat_id]);
                }
            } else {
                // 新文章插入后处理多分类
                foreach ($category_ids as $cat_id) {
                    $stmt = $db->prepare("INSERT INTO post_categories (post_id, category_id) VALUES (?, ?)");
                    $stmt->execute([$post_id, $cat_id]);
                }
            }
            
            // 提交事务
            $db->commit();
            
            header('Location: posts.php?message=' . urlencode($message));
            exit;
        } catch (PDOException $e) {
            // 回滚事务
            $db->rollback();
            $error = '保存失败：' . $e->getMessage();
        }
    }
}

// 获取编辑的文章
$edit_post = null;
$edit_post_tags = '';
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    $edit_post = $stmt->fetch();
    
    // 获取文章的标签
    if ($edit_post) {
        $stmt = $db->prepare("SELECT t.name FROM tags t 
                             JOIN post_tags pt ON t.id = pt.tag_id 
                             WHERE pt.post_id = ?");
        $stmt->execute([$id]);
        $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $edit_post_tags = implode(', ', $tags);
    }
}

// 获取所有分类
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// 获取文章已选分类ID数组
$edit_post_category_ids = [];
if ($edit_post) {
    $stmt = $db->prepare("SELECT category_id FROM post_categories WHERE post_id = ?");
    $stmt->execute([$edit_post['id']]);
    $edit_post_category_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// 获取文章列表
$stmt = $db->query("SELECT p.*, c.name as category_name, u.username as author_name,
                           (SELECT GROUP_CONCAT(t.name SEPARATOR ', ') 
                            FROM post_tags pt 
                            JOIN tags t ON pt.tag_id = t.id 
                            WHERE pt.post_id = p.id) as tags,
                           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count
                    FROM posts p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    LEFT JOIN users u ON p.author_id = u.id 
                    ORDER BY p.created_at DESC");
$posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文章管理 - <?php echo escape(get_setting('site_title', '我的博客')); ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/simplemde/1.11.2/simplemde.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet">
    <link href="assets/sidebar.css" rel="stylesheet">
    <link href="assets/dashboard.css" rel="stylesheet">
    <link href="assets/posts.css" rel="stylesheet">
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
                <a class="nav-link active" href="posts.php">
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
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">文章管理</h1>
            <a href="?action=new" class="btn btn-primary"><i class="fas fa-plus me-2"></i>写文章</a>
        </div>

        <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success animate-fade-in">
            <?php echo escape($_GET['message']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger animate-fade-in">
            <?php echo escape($_GET['error']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger animate-fade-in">
            <?php echo escape($error); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['action']) && ($_GET['action'] === 'new' || $_GET['action'] === 'edit')): ?>
        <!-- 文章编辑表单 -->
        <div class="dashboard-card animate-fade-in">
            <div class="dashboard-card-header">
                <h5 class="dashboard-card-title">
                    <?php echo $edit_post ? '编辑文章' : '写文章'; ?>
                </h5>
            </div>
            <div class="dashboard-card-body">
                <!-- 上传进度提示 -->
                <div class="upload-progress alert alert-info" role="alert">
                    <i class="fas fa-spinner fa-spin me-2"></i>正在上传图片...
                </div>

                <form method="post" class="needs-validation" novalidate>
                    <?php if ($edit_post): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_post['id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="title" class="form-label">标题</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo $edit_post ? escape($edit_post['title']) : ''; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="excerpt" class="form-label">摘要</label>
                        <textarea class="form-control" id="excerpt" name="excerpt" rows="3" 
                                  placeholder="文章摘要（可选）"><?php echo $edit_post ? escape($edit_post['excerpt'] ?? '') : ''; ?></textarea>
                        <div class="form-text">简短描述文章内容，用于首页展示和SEO</div>
                    </div>

                    <div class="mb-3">
                        <label for="cover_image" class="form-label">封面图片</label>
                        <input type="text" class="form-control" id="cover_image" name="cover_image" 
                               value="<?php echo $edit_post ? escape($edit_post['cover_image'] ?? '') : ''; ?>" 
                               placeholder="输入图片URL或点击上传图片按钮">
                        <div class="form-text">用于首页和文章详情页展示的封面图片</div>
                        <?php if ($edit_post && !empty($edit_post['cover_image'])): ?>
                        <div class="mt-2">
                            <small class="text-muted">当前封面图片：</small>
                            <div class="mt-1">
                                <img src="<?php echo escape($edit_post['cover_image']); ?>" 
                                     alt="封面图片" 
                                     style="max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 4px;">
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- 图片和附件上传按钮 -->
                    <div class="upload-buttons mb-3">
                        <div class="btn-group" role="group">
                            <label for="image-upload" class="btn btn-outline-primary">
                                <i class="fas fa-image"></i> 上传图片
                            </label>
                            <label for="cover-upload" class="btn btn-outline-success">
                                <i class="fas fa-file-image"></i> 上传封面
                            </label>
                            <label for="file-upload" class="btn btn-outline-secondary">
                                <i class="fas fa-paperclip"></i> 上传附件
                            </label>
                        </div>
                        <input type="file" id="image-upload" accept="image/*">
                        <input type="file" id="cover-upload" accept="image/*">
                        <input type="file" id="file-upload">
                        
                        <!-- 图片预览区域 -->
                        <div id="image-preview" class="mt-3" style="display: none;">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-eye me-2"></i>图片预览</h6>
                                </div>
                                <div class="card-body text-center">
                                    <img id="preview-img" src="" alt="预览图片" style="max-width: 100%; max-height: 300px; border-radius: 8px;">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="insertPreviewImage()">
                                            <i class="fas fa-plus me-1"></i>插入图片
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cancelImagePreview()">
                                            <i class="fas fa-times me-1"></i>取消
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="upload-info mt-2">
                            <small class="text-muted d-block">
                                <i class="fas fa-info-circle"></i> 
                                图片：支持 JPG, PNG, GIF, WebP 格式，最大5MB（插入到文章内容中）
                            </small>
                            <small class="text-muted d-block">
                                <i class="fas fa-info-circle"></i> 
                                封面：支持 JPG, PNG, GIF, WebP 格式，最大5MB（设置为文章封面图片）
                            </small>
                            <small class="text-muted d-block">
                                <i class="fas fa-info-circle"></i> 
                                附件：支持 PDF, DOC, XLS, PPT, TXT, ZIP, RAR, 7Z, MP3, MP4, AVI 等格式，最大100MB
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label">内容</label>
                        <textarea class="form-control" id="content" name="content" rows="10" required><?php 
                            echo $edit_post ? escape($edit_post['content']) : ''; 
                        ?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label">分类</label>
                            <select class="form-select" id="category" name="category_id[]" multiple required>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($edit_post && in_array($category['id'], $edit_post_category_ids)) ? 'selected' : ''; ?>>
                                    <?php echo escape($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">按住Ctrl或Shift可多选</div>
                        </div>

                        <div class="col-md-6">
                            <label for="status" class="form-label">状态</label>
                            <select class="form-select" id="status" name="status">
                                <option value="draft" <?php 
                                    echo ($edit_post && $edit_post['status'] === 'draft') ? 'selected' : ''; 
                                ?>>草稿</option>
                                <option value="publish" <?php 
                                    echo ($edit_post && $edit_post['status'] === 'publish') ? 'selected' : ''; 
                                ?>>发布</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tags" class="form-label">标签</label>
                        <input type="text" class="form-control" id="tags" name="tags" 
                               value="<?php echo escape($edit_post_tags); ?>" 
                               placeholder="输入标签，用逗号或空格分隔">
                        <div class="form-text">例如：技术, 教程, PHP 或 技术 教程 PHP</div>
                        <div id="tag-suggestions" class="mt-2"></div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存
                        </button>
                        <a href="posts.php" class="btn btn-outline-secondary">取消</a>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- 文章列表 -->
        <div class="dashboard-card animate-fade-in">
            <div class="dashboard-card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="dashboard-card-title mb-0">
                        <i class="fas fa-file-alt me-2"></i>文章列表
                    </h5>
                </div>
            </div>
            <div class="dashboard-card-body">
                <?php if (!empty($posts)): ?>
                <!-- 批量操作工具栏 -->
                <div class="batch-actions-toolbar mb-3" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted">已选择 <span id="selected-count">0</span> 篇文章</span>
                        <div class="vr"></div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-success" onclick="batchAction('publish')">
                                <i class="fas fa-check me-1"></i>批量发布
                            </button>
                            <button type="button" class="btn btn-sm btn-warning" onclick="batchAction('offline')">
                                <i class="fas fa-eye-slash me-1"></i>批量下架
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="batchAction('delete')">
                                <i class="fas fa-trash me-1"></i>批量删除
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                            <i class="fas fa-times me-1"></i>取消选择
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select-all" onchange="toggleSelectAll()">
                                    </div>
                                </th>
                                <th>标题</th>
                                <th>分类</th>
                                <th>标签</th>
                                <th>作者</th>
                                <th>状态</th>
                                <th>发布时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($posts)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">暂无文章</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input post-checkbox" type="checkbox" value="<?php echo $post['id']; ?>" onchange="updateSelection()">
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-file-alt text-primary me-2"></i>
                                        <?php echo escape($post['title']); ?>
                                    </div>
                                </td>
                                <td><?php echo escape($post['category_name']); ?></td>
                                <td>
                                    <?php if (!empty($post['tags'])): ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php 
                                        $tags = explode(', ', $post['tags']);
                                        $tag_colors = ['primary', 'secondary', 'success', 'info', 'warning'];
                                        foreach ($tags as $index => $tag): 
                                            $color = $tag_colors[$index % count($tag_colors)];
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> border border-<?php echo $color; ?> border-opacity-25">
                                            <?php echo escape($tag); ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">无标签</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user text-muted me-1"></i>
                                        <?php echo escape($post['author_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $post['status'] === 'publish' ? 'published' : 'draft'; ?>">
                                        <?php echo $post['status'] === 'publish' ? '发布' : '草稿'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        <?php echo date('Y-m-d', strtotime($post['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="?action=edit&id=<?php echo $post['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0)" 
                                           onclick="deletePost(<?php echo $post['id']; ?>)"
                                           class="btn btn-sm btn-outline-danger"
                                           title="删除文章">
                                            <i class="fas fa-trash"></i>
                                        </a>
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
        <?php endif; ?>
    </main>

    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/simplemde/1.11.2/simplemde.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-css.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-clike.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-python.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-java.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-c.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-cpp.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-csharp.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-bash.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-yaml.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-xml.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/prism/1.29.0/components/prism-html.min.js"></script>
    <script src="assets/posts.js"></script>

</body>
</html> 
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once 'includes/auth.php';

// 评论管理需要编辑以上权限
require_admin_permission('editor');

// 获取数据库连接
$db = Database::getInstance()->getConnection();

// 处理批量操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = $_POST['bulk_action'];
    $comment_ids = $_POST['comment_ids'] ?? [];
    
    if (!empty($comment_ids) && is_array($comment_ids)) {
        $placeholders = str_repeat('?,', count($comment_ids) - 1) . '?';
        
        switch ($bulk_action) {
            case 'approve':
                $stmt = $db->prepare("UPDATE comments SET status = 'approved' WHERE id IN ($placeholders)");
                $stmt->execute($comment_ids);
                $message = '评论已批准';
                break;
                
            case 'pending':
                $stmt = $db->prepare("UPDATE comments SET status = 'pending' WHERE id IN ($placeholders)");
                $stmt->execute($comment_ids);
                $message = '评论已设为待审核';
                break;
                
            case 'spam':
                $stmt = $db->prepare("UPDATE comments SET status = 'spam' WHERE id IN ($placeholders)");
                $stmt->execute($comment_ids);
                $message = '评论已标记为垃圾';
                break;
                
            case 'delete':
                // 软删除：设置状态为deleted
                $stmt = $db->prepare("UPDATE comments SET status = 'deleted' WHERE id IN ($placeholders)");
                $stmt->execute($comment_ids);
                $message = '评论已删除（软删除）';
                break;
                
            case 'hard_delete':
                // 硬删除：真正从数据库删除
                $stmt = $db->prepare("DELETE FROM comments WHERE id IN ($placeholders)");
                $stmt->execute($comment_ids);
                $message = '评论已彻底删除';
                break;
        }
        
        header('Location: comments.php?message=' . urlencode($message));
        exit;
    }
}

// 处理单个评论操作
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $comment_id = (int)$_GET['id'];
    
    switch ($action) {
        case 'approve':
            $stmt = $db->prepare("UPDATE comments SET status = 'approved' WHERE id = ?");
            $stmt->execute([$comment_id]);
            $message = '评论已批准';
            break;
            
        case 'pending':
            $stmt = $db->prepare("UPDATE comments SET status = 'pending' WHERE id = ?");
            $stmt->execute([$comment_id]);
            $message = '评论已设为待审核';
            break;
            
        case 'spam':
            $stmt = $db->prepare("UPDATE comments SET status = 'spam' WHERE id = ?");
            $stmt->execute([$comment_id]);
            $message = '评论已标记为垃圾';
            break;
            
        case 'delete':
            // 软删除：设置状态为deleted
            $stmt = $db->prepare("UPDATE comments SET status = 'deleted' WHERE id = ?");
            $stmt->execute([$comment_id]);
            $message = '评论已删除（软删除）';
            break;
            
        case 'hard_delete':
            // 硬删除：真正从数据库删除
            $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$comment_id]);
            $message = '评论已彻底删除';
            break;
    }
    
    header('Location: comments.php?message=' . urlencode($message));
    exit;
}

// 获取筛选参数
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 构建查询条件
$where_conditions = [];
$params = [];

// 默认不显示已删除的评论
if ($status_filter !== 'all') {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
} else {
    $where_conditions[] = "c.status != 'deleted'";
}

if (!empty($search)) {
    $where_conditions[] = "(c.content LIKE ? OR u.username LIKE ? OR p.title LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 获取评论总数
$count_sql = "SELECT COUNT(*) FROM comments c 
              JOIN users u ON c.user_id = u.id 
              JOIN posts p ON c.post_id = p.id 
              $where_clause";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_comments = $count_stmt->fetchColumn();

// 获取评论列表
$sql = "SELECT c.*, u.username, u.avatar, p.title as post_title,
               (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) as likes_count,
               (SELECT COUNT(*) FROM comments cc WHERE cc.parent_id = c.id) as reply_count
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        JOIN posts p ON c.post_id = p.id 
        $where_clause 
        ORDER BY c.created_at DESC 
        LIMIT ?, ?";

$params[] = $offset;
$params[] = $per_page;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$comments = $stmt->fetchAll();

// 计算分页
$total_pages = ceil($total_comments / $per_page);

// 获取评论统计
$stats = get_comment_stats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>评论管理 - <?php echo escape(get_setting('site_title', '我的博客')); ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/sidebar.css" rel="stylesheet">
    <link href="assets/dashboard.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
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
                <a class="nav-link active" href="comments.php">
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
            <h1 class="page-title">评论管理</h1>
            <div></div>
        </div>

        <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success animate-fade-in">
            <?php echo escape($_GET['message']); ?>
        </div>
        <?php endif; ?>

        <!-- 统计卡片 -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">总评论</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">已通过</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">待审核</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['spam']; ?></div>
                <div class="stat-label">垃圾评论</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['deleted']; ?></div>
                <div class="stat-label">已删除</div>
            </div>
        </div>

        <!-- 筛选和搜索 -->
        <div class="dashboard-card animate-fade-in">
            <div class="dashboard-card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>全部状态</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>已通过</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>待审核</option>
                            <option value="spam" <?php echo $status_filter === 'spam' ? 'selected' : ''; ?>>垃圾评论</option>
                            <option value="deleted" <?php echo $status_filter === 'deleted' ? 'selected' : ''; ?>>已删除</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="search" class="form-control" placeholder="搜索评论内容、用户名或文章标题..." value="<?php echo escape($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> 搜索
                        </button>
                        <a href="comments.php" class="btn btn-outline-secondary">重置</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- 批量操作 -->
        <form method="post" id="bulkForm">
            <div class="bulk-actions">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" id="selectAll" class="form-check-input">
                            <label for="selectAll" class="form-check-label">全选</label>
                            <select name="bulk_action" class="form-select" style="width: auto;">
                                <option value="">批量操作</option>
                                <option value="approve">批准</option>
                                <option value="pending">设为待审核</option>
                                <option value="spam">标记为垃圾</option>
                                <option value="delete">软删除</option>
                                <option value="hard_delete">彻底删除</option>
                            </select>
                            <button type="submit" class="btn btn-primary" onclick="return confirmBulkAction()">
                                执行
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="text-muted">共 <?php echo $total_comments; ?> 条评论</span>
                    </div>
                </div>
            </div>

            <!-- 评论列表卡片（UI与分类管理一致） -->
            <div class="data-card animate-fade-in">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>用户</th>
                                <th>内容</th>
                                <th>所属文章</th>
                                <th>状态</th>
                                <th>时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($comments)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">暂无评论</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                            <tr>
                                <td class="d-flex align-items-center">
                                    <img src="<?php echo get_user_avatar($comment['user_id']); ?>" alt="<?php echo escape($comment['username']); ?>" class="comment-avatar me-2" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                    <div>
                                        <div class="fw-bold"><?php echo escape($comment['username']); ?></div>
                                        <div class="text-muted" style="font-size: 12px;">
                                            <?php echo $comment['ip_address']; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo nl2br(escape($comment['content'])); ?></td>
                                <td><a href="../post.php?id=<?php echo $comment['post_id']; ?>" target="_blank"><?php echo escape($comment['post_title']); ?></a></td>
                                <td><span class="status-badge status-<?php echo $comment['status']; ?>"><?php 
                                    $status_names = [
                                        'approved' => '已通过',
                                        'pending' => '待审核',
                                        'spam' => '垃圾评论',
                                        'deleted' => '已删除'
                                    ];
                                    echo $status_names[$comment['status']];
                                ?></span></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="?action=approve&id=<?php echo $comment['id']; ?>" class="btn btn-sm btn-success" title="批准"><i class="fas fa-check"></i></a>
                                        <a href="?action=pending&id=<?php echo $comment['id']; ?>" class="btn btn-sm btn-warning" title="待审核"><i class="fas fa-clock"></i></a>
                                        <a href="?action=spam&id=<?php echo $comment['id']; ?>" class="btn btn-sm btn-danger" title="垃圾评论"><i class="fas fa-ban"></i></a>
                                        <a href="?action=delete&id=<?php echo $comment['id']; ?>" class="btn btn-sm btn-outline-danger" title="软删除" onclick="return confirm('确定要软删除这条评论吗？')"><i class="fas fa-trash"></i></a>
                                        <a href="?action=hard_delete&id=<?php echo $comment['id']; ?>" class="btn btn-sm btn-danger" title="彻底删除" onclick="return confirm('确定要彻底删除这条评论吗？此操作不可恢复！')"><i class="fas fa-times"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="评论分页">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">上一页</a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">下一页</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </main>

    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html> 
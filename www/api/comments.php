<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '只支持POST请求']);
    exit;
}

// 检查用户是否登录
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => '请先登录']);
    exit;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? '';

// 验证CSRF令牌
if (!isset($input['csrf_token']) && !isset($_POST['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['error' => '缺少CSRF令牌']);
    exit;
}

$csrf_token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(400);
    echo json_encode(['error' => 'CSRF令牌验证失败']);
    exit;
}

try {
    switch ($action) {
        case 'add_comment':
            $post_id = (int)($input['post_id'] ?? $_POST['post_id'] ?? 0);
            $content = trim($input['content'] ?? $_POST['content'] ?? '');
            $parent_id = (int)($input['parent_id'] ?? $_POST['parent_id'] ?? 0) ?: null;
            
            if ($post_id <= 0) {
                throw new Exception('文章ID无效');
            }
            
            if (empty($content)) {
                throw new Exception('评论内容不能为空');
            }
            
            // 验证文章是否存在
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM posts WHERE id = ? AND status = 'publish'");
            $stmt->execute([$post_id]);
            if (!$stmt->fetch()) {
                throw new Exception('文章不存在');
            }
            
            // 如果是回复，验证父评论是否存在
            if ($parent_id) {
                $stmt = $db->prepare("SELECT id FROM comments WHERE id = ? AND post_id = ? AND status = 'approved'");
                $stmt->execute([$parent_id, $post_id]);
                if (!$stmt->fetch()) {
                    throw new Exception('父评论不存在');
                }
            }
            
            // 添加评论
            $result = add_comment($post_id, $_SESSION['user_id'], $content, $parent_id);
            
            if ($result) {
                $status = get_setting('moderate_comments', 1) ? 'pending' : 'approved';
                echo json_encode([
                    'success' => true,
                    'message' => $status === 'pending' ? '评论已提交，等待审核' : '评论发表成功',
                    'status' => $status
                ]);
            } else {
                throw new Exception('评论发表失败');
            }
            break;
            
        case 'like_comment':
            $comment_id = (int)($input['comment_id'] ?? $_POST['comment_id'] ?? 0);
            
            if ($comment_id <= 0) {
                throw new Exception('评论ID无效');
            }
            
            // 验证评论是否存在
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM comments WHERE id = ? AND status = 'approved'");
            $stmt->execute([$comment_id]);
            if (!$stmt->fetch()) {
                throw new Exception('评论不存在');
            }
            
            // 切换点赞状态
            $is_liked = toggle_comment_like($comment_id, $_SESSION['user_id']);
            
            // 获取新的点赞数
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM comment_likes WHERE comment_id = ?");
            $stmt->execute([$comment_id]);
            $likes_count = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'is_liked' => $is_liked,
                'likes_count' => $likes_count,
                'message' => $is_liked ? '点赞成功' : '取消点赞'
            ]);
            break;
            
        case 'delete_comment':
            $comment_id = (int)($input['comment_id'] ?? $_POST['comment_id'] ?? 0);
            
            if ($comment_id <= 0) {
                throw new Exception('评论ID无效');
            }
            
            // 验证评论是否存在且属于当前用户
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, user_id FROM comments WHERE id = ?");
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch();
            
            if (!$comment) {
                throw new Exception('评论不存在');
            }
            
            if ($comment['user_id'] != $_SESSION['user_id'] && !is_admin()) {
                throw new Exception('没有删除权限');
            }
            
            // 删除评论（软删除）
            $stmt = $db->prepare("UPDATE comments SET status = 'deleted' WHERE id = ?");
            $result = $stmt->execute([$comment_id]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => '评论删除成功'
                ]);
            } else {
                throw new Exception('删除失败');
            }
            break;
            
        case 'load_comments':
            $post_id = (int)($input['post_id'] ?? $_POST['post_id'] ?? 0);
            $page = (int)($input['page'] ?? $_POST['page'] ?? 1);
            $limit = (int)get_setting('comments_per_page', 20);
            $offset = ($page - 1) * $limit;
            
            if ($post_id <= 0) {
                throw new Exception('文章ID无效');
            }
            
            // 获取评论
            $comments = get_comments_by_post($post_id, 'approved', $limit, $offset);
            $total_comments = get_comments_count($post_id, 'approved');
            
            // 处理每个评论的数据
            foreach ($comments as &$comment) {
                $comment['content'] = nl2br(htmlspecialchars($comment['content']));
                $comment['created_at_formatted'] = friendly_date($comment['created_at']);
                $comment['avatar'] = get_user_avatar($comment['user_id']);
                $comment['can_delete'] = $comment['user_id'] == $_SESSION['user_id'] || is_admin();
                $comment['is_liked'] = has_user_liked_comment($comment['id'], $_SESSION['user_id']);
                
                // 处理回复
                foreach ($comment['replies'] as &$reply) {
                    $reply['content'] = nl2br(htmlspecialchars($reply['content']));
                    $reply['created_at_formatted'] = friendly_date($reply['created_at']);
                    $reply['avatar'] = get_user_avatar($reply['user_id']);
                    $reply['can_delete'] = $reply['user_id'] == $_SESSION['user_id'] || is_admin();
                    $reply['is_liked'] = has_user_liked_comment($reply['id'], $_SESSION['user_id']);
                }
            }
            
            echo json_encode([
                'success' => true,
                'comments' => $comments,
                'total_comments' => $total_comments,
                'current_page' => $page,
                'has_more' => ($offset + $limit) < $total_comments
            ]);
            break;
            
        default:
            throw new Exception('未知的操作');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 
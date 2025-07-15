<?php
/**
 * 后台权限检查函数
 */

// 确保加载了必要的函数文件
if (!function_exists('is_admin')) {
    require_once dirname(__DIR__, 2) . '/includes/functions.php';
}

/**
 * 检查用户是否有后台访问权限
 * @param string $required_role 需要的最低角色权限 ('admin', 'editor', 'subscriber')
 * @return bool
 */
function check_admin_permission($required_role = 'editor') {
    $is_admin_logged = false;
    $user_role = '';
    
    // 检查是否通过后台登录
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $is_admin_logged = true;
        $user_role = $_SESSION['user_role'] ?? '';
    }
    // 检查是否通过前端登录且有后台权限
    elseif (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $user_role = $_SESSION['user_role'] ?? '';
        if ($user_role === 'admin' || $user_role === 'editor') {
            $is_admin_logged = true;
            // 设置后台登录标识以保持兼容性
            $_SESSION['admin_logged_in'] = true;
        }
    }
    
    // 检查角色权限层级
    $role_levels = [
        'subscriber' => 1,
        'editor' => 2,
        'admin' => 3
    ];
    
    $user_level = $role_levels[$user_role] ?? 0;
    $required_level = $role_levels[$required_role] ?? 2;
    
    return $is_admin_logged && $user_level >= $required_level;
}

/**
 * 强制检查后台权限，如果没有权限则重定向
 * @param string $required_role 需要的最低角色权限
 */
function require_admin_permission($required_role = 'editor') {
    if (!check_admin_permission($required_role)) {
        $redirect_url = 'login.php';
        
        // 如果是前端登录的用户但权限不足
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            $redirect_url = '../login.php?error=permission_denied';
        }
        
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * 获取当前用户角色
 * @return string
 */
function get_current_user_role() {
    return $_SESSION['user_role'] ?? '';
}

/**
 * 检查当前用户是否至少是编辑
 * @return bool
 */
function is_editor_or_above() {
    $role = get_current_user_role();
    return $role === 'admin' || $role === 'editor';
}
?> 
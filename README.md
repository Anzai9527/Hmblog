# HM博客系统开发说明

## 项目概述

HM博客系统是一个基于PHP开发的现代化博客平台，采用MVC架构设计，具有完整的文章管理、用户管理、评论系统等功能。系统支持响应式设计，适配各种设备，并提供友好的管理后台。

### 主要特性

- **现代化设计**: 响应式布局，支持桌面端和移动端
- **完整功能**: 文章管理、分类管理、标签系统、评论系统
- **用户权限**: 多级用户权限管理（管理员、编辑）
- **SEO优化**: 完整的SEO标签和伪静态支持
- **安全防护**: CSRF防护、SQL注入防护、XSS防护
- **性能优化**: 图片懒加载、静态资源缓存

## 技术架构

### 技术栈

- **后端**: PHP 7.4+
- **数据库**: MySQL 5.7+
- **前端**: HTML5, CSS3, JavaScript (ES6+)
- **UI框架**: Bootstrap 5.2.3
- **图标库**: Font Awesome 6.4.0
- **数据库操作**: PDO (PHP Data Objects)

### 目录结构

```
www/
├── admin/                    # 管理后台
│   ├── assets/              # 后台静态资源
│   ├── includes/            # 后台包含文件
│   ├── index.php           # 后台首页
│   ├── posts.php           # 文章管理
│   ├── categories.php      # 分类管理
│   ├── comments.php        # 评论管理
│   ├── users.php           # 用户管理
│   ├── settings.php        # 系统设置
│   ├── login.php           # 后台登录
│   └── logout.php          # 退出登录
├── api/                     # API接口
│   └── comments.php        # 评论API
├── assets/                  # 前台静态资源
│   ├── css/                # 样式文件
│   ├── js/                 # JavaScript文件
│   ├── images/             # 图片资源
│   └── uploads/            # 上传文件
├── content/                 # 内容目录
│   ├── articles/           # 文章内容
│   └── feeds/              # 订阅源
├── includes/                # 核心文件
│   ├── config.php          # 配置文件
│   ├── database.php        # 数据库连接
│   ├── functions.php       # 公共函数
│   ├── template.php        # 模板文件
│   └── installed.lock      # 安装锁定文件
├── templates/               # 模板目录
│   └── frontend/           # 前台模板
├── uploads/                 # 上传目录
├── index.php               # 前台首页
├── post.php                # 文章详情页
├── login.php               # 用户登录
├── register.php            # 用户注册
├── profile.php             # 用户资料
├── install.php             # 安装程序
└── 开发说明.md             # 本文档
```

## 核心功能

### 1. 文章管理系统

#### 功能特性
- 文章发布、编辑、删除
- 草稿保存功能
- 分类和标签管理
- 文章状态管理（发布/草稿）
- 浏览量统计
- 文章搜索和过滤

#### 数据库表结构
```sql
-- 文章表
CREATE TABLE posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    excerpt TEXT,
    cover_image VARCHAR(255),
    status ENUM('publish', 'draft') DEFAULT 'draft',
    views INT DEFAULT 0,
    likes INT DEFAULT 0,
    author_id INT,
    category_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 分类表
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 标签表
CREATE TABLE tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#007bff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 文章标签关联表
CREATE TABLE post_tags (
    post_id INT,
    tag_id INT,
    PRIMARY KEY (post_id, tag_id)
);
```

### 2. 用户管理系统

#### 功能特性
- 用户注册、登录、退出
- 用户权限管理（管理员、编辑）
- 用户资料管理
- 密码安全存储（bcrypt加密）
- 登录状态管理

#### 数据库表结构
```sql
-- 用户表
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor') DEFAULT 'editor',
    avatar VARCHAR(255),
    bio TEXT,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 3. 评论系统

#### 功能特性
- 文章评论功能
- 评论回复功能
- 评论审核机制
- 评论点赞功能
- 评论管理（审核、删除）

#### 数据库表结构
```sql
-- 评论表
CREATE TABLE comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT,
    parent_id INT NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'approved', 'spam') DEFAULT 'pending',
    likes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 评论点赞表
CREATE TABLE comment_likes (
    comment_id INT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id, user_id)
);
```

### 4. 系统设置

#### 功能特性
- 网站基本信息设置
- SEO相关设置
- 显示设置
- 安全设置

#### 数据库表结构
```sql
-- 设置表
CREATE TABLE settings (
    name VARCHAR(100) PRIMARY KEY,
    value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 安装部署

### 环境要求

- **PHP**: 7.4 或更高版本
- **MySQL**: 5.7 或更高版本
- **Web服务器**: Apache 或 Nginx
- **PHP扩展**: PDO, PDO_MySQL, JSON, mbstring

### 安装步骤

1. **上传文件**
   ```bash
   # 将项目文件上传到Web服务器目录
   ```

2. **设置权限**
   ```bash
   # 设置目录权限
   ```

3. **访问安装页面**
   ```
   http://your-domain.com/blog/install.php
   ```

4. **配置数据库**
   - 输入数据库连接信息
   - 系统会自动创建数据库和表结构

5. **配置网站信息**
   - 设置网站标题、描述等基本信息
   - 创建管理员账户

6. **完成安装**
   - 系统会自动创建 `includes/installed.lock` 文件
   - 删除 `install.php` 文件（安全考虑）

### Nginx配置

#### 伪静态规则
```nginx
# 文章详情页
rewrite ^/post/([0-9]+)\.html$ /post.php?id=$1 last;

# 标签搜索页
rewrite ^/index/tags\.html$ /index.php?view=tags last;

# 归档页
rewrite ^/index/archive/(.+?)\.html$ /index.php?archive=$1 last;

# 标签页
rewrite ^/index/tag/(.+?)\.html$ /index.php?tag=$1 last;

# 分类页
rewrite ^/index/category/(.+?)\.html$ /index.php?category=$1 last;

# 首页
rewrite ^/$ /index.php last;
```

#### 安全配置
```nginx
# 禁止访问敏感目录
location ~ /\. {
    deny all;
}

location ~ /(config|includes|admin)/ {
    deny all;
}

# 静态文件缓存
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

## 开发指南

### 1. 代码规范

#### PHP代码规范
- 使用PSR-4自动加载规范
- 类名使用大驼峰命名法
- 方法名使用小驼峰命名法
- 常量使用大写下划线命名法
- 缩进使用4个空格

#### 数据库规范
- 表名使用小写字母和下划线
- 字段名使用小写字母和下划线
- 主键统一使用 `id`
- 时间字段使用 `created_at` 和 `updated_at`

#### 前端规范
- HTML使用语义化标签
- CSS使用BEM命名规范
- JavaScript使用ES6+语法
- 图片使用alt属性

### 2. 安全考虑

#### SQL注入防护
```php
// 使用PDO预处理语句
$stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$id]);
```

#### XSS防护
```php
// 使用htmlspecialchars函数
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
```

#### CSRF防护
```php
// 生成CSRF令牌
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// 验证CSRF令牌
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
```

### 3. 性能优化

#### 数据库优化
- 使用索引优化查询性能
- 避免N+1查询问题
- 使用连接查询减少数据库请求

#### 前端优化
- 图片懒加载
- CSS和JavaScript压缩
- 静态资源缓存
- CDN加速

#### 缓存策略
```php
// 使用Redis缓存热门文章
function get_hot_posts($limit = 10) {
    $cache_key = "hot_posts_{$limit}";
    $cached = redis_get($cache_key);
    
    if ($cached) {
        return json_decode($cached, true);
    }
    
    $posts = get_posts_by_views($limit);
    redis_set($cache_key, json_encode($posts), 3600);
    
    return $posts;
}
```

### 4. 扩展开发

#### 添加新功能模块
1. 创建数据库表
2. 编写模型类
3. 创建控制器
4. 设计前端界面
5. 添加路由规则

#### 插件系统
```php
// 插件接口
interface PluginInterface {
    public function install();
    public function uninstall();
    public function activate();
    public function deactivate();
}

// 插件基类
abstract class BasePlugin implements PluginInterface {
    protected $name;
    protected $version;
    protected $description;
    
    abstract public function init();
}
```

## 维护指南

### 1. 日常维护

#### 数据库备份
```bash
# 创建数据库备份
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# 恢复数据库
mysql -u username -p database_name < backup_20250112.sql
```

#### 日志管理
```php
// 错误日志记录
function log_error($message, $context = []) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    file_put_contents('logs/error.log', json_encode($log_entry) . "\n", FILE_APPEND);
}
```

#### 性能监控
```php
// 页面加载时间监控
$start_time = microtime(true);

// ... 页面逻辑 ...

$end_time = microtime(true);
$load_time = ($end_time - $start_time) * 1000;

if ($load_time > 1000) {
    log_error("页面加载时间过长: {$load_time}ms");
}
```

### 2. 故障排除

#### 常见问题

1. **数据库连接失败**
   - 检查数据库配置
   - 确认数据库服务运行状态
   - 验证用户权限

2. **页面显示空白**
   - 检查PHP错误日志
   - 确认文件权限设置
   - 验证PHP扩展安装

3. **上传功能异常**
   - 检查上传目录权限
   - 确认PHP上传配置
   - 验证文件大小限制

#### 调试工具
```php
// 调试函数
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    exit;
}

// 性能分析
function profile($name) {
    static $profiles = [];
    $profiles[$name] = microtime(true);
    return $profiles;
}
```



*本文档最后更新时间: 2025年7月12日* 

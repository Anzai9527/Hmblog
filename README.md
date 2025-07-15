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
  

### 安装教程## 宝塔面板安装教程

如果你使用宝塔面板（BT Panel）来部署本博客系统，可以按照以下步骤进行：

### 1. 环境准备
- 登录宝塔面板，确保已安装以下组件：
  - Nginx
  - MySQL 5.7 及以上
  - PHP 7.4 及以上
- 在“软件商店”中安装并启用 `PDO`、`PDO_MySQL`、`fileinfo`、`mbstring`、`json` 等PHP扩展。

### 2. 创建站点和数据库
- 在“网站”菜单点击“添加站点”，填写你的域名或IP。
- 勾选“创建数据库”，设置数据库名、用户名和密码，记下这些信息，后续安装时会用到。

### 3. 配置PHP扩展
- 进入“软件商店”->“PHP设置”，确保已安装并启用：
  - PDO
  - PDO_MySQL
  - fileinfo
  - mbstring
  - json
- 可根据需要调整 `upload_max_filesize`、`post_max_size`、`max_execution_time` 等参数。

### 4. 上传部署项目
- 使用宝塔的“文件”功能或FTP工具，将本项目所有文件上传到站点根目录（如 `/www/wwwroot/yourdomain/`）。
- 检查 `uploads/`、`assets/uploads/`、`content/` 等目录权限，确保可写（755或777，视服务器安全策略而定）。

### 5. 设置伪静态规则
- 在“网站”->“设置”->“伪静态”中，选择“Nginx”，粘贴如下规则：

```
# Nginx 伪静态规则配置
# 适用于 hmjisu.com 博客系统

# 文章详情页：post.php?id=18 -> post/18.html
rewrite ^/post/([0-9]+)\.html$ /post.php?id=$1 last;

# 标签搜索页：index.php?view=tags -> index/tags.html
rewrite ^/index/tags\.html$ /index.php?view=tags last;

# 归档页：index.php?archive=2025+%E5%B9%B4+07+%E6%9C%88 -> index/archive/2025+%E5%B9%B4+07+%E6%9C%88.html
rewrite ^/index/archive/(.+?)\.html$ /index.php?archive=$1 last;

# 标签页：index.php?tag=123 -> index/tag/123.html
rewrite ^/index/tag/(.+?)\.html$ /index.php?tag=$1 last;

# 分类页：index.php?category=分类名 -> index/category/分类名.html
rewrite ^/index/category/(.+?)\.html$ /index.php?category=$1 last;

# 首页保持原样
rewrite ^/$ /index.php last;

# 其他PHP文件保持原样
location ~ \.php$ {
    try_files $uri =404;
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock; # 根据您的PHP-FPM配置调整
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}

# 静态文件缓存
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}

# 安全设置
location ~ /\. {
    deny all;
}

location ~ /(config|includes|admin)/ {
    deny all;
} 
```

### 6. 安装与初始化
- 在浏览器访问 `http://你的域名/install.php`，根据提示填写数据库信息和网站信息，完成安装。
- 安装完成后，系统会自动生成 `includes/installed.lock` 文件。
- 为安全起见，建议删除 `install.php` 文件。

### 7. 常见问题与建议
- **数据库连接失败**：检查数据库信息是否正确，数据库是否允许本地连接。
- **上传失败或权限问题**：检查上传目录权限，必要时设置为 755 或 777。
- **伪静态不生效**：确认已正确设置伪静态规则，并重载Nginx/Apache配置。
- **PHP扩展缺失**：在宝塔“软件商店”中安装缺失的扩展。
- **安全建议**：定期备份数据库和站点文件，及时更新宝塔和各组件。

如遇到其他问题，可参考宝塔官方文档或在社区发帖求助。


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

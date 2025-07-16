#!/bin/bash

# Hmblog一键安装脚本
# 支持Ubuntu/Debian和CentOS/RHEL系统
# 自动安装PHP 7.4+、MySQL 5.7+、Nginx并部署Hmblog

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查是否为root用户
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "此脚本需要root权限运行"
        exit 1
    fi
}

# 检测系统类型
detect_os() {
    if [[ -f /etc/redhat-release ]]; then
        OS="centos"
        log_info "检测到CentOS/RHEL系统"
    elif [[ -f /etc/debian_version ]]; then
        OS="ubuntu"
        log_info "检测到Ubuntu/Debian系统"
    else
        log_error "不支持的操作系统"
        exit 1
    fi
}

# 更新系统包
update_system() {
    log_info "更新系统包..."
    if [[ $OS == "ubuntu" ]]; then
        apt update && apt upgrade -y
        apt install -y curl wget unzip git software-properties-common
    else
        yum update -y
        yum install -y curl wget unzip git epel-release
    fi
}

# 安装Nginx
install_nginx() {
    log_info "安装Nginx..."
    if [[ $OS == "ubuntu" ]]; then
        apt install -y nginx
    else
        yum install -y nginx
    fi
    
    systemctl enable nginx
    systemctl start nginx
    log_info "Nginx安装完成"
}

# 安装MySQL
install_mysql() {
    log_info "安装MySQL 5.7+..."
    
    if [[ $OS == "ubuntu" ]]; then
        # Ubuntu安装MySQL
        apt install -y mysql-server mysql-client
        
        # 启动MySQL服务
        systemctl enable mysql
        systemctl start mysql
        
        # 设置MySQL root密码
        mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root123456';"
        mysql -e "FLUSH PRIVILEGES;"
        
    else
        # CentOS安装MySQL
        yum install -y mysql-server mysql
        
        # 启动MySQL服务
        systemctl enable mysqld
        systemctl start mysqld
        
        # 获取临时密码并重置
        TEMP_PASSWORD=$(grep 'temporary password' /var/log/mysqld.log | awk '{print $NF}')
        mysql -uroot -p"$TEMP_PASSWORD" --connect-expired-password -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'Root123456!';"
    fi
    
    log_info "MySQL安装完成"
}

# 安装PHP 7.4+
install_php() {
    log_info "安装PHP 7.4+..."
    
    if [[ $OS == "ubuntu" ]]; then
        # 添加PHP仓库
        add-apt-repository ppa:ondrej/php -y
        apt update
        
        # 安装PHP及扩展
        apt install -y php7.4 php7.4-fpm php7.4-mysql php7.4-curl php7.4-gd \
                       php7.4-mbstring php7.4-xml php7.4-zip php7.4-json \
                       php7.4-opcache php7.4-readline
        
        # 启动PHP-FPM
        systemctl enable php7.4-fpm
        systemctl start php7.4-fpm
        
    else
        # CentOS安装PHP
        yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm
        yum-config-manager --enable remi-php74
        
        yum install -y php php-fpm php-mysql php-curl php-gd php-mbstring \
                       php-xml php-zip php-json php-opcache
        
        # 启动PHP-FPM
        systemctl enable php-fpm
        systemctl start php-fpm
    fi
    
    log_info "PHP安装完成"
}

# 创建数据库和用户
setup_database() {
    log_info "创建数据库和用户..."
    
    # 创建数据库
    mysql -uroot -proot123456 -e "CREATE DATABASE IF NOT EXISTS hmblog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -uroot -proot123456 -e "CREATE USER IF NOT EXISTS 'hmblog'@'localhost' IDENTIFIED BY 'hmblog123456';"
    mysql -uroot -proot123456 -e "GRANT ALL PRIVILEGES ON hmblog.* TO 'hmblog'@'localhost';"
    mysql -uroot -proot123456 -e "FLUSH PRIVILEGES;"
    
    log_info "数据库创建完成"
}# 下载并部署Hmb
log
deploy_hmblog() {
    log_info "下载并部署Hmblog..."
    
    # 创建网站目录
    mkdir -p /var/www/hmblog
    cd /var/www/hmblog
    
    # 下载项目
    git clone https://github.com/Anzai9527/Hmblog.git temp
    cp -r temp/www/* .
    rm -rf temp
    
    # 设置权限
    chown -R www-data:www-data /var/www/hmblog
    chmod -R 755 /var/www/hmblog
    chmod -R 777 /var/www/hmblog/uploads
    chmod -R 777 /var/www/hmblog/content
    
    log_info "Hmblog部署完成"
}

# 配置Nginx
configure_nginx() {
    log_info "配置Nginx..."
    
    # 备份默认配置
    cp /etc/nginx/sites-available/default /etc/nginx/sites-available/default.bak 2>/dev/null || true
    
    # 创建Nginx配置
    cat > /etc/nginx/sites-available/hmblog << 'EOF'
server {
    listen 80;
    server_name localhost;
    root /var/www/hmblog;
    index index.php index.html index.htm;

    # 日志文件
    access_log /var/log/nginx/hmblog_access.log;
    error_log /var/log/nginx/hmblog_error.log;

    # 主要位置配置
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP处理
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静态文件缓存
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # 安全配置
    location ~ /\.ht {
        deny all;
    }
    
    location ~ /\.git {
        deny all;
    }

    # 上传文件大小限制
    client_max_body_size 100M;
}
EOF

    # 启用站点
    ln -sf /etc/nginx/sites-available/hmblog /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
    
    # 测试配置并重启
    nginx -t
    systemctl restart nginx
    
    log_info "Nginx配置完成"
}

# 初始化数据库
init_database() {
    log_info "初始化数据库..."
    
    # 创建基础表结构
    mysql -uhmblog -phmblog123456 hmblog << 'EOF'
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `author_id` int(11) NOT NULL,
  `status` enum('draft','published') DEFAULT 'draft',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `author_name` varchar(100) NOT NULL,
  `author_email` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF

    # 插入管理员账户 (密码: admin)
    ADMIN_PASSWORD=$(php -r "echo password_hash('admin', PASSWORD_DEFAULT);")
    mysql -uhmblog -phmblog123456 hmblog -e "INSERT IGNORE INTO users (username, password, email, role) VALUES ('admin', '$ADMIN_PASSWORD', 'admin@example.com', 'admin');"
    
    log_info "数据库初始化完成"
}

# 创建配置文件
create_config() {
    log_info "创建配置文件..."
    
    cat > /var/www/hmblog/includes/config.php << 'EOF'
<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'hmblog');
define('DB_USER', 'hmblog');
define('DB_PASS', 'hmblog123456');

// 站点配置
define('SITE_URL', 'http://localhost');
define('SITE_NAME', 'Hmblog');
define('SITE_DESCRIPTION', '一个简单的博客系统');

// 安全配置
define('SECRET_KEY', 'your-secret-key-here-' . md5(uniqid()));

// 上传配置
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB

// 调试模式
define('DEBUG', false);

// 时区设置
date_default_timezone_set('Asia/Shanghai');
?>
EOF

    chown www-data:www-data /var/www/hmblog/includes/config.php
    chmod 644 /var/www/hmblog/includes/config.php
    
    log_info "配置文件创建完成"
}

# 设置防火墙
setup_firewall() {
    log_info "配置防火墙..."
    
    if command -v ufw >/dev/null 2>&1; then
        ufw --force enable
        ufw allow 22
        ufw allow 80
        ufw allow 443
    elif command -v firewall-cmd >/dev/null 2>&1; then
        systemctl enable firewalld
        systemctl start firewalld
        firewall-cmd --permanent --add-service=http
        firewall-cmd --permanent --add-service=https
        firewall-cmd --permanent --add-service=ssh
        firewall-cmd --reload
    fi
    
    log_info "防火墙配置完成"
}

# 主安装函数
main() {
    log_info "开始安装Hmblog..."
    
    check_root
    detect_os
    update_system
    install_nginx
    install_mysql
    install_php
    setup_database
    deploy_hmblog
    configure_nginx
    init_database
    create_config
    setup_firewall
    
    log_info "安装完成！"
    echo
    echo "=================================="
    echo "Hmblog安装成功！"
    echo "=================================="
    echo "访问地址: http://$(curl -s ifconfig.me || echo 'your-server-ip')"
    echo "后台地址: http://$(curl -s ifconfig.me || echo 'your-server-ip')/admin"
    echo "管理员账号: admin"
    echo "管理员密码: admin"
    echo "数据库用户: hmblog"
    echo "数据库密码: hmblog123456"
    echo "=================================="
    echo
    log_info "请记住以上信息，并及时修改默认密码！"
}

# 执行主函数
main "$@"

#!/bin/bash

# Hmblog一键安装脚本 - 简化版
set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查root权限
if [[ $EUID -ne 0 ]]; then
    log_error "此脚本需要root权限运行"
    exit 1
fi

# 检测系统
if [[ -f /etc/debian_version ]]; then
    OS="ubuntu"
    log_info "检测到Ubuntu/Debian系统"
elif [[ -f /etc/redhat-release ]]; then
    OS="centos"
    log_info "检测到CentOS/RHEL系统"
else
    log_error "不支持的操作系统"
    exit 1
fi

# 更新系统
log_info "更新系统包..."
if [[ $OS == "ubuntu" ]]; then
    apt update -y
    apt install -y curl wget unzip git software-properties-common nginx mysql-server
    
    # 安装PHP
    add-apt-repository ppa:ondrej/php -y
    apt update -y
    apt install -y php7.4 php7.4-fpm php7.4-mysql php7.4-curl php7.4-gd php7.4-mbstring php7.4-xml php7.4-zip
else
    yum update -y
    yum install -y curl wget unzip git nginx mysql-server
    yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm
    yum-config-manager --enable remi-php74
    yum install -y php php-fpm php-mysql php-curl php-gd php-mbstring php-xml php-zip
fi

# 启动服务
log_info "启动服务..."
systemctl enable nginx mysql
systemctl start nginx mysql

if [[ $OS == "ubuntu" ]]; then
    systemctl enable php7.4-fpm
    systemctl start php7.4-fpm
else
    systemctl enable php-fpm
    systemctl start php-fpm
fi

# 设置MySQL
log_info "配置MySQL..."
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root123456';" 2>/dev/null || true
mysql -uroot -proot123456 -e "CREATE DATABASE IF NOT EXISTS hmblog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -proot123456 -e "CREATE USER IF NOT EXISTS 'hmblog'@'localhost' IDENTIFIED BY 'hmblog123456';"
mysql -uroot -proot123456 -e "GRANT ALL PRIVILEGES ON hmblog.* TO 'hmblog'@'localhost';"
mysql -uroot -proot123456 -e "FLUSH PRIVILEGES;"

# 部署项目
log_info "部署Hmblog..."
mkdir -p /var/www/hmblog
cd /var/www/hmblog
git clone https://github.com/Anzai9527/Hmblog.git temp
cp -r temp/www/* .
rm -rf temp

# 设置权限
chown -R www-data:www-data /var/www/hmblog 2>/dev/null || chown -R nginx:nginx /var/www/hmblog
chmod -R 755 /var/www/hmblog
chmod -R 777 /var/www/hmblog/uploads 2>/dev/null || true
chmod -R 777 /var/www/hmblog/content 2>/dev/null || true

# 配置Nginx
log_info "配置Nginx..."
cat > /etc/nginx/conf.d/hmblog.conf << 'EOF'
server {
    listen 80;
    server_name _;
    root /var/www/hmblog;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    client_max_body_size 100M;
}
EOF

# 重启Nginx
nginx -t && systemctl restart nginx

# 创建配置文件
log_info "创建配置文件..."
cat > /var/www/hmblog/includes/config.php << 'EOF'
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'hmblog');
define('DB_USER', 'hmblog');
define('DB_PASS', 'hmblog123456');
define('SITE_URL', 'http://localhost');
define('SITE_NAME', 'Hmblog');
define('SECRET_KEY', 'hmblog-secret-key');
date_default_timezone_set('Asia/Shanghai');
?>
EOF

# 初始化数据库
log_info "初始化数据库..."
mysql -uhmblog -phmblog123456 hmblog << 'EOF'
CREATE TABLE IF NOT EXISTS users (
  id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(50) NOT NULL,
  password varchar(255) NOT NULL,
  email varchar(100) NOT NULL,
  role enum('admin','user') DEFAULT 'user',
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF

# 创建管理员账户
ADMIN_PASSWORD=$(php -r "echo password_hash('admin', PASSWORD_DEFAULT);")
mysql -uhmblog -phmblog123456 hmblog -e "INSERT IGNORE INTO users (username, password, email, role) VALUES ('admin', '$ADMIN_PASSWORD', 'admin@example.com', 'admin');"

log_info "安装完成！"
echo "=================================="
echo "Hmblog安装成功！"
echo "=================================="
echo "访问地址: http://$(curl -s ifconfig.me 2>/dev/null || echo 'your-server-ip')"
echo "后台地址: http://$(curl -s ifconfig.me 2>/dev/null || echo 'your-server-ip')/admin"
echo "管理员账号: admin"
echo "管理员密码: admin"
echo "=================================="

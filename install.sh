#!/bin/bash

# Hmblog通用安装脚本 - 支持多种Linux发行版
set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查root权限
if [[ $EUID -ne 0 ]]; then
    log_error "此脚本需要root权限运行"
    exit 1
fi

# 检测系统类型
detect_system() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS=$ID
        VERSION=$VERSION_ID
        log_info "检测到系统: $PRETTY_NAME"
    elif [[ -f /etc/redhat-release ]]; then
        OS="centos"
        VERSION=$(grep -oE '[0-9]+\.[0-9]+' /etc/redhat-release | cut -d. -f1)
        log_info "检测到CentOS/RHEL $VERSION"
    elif [[ -f /etc/debian_version ]]; then
        OS="debian"
        log_info "检测到Debian系统"
    else
        log_error "不支持的操作系统"
        exit 1
    fi
}

# 修复CentOS 8镜像源
fix_centos8_repos() {
    if [[ "$OS" == "centos" && "$VERSION" == "8" ]]; then
        log_warn "CentOS 8已停止维护，修复镜像源..."
        
        # 备份原始repo文件
        mkdir -p /etc/yum.repos.d/backup
        cp /etc/yum.repos.d/*.repo /etc/yum.repos.d/backup/ 2>/dev/null || true
        
        # 使用阿里云CentOS 8 vault镜像
        cat > /etc/yum.repos.d/CentOS-Base.repo << 'EOF'
[base]
name=CentOS-8 - Base - mirrors.aliyun.com
baseurl=https://mirrors.aliyun.com/centos-vault/8.5.2111/BaseOS/x86_64/os/
gpgcheck=1
enabled=1
gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-centosofficial

[appstream]
name=CentOS-8 - AppStream - mirrors.aliyun.com
baseurl=https://mirrors.aliyun.com/centos-vault/8.5.2111/AppStream/x86_64/os/
gpgcheck=1
enabled=1
gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-centosofficial

[extras]
name=CentOS-8 - Extras - mirrors.aliyun.com
baseurl=https://mirrors.aliyun.com/centos-vault/8.5.2111/extras/x86_64/os/
gpgcheck=1
enabled=1
gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-centosofficial

[powertools]
name=CentOS-8 - PowerTools - mirrors.aliyun.com
baseurl=https://mirrors.aliyun.com/centos-vault/8.5.2111/PowerTools/x86_64/os/
gpgcheck=1
enabled=1
gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-centosofficial
EOF

        # 清理缓存
        yum clean all
        yum makecache
        log_info "CentOS 8镜像源修复完成"
    fi
}

# 安装基础包
install_base_packages() {
    log_info "安装基础软件包..."
    
    case "$OS" in
        "ubuntu"|"debian")
            apt update -y || log_warn "apt update失败，继续安装..."
            apt install -y curl wget unzip git software-properties-common || log_warn "部分包安装失败"
            ;;
        "centos"|"rhel"|"rocky"|"almalinux")
            if [[ "$VERSION" == "8" ]]; then
                dnf install -y curl wget unzip git || log_warn "部分包安装失败"
            else
                yum install -y curl wget unzip git epel-release || log_warn "部分包安装失败"
            fi
            ;;
        *)
            log_error "不支持的系统: $OS"
            exit 1
            ;;
    esac
}

# 安装Nginx
install_nginx() {
    log_info "安装Nginx..."
    
    case "$OS" in
        "ubuntu"|"debian")
            apt install -y nginx
            ;;
        "centos"|"rhel"|"rocky"|"almalinux")
            if [[ "$VERSION" == "8" ]]; then
                dnf install -y nginx
            else
                yum install -y nginx
            fi
            ;;
    esac
    
    systemctl enable nginx
    systemctl start nginx
    log_info "Nginx安装完成"
}

# 安装MySQL/MariaDB
install_database() {
    log_info "安装数据库..."
    
    case "$OS" in
        "ubuntu"|"debian")
            apt install -y mysql-server mysql-client
            systemctl enable mysql
            systemctl start mysql
            # 设置root密码
            mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root123456';" 2>/dev/null || \
            mysql -e "SET PASSWORD FOR 'root'@'localhost' = PASSWORD('root123456');" 2>/dev/null || true
            ;;
        "centos"|"rhel"|"rocky"|"almalinux")
            if [[ "$VERSION" == "8" ]]; then
                dnf install -y mysql-server mysql || dnf install -y mariadb-server mariadb
            else
                yum install -y mysql-server mysql || yum install -y mariadb-server mariadb
            fi
            
            # 启动数据库服务
            systemctl enable mysqld 2>/dev/null || systemctl enable mariadb
            systemctl start mysqld 2>/dev/null || systemctl start mariadb
            
            # 设置root密码
            mysql -e "SET PASSWORD FOR 'root'@'localhost' = PASSWORD('root123456');" 2>/dev/null || \
            mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'root123456';" 2>/dev/null || true
            ;;
    esac
    
    log_info "数据库安装完成"
}

# 安装PHP
install_php() {
    log_info "安装PHP..."
    
    case "$OS" in
        "ubuntu"|"debian")
            # 添加PHP仓库
            add-apt-repository ppa:ondrej/php -y 2>/dev/null || true
            apt update -y
            apt install -y php7.4 php7.4-fpm php7.4-mysql php7.4-curl php7.4-gd php7.4-mbstring php7.4-xml php7.4-zip
            systemctl enable php7.4-fpm
            systemctl start php7.4-fpm
            PHP_FPM_SOCK="/var/run/php/php7.4-fpm.sock"
            ;;
        "centos"|"rhel"|"rocky"|"almalinux")
            if [[ "$VERSION" == "8" ]]; then
                # CentOS 8使用dnf
                dnf install -y epel-release
                dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm 2>/dev/null || true
                dnf module enable -y php:remi-7.4 2>/dev/null || true
                dnf install -y php php-fpm php-mysql php-curl php-gd php-mbstring php-xml php-zip
            else
                # CentOS 7使用yum
                yum install -y epel-release
                yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm 2>/dev/null || true
                yum-config-manager --enable remi-php74 2>/dev/null || true
                yum install -y php php-fpm php-mysql php-curl php-gd php-mbstring php-xml php-zip
            fi
            
            systemctl enable php-fpm
            systemctl start php-fpm
            PHP_FPM_SOCK="/var/run/php-fpm/www.sock"
            ;;
    esac
    
    log_info "PHP安装完成"
}#
 配置数据库
setup_database() {
    log_info "配置数据库..."
    
    # 创建数据库和用户
    mysql -uroot -proot123456 -e "CREATE DATABASE IF NOT EXISTS hmblog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || \
    mysql -uroot -proot123456 -e "CREATE DATABASE IF NOT EXISTS hmblog;" 2>/dev/null || \
    mysql -uroot -e "CREATE DATABASE IF NOT EXISTS hmblog;" 2>/dev/null || true
    
    mysql -uroot -proot123456 -e "CREATE USER IF NOT EXISTS 'hmblog'@'localhost' IDENTIFIED BY 'hmblog123456';" 2>/dev/null || \
    mysql -uroot -e "CREATE USER IF NOT EXISTS 'hmblog'@'localhost' IDENTIFIED BY 'hmblog123456';" 2>/dev/null || true
    
    mysql -uroot -proot123456 -e "GRANT ALL PRIVILEGES ON hmblog.* TO 'hmblog'@'localhost';" 2>/dev/null || \
    mysql -uroot -e "GRANT ALL PRIVILEGES ON hmblog.* TO 'hmblog'@'localhost';" 2>/dev/null || true
    
    mysql -uroot -proot123456 -e "FLUSH PRIVILEGES;" 2>/dev/null || \
    mysql -uroot -e "FLUSH PRIVILEGES;" 2>/dev/null || true
    
    log_info "数据库配置完成"
}

# 部署Hmblog项目
deploy_hmblog() {
    log_info "部署Hmblog项目..."
    
    # 创建网站目录
    mkdir -p /var/www/hmblog
    cd /var/www/hmblog
    
    # 下载项目
    git clone https://github.com/Anzai9527/Hmblog.git temp
    cp -r temp/www/* . 2>/dev/null || cp -r temp/* .
    rm -rf temp
    
    # 设置权限
    if id "www-data" &>/dev/null; then
        chown -R www-data:www-data /var/www/hmblog
    elif id "nginx" &>/dev/null; then
        chown -R nginx:nginx /var/www/hmblog
    else
        chown -R apache:apache /var/www/hmblog 2>/dev/null || true
    fi
    
    chmod -R 755 /var/www/hmblog
    chmod -R 777 /var/www/hmblog/uploads 2>/dev/null || mkdir -p /var/www/hmblog/uploads && chmod 777 /var/www/hmblog/uploads
    chmod -R 777 /var/www/hmblog/content 2>/dev/null || mkdir -p /var/www/hmblog/content && chmod 777 /var/www/hmblog/content
    
    log_info "项目部署完成"
}

# 配置Nginx
configure_nginx() {
    log_info "配置Nginx..."
    
    # 检测PHP-FPM socket路径
    if [[ -S "/var/run/php/php7.4-fpm.sock" ]]; then
        PHP_FPM_SOCK="/var/run/php/php7.4-fpm.sock"
    elif [[ -S "/var/run/php-fpm/www.sock" ]]; then
        PHP_FPM_SOCK="/var/run/php-fpm/www.sock"
    elif [[ -S "/var/run/php/php-fpm.sock" ]]; then
        PHP_FPM_SOCK="/var/run/php/php-fpm.sock"
    else
        PHP_FPM_SOCK="/var/run/php-fpm/www.sock"
    fi
    
    # 创建Nginx配置文件
    cat > /etc/nginx/conf.d/hmblog.conf << EOF
server {
    listen 80 default_server;
    server_name _;
    root /var/www/hmblog;
    index index.php index.html index.htm;

    # 日志文件
    access_log /var/log/nginx/hmblog_access.log;
    error_log /var/log/nginx/hmblog_error.log;

    # 主要位置配置
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP处理
    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静态文件缓存
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)\$ {
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

    # 禁用默认站点
    rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
    rm -f /etc/nginx/conf.d/default.conf 2>/dev/null || true
    
    # 测试配置并重启
    nginx -t && systemctl restart nginx
    
    log_info "Nginx配置完成"
}

# 创建配置文件
create_config() {
    log_info "创建配置文件..."
    
    # 确保includes目录存在
    mkdir -p /var/www/hmblog/includes
    
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
define('SECRET_KEY', 'hmblog-secret-key-' . md5(uniqid()));

// 上传配置
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB

// 调试模式
define('DEBUG', false);

// 时区设置
date_default_timezone_set('Asia/Shanghai');
?>
EOF

    # 设置权限
    if id "www-data" &>/dev/null; then
        chown www-data:www-data /var/www/hmblog/includes/config.php
    elif id "nginx" &>/dev/null; then
        chown nginx:nginx /var/www/hmblog/includes/config.php
    fi
    chmod 644 /var/www/hmblog/includes/config.php
    
    log_info "配置文件创建完成"
}

# 初始化数据库
init_database() {
    log_info "初始化数据库..."
    
    # 创建基础表结构
    mysql -uhmblog -phmblog123456 hmblog << 'EOF' 2>/dev/null || mysql -uroot -proot123456 hmblog << 'EOF' || mysql -uroot hmblog << 'EOF'
CREATE TABLE IF NOT EXISTS users (
  id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(50) NOT NULL,
  password varchar(255) NOT NULL,
  email varchar(100) NOT NULL,
  role enum('admin','user') DEFAULT 'user',
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY username (username),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS posts (
  id int(11) NOT NULL AUTO_INCREMENT,
  title varchar(255) NOT NULL,
  content longtext NOT NULL,
  author_id int(11) NOT NULL,
  status enum('draft','published') DEFAULT 'draft',
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY author_id (author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  description text,
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comments (
  id int(11) NOT NULL AUTO_INCREMENT,
  post_id int(11) NOT NULL,
  author_name varchar(100) NOT NULL,
  author_email varchar(100) NOT NULL,
  content text NOT NULL,
  status enum('pending','approved','rejected') DEFAULT 'pending',
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY post_id (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF

    # 创建管理员账户
    ADMIN_PASSWORD=$(php -r "echo password_hash('admin', PASSWORD_DEFAULT);")
    mysql -uhmblog -phmblog123456 hmblog -e "INSERT IGNORE INTO users (username, password, email, role) VALUES ('admin', '$ADMIN_PASSWORD', 'admin@example.com', 'admin');" 2>/dev/null || \
    mysql -uroot -proot123456 hmblog -e "INSERT IGNORE INTO users (username, password, email, role) VALUES ('admin', '$ADMIN_PASSWORD', 'admin@example.com', 'admin');" 2>/dev/null || \
    mysql -uroot hmblog -e "INSERT IGNORE INTO users (username, password, email, role) VALUES ('admin', '$ADMIN_PASSWORD', 'admin@example.com', 'admin');" 2>/dev/null || true
    
    log_info "数据库初始化完成"
}

# 配置防火墙
setup_firewall() {
    log_info "配置防火墙..."
    
    # Ubuntu/Debian使用ufw
    if command -v ufw >/dev/null 2>&1; then
        ufw --force enable 2>/dev/null || true
        ufw allow 22 2>/dev/null || true
        ufw allow 80 2>/dev/null || true
        ufw allow 443 2>/dev/null || true
    # CentOS/RHEL使用firewalld
    elif command -v firewall-cmd >/dev/null 2>&1; then
        systemctl enable firewalld 2>/dev/null || true
        systemctl start firewalld 2>/dev/null || true
        firewall-cmd --permanent --add-service=http 2>/dev/null || true
        firewall-cmd --permanent --add-service=https 2>/dev/null || true
        firewall-cmd --permanent --add-service=ssh 2>/dev/null || true
        firewall-cmd --reload 2>/dev/null || true
    fi
    
    log_info "防火墙配置完成"
}

# 主安装函数
main() {
    log_info "开始安装Hmblog..."
    
    detect_system
    fix_centos8_repos
    install_base_packages
    install_nginx
    install_database
    install_php
    setup_database
    deploy_hmblog
    configure_nginx
    create_config
    init_database
    setup_firewall
    
    log_info "安装完成！"
    echo
    echo "=================================="
    echo "Hmblog安装成功！"
    echo "=================================="
    echo "访问地址: http://$(curl -s ifconfig.me 2>/dev/null || curl -s ipinfo.io/ip 2>/dev/null || echo 'your-server-ip')"
    echo "后台地址: http://$(curl -s ifconfig.me 2>/dev/null || curl -s ipinfo.io/ip 2>/dev/null || echo 'your-server-ip')/admin"
    echo "管理员账号: admin"
    echo "管理员密码: admin"
    echo "数据库用户: hmblog"
    echo "数据库密码: hmblog123456"
    echo "=================================="
    echo
    log_info "请记住以上信息，并及时修改默认密码！"
    log_info "如果无法访问，请检查防火墙设置和服务状态"
}

# 执行主函数
main "$@"

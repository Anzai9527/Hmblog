#!/bin/bash
# HM博客一键安装脚本
# 适配 Ubuntu/Debian/CentOS/Rocky/Alma/openEuler 等主流Linux发行版
# 安装 MySQL 5.7、Nginx、PHP 7.4，自动初始化数据库和站点

set -e

# 颜色输出
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

# 检查root权限
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}请以root权限运行本脚本！${NC}"
  exit 1
fi

# 检测系统类型
if [ -f /etc/os-release ]; then
  . /etc/os-release
  OS=$ID
else
  echo -e "${RED}无法识别的Linux发行版！${NC}"
  exit 1
fi

# 生成随机数据库密码
DB_PASS=$(tr -dc A-Za-z0-9 </dev/urandom | head -c 16)
DB_NAME="hmblog"
DB_USER="hmblog"
ADMIN_USER="admin"
ADMIN_PASS="admin"
SITE_NAME="HM博客"
SITE_URL="http://$(hostname -I | awk '{print $1}')"

# 变量
GIT_REPO="https://github.com/Anzai9527/Hmblog.git"
WEB_ROOT="/var/www/hmblog"

# 安装git
install_git() {
  if ! command -v git >/dev/null 2>&1; then
    case $OS in
      ubuntu|debian) apt-get install -y git ;;
      centos|rocky|almalinux|openEuler) yum install -y git ;;
    esac
  fi
}

# 拉取或更新代码
deploy_code() {
  if [ ! -d "$WEB_ROOT" ]; then
    git clone "$GIT_REPO" "$WEB_ROOT"
  else
    cd "$WEB_ROOT"
    git pull
    cd -
  fi
  cd "$WEB_ROOT/www"
}

# 安装依赖
install_packages() {
  case $OS in
    ubuntu|debian)
      export DEBIAN_FRONTEND=noninteractive
      apt-get update
      apt-get install -y lsb-release gnupg2 curl wget unzip pwgen
      # 安装Nginx
      apt-get install -y nginx
      # 安装MySQL 5.7
      wget https://dev.mysql.com/get/mysql-apt-config_0.8.13-1_all.deb
      echo "mysql-apt-config mysql-apt-config/select-server select mysql-5.7" | debconf-set-selections
      dpkg -i mysql-apt-config_0.8.13-1_all.deb
      apt-get update
      apt-get install -y mysql-server
      # 安装PHP 7.4
      apt-get install -y software-properties-common
      add-apt-repository ppa:ondrej/php -y
      apt-get update
      apt-get install -y php7.4 php7.4-fpm php7.4-mysql php7.4-mbstring php7.4-json php7.4-xml php7.4-curl php7.4-zip
      ;;
    centos|rocky|almalinux|openEuler)
      yum install -y epel-release wget curl unzip pwgen
      # 禁用系统自带MySQL模块，避免yum/dnf模块化机制导致无法安装mysql-community-server
      if command -v dnf >/dev/null 2>&1; then
        dnf module disable -y mysql || true
      elif command -v yum >/dev/null 2>&1; then
        yum module disable -y mysql || true
      fi
      yum clean all
      yum makecache
      # 安装Nginx
      yum install -y nginx
      # 安装MySQL 5.7
      rpm -Uvh https://repo.mysql.com/mysql57-community-release-el7-11.noarch.rpm
      yum install -y mysql-community-server
      # 安装PHP 7.4
      yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm
      yum-config-manager --enable remi-php74
      yum install -y php php-fpm php-mysqlnd php-mbstring php-json php-xml php-curl php-zip
      ;;
    *)
      echo -e "${RED}暂不支持的Linux发行版: $OS${NC}"
      exit 1
      ;;
  esac
}

# 启动服务
start_services() {
  systemctl enable nginx
  systemctl start nginx
  systemctl enable mysqld || systemctl enable mysql
  systemctl start mysqld || systemctl start mysql
  systemctl enable php7.4-fpm || systemctl enable php-fpm
  systemctl start php7.4-fpm || systemctl start php-fpm
}

# 初始化MySQL
init_mysql() {
  # 获取MySQL root密码（CentOS首次安装有临时密码）
  MYSQL_ROOT_PASS="root"
  if [ "$OS" = "centos" ] || [ "$OS" = "rocky" ] || [ "$OS" = "almalinux" ] || [ "$OS" = "openEuler" ]; then
    TEMP_PASS=$(grep 'temporary password' /var/log/mysqld.log | awk '{print $NF}' | tail -1)
    if [ -n "$TEMP_PASS" ]; then
      MYSQL_ROOT_PASS="$TEMP_PASS"
      mysql --connect-expired-password -uroot -p"$MYSQL_ROOT_PASS" -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'root';"
      MYSQL_ROOT_PASS="root"
    fi
  fi

  # 创建数据库和用户
  mysql -uroot -p"$MYSQL_ROOT_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
}

# 生成includes/config.php
write_config_php() {
  mkdir -p includes
  cat > includes/config.php <<EOL
<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', '$DB_NAME');
define('DB_USER', '$DB_USER');
define('DB_PASS', '$DB_PASS');
define('DB_CHARSET', 'utf8mb4');
// 其他配置将在安装过程中添加
EOL
}

# 导入表结构和管理员账号
init_db_schema() {
  mysql -u$DB_USER -p$DB_PASS $DB_NAME <<EOSQL
-- 用户表
CREATE TABLE IF NOT EXISTS users (
  id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(50) NOT NULL,
  password varchar(255) NOT NULL,
  email varchar(100) NOT NULL,
  role enum('admin','editor','subscriber') DEFAULT 'subscriber',
  status tinyint(1) DEFAULT 1,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  last_login datetime DEFAULT NULL,
  avatar varchar(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY username (username),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 分类表
CREATE TABLE IF NOT EXISTS categories (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(50) NOT NULL,
  slug varchar(50) NOT NULL,
  description varchar(255) DEFAULT NULL,
  parent_id int(11) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 标签表
CREATE TABLE IF NOT EXISTS tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(50) NOT NULL,
  slug varchar(50) NOT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 文章表
CREATE TABLE IF NOT EXISTS posts (
  id int(11) NOT NULL AUTO_INCREMENT,
  title varchar(255) NOT NULL,
  slug varchar(255) DEFAULT NULL,
  content text,
  cover_image varchar(255) DEFAULT NULL COMMENT '文章封面图片路径',
  excerpt text,
  status enum('publish','draft','pending','private') DEFAULT 'draft',
  author_id int(11) NOT NULL,
  category_id int(11) DEFAULT NULL,
  views int(11) DEFAULT 0,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  likes int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY slug (slug),
  KEY author_id (author_id),
  KEY category_id (category_id),
  FULLTEXT KEY title_content (title,content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 文章标签关联表
CREATE TABLE IF NOT EXISTS post_tags (
  post_id int(11) NOT NULL,
  tag_id int(11) NOT NULL,
  PRIMARY KEY (post_id,tag_id),
  KEY tag_id (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 文章分类关联表
CREATE TABLE IF NOT EXISTS post_categories (
  post_id int(11) NOT NULL,
  category_id int(11) NOT NULL,
  PRIMARY KEY (post_id,category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 评论表
CREATE TABLE IF NOT EXISTS comments (
  id int(11) NOT NULL AUTO_INCREMENT,
  post_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  parent_id int(11) DEFAULT NULL,
  content text COLLATE utf8mb4_unicode_ci NOT NULL,
  status enum('approved','pending','spam','deleted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  ip_address varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  user_agent text COLLATE utf8mb4_unicode_ci,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY post_id (post_id),
  KEY user_id (user_id),
  KEY parent_id (parent_id),
  KEY status (status),
  KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 评论点赞表
CREATE TABLE IF NOT EXISTS comment_likes (
  id int(11) NOT NULL AUTO_INCREMENT,
  comment_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_like (comment_id,user_id),
  KEY comment_id (comment_id),
  KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 导航表
CREATE TABLE IF NOT EXISTS navigation (
  id int(11) NOT NULL AUTO_INCREMENT,
  title varchar(50) NOT NULL,
  url varchar(255) NOT NULL,
  `order` int(11) DEFAULT 0,
  parent_id int(11) DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 设置表
CREATE TABLE IF NOT EXISTS settings (
  name varchar(50) NOT NULL,
  value text,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理员账号
INSERT INTO users (username, password, email, role) VALUES ('$ADMIN_USER', '$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")', 'admin@hmblog.local', 'admin') ON DUPLICATE KEY UPDATE username=username;
-- 默认分类
INSERT INTO categories (name, slug, description, parent_id) VALUES ('默认分类', 'default', '默认分类描述', NULL) ON DUPLICATE KEY UPDATE name=name;
-- 网站设置
INSERT INTO settings (name, value) VALUES
  ('site_title', '$SITE_NAME'),
  ('site_description', '欢迎使用HM博客'),
  ('site_url', '$SITE_URL'),
  ('site_author', '$ADMIN_USER'),
  ('site_keywords', '博客,HM博客,PHP'),
  ('posts_per_page', '10'),
  ('default_category', '1'),
  ('allow_comments', '1'),
  ('allow_comment_replies', '1'),
  ('comment_approval_required', '1'),
  ('comment_max_length', '1000'),
  ('comment_per_page', '10'),
  ('comments_per_page', '20'),
  ('comment_allow_html', '0'),
  ('comment_close_days', '30'),
  ('comment_notification_email', ''),
  ('moderate_comments', '0'),
  ('auto_generate_sitemap', '0'),
  ('sitemap_update_frequency', 'daily'),
  ('banner_image', '')
ON DUPLICATE KEY UPDATE name=name;
EOSQL
}

# 配置Nginx虚拟主机
setup_nginx() {
  NGINX_CONF="/etc/nginx/conf.d/hmblog.conf"
  cat > $NGINX_CONF <<EON
server {
    listen 80;
    server_name _;
    root $WEB_ROOT/www;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        access_log off;
    }
}
EON
  nginx -t && systemctl reload nginx
}

# 主流程
main() {
  echo -e "${GREEN}0. 安装git...${NC}"
  install_git
  echo -e "${GREEN}0. 拉取/更新代码...${NC}"
  deploy_code
  echo -e "${GREEN}1. 安装依赖...${NC}"
  install_packages
  echo -e "${GREEN}2. 启动服务...${NC}"
  start_services
  echo -e "${GREEN}3. 初始化MySQL...${NC}"
  init_mysql
  echo -e "${GREEN}4. 写入数据库配置...${NC}"
  write_config_php
  echo -e "${GREEN}5. 初始化数据库表结构和管理员账号...${NC}"
  init_db_schema
  echo -e "${GREEN}6. 配置Nginx虚拟主机...${NC}"
  setup_nginx
  echo -e "${GREEN}安装完成！${NC}"
  echo -e "${GREEN}数据库名: $DB_NAME${NC}"
  echo -e "${GREEN}数据库用户: $DB_USER${NC}"
  echo -e "${GREEN}数据库密码: $DB_PASS${NC}"
  echo -e "${GREEN}管理员账号: $ADMIN_USER${NC}"
  echo -e "${GREEN}管理员密码: $ADMIN_PASS${NC}"
  echo -e "${GREEN}站点名: $SITE_NAME${NC}"
  echo -e "${GREEN}访问地址: $SITE_URL${NC}"
}

main 

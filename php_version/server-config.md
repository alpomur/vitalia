# Vitalia API - Apache/Nginx Configuration

## Apache (.htaccess)
# Dosya adı: .htaccess
# Konum: public_html veya www klasörü

```apache
RewriteEngine On
RewriteBase /

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# API routes - route to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ index.php [QSA,L]

# React SPA fallback - all other requests go to index.html
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/api/
RewriteRule . /index.html [L]

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Disable directory listing
Options -Indexes

# Protect config file
<Files "config.php">
    Order Allow,Deny
    Deny from all
</Files>

# PHP settings
<IfModule mod_php8.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    php_value max_execution_time 60
    php_value memory_limit 128M
</IfModule>
```

## Nginx Configuration
# Dosya adı: nginx.conf veya site config

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    
    root /var/www/vitalia/public;
    index index.html index.php;
    
    # SSL configuration
    ssl_certificate /path/to/ssl/certificate.crt;
    ssl_certificate_key /path/to/ssl/private.key;
    
    # Security headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # API routes
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # React SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
    }
    
    # Block access to config files
    location ~ /config\.php$ {
        deny all;
        return 404;
    }
    
    # Block hidden files
    location ~ /\. {
        deny all;
    }
    
    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
}
```

## Klasör Yapısı (Paylaşımlı Hosting)

```
public_html/
├── .htaccess           # Apache rewrite kuralları
├── index.html          # React build (ana sayfa)
├── index.php           # API router
├── config.php          # Yapılandırma (web erişimine kapalı)
├── api/
│   ├── chat.php
│   ├── log.php
│   ├── auth.php
│   ├── admin.php
│   └── profile.php
├── static/             # React build static dosyaları
│   ├── css/
│   └── js/
└── assets/             # Resimler ve diğer statik dosyalar
```

## Kurulum Adımları

1. MySQL veritabanı oluştur
2. `database.sql` dosyasını import et
3. `config.php` dosyasını düzenle
4. PHP dosyalarını yükle
5. React build dosyalarını yükle
6. `.htaccess` dosyasını ayarla
7. SSL sertifikası ekle

## Gereksinimler

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache mod_rewrite veya Nginx
- SSL sertifikası
- cURL extension
- JSON extension
- PDO MySQL extension

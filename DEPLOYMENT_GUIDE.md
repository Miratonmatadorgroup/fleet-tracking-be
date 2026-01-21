# Loops Freight Fleet Tracking System - Deployment Guide

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Local Development Setup](#local-development-setup)
3. [Production Deployment](#production-deployment)
4. [Database Setup](#database-setup)
5. [Environment Configuration](#environment-configuration)
6. [SSL/TLS Configuration](#ssltls-configuration)
7. [Monitoring & Logging](#monitoring--logging)
8. [Backup & Recovery](#backup--recovery)
9. [Scaling Strategies](#scaling-strategies)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Software
- **Docker** 24.0+ & **Docker Compose** 2.20+
- **PostgreSQL** 15+ with PostGIS extension
- **Redis** 7+
- **Node.js** 20+
- **PHP** 8.2+
- **Composer** 2.6+
- **Nginx** or **Apache** (for production)

### External Services
- **Pusher** account (or self-hosted Soketi)
- **Stripe** account (payment processing)
- **Paystack** account (Nigeria-specific payments)
- **AWS S3** or **Cloudinary** (file storage)
- **SendGrid** or **Mailgun** (email notifications)
- **Twilio** (SMS notifications)

---

## Local Development Setup

### Option 1: Docker Compose (Recommended)

```bash
# Clone repository
git clone <repository-url>
cd fleet-tracking-system

# Copy environment files
cp fleet-tracking-backend/.env.example fleet-tracking-backend/.env
cp fleet-tracking-frontend/.env.example fleet-tracking-frontend/.env.local

# Edit environment files with your credentials
nano fleet-tracking-backend/.env
nano fleet-tracking-frontend/.env.local

# Start all services
docker-compose up -d

# Run database migrations
docker-compose exec backend php artisan migrate

# Seed initial data (optional)
docker-compose exec backend php artisan db:seed

# Access the application
# Frontend: http://localhost:3000
# Backend API: http://localhost:8000/api
```

### Option 2: Manual Setup

#### Backend Setup
```bash
cd fleet-tracking-backend

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fleet_tracking
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Start development server
php artisan serve

# Start queue worker (separate terminal)
php artisan queue:work redis --queue=default,gps,notifications

# Start scheduler (separate terminal)
php artisan schedule:work
```

#### Frontend Setup
```bash
cd fleet-tracking-frontend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env.local

# Configure API URL in .env.local
NEXT_PUBLIC_API_URL=http://localhost:8000/api

# Start development server
npm run dev
```

---

## Production Deployment

### Server Requirements

#### Minimum Specifications
- **CPU**: 4 vCPUs
- **RAM**: 8 GB
- **Storage**: 100 GB SSD
- **Bandwidth**: 1 Gbps

#### Recommended Specifications (for 10,000+ assets)
- **CPU**: 8 vCPUs
- **RAM**: 16 GB
- **Storage**: 500 GB SSD (with auto-scaling)
- **Bandwidth**: 10 Gbps

### Step 1: Server Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y \
    nginx \
    postgresql-15 \
    postgresql-15-postgis-3 \
    redis-server \
    php8.2-fpm \
    php8.2-cli \
    php8.2-pgsql \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-zip \
    php8.2-redis \
    supervisor \
    certbot \
    python3-certbot-nginx

# Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Step 2: Database Setup

```bash
# Switch to postgres user
sudo -u postgres psql

# Create database and user
CREATE DATABASE fleet_tracking;
CREATE USER fleet_user WITH ENCRYPTED PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE fleet_tracking TO fleet_user;

# Enable extensions
\c fleet_tracking
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_partman;

# Exit psql
\q

# Configure PostgreSQL for production
sudo nano /etc/postgresql/15/main/postgresql.conf

# Recommended settings:
max_connections = 200
shared_buffers = 4GB
effective_cache_size = 12GB
maintenance_work_mem = 1GB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
work_mem = 20MB
min_wal_size = 1GB
max_wal_size = 4GB

# Restart PostgreSQL
sudo systemctl restart postgresql
```

### Step 3: Deploy Backend

```bash
# Create application directory
sudo mkdir -p /var/www/fleet-tracking-backend
sudo chown -R $USER:$USER /var/www/fleet-tracking-backend

# Clone repository
cd /var/www/fleet-tracking-backend
git clone <repository-url> .

# Install dependencies
composer install --no-dev --optimize-autoloader

# Configure environment
cp .env.example .env
nano .env

# Set production values:
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.loopsfreight.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fleet_tracking
DB_USERNAME=fleet_user
DB_PASSWORD=secure_password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=redis_password

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Optimize application
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
sudo chown -R www-data:www-data /var/www/fleet-tracking-backend/storage
sudo chown -R www-data:www-data /var/www/fleet-tracking-backend/bootstrap/cache
```

### Step 4: Configure Nginx

```bash
sudo nano /etc/nginx/sites-available/fleet-tracking-backend

# Add configuration:
server {
    listen 80;
    server_name api.loopsfreight.com;
    root /var/www/fleet-tracking-backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# Enable site
sudo ln -s /etc/nginx/sites-available/fleet-tracking-backend /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Step 5: Configure Supervisor (Queue Workers)

```bash
sudo nano /etc/supervisor/conf.d/fleet-tracking-worker.conf

# Add configuration:
[program:fleet-tracking-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/fleet-tracking-backend/artisan queue:work redis --queue=default,gps,notifications --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/fleet-tracking-backend/storage/logs/worker.log
stopwaitsecs=3600

[program:fleet-tracking-scheduler]
process_name=%(program_name)s
command=php /var/www/fleet-tracking-backend/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/fleet-tracking-backend/storage/logs/scheduler.log

# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start fleet-tracking-worker:*
sudo supervisorctl start fleet-tracking-scheduler:*
```

### Step 6: Deploy Frontend

```bash
# Create application directory
sudo mkdir -p /var/www/fleet-tracking-frontend
sudo chown -R $USER:$USER /var/www/fleet-tracking-frontend

# Clone repository
cd /var/www/fleet-tracking-frontend
git clone <repository-url> .

# Install dependencies
npm ci

# Configure environment
cp .env.example .env.local
nano .env.local

# Set production values:
NEXT_PUBLIC_API_URL=https://api.loopsfreight.com/api
NEXT_PUBLIC_PUSHER_KEY=production_key
NEXT_PUBLIC_PUSHER_CLUSTER=mt1

# Build application
npm run build

# Install PM2 for process management
sudo npm install -g pm2

# Start application
pm2 start npm --name "fleet-tracking-frontend" -- start

# Configure PM2 to start on boot
pm2 startup
pm2 save
```

### Step 7: Configure Nginx for Frontend

```bash
sudo nano /etc/nginx/sites-available/fleet-tracking-frontend

# Add configuration:
server {
    listen 80;
    server_name dashboard.loopsfreight.com;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}

# Enable site
sudo ln -s /etc/nginx/sites-available/fleet-tracking-frontend /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## SSL/TLS Configuration

### Using Let's Encrypt (Free)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain SSL certificates
sudo certbot --nginx -d api.loopsfreight.com -d dashboard.loopsfreight.com

# Auto-renewal is configured automatically
# Test renewal:
sudo certbot renew --dry-run
```

---

## Monitoring & Logging

### Application Monitoring

#### Install Sentry (Error Tracking)
```bash
# Backend
composer require sentry/sentry-laravel

# Configure in .env
SENTRY_LARAVEL_DSN=https://...@sentry.io/...

# Frontend
npm install @sentry/nextjs

# Configure in next.config.js
```

#### Install New Relic (APM)
```bash
# Follow New Relic installation guide
# https://docs.newrelic.com/docs/apm/agents/php-agent/installation/php-agent-installation-overview/
```

### Log Management

```bash
# Configure log rotation
sudo nano /etc/logrotate.d/fleet-tracking

# Add configuration:
/var/www/fleet-tracking-backend/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}

# Test configuration
sudo logrotate -d /etc/logrotate.d/fleet-tracking
```

---

## Backup & Recovery

### Automated Database Backups

```bash
# Create backup script
sudo nano /usr/local/bin/backup-fleet-tracking.sh

#!/bin/bash
BACKUP_DIR="/backups/fleet-tracking"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

# Database backup
pg_dump -U fleet_user fleet_tracking | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Application files backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/fleet-tracking-backend/storage

# Upload to S3 (optional)
aws s3 cp $BACKUP_DIR/db_$DATE.sql.gz s3://fleet-tracking-backups/

# Delete backups older than 30 days
find $BACKUP_DIR -type f -mtime +30 -delete

# Make executable
sudo chmod +x /usr/local/bin/backup-fleet-tracking.sh

# Add to crontab (daily at 2 AM)
sudo crontab -e
0 2 * * * /usr/local/bin/backup-fleet-tracking.sh
```

### Recovery Procedure

```bash
# Stop application
pm2 stop fleet-tracking-frontend
sudo supervisorctl stop fleet-tracking-worker:*

# Restore database
gunzip < /backups/fleet-tracking/db_20240121_020000.sql.gz | psql -U fleet_user fleet_tracking

# Restore files
tar -xzf /backups/fleet-tracking/files_20240121_020000.tar.gz -C /

# Start application
pm2 start fleet-tracking-frontend
sudo supervisorctl start fleet-tracking-worker:*
```

---

## Scaling Strategies

### Horizontal Scaling (Multiple Servers)

#### Load Balancer Configuration (Nginx)
```nginx
upstream backend_servers {
    least_conn;
    server backend1.loopsfreight.com:8000;
    server backend2.loopsfreight.com:8000;
    server backend3.loopsfreight.com:8000;
}

server {
    listen 80;
    server_name api.loopsfreight.com;

    location / {
        proxy_pass http://backend_servers;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### Database Scaling (Read Replicas)

```bash
# Configure PostgreSQL streaming replication
# Primary server: /etc/postgresql/15/main/postgresql.conf
wal_level = replica
max_wal_senders = 3
wal_keep_size = 64

# Replica server: /etc/postgresql/15/main/recovery.conf
standby_mode = 'on'
primary_conninfo = 'host=primary.loopsfreight.com port=5432 user=replicator password=...'

# Laravel configuration (config/database.php)
'pgsql' => [
    'read' => [
        'host' => ['replica1.loopsfreight.com', 'replica2.loopsfreight.com'],
    ],
    'write' => [
        'host' => ['primary.loopsfreight.com'],
    ],
],
```

---

## Troubleshooting

### Common Issues

#### 1. Queue Jobs Not Processing
```bash
# Check supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart fleet-tracking-worker:*

# Check logs
tail -f /var/www/fleet-tracking-backend/storage/logs/worker.log
```

#### 2. High Database Load
```bash
# Check slow queries
sudo -u postgres psql fleet_tracking -c "SELECT query, calls, total_time FROM pg_stat_statements ORDER BY total_time DESC LIMIT 10;"

# Analyze and vacuum
sudo -u postgres psql fleet_tracking -c "VACUUM ANALYZE;"
```

#### 3. WebSocket Connection Issues
```bash
# Check Pusher configuration
php artisan config:cache

# Test WebSocket connection
php artisan pusher:test
```

---

## Security Checklist

- [ ] Change all default passwords
- [ ] Enable firewall (UFW)
- [ ] Configure fail2ban
- [ ] Set up SSL/TLS certificates
- [ ] Enable database encryption at rest
- [ ] Configure CORS properly
- [ ] Implement rate limiting
- [ ] Enable audit logging
- [ ] Set up intrusion detection (OSSEC)
- [ ] Regular security updates

---

## Performance Tuning

### PHP-FPM Optimization
```ini
# /etc/php/8.2/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

### Redis Optimization
```conf
# /etc/redis/redis.conf
maxmemory 2gb
maxmemory-policy allkeys-lru
```

### PostgreSQL Optimization
```sql
-- Create indexes for frequently queried columns
CREATE INDEX CONCURRENTLY idx_gps_logs_asset_timestamp ON gps_logs(asset_id, timestamp);
CREATE INDEX CONCURRENTLY idx_subscriptions_end_date ON subscriptions(end_date) WHERE status = 'active';
```

---

**Document Version**: 1.0  
**Last Updated**: 2024-01-21  
**Deployment Target**: Production  
**Estimated Setup Time**: 4-6 hours
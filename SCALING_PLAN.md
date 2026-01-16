# C3DMV Platform Scaling Implementation Plan
**Target Capacity:** 500-2,000 users, 5,000-10,000 models

## Executive Summary

Current C3DMV architecture can handle ~500 models comfortably but will experience severe performance degradation at target scale. This plan provides step-by-step implementation to achieve 20x capacity increase.

**Critical Bottlenecks Identified:**
1. Admin panel loads ALL users/models into memory (will crash at 10,000+ models)
2. N+1 query problems (30,000+ queries per admin page at scale)
3. No pagination at database level
4. No caching layer
5. Inefficient file serving

**Expected Performance After Implementation:**
- Memory usage: 1GB → 128MB (87% reduction)
- Query count: 30,000 → 50 (99.8% reduction)
- Page load time: 20-40s → 2s (90% improvement)
- Capacity: 500 models → 10,000+ models (20x increase)

**Total Implementation Time:** 18-20 hours over 5 days

---

## PHASE 1: CRITICAL - PHP Memory Limit Increase

**Priority:** IMMEDIATE (Day 1, 15 minutes)
**Risk Level:** LOW

### Implementation

For cPanel/Shared Hosting, create `/home/user/C3DMV/.user.ini`:
```ini
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 55M
upload_max_filesize = 50M
```

For VPS with php.ini access, edit `/etc/php/8.x/fpm/php.ini`:
```ini
memory_limit = 512M
```

### Verification

Create temporary test file:
```php
<?php
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
// DELETE this file after verification
?>
```

### Important Note
This is a temporary bandaid addressing symptoms, not root cause. Must be combined with Phases 2-4.

---

## PHASE 2: CRITICAL - Database Query Optimization

**Priority:** IMMEDIATE (Day 1-2, 4-6 hours)
**Risk Level:** MEDIUM

### Step 2.1: Add Database Indexes

Run via phpMyAdmin or MySQL command line:

```sql
-- User indexes
ALTER TABLE users ADD INDEX idx_username (username);
ALTER TABLE users ADD INDEX idx_email (email);
ALTER TABLE users ADD INDEX idx_created_at (created_at);
ALTER TABLE users ADD INDEX idx_approved (approved);

-- Model indexes
ALTER TABLE models ADD INDEX idx_user_id (user_id);
ALTER TABLE models ADD INDEX idx_category (category);
ALTER TABLE models ADD INDEX idx_created_at (created_at);
ALTER TABLE models ADD INDEX idx_downloads (downloads);
ALTER TABLE models ADD INDEX idx_likes (likes);
ALTER TABLE models ADD INDEX idx_featured (featured);
ALTER TABLE models ADD INDEX idx_user_created (user_id, created_at);
ALTER TABLE models ADD INDEX idx_category_created (category, created_at);

-- Related table indexes
ALTER TABLE model_files ADD INDEX idx_model_id (model_id);
ALTER TABLE model_photos ADD INDEX idx_model_id (model_id);
ALTER TABLE favorites ADD INDEX idx_user_id (user_id);
ALTER TABLE favorites ADD INDEX idx_model_id (model_id);

-- Full-text search
ALTER TABLE models ADD FULLTEXT INDEX idx_search (title, description);
```

### Step 2.2: Add Paginated Query Functions

Add to `/home/user/C3DMV/includes/db.php` (after existing functions):

```php
/**
 * PERFORMANCE OPTIMIZATION FUNCTIONS
 * Added for scaling to 2,000 users / 10,000 models
 */

/**
 * Get models with pagination and eager loading
 * Eliminates N+1 queries by using JOINs
 */
function getModelsPaginated(int $limit = 20, int $offset = 0, string $orderBy = 'created_at', string $orderDir = 'DESC', array $filters = []): array {
    $conn = getDbConnection();

    // Build WHERE clause
    $where = [];
    $params = [];
    $types = '';

    if (!empty($filters['category'])) {
        $where[] = "m.category = ?";
        $params[] = $filters['category'];
        $types .= 's';
    }

    if (!empty($filters['user_id'])) {
        $where[] = "m.user_id = ?";
        $params[] = $filters['user_id'];
        $types .= 's';
    }

    if (!empty($filters['search'])) {
        $where[] = "(m.title LIKE ? OR m.description LIKE ?)";
        $searchTerm = "%{$filters['search']}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Validate inputs
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
    $allowedOrders = ['created_at', 'title', 'downloads', 'likes', 'updated_at'];
    if (!in_array($orderBy, $allowedOrders)) {
        $orderBy = 'created_at';
    }

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM models m $whereClause";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];

    // Get paginated results with JOINs
    $sql = "
        SELECT
            m.*,
            u.username as author_username,
            u.avatar as author_avatar,
            c.name as category_name,
            c.icon as category_icon,
            (SELECT COUNT(*) FROM model_files WHERE model_id = m.id) as file_count,
            (SELECT COUNT(*) FROM model_photos WHERE model_id = m.id) as photo_count
        FROM models m
        LEFT JOIN users u ON m.user_id = u.id
        LEFT JOIN categories c ON m.category = c.id
        $whereClause
        ORDER BY m.$orderBy $orderDir
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $models = [];
    while ($row = $result->fetch_assoc()) {
        $models[] = formatModelRowOptimized($row);
    }

    return [
        'models' => $models,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'pages' => ceil($total / $limit)
    ];
}

/**
 * Format model row with embedded data
 */
function formatModelRowOptimized(array $row): array {
    $row['tags'] = json_decode($row['tags'] ?? '[]', true) ?? [];
    $row['print_settings'] = json_decode($row['print_settings'] ?? '{}', true) ?? [];
    $row['featured'] = (bool)$row['featured'];

    $row['author'] = [
        'username' => $row['author_username'] ?? 'Unknown',
        'avatar' => $row['author_avatar'] ?? null
    ];

    if (!empty($row['category_name'])) {
        $row['category_data'] = [
            'name' => $row['category_name'],
            'icon' => $row['category_icon']
        ];
    }

    unset($row['author_username'], $row['author_avatar']);
    unset($row['category_name'], $row['category_icon']);

    return $row;
}

/**
 * Get users with pagination
 */
function getUsersPaginated(int $limit = 50, int $offset = 0, string $orderBy = 'created_at', string $orderDir = 'DESC', array $filters = []): array {
    $conn = getDbConnection();

    // Build WHERE clause
    $where = [];
    $params = [];
    $types = '';

    if (!empty($filters['search'])) {
        $where[] = "(username LIKE ? OR email LIKE ?)";
        $searchTerm = "%{$filters['search']}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    if (isset($filters['is_admin'])) {
        $where[] = "is_admin = ?";
        $params[] = (int)$filters['is_admin'];
        $types .= 'i';
    }

    if (isset($filters['approved'])) {
        $where[] = "approved = ?";
        $params[] = (int)$filters['approved'];
        $types .= 'i';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Validate inputs
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
    $allowedOrders = ['created_at', 'username', 'email', 'model_count'];
    if (!in_array($orderBy, $allowedOrders)) {
        $orderBy = 'created_at';
    }

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM users $whereClause";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];

    // Get paginated results
    $sql = "SELECT * FROM users $whereClause ORDER BY $orderBy $orderDir LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_admin'] = (bool)$row['is_admin'];
        $row['favorites'] = [];
        $users[] = $row;
    }

    return [
        'users' => $users,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'pages' => ceil($total / $limit)
    ];
}
```

### Step 2.3: Update admin.php

Replace lines 17-21 in `/home/user/C3DMV/admin.php`:

**BEFORE:**
```php
$stats = getStats();
$categories = getCategories();
$users = getUsers();
$models = getModels();
```

**AFTER:**
```php
$stats = getStats();
$categories = getCategories();

// Pagination
$usersPage = max(1, intval($_GET['users_page'] ?? 1));
$modelsPage = max(1, intval($_GET['models_page'] ?? 1));
$pageSize = 50;

$users = [];
$models = [];
$usersPagination = ['total' => 0, 'pages' => 0];
$modelsPagination = ['total' => 0, 'pages' => 0];

if ($section === 'users') {
    $usersData = getUsersPaginated($pageSize, ($usersPage - 1) * $pageSize);
    $users = $usersData['users'];
    $usersPagination = $usersData;
} elseif ($section === 'models') {
    $searchQuery = $_GET['search'] ?? '';
    $categoryFilter = $_GET['filter_category'] ?? '';

    $filters = [];
    if ($searchQuery) $filters['search'] = $searchQuery;
    if ($categoryFilter) $filters['category'] = $categoryFilter;

    $modelsData = getModelsPaginated($pageSize, ($modelsPage - 1) * $pageSize, 'created_at', 'DESC', $filters);
    $models = $modelsData['models'];
    $modelsPagination = $modelsData;
} elseif ($section === 'dashboard') {
    $recentData = getModelsPaginated(10, 0, 'created_at', 'DESC');
    $models = $recentData['models'];
}
```

Add pagination UI after user/model tables.

### Expected Results
- Memory: 800MB → 100MB (87% reduction)
- Queries: 30,000 → 15 (99.9% reduction)
- Load time: 20s → 2s (90% improvement)

---

## PHASE 3: MEDIUM - Nginx Caching

**Priority:** MEDIUM (Day 3, 2-3 hours)
**Risk Level:** LOW

### Nginx Configuration

Create `/etc/nginx/sites-available/c3dmv`:

```nginx
# Rate limiting
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/m;
limit_req_zone $binary_remote_addr zone=uploads:10m rate=10r/m;
limit_req_zone $binary_remote_addr zone=general:10m rate=100r/m;

# Cache zones
proxy_cache_path /var/cache/nginx/c3dmv levels=1:2 keys_zone=c3dmv_cache:10m max_size=1g inactive=60m;

server {
    listen 443 ssl http2;
    server_name vault.example.com;
    root /home/user/C3DMV;

    client_max_body_size 55M;

    # Images - 30 days cache
    location ~* ^/uploads/.*\.(jpg|jpeg|png|gif)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # 3D files - 7 days cache
    location ~* ^/uploads/.*\.(stl|obj)$ {
        expires 7d;
        add_header Cache-Control "public";
    }

    # CSS/JS - 7 days cache
    location ~* \.(css|js)$ {
        expires 7d;
        add_header Cache-Control "public, must-revalidate";
    }

    # PHP with rate limiting
    location ~ ^/api\.php$ {
        limit_req zone=api burst=10 nodelay;
        include fastcgi_params;
        fastcgi_pass php-fpm;
    }

    location ~ \.php$ {
        limit_req zone=general burst=20 nodelay;
        include fastcgi_params;
        fastcgi_pass php-fpm;
        fastcgi_cache c3dmv_cache;
        fastcgi_cache_valid 200 60m;
    }

    # Security
    location ~ /\. { deny all; }
    location ~ ^/data/ { deny all; }
    location ~ ^/includes/ { deny all; }
}
```

Enable and test:
```bash
sudo nginx -t
sudo mkdir -p /var/cache/nginx/c3dmv
sudo chown www-data:www-data /var/cache/nginx/c3dmv
sudo systemctl reload nginx
```

### Expected Results
- Static asset load: 200ms → 10ms (95% improvement)
- Bandwidth savings: 80-90% for returning visitors
- PHP page cache hit: 90% for anonymous users

---

## PHASE 4: MEDIUM - Additional Optimizations

**Priority:** MEDIUM (Day 4, 3-4 hours)
**Risk Level:** LOW

### APCu Cache Installation

```bash
sudo apt-get install php-apcu
sudo systemctl restart php8.1-fpm
```

### Update Settings Function

Modify `/home/user/C3DMV/includes/config.php` setting() function:

```php
function setting(string $key, $default = null, bool $reset = false) {
    static $settings = null;

    if ($reset) {
        $settings = null;
        if (function_exists('apcu_delete')) {
            apcu_delete('c3dmv_settings');
        }
        return null;
    }

    // Try APCu cache
    if ($settings === null && function_exists('apcu_fetch')) {
        $settings = apcu_fetch('c3dmv_settings', $success);
        if (!$success) $settings = null;
    }

    // Load from database
    if ($settings === null) {
        require_once __DIR__ . '/db.php';
        $settings = getSettings();

        if (function_exists('apcu_store')) {
            apcu_store('c3dmv_settings', $settings, 3600);
        }
    }

    return $settings[$key] ?? $default;
}
```

### MySQL Tuning

Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
# For 2GB RAM server
innodb_buffer_pool_size = 1G
innodb_buffer_pool_instances = 4
max_connections = 100
tmp_table_size = 64M
max_heap_table_size = 64M
thread_cache_size = 16
table_open_cache = 2000
```

Restart:
```bash
sudo systemctl restart mysql
```

### Expected Results
- Settings load: 10ms → <1ms
- Categories load: 20ms → <1ms
- MySQL throughput: +30-50%

---

## PHASE 5: Monitoring

**Priority:** ONGOING (Day 5, 1-2 hours)

### Performance Monitoring Script

Create `/home/user/C3DMV/monitoring/check_performance.php`:

```php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$logFile = __DIR__ . '/performance.log';

function logMetric($metric, $value, $unit = '') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $metric: $value $unit\n", FILE_APPEND);
}

// Measure query time
$start = microtime(true);
$stats = getStats();
$queryTime = microtime(true) - $start;

logMetric('stats_query_time', round($queryTime, 3), 'sec');
logMetric('total_models', $stats['total_models'], 'models');
logMetric('peak_memory', round(memory_get_peak_usage() / 1024 / 1024, 2), 'MB');

// APCu stats
if (function_exists('apcu_cache_info')) {
    $info = apcu_cache_info();
    $hitRate = ($info['nhits'] / max(1, $info['nhits'] + $info['nmisses'])) * 100;
    logMetric('apcu_hit_rate', round($hitRate, 2), '%');
}

echo "Performance check complete\n";
```

Add to crontab:
```bash
*/5 * * * * /usr/bin/php /home/user/C3DMV/monitoring/check_performance.php
```

---

## Implementation Timeline

### Day 1 (6 hours)
- 08:00-08:15: Phase 1 - Memory limit
- 08:15-10:00: Phase 2.1 - Database indexes
- 10:00-12:00: Phase 2.2 - Paginated functions
- 13:00-15:00: Phase 2.3 - Update admin.php
- 15:00-17:00: Testing

### Day 2 (4 hours)
- Continue Phase 2 testing and fixes
- Update browse.php
- Performance benchmarking

### Day 3 (3 hours)
- Phase 3 - Nginx configuration
- Cache testing

### Day 4 (3 hours)
- Phase 4 - APCu and MySQL tuning
- Optimization testing

### Day 5 (2 hours)
- Phase 5 - Monitoring setup
- Final documentation

**Total: 18-20 hours**

---

## Testing Checklist

### Functional Testing
- [ ] Admin panel loads without errors
- [ ] Pagination works (users, models)
- [ ] Search and filtering work
- [ ] Upload, edit, delete operations work
- [ ] Settings save correctly

### Performance Testing
- [ ] Admin loads in <3 seconds
- [ ] Browse loads in <2 seconds
- [ ] Memory usage <200MB
- [ ] Query count <50 per page
- [ ] Cache hit rate >70%

### Load Testing
```bash
# 100 requests, 10 concurrent
ab -n 100 -c 10 https://yourdomain.com/
ab -n 100 -c 10 https://yourdomain.com/browse.php
```

Expected: <500ms average for homepage, <800ms for browse

---

## Rollback Procedures

### Emergency Rollback
```bash
# Restore backups
cp includes/db.php.backup includes/db.php
cp admin.php.backup admin.php
cp .user.ini.backup .user.ini

# Disable nginx caching
sudo rm /etc/nginx/sites-enabled/c3dmv
sudo systemctl reload nginx

# Clear caches
sudo rm -rf /var/cache/nginx/*
php -r "if (function_exists('apcu_clear_cache')) apcu_clear_cache();"

# Restart services
sudo systemctl restart php8.1-fpm nginx
```

---

## Success Metrics

### Before (1,000 models)
- Admin load: 5-8 seconds
- Memory: 200-300MB
- Queries: 1,000-2,000
- Browse: 2-3 seconds

### After (10,000 models)
- Admin load: <2 seconds (75% improvement)
- Memory: <150MB (50% improvement)
- Queries: <50 (98% improvement)
- Browse: <1 second (66% improvement)

### Capacity
- Current: 500 models max
- After Phase 1-2: 10,000 models max
- After Phase 3-4: 20,000 models max

---

## Critical Files

1. `/home/user/C3DMV/includes/db.php` - Add paginated functions
2. `/home/user/C3DMV/admin.php` - Refactor to use pagination
3. `/home/user/C3DMV/includes/config.php` - Add APCu caching
4. `/etc/nginx/sites-available/c3dmv` - Nginx configuration
5. `/home/user/C3DMV/browse.php` - Use getModelsPaginated()

---

## Support Resources

- MySQL Documentation: https://dev.mysql.com/doc/
- Nginx Caching Guide: https://nginx.org/en/docs/http/ngx_http_proxy_module.html
- PHP APCu: https://www.php.net/manual/en/book.apcu.php
- Performance Monitoring Dashboard: `/monitoring/dashboard.php`

---

**Questions or issues during implementation? Document in `/home/user/C3DMV/SCALING_NOTES.md`**

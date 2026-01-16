# Community 3D Model Vault

A full-featured 3D model sharing platform built with PHP and Three.js. Designed for communities, makerspaces, and organizations to share STL and OBJ files for 3D printing.

## Core Features

### Model Management
- Multi-file model uploads (up to 10 files per model)
- Multi-photo galleries (up to 5 photos per model)
- Drag-and-drop upload interface with live 3D preview
- Support for STL and OBJ formats
- Configurable file size limits (default 50MB)
- Primary display selector (3D render, photo, or auto)
- Bulk download as ZIP archive
- Model statistics tracking (downloads, likes, views)
- Tag-based organization
- Print settings metadata
- License assignment (CC BY, CC BY-NC, CC0, MIT, GPL, etc.)

### Interactive 3D Viewer
- WebGL-based rendering with Three.js
- 20+ color presets (neon, standard, metallic palettes)
- Custom color picker with HEX input
- Orbit controls (rotate, zoom, pan)
- Auto-rotation toggle
- Wireframe mode
- Grid display
- Screenshot capture
- Fullscreen mode
- Enhanced lighting system (ambient, hemisphere, directional, point lights)
- Environment mapping for reflections
- Keyboard shortcuts (arrow keys, R, W, F, S)
- Unified gallery navigation between photos and 3D files

### Search and Discovery
- Full-text search across titles and descriptions
- Category-based filtering
- Multi-criteria sorting (newest, oldest, most downloaded, most liked)
- Pagination with configurable page size
- Tag-based filtering
- User profile browsing

### User System
- Registration with username/email validation
- Secure password hashing (bcrypt)
- Session-based authentication
- User profiles with:
  - Bio, location, website
  - Social links (Twitter, GitHub)
  - Avatar uploads
  - Model galleries
  - Download statistics
- Favorites system
- Like tracking (session-based anti-spam)
- Admin role management

### Admin Dashboard
Comprehensive administration interface at `/admin.php`:

#### Dashboard
- Platform statistics (models, users, downloads, categories)
- Recent activity feed
- Quick access to all management sections

#### Category Management
- Create, edit, delete categories
- Font Awesome icon selection
- Usage statistics (model count per category)
- Bulk operations

#### User Management
- List all users with search/filter
- Role assignment (admin/user toggle)
- User deletion with cascade (removes all models)
- View user profiles and statistics
- Manual approval system

#### Model Management
- View all uploaded models
- Search by title or author
- Filter by category
- Edit model metadata (title, description, category, tags, license)
- Delete models with file cleanup

#### Settings System
Organized into six categories:

**Site Configuration**
- Site name and tagline
- Description for SEO
- Contact email
- Maintenance mode with custom message

**Users & Registration**
- Allow/disable public registration
- Require admin approval for new users
- Email verification toggle
- Default user role

**Upload Settings**
- Max file size (1-500MB)
- Max files per model (1-50)
- Max photos per model (1-20)
- Allowed file extensions (comma-separated)

**Feature Toggles**
- Enable/disable downloads
- Enable/disable likes
- Enable/disable favorites
- Enable/disable comments (planned)
- Enable/disable user profiles

**Display Settings**
- Items per page (6, 12, 24, 48)
- Default sort order
- Default license for uploads
- Show/hide download counts
- Show/hide like counts
- Show/hide view counts

**3D Viewer Settings**
- Default model color (color picker)
- Auto-rotation default state
- Wireframe toggle visibility
- Grid display default state

#### Pending Users
- Review registration queue when approval is enabled
- Approve or reject pending users
- Bypass mechanism via invite codes

#### Invite Code System
- Generate unique 8-character codes
- Configurable expiration (days)
- Usage limits (1 to unlimited)
- Active/inactive toggle
- Usage tracking
- Private notes for each code
- Copy code or full registration URL
- Bypasses admin approval requirement

## Tech Stack

### Backend
- PHP 7.4+ (procedural with functional patterns)
- MySQL 5.7+ or JSON file storage (hybrid system)
- Session-based authentication
- Database abstraction layer with automatic MySQL/JSON detection

### Frontend
- HTML5, CSS3, Vanilla JavaScript
- Three.js r128 (WebGL 3D rendering)
- Font Awesome 6.5.1 (icons)
- Google Fonts (Orbitron, Exo 2)
- Custom UI components (modals, toasts, forms)

### Architecture
- Hybrid storage: MySQL for production, JSON for development
- Single-file database abstraction (`includes/db.php`)
- RESTful API design (`api.php`)
- Server-side rendering with AJAX enhancements
- No external frameworks (zero dependencies except CDN resources)

## Requirements

### Minimum
- PHP 7.4 or higher
- Web server (Apache, nginx, or PHP built-in server)
- Writable `data/` directory (for JSON storage)
- Writable `uploads/` directory (for file storage)

### Recommended (Production)
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.2+
- HTTPS with valid SSL certificate
- 2GB RAM minimum
- Fast storage (SSD preferred)

### PHP Extensions
- `mysqli` (for MySQL support)
- `gd` or `imagick` (for image processing)
- `zip` (for archive downloads)
- `json` (usually enabled by default)
- `mbstring` (for string handling)

## Installation

### Development Setup (JSON Storage)

1. Clone repository:
```bash
git clone https://github.com/your-org/C3DMV.git /var/www/html/C3DMV
cd /var/www/html/C3DMV
```

2. Set permissions:
```bash
chmod 755 .
chmod 775 data
chmod 775 uploads
```

3. Start PHP development server:
```bash
php -S localhost:8000
```

4. Access the application:
```
http://localhost:8000/
```

5. Default admin credentials:
```
Username: admin
Password: admin123
```

6. Change admin password immediately:
   - Edit `data/users.json`
   - Replace password hash with output from:
   ```php
   php -r "echo password_hash('your_new_password', PASSWORD_DEFAULT);"
   ```

### Production Setup (MySQL Storage)

1. Create MySQL database:
```sql
CREATE DATABASE c3dmv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'c3dmv_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON c3dmv.* TO 'c3dmv_user'@'localhost';
FLUSH PRIVILEGES;
```

2. Configure database connection:
```bash
cp includes/db_config.sample.php includes/db_config.php
nano includes/db_config.php
```

Update with your credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'c3dmv_user');
define('DB_PASS', 'secure_password');
define('DB_NAME', 'c3dmv');
```

3. Run database setup:
```
http://your-domain.com/setup_database.php
```

4. (Optional) Migrate existing JSON data:
```
http://your-domain.com/migrate_json_to_mysql.php
```

5. Secure setup files:
```bash
# Move setup scripts outside web root or delete them
rm setup_database.php migrate_json_to_mysql.php
```

6. Configure web server (Apache example):
```apache
<VirtualHost *:443>
    ServerName vault.example.com
    DocumentRoot /var/www/html/C3DMV

    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem

    <Directory /var/www/html/C3DMV>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Increase upload limits
    php_value upload_max_filesize 50M
    php_value post_max_size 50M
    php_value max_execution_time 300
</VirtualHost>
```

7. Set file permissions:
```bash
chown -R www-data:www-data /var/www/html/C3DMV
chmod 750 /var/www/html/C3DMV
chmod 770 /var/www/html/C3DMV/uploads
chmod 770 /var/www/html/C3DMV/data
find /var/www/html/C3DMV -type f -exec chmod 640 {} \;
```

## API Reference

All API calls are POST/GET requests to `api.php` with an `action` parameter.

### Authentication

| Endpoint | Method | Parameters | Description |
|----------|--------|------------|-------------|
| `login` | POST | username, password | Authenticate user, returns user object |
| `register` | POST | username, email, password, code (optional) | Create account, auto-login on success |
| `logout` | POST | - | Destroy session |
| `check_auth` | GET | - | Return current session state |

### Models

| Endpoint | Method | Parameters | Description |
|----------|--------|------------|-------------|
| `get_models` | GET | query, category, sort, page, limit | Search/filter models with pagination |
| `get_model` | GET | id | Get single model with author info |
| `upload_model` | POST | title, category, model_file, description, tags (JSON), license, print_settings (JSON) | Upload new model |
| `update_model` | POST | id, title, description, category, tags (JSON), license, primary_display | Update model metadata |
| `delete_model` | POST | id | Delete model (owner or admin only) |
| `add_model_file` | POST | model_id, file | Add file to existing model |
| `remove_model_file` | POST | model_id, filename | Remove file from model |
| `add_model_photo` | POST | model_id, photo | Upload photo to model gallery |
| `remove_model_photo` | POST | model_id, filename | Remove photo from gallery |
| `download_model` | GET/POST | id | Track download, return file URL |
| `download_model_zip` | GET/POST | id | Generate and serve ZIP archive |
| `like_model` | POST | id | Increment like counter |
| `check_liked` | GET | id | Check if user liked model |
| `favorite_model` | POST | id | Toggle favorite status |

### Categories (Admin Only)

| Endpoint | Method | Parameters | Description |
|----------|--------|------------|-------------|
| `get_categories` | GET | - | List all categories |
| `create_category` | POST | name, icon, description | Create new category |
| `update_category` | POST | id, name, icon, description | Update category |
| `delete_category` | POST | id | Delete category (must be empty) |

### Users

| Endpoint | Method | Parameters | Description |
|----------|--------|------------|-------------|
| `get_users` | GET | - | List all users (admin only) |
| `get_user` | GET | id | Get user profile with models |
| `update_user` | POST | id, bio, location, website, twitter, github, is_admin (admin only) | Update user profile |
| `upload_avatar` | POST | avatar | Upload user avatar (2MB max) |
| `delete_user` | POST | id | Delete user and cascade models (admin only) |

### Statistics

| Endpoint | Method | Parameters | Description |
|----------|--------|------------|-------------|
| `get_stats` | GET | - | Platform statistics (models, users, downloads, categories) |

### Response Format

All API responses are JSON:

```json
{
    "success": true,
    "data": { ... },
    "error": null
}
```

Error response:
```json
{
    "success": false,
    "error": "Error message"
}
```

## Database Schema

### MySQL Tables

**users**
```sql
CREATE TABLE users (
    id VARCHAR(32) PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    approved TINYINT(1) DEFAULT 1,
    avatar VARCHAR(255),
    bio TEXT,
    location VARCHAR(100),
    website VARCHAR(255),
    twitter VARCHAR(100),
    github VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    model_count INT DEFAULT 0,
    download_count INT DEFAULT 0,
    INDEX idx_username (username),
    INDEX idx_email (email)
);
```

**models**
```sql
CREATE TABLE models (
    id VARCHAR(32) PRIMARY KEY,
    user_id VARCHAR(32) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    tags JSON,
    filename VARCHAR(255) NOT NULL,
    filesize BIGINT NOT NULL,
    file_count INT DEFAULT 1,
    thumbnail VARCHAR(255),
    photo VARCHAR(255),
    primary_display VARCHAR(20) DEFAULT 'auto',
    license VARCHAR(50) DEFAULT 'CC BY-NC',
    print_settings JSON,
    downloads INT DEFAULT 0,
    likes INT DEFAULT 0,
    views INT DEFAULT 0,
    featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_category (category),
    INDEX idx_created (created_at),
    INDEX idx_downloads (downloads),
    INDEX idx_likes (likes),
    INDEX idx_featured (featured),
    FULLTEXT idx_search (title, description)
);
```

**model_files**
```sql
CREATE TABLE model_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_id VARCHAR(32) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    filesize BIGINT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    extension VARCHAR(10) NOT NULL,
    has_color TINYINT(1) DEFAULT 0,
    file_order INT DEFAULT 0,
    FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE CASCADE,
    INDEX idx_model (model_id)
);
```

**model_photos**
```sql
CREATE TABLE model_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_id VARCHAR(32) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    photo_order INT DEFAULT 0,
    FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE CASCADE,
    INDEX idx_model (model_id)
);
```

**categories**
```sql
CREATE TABLE categories (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT 'fa-cube',
    description TEXT,
    count INT DEFAULT 0
);
```

**favorites**
```sql
CREATE TABLE favorites (
    user_id VARCHAR(32) NOT NULL,
    model_id VARCHAR(32) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, model_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE CASCADE
);
```

**settings**
```sql
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**invites**
```sql
CREATE TABLE invites (
    id VARCHAR(32) PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    created_by VARCHAR(32) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    max_uses INT DEFAULT 1,
    uses INT DEFAULT 0,
    note VARCHAR(255) DEFAULT '',
    active TINYINT(1) DEFAULT 1,
    INDEX idx_code (code),
    INDEX idx_active (active)
);
```

### JSON Storage Structure

When MySQL is not configured, data is stored in JSON files:

**data/users.json**
```json
[
    {
        "id": "admin",
        "username": "admin",
        "email": "admin@example.com",
        "password": "$2y$10$...",
        "is_admin": true,
        "approved": true,
        "avatar": null,
        "bio": "",
        "location": "",
        "website": "",
        "twitter": "",
        "github": "",
        "created_at": "2024-01-01 00:00:00",
        "model_count": 0,
        "download_count": 0,
        "favorites": []
    }
]
```

**data/models.json**
```json
[
    {
        "id": "abc123def456",
        "user_id": "admin",
        "title": "Sample Model",
        "description": "Description here",
        "category": "arcade-parts",
        "tags": ["tag1", "tag2"],
        "files": [
            {
                "filename": "unique_model.stl",
                "filesize": 1024000,
                "original_name": "model",
                "extension": "stl",
                "has_color": false,
                "file_order": 0
            }
        ],
        "photos": ["photo_xyz.jpg"],
        "primary_display": "auto",
        "license": "CC BY-NC",
        "print_settings": {},
        "downloads": 0,
        "likes": 0,
        "views": 0,
        "featured": false,
        "created_at": "2024-01-01 00:00:00",
        "updated_at": "2024-01-01 00:00:00"
    }
]
```

**data/categories.json**
```json
[
    {
        "id": "arcade-parts",
        "name": "Arcade Parts",
        "icon": "fa-gamepad",
        "description": "Buttons, joysticks, bezels, and arcade cabinet components",
        "count": 0
    }
]
```

**data/settings.json**
```json
{
    "site_name": "Community 3D Model Vault",
    "allow_registration": true,
    "max_file_size": 50,
    "enable_downloads": true
}
```

**data/invites.json**
```json
[
    {
        "id": "inv123",
        "code": "ABC12345",
        "created_by": "admin",
        "created_at": "2024-01-01 00:00:00",
        "expires_at": "2024-01-08 00:00:00",
        "max_uses": 1,
        "uses": 0,
        "note": "For John",
        "active": true
    }
]
```

## Security Features

### Implemented Protections
1. **Password Security**: bcrypt hashing with `password_hash()`
2. **CSRF Protection**: Token-based validation on all forms
3. **SQL Injection Prevention**: Prepared statements throughout
4. **Input Sanitization**: `htmlspecialchars()` with ENT_QUOTES on all outputs
5. **File Upload Validation**: Extension whitelist, size limits
6. **Session Security**: Server-side session management
7. **Access Control**: Role-based authorization, ownership verification
8. **Timing-Safe Comparison**: `hash_equals()` for token verification

### Known Limitations
1. No HTTPS enforcement (must be configured at web server level)
2. No rate limiting (implement at reverse proxy or application level)
3. No email verification (planned feature)
4. Basic password requirements (6 characters minimum)
5. No two-factor authentication
6. No password reset mechanism
7. No account lockout after failed logins
8. Session IDs not regenerated after login

### Production Hardening Checklist

**Critical (Do Before Production)**
- [ ] Enable HTTPS and enforce SSL/TLS
- [ ] Change default admin password
- [ ] Configure strong database credentials
- [ ] Set restrictive file permissions (750/640)
- [ ] Remove or move setup scripts outside web root
- [ ] Configure CSP headers
- [ ] Implement rate limiting (fail2ban, nginx limit_req, etc.)
- [ ] Enable error logging, disable display_errors
- [ ] Configure session security (httponly, secure, samesite cookies)
- [ ] Review all SQL queries for injection vulnerabilities
- [ ] Implement backup strategy

**Recommended**
- [ ] Enable email verification
- [ ] Implement password reset flow
- [ ] Add account lockout after N failed logins
- [ ] Configure CDN for static assets
- [ ] Set up monitoring (uptime, errors, performance)
- [ ] Implement CAPTCHA on registration
- [ ] Add file virus scanning
- [ ] Configure automated backups
- [ ] Set up log aggregation
- [ ] Implement MIME type verification for uploads

**Optional Enhancements**
- [ ] Add two-factor authentication
- [ ] Implement OAuth providers (Google, GitHub)
- [ ] Add API rate limiting per user
- [ ] Configure Redis for session storage
- [ ] Implement job queue for file processing
- [ ] Add full-text search with Elasticsearch
- [ ] Configure CDN for uploads
- [ ] Implement image optimization pipeline

## Configuration

### PHP Configuration
Edit `php.ini` or use `.htaccess` / web server config:

```ini
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
memory_limit = 256M
session.cookie_httponly = 1
session.cookie_secure = 1  # Enable for HTTPS
session.cookie_samesite = Strict
```

### Application Settings
Configure via Admin Dashboard → Settings or edit:
- **MySQL**: `settings` table
- **JSON**: `data/settings.json`

Available settings documented in `includes/db.php` function `getDefaultSettings()`.

### Storage Configuration
The application automatically detects MySQL availability:
- If `includes/db_config.php` exists and MySQL is reachable: uses MySQL
- Otherwise: falls back to JSON storage in `data/` directory

Force JSON mode by removing or renaming `includes/db_config.php`.

## File Structure

```
C3DMV/
├── index.php                    # Homepage with trending/recent models
├── browse.php                   # Browse, search, and filter models
├── model.php                    # Single model view with 3D viewer and gallery
├── upload.php                   # Multi-file upload interface with live preview
├── login.php                    # Authentication (login/register with invite codes)
├── profile.php                  # User profiles with stats and model galleries
├── admin.php                    # Admin dashboard with 7 management sections
├── api.php                      # REST-like API endpoints (30+ actions)
├── logout.php                   # Session destruction handler
│
├── includes/
│   ├── config.php               # Configuration, helpers, settings cache
│   ├── db.php                   # Database abstraction (MySQL/JSON hybrid)
│   ├── db_config.sample.php     # MySQL configuration template
│   └── maintenance.php          # Maintenance mode template
│
├── css/
│   └── style.css                # Main stylesheet (6,287 lines, comprehensive)
│
├── js/
│   └── app.js                   # JavaScript (1,752 lines, 3D viewer, API, UI)
│
├── data/                        # JSON storage fallback
│   ├── users.json
│   ├── models.json
│   ├── categories.json
│   ├── settings.json
│   └── invites.json
│
├── uploads/                     # Uploaded files and images
│   ├── *.stl                   # Model files
│   ├── *.obj                   # Model files
│   ├── photo_*.jpg             # Model photos
│   └── avatar_*.jpg            # User avatars
│
├── setup_database.php           # MySQL schema setup script
├── migrate_json_to_mysql.php    # JSON to MySQL migration utility
├── cleanup_duplicates.php       # Maintenance utility
├── DATABASE_MIGRATION.md        # MySQL deployment guide
└── README.md                    # This file
```

## Deployment

### cPanel Deployment
See `DATABASE_MIGRATION.md` for detailed cPanel-specific instructions.

### Docker Deployment
Example `docker-compose.yml`:

```yaml
version: '3.8'

services:
  web:
    image: php:8.0-apache
    volumes:
      - ./:/var/www/html
    ports:
      - "8080:80"
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpass
      MYSQL_DATABASE: c3dmv
      MYSQL_USER: c3dmv_user
      MYSQL_PASSWORD: c3dmv_pass
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

### Nginx Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name vault.example.com;
    root /var/www/html/C3DMV;
    index index.php;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|stl|obj)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\. {
        deny all;
    }

    location /data/ {
        deny all;
    }
}
```

## Troubleshooting

### Upload fails with "413 Request Entity Too Large"
Increase nginx/Apache upload limits:
```nginx
client_max_body_size 50M;
```

### MySQL connection fails
1. Verify credentials in `includes/db_config.php`
2. Check MySQL is running: `systemctl status mysql`
3. Verify user permissions: `SHOW GRANTS FOR 'c3dmv_user'@'localhost';`
4. Check error logs: `tail -f /var/log/mysql/error.log`

### 3D viewer not loading
1. Check browser console for JavaScript errors
2. Verify Three.js CDN is accessible
3. Ensure STL/OBJ file is valid (test with external viewer)
4. Check file size (large files may timeout)

### File permissions errors
```bash
chown -R www-data:www-data /var/www/html/C3DMV
chmod 770 /var/www/html/C3DMV/uploads
chmod 770 /var/www/html/C3DMV/data
```

### Session issues (logged out immediately)
1. Check `session.save_path` is writable
2. Verify `session.cookie_secure` matches HTTPS status
3. Check browser is accepting cookies
4. Review PHP session configuration

### Slow performance
1. Enable OPcache in PHP
2. Add database indexes (included in schema)
3. Implement reverse proxy caching (Varnish, nginx)
4. Use CDN for static assets
5. Optimize uploaded images (implement resize pipeline)

## Browser Compatibility

### Fully Supported
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### Partial Support
- Chrome 70-89 (some 3D features may be limited)
- Firefox 70-87 (some 3D features may be limited)
- Safari 12-13 (WebGL performance may vary)

### Not Supported
- Internet Explorer (any version)
- Browsers without WebGL support

## Performance Tuning

### Database Optimization
```sql
-- Add indexes if missing
ALTER TABLE models ADD INDEX idx_user (user_id);
ALTER TABLE model_files ADD INDEX idx_model (model_id);

-- Analyze tables periodically
ANALYZE TABLE models, users, categories;

-- Enable query cache (MySQL 5.7)
SET GLOBAL query_cache_type = ON;
SET GLOBAL query_cache_size = 67108864;  # 64MB
```

### PHP Optimization
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### Caching Strategy
1. **OPcache**: PHP opcode caching (built-in)
2. **Static assets**: CDN or nginx caching
3. **Database queries**: Application-level caching (implement Redis)
4. **3D models**: Browser caching with long expiry headers

## Development

### Local Development
```bash
# Start development server
php -S localhost:8000

# Watch logs
tail -f /var/log/php_errors.log

# Test database connection
php -r "require 'includes/db.php'; print_r(getStats());"
```

### Adding New Categories
Via Admin Dashboard or directly:
```sql
INSERT INTO categories (id, name, icon, description, count)
VALUES ('custom-id', 'Custom Category', 'fa-icon-name', 'Description', 0);
```

### Custom Theming
Edit `css/style.css` variables:
```css
:root {
    --neon-cyan: #00f0ff;
    --neon-magenta: #ff00aa;
    --neon-yellow: #f0ff00;
    --neon-green: #00ff88;
    --bg-dark: #0a0a0f;
    --bg-elevated: #12121a;
}
```

## License

MIT License - See LICENSE file for details.

## Credits

- 3D Rendering: Three.js
- Icons: Font Awesome
- Fonts: Google Fonts (Orbitron, Exo 2)

## Support

For issues, questions, or contributions:
- GitHub Issues: https://github.com/your-org/C3DMV/issues
- Documentation: This README
- Admin Guide: See Admin Dashboard sections above

## Version History

### v1.0.0 (Current)
- Initial release
- Multi-file upload system
- Advanced 3D viewer with 20+ color presets
- Comprehensive admin dashboard
- Settings system with 6 categories
- Invite code system
- User approval workflow
- Hybrid MySQL/JSON storage
- 30+ API endpoints

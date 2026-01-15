# Community 3D Model Vault

**Share. Print. Play.**

Community 3D Model Vault is a PHP-based 3D model sharing platform designed for communities, companies, and organizations. It supports browsing, uploading, and managing 3D printable models (STL and OBJ formats) with a flexible hybrid MySQL/JSON data store.

## Features

### User Features
- Browse, search, and filter models by category and keywords
- Upload STL and OBJ files with metadata, images, and print settings
- Interactive 3D model viewer powered by Three.js with:
  - Orbit controls (rotate, zoom, pan)
  - 20+ color presets including neon colors
  - Wireframe toggle
  - Auto-rotation
  - Grid display
- Download models individually or as ZIP archives
- Favorite and like models
- Track downloads and views
- User profiles with bio, location, website, and social links
- Avatar uploads

### Admin Features
- Dashboard with platform statistics
- Create, update, and delete categories
- Manage users and their roles
- Update or remove uploaded models

## Tech Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+ (optional) or JSON file storage
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **3D Rendering**: Three.js (CDN)
- **Icons**: Font Awesome 6 (CDN)
- **Fonts**: Google Fonts (Orbitron, Exo 2)

## Requirements

- PHP 7.4 or higher
- Web server (Apache or nginx) or PHP built-in server
- Writable `data/` and `uploads/` directories
- MySQL 5.7+ (optional, for production deployments)

## Setup

1. **Clone the repo**:
   ```bash
   git clone [repository] /var/www/html/community-vault
   ```

2. **Set permissions**:
   ```bash
   chmod 755 /var/www/html/community-vault
   chmod 775 /var/www/html/community-vault/data
   chmod 775 /var/www/html/community-vault/uploads
   ```

3. **Run locally (optional)**:
   ```bash
   php -S localhost:8000 -t /var/www/html/community-vault
   ```

4. **Open the app**:
   ```
   http://localhost:8000/
   ```

5. **Default admin account**:
   - Username: `admin`
   - Password: `admin123`

6. **Update the admin password**:
   - Edit `data/users.json` and replace the password hash with a new value from PHP's `password_hash()`.

## Database Configuration

The application supports two storage modes:

### JSON Storage (Default)
No configuration needed. Data is stored in `data/*.json` files. Suitable for small deployments and development.

### MySQL Storage (Production)
For larger deployments with concurrent users:

1. Create a MySQL database
2. Copy `includes/db_config.sample.php` to `includes/db_config.php`
3. Update the database credentials in `db_config.php`
4. Run `setup_database.php` in your browser to create the schema
5. Optionally run `migrate_json_to_mysql.php` to migrate existing JSON data

See `DATABASE_MIGRATION.md` for detailed cPanel deployment instructions.

## File Structure

```
FEC-STL/
├── index.php                    # Homepage with trending/recent models
├── browse.php                   # Browse and search models
├── model.php                    # Single model view with 3D preview
├── upload.php                   # Upload interface
├── login.php                    # Authentication (login/register)
├── profile.php                  # User profiles
├── admin.php                    # Admin dashboard
├── api.php                      # REST-like API endpoints
├── logout.php                   # Logout handler
│
├── includes/
│   ├── config.php               # Configuration and helpers
│   ├── db.php                   # Database abstraction layer
│   └── db_config.sample.php     # MySQL configuration template
│
├── css/
│   └── style.css                # Main stylesheet
│
├── js/
│   └── app.js                   # JavaScript (3D viewer, API, UI)
│
├── data/                        # JSON data storage
│   ├── users.json
│   ├── models.json
│   └── categories.json
│
├── uploads/                     # Model files and images
│
├── setup_database.php           # MySQL schema setup
├── migrate_json_to_mysql.php    # JSON to MySQL migration
├── cleanup_duplicates.php       # Maintenance utility
│
├── DATABASE_MIGRATION.md        # MySQL migration guide
└── README.md                    # This file
```

## API Endpoints

All API calls go through `api.php` via POST or GET parameters.

### Authentication
| Action | Description |
|--------|-------------|
| `login` | Authenticate user |
| `register` | Create new account |
| `logout` | End session |
| `check_auth` | Return current session user |

### Models
| Action | Description |
|--------|-------------|
| `get_models` | List/search models with pagination |
| `get_model` | Get a single model |
| `upload_model` | Upload a new model |
| `update_model` | Update model metadata |
| `delete_model` | Delete model |
| `download_model` | Track and download single file |
| `download_model_zip` | Download model as ZIP archive |
| `like_model` | Like/unlike a model |
| `check_liked` | Check if model is liked |
| `favorite_model` | Toggle favorite status |

### Categories (Admin)
| Action | Description |
|--------|-------------|
| `get_categories` | List categories |
| `create_category` | Create category |
| `update_category` | Update category |
| `delete_category` | Remove category |

### Users (Admin)
| Action | Description |
|--------|-------------|
| `get_users` | List users |
| `get_user` | Get a single user |
| `update_user` | Update user profile/role |
| `upload_avatar` | Upload user avatar |
| `delete_user` | Remove user |
| `get_stats` | Site statistics |

## Configuration Notes

- File upload limit: 50MB (configurable in `includes/config.php`)
- Supported file types: STL, OBJ
- The application automatically detects and uses MySQL if configured, otherwise falls back to JSON storage

## Security Features

- Password hashing with `password_hash()` (bcrypt)
- CSRF token protection on forms
- Input sanitization with `htmlspecialchars()`
- Prepared statements for SQL queries
- Session-based authentication
- File extension validation

## Production Checklist

1. Change default admin credentials immediately
2. Use HTTPS only
3. Set least-privilege permissions for `data/` and `uploads/`
4. Configure MySQL for concurrent access
5. Move setup scripts outside web root after deployment
6. Add rate limiting and backups
7. Monitor error logs

## License

MIT

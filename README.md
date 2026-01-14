# FEC STL Vault

FEC STL Vault is a PHP-based STL file sharing platform for Family Entertainment Center (FEC) operators. It supports browsing, uploading, and managing 3D printable parts with a JSON-backed data store.

## Features

### User Features
- Browse, search, and filter STL models by category.
- Upload STL files with metadata and images.
- View models with a Three.js STL preview.
- Favorite and like models.
- Track downloads and views.
- Manage a user profile.

### Admin Features
- Review platform statistics.
- Create, update, and delete categories.
- Manage users and their roles.
- Update or remove uploaded models.

## Tech Stack

- **Backend**: PHP 7.4+
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **3D Rendering**: Three.js (CDN)
- **Icons**: Font Awesome 6 (CDN)
- **Fonts**: Google Fonts (Orbitron, Exo 2)
- **Storage**: JSON files in `data/`
- **Uploads**: Files stored in `uploads/`

## Requirements

- PHP 7.4 or higher
- Web server (Apache or nginx) or PHP built-in server
- Writable `data/` and `uploads/` directories

## Setup

1. **Clone the repo**:
   ```bash
   git clone [repository] /var/www/html/fec-stl-vault
   ```

2. **Set permissions**:
   ```bash
   chmod 755 /var/www/html/fec-stl-vault
   chmod 775 /var/www/html/fec-stl-vault/data
   chmod 775 /var/www/html/fec-stl-vault/uploads
   ```

3. **Run locally (optional)**:
   ```bash
   php -S localhost:8000 -t /var/www/html/fec-stl-vault
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

## File Structure

```
FEC-STL/
├── index.php           # Homepage
├── browse.php          # Browse/search models
├── model.php           # Single model view with 3D preview
├── upload.php          # Upload interface
├── login.php           # Authentication
├── profile.php         # User profiles
├── admin.php           # Admin dashboard
├── api.php             # AJAX endpoints
├── logout.php          # Logout handler
│
├── includes/
│   ├── config.php      # Configuration & helpers
│   └── db.php          # JSON database operations
│
├── css/
│   └── style.css       # Main stylesheet
│
├── js/
│   └── app.js          # JavaScript (3D viewer, API, UI)
│
├── data/               # JSON data storage
│   ├── users.json
│   ├── models.json
│   └── categories.json
│
└── uploads/            # STL file storage
```

## API Endpoints

All API calls go through `api.php` and accept POST or GET parameters (POST preferred).

| Action | Description |
|--------|-------------|
| `login` | Authenticate user |
| `register` | Create new account |
| `logout` | End session |
| `check_auth` | Return current session user |
| `get_models` | List/search models |
| `get_model` | Get a single model |
| `upload_model` | Upload a new STL |
| `update_model` | Update model metadata |
| `delete_model` | Delete model |
| `download_model` | Track download |
| `like_model` | Like a model |
| `favorite_model` | Toggle favorite |
| `get_categories` | List categories |
| `create_category` | Admin: create category |
| `update_category` | Admin: update category |
| `delete_category` | Admin: remove category |
| `get_users` | Admin: list users |
| `get_user` | Admin: get a single user |
| `update_user` | Admin: update user |
| `delete_user` | Admin: remove user |
| `get_stats` | Admin: site stats |

## Configuration Notes

- File upload limit is set to 50MB in `includes/config.php` (`MAX_FILE_SIZE`).
- JSON storage can be replaced with a database by rewriting the helpers in `includes/db.php`.

## Production Checklist

1. Change admin credentials.
2. Use HTTPS.
3. Set least-privilege permissions for `data/` and `uploads/`.
4. Consider moving JSON storage to a database for concurrency.
5. Add rate limiting and backups.

## License

MIT

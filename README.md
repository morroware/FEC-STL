# FEC STL Vault

A community-driven STL file sharing platform for Family Entertainment Center (FEC) owners to share 3D printable parts, repairs, and upgrades.

![FEC STL Vault](https://via.placeholder.com/800x400/0a0a0f/00f0ff?text=FEC+STL+Vault)

## Features

### For Users
- 🎮 **Browse & Search** - Find STL files by category, keyword, or popularity
- 📤 **Upload Models** - Share your 3D designs with the community
- 👁️ **3D Preview** - Interactive Three.js viewer for STL files
- ❤️ **Favorites** - Save models for later
- 👤 **User Profiles** - Track your uploads and downloads
- 🏷️ **Tags & Categories** - Organized content for easy discovery

### For Admins
- 📊 **Dashboard** - Overview of site statistics
- 📁 **Category Management** - Create, edit, delete categories
- 👥 **User Management** - Manage users and admin privileges
- 🗂️ **Model Management** - Moderate uploaded content

## Tech Stack

- **Backend**: PHP 7.4+
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **3D Rendering**: Three.js (via CDN)
- **Icons**: Font Awesome 6 (via CDN)
- **Fonts**: Google Fonts (Orbitron, Exo 2)
- **Storage**: JSON files (for PoC - easily replaceable with MySQL)

## Installation

### Requirements
- PHP 7.4 or higher
- Apache with mod_rewrite enabled (or nginx)
- Write permissions for `data/` and `uploads/` directories

### Quick Setup

1. **Clone or download** the project files to your web server:
   ```bash
   git clone [repository] /var/www/html/stl-vault
   # or just copy files to your web directory
   ```

2. **Set permissions**:
   ```bash
   chmod 755 /var/www/html/stl-vault
   chmod 777 /var/www/html/stl-vault/data
   chmod 777 /var/www/html/stl-vault/uploads
   ```

3. **Access the site** in your browser:
   ```
   http://localhost/stl-vault/
   ```

4. **Login as admin**:
   - Username: `admin`
   - Password: `admin123`

5. **Change the admin password** (recommended):
   - Log in as admin
   - The password can be changed by editing `data/users.json` directly
   - Use PHP's `password_hash()` function to generate new hashes

## File Structure

```
stl-share/
├── index.php           # Homepage
├── browse.php          # Browse/search models
├── model.php           # Single model view with 3D preview
├── upload.php          # Upload interface
├── login.php           # Authentication
├── profile.php         # User profiles
├── admin.php           # Admin dashboard
├── api.php             # AJAX endpoints
├── logout.php          # Logout handler
├── .htaccess           # Apache configuration
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
├── data/               # JSON data storage (auto-created)
│   ├── users.json
│   ├── models.json
│   └── categories.json
│
└── uploads/            # STL file storage (auto-created)
```

## Default Categories

- Arcade Parts
- Redemption Games
- Signage & Displays
- Coin-Op & Tokens
- Maintenance Tools
- Prize Displays
- Accessories
- Other

## API Endpoints

All API calls go through `api.php` with POST requests:

| Action | Description |
|--------|-------------|
| `login` | Authenticate user |
| `register` | Create new account |
| `logout` | End session |
| `get_models` | List/search models |
| `get_model` | Get single model |
| `upload_model` | Upload new STL |
| `delete_model` | Delete model |
| `download_model` | Track download |
| `like_model` | Like a model |
| `favorite_model` | Toggle favorite |
| `get_categories` | List categories |
| `create_category` | Admin: new category |
| `update_category` | Admin: edit category |
| `delete_category` | Admin: remove category |
| `get_users` | Admin: list users |
| `delete_user` | Admin: remove user |

## Customization

### Changing Colors
Edit CSS variables in `css/style.css`:
```css
:root {
    --neon-cyan: #00f0ff;
    --neon-magenta: #ff00aa;
    --bg-dark: #0a0a0f;
    /* ... */
}
```

### Adding Categories
Use the admin panel or edit `data/categories.json`:
```json
{
    "id": "new-category",
    "name": "New Category",
    "icon": "fa-star",
    "description": "Description here",
    "count": 0
}
```

### Switching to MySQL
Replace the functions in `includes/db.php` with MySQL queries. The data structure remains the same.

## Production Considerations

Before deploying to production:

1. **Change admin credentials**
2. **Use HTTPS**
3. **Set up proper file permissions**
4. **Consider switching to MySQL** for better performance
5. **Add rate limiting** to prevent abuse
6. **Set up backups** for data and uploads
7. **Add CSRF tokens** to all forms (basic implementation included)

## License

MIT License - Feel free to use, modify, and distribute.

## Credits

Built with ❤️ for the FEC community.

- 3D Rendering: [Three.js](https://threejs.org/)
- Icons: [Font Awesome](https://fontawesome.com/)
- Fonts: [Google Fonts](https://fonts.google.com/)

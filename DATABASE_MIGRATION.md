# FEC STL Vault - MySQL Database Migration Guide

This guide will help you migrate from JSON file storage to MySQL database for better performance, scalability, and cPanel hosting compatibility.

## 📋 Prerequisites

Before starting, ensure you have:

- **cPanel account** with MySQL database access
- **PHP 7.4+** with mysqli extension enabled
- **Existing FEC STL Vault** installation (optional, for migration)
- **phpMyAdmin** or command-line MySQL access

---

## 🚀 Quick Start Guide

### For New Installations

If you're setting up a fresh installation:

1. **Create MySQL Database in cPanel**
   - Log into cPanel
   - Navigate to **MySQL Databases**
   - Create a new database (e.g., `fecvault_db`)
   - Create a database user (e.g., `fecvault_user`)
   - Assign the user to the database with ALL PRIVILEGES
   - Note the database name, username, and password

2. **Configure Database Credentials**

   Edit `includes/db_config.php`:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_cpanel_username_fecvault');
   define('DB_USER', 'your_cpanel_username_dbuser');
   define('DB_PASS', 'your_database_password');
   ```

   **⚠️ Important for cPanel:**
   - Database names are prefixed: `cpaneluser_dbname`
   - Database users are prefixed: `cpaneluser_username`
   - Example: `john_fecvault` and `john_fecuser`

3. **Run Database Setup**

   ```bash
   php setup_database.php
   ```

   This will:
   - Create all necessary tables
   - Insert default categories
   - Create admin user (username: `admin`, password: `admin123`)

4. **Done!** Your application is now using MySQL.

---

### For Existing Installations (Migration)

If you have existing JSON data to migrate:

1. **Backup Your Data**

   ```bash
   cp -r data/ data_backup/
   cp -r uploads/ uploads_backup/
   ```

2. **Follow Steps 1-3 from "New Installations"** above

3. **Run Migration Script**

   ```bash
   php migrate_json_to_mysql.php
   ```

   This will:
   - Import all users from `users.json`
   - Import all models from `models.json`
   - Import all categories from `categories.json`
   - Preserve all relationships (favorites, files, photos)
   - Maintain all statistics and timestamps

4. **Verify Migration**
   - Log in to your application
   - Check that all models are visible
   - Verify user accounts work
   - Test uploading new models

5. **Archive Old JSON Files** (after verification)

   ```bash
   mkdir archive
   mv data/ archive/data_backup_$(date +%Y%m%d)/
   ```

---

## 📊 Database Schema

The migration creates the following tables:

### `users`
Stores user accounts, profiles, and authentication data.

### `categories`
Stores model categories with icons and descriptions.

### `models`
Main table for 3D model metadata, stats, and settings.

### `model_files`
Stores multiple file attachments per model (STL, OBJ).

### `model_photos`
Stores multiple photos per model with ordering.

### `favorites`
Many-to-many relationship between users and favorited models.

---

## 🔧 cPanel-Specific Configuration

### Database Connection Settings

In cPanel shared hosting:

```php
// Standard configuration
define('DB_HOST', 'localhost');

// Database name format: cpanelusername_dbname
define('DB_NAME', 'john_fecvault');

// User format: cpanelusername_username
define('DB_USER', 'john_fecuser');

// Your chosen password
define('DB_PASS', 'SecurePassword123!');
```

### Common Issues & Solutions

#### **"Can't connect to database"**
- ✓ Verify database name includes cPanel prefix
- ✓ Check user has been assigned to database
- ✓ Confirm password is correct
- ✓ Test connection in phpMyAdmin

#### **"Table doesn't exist"**
- ✓ Run `setup_database.php` first
- ✓ Check if script completed without errors
- ✓ Verify tables exist in phpMyAdmin

#### **"Access denied for user"**
- ✓ User must have ALL PRIVILEGES on database
- ✓ Check user is assigned to correct database
- ✓ Verify credentials in `db_config.php`

#### **"Lost connection to MySQL server"**
- ✓ Large uploads may timeout - increase PHP limits:
  ```ini
  max_execution_time = 300
  max_input_time = 300
  memory_limit = 256M
  ```

---

## 🔒 Security Best Practices

### 1. Protect Database Configuration

Add to `.gitignore`:
```
includes/db_config.php
```

### 2. Restrict Setup Script Access

After setup, either:
- Delete `setup_database.php` and `migrate_json_to_mysql.php`
- Move them outside web root
- Add .htaccess protection:
  ```apache
  <Files "setup_database.php">
    Require all denied
  </Files>
  <Files "migrate_json_to_mysql.php">
    Require all denied
  </Files>
  ```

### 3. Use Strong Database Password

Generate strong password in cPanel:
- Use Password Generator in MySQL Databases section
- Minimum 16 characters recommended

### 4. Regular Backups

Set up automated backups in cPanel:
- Use **Backup Wizard** for full backups
- Export database regularly via phpMyAdmin
- Store backups off-server

---

## 📈 Performance Optimization

### For cPanel Shared Hosting

1. **Add Indexes** (already included in schema):
   - User lookups by username/email
   - Model searches by category/date
   - Full-text search on titles/descriptions

2. **Enable Query Caching** (if available):
   Check with hosting provider for MySQL query cache

3. **Optimize Uploads Directory**:
   - Keep large files out of database
   - Use CDN for static assets if needed

---

## 🔄 Rollback Procedure

If you need to rollback to JSON storage:

1. **Restore Backup**:
   ```bash
   rm -rf data/
   cp -r data_backup/ data/
   ```

2. **Restore Old db.php**:
   ```bash
   git checkout HEAD -- includes/db.php
   ```

3. **Remove Database Files**:
   ```bash
   rm includes/db_config.php
   rm setup_database.php
   rm migrate_json_to_mysql.php
   ```

---

## ✅ Testing Checklist

After migration, verify:

- [ ] Login works with existing users
- [ ] All models are visible on homepage
- [ ] Model detail pages load correctly
- [ ] File downloads work
- [ ] Photo galleries display properly
- [ ] Upload new model works
- [ ] Search functionality works
- [ ] Category filtering works
- [ ] User favorites are preserved
- [ ] Admin panel functions correctly
- [ ] Statistics are accurate

---

## 🆘 Getting Help

If you encounter issues:

1. **Check Error Logs**:
   - cPanel > Error Logs
   - Look for MySQL connection errors
   - Check PHP error logs

2. **Enable Debug Mode**:
   Add to `config.php`:
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

3. **Test Database Connection**:
   ```php
   <?php
   require_once 'includes/db_config.php';
   $conn = getDbConnection();
   echo "Connection successful!";
   ?>
   ```

4. **Verify Data Migration**:
   Check row counts in phpMyAdmin:
   ```sql
   SELECT 'users' as table_name, COUNT(*) as count FROM users
   UNION ALL
   SELECT 'models', COUNT(*) FROM models
   UNION ALL
   SELECT 'categories', COUNT(*) FROM categories;
   ```

---

## 📝 Maintenance

### Regular Maintenance Tasks

1. **Optimize Tables** (monthly):
   ```sql
   OPTIMIZE TABLE users, models, categories, model_files, model_photos, favorites;
   ```

2. **Check Table Health**:
   ```sql
   CHECK TABLE users, models, categories;
   ```

3. **Monitor Database Size**:
   Check in cPanel > MySQL Databases

### Backup Strategy

- **Daily**: Automated cPanel backups
- **Weekly**: Manual phpMyAdmin export
- **Before Updates**: Full database dump
- **Monthly**: Download off-server copy

---

## 🎉 Benefits of MySQL Migration

✅ **Better Performance**: Faster queries and indexing
✅ **Scalability**: Handle thousands of models efficiently
✅ **Data Integrity**: ACID compliance and foreign keys
✅ **Advanced Search**: Full-text search on titles/descriptions
✅ **Concurrent Access**: Multiple users without file locks
✅ **Backup Tools**: Industry-standard backup solutions
✅ **cPanel Integration**: Native database management tools
✅ **Professional**: Production-ready database solution

---

## 📚 Additional Resources

- [cPanel MySQL Documentation](https://docs.cpanel.net/cpanel/databases/mysql-databases/)
- [MySQL 5.7 Reference](https://dev.mysql.com/doc/refman/5.7/en/)
- [PHP mysqli Documentation](https://www.php.net/manual/en/book.mysqli.php)

---

**Version**: 1.0
**Last Updated**: 2026-01-14
**Compatibility**: cPanel shared hosting, PHP 7.4+, MySQL 5.7+

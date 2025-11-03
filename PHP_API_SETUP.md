# Star Citizen Team Up - PHP API Setup Guide

This guide will help you set up the PHP backend for the Star Citizen Team Up application on your server at `https://starcitizen.gamer.gd/`.

## Prerequisites

- PHP 7.4 or higher (PHP 8.x recommended)
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache/Nginx)
- PDO MySQL extension enabled

## Installation Steps

### 1. Upload Files

Upload the following files to your server:

```
starcitizen.gamer.gd/
├── api/
│   ├── config.php
│   ├── create_group.php
│   ├── load_groups.php
│   └── join_group.php
└── index.html
```

### 2. Set Up Database

#### Create the Database

```sql
CREATE DATABASE starcitizen_teamup CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### Import the Schema

Run the `database_schema_mysql.sql` file to create all necessary tables:

```bash
mysql -u your_username -p starcitizen_teamup < database_schema_mysql.sql
```

Or import it using phpMyAdmin if available.

### 3. Configure Database Connection

Edit `api/config.php` and update these lines with your actual database credentials:

```php
define('DB_HOST', 'localhost');          // Your database host
define('DB_NAME', 'starcitizen_teamup'); // Your database name
define('DB_USER', 'your_db_user');       // Your database username
define('DB_PASS', 'your_db_password');   // Your database password
```

### 4. Set Proper File Permissions

Ensure the API files have the correct permissions:

```bash
chmod 644 api/*.php
chmod 755 api/
```

### 5. Configure CORS (if needed)

The API is configured to accept requests from `https://starcitizen.gamer.gd`. If you need to change this, edit the `setCorsHeaders()` function in `api/config.php`:

```php
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: https://your-domain.com');
    // ...
}
```

### 6. Test the API

Test each endpoint to ensure they're working:

#### Test Load Groups
```bash
curl https://starcitizen.gamer.gd/api/load_groups.php
```

Expected response:
```json
{
    "success": true,
    "groups": []
}
```

#### Test Create Group
```bash
curl -X POST https://starcitizen.gamer.gd/api/create_group.php \
  -H "Content-Type: application/json" \
  -d '{
    "creator_handle": "TestUser",
    "activity_type": "Bounty Hunting",
    "title": "Test Group",
    "description": "Test description",
    "ship": "Carrack",
    "max_players": 4
  }'
```

Expected response:
```json
{
    "success": true,
    "group": { ... }
}
```

## API Endpoints

### GET /api/load_groups.php
Loads all active groups with member counts.

**Response:**
```json
{
    "success": true,
    "groups": [
        {
            "id": "uuid",
            "creator_handle": "PlayerName",
            "activity_type": "Bounty Hunting",
            "title": "Group Title",
            "description": "Description",
            "ship": "Carrack",
            "max_players": 4,
            "status": "open",
            "member_count": 2,
            "expires_at": "2025-01-01 12:00:00",
            "created_at": "2025-01-01 11:00:00"
        }
    ]
}
```

### POST /api/create_group.php
Creates a new group.

**Request Body:**
```json
{
    "creator_handle": "PlayerName",
    "activity_type": "Bounty Hunting",
    "title": "Need 3 for ERT",
    "description": "Looking for experienced players",
    "ship": "Hammerhead",
    "max_players": 4
}
```

**Response:**
```json
{
    "success": true,
    "group": { ... }
}
```

### POST /api/join_group.php
Joins an existing group.

**Request Body:**
```json
{
    "group_id": "uuid-of-group",
    "player_handle": "PlayerName"
}
```

**Response:**
```json
{
    "success": true,
    "is_full": false,
    "member_count": 3
}
```

## Security Features

### Rate Limiting
- Create Group: 3 requests per minute per IP
- Join Group: 10 requests per minute per IP

### Input Validation
All inputs are validated on both frontend and backend:
- Player handles: 3-50 characters, alphanumeric + underscores/dashes
- Titles: 3-100 characters
- Descriptions: max 500 characters
- Ships: 2-50 characters
- Max players: 2-50

### SQL Injection Protection
All queries use PDO prepared statements with bound parameters.

### XSS Protection
- All outputs are sanitized
- Security headers are set
- HTML special characters are escaped

## Database Maintenance

### Clean Up Old Rate Limits
Run this periodically (e.g., daily cron job):

```sql
DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

### Clean Up Expired Groups
This is done automatically by the `load_groups.php` endpoint, but you can also run:

```sql
CALL clean_expired_groups();
```

Or manually:

```sql
UPDATE starcitizen_teamup_groups
SET status = 'closed'
WHERE status IN ('open', 'full')
AND expires_at < NOW();
```

## Troubleshooting

### "Database connection failed"
- Check your database credentials in `api/config.php`
- Ensure MySQL/MariaDB is running
- Verify the database user has proper permissions

### "CORS error"
- Check the `Access-Control-Allow-Origin` header in `api/config.php`
- Ensure your frontend domain matches the allowed origin

### "500 Internal Server Error"
- Check PHP error logs: `tail -f /var/log/apache2/error.log` (or nginx equivalent)
- Ensure PDO MySQL extension is installed: `php -m | grep pdo_mysql`
- Verify file permissions

### Groups not appearing
- Check that the database tables were created properly
- Verify the API endpoints are accessible
- Check browser console for JavaScript errors

## Support

For issues or questions:
1. Check the browser console for errors
2. Check server error logs
3. Verify all configuration settings
4. Test API endpoints directly with curl

## License

This project is open source and available for use in Star Citizen community projects.

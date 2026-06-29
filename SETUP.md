# FA Print - Production Setup Guide

## Overview
FA Print is a comprehensive print-on-demand platform connecting students with printing vendors. This guide covers the complete setup for a production deployment.

## System Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher (8.0 recommended)
- **Web Server**: Apache 2.4+ with mod_rewrite or Nginx
- **PHP Extensions**: PDO, PDO_MySQL, JSON, cURL, GD

## Installation Steps

### 1. Database Setup

```bash
# Connect to MySQL
mysql -u root -p

# Create the database and tables
SOURCE /path/to/database.sql;

# Verify installation
USE faprint;
SHOW TABLES;
```

### 2. Environment Configuration

Create a `.env` file in the project root:

```bash
DB_HOST=localhost
DB_USER=faprint_user
DB_PASS=your_secure_password
DB_NAME=faprint

JWT_SECRET=your_jwt_secret_key_here
MAIL_FROM=noreply@faprint.local
MAIL_HOST=smtp.gmail.com

DEMO_MODE=false
```

### 3. File Permissions

```bash
# Create uploads directory
mkdir -p /var/www/faprint/uploads
mkdir -p /var/www/faprint/logs

# Set proper permissions
chmod 755 /var/www/faprint/uploads
chmod 755 /var/www/faprint/logs
chmod 644 /var/www/faprint/api/*.php
```

### 4. Web Server Configuration

#### Apache (.htaccess)
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^api/(.*)$ api/index.php?route=$1 [QSA,L]
</IfModule>
```

#### Nginx
```nginx
location /api/ {
    try_files $uri $uri/ /api/index.php?$query_string;
}
```

## API Endpoints

### Authentication
- `POST /api/users.php?action=register_student` - Register student
- `POST /api/users.php?action=register_vendor` - Register vendor
- `POST /api/users.php?action=login` - User login
- `POST /api/users.php?action=verify_otp` - Verify OTP

### Users
- `GET /api/users.php?action=get_profile&user_id=ID` - Get user profile
- `POST /api/users.php?action=update_profile` - Update profile
- `GET /api/users.php?action=get_all_vendors&status=approved` - List vendors

### Orders
- `POST /api/orders.php?action=create_order` - Create order
- `GET /api/orders.php?action=get_order&order_id=ID` - Get order details
- `GET /api/orders.php?action=get_student_orders&student_id=ID` - Student orders
- `GET /api/orders.php?action=get_vendor_orders&vendor_id=ID` - Vendor orders

### Admin
- `GET /api/admin.php?action=get_dashboard_stats` - Dashboard statistics
- `GET /api/admin.php?action=get_all_vendors` - List all vendors
- `POST /api/admin.php?action=approve_vendor` - Approve vendor
- `GET /api/admin.php?action=get_all_orders` - List all orders

### Support
- `POST /api/support.php?action=create_ticket` - Create support ticket
- `GET /api/support.php?action=get_user_tickets&user_id=ID` - User tickets

## Frontend Configuration

### Update API Base URL
In each HTML file, update the API base URL:

```javascript
const API_BASE = 'https://your-domain.com/api';
```

### Disable Demo Mode
In all HTML files, change:

```javascript
const DEMO_MODE = false;
```

## Security Considerations

1. **HTTPS**: Always use HTTPS in production
2. **CORS**: Configure CORS headers appropriately
3. **Input Validation**: All inputs are validated on the server
4. **Password Hashing**: Passwords are hashed using bcrypt
5. **SQL Injection**: All queries use prepared statements
6. **File Upload**: Validate file types and sizes
7. **Rate Limiting**: Implement rate limiting on API endpoints

## Maintenance

### Database Backups
```bash
mysqldump -u faprint_user -p faprint > backup_$(date +%Y%m%d).sql
```

### Log Rotation
Configure logrotate for `/var/www/faprint/logs/error.log`

### Monitoring
- Monitor database performance
- Track API response times
- Monitor file upload directory size
- Review audit logs regularly

## Troubleshooting

### Database Connection Error
- Verify MySQL credentials in config.php
- Check database exists: `SHOW DATABASES;`
- Verify user permissions: `SHOW GRANTS FOR 'faprint_user'@'localhost';`

### File Upload Issues
- Check directory permissions: `ls -la uploads/`
- Verify PHP upload limits in php.ini
- Check available disk space

### API Errors
- Check error logs: `tail -f logs/error.log`
- Verify CORS headers are correct
- Test endpoints with curl

## Performance Optimization

1. **Database Indexing**: Indexes are already created on key columns
2. **Caching**: Implement Redis for session caching
3. **CDN**: Serve static assets through CDN
4. **Compression**: Enable gzip compression
5. **Minification**: Minify CSS and JavaScript files

## Support

For issues or questions:
- Check error logs in `/logs/error.log`
- Review API response messages
- Consult the API documentation

---
**FA Print v1.0.0** | Last Updated: 2024

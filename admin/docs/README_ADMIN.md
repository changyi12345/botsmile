# SmileOne Web Admin System

A comprehensive web-based admin dashboard for managing your SmileOne payment system, bot operations, and user management.

## Features

### 🎛️ Dashboard
- Real-time statistics and analytics
- User activity overview
- Transaction monitoring
- Revenue tracking
- Commission management

### 👥 User Management
- View all registered users
- Edit user details and balances
- Activate/deactivate accounts
- User search and filtering

### 💳 Transaction Management
- View all transactions
- Transaction status tracking
- Export transaction data
- Transaction search and filtering

### 📦 Product Management
- Add/edit/delete products
- Product categorization
- Price management
- Product status control

### 🤖 Bot Management
- Start/stop bot operations
- Real-time bot status monitoring
- Bot configuration settings
- Log viewing and management

### ⚙️ Settings & Security
- Admin password management
- Data backup and restore
- System optimization tools
- Cache management

## Installation & Setup

### 1. Initial Setup
1. Navigate to your SmileOne directory
2. Run the setup script: `php setup_admin.php`
3. Follow the setup wizard to create your admin account
4. **Important**: Delete `setup_admin.php` after setup for security

### 2. Default Login Credentials
- **Username**: admin (or your chosen username)
- **Password**: admin123 (or your chosen password)

**⚠️ Security Warning**: Change the default password immediately after first login!

## File Structure

```
smileone/
├── admin_login.php          # Admin login page
├── admin_dashboard.php      # Main admin dashboard
├── admin_logout.php         # Logout functionality
├── admin_api.php           # API endpoints for admin operations
├── setup_admin.php         # Initial setup script (delete after use)
├── README_ADMIN.md         # This documentation
├── admins.json             # Admin user data
├── users.json              # User data
├── transactions.json       # Transaction records
├── products.json           # Product catalog
├── commissions.json        # Commission records
├── topup_codes.json        # Top-up codes
└── bot_log.txt            # Bot operation logs
```

## Usage Guide

### Accessing the Admin Panel
1. Navigate to `admin_login.php` in your web browser
2. Enter your admin credentials
3. Click "Login" to access the dashboard

### Dashboard Overview
- **Statistics Cards**: View key metrics at a glance
- **Recent Transactions**: Monitor latest system activity
- **Quick Actions**: Access frequently used features

### User Management
1. Click "Users" in the sidebar
2. View all registered users in the table
3. Use action buttons to:
   - Edit user details
   - Update user balance
   - Activate/deactivate accounts
   - Delete users

### Transaction Management
1. Click "Transactions" in the sidebar
2. View all transactions with filtering options
3. Export transaction data for reporting
4. Update transaction statuses

### Product Management
1. Click "Products" in the sidebar
2. Add new products with pricing
3. Edit existing product details
4. Manage product availability

### Bot Management
1. Click "Bot Management" in the sidebar
2. View current bot status
3. Start/stop bot operations
4. Configure bot settings
5. View operation logs

### Settings
1. Click "Settings" in the sidebar
2. Change admin password
3. Backup system data
4. Optimize database performance
5. Clear system cache

## API Endpoints

The admin system provides RESTful API endpoints through `admin_api.php`:

### User Operations
- `GET /admin_api.php?action=get_users` - Get all users
- `GET /admin_api.php?action=get_user&telegram_id=ID` - Get specific user
- `POST /admin_api.php?action=update_user` - Update user data
- `POST /admin_api.php?action=delete_user` - Delete user

### Transaction Operations
- `GET /admin_api.php?action=get_transactions` - Get all transactions
- `GET /admin_api.php?action=get_transaction&id=ID` - Get specific transaction
- `POST /admin_api.php?action=update_transaction` - Update transaction

### Product Operations
- `GET /admin_api.php?action=get_products` - Get all products
- `POST /admin_api.php?action=update_product` - Update product

### System Operations
- `GET /admin_api.php?action=get_statistics` - Get system statistics
- `POST /admin_api.php?action=backup_data` - Create data backup
- `GET /admin_api.php?action=get_bot_status` - Get bot status
- `POST /admin_api.php?action=start_bot` - Start bot
- `POST /admin_api.php?action=stop_bot` - Stop bot

## Security Features

### Authentication
- Session-based authentication
- Secure login system with CSRF protection
- Automatic session timeout
- Password hashing (in production)

### Access Control
- Admin-only access to sensitive operations
- Role-based permissions (extensible)
- API endpoint authentication

### Data Protection
- Input validation and sanitization
- XSS protection
- SQL injection prevention (using JSON files)
- Secure file permissions

## Customization

### Styling
The admin interface uses Bootstrap 5 with custom CSS. You can customize:
- Color schemes in the CSS section
- Layout and spacing
- Component styling
- Responsive breakpoints

### Adding New Features
1. Add new sections to `admin_dashboard.php`
2. Create corresponding API endpoints in `admin_api.php`
3. Update navigation menu
4. Add JavaScript functionality

### Database Integration
Currently uses JSON files for data storage. To integrate with a database:
1. Replace JSON file operations with database queries
2. Update connection settings
3. Modify data models accordingly

## Troubleshooting

### Common Issues

**Login Problems:**
- Check if `admins.json` exists and is readable
- Verify session configuration in PHP
- Check file permissions

**Data Not Loading:**
- Ensure JSON files exist and are readable
- Check file permissions (should be 644 or 664)
- Verify JSON syntax in data files

**API Errors:**
- Check if admin is logged in
- Verify API action parameter
- Check request method (GET/POST)

**Bot Management Issues:**
- Ensure proper server permissions for process management
- Check if `bot.php` exists and is executable
- Verify `bot.pid` file permissions

### Performance Optimization
- Implement pagination for large datasets
- Add caching for frequently accessed data
- Optimize JSON file operations
- Consider database migration for large-scale usage

## Support & Updates

### Regular Maintenance
- Monitor system logs
- Backup data regularly
- Update dependencies
- Review security settings

### Security Best Practices
- Use strong passwords
- Enable HTTPS
- Regular security audits
- Keep software updated
- Monitor access logs

## License

This admin system is part of the SmileOne project. Please refer to the main project license for usage terms.

---

**Note**: This is a basic admin system implementation. For production use, consider:
- Implementing proper database integration
- Adding more robust security features
- Implementing advanced user permissions
- Adding audit logging
- Implementing data encryption
- Adding automated backups
- Implementing monitoring and alerting
# WordPress CSV Page Generator Plugin

A professional WordPress plugin that allows CSV file uploads to automatically generate WordPress pages with enhanced security, performance, and user experience features.

## 🎯 Assignment Overview

This project is developed for the Reason Digital technical assignment, demonstrating modern WordPress plugin development practices, clean code architecture, and comprehensive testing strategies.

### Core Requirements
- ✅ WordPress plugin for CSV file uploads
- ✅ Automatic page generation from CSV data in draft mode
- ✅ Modified default WordPress theme to display CSV content
- ✅ DDEV setup for local development
- ✅ Public GitHub repository

### Bonus Features
- 🔐 JWT token-based authentication for page access
- 📊 Advanced admin interface with progress tracking
- 🛡️ Enhanced security measures
- ⚡ Performance optimization for large files

## 🚀 Quick Start with DDEV

### Prerequisites
- [DDEV](https://ddev.readthedocs.io/en/stable/) installed
- [Docker](https://www.docker.com/) running
- [Composer](https://getcomposer.org/) installed
- [Node.js](https://nodejs.org/) (v16+) installed

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/reason-digital/wordpress-csv-plugin.git
   cd wordpress-csv-plugin
   ```

2. **Start DDEV environment**
   ```bash
   ddev start
   ```

3. **Install dependencies**
   ```bash
   # Install PHP dependencies
   ddev composer install

   # Install Node.js dependencies
   ddev exec npm install

   # Install plugin dependencies
   cd wp-content/plugins/csv-page-generator
   ddev composer install
   ddev exec npm install
   ```

4. **Build assets**
   ```bash
   cd wp-content/plugins/csv-page-generator
   ddev exec npm run build
   ```

5. **Access the site**
   - Frontend: https://wordpress-csv-plugin.ddev.site
   - Admin: https://wordpress-csv-plugin.ddev.site/wp-admin
   - Credentials: admin / admin

## 📁 Project Structure

```
wordpress-csv-plugin/
├── .ddev/                          # DDEV configuration
├── wp-content/
│   ├── plugins/
│   │   └── csv-page-generator/     # Main plugin directory
│   │       ├── src/                # PSR-4 autoloaded source code
│   │       ├── assets/             # Frontend assets
│   │       ├── templates/          # Template files
│   │       ├── tests/              # Unit and integration tests
│   │       └── languages/          # Translation files
│   └── themes/
│       └── twentytwentyfour-child/ # Child theme modifications
├── composer.json                   # Root dependencies
├── package.json                    # Node.js dependencies
└── README.md                       # This file
```

## 🔧 Development

### Code Quality
```bash
# Run PHP CodeSniffer
ddev composer phpcs

# Fix coding standards
ddev composer phpcbf

# Run tests
ddev composer test

# Run tests with coverage
ddev composer test:coverage
```

### Asset Development
```bash
# Watch for changes (development)
ddev exec npm run dev

# Build for production
ddev exec npm run build

# Lint JavaScript
ddev exec npm run lint:js

# Lint CSS
ddev exec npm run lint:css
```

## 📋 Features

### Core Functionality
- **Secure CSV Upload**: File validation, size limits, and security scanning
- **Intelligent Parsing**: Flexible column mapping and data validation
- **Batch Processing**: Handle large files without timeouts
- **Draft Page Creation**: Automatic WordPress page generation
- **Error Handling**: Comprehensive error reporting and recovery

### Admin Interface
- **Drag & Drop Upload**: Intuitive file upload experience
- **Real-time Progress**: Live progress tracking with cancel option
- **Import History**: Complete audit trail of all imports
- **Bulk Actions**: Manage generated pages efficiently
- **Settings Management**: Configurable plugin options

### Security Features
- **Input Sanitization**: WordPress-standard data cleaning
- **CSRF Protection**: Nonce verification for all actions
- **Capability Checks**: Proper user permission validation
- **File Validation**: Comprehensive upload security
- **Rate Limiting**: Prevent abuse and attacks

### Performance Optimization
- **Background Processing**: WP-Cron integration for large files
- **Memory Management**: Efficient handling of large datasets
- **Caching Strategy**: Object caching and transients
- **Database Optimization**: Efficient queries and indexing

## 🧪 Testing

The plugin includes comprehensive testing coverage:

```bash
# Run all tests
ddev composer test

# Run specific test suites
ddev exec vendor/bin/phpunit tests/Unit
ddev exec vendor/bin/phpunit tests/Integration

# Generate coverage report
ddev composer test:coverage
```

### Test Coverage
- Unit tests for all core functionality
- Integration tests with WordPress
- Security testing and validation
- Performance benchmarking

## 🔐 Security

### Security Measures Implemented
- Input sanitization using WordPress functions
- CSRF protection with nonces
- File upload validation and scanning
- SQL injection prevention
- XSS protection
- Rate limiting for uploads

### Security Testing
```bash
# Run security-focused tests
ddev exec vendor/bin/phpunit tests/Security

# Check for vulnerabilities
ddev composer audit
```

## 📚 Documentation

- [Implementation Plan](implementation-plan.md) - Detailed development strategy
- [API Documentation](wp-content/plugins/csv-page-generator/docs/) - Generated from code
- [User Guide](wp-content/plugins/csv-page-generator/docs/user-guide.md) - End-user instructions
- [Developer Guide](wp-content/plugins/csv-page-generator/docs/developer-guide.md) - Technical documentation

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Standards
- Follow WordPress Coding Standards
- Write comprehensive tests
- Document all functions and classes
- Use semantic versioning

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Reason Digital for the technical assignment
- WordPress community for excellent documentation
- DDEV team for the amazing development environment

---

**Note**: This is a technical assignment project demonstrating WordPress plugin development best practices. The code is production-ready and follows industry standards for security, performance, and maintainability.
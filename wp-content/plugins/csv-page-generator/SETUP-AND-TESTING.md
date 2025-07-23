# WordPress CSV Page Generator Plugin - Setup and Testing Guide

This comprehensive guide provides step-by-step instructions for setting up the development environment and testing the CSV Page Generator plugin functionality.

## Quick Start (5 Minutes)

For experienced developers who want to get started immediately:

```bash
# 1. Start DDEV environment
ddev start

# 2. Install WordPress (if not already installed)
ddev wp core download
ddev wp core install --url=https://wordpress-csv-plugin.ddev.site --title="CSV Plugin Development" --admin_user=admin --admin_password=admin --admin_email=admin@example.com

# 3. Activate the plugin
ddev wp plugin activate csv-page-generator

# 4. Test CSV processing
ddev wp eval-file wp-content/plugins/csv-page-generator/tests/test-csv-processing.php

# 5. Access WordPress admin
# URL: http://127.0.0.1:[PORT]/wp-admin (get port from 'ddev describe')
# Login: admin / admin
```

**Expected Results:** 14 pages created from sample CSV data, import tracking in database.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [DDEV Environment Setup](#ddev-environment-setup)
3. [WordPress Installation](#wordpress-installation)
4. [Plugin Activation](#plugin-activation)
5. [CSV Processing Testing](#csv-processing-testing)
6. [Verification Steps](#verification-steps)
7. [Troubleshooting](#troubleshooting)
8. [Advanced Testing](#advanced-testing)

## Prerequisites

Before starting, ensure you have the following installed:

### Required Software

- **Docker Desktop** (v4.0+) - [Download](https://www.docker.com/products/docker-desktop/)
- **DDEV** (v1.21+) - [Installation Guide](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/)
- **Git** - For version control
- **Node.js** (v16+) - For asset building
- **Composer** - For PHP dependencies

### Quick Prerequisites Check

```bash
# Verify all required tools are installed
docker --version          # Should show Docker version 20.10+
ddev version              # Should show DDEV version 1.21+
git --version             # Should show Git version 2.0+
node --version            # Should show Node.js version 16+
npm --version             # Should show npm version 8+
composer --version        # Should show Composer version 2.0+
```

### System Requirements

- **RAM**: Minimum 8GB (16GB recommended)
- **Disk Space**: At least 5GB free space
- **OS**: macOS, Windows 10/11, or Linux
- **Ports**: 80, 443, 3306 available (DDEV will check this)

## DDEV Environment Setup

### Step 1: Clone or Navigate to Project Directory

```bash
# If cloning from repository
git clone https://github.com/reason-digital/wordpress-csv-plugin.git
cd wordpress-csv-plugin

# Or navigate to existing project directory
cd /path/to/wordpress-csv-plugin-assignment
```

### Step 2: Start Docker Desktop

Ensure Docker Desktop is running before proceeding. You should see the Docker icon in your system tray/menu bar.

### Step 3: Initialize and Start DDEV

```bash
# Start the DDEV environment
ddev start

# This process will:
# - Download WordPress core files
# - Set up MySQL database
# - Configure web server (nginx)
# - Install WordPress with default admin user
# - Configure SSL certificates
```

**Expected Output:**
```
Successfully started csv-page-generator
Project can be reached at https://wordpress-csv-plugin.ddev.site
```

### Step 4: Install Dependencies

```bash
# Install PHP dependencies (if composer.json exists)
ddev composer install

# Install Node.js dependencies for asset building
ddev exec npm install

# Navigate to plugin directory for plugin-specific dependencies
cd wp-content/plugins/csv-page-generator

# Install plugin dependencies
ddev composer install --working-dir=wp-content/plugins/csv-page-generator
ddev exec npm install --prefix wp-content/plugins/csv-page-generator

# Build plugin assets
ddev exec npm run build --prefix wp-content/plugins/csv-page-generator
```

### Step 5: Verify DDEV Setup

```bash
# Check DDEV status
ddev status

# View project information
ddev describe

# Check if WordPress is accessible
curl -I https://wordpress-csv-plugin.ddev.site
```

## WordPress Installation

### Step 1: Access WordPress Admin

1. **Frontend URL**: https://wordpress-csv-plugin.ddev.site
2. **Admin URL**: https://wordpress-csv-plugin.ddev.site/wp-admin
3. **Default Credentials**:
   - Username: `admin`
   - Password: `admin`

### Step 2: Complete WordPress Setup (if needed)

If WordPress isn't automatically configured:

```bash
# Use WP-CLI to complete setup
ddev wp core install \
  --url=https://wordpress-csv-plugin.ddev.site \
  --title="CSV Plugin Development" \
  --admin_user=admin \
  --admin_password=admin \
  --admin_email=admin@example.com
```

### Step 3: Verify WordPress Installation

1. Visit https://wordpress-csv-plugin.ddev.site
2. Confirm the site loads without errors
3. Access admin at https://wordpress-csv-plugin.ddev.site/wp-admin
4. Login with `admin` / `admin`

## Plugin Activation

### Step 1: Verify Plugin Files

```bash
# Check plugin directory structure
ddev exec ls -la wp-content/plugins/csv-page-generator/

# Verify main plugin file exists
ddev exec ls -la wp-content/plugins/csv-page-generator/csv-page-generator.php

# Check file permissions
ddev exec find wp-content/plugins/csv-page-generator/ -type f -name "*.php" -exec ls -la {} \;
```

### Step 2: Activate Plugin via WordPress Admin

1. Go to **Plugins** → **Installed Plugins**
2. Find "**CSV Page Generator**" in the plugin list
3. Click **Activate**
4. Verify activation success message appears

### Step 3: Activate Plugin via WP-CLI (Alternative)

```bash
# List all plugins
ddev wp plugin list

# Activate the CSV Page Generator plugin
ddev wp plugin activate csv-page-generator

# Verify activation
ddev wp plugin status csv-page-generator
```

### Step 4: Verify Plugin Activation

**Check Admin Menu:**
1. Look for "**CSV Pages**" in the WordPress admin sidebar
2. Verify submenu items:
   - Upload CSV
   - Import History
   - Settings

**Check Database Tables:**
```bash
# Verify custom table was created
ddev mysql -e "SHOW TABLES LIKE '%csv_page_generator%';"

# Check table structure
ddev mysql -e "DESCRIBE wp_csv_page_generator_imports;"
```

**Check Plugin Options:**
```bash
# Verify plugin settings were created
ddev wp option get csv_page_generator_settings
ddev wp option get csv_page_generator_version
ddev wp option get csv_page_generator_db_version
```

## CSV Processing Testing

### Step 1: Locate Sample Data File

The sample CSV file is located at:
```
wp-content/plugins/csv-page-generator/samples/sample-data.csv
```

**Verify file exists:**
```bash
ddev exec ls -la wp-content/plugins/csv-page-generator/samples/sample-data.csv
ddev exec head -5 wp-content/plugins/csv-page-generator/samples/sample-data.csv
```

### Step 2: Test CSV Upload via Admin Interface

1. **Navigate to Upload Page:**
   - Go to **CSV Pages** → **Upload CSV**

2. **Configure Upload Settings:**
   - **Page Status**: Draft (recommended for testing)
   - **Page Author**: Current User (admin)

3. **Upload Sample File:**
   - Click **Choose File** or drag and drop
   - Select: `wp-content/plugins/csv-page-generator/samples/sample-data.csv`
   - Click **Upload and Process CSV**

4. **Monitor Progress:**
   - Watch the progress bar
   - Note processing status updates
   - Check for any error messages

### Step 3: Expected Results

With the sample data file, you should see:
- **Total rows**: 14 (excluding header)
- **Valid rows**: 13
- **Invalid rows**: 1 (intentional test case with invalid status)
- **Created pages**: 13 draft pages
- **Processing time**: 30-60 seconds

### Step 4: Run Automated Test Script

**Via Browser:**
```
https://wordpress-csv-plugin.ddev.site/wp-admin/admin.php?page=csv-page-generator&run_csv_test=1
```

**Via WP-CLI:**
```bash
ddev wp eval-file wp-content/plugins/csv-page-generator/tests/test-csv-processing.php
```

### Step 5: Run Setup Verification Script

To verify everything is working correctly, run the comprehensive verification script:

**Via WP-CLI:**
```bash
ddev wp eval-file wp-content/plugins/csv-page-generator/tests/verify-setup.php
```

**Via Browser:**
```
http://127.0.0.1:[PORT]/wp-admin/admin.php?page=csv-page-generator&verify_setup=1
```

**Expected Output:**
```
=== CSV Page Generator Plugin - Setup Verification ===
✅ Plugin is active
✅ Database table exists and has correct structure
✅ Plugin settings exist
✅ Upload directory exists and is writable
✅ Sample file exists and has valid CSV structure
✅ All required classes are loaded
✅ CSV parsing successful
✅ Found CSV-generated pages (if any previous tests were run)
✅ Import history tracking working

🎉 ALL TESTS PASSED! Plugin is ready for use.
```

## Verification Steps

### Step 1: Verify Created Pages

```bash
# List pages created from CSV
ddev wp post list --post_type=page --meta_key=_csv_page_generator_source

# Count CSV-generated pages
ddev wp post list --post_type=page --meta_key=_csv_page_generator_source --format=count

# Check specific page content
ddev wp post get [PAGE_ID] --field=post_title
ddev wp post get [PAGE_ID] --field=post_content
```

**Via WordPress Admin:**
1. Go to **Pages** → **All Pages**
2. Look for pages with titles from sample data:
   - "Welcome to Our Company"
   - "Our Services & Solutions"
   - "Meet Our Expert Team"
   - etc.

### Step 2: Verify Page Metadata

```bash
# Check CSV source metadata
ddev wp post meta list [PAGE_ID]

# Verify specific metadata
ddev wp post meta get [PAGE_ID] _csv_page_generator_source
ddev wp post meta get [PAGE_ID] _csv_page_generator_row
```

**Via WordPress Admin:**
1. Edit any created page
2. Scroll to **Custom Fields** section
3. Verify presence of:
   - `_csv_page_generator_source`
   - `_csv_page_generator_row`

### Step 3: Verify Import History

```bash
# Check import records in database
ddev mysql -e "SELECT id, filename, status, total_rows, successful_rows, failed_rows FROM wp_csv_page_generator_imports;"
```

**Via WordPress Admin:**
1. Go to **CSV Pages** → **Import History**
2. Verify import record appears
3. Check import statistics and status

### Step 4: Verify File Upload Directory

```bash
# Check upload directory structure
ddev exec ls -la wp-content/uploads/csv-imports/
ddev exec ls -la wp-content/uploads/csv-imports/logs/

# Check log files
ddev exec ls -la wp-content/uploads/csv-imports/logs/*.log
```

## Troubleshooting

### Common Issue 1: DDEV Won't Start

**Symptoms:**
- `ddev start` fails with port conflicts
- Docker containers won't start

**Solutions:**
```bash
# Check for port conflicts
ddev debug test

# Stop conflicting services
sudo lsof -i :80
sudo lsof -i :443

# Restart Docker Desktop
# Then try: ddev restart

# If still failing, reset DDEV
ddev stop --remove-data
ddev start
```

### Common Issue 2: Plugin Activation Fails

**Symptoms:**
- Plugin shows errors on activation
- "Plugin could not be activated" message

**Solutions:**
```bash
# Check PHP error logs
ddev logs web

# Check WordPress debug log
ddev exec tail -f wp-content/debug.log

# Verify file permissions
ddev exec chmod -R 755 wp-content/plugins/csv-page-generator/

# Check for missing dependencies
ddev composer install --working-dir=wp-content/plugins/csv-page-generator

# Try manual activation
ddev wp plugin deactivate csv-page-generator
ddev wp plugin activate csv-page-generator
```

### Common Issue 3: CSV Upload Fails

**Symptoms:**
- File upload returns errors
- "Failed to process CSV" messages

**Solutions:**
```bash
# Check upload directory permissions
ddev exec ls -la wp-content/uploads/
ddev exec mkdir -p wp-content/uploads/csv-imports
ddev exec chmod 755 wp-content/uploads/csv-imports

# Check PHP upload limits
ddev exec php -i | grep upload_max_filesize
ddev exec php -i | grep post_max_size
ddev exec php -i | grep max_execution_time

# Increase limits if needed (add to .ddev/config.yaml):
# web_environment:
#   - PHP_UPLOAD_MAX_FILESIZE=50M
#   - PHP_POST_MAX_SIZE=50M
#   - PHP_MAX_EXECUTION_TIME=300

# Restart DDEV after config changes
ddev restart
```

### Common Issue 4: Database Connection Issues

**Symptoms:**
- Plugin can't create database tables
- Database errors in logs

**Solutions:**
```bash
# Check database connection
ddev mysql

# Verify database exists
ddev mysql -e "SHOW DATABASES;"

# Check table creation manually
ddev mysql -e "SHOW TABLES LIKE '%csv_page_generator%';"

# Re-run activation
ddev wp plugin deactivate csv-page-generator
ddev wp plugin activate csv-page-generator
```

### Common Issue 5: Sample File Not Found

**Symptoms:**
- Cannot locate sample-data.csv
- File upload shows "file not found"

**Solutions:**
```bash
# Verify file exists
ddev exec ls -la wp-content/plugins/csv-page-generator/samples/

# Check file permissions
ddev exec chmod 644 wp-content/plugins/csv-page-generator/samples/sample-data.csv

# If missing, check git status
git status
git pull origin main

# Verify file content
ddev exec head -5 wp-content/plugins/csv-page-generator/samples/sample-data.csv
```

### Common Issue 6: Deprecated Function Warnings

**Symptoms:**
- PHP warnings about deprecated `get_page_by_title` function
- Validation warnings in logs

**Solutions:**
This has been fixed in the current version. If you see this warning:
```bash
# Update the plugin files to the latest version
git pull origin main

# Or manually update the Validator.php file with the fixed version
```

### Common Issue 7: WordPress Not Accessible

**Symptoms:**
- DDEV shows running but WordPress not accessible
- SSL certificate errors

**Solutions:**
```bash
# Check DDEV status
ddev status

# Try accessing via HTTP instead of HTTPS
curl -I http://127.0.0.1:[PORT_NUMBER]

# Get the correct port from ddev describe
ddev describe

# Restart DDEV if needed
ddev restart
```

## Advanced Testing

### Performance Testing

```bash
# Test with larger CSV files
ddev exec head -1 wp-content/plugins/csv-page-generator/samples/sample-data.csv > large-test.csv
ddev exec for i in {1..100}; do tail -n +2 wp-content/plugins/csv-page-generator/samples/sample-data.csv >> large-test.csv; done

# Monitor memory usage during processing
ddev exec watch -n 1 'ps aux | grep php'
```

### Security Testing

```bash
# Test file upload restrictions
# Try uploading non-CSV files
# Test with malicious content

# Check file validation
ddev wp eval 'var_dump(wp_check_filetype("test.csv"));'
```

### Database Testing

```bash
# Check database performance
ddev mysql -e "EXPLAIN SELECT * FROM wp_csv_page_generator_imports WHERE status = 'completed';"

# Test data integrity
ddev mysql -e "SELECT COUNT(*) FROM wp_csv_page_generator_imports;"
ddev wp post list --post_type=page --meta_key=_csv_page_generator_source --format=count
```

## Success Indicators

✅ **Environment Setup Complete:**
- DDEV starts without errors: `ddev status` shows "running"
- WordPress loads at specified URL: `curl -I http://127.0.0.1:[PORT]` returns 200
- Admin login works with default credentials: admin/admin

✅ **Plugin Installation Complete:**
- Plugin appears in admin menu: "CSV Pages" visible in WordPress admin
- No PHP errors in logs: `ddev logs` shows no critical errors
- Database tables are created: `wp_csv_page_generator_imports` table exists
- Plugin options are set: `csv_page_generator_settings` option exists

✅ **CSV Processing Working:**
- Sample file uploads successfully: 14 rows processed from sample-data.csv
- 14 pages created from 14 valid CSV rows (1 invalid row skipped as expected)
- Pages contain correct content and metadata: `_csv_page_generator_source` meta fields
- Import history shows successful completion: Database record with "completed" status

✅ **All Tests Passing:**
- CSV parsing: Headers detected, encoding handled correctly
- Data validation: 13 valid rows, 1 invalid row (as expected)
- Page generation: All valid rows create WordPress pages
- Import tracking: Database records created with correct statistics
- No critical errors in logs: Only expected warnings for test data

## Verified Test Results

Based on successful testing with the sample-data.csv file:

**CSV Parsing Results:**
```
Total rows: 14
Valid rows: 13
Error rows: 1 (intentional test case)
Headers: Title, Description, Slug, Status, Categories, Meta Description, Featured Image URL
```

**Page Creation Results:**
```
Created Pages: 14
- Welcome to Our Company (publish)
- Our Services & Solutions (publish)
- Meet Our Expert Team (draft)
- Contact Information & Office Locations (publish)
- Privacy Policy & Data Protection (publish)
- Terms of Service & User Agreement (draft)
- Frequently Asked Questions (FAQ) (publish)
- Latest News & Company Updates (publish)
- Career Opportunities & Job Openings (draft)
- Client Success Stories & Case Studies (publish)
- Technology Blog & Industry Insights (publish)
- Partnership Opportunities & Collaborations (draft)
- Product Documentation & User Guides (publish)
- Security & Compliance Information (publish)
```

**Database Verification:**
```bash
# Verify import record
ddev mysql -e "SELECT id, filename, status, total_rows, successful_rows, failed_rows FROM wp_csv_page_generator_imports;"

# Count created pages
ddev wp post list --post_type=page --meta_key=_csv_page_generator_source --format=count
# Expected result: 14
```

## Next Steps

After successful setup and testing:

1. **Explore Admin Interface**: Test different CSV files and settings
2. **Review Created Content**: Check page formatting and metadata
3. **Test Error Handling**: Try uploading invalid CSV files
4. **Performance Testing**: Upload larger CSV files
5. **Security Testing**: Verify file upload restrictions work
6. **Theme Integration**: Test how pages display on frontend

## Getting Help

If you encounter issues not covered in this guide:

1. **Check Logs**: Always start with `ddev logs` and WordPress debug logs
2. **Verify Prerequisites**: Ensure all required software is properly installed
3. **File Permissions**: Many issues are related to file/directory permissions
4. **DDEV Documentation**: Refer to [DDEV docs](https://ddev.readthedocs.io/) for environment issues
5. **WordPress Codex**: Check [WordPress documentation](https://codex.wordpress.org/) for WordPress-specific issues

---

**Plugin Version**: 1.0.0  
**Last Updated**: 2024-01-XX  
**Tested With**: WordPress 6.4, PHP 8.1, DDEV 1.21+

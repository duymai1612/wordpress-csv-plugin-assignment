# CSV Page Generator Plugin - Testing Suite

This directory contains testing scripts and utilities for the CSV Page Generator plugin.

## Test Scripts

### `verify-setup.php`
**Purpose**: Comprehensive setup verification script that checks all plugin components.

**Usage**:
```bash
# Via WP-CLI
ddev wp eval-file wp-content/plugins/csv-page-generator/tests/verify-setup.php

# Via browser (must be logged in as admin)
http://127.0.0.1:[PORT]/wp-admin/admin.php?page=csv-page-generator&verify_setup=1
```

**What it tests**:
- ✅ Plugin activation status
- ✅ Database table creation and structure
- ✅ Plugin options and settings
- ✅ Upload directory permissions
- ✅ Sample file availability and format
- ✅ Class loading and autoloading
- ✅ Basic CSV parsing functionality
- ✅ Existing CSV-generated pages
- ✅ Import history tracking

### `test-csv-processing.php`
**Purpose**: Interactive testing script for CSV processing functionality.

**Usage**:
```bash
# Via WP-CLI
ddev wp eval-file wp-content/plugins/csv-page-generator/tests/test-csv-processing.php

# Via browser (must be logged in as admin)
http://127.0.0.1:[PORT]/wp-admin/admin.php?page=csv-page-generator&run_csv_test=1
```

**What it tests**:
- CSV file parsing with sample data
- Data validation and error handling
- Page generation from CSV rows
- Metadata assignment and tracking
- Error logging and reporting

## Running Tests

### Quick Verification
```bash
# Run the setup verification script
ddev wp eval-file wp-content/plugins/csv-page-generator/tests/verify-setup.php
```

### Full CSV Processing Test
```bash
# Test complete CSV processing workflow
ddev wp eval-file wp-content/plugins/csv-page-generator/tests/test-csv-processing.php
```

### Manual Testing Commands
```bash
# Check plugin status
ddev wp plugin status csv-page-generator

# List CSV-generated pages
ddev wp post list --post_type=page --meta_key=_csv_page_generator_source

# Check import history
ddev mysql -e "SELECT * FROM wp_csv_page_generator_imports;"

# Verify database table structure
ddev mysql -e "DESCRIBE wp_csv_page_generator_imports;"
```

## Expected Results

### Setup Verification Results
When `verify-setup.php` runs successfully, you should see:
```
🎉 ALL TESTS PASSED! Plugin is ready for use.
```

### CSV Processing Test Results
When `test-csv-processing.php` runs successfully, you should see:
- **CSV Parsing**: 15 total rows, 15 valid rows, 0 error rows
- **Validation**: 14 valid rows, 1 invalid row (intentional test case)
- **Page Creation**: 3 test pages created successfully

## Troubleshooting Test Failures

### Plugin Not Active
```bash
ddev wp plugin activate csv-page-generator
```

### Database Table Missing
```bash
ddev wp plugin deactivate csv-page-generator
ddev wp plugin activate csv-page-generator
```

### Class Loading Issues
```bash
# Check if all plugin files exist
ddev exec find wp-content/plugins/csv-page-generator/src -name "*.php" -type f

# Verify file permissions
ddev exec chmod -R 755 wp-content/plugins/csv-page-generator/
```

### Upload Directory Issues
```bash
# Create upload directories
ddev exec mkdir -p wp-content/uploads/csv-imports/{temp,processed,logs}
ddev exec chmod -R 755 wp-content/uploads/csv-imports/
```

### Sample File Issues
```bash
# Verify sample file exists and is readable
ddev exec ls -la wp-content/plugins/csv-page-generator/samples/sample-data.csv
ddev exec head -3 wp-content/plugins/csv-page-generator/samples/sample-data.csv
```

## Test Data

The tests use the sample data file located at:
```
wp-content/plugins/csv-page-generator/samples/sample-data.csv
```

This file contains:
- **15 total rows** (including header)
- **14 data rows** with realistic content
- **1 intentionally invalid row** for error testing
- **Various content types**: HTML, special characters, UTF-8 encoding
- **All CSV columns**: Title, Description, Slug, Status, Categories, Meta Description, Featured Image URL

## Integration with CI/CD

These test scripts can be integrated into automated testing pipelines:

```bash
#!/bin/bash
# Example CI test script

# Start DDEV environment
ddev start

# Install WordPress and activate plugin
ddev wp core install --url=https://test.ddev.site --title="Test" --admin_user=admin --admin_password=admin --admin_email=admin@test.com
ddev wp plugin activate csv-page-generator

# Run verification tests
ddev wp eval-file wp-content/plugins/csv-page-generator/tests/verify-setup.php

# Check exit code
if [ $? -eq 0 ]; then
    echo "✅ All tests passed"
    exit 0
else
    echo "❌ Tests failed"
    exit 1
fi
```

## Performance Testing

For performance testing with larger datasets:

```bash
# Create larger test file
ddev exec head -1 wp-content/plugins/csv-page-generator/samples/sample-data.csv > large-test.csv
ddev exec for i in {1..1000}; do tail -n +2 wp-content/plugins/csv-page-generator/samples/sample-data.csv >> large-test.csv; done

# Monitor memory usage during processing
ddev exec watch -n 1 'ps aux | grep php'
```

## Security Testing

Test file upload security:

```bash
# Test file type restrictions
# Try uploading non-CSV files to verify they're rejected

# Test file size limits
# Create files larger than configured limits

# Test malicious content
# Include potentially dangerous content in CSV files
```

---

**Note**: Always run tests in a development environment, not on production sites. The test scripts may create, modify, or delete WordPress content.

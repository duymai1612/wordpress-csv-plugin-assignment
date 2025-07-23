# CSV Sample Data Documentation

This directory contains sample CSV files for testing the CSV Page Generator plugin functionality.

## Sample Files

### `sample-data.csv`

A comprehensive test file containing 15 rows of realistic data designed to test all aspects of the CSV processing engine.

#### File Structure

The sample CSV includes the following columns:

- **Title** (Required) - Page titles with varying lengths and complexity
- **Description** (Required) - Page content with HTML formatting and varied lengths
- **Slug** (Optional) - Custom URL slugs, some empty to test auto-generation
- **Status** (Optional) - Page status (draft, publish, private, invalid_status for testing)
- **Categories** (Optional) - Comma-separated category names
- **Meta Description** (Optional) - SEO meta descriptions with length variations
- **Featured Image URL** (Optional) - Image URLs for featured images, including invalid URLs for testing

#### Test Scenarios Included

1. **Valid Standard Pages** (Rows 1-13)
   - Various content lengths and complexities
   - Mix of published and draft statuses
   - Different category assignments
   - Some with featured images, some without
   - Mix of custom slugs and auto-generated ones

2. **Edge Case Testing** (Row 14)
   - Extremely long title with special characters
   - UTF-8 characters including emojis, accented characters, and non-Latin scripts
   - HTML content with various formatting
   - Invalid post status for validation testing
   - Invalid category names with spaces
   - Overly long meta description
   - Invalid image URL format

3. **Character Encoding Tests**
   - UTF-8 BOM handling
   - International characters: áéíóú, çñ, ¿¡, €£¥
   - Emojis: 🚀🎉🔥💡
   - Non-Latin scripts: 中文 (Chinese), العربية (Arabic)

4. **Content Complexity Tests**
   - HTML formatting: `<p>`, `<ul>`, `<li>`, `<h3>`, `<strong>`, `<em>`, `<a>`
   - Long paragraphs and structured content
   - Special characters in URLs and text
   - Empty optional fields

## Using the Sample Data

### For Development Testing

1. **Upload via Admin Interface**
   ```
   WordPress Admin → CSV Pages → Upload CSV
   Select: wp-content/plugins/csv-page-generator/samples/sample-data.csv
   ```

2. **Programmatic Testing**
   ```php
   // Access the test script
   /wp-admin/admin.php?page=csv-page-generator&run_csv_test=1
   ```

3. **Manual Testing with WP-CLI** (if available)
   ```bash
   wp eval-file wp-content/plugins/csv-page-generator/tests/test-csv-processing.php
   ```

### Expected Results

When processing `sample-data.csv`, you should expect:

- **Total Rows**: 15 (including 1 header row = 14 data rows)
- **Valid Rows**: 13 (rows 1-13 should pass validation)
- **Invalid Rows**: 1 (row 14 should fail validation due to invalid status)
- **Warnings**: Several (long title, long meta description, invalid image URL)
- **Created Pages**: 13 draft pages with various content and metadata

### Validation Test Results

The sample data is designed to test these validation scenarios:

#### ✅ Should Pass Validation
- Standard titles and descriptions
- Valid post statuses (draft, publish, private)
- Proper slug formats
- Valid image URLs
- Reasonable content lengths

#### ⚠️ Should Generate Warnings
- Very long titles (>255 characters)
- Long meta descriptions (>160 characters)
- Invalid image URL formats
- Unusual category names

#### ❌ Should Fail Validation
- Invalid post status ("invalid_status")
- Missing required fields (if any rows had empty title/description)

## File Format Specifications

### Encoding
- **Character Encoding**: UTF-8 with BOM
- **Line Endings**: Unix-style (LF)
- **Delimiter**: Comma (,)
- **Text Qualifier**: Double quotes (")

### Content Guidelines

1. **Title Field**
   - Maximum length: 255 characters
   - HTML tags will be stripped
   - Special characters are allowed

2. **Description Field**
   - Maximum length: 65,535 characters
   - HTML formatting is preserved
   - Allowed HTML tags: p, br, strong, em, ul, ol, li, h1-h6, a, img

3. **Slug Field**
   - Maximum length: 200 characters
   - Must contain only lowercase letters, numbers, and hyphens
   - Auto-generated from title if empty

4. **Status Field**
   - Allowed values: draft, publish, private, pending
   - Defaults to 'draft' if empty or invalid

5. **Categories Field**
   - Comma-separated list
   - Each category name maximum 50 characters
   - Will be created if they don't exist

6. **Meta Description Field**
   - Recommended maximum: 160 characters for SEO
   - Longer descriptions will generate warnings

7. **Featured Image URL Field**
   - Must be a valid HTTP/HTTPS URL
   - Must end with image extension (.jpg, .jpeg, .png, .gif, .webp)
   - Images will be downloaded and added to media library

## Creating Your Own Test Data

### Minimal CSV Structure
```csv
Title,Description
"Test Page 1","This is a simple test page with minimal content."
"Test Page 2","This is another test page with <strong>HTML formatting</strong>."
```

### Complete CSV Structure
```csv
Title,Description,Slug,Status,Categories,Meta Description,Featured Image URL
"Complete Test Page","<p>This page has all optional fields filled out.</p>","complete-test","publish","Test,Sample","A complete test page with all fields","https://example.com/image.jpg"
```

### Best Practices

1. **Always include headers** in the first row
2. **Use double quotes** around field values containing commas or special characters
3. **Escape double quotes** within field values by doubling them ("")
4. **Test with small files first** before processing large datasets
5. **Validate URLs** before including them in the Featured Image URL field
6. **Keep meta descriptions under 160 characters** for optimal SEO

## Troubleshooting

### Common Issues

1. **"Required headers missing"**
   - Ensure your CSV has "Title" and "Description" columns
   - Check for typos in header names
   - Verify the first row contains headers, not data

2. **"File encoding issues"**
   - Save your CSV as UTF-8 encoding
   - Remove any BOM if causing issues
   - Check for special characters that might not be properly encoded

3. **"Invalid post status"**
   - Use only: draft, publish, private, pending
   - Leave empty to use default (draft)

4. **"Slug already exists"**
   - The plugin will automatically generate unique slugs
   - This is a warning, not an error

5. **"Featured image download failed"**
   - Verify the image URL is accessible
   - Check image file format is supported
   - Ensure the server can download external images

### Performance Considerations

- **Large files**: Files with >1000 rows will be processed in batches
- **Memory usage**: Monitor memory usage with very large files
- **Processing time**: Allow extra time for files with many featured images
- **Server limits**: Check PHP upload limits and execution time limits

## Security Notes

The sample data includes some potentially problematic content for security testing:

- Long content that could cause memory issues
- Special characters that might cause encoding problems
- Invalid URLs that should be properly handled
- HTML content that should be properly sanitized

This is intentional to ensure the plugin handles edge cases gracefully and securely.

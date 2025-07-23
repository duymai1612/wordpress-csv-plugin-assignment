<?php
/**
 * Admin Upload Form Template
 *
 * @package ReasonDigital\CSVPageGenerator
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get plugin settings
$settings = get_option( 'csv_page_generator_settings', array() );
$max_file_size = $settings['max_file_size'] ?? 10485760; // 10MB default
$max_rows = $settings['max_rows'] ?? 10000;
?>

<div class="wrap">
	<h1><?php esc_html_e( 'CSV Page Generator', 'csv-page-generator' ); ?></h1>
	
	<div class="csv-page-generator-admin">
		<div class="upload-section">
			<div class="card">
				<h2><?php esc_html_e( 'Upload CSV File', 'csv-page-generator' ); ?></h2>
				<p><?php esc_html_e( 'Upload a CSV file to automatically generate WordPress pages. The CSV should contain at least "Title" and "Description" columns.', 'csv-page-generator' ); ?></p>
				
				<form id="csv-upload-form" method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'csv_page_generator_upload', 'csv_upload_nonce' ); ?>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="csv_file"><?php esc_html_e( 'CSV File', 'csv-page-generator' ); ?></label>
							</th>
							<td>
								<div class="csv-file-upload">
									<input type="file" 
										   id="csv_file" 
										   name="csv_file" 
										   accept=".csv" 
										   required 
										   data-max-size="<?php echo esc_attr( $max_file_size ); ?>" />
									<div class="file-upload-info">
										<p class="description">
											<?php
											printf(
												/* translators: 1: Maximum file size, 2: Maximum rows */
												esc_html__( 'Maximum file size: %1$s. Maximum rows: %2$s.', 'csv-page-generator' ),
												size_format( $max_file_size ),
												number_format( $max_rows )
											);
											?>
										</p>
									</div>
								</div>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="post_status"><?php esc_html_e( 'Page Status', 'csv-page-generator' ); ?></label>
							</th>
							<td>
								<select id="post_status" name="post_status">
									<option value="draft" <?php selected( $settings['default_post_status'] ?? 'draft', 'draft' ); ?>>
										<?php esc_html_e( 'Draft', 'csv-page-generator' ); ?>
									</option>
									<option value="publish" <?php selected( $settings['default_post_status'] ?? 'draft', 'publish' ); ?>>
										<?php esc_html_e( 'Published', 'csv-page-generator' ); ?>
									</option>
									<option value="private" <?php selected( $settings['default_post_status'] ?? 'draft', 'private' ); ?>>
										<?php esc_html_e( 'Private', 'csv-page-generator' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Status for the generated pages.', 'csv-page-generator' ); ?>
								</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="post_author"><?php esc_html_e( 'Page Author', 'csv-page-generator' ); ?></label>
							</th>
							<td>
								<?php
								wp_dropdown_users(
									array(
										'name'             => 'post_author',
										'id'               => 'post_author',
										'selected'         => $settings['default_post_author'] ?? get_current_user_id(),
										'include_selected' => true,
										'show_option_none' => __( 'Current User', 'csv-page-generator' ),
									)
								);
								?>
								<p class="description">
									<?php esc_html_e( 'Author for the generated pages.', 'csv-page-generator' ); ?>
								</p>
							</td>
						</tr>
					</table>
					
					<div class="csv-upload-actions">
						<button type="submit" class="button button-primary" id="upload-csv-btn">
							<?php esc_html_e( 'Upload and Process CSV', 'csv-page-generator' ); ?>
						</button>
						<span class="spinner"></span>
					</div>
				</form>
			</div>
		</div>
		
		<div class="progress-section" id="upload-progress" style="display: none;">
			<div class="card">
				<h3><?php esc_html_e( 'Import Progress', 'csv-page-generator' ); ?></h3>
				<div class="progress-bar-container">
					<div class="progress-bar">
						<div class="progress-fill" style="width: 0%;"></div>
					</div>
					<div class="progress-text">
						<span class="progress-percentage">0%</span>
						<span class="progress-status"><?php esc_html_e( 'Preparing...', 'csv-page-generator' ); ?></span>
					</div>
				</div>
				<div class="progress-details">
					<p class="processed-count">
						<?php esc_html_e( 'Processed: 0 of 0 rows', 'csv-page-generator' ); ?>
					</p>
					<p class="success-count">
						<?php esc_html_e( 'Successful: 0', 'csv-page-generator' ); ?>
					</p>
					<p class="error-count">
						<?php esc_html_e( 'Errors: 0', 'csv-page-generator' ); ?>
					</p>
				</div>
				<div class="progress-actions">
					<button type="button" class="button" id="cancel-import-btn">
						<?php esc_html_e( 'Cancel Import', 'csv-page-generator' ); ?>
					</button>
				</div>
			</div>
		</div>
		
		<div class="results-section" id="import-results" style="display: none;">
			<div class="card">
				<h3><?php esc_html_e( 'Import Results', 'csv-page-generator' ); ?></h3>
				<div class="results-summary">
					<!-- Results will be populated via JavaScript -->
				</div>
				<div class="results-actions">
					<button type="button" class="button button-primary" id="view-created-pages-btn">
						<?php esc_html_e( 'View Created Pages', 'csv-page-generator' ); ?>
					</button>
					<button type="button" class="button" id="download-log-btn">
						<?php esc_html_e( 'Download Import Log', 'csv-page-generator' ); ?>
					</button>
					<button type="button" class="button" id="start-new-import-btn">
						<?php esc_html_e( 'Start New Import', 'csv-page-generator' ); ?>
					</button>
				</div>
			</div>
		</div>
		
		<div class="help-section">
			<div class="card">
				<h3><?php esc_html_e( 'CSV Format Requirements', 'csv-page-generator' ); ?></h3>
				<div class="help-content">
					<h4><?php esc_html_e( 'Required Columns:', 'csv-page-generator' ); ?></h4>
					<ul>
						<li><strong>Title</strong> - <?php esc_html_e( 'The page title (required)', 'csv-page-generator' ); ?></li>
						<li><strong>Description</strong> - <?php esc_html_e( 'The page content (required)', 'csv-page-generator' ); ?></li>
					</ul>
					
					<h4><?php esc_html_e( 'Optional Columns:', 'csv-page-generator' ); ?></h4>
					<ul>
						<li><strong>Slug</strong> - <?php esc_html_e( 'Custom URL slug (auto-generated if empty)', 'csv-page-generator' ); ?></li>
						<li><strong>Status</strong> - <?php esc_html_e( 'Page status (draft, publish, private)', 'csv-page-generator' ); ?></li>
						<li><strong>Categories</strong> - <?php esc_html_e( 'Comma-separated category names', 'csv-page-generator' ); ?></li>
						<li><strong>Meta Description</strong> - <?php esc_html_e( 'SEO meta description', 'csv-page-generator' ); ?></li>
					</ul>
					
					<h4><?php esc_html_e( 'Example CSV Format:', 'csv-page-generator' ); ?></h4>
					<pre><code>Title,Description,Slug,Status
"Sample Page 1","This is the content for page 1","sample-page-1","draft"
"Sample Page 2","This is the content for page 2","sample-page-2","publish"</code></pre>
					
					<p class="description">
						<?php esc_html_e( 'Make sure your CSV file is UTF-8 encoded and uses comma separators.', 'csv-page-generator' ); ?>
					</p>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
.csv-page-generator-admin .card {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
}

.csv-page-generator-admin .card h2,
.csv-page-generator-admin .card h3 {
	margin-top: 0;
}

.csv-file-upload {
	position: relative;
}

.progress-bar-container {
	margin: 15px 0;
}

.progress-bar {
	width: 100%;
	height: 20px;
	background-color: #f0f0f1;
	border-radius: 10px;
	overflow: hidden;
}

.progress-fill {
	height: 100%;
	background-color: #00a32a;
	transition: width 0.3s ease;
}

.progress-text {
	display: flex;
	justify-content: space-between;
	margin-top: 5px;
	font-size: 14px;
}

.progress-details p {
	margin: 5px 0;
	font-size: 14px;
}

.results-summary {
	margin: 15px 0;
}

.help-content ul {
	margin-left: 20px;
}

.help-content pre {
	background: #f6f7f7;
	padding: 15px;
	border-radius: 4px;
	overflow-x: auto;
}

.csv-upload-actions,
.progress-actions,
.results-actions {
	margin-top: 20px;
}

.csv-upload-actions .spinner {
	float: none;
	margin-left: 10px;
}
</style>

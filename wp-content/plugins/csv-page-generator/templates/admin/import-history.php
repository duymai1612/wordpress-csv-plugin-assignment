<?php
/**
 * Admin Import History Template
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

// Variables available from AdminPage::display_history_page():
// $imports - array of import records
// $current_page - current page number
// $total_pages - total number of pages
// $total_items - total number of import records
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Import History', 'csv-page-generator' ); ?></h1>
	
	<?php if ( empty( $imports ) ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'No CSV imports have been performed yet.', 'csv-page-generator' ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=csv-page-generator' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Upload Your First CSV', 'csv-page-generator' ); ?>
				</a>
			</p>
		</div>
	<?php else : ?>
		
		<div class="csv-history-stats">
			<div class="stats-grid">
				<div class="stat-card">
					<h3><?php echo esc_html( number_format( $total_items ) ); ?></h3>
					<p><?php esc_html_e( 'Total Imports', 'csv-page-generator' ); ?></p>
				</div>
				<div class="stat-card">
					<h3><?php echo esc_html( number_format( array_sum( wp_list_pluck( $imports, 'successful_rows' ) ) ) ); ?></h3>
					<p><?php esc_html_e( 'Pages Created', 'csv-page-generator' ); ?></p>
				</div>
				<div class="stat-card">
					<h3><?php echo esc_html( number_format( array_sum( wp_list_pluck( $imports, 'total_rows' ) ) ) ); ?></h3>
					<p><?php esc_html_e( 'Rows Processed', 'csv-page-generator' ); ?></p>
				</div>
				<div class="stat-card">
					<?php 
					$completed_imports = array_filter( $imports, function( $import ) { return $import->status === 'completed'; } );
					$success_rate = $total_items > 0 ? ( count( $completed_imports ) / $total_items ) * 100 : 0;
					?>
					<h3><?php echo esc_html( number_format( $success_rate, 1 ) ); ?>%</h3>
					<p><?php esc_html_e( 'Success Rate', 'csv-page-generator' ); ?></p>
				</div>
			</div>
		</div>

		<div class="csv-history-table">
			<div class="tablenav top">
				<div class="alignleft actions">
					<span class="displaying-num">
						<?php
						printf(
							esc_html( _n( '%s item', '%s items', $total_items, 'csv-page-generator' ) ),
							number_format_i18n( $total_items )
						);
						?>
					</span>
				</div>
				
				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav-pages">
						<?php
						$page_links = paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => __( '&laquo;', 'csv-page-generator' ),
							'next_text' => __( '&raquo;', 'csv-page-generator' ),
							'total'     => $total_pages,
							'current'   => $current_page,
							'type'      => 'plain',
						) );
						echo $page_links;
						?>
					</div>
				<?php endif; ?>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-id"><?php esc_html_e( 'ID', 'csv-page-generator' ); ?></th>
						<th scope="col" class="manage-column column-filename"><?php esc_html_e( 'Filename', 'csv-page-generator' ); ?></th>
						<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'csv-page-generator' ); ?></th>
						<th scope="col" class="manage-column column-rows"><?php esc_html_e( 'Total Rows', 'csv-page-generator' ); ?></th>
						<th scope="col" class="manage-column column-success"><?php esc_html_e( 'Successful', 'csv-page-generator' ); ?></th>
						<th scope="col" class="manage-column column-failed"><?php esc_html_e( 'Failed', 'csv-page-generator' ); ?></th>
						<th scope="col" class="manage-column column-size"><?php esc_html_e( 'File Size', 'csv-page-generator' ); ?></th>
						<th scope="col" class="manage-column column-date"><?php esc_html_e( 'Date', 'csv-page-generator' ); ?></th>
						<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'csv-page-generator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $imports as $import ) : ?>
						<tr>
							<td class="column-id">
								<strong><?php echo esc_html( $import->id ); ?></strong>
							</td>
							<td class="column-filename">
								<strong><?php echo esc_html( $import->original_filename ); ?></strong>
								<?php if ( $import->original_filename !== $import->filename ) : ?>
									<br><small class="description"><?php echo esc_html( $import->filename ); ?></small>
								<?php endif; ?>
							</td>
							<td class="column-status">
								<span class="status-badge status-<?php echo esc_attr( $import->status ); ?>">
									<?php echo esc_html( ucfirst( $import->status ) ); ?>
								</span>
							</td>
							<td class="column-rows">
								<?php echo esc_html( number_format( $import->total_rows ) ); ?>
							</td>
							<td class="column-success">
								<span class="success-count">
									<?php echo esc_html( number_format( $import->successful_rows ) ); ?>
								</span>
								<?php if ( $import->total_rows > 0 ) : ?>
									<br><small class="description">
										<?php echo esc_html( number_format( ( $import->successful_rows / $import->total_rows ) * 100, 1 ) ); ?>%
									</small>
								<?php endif; ?>
							</td>
							<td class="column-failed">
								<?php if ( $import->failed_rows > 0 ) : ?>
									<span class="failed-count error">
										<?php echo esc_html( number_format( $import->failed_rows ) ); ?>
									</span>
								<?php else : ?>
									<span class="failed-count">0</span>
								<?php endif; ?>
							</td>
							<td class="column-size">
								<?php echo esc_html( size_format( $import->file_size ) ); ?>
							</td>
							<td class="column-date">
								<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $import->started_at ) ) ); ?></strong>
								<br><?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $import->started_at ) ) ); ?>
								<?php if ( $import->completed_at ) : ?>
									<br><small class="description">
										<?php
										$duration = strtotime( $import->completed_at ) - strtotime( $import->started_at );
										printf( esc_html__( 'Duration: %s', 'csv-page-generator' ), human_time_diff( 0, $duration ) );
										?>
									</small>
								<?php endif; ?>
							</td>
							<td class="column-actions">
								<div class="row-actions">
									<?php if ( ! empty( $import->created_pages ) ) : ?>
										<?php $created_pages = maybe_unserialize( $import->created_pages ); ?>
										<?php if ( is_array( $created_pages ) && ! empty( $created_pages ) ) : ?>
											<span class="view-pages">
												<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page&meta_key=_csv_import_id&meta_value=' . $import->id ) ); ?>">
													<?php esc_html_e( 'View Pages', 'csv-page-generator' ); ?>
												</a>
											</span>
										<?php endif; ?>
									<?php endif; ?>
									
									<?php if ( ! empty( $import->error_log ) ) : ?>
										<span class="view-errors">
											| <a href="#" class="view-error-log" data-import-id="<?php echo esc_attr( $import->id ); ?>">
												<?php esc_html_e( 'View Errors', 'csv-page-generator' ); ?>
											</a>
										</span>
									<?php endif; ?>
								</div>
								
								<?php if ( ! empty( $import->error_log ) ) : ?>
									<div id="error-log-<?php echo esc_attr( $import->id ); ?>" class="error-log-details" style="display: none;">
										<h4><?php esc_html_e( 'Error Log', 'csv-page-generator' ); ?></h4>
										<pre><?php echo esc_html( $import->error_log ); ?></pre>
									</div>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php echo $page_links; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

	<?php endif; ?>
</div>



<script>
jQuery(document).ready(function($) {
	$('.view-error-log').on('click', function(e) {
		e.preventDefault();
		var importId = $(this).data('import-id');
		$('#error-log-' + importId).toggle();
	});
});
</script>

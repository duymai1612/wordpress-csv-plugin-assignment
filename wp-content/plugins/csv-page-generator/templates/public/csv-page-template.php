<?php
/**
 * Custom template for CSV-generated pages
 * 
 * This template provides a clean, professional display for pages created
 * from CSV imports, with optional metadata display for administrative purposes.
 *
 * @package ReasonDigital\CSVPageGenerator
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header(); ?>

<div class="csv-page-wrapper">
    <?php while ( have_posts() ) : the_post(); ?>
        
        <article id="post-<?php the_ID(); ?>" <?php post_class( 'csv-generated-page' ); ?>>
            
            <!-- Page Header -->
            <header class="page-header">
                <h1 class="page-title"><?php the_title(); ?></h1>
                
                <?php if ( has_excerpt() ) : ?>
                    <div class="page-excerpt">
                        <?php the_excerpt(); ?>
                    </div>
                <?php endif; ?>
            </header>

            <!-- Main Content -->
            <div class="page-content">
                <?php
                the_content();
                
                wp_link_pages( array(
                    'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'csv-page-generator' ),
                    'after'  => '</div>',
                ) );
                ?>
            </div>

            <!-- CSV Metadata Section (Collapsible) -->
            <?php if ( current_user_can( 'edit_posts' ) ) : ?>
                <div class="csv-metadata-section">
                    <button type="button" class="csv-metadata-toggle" aria-expanded="false">
                        <span class="toggle-text"><?php esc_html_e( 'Show Technical Details', 'csv-page-generator' ); ?></span>
                        <span class="toggle-icon">▼</span>
                    </button>
                    
                    <div class="csv-metadata-content" style="display: none;">
                        <h3><?php esc_html_e( 'CSV Import Information', 'csv-page-generator' ); ?></h3>
                        
                        <?php
                        $import_id = get_post_meta( get_the_ID(), '_csv_page_generator_source', true );
                        $row_number = get_post_meta( get_the_ID(), '_csv_page_generator_row', true );
                        ?>
                        
                        <div class="metadata-grid">
                            <?php if ( $import_id ) : ?>
                                <div class="metadata-item">
                                    <strong><?php esc_html_e( 'Import ID:', 'csv-page-generator' ); ?></strong>
                                    <span><?php echo esc_html( $import_id ); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ( $row_number !== '' ) : ?>
                                <div class="metadata-item">
                                    <strong><?php esc_html_e( 'CSV Row:', 'csv-page-generator' ); ?></strong>
                                    <span><?php echo esc_html( $row_number ); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="metadata-item">
                                <strong><?php esc_html_e( 'Created:', 'csv-page-generator' ); ?></strong>
                                <span><?php echo esc_html( get_the_date() ); ?></span>
                            </div>
                            
                            <div class="metadata-item">
                                <strong><?php esc_html_e( 'Last Modified:', 'csv-page-generator' ); ?></strong>
                                <span><?php echo esc_html( get_the_modified_date() ); ?></span>
                            </div>
                        </div>
                        
                        <?php if ( current_user_can( 'edit_post', get_the_ID() ) ) : ?>
                            <div class="metadata-actions">
                                <a href="<?php echo esc_url( get_edit_post_link() ); ?>" class="button">
                                    <?php esc_html_e( 'Edit Page', 'csv-page-generator' ); ?>
                                </a>
                                
                                <?php if ( $import_id ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page&meta_key=_csv_page_generator_source&meta_value=' . $import_id ) ); ?>" class="button">
                                        <?php esc_html_e( 'View All Pages from This Import', 'csv-page-generator' ); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Page Footer -->
            <footer class="page-footer">
                <?php
                // Display tags if any
                $tags = get_the_tags();
                if ( $tags ) :
                ?>
                    <div class="page-tags">
                        <strong><?php esc_html_e( 'Tags:', 'csv-page-generator' ); ?></strong>
                        <?php the_tags( '', ', ', '' ); ?>
                    </div>
                <?php endif; ?>
            </footer>

        </article>

    <?php endwhile; ?>
</div>

<?php get_footer(); ?>

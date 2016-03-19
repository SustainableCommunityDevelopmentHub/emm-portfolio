<?php
/**
* Plugin Name: emm-portfolio
* Plugin URI: http://www.emm-gfx.net
* Description: Enable portfolio features on some themes.
* Version: 1.1
* Author: Josep Viciana
* Author URI: http://emm-gfx.net
* License: GPL2
*/
add_action( 'init', 'create_post_type' );
add_action( 'add_meta_boxes', 'page_meta_boxes' );
add_action( 'save_post', 'emm_portfolio_save_metadata' );
add_action( 'pre_get_posts', 'emm_modify_portfolio_posts_per_page' );
add_action( 'admin_menu' , 'emm_portfolio_settings_menu' );

function create_post_type() {
	register_post_type( 'emm_portfolio',
		array(
			'labels' => array(
				'name' => __( 'Projects' ),
				'singular_name' => __( 'Project' )
			),
			'public' => true,
			'has_archive' => true,
			'rewrite' => array('slug' => 'project'),
			# Add 'thumbnail' support when the option were enabled.
			'supports' => array('title', 'editor', 'thumbnail')
		)
	);

	register_taxonomy( 'project_categories', 'emm_portfolio', array(
		'label' => __( 'Categories' ),
		'rewrite' => array( 'slug' => 'projects/category'),
	));

	register_taxonomy( 'project_technologies', 'emm_portfolio', array(
		'label' => __( 'Technologies' ),
		'rewrite' => array( 'slug' => 'projects/technology' ),
	));

	register_taxonomy( 'project_tools', 'emm_portfolio', array(
		'label' => __( 'Tools' ),
		'rewrite' => array( 'slug' => 'projects/tool' ),
	));

}

function emm_modify_portfolio_posts_per_page( $query ) {
	if(
		!is_admin() &&
		(
			is_tax('project_categories') ||
			is_tax('project_technologies') ||
			is_tax('project_tools')
		) &&
		$query->is_main_query()
	){
		$projects_per_page = intval(get_option('portfolio_projects_per_page'));
		if($projects_per_page == false && $projects_per_page > 0)
			$projects_per_page = get_option('posts_per_page');

		$query->set('posts_per_page', $projects_per_page);
	}

}

function page_meta_boxes(){

    global $_wp_post_type_features;

	add_meta_box(
        $id     	= 'page_heading_meta_box',
        $title		= __('Images'),
        $callback   = 'render_emm_portfolio_images_metabox',
        $post_type  = 'emm_portfolio',
        $context    = 'normal',
        $priority   = 'core'
    );

	add_meta_box(
        $id     	= 'page_heading_meta_box2',
        $title		= __('Links'),
        $callback   = 'render_emm_portfolio_links_metabox',
        $post_type  = 'emm_portfolio',
        $context    = 'normal',
        $priority   = 'core'
    );
}

function render_emm_portfolio_images_metabox($post){
	?>
	<div class="uploader">
		<?php
		wp_nonce_field( 'emm_portfolio_images_metabox_data', 'emm_portfolio_images_metabox_nonce' );
		$images_json = get_post_meta( $post->ID, 'emm_portfolio_images_order', true );
		if(!is_array(@json_decode($images_json, true)))
			$images_json = json_encode(array());
		?>

		<p><a href="#" class="upload_image_button button button-primary">Add images to project</a></p>
		<div class="images-list">
			<?php foreach(json_decode($images_json) as $attachment_id): ?>

				<?php
				$attachment_meta = wp_get_attachment_metadata($attachment_id);
				if($attachment_meta == false)
					continue;
				?>
				<div class="image" data-id="<?php echo $attachment_id; ?>">
					<img src="<?php echo wp_get_attachment_thumb_url($attachment_id); ?>" />
					<a href="#" class="button delete">Remove</a>
				</div>
			<?php endforeach; ?>
		</div>

		<input type="hidden" id="emm_portfolio_images_order" name="emm_portfolio_images_order" value="<?php echo esc_attr( $images_json ); ?> " />
	</div>
	<style>
		.images-list{
			margin-top: 20px;
			overflow: auto;
		}
		.images-list .image{
			margin: 0 15px 15px 0;
		    padding: 5px;
		    background: #FFF;
		    border: 1px solid #DCDCDC;
			border-radius: 2px;
			float: left;
		}
		.images-list .image img{
			width: 150px;
			height: 150px;
		}
		.images-list a.delete{
			display: block;
			text-align: center;
		}
	</style>
	<script>
	jQuery(document).ready(function() {

		var $ = jQuery;

		var file_frame;

		$(document).on('click', '.upload_image_button', function( event ){

			event.preventDefault();

			if ( file_frame ) {
				file_frame.open();
				return;
			}

			file_frame = wp.media.frames.file_frame = wp.media({
				title: jQuery( this ).data( 'uploader_title' ),
				button: {
					text: jQuery( this ).data( 'uploader_button_text' ),
				},
				multiple: true
			});

			file_frame.on( 'select', function() {
				attachments = file_frame.state().get('selection').toJSON();

				for(i = 0; i < attachments.length; i++){

					var image = '<img src="' + attachments[i].sizes.thumbnail.url+ '" />';
					var bt_delete = '<a href="#" class="button delete">Remove</a>';
					var container = '<div class="image" data-id="' + attachments[i].id + '">' + image + bt_delete + '</div>'

					$(".uploader .images-list").append(container);
				}

				updateImagesJSONField();

			});

			file_frame.open();
		});

		$(document).on('click', '.uploader .images-list div.image a.button.delete', function(){
			$(this).closest('div.image').hide(500, function(){
				$(this).remove();
				updateImagesJSONField();
			});
			return false;
		});

		$( ".images-list" ).sortable({
			update: function( event, ui ) {
				updateImagesJSONField();
			}
		});

		function getImagesJSON(){

			var image_ids = [];

			$('.images-list div.image').each(function(index, value){
				var id = $(this).attr('data-id');
				image_ids.push(id);
			});

			return JSON.stringify(image_ids);
		}

		function updateImagesJSONField(){
			$('#emm_portfolio_images_order').val(getImagesJSON());
		}

	});
	</script>
	<?php
}

function render_emm_portfolio_links_metabox($post){
	$link_website = get_post_meta( $post->ID, 'emm_portfolio_link_website', true );
	$link_download = get_post_meta( $post->ID, 'emm_portfolio_link_download', true );
	wp_nonce_field( 'emm_portfolio_links_metabox_data', 'emm_portfolio_links_metabox_nonce' );
	?>
	<label>
		<p>Website:</p>
		<input id="emm_portfolio_link_website" name="emm_portfolio_link_website" value="<?php echo esc_attr( $link_website ); ?>" placeholder="http://..." />
	</label>
	<label>
		<p>Download:</p>
		<input id="emm_portfolio_link_download" name="emm_portfolio_link_download" value="<?php echo esc_attr( $link_download ); ?>" placeholder="http://..." />
	</label>
	<?php
}

function emm_portfolio_save_metadata( $post_id ) {

	if(!isset( $_POST['emm_portfolio_images_metabox_nonce']))
		return;

	if(!isset( $_POST['emm_portfolio_links_metabox_nonce']))
		return;

	if(!wp_verify_nonce( $_POST['emm_portfolio_images_metabox_nonce'], 'emm_portfolio_images_metabox_data'))
		return;

	if(!wp_verify_nonce( $_POST['emm_portfolio_links_metabox_nonce'], 'emm_portfolio_links_metabox_data'))
		return;

	if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		return;

	if(isset( $_POST['post_type'] ) && 'page' == $_POST['post_type']){
		if(!current_user_can( 'edit_page', $post_id))
			return;
	}else{
		if(!current_user_can( 'edit_post', $post_id))
			return;
	}

	if (!isset($_POST['emm_portfolio_images_order']))
		return;

	$images_order_json = sanitize_text_field($_POST['emm_portfolio_images_order']);
	$link_website = sanitize_text_field($_POST['emm_portfolio_link_website']);
	$link_download = sanitize_text_field($_POST['emm_portfolio_link_download']);

	update_post_meta( $post_id, 'emm_portfolio_images_order', $images_order_json );
	update_post_meta( $post_id, 'emm_portfolio_link_website', $link_website );
	update_post_meta( $post_id, 'emm_portfolio_link_download', $link_download );
}

function emm_portfolio_settings_menu() {
    add_submenu_page('edit.php?post_type=emm_portfolio', 'EMM Portfolio Settings', 'Portfolio settings', 'edit_posts', basename(__FILE__), 'emm_portfolio_settings');
}

function emm_portfolio_settings() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}
	?>
	<div class="wrap">

        <h2>EMM Portfolio Settings</h2>

		<div class="card">
			<h3><span class="dashicons dashicons-admin-generic"></span> Import projects:</h3>
			<p>Import old system projects.</p>
			<p>This tool only moves your projects from old m4c_portfolio custom post type (from Material for Coders template) to the new standalone plugin emm_portfolio.<p>
			<hr />
			<div style="padding: 10px;" align="center">
				<?php global $wpdb; ?>

				<?php if(isset($_GET['import'])): ?>
					<?php
					$wpdb->update( $wpdb->posts, array( "post_type" => "emm_portfolio" ), array( "post_type" => "m4c_portfolio" ) );
					$wpdb->update( $wpdb->postmeta, array( "meta_key" => "emm_portfolio_images_order" ), array( "meta_key" => "_m4c_portfolio_images_order" ) );
					?>
					<p><span class="dashicons dashicons-yes"></span> All pending data has been imported.</p>
					<p><a href="edit.php?post_type=emm_portfolio&page=emm-portfolio.php" class="button">&larr; Return</a></p>
				<?php else: ?>
					<?php
					$projects_count = $wpdb->get_var("SELECT count(DISTINCT ID) FROM $wpdb->posts WHERE post_type = 'm4c_portfolio'");
					$metadata_count = $wpdb->get_var("SELECT count(DISTINCT post_id) FROM $wpdb->postmeta WHERE meta_key = '_m4c_portfolio_images_order'");
					?>
					<?php if($projects_count == 0 && $metadata_count == 0): ?>
						<p>No data pending to import. Great.</p>
					<?php else: ?>
						<p><?php echo intval($projects_count); ?> projects and <?php echo intval($metadata_count); ?> metadata found.</p>
						<p><a href="edit.php?post_type=emm_portfolio&page=emm-portfolio.php&import" class="button button-primary">Continue &rarr;</a></p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

?>

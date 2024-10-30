<?php
 /**
 * Plugin Name:         Jigoshop QR Code
 * Plugin URI:          http://www.chriscct7.com
 * Description:         Generate QR codes for your products!
 * Author:              Chris Christoff
 * Author URI:          http://www.chriscct7.com
 *
 * Contributors:        chriscct7
 *
 * Version:             4.0
 * Requires at least:   3.5.0
 * Tested up to:        3.6 Beta 3
 *
 * Text Domain:         jqrc
 * Domain Path:         /languages/
 *
 * @category            Plugin
 * @copyright           Copyright © 2013 Chris Christoff
 * @author              Chris Christoff
 * @package             JQRC
 */
function jqrc_require_jigoshop() {
	require_once 'jigoshop-functions.php';
	is_jigoshop_active('1.5');
	if ( !defined( 'jqrc_plugin_dir' ) ) define( 'jqrc_plugin_dir', trailingslashit( dirname( __FILE__ ) ) );
	load_plugin_textdomain( 'jigoshop', false, jqrc_plugin_dir . 'languages/' );
}
add_action('init', 'jqrc_require_jigoshop');
add_action( 'plugins_loaded', 'jigo_qr_load' );
function jigo_qr_load() {

	/**
	 * Check if Jigoshop is active
	 */
	if ( is_jigoshop_activated() ) {
		/* Define an absolute path to our plugin directory. */
		if ( !defined( 'qr_plugin_dir' ) ) define( 'qr_plugin_dir', trailingslashit( dirname( __FILE__ ) ) . 'qrcode/' );
		}

		class QR_Code_Main {

			public function __construct() {

				$this->title = __( 'QR Code', 'jigo_qrcode' );
				$this->id = 'jigo_qrcodegen';

				/* Define the custom box */
				add_action( 'add_meta_boxes', array( &$this, 'qrcode_add_custom_box' ) );

				/* Do something with the data entered */
				add_action( 'save_post', array( &$this, 'qrcode_save_postdata' ) );

				/* jQuery AJAX download button eheh */
				add_action('wp_ajax_qrcode_download_image', array( &$this, 'download_image' ) );
			}

			/* Adds a box to the main column on the Post and Page edit screens */
			function qrcode_add_custom_box() {
				add_meta_box(
					'qrcode_sectionid',
					__( 'QR Code', 'jigo_qrcode' ),
					array( &$this, 'qrcode_inner_custom_box'),
					'product',
					'side'
				);
			}

			function download_image() {

				if ( !empty($_POST['security']) && wp_verify_nonce( $_POST['security'], 'download-qr-code' ) ) {
					$api = 'http://qrickit.com/api/qr';

					$product_id = $_POST['product_id'];

					// 2.0 compat
					if ( function_exists( 'get_product' ) )
						$product = get_product( $product_id );
					else
						$product = new jigoshop_product( $product_id );

					$args = array();
					switch ($_POST['selection']) :
						case 'product_url' :
							$args['d'] = get_permalink( $product->id );
							break;
						case 'add_to_cart_url' :
							$args['d'] =get_permalink( $product->id );
							if(substr($args['d'], -1) == '/') {
							$args['d'] = substr($args['d'], 0, -1);
							}
							$addtocart = $product->add_to_cart_url();
							$explode = explode('?',$addtocart);
							$args['d'] = $args['d'].'?'.$explode[1].'&_wp_http_referer='. get_permalink( $product->id );
							break;
					endswitch;

					$args['qrsize'] = 258;
					$img_url = add_query_arg( $args, $api );

					$args['qrsize'] = 1480;
					$large_image_url = add_query_arg( $args, $api );
				
					$short_url=$img_url;
					if ( empty($error) ) {
						$response = array(
							'url' => $short_url,
							'img' => $img_url,
							'large' => $large_image_url,
						);
					} else {
						$response = array(
							'error_message' => $error
						);
					}
					echo json_encode($response);
				}

				exit;
			}

			/* Prints the box content */
			function qrcode_inner_custom_box( $post ) {

				// 2.0 compat
				if ( function_exists( 'get_product' ) )
					$product = get_product( $post->ID );
				else
					$product = new jigoshop_product( $post->ID );

				$meta = !empty($product->product_custom_fields[$this->id . '_meta']) ? maybe_unserialize($product->product_custom_fields[$this->id . '_meta'][0]) : array('selection' => 'add_to_cart_url'); 
				// get custom text from stored DB value
				?> 

				<?php wp_nonce_field( plugin_basename( __FILE__ ), $this->id . '_wp_nonce' ); ?>

				<script>
				jQuery(function() {
					jQuery('#qr-results').hide();
					var current = jQuery('input[name*="jigo_qrcodegen"]:checked').val();

					jQuery('input[name*="jigo_qrcodegen"]').click(function() {
						var current = jQuery('input[name*="jigo_qrcodegen"]:checked').val();

					});

					jQuery('a.download-qrcode').click(function(e) {
						e.preventDefault();

						var data = {
							action     : 'qrcode_download_image',
							product_id : '<?php echo $product->id; ?>',
							selection  : jQuery('input[name*="jigo_qrcodegen"]:checked').val(),
							security   : '<?php echo wp_create_nonce("download-qr-code"); ?>'
						};

						jQuery.post( '<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {
							jQuery('p#generated-qr-code-error').fadeOut(function() {
								jQuery('div#qr-results').slideUp(function() {
									if ( response.error_message ) {
										jQuery('p#generated-qr-code-error').text(response.error_message).fadeIn();
									} else {
										jQuery('p#generated-qr-code-error').hide();
										jQuery('a#generated-qr-code-large').attr( 'href', response.large );
										jQuery('img#generated-qr-code').attr( 'src', response.img );
										jQuery('input#generated-qr-code-url').attr( 'value', response.url );
										jQuery('div#qr-results').slideDown();
									}
								});
							});
						}, "json");
					});
				});
				</script>

				<p id="generated-qr-code-error" style="color:red;"></p>

				<div id="qr-results">
					<p><img id="generated-qr-code"></p>
					<p><a id="generated-qr-code-large"><?php _e('Download large (1440x1440)', 'jigo_qrcode'); ?></a><br/>
						<?php _e('(right click, save link as)', 'jigo_qrcode'); ?>
					</p>
					<p><input type="text" style="width:100%;" readonly="readonly" id="generated-qr-code-url" /></p>
				</div>

				<p>
					<label class="radio">
						<input type="radio" name="jigo_qrcodegen[selection]" id="add_to_cart_url" value="add_to_cart_url" <?php checked($meta['selection'], 'add_to_cart_url', true); ?>>
						<?php _e('Redirect to the add to cart URL', 'jigo_qrcode'); ?>
					</label>
				</p>

				<p>
					<label class="radio">
						<input type="radio" name="jigo_qrcodegen[selection]" id="product_url" value="product_url" <?php checked($meta['selection'], 'product_url', true); ?>>
						<?php _e('Redirect to the product\'s page', 'jigo_qrcode'); ?>
					</label>
				</p>
				<?php

				echo '<p><a class="button download-qrcode" href="' . add_query_arg( 'get_qrcode', $product->id ) . '">' . __('Generate', 'jigo_qrcode') .'</a></p>';

			}

			/* When the post is saved, saves our custom data */
			function qrcode_save_postdata( $post_id ) {
				// verify if this is an auto save routine.
				// If it is our form has not been submitted, so we dont want to do anything
				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

				// verify this came from the our screen and with proper authorization,
				// because save_post can be triggered at other times

				if ( empty($_POST[$this->id]) || !wp_verify_nonce( $_POST[$this->id . '_wp_nonce'], plugin_basename( __FILE__ ) ) ) return;
				if ( !current_user_can( 'edit_post', $post_id ) ) return;

				// OK, we're authenticated: we need to find and save the data
				$selection = $_POST[$this->id];

				update_post_meta($post_id, $this->id . '_meta', $selection);
			}

		}

	new QR_Code_Main();
}
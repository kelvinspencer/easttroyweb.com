<?php
/**
 * Theme updater admin page and functions.
 *
 * @package Checkout
 */

/**
 * Load Getting Started styles in the admin
 *
 * since 1.0.0
 */
function checkout_start_load_admin_scripts() {

	// Load styles only on our page
	global $pagenow;
	if( 'themes.php' != $pagenow )
		return;

	/**
	 * Getting Started scripts and styles
	 *
	 * @since 1.0
	 */

	// Getting Started javascript
	wp_enqueue_script( 'getting-started', get_template_directory_uri() . '/inc/admin/getting-started/getting-started.js', array( 'jquery' ), '1.0.0', true );

	// Fitvids responsive videos
	wp_enqueue_script( 'getting-started-fitvid', get_template_directory_uri() . '/js/jquery.fitvids.js', array( 'jquery' ), '1.1', true );

	// Getting Started styles
	wp_register_style( 'getting-started', get_template_directory_uri() . '/inc/admin/getting-started/getting-started.css', false, '1.0.0' );
	wp_enqueue_style( 'getting-started' );

	// Thickbox
	add_thickbox();
}
add_action( 'admin_enqueue_scripts', 'checkout_start_load_admin_scripts' );

class Array_Theme_Updater_Admin {

	/**
	 * Variables required for the theme updater
	 *
	 * @since 1.0.0
	 * @type string
	 */
	 protected $remote_api_url = null;
	 protected $theme_slug = null;
	 protected $api_slug = null;
	 protected $version = null;
	 protected $author = null;
	 protected $download_id = null;
	 protected $renew_url = null;
	 protected $strings = null;

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	function __construct( $config = array(), $strings = array() ) {

		$config = wp_parse_args( $config, array(
			'remote_api_url' => 'https://arraythemes.com',
			'theme_slug' => get_template(),
			'api_slug' => get_template() . '-wordpress-theme',
			'item_name' => '',
			'license' => '',
			'version' => '',
			'author' => '',
			'download_id' => '',
			'renew_url' => ''
		) );

		// Set config arguments
		$this->remote_api_url = $config['remote_api_url'];
		$this->item_name = $config['item_name'];
		$this->theme_slug = sanitize_key( $config['theme_slug'] );
		$this->api_slug = sanitize_key( $config['api_slug'] );
		$this->version = $config['version'];
		$this->author = $config['author'];
		$this->download_id = $config['download_id'];
		$this->renew_url = $config['renew_url'];

		// Populate version fallback
		if ( '' == $config['version'] ) {
			$theme = wp_get_theme( $this->theme_slug );
			$this->version = $theme->get( 'Version' );
		}

		// Strings passed in from the updater config
		$this->strings = $strings;

		add_action( 'admin_init', array( $this, 'updater' ) );
		add_action( 'admin_init', array( $this, 'register_option' ) );
		add_action( 'admin_init', array( $this, 'license_action' ) );
		add_action( 'admin_menu', array( $this, 'license_menu' ) );
		add_action( 'update_option_' . $this->theme_slug . '_license_key', array( $this, 'activate_license' ), 10, 2 );
		add_filter( 'http_request_args', array( $this, 'disable_wporg_request' ), 5, 2 );

	}

	/**
	 * Creates the updater class.
	 *
	 * since 1.0.0
	 */
	function updater() {

		/* If there is no valid license key status, don't allow updates. */
		if ( get_option( $this->theme_slug . '_license_key_status', false) != 'valid' ) {
			return;
		}

		if ( !class_exists( 'Array_Theme_Updater' ) ) {
			// Load our custom theme updater
			include( dirname( __FILE__ ) . '/theme-updater-class.php' );
		}

		new Array_Theme_Updater(
			array(
				'remote_api_url' 	=> $this->remote_api_url,
				'version' 			=> $this->version,
				'license' 			=> trim( get_option( $this->theme_slug . '_license_key' ) ),
				'item_name' 		=> $this->item_name,
				'author'			=> $this->author
			),
			$this->strings
		);
	}

	/**
	 * Adds a menu item for the theme license under the appearance menu.
	 *
	 * since 1.0.0
	 */
	function license_menu() {

		$strings = $this->strings;

		add_theme_page(
			$strings['theme-license'],
			$strings['theme-license'],
			'manage_options',
			$this->theme_slug . '-license',
			array( $this, 'license_page' )
		);
	}

	/**
	 * Outputs the markup used on the theme license page.
	 *
	 * since 1.0.0
	 */
	function license_page() {

		$strings = $this->strings;

		$license = trim( get_option( $this->theme_slug . '_license_key' ) );
		$status = get_option( $this->theme_slug . '_license_key_status', false );

		// Checks license status to display under license key
		if ( ! $license ) {
			$message    = $strings['enter-key'];
		} else {
			// For testing messages
			// delete_transient( $this->theme_slug . '_license_message' );

			if ( ! get_transient( $this->theme_slug . '_license_message', false ) ) {
				set_transient( $this->theme_slug . '_license_message', $this->check_license(), ( 60 * 60 * 24 ) );
			}
			$message = get_transient( $this->theme_slug . '_license_message' );
		}

		/**
		 * Retrieve help file and theme update changelog
		 *
		 * since 1.0.0
		 */

		// Theme info
		$theme = wp_get_theme( 'checkout' );

		// Lowercase theme name for resources links
		$theme_name_lower = get_template();

		// Grab the change log from arraythemes.com for display in the Latest Updates tab
		$changelog = wp_remote_get( 'https://arraythemes.com/themes/' . $this->api_slug . '/changelog/' );
		if( $changelog && !is_wp_error( $changelog ) && 200 === wp_remote_retrieve_response_code( $changelog ) ) {
			$changelog = $changelog['body'];
		} else {
			$changelog = __( 'There seems to be a temporary problem retrieving the latest updates for this theme. You can always view your theme&apos;s latest updates in your Array account dashboard.', 'checkout' );
		}


		/**
		 * Create recommended plugin install URLs
		 *
		 * since 1.0.0
		 */

		$toolkitUrl   = esc_url( 'https://wordpress.org/plugins/array-toolkit/' );
 		$eddUrl       = esc_url( 'https://wordpress.org/plugins/easy-digital-downloads/' );
 		$subtitlesUrl = esc_url( 'https://wordpress.org/plugins/subtitles/' );
 		$wpformsUrl   = esc_url( 'https://wordpress.org/plugins/wpforms-lite/' );
 		$mailchimpUrl = esc_url( 'https://wordpress.org/plugins/mailchimp-for-wp/' );
	?>


			<div class="wrap getting-started">
				<h2 class="notices"></h2>
				<div class="intro-wrap">
					<div class="intro">
						<h3><?php printf( __( 'Getting started with %1$s v%2$s', 'checkout' ), $theme['Name'], $theme['Version'] ); ?></h3>

						<h4><?php printf( __( 'You will find everything you need to get started with Checkout below.', 'checkout' ), $theme['Name'] ); ?></h4>
					</div>
				</div>

				<div class="panels">
					<ul class="inline-list">
						<li class="current"><a id="help" href="#"><i class="fa fa-check"></i> <?php _e( 'Help File', 'checkout' ); ?></a></li>
						<li><a id="plugins" href="#"><i class="fa fa-plug"></i> <?php _e( 'Plugins', 'checkout' ); ?></a></li>
						<li><a id="support" href="#"><i class="fa fa-question-circle"></i> <?php _e( 'FAQ &amp; Support', 'checkout' ); ?></a></li>
						<li><a id="updates" href="#"><i class="fa fa-refresh"></i> <?php _e( 'Latest Updates', 'checkout' ); ?></a></li>
						<li><a id="themeclub" href="#"><i class="fa fa-star"></i> <?php _e( 'Theme Club', 'checkout' ); ?></a></li>
					</ul>

					<div id="panel" class="panel">

						<!-- Help file panel -->
						<div id="help-panel" class="panel-left visible">

							<!-- Grab feed of help file -->
							<?php
								include_once( ABSPATH . WPINC . '/feed.php' );

								$rss = fetch_feed( 'https://arraythemes.com/articles/checkout/feed/?withoutcomments=1' );

								if ( ! is_wp_error( $rss ) ) :
								    $maxitems = $rss->get_item_quantity( 1 );
								    $rss_items = $rss->get_items( 0, $maxitems );
								endif;

								$rss_items_check = array_filter( $rss_items );
							?>

							<!-- Output the feed -->
							<?php if ( is_wp_error( $rss ) || empty( $rss_items_check ) ) : ?>
								<p><?php _e( 'This help file feed seems to be temporarily down. You can always view the help file on Array in the meantime.', 'checkout' ); ?> <a href="https://arraythemes.com/articles/<?php echo $theme_name_lower; ?>" title="View help file"><?php echo $theme['Name']; ?> <?php _e( 'Help File &rarr;', 'checkout' ); ?></a></p>
							<?php else : ?>
							    <?php foreach ( $rss_items as $item ) : ?>
									<?php echo $item->get_content(); ?>
							    <?php endforeach; ?>
							<?php endif; ?>
						</div>

						<!-- Updates panel -->
						<div id="plugins-panel" class="panel-left">
							<h4><?php _e( 'Recommended Plugins', 'checkout' ); ?></h4>

							<p><?php _e( 'Below is a list of recommended plugins to install that will help you get the most out of Checkout. Although each plugin is optional, it is recommended that you at least install the Array Toolkit and Easy Digital Downloads to create a website similar to the Checkout demo.', 'checkout' ); ?></p>

							<hr/>

							<h4><?php _e( 'Array Toolkit', 'checkout' ); ?>
								<?php if ( ! class_exists( 'Array_Toolkit' ) ) { ?>
									<a class="button button-secondary thickbox onclick" href="<?php echo esc_url( $toolkitUrl ); ?>" title="<?php esc_attr_e( 'Install Array toolkit', 'checkout' ); ?>"><i class="fa fa-download"></i> <?php _e( 'View on WP.org', 'checkout' ); ?></a>
								<?php } else { ?>
									<span class="button button-secondary disabled"><i class="fa fa-check"></i> <?php _e( 'Activated', 'checkout' ); ?></span>
								<?php } ?>
							</h4>

							<p><?php _e( 'The Array Toolkit is a free plugin that we&apos;ve developed to add Portfolio Items and Testimonials to your site. We recommend this plugin if you&apos;d like to showcase your work or display testimonials on your homepage.', 'checkout' ); ?></p>

							<hr/>

							<h4><?php _e( 'Easy Digital Downloads', 'checkout' ); ?>
								<?php if ( ! class_exists( 'Easy_Digital_Downloads' ) ) { ?>
									<a class="button button-secondary thickbox onclick" href="<?php echo esc_url( $eddUrl ); ?>" title="<?php esc_attr_e( 'Install Easy Digital Downloads', 'checkout' ); ?>"><i class="fa fa-download"></i> <?php _e( 'View on WP.org', 'checkout' ); ?></a>
								<?php } else { ?>
									<span class="button button-secondary disabled"><i class="fa fa-check"></i> <?php _e( 'Activated', 'checkout' ); ?></span>
								<?php } ?>
							</h4>

							<p><?php _e( 'Easy Digital Downloads is a free plugin that enables you to sell digital goods on your website. This plugin powers the ecommerce store in Checkout. If you would like to sell goods on your website, you will need to install this plugin.', 'checkout' ); ?></p>

							<hr/>

							<h4><?php _e( 'Subtitles', 'checkout' ); ?>
								<?php if ( ! class_exists( 'Subtitles' ) ) { ?>
									<a class="button button-secondary thickbox onclick" href="<?php echo esc_url( $subtitlesUrl ); ?>" title="<?php esc_attr_e( 'Install Subtitles', 'checkout' ); ?>"><i class="fa fa-download"></i> <?php _e( 'View on WP.org', 'checkout' ); ?></a>
								<?php } else { ?>
									<span class="button button-secondary disabled"><i class="fa fa-check"></i> <?php _e( 'Activated', 'checkout' ); ?></span>
								<?php } ?>
							</h4>

							<p><?php
								$subtitle_img = 'https://s3.amazonaws.com/f.cl.ly/items/2Q1T1K3F102a3u3x3V3h/Image%202015-03-06%20at%202.01.11%20AM.png?TB_iframe=true&width=660&height=520';
								printf( __( 'Subtitles is a free plugin that lets you add optional subtitles to your post and pages. As you can see in the Checkout demo, <a class="thickbox" href="%s">these subtitles</a> add a nice accent to introduce your page. Once installed, you&apos;ll see a Subtitle field under your Title field on post edit pages. Checkout outputs this title in the header for you, no setup required.', 'checkout' ), esc_url( $subtitle_img ) );
							?></p>

							<hr/>

							<h4><?php _e( 'WPForms', 'checkout' ); ?>
								<?php if ( ! function_exists( 'WPForms' ) ) { ?>
									<a class="button button-secondary thickbox onclick" href="<?php echo esc_url( $wpformsUrl ); ?>" title="<?php esc_attr_e( 'Install CF7', 'checkout' ); ?>"><i class="fa fa-download"></i> <?php _e( 'View on WP.org', 'checkout' ); ?></a>
								<?php } else { ?>
									<span class="button button-secondary disabled"><i class="fa fa-check"></i> <?php _e( 'Activated', 'checkout' ); ?></span>
								<?php } ?>
							</h4>

							<p><?php _e( 'WPForms allows you to easily create contact forms for your site with a powerful drag and drop editor.', 'checkout' ); ?></p>

							<hr/>

							<h4><?php _e( 'MailChimp for WP', 'checkout' ); ?>
								<?php if ( ! function_exists( '__mc4wp_load_plugin' ) ) { ?>
									<a class="button button-secondary thickbox onclick" href="<?php echo esc_url( $mailchimpUrl ); ?>" title="<?php esc_attr_e( 'Install MailChimp for WP', 'checkout' ); ?>"><i class="fa fa-download"></i> <?php _e( 'View on WP.org', 'checkout' ); ?></a>
								<?php } else { ?>
									<span class="button button-secondary disabled"><i class="fa fa-check"></i> <?php _e( 'Activated', 'checkout' ); ?></span>
								<?php } ?>
							</h4>

							<p><?php _e( 'MailChimp for WP is a free plugin that lets you quickly and easily add email subscription forms to your site. You can see this in action in the footer of Checkout&apos;s live demo.', 'checkout' ); ?></p>
						</div><!-- .panel-left -->

						<!-- Support panel -->
						<div id="support-panel" class="panel-left">
							<ul id="top" class="anchor-nav">
								<li><a href="#checkout-support"><?php _e( 'Where do I get support for Checkout?', 'checkout' ); ?></a></li>
								<li><a href="#checkout-typekit"><?php _e( 'How do I activate Typekit fonts and enable theme updates?', 'checkout' ); ?></a></li>
								<li><a href="#checkout-typekit-active"><?php _e( 'How long are my Typekit fonts active?', 'checkout' ); ?></a></li>

								<li><a href="#checkout-edd"><?php _e( 'Is Easy Digital Downloads required to use Checkout?', 'checkout' ); ?></a></li>
								<li><a href="#checkout-bundle"><?php _e( 'What is the Marketplace bundle and do I need it?', 'checkout' ); ?></a></li>
							</ul>

							<h3 id="checkout-support"><?php _e( 'Where do I get support for Checkout?', 'checkout' ); ?></h3>

							<p><?php
								$signIn = 'https://arraythemes.com/login';
								printf( __( 'If you&apos;ve read through the Help File in the first tab and still have questions or are experiencing issues, we&apos;re happy to help! Simply <a href="%s" title="Sign in to your Array account">sign in</a> to your account dashboard on Array and visit the Support tab to send us a question.', 'checkout' ), esc_url( $signIn ) );
							?></p>

							<p><?php
								$themeforestRegister = 'https://arraythemes.com/themeforest';
								printf( __( 'If you&apos;ve purchased your theme via ThemeForest, you will first have to create an account at Array to get a license key and access support. Visit the <a href="%s" title="Create an account">ThemeForest</a> page to validate your purchase code and create an account.', 'checkout' ), esc_url( $themeforestRegister ) );
							?></p>

							<hr/>

							<h3 id="checkout-typekit"><?php _e( 'How do I activate Typekit fonts and enable theme updates?', 'checkout' ); ?></h3>

							<p><?php
								$dashboardLink = 'https://arraythemes.com/dashboard/';
								printf( __( 'To activate the fancy Typekit fonts and theme updates on your site, you simply need to activate your Checkout license in the sidebar. You can find your theme license by visiting your <a target="blank" href="%s" title="Visit your Array Dashboard">Dashboard</a> at Array. Click the Manage License link next to your link, copy the theme license and enter it into the sidebar License box. Once activated, Typekit will be enabled as well as seamless theme updates!', 'checkout' ), esc_url( $dashboardLink ) );
							?></p>

							<hr/>

							<h3 id="checkout-typekit-active"><?php _e( 'How long are my Typekit fonts active?', 'checkout' ); ?></h3>

							<p><?php
								printf( __( 'Once you activate your license, your Typekit fonts are active for 1 year. At the end of the year, your license will be renewed to ensure your site stays in tip top shape. You may opt out of renewing your license in your Array Dashboard, but you will no longer have access to theme support, updates or your fancy Typekit fonts.', 'checkout' ) );
							?></p>

							<hr/>

							<h3 id="checkout-edd"><?php _e( 'Is Easy Digital Downloads required to use Checkout?', 'checkout' ); ?></h3>

							<p><?php
								$eddLink = 'https:easydigitaldownloads.com/?ref=210';
								printf( __( '<a target="blank" href="%s" title="Easy Digital Downloads">Easy Digital Downloads</a> is entirely optional and is only required if you would like to sell digital products on your website. Easy Digital Downloads is 100&#37; free, but you can also purchase extensions to add more features to your site. Checkout is compatible with many of the Easy Digital Downloads extensions.', 'checkout' ), esc_url( $eddLink ) );
							?></p>

							<hr/>

							<h3 id="checkout-bundle"><?php _e( 'What is the Marketplace bundle and do I need it?', 'checkout' ); ?></h3>

							<p><?php
								$bundleLink = 'https://easydigitaldownloads.com/extensions/marketplace-bundle/?ref=210';
								printf( __( 'The <a target="blank" href="%s" title="EDD Marketplace Bundle">Marketplace bundle</a> is a collection of extensions that have been specifically chosen for users that wish to setup a marketplace with Easy Digital Downloads. These extensions add features like Frontend User Submissions, Reviews, Recommended Products and Wishlists, allowing you to create a single or multi-user marketplace. Checkout has been designed to seamlessly inteegrate with all of the extensions in the Marketplace bundle.', 'checkout' ), esc_url( $bundleLink ) );
							?></p>

							<p><?php _e( 'The Marketplace bundle is not required to sell your products with Easy Digital Downloads. The bundle is meant for users who would like to quickly and easily start a marketplace of their own.', 'checkout' ); ?></p>

							<hr/>
						</div><!-- .panel-left support -->

						<!-- Updates panel -->
						<div id="updates-panel" class="panel-left">
							<p><?php echo $changelog; ?></p>
						</div><!-- .panel-left updates -->

						<!-- More themes -->
						<div id="themes-panel" class="panel-left">
							<div class="theme-intro">
								<div class="theme-intro-left">
									<p><?php _e( 'Join the Theme Club to download all the themes you see below and new releases for one year for <strong>only $89</strong>, a huge value!', 'checkout' ); ?></p>
								</div>
								<div class="theme-intro-right">
									<a class="button-primary club-button" href="<?php echo esc_url('https://arraythemes.com/theme-club'); ?>"><?php esc_html_e( 'Learn about the Theme Club', 'checkout' ); ?> &rarr;</a>
								</div>
							</div>

							<div class="theme-list">
							<?php
							// @todo cache this after all the dust has settled
							$themes_list = wp_remote_get( 'https://arraythemes.com/feed/themes' );

							if ( ! is_wp_error( $themes_list ) && 200 === wp_remote_retrieve_response_code( $themes_list ) ) {

								echo wp_remote_retrieve_body( $themes_list );
							} else {
								$themes_link = 'https://arraythemes.com/wordpress-themes';
								printf( __( 'This theme feed seems to be temporarily down. Please check back later, or visit our <a href="%s">Themes page on Array</a>.', 'checkout' ), esc_url( $themes_link ) );
							} ?>

							</div><!-- .theme-list -->
						</div><!-- .panel-left updates -->

						<div class="panel-right">
							<!-- Activate license -->
							<div class="panel-aside">
								<?php if ( 'valid' == $status ) { ?>

								<h4><?php _e( 'Sweet, your license is active!', 'checkout' ); ?></h4>

								<!-- Activation message -->
								<p><?php echo $message; ?></p>

								<?php } else { ?>
									<h4><?php _e( 'Activate Typekit fonts and seamless theme updates!', 'checkout' ); ?></h4>

								<p>
									<?php _e( 'To get the most out of Checkout, activate your license key! With an active license, you get beautiful Typekit fonts and one-click theme updates to keep your site healthy.', 'checkout' ); ?>
								</p>

								<p>
									<?php
										$license_screenshot = 'http://cl.ly/UKW6/license.jpg?TB_iframe=true&amp;width=1000&amp;height=485';
										printf( __( 'You can find your license key in your Array Dashboard in the <a class="thickbox" href="%s">Downloads</a> section.', 'checkout' ), esc_url( $license_screenshot ) );
									?>
								</p>
								<?php } ?>

								<!-- License setting -->
								<form class="enter-license" method="post" action="options.php">
									<?php settings_fields( $this->theme_slug . '-license' ); ?>

									<input id="<?php echo $this->theme_slug; ?>_license_key" name="<?php echo $this->theme_slug; ?>_license_key" type="text" class="regular-text license-key-input" value="<?php echo esc_attr( $license ); ?>" placeholder="<?php echo $strings['license-key']; ?>"/>

									<!-- If we have a license -->
									<?php
										wp_nonce_field( $this->theme_slug . '_nonce', $this->theme_slug . '_nonce' );
										if ( 'valid' == $status ) { ?>
											<input type="submit" class="button-primary" name="<?php echo $this->theme_slug; ?>_license_deactivate" value="<?php echo $strings['deactivate-license']; ?>"/>
										<?php } else if ( $license ) { ?>
											<input type="submit" class="button-primary" name="<?php echo $this->theme_slug; ?>_license_activate" value="<?php echo $strings['activate-license']; ?>"/>
										<?php } else { ?>
											<input type="submit" class="button-primary" name="<?php echo $this->theme_slug; ?>_license_activate" value="<?php echo $strings['save-license']; ?>"/>
										<?php } ?>

								</form><!-- .enter-license -->

							</div><!-- .panel-aside license -->

							<!-- Knowledge base -->
							<div class="panel-aside">
								<h4><?php _e( 'Visit the Knowledge Base', 'checkout' ); ?></h4>
								<p><?php _e( 'New to the WordPress world? Our Knowledge Base has over 20 video tutorials, from installing WordPress to working with themes and more.', 'checkout' ); ?></p>

								<a class="button button-primary" href="https://arraythemes.com/articles/" title="<?php esc_attr_e( 'Visit the knowledge base', 'checkout' ); ?>"><?php _e( 'Visit the Knowledge Base', 'checkout' ); ?></a>
							</div><!-- .panel-aside knowledge base -->
						</div><!-- .panel-right -->
					</div><!-- .panel -->
				</div><!-- .panels -->
			</div><!-- .getting-started -->

		<?php
	}

	/**
	 * Registers the option used to store the license key in the options table.
	 *
	 * since 1.0.0
	 */
	function register_option() {
		register_setting(
			$this->theme_slug . '-license',
			$this->theme_slug . '_license_key',
			array( $this, 'sanitize_license' )
		);
	}

	/**
	 * Sanitizes the license key.
	 *
	 * since 1.0.0
	 *
	 * @param string $new License key that was submitted.
	 * @return string $new Sanitized license key.
	 */
	function sanitize_license( $new ) {

		$old = get_option( $this->theme_slug . '_license_key' );

		if ( $old && $old != $new ) {
			// New license has been entered, so must reactivate
			delete_option( $this->theme_slug . '_license_key_status' );
			delete_transient( $this->theme_slug . '_license_message' );
		}

		return $new;
	}

	/**
	 * Makes a call to the API.
	 *
	 * @since 1.0.0
	 *
	 * @param array $api_params to be used for wp_remote_get.
	 * @return array $response decoded JSON response.
	 */
	 function get_api_response( $api_params ) {

		 // Call the custom API.
		$response = wp_remote_get(
			esc_url_raw( add_query_arg( $api_params, $this->remote_api_url ) ),
			array( 'timeout' => 15, 'sslverify' => false )
		);

		// Make sure the response came back okay.
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		return $response;
	 }

	/**
	 * Activates the license key.
	 *
	 * @since 1.0.0
	 */
	function activate_license() {

		$license = trim( get_option( $this->theme_slug . '_license_key' ) );

		// Data to send in our API request.
		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $license,
			'item_name'  => urlencode( $this->item_name )
		);

		$license_data = $this->get_api_response( $api_params );

		// $response->license will be either "active" or "inactive"
		if ( $license_data && isset( $license_data->license ) ) {
			update_option( $this->theme_slug . '_license_key_status', $license_data->license );
			delete_transient( $this->theme_slug . '_license_message' );

			// Set the Typekit kit ID
			if( 'invalid' != $license_data->license ) {

				// If the Typekit kit ID is missing from the license response, fetch it by other means.
				if( isset( $license_data->typekit_id ) && empty( $license_data->typekit_id ) || ! isset( $license_data->typekit_id ) ) {

					$response = wp_remote_get( 'https://arraythemes.com/themes/'. $this->api_slug .'/array_json_api/typekit_api/?get-typekit-id='. $license );

					$typekit_id = json_decode( wp_remote_retrieve_body( $response ) );

					if( $typekit_id && ! empty( $typekit_id ) ) {
						update_option( 'array_typekit_id', $typekit_id );
					}

				} else {
					update_option( 'array_typekit_id', $license_data->typekit_id );
				}
			}
		}
	}

	/**
	 * Deactivates the license key.
	 *
	 * @since 1.0.0
	 */
	function deactivate_license() {

		// Retrieve the license from the database.
		$license = trim( get_option( $this->theme_slug . '_license_key' ) );

		// Data to send in our API request.
		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_name'  => urlencode( $this->item_name )
		);

		$license_data = $this->get_api_response( $api_params );

		// $license_data->license will be either "deactivated" or "failed"
		if ( $license_data && ( $license_data->license == 'deactivated' ) ) {
			// Delete license key status
			delete_option( $this->theme_slug . '_license_key_status' );
			// Delete the Typekit ID
			delete_option( 'array_typekit_id' );
			delete_transient( $this->theme_slug . '_license_message' );
		}
	}

	/**
	 * Constructs a renewal link
	 *
	 * @since 1.0.0
	 */
	function get_renewal_link() {

		// If a renewal link was passed in the config, use that
		if ( '' != $this->renew_url ) {
			return $this->renew_url;
		}

		// If download_id was passed in the config, a renewal link can be constructed
		$license_key = trim( get_option( $this->theme_slug . '_license_key', false ) );
		if ( '' != $this->download_id && $license_key ) {
			$url = esc_url( $this->remote_api_url );
			$url .= '/checkout/?edd_license_key=' . $license_key . '&download_id=' . $this->download_id;
			return $url;
		}

		// Otherwise return the remote_api_url
		return $this->remote_api_url;

	}



	/**
	 * Checks if a license action was submitted.
	 *
	 * @since 1.0.0
	 */
	function license_action() {

		if ( isset( $_POST[ $this->theme_slug . '_license_activate' ] ) ) {
			if ( check_admin_referer( $this->theme_slug . '_nonce', $this->theme_slug . '_nonce' ) ) {
				$this->activate_license();
			}
		}

		if ( isset( $_POST[$this->theme_slug . '_license_deactivate'] ) ) {
			if ( check_admin_referer( $this->theme_slug . '_nonce', $this->theme_slug . '_nonce' ) ) {
				$this->deactivate_license();
			}
		}

	}

	/**
	 * Checks if license is valid and gets expire date.
	 *
	 * @since 1.0.0
	 *
	 * @return string $message License status message.
	 */
	function check_license() {

		$license = trim( get_option( $this->theme_slug . '_license_key' ) );
		$strings = $this->strings;

		$api_params = array(
			'edd_action' => 'check_license',
			'license'    => $license,
			'item_name'  => urlencode( $this->item_name )
		);

		$license_data = $this->get_api_response( $api_params );

		// If response doesn't include license data, return
		if ( !isset( $license_data->license ) ) {
			$message = $strings['license-unknown'];
			return $message;
		}

		// Get expire date
		$expires = false;
		if ( isset( $license_data->expires ) ) {
			$expires = date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires ) );
			$renew_link = '<a href="' . esc_url( $this->get_renewal_link() ) . '" target="_blank">' . $strings['renew'] . '</a>';
		}

		// Get site counts
		$site_count = $license_data->site_count;
		$license_limit = $license_data->license_limit;

		// If unlimited
		if ( 0 == $license_limit ) {
			$license_limit = $strings['unlimited'];
		}

		if ( $license_data->license == 'valid' ) {
			$message = $strings['license-key-is-active'] . ' ';
			if ( $expires ) {
				$message .= sprintf( $strings['expires%s'], $expires ) . ' ';
			}
			if ( $site_count && $license_limit ) {
				//$message .= sprintf( $strings['%1$s/%2$-sites'], $site_count, $license_limit );
			}
		} else if ( $license_data->license == 'expired' ) {
			if ( $expires ) {
				$message = sprintf( $strings['license-key-expired-%s'], $expires );
			} else {
				$message = $strings['license-key-expired'];
			}
			if ( $renew_link ) {
				$message .= ' ' . $renew_link;
			}
		} else if ( $license_data->license == 'invalid' ) {
			$message = $strings['license-keys-do-not-match'];
		} else if ( $license_data->license == 'inactive' ) {
			$message = $strings['license-is-inactive'];
		} else if ( $license_data->license == 'disabled' ) {
			$message = $strings['license-key-is-disabled'];
		} else if ( $license_data->license == 'site_inactive' ) {
			// Site is inactive
			$message = $strings['site-is-inactive'];
		} else {
			$message = $strings['license-status-unknown'];
		}

		return $message;
	}

	/**
	 * Disable requests to wp.org repository for this theme.
	 *
	 * @since 1.0.0
	 */
	function disable_wporg_request( $r, $url ) {

		// If it's not a theme update request, bail.
		if ( 0 !== strpos( $url, 'https://api.wordpress.org/themes/update-check/1.1/' ) ) {
 			return $r;
 		}

 		// Decode the JSON response
 		$themes = json_decode( $r['body']['themes'] );

 		// Remove the active parent and child themes from the check
 		$parent = get_option( 'template' );
 		$child = get_option( 'stylesheet' );
 		unset( $themes->themes->$parent );
 		unset( $themes->themes->$child );

 		// Encode the updated JSON response
 		$r['body']['themes'] = json_encode( $themes );

 		return $r;
	}

}

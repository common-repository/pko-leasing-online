<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.pkoleasing.pl
 * @since      1.0.6
 *
 * @package    Pkol
 * @subpackage Pkol/admin
 */
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Pkol
 * @subpackage Pkol/admin
 * @author     PKO Leasing
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

class Pkol_Admin {
	private $dir;
	private $file;
	private $assets_dir;
	private $assets_url;
	private $settings_base;
	private $settings;
	private $shopID;
	private $secret;
	private $message;
	private $plugin_name;
	private $client;
	public $test_data;
	public $site_url;
	public $auth;
	public $prod_url;
	public $env;
	public $test_url;
	public $lease_url;
	public $public_url;
	public $endpoint;
	public $allowed_tags;
	public function __construct( $file ) {		
		$message = (isset($_GET['message']) ? sanitize_text_field($_GET['message']) : null);

        if (isset($message) && $message  == '1') {
				$this->message['text'] = __('Nie udało się połączyć  z serwerem PKO leasing. Sprawdź poprawność ustawień');					
				$this->message['status'] = 'error';
		}

		if (isset($message) && $message  == '2') {
			$this->message['text'] = __('Ustawienia są prawidłowe');					
			$this->message['status'] = 'success';
		}

        if (isset($message) && $message == 'no_logs') {
            $this->message['text'] = __('Brak dostępnych logów do pobrania.', 'plugin_textdomain');
            $this->message['status'] = 'error';
        }

        $this->plugin_name = 'PKO Leasing';
		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );
		$this->settings_base = 'wpt_';
		$this->secret = sanitize_text_field(get_option($this->settings_base.'text_secret'));
		$this->shopID = sanitize_text_field(get_option($this->settings_base.'text_id'));
		$this->env = sanitize_text_field(get_option($this->settings_base.'radio_env'));
		$this->site_url = esc_url(get_site_url());
		
		$this->test_data = [];
		$this->prod_url = 'https://pc.pkoleasing.pl';
        $this->test_url = 'https://stpc.pkoleasing.pl';

        $this->lease_url = '/leasing/init';
		$this->endpoint = '/leasing/public/plugin/simulation';
		if ($this->env == 'prod') {
			$this->test_url = $this->prod_url;
			$this->lease_url = esc_url($this->prod_url. $this->lease_url);
		} else {
			$this->lease_url = esc_url($this->test_url. $this->lease_url);
		}
        // pc.pkoleasing.pl
		$test_par = (isset($_GET['test']) ? sanitize_text_field($_GET['test']) : null);
		if ($test_par && $this->shopID && $this->secret) {
			$test = [
				'shopId' => $this->shopID,
				'widgetOption' => 2,
				'totalNetValue' => 1000,
				'uniqueItemQuantity' => 1,
				'source' => "BASKET",
				'products' => [
					[
					'categoryId' => '475',
					'quantity' => 1,
					'netValue' => 1000,
					'vatRate' => $this->getRate('23%')
				]
				]
			];
		

			$data = json_encode($test);	
			//authorization
			$settings_token = $this->shopID.':'.$this->secret;				
			$token = base64_encode($settings_token);
			$env_setting = ($this->env == 'dev' ? false : true);
			$this->client = new \GuzzleHttp\Client([
				'verify'=>$env_setting,
				'http_errors' => false,
				'headers' => [ 'Content-Type' => 'application/json','Authorization' => 'Bearer ' . $token]
			]);				
			
		try {
			$response = $this->client->request('POST', $this->test_url.$this->endpoint,
			[								
				'http_errors' => false,
				'body' => $data,
				'timeout' => 5, // Response timeout
 				'connect_timeout' => 5, // Connection timeout
			],			
		);
		
			if ($response->getStatusCode() == 200) {
				header('Location: '.$this->site_url.'/wp-admin/options-general.php?page=pkol_settings&message=2');
				exit();
			} else {
				header('Location: '.$this->site_url.'/wp-admin/options-general.php?page=pkol_settings&message=1');
				exit();
			}
		
	
		} catch (\GuzzleHttp\Exception\GuzzleException $e) {           
					header('Location: '.$this->site_url.'/wp-admin/options-general.php?page=pkol_settings&message=1');
					exit();
		}
		}
	
		// Initialise settings
		add_action( 'admin_init', array( $this, 'init' ) );
		// Register plugin settings
		add_action( 'admin_init' , array( $this, 'register_settings' ) );
        // Register logs action
        add_action('admin_post_pkol_download_logs', array($this, 'download_logs'));

		// Add settings page to menu
	if ($this->file !== 'check') {
		add_action( 'admin_menu' , array( $this, 'add_menu_item' ) );
		// Add settings link to plugins page
		
		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ) , array( $this, 'add_settings_link' ) );
		}
		$this->allowed_tags = $allowed_tags = array(
			'a' => array(
				'class' => array(),
				'href'  => array(),
				'rel'   => array(),
				'title' => array(),
			),
			'abbr' => array(
				'title' => array(),
			),
			'b' => array(),
			'blockquote' => array(
				'cite'  => array(),
			),
			'cite' => array(
				'title' => array(),
			),
			'code' => array(),
			'del' => array(
				'datetime' => array(),
				'title' => array(),
			),
			'dd' => array(),
			'div' => array(
				'class' => array(),
				'title' => array(),
				'style' => array(),
				'data-baseurl' => array(),
				'data-pid' => array(),
				'id' => array()
			),
			'dl' => array(),
			'dt' => array(),
			'em' => array(),
			'h1' => array(),
			'h2' => array(),
			'h3' => array(),
			'h4' => array(),
			'h5' => array(),
			'h6' => array(),
			'i' => array(),
			'img' => array(
				'id' => array(),
				'alt'    => array(),
				'class'  => array(),
				'height' => array(),
				'src'    => array(),
				'width'  => array(),
			),
			'li' => array(
				'class' => array(),
			),
			'ol' => array(
				'class' => array(),
			),
			'p' => array(
				'class' => array(),
			),
			'q' => array(
				'cite' => array(),
				'title' => array(),
			),
			'span' => array(
				'class' => array(),
				'title' => array(),
				'style' => array(),
			),
			'strike' => array(),
			'strong' => array(),
			'ul' => array(
				'class' => array(),
			),
			'form' => array(
				'class' => array(),
				'id' => array(),
				'name' => array(),
				'action' => array(),
				'method' => array(),
			),
			'label' => array(
				'class' => array(),
				'id' => array(),
				'for' => array(),
			),
			'input' => array(
				'class' => array(),
				'id' => array(),
				'value' => array(),
				'type' => array(),
				'name' => array(),
				'checked' => array()
			),
			'table' => array(
				'class' => array(),
				'id' => array(),
			),
			'tbody' => array(
				'class' => array(),
				'id' => array(),
			),
			'th' => array(
				'class' => array(),
				'id' => array(),
				'scope' => array()
			),
			'tr' => array(
				'class' => array(),
				'id' => array(),
			),
			'td' => array(
				'class' => array(),
				'id' => array(),
			),
			'script' => array(
				'type' => array()
			),
			'style' => array()
			
		);
	}
	/**
	 * Initialise settings
	 * @return void
	 */
	public function init() {
		$this->settings = $this->settings_fields();
		
			
				if ( !class_exists( 'woocommerce' ) ) { 
					
						$this->message['text'] = __('Wtyczka PKO leasing do poprawnego działania wymaga aktywnego plugniu woocoomerce.');					
						$this->message['status'] = 'error';
				}
			
	}
	public function getRate($tax) {
		$taxes = [
			'0%' => 1,
			'7%' => 2,
			'8%' => 3,
			'22%' => 4,
			'23%' => 5,
			'23' => 5,
			'22' => 4,
			'8' => 3,
			'7' => 2,
			'0' => 1
		];
		return $taxes[$tax];
	}
	public function getEnv() {
		return $this->env;
	}
	public function getLeaseUrl() {
		return $this->lease_url;
	}
	public function getEndpoint() {
		return $this->endpoint;
	}
	public function getApiUrl() {
		
		return $this->test_url;
	}
	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_item() {
		$page = add_options_page( __( 'Ustawienia PKO Leasing Online', 'plugin_textdomain' ) , __( 'Ustawienia PKO Leasing Online', 'plugin_textdomain' ) , 'manage_options' , 'pkol_settings' ,  array( $this, 'settings_page' ) );
		add_action( 'admin_print_styles-' . $page, array( $this, 'settings_assets' ) );
	}
	/**
	 * Load settings JS & CSS
	 * @return void
	 */
	public function settings_assets() {
		// We're including the farbtastic script & styles here because they're needed for the colour picker
		// If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below
		wp_enqueue_style( 'farbtastic' );
    wp_enqueue_script( 'farbtastic' );
	//wp_enqueue_style('pkol', $this->plugin_name.'/'.$this->assets_url . 'css/pkol-admin.css', array(), '1.0', 'all' );
    // We're including the WP media scripts here because they're needed for the image upload field
    // If you're not including an image upload then you can leave this function call out
    wp_enqueue_media();
   // wp_register_script( 'wpt-admin-js',$this->plugin_name.'/'. $this->assets_url . 'js/settings.js', array( 'farbtastic', 'jquery' ), '1.0.1' );
   // wp_enqueue_script( 'wpt-admin-js' );
	}
	public function check_connection() {
		$error = false;
		$test = [
			'shopId' => $this->shopID,
			'widgetOption' => 2,
			'totalNetValue' => 1000,
			'uniqueItemQuantity' => 1,
			'source' => "BASKET",
			'products' => [
				[
				'categoryId' => '475',
				'quantity' => 1,
				'netValue' => 1000,
				'vatRate' => $this->getRate('23%')
			]
			]
		];
	
	
		$data = json_encode($test);
// 			print_r($data);

		//autoryzacja
		$settings_token = $this->shopID.':'.$this->secret;
		$token = base64_encode($settings_token);
		$env_setting = ($this->env == 'dev' ? false : true);
		$this->client = new \GuzzleHttp\Client([
			'verify'=>$env_setting,
			'http_errors' => false,
			'headers' => [ 'Content-Type' => 'application/json','Authorization' => 'Bearer ' . $token]
		]);				
		try {
			$response = $this->client->request('POST', $this->test_url.$this->endpoint,
			[								
				'http_errors' => false,
				'body' => $data,
				'timeout' => 5, // Response timeout
 				'connect_timeout' => 5, // Connection timeout
			],			
		);

			if ($response->getStatusCode() == 200) {
				return false;
			} else {
				return true;
			}


		} catch (\GuzzleHttp\Exception\GuzzleException $e) {  

			$error = true;
		}
		return $error;
	}
	/**
	 * Add settings link to plugin list table
	 * @param  array $links Existing links
	 * @return array 		Modified links
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="'. esc_url("options-general.php?page=plugin_settings").'">' . __( 'Settings', 'plugin_textdomain' ) . '</a>';
  		array_push( $links, $settings_link );
  		return $links;
	}
	/**
	 * Build settings fields
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields() {
		$settings['standard'] = array(
			'title'					=> __( 'Podstawowa konfiguracja', 'plugin_textdomain' ),
			'description'			=> __( '', 'plugin_textdomain' ),
			'fields'				=> array(
							
				array(
					'id' 			=> 'text_id',
					'label'			=> __( 'ID sklepu  <span style="color:red;">*</span>' , 'plugin_textdomain' ),
					'description'	=> __( 'Podaj ID sklepu otrzymany od PKO Leasing.', 'plugin_textdomain' ),
					'type'			=> 'text',
					'default'		=> '',
					'required'		=> true,
					'placeholder'	=> __( 'ID sklepu', 'plugin_textdomain' )
				),
				array(
					'id' 			=> 'text_secret',
					'label'			=> __( 'Sekretny klucz <span style="color:red;">*</span>' , 'plugin_textdomain' ),
					'description'	=> __( '', 'plugin_textdomain' ),
					'type'			=> 'text',
					'default'		=> '',
					'required'		=> true,
					'placeholder'	=> __( 'Sekretny klucz', 'plugin_textdomain' )
				),
				array(
					'id' 			=> 'single_checkbox',
					'label'			=> __( 'Wyświetlaj przycisk na karcie produktu?', 'plugin_textdomain' ),
					'description'	=> __( '', 'plugin_textdomain' ),
					'type'			=> 'checkbox',
					'default'		=> ''
				),
				array(
					'id' 			=> 'single_checkbox_cart',
					'label'			=> __( 'Wyświetlaj przycisk w koszyku?', 'plugin_textdomain' ),
					'description'	=> __( '', 'plugin_textdomain' ),
					'type'			=> 'checkbox',
					'default'		=> ''
				),
				array(
					'id' 			=> 'single_checkbox_order',
					'label'			=> __( 'Wyświetlaj metodę płatności w zamówieniu?', 'plugin_textdomain' ),
					'description'	=> __( '', 'plugin_textdomain' ),
					'type'			=> 'checkbox',
					'default'		=> ''
				),
				array(
					'id' 			=> 'radio_env',
					'label'			=> __( 'Wybierz środowisko', 'plugin_textdomain' ),
					'description'	=> __( '', 'plugin_textdomain' ),
					'type'			=> 'radio',
					'options'		=> array( 'dev' => 'Testowe', 'prod' => 'Produkcyjne'),
					'default'		=> 'dev'
				),
				array(
					'id' 			=> 'radio_type',
					'label'			=> __( 'Wybierz styl widgetu', 'plugin_textdomain' ),
					'description'	=> __( '' ),
					'type'			=> 'radio',
					'options'		=> array( 'blue' => 'Ciemna kolorystyka', 'white' => 'Jasna kolorystyka'),
					'default'		=> 'blue'
				),
				array(
					'id' 			=> 'radio_type_size',
					'label'			=> __( 'Wybierz rozmiar widgetu', 'plugin_textdomain' ),
					'description'	=> __( '', 'plugin_textdomain' ),
					'type'			=> 'radio',
					'options'		=> array( 'xxl' => 'Duży rozmiar','big' => 'Średni rozmiar', 'medium' => 'Mały rozmiar'),
					'default'		=> 'big'
				),
				array(
					'id' 			=> 'radio_type_pricing',
					'label'			=> __( 'Wyświetl widget: Rata od', 'plugin_textdomain' ),
					'description'	=> __( '', 'plugin_textdomain' ),
					'type'			=> 'radio',
					'options'		=> array( 'yes' => 'Tak','no' => 'Nie'),
					'default'		=> 'no'
				),
			
			)
		);
		$settings = apply_filters( 'plugin_settings_fields', $settings );
		return $settings;
	}
        
	/**
	 * Register plugin settings
	 * @return void
	 */
	public function register_settings() {
		if( is_array( $this->settings ) ) {
			foreach( $this->settings as $section => $data ) {
				// Add section to page
				add_settings_section( $section, $data['title'], array( $this, 'settings_section' ), 'pkol_settings' );
				foreach( $data['fields'] as $field ) {
					// Validation callback for field
					$validation = '';
					if( isset( $field['callback'] ) ) {
						$validation = $field['callback'];
					}
					// Register field
					$option_name = $this->settings_base . $field['id'];
					register_setting( 'pkol_settings', $option_name, $validation );
					// Add field to page
					add_settings_field( $field['id'], $field['label'], array( $this, 'display_field' ), 'pkol_settings', $section, array( 'field' => $field ) );
				}
			}
		}
	}
	public function settings_section( $section ) {
		$html = '<p> ' . $this->settings[ $section['id'] ]['description'] . '</p>' . "\n";
		echo wp_kses($html,$this->allowed_tags);
	}
	/**
	 * Generate HTML for displaying fields
	 * @param  array $args Field data
	 * @return void
	 */
	public function display_field( $args ) {
		$field = $args['field'];
        $html = '';
		
		$option_name = $this->settings_base . $field['id'];
		$option = get_option( $option_name );
		$data = '';
		if( isset( $field['default'] ) ) {
			$data = $field['default'];
			if( $option ) {
				$data = $option;
			}
		}
		switch( $field['type'] ) {
			case 'text':
			case 'password':
			case 'number':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . esc_attr($field['type']) . '" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . esc_html($data) . '" '.esc_attr(($field['required'] ? "required" : "")).'/>'.($option_name == 'wpt_text_secret' ? ' Podaj Sekretny klucz otrzymany od PKO Leasing.' : ''). "\n";
				
				if ($option_name == 'wpt_text_secret') {
					if ($this->shopID && $this->secret) {
						$html .= '<div><br/><a href="'.$this->site_url.'/wp-admin/options-general.php?page=pkol_settings&test=1">Przetestuj połączenie z PKO Leasing</a></div>' ."\n";
						}
				}
			break;
			case 'text_secret':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="'.esc_attr("text") .'" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value=""/>' . "\n";
			break;
			case 'textarea':
				$html .= '<textarea id="' . esc_attr( $field['id'] ) . '" rows="5" cols="50" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '">' . esc_html($data) . '</textarea><br/>'. "\n";
			break;
			case 'checkbox':
				$checked = '';
				if( $option && 'on' == $option ){
					$checked = 'checked="checked"';
				}
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . esc_attr($field['type']) . '" name="' . esc_attr( $option_name ) . '" ' . $checked . '/>' . "\n";
			break;
			case 'checkbox_multi':
				foreach( $field['options'] as $k => $v ) {
					$checked = false;
					if( in_array( $k, $data ) ) {
						$checked = true;
					}
					$html .= '<label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="'. esc_attr("checkbox").'" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '[]" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . $v . '</label> ';
				}
			break;
			case 'radio':
				foreach( $field['options'] as $k => $v ) {
					$checked = false;
					if( $k == $data ) {
						$checked = true;
					}
					$html .= '<label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="radio" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . $v . '</label> ';
				}
			break;
			case 'select':
				$html .= '<select name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $field['id'] ) . '">';
				foreach( $field['options'] as $k => $v ) {
					$selected = false;
					if( $k == $data ) {
						$selected = true;
					}
					$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '">' . $v . '</option>';
				}
				$html .= '</select> ';
			break;
			case 'select_multi':
				$html .= '<select name="' . esc_attr( $option_name ) . '[]" id="' . esc_attr( $field['id'] ) . '" multiple="multiple">';
				foreach( $field['options'] as $k => $v ) {
					$selected = false;
					if( in_array( $k, $data ) ) {
						$selected = true;
					}
					$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '" />' . $v . '</label> ';
				}
				$html .= '</select> ';
			break;
		}
		switch( $field['type'] ) {
			case 'checkbox_multi':
			case 'radio':
			case 'select_multi':
				$html .= '<br/><span class="description">' . esc_html($field['description']) . '</span>';
			break;
			default:
				$html .= '<label for="' . esc_attr( $field['id'] ) . '"><span class="description">' . esc_attr($field['description']) . '</span></label>' . "\n";
			break;
		}
		echo wp_kses($html,$this->allowed_tags);
	}
	/**
	 * Validate individual settings field
	 * @param  string $data Inputted value
	 * @return string       Validated value
	 */
	public function validate_field( $data ) {
		if( $data && strlen( $data ) > 0 && $data != '' ) {
			$data = urlencode( strtolower( str_replace( ' ' , '-' , $data ) ) );
		}
		return $data;
	}
	/**
	 * Load settings page content
	 * @return void
	 */
    public function settings_page() {
        // Build page HTML
        $html = '<div class="wrap" id="plugin_settings">' . "\n";
        $html .= '<h2>' . __( 'Ustawienia wtyczki', 'plugin_textdomain' ) . '</h2>' . "\n";

        if (!empty($this->message)) {
            $error = $this->message['status'];
            if ($error == 'success') {
                $html .= '<div style="color:green;" class="alert alert-' . $error . '">';
            } else {
                $html .= '<div style="color:red;" class="alert alert-' . $error . '">';
            }
            $html .= $this->message['text'];
            $html .= '</div>';
        }

        // Settings form
        $html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";
        $html .= '<div class="clear"></div>' . "\n";

        // Get settings fields
        ob_start();
        settings_fields('pkol_settings');
        do_settings_sections('pkol_settings');
        $html .= ob_get_clean();

        // Submit button for saving settings
        $html .= '<p class="submit">' . "\n";
        $html .= '<input name="Submit" type="' . esc_attr("submit") . '" class="button-primary" value="' . esc_attr(__('Zapisz ustawienia', 'plugin_textdomain')) . '" />' . "\n";
        $html .= '</p>' . "\n";

        $html .= '</form>' . "\n";
        $html .= '</div>' . "\n";


        // Form for downloading logs
        $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">' . "\n";
        $html .= '<input type="hidden" name="action" value="pkol_download_logs" />' . "\n";
        $html .= '<p class="submit">' . "\n";
        $html .= '<input type="submit" name="download_logs" class="button-secondary" value="' . esc_attr(__('Pobierz logi', 'plugin_textdomain')) . '" />' . "\n";
        $html .= '</p>' . "\n";
        $html .= '</form>' . "\n";
        echo wp_kses($html, $this->allowed_tags);
    }

    public function download_logs() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nie masz uprawnień do tej akcji.', 'plugin_textdomain'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'pkol_logs';
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);

        if (!empty($results)) {
            if (ob_get_length()) {
                ob_end_clean();
            }

            $filename = 'pkol_logs_'. $this->shopID . '_' . date('Y-m-d_H-i-s') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);

            $output = fopen('php://output', 'w');

            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, array_keys($results[0]));

            foreach ($results as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
            exit();
        } else {
            $this->message['text'] = __('Brak dostępnych logów do pobrania.', 'plugin_textdomain');
            $this->message['status'] = 'error';
            header('Location: ' . admin_url('options-general.php?page=pkol_settings&message=no_logs'));
        }
    }


    public function getHtmlTags() {
		return $this->allowed_tags;
	}
	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.3
	 */
	public function enqueue_scripts() {
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Pkol_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Pkol_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/pkol-admin.js', array( 'jquery' ), $this->version, false );
	}
}

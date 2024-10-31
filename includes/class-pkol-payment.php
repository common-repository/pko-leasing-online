<?php
function wc_offline_add_to_gateways($gateways)
{
	$gateways[] = 'WC_Gateway_Offline';
	return $gateways;
}
add_filter('woocommerce_payment_gateways', 'wc_offline_add_to_gateways');
/**
 * Adds plugin page links
 *
 * @since 1.0.3
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_offline_gateway_plugin_links($links)
{
	$admin_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=offline_gateway');
	$plugin_links = array(
		'<a href="' . esc_url($admin_url) . '">' . __('Configure', 'wc-gateway-offline') . '</a>'
	);
	return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_offline_gateway_plugin_links');
add_filter('woocommerce_available_payment_gateways', function ($available_gateways) {
	global $cart;
	$settings_base = 'wpt_';
	$shopID = sanitize_text_field(get_option($settings_base . 'text_id'));
	$secret = sanitize_text_field(get_option($settings_base . 'text_secret'));
	//$service = get_option($settings_base . 'radio_env');
	$enabled = sanitize_text_field(get_option($settings_base.'single_checkbox_order'));
	$settings = new Pkol_Admin('check');
	$test_url = $settings->getApiUrl();
	$endpoint = $settings->getEndpoint();
	$leaseurl = $settings->getLeaseUrl();
	$env = $settings->getEnv();
	$products = [];
	$tax = new WC_Tax();
	if ($enabled !== 'on') {
		unset($available_gateways['offline_gateway']);
		return $available_gateways;
	}
	if (is_checkout()) {
	
		$products = [];
		$subtotal = 0;
		foreach (WC()->cart->cart_contents as $c) {
			$_product = wc_get_product($c['variation_id'] ? $c['variation_id'] : $c['product_id']);
			$product_price = explode('&nbsp;', strip_tags(wc_price(wc_get_price_excluding_tax($_product), ['decimal_separator' => '.'])))[0];
            $product_price = str_replace([' ', '&nbsp;'], '', $product_price);
            $subtotal = $subtotal + ($product_price * (int)$c['quantity']);
			//Get rates of the product
			$taxes = $tax->get_rates($_product->get_tax_class());
			$rates = array_shift($taxes);
			$item_rate = null;
			//Take only the item rate and round it.
			if (is_array($rates)) {
				$item_rate = round(array_shift($rates));
			}
			$product_tax = 1;
			if ($item_rate) {
				$admin = new Pkol_Admin('check');
				$product_tax = $admin->getRate($item_rate);
			}

            if ($_product->is_type('variation')) {
                $parent_product = wc_get_product($_product->get_parent_id());
                $term_list = wp_get_post_terms($parent_product->get_id(), 'product_cat', array('fields' => 'ids'));
            } else {
                $term_list = wp_get_post_terms($_product->get_id(), 'product_cat', array('fields' => 'ids'));
            }

			$cat_id = (int)$term_list[0];

			$p = [
				'categoryId' => sanitize_text_field($cat_id),
				'quantity' => sanitize_text_field($c['quantity']),
				'netValue' => $product_price,
				'vatRate' => $product_tax
			];
			array_push($products, $p);
		}
		$test = [
			'shopId' => $shopID,
			'widgetOption' => 1,
			'totalNetValue' =>  WC()->cart->get_subtotal(),
			'uniqueItemQuantity' => count(WC()->cart->cart_contents),
			'source' => 'BASKET',
			'products' => (array) $products
		];
		$data = json_encode($test);
		$settings_token = $shopID . ':' . $secret;
		$token = base64_encode($settings_token);
		$env_setting = ($env == 'dev' ? false : true);

		$this->client = new \GuzzleHttp\Client([
			'verify' => $env_setting,
			'http_errors' => false,
			'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token]
		]);
		$price = null;
		if ($shopID && $secret  ) {
			
			try {
			
				$response = $this->client->request(
					'POST',
					$test_url . $endpoint,
					[
						'http_errors' => false,
						'body' => $data,
						'timeout' => 5, // Response timeout
						'connect_timeout' => 5, // Connection timeout
					],
				);
				if ($response->getBody()) {
					$data = json_decode($response->getBody());
					if (!$data->errors && $data->validityResult == 'VALID') {
						//	$price = $data->firstInstallment->value;
					} else {
						unset($available_gateways['offline_gateway']);
					}
				}
			} catch (\GuzzleHttp\Exception\GuzzleException $e) {
				unset($available_gateways['offline_gateway']);
				// }
			}
		} else {
			unset($available_gateways['offline_gateway']);
		}
	}
	//  unset(  $available_gateways['offline_gateway'] );
	return $available_gateways;
}, 100);
/**
 * Offline Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class                      WC_Gateway_Offline
 * @extends                  WC_Payment_Gateway
 * @version                   1.0.1
 *
 */
add_action('plugins_loaded', 'wc_offline_gateway_init', 11);
function wc_offline_gateway_init()
{
	class WC_Gateway_Offline extends WC_Payment_Gateway
	{
		/**
		 * Constructor for the gateway.
		 */
        public string $instructions;
		public function __construct()
		{
			$this->id                 = 'offline_gateway';
			$this->icon               = apply_filters('woocommerce_offline_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __('PKO Leasing Online ', 'wc-gateway-offline');
			$this->method_description = __('PKO Leasing Online - finansowanie leasingiem.', 'wc-gateway-offline');
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
			// Define user set variables
			$this->title        = sanitize_text_field($this->get_option('title'));
			$this->description  = esc_html($this->get_option('description'));
			$this->instructions = sanitize_text_field($this->get_option('instructions', $this->description));
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
			// Customer Emails
			add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
		}
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields()
		{
	
			$this->form_fields = apply_filters('wc_offline_form_fields', array(
				'enabled' => array(
					'title'   => __(' Włącz/Wyłącz', 'wc-gateway-offline'),
					'id'	  => 'switch_button',
					'type'    => 'checkbox',
					'label'   => __('Udostępnienie leasingu jako finansowania', 'wc-gateway-offline'),
					'default' => 'yes'
				),
				'title' => array(
					'title'       => __('Nazwa płatności', 'wc-gateway-offline'),
					'type'        => 'text',
					'description' => __('Nazwa wyświetla się przy wyborze metody płatności', 'wc-gateway-offline'),
					'default'     => __('PKO Leasing Online', 'wc-gateway-offline'),
					'desc_tip'    => false,
				),
				'description' => array(
					'title'       => __('Opis', 'wc-gateway-offline'),
					'type'        => 'textarea',
					'description' => __('Opis wyświetlany jest pod metodą płatności', 'wc-gateway-offline'),
					'default'     => __('Po zakończeniu zamówienia zostaniesz przekierowany do wniosku', 'wc-gateway-offline'),
					'desc_tip'    => true,
				),
				'instructions' => array(
					'title'       => __('Informacje w potwierdzeniu zamówenia', 'wc-gateway-offline'),
					'type'        => 'textarea',
					'description' => __('Dodatkowe informacje które zostaną wyświetlone w podsumowaniu zamówienia i emailu', 'wc-gateway-offline'),
					'default'     => 'Za chwilę zostaniesz przekierowany do wniosku',
					'desc_tip'    => true,
				),
			));
		}
		/**
		 * Output for the order received page.
		 */
        public function thankyou_page($order_id)
        {
            // getting order object
            $order = wc_get_order($order_id);
            $settings_base = 'wpt_';
            $shopID = sanitize_text_field(get_option($settings_base . 'text_id'));
            $settings = new Pkol_Admin('check');
            $allowed_html = $settings->getHtmlTags();
            $leaseurl = $settings->getLeaseUrl();
            $n = 1;
            $form = '<form action="' . esc_url($leaseurl) . '" id="'.esc_attr("leasing").'" method="POST">';
            $form .= '<input type="'.esc_attr("hidden").'" name="'.esc_attr("shopId").'" value="' . esc_attr($shopID) . '" />';
            $form .= '<input type="'.esc_attr("hidden").'" name="'.esc_attr("returnLink").'" value="' . esc_url(get_site_url()) . '" />';
            $items = $order->get_items();
            $x = 0;
            $subtotal = 0;
            $cart_total = 0;

            foreach ($items as $item_id => $item_data) {
                $product = wc_get_product($item_data['product_id']);
                $variant_id = isset($item_data['variation_id']) ? $item_data['variation_id'] : 0;

                // Jeśli istnieje wariant, pobierz cenę wariantu
                if (!empty($variant_id)) {
                    $variation = wc_get_product($variant_id);
                    $product_price_net = wc_get_price_excluding_tax($variation);
                    $product_price = wc_get_price_including_tax($variation);
                } else {
                    // Jeśli nie ma wariantu, pobierz cenę produktu bazowego
                    $product_price_net = wc_get_price_excluding_tax($product);
                    $product_price = wc_get_price_including_tax($product);
                }

                // Zastosowanie walidacji i formatu liczby
                $product_price_net = explode('&nbsp;', strip_tags(wc_price($product_price_net, ['decimal_separator' => '.'])))[0];
                $product_price_net = str_replace([' ', '&nbsp;'], '', $product_price_net);
                $validate_dots = explode('.', $product_price_net);

                if (count($validate_dots) > 2) {
                    $product_price_net = number_format($validate_dots[0] . $validate_dots[1] . '.' . $validate_dots[2], 2, '.', '');
                }

                $subtotal += $product_price_net * (int)$item_data['quantity'];
                $cart_total += $product_price * (int)$item_data['quantity'];

                $tax = new WC_Tax();
                $taxes = $tax->get_rates($product->get_tax_class());
                $item_rate = null;

                if (is_array($taxes) && !empty($taxes)) {
                    $rates = array_shift($taxes);
                    $item_rate = round(array_shift($rates));
                    if ($item_rate) {
                        $admin = new Pkol_Admin('check');
                        $product_tax = $admin->getRate($item_rate);
                    }
                    if (!$product_tax) {
                        $product_tax = 1;
                    }
                }

                $image = esc_url(wp_get_attachment_url($product->get_image_id()));

                if ($product->is_type('variation')) {
                    $parent_product = wc_get_product($product->get_parent_id());
                    $term_list = wp_get_post_terms($parent_product->get_id(), 'product_cat', array('fields' => 'ids'));
                } else {
                    $term_list = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
                }
                if (!empty($term_list) && isset($term_list[0])) {
                    $cat_id = (int)$term_list[0];
                } else {
                    $cat_id = 0;
                }

                $variant_name = '';
                if (!empty($variant_id)) {
                    $attributes = [];
                    foreach ($variation->get_attributes() as $taxonomy => $term_slug) {
                        $term = get_term_by('slug', $term_slug, $taxonomy);
                        if ($term) {
                            $attributes[] = $term->name;
                        }
                    }
                    if (!empty($attributes)) {
                        $variant_name = implode(', ', $attributes);
                    }
                }
                $product_name_with_variant = $product->get_name();
                if ($variant_name) {
                    $product_name_with_variant .= ' - ' . $variant_name;
                }

                $qty = (int)$item_data['quantity'];

                $form .= '<input type="'.esc_attr("hidden").'" name="productName' . esc_attr($n) . '" value="' . esc_attr($product_name_with_variant) . '" />';
                $form .= '<input type="'.esc_attr("hidden").'" name="productPrice' . esc_attr($n) . '" value="' . esc_attr(number_format($product_price, 2, '.', '')) . '" />';
                $form .= '<input type="'.esc_attr("hidden").'" name="productNetPrice' . esc_attr($n) . '" value="' . esc_attr($product_price_net) . '" />';
                $form .= '<input type="'.esc_attr("hidden").'" name="productQuantity' . esc_attr($n) . '" value="' . esc_attr($qty) . '" />';
                $form .= '<input type="'.esc_attr("hidden").'" name="productCategory' . esc_attr($n) . '" value="' . esc_attr($cat_id) . '" />';
                $form .= '<input type="'.esc_attr("hidden").'" name="productVatRate' . esc_attr($n) . '" value="' . esc_attr($product_tax) . '" />';
                $form .= '<input type="'.esc_attr("hidden").'" name="productAvatarUrl' . esc_attr($n) . '" value="' . esc_url($image) . '" />';
                $n++;
                $x++;
            }

            $form .= '<input type="'.esc_attr("hidden").'" name="uniqueItemQuantity" value="' . esc_attr($x) . '" />';
            $form .= '<input type="'.esc_attr("hidden").'" name="totalValue" value="' . esc_attr(number_format($cart_total, 2, '.', '')) . '" />';
            $form .= '<input type="'.esc_attr("hidden").'" name="totalNetValue" value="' . esc_attr(number_format($subtotal, 2, '.', '')) . '" />';
            $form .= '<input type="'.esc_attr("hidden").'" name="source" value="'. esc_html("ORDER") .'" />';
            $form .= '<input type="'.esc_attr("hidden").'" name="orderId" value="' . esc_attr($order->get_id()) . '" />';
            $form .= '</form>';
            $form .= '<script>document.getElementById("leasing").submit();</script>';

            echo wp_kses($form, $allowed_html);
            if ($this->instructions) {
                $instructions = wpautop(wptexturize($this->instructions));
                echo wp_kses($instructions, $allowed_html);
            }
        }

        /**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions($order, $sent_to_admin, $plain_text = false)
		{
			if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method && $order->has_status('on-hold')) {
				$settings = new Pkol_Admin('check');
				$allowed_html = $settings->getHtmlTags();
				$instructions = wpautop(wptexturize($this->instructions)) . PHP_EOL;
				echo wp_kses($instructions,$allowed_html);
			}
		}
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment($order_id)
		{
			$order = wc_get_order($order_id);
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status('on-hold', __('Awaiting offline payment', 'wc-gateway-offline'));
			// Reduce stock levels
			$order->reduce_order_stock();
			// Remove cart
			WC()->cart->empty_cart();
			// Return thankyou redirect
			return array(
				'result'   => 'success',
				'redirect'            => $this->get_return_url($order)
			);
		}
	} // end \WC_Gateway_Offline class
}

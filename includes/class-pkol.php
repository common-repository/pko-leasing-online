<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.pkoleasing.pl
 * @since      1.0.6
 *
 * @package    Pkol
 * @subpackage Pkol/includes
 */
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.6
 * @package    Pkol
 * @subpackage Pkol/includes
 * @author     PKO Leasing
 */
class Pkol
{
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.6
     * @access   protected
     * @var      Pkol_Loader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;
    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.6
     * @access   protected
     * @var      string $plugin_name The string used to uniquely identify this plugin.
     */
    protected $plugin_name;
    /**
     * The current version of the plugin.
     *
     * @since    1.0.6
     * @access   protected
     * @var      string $version The current version of the plugin.
     */
    protected $version;
    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.6
     */
    private $settings_base;
    private $test_url;
    private $endpoint;
    private $client;
    public $site_url;
    private string $leaseurl;
    private string $env;
    private $file;
    private $logger;
    public function __construct()
    {
        if (defined('PKOL_VERSION')) {
            $this->version = PKOL_VERSION;
        } else {
            $this->version = '1.0.6';
        }
        $this->plugin_name = 'pkol';
        $this->settings_base = 'wpt_';
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $settings = new Pkol_Admin('check');
        $this->test_url = $settings->getApiUrl();
        $this->endpoint = $settings->getEndpoint();
        $this->leaseurl = $settings->getLeaseUrl();
        $this->env = $settings->getEnv();
        $this->site_url = esc_url(get_site_url());
        $this->file = __FILE__;
        $this->logger = new Pkol_Logger();
    }
    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Pkol_Loader. Orchestrates the hooks of the plugin.
     * - Pkol_i18n. Defines internationalization functionality.
     * - Pkol_Admin. Defines all hooks for the admin area.
     * - Pkol_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.6
     * @access   private
     */
    private function load_dependencies()
    {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pkol-loader.php';
        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pkol-i18n.php';
        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-pkol-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pkol-payment.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pkol-logger.php';
        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-pkol-public.php';
        $this->loader = new Pkol_Loader();
    }
    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Pkol_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.6
     * @access   private
     */
    private function set_locale()
    {
        $plugin_i18n = new Pkol_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }
    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.6
     * @access   private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Pkol_Admin($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
    }
    public function woocommerce_add_gateway_name_gateway($methods)
    {
        $methods[] = 'PKO Leasing';
        return $methods;
    }
    public function check_product()
    {
    }
    public function check_rate($request)
    {
        return json_encode('ok');
    }
    public function render_cart_button($cart)
    {
        $admin = new Pkol_Admin('check');
        $verify_conection = $admin->check_connection();
        $allowed_html = $admin->getHtmlTags();
        if ($verify_conection) {
            return '';
        }
        $cart = WC()->cart;
        $totalQuant = count(WC()->cart->cart_contents);
        $total = WC()->cart->total;
        $shopID = get_option($this->settings_base . 'text_id');
        $secret = get_option($this->settings_base . 'text_secret');
        $enabled = get_option($this->settings_base . 'single_checkbox_cart');
        $color_style = get_option($this->settings_base . 'radio_type');
        $size_style = get_option($this->settings_base . 'radio_type_size');
        $show_rates = get_option($this->settings_base . 'radio_type_pricing');
        if ($enabled !== 'on') {
            return '';
        }
        $products = [];
        $prod_ids = [];
        $subtotal = 0;
        foreach (WC()->cart->cart_contents as $c) {
            $product = $c['data'];
            $id = $c['product_id'];
            array_push($prod_ids, $id);
            $quant = $c['quantity'];
            $id = $product->get_id();
            $product_price_net = explode('&nbsp;', strip_tags(wc_price(wc_get_price_excluding_tax($product), ['decimal_separator' => '.'])))[0];
            $product_price_net = str_replace([' ', '&nbsp;'], '', $product_price_net);
            $validate_dots = explode('.', $product_price_net);
            if (count($validate_dots) > 2) {
                $product_price_net = number_format($validate_dots[0] . $validate_dots[1] . '.' . $validate_dots[2], 2, '.', '');
            }
            $subtotal = $subtotal + ($product_price_net * $c['quantity']);
            $subtotal = str_replace([' ', '&nbsp;'], '', $subtotal);
            $tax = new WC_Tax();
            $taxes = $tax->get_rates($product->get_tax_class());
            $item_rate = null;
            if (is_array($taxes) && !empty($taxes)) {
                $rates = array_shift($taxes);
                $item_rate = round(array_shift($rates));
            }
            $variation = wc_get_product($product->get_id());
            if ($variation->get_parent_id() === 0) {
                $basket_product_id = $product->get_id();
            } else {
                $basket_product_id = $variation->get_parent_id();
            }
            $term_list = wp_get_post_terms($basket_product_id, 'product_cat', array('fields' => 'ids'));
            $cat_id = (int)$term_list[0];
            if ($item_rate) {
                $admin = new Pkol_Admin('check');
                $product_tax = $admin->getRate($item_rate);
            }
            array_push(
                $products,
                [
                    'categoryId' => $cat_id,
                    'quantity' => $c['quantity'],
                    'netValue' => $product_price_net,
                    'vatRate' => $product_tax
                ]
            );
        }
        $status = false;
        $subtotal = explode('&nbsp;', strip_tags(wc_price($subtotal, ['decimal_separator' => '.'])))[0];
        $subtotal = str_replace([' ', '&nbsp;'], '', $subtotal);
        $test = [
            'shopId' => $shopID,
            'widgetOption' => 1,
            'totalNetValue' => $subtotal,
            'uniqueItemQuantity' => $totalQuant,
            'source' => 'BASKET',
            'products' => $products
        ];
        $data = json_encode($test);
        //autoryzacja
        $settings_token = $shopID . ':' . $secret;
        $token = base64_encode($settings_token);
        $env_setting = ($this->env == 'dev' ? false : true);
        $this->client = new \GuzzleHttp\Client([
            'http_errors' => false,
            'verify' => $env_setting,
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token]
        ]);
        $price = null;

        try {
            $response = $this->client->request(
                'POST',
                $this->test_url . $this->endpoint,
                [
                    'http_errors' => false,
                    'body' => $data,
                    'timeout' => 5, // Response timeout
                    'connect_timeout' => 5, // Connection timeout
                ],
            );
            if ($response->getBody()) {
                $data = json_decode($response->getBody());
                if (!$data || isset($data->errorCode) || !empty($data->errors)) {
                    $this->logger->log(
                        $this->test_url . $this->endpoint,
                        json_encode($test),
                        json_encode($data),
                        'render_cart_button'
                    );
                    return null;
                }
                if (!$data->errors && $data->validityResult == 'VALID') {
                    if ($show_rates == 'yes') {
                        $price = $data->firstInstallment->value;
                    }
                    $status = true;
                } else {
                    $status = false;
                    $disabled = false;
                }
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->logger->log(
                $this->test_url . $this->endpoint,
                json_encode($test),
                $e->getMessage(),
                'render_cart_button'
            );
            return null;
        }
        $file = 'pko_' . $size_style . '_' . $color_style . '_leasing.' . ($size_style == 'xxl' ? 'webp' : 'svg');
        if ($size_style == 'big' && $color_style !== 'blue' && $show_rates == 'no') {
            $file = 'pko_' . $size_style . '_' . $color_style . '_leasing.webp';
        }
        $disabled = false;
        $img_styles = '';
        if ($status == false) {
            $show_rates = 'no';
            $file = 'pko_disabled_' . $size_style . '_leasing.' . ($size_style == 'xxl' ? 'webp' : 'svg');
            if ($size_style == 'big' || $size_style == 'xxl') {
                $img_styles = 'border: 1px solid #E6E6E6;border-radius:4px;';
            }
        }
        $text = '';
        $class = '';
        if ($color_style !== 'blue') {
            $border = '';
            $class = 'white';
        }
        if ($show_rates == 'yes' && $price) {
            if ($color_style == 'blue') {
                $color = '#fff';
            } else {
                $color = '#000';
            }
            if ($size_style == 'big') {
                $styles = 'style="position:absolute;right:3px;top:17px;line-height:17px;width:70px;display:inline-block;font-size:15px;font-family:pkobp;color:' . $color . '"';
                $file = str_replace('.svg', '_raty.webp', $file);
            } else if ($size_style == 'xxl') {
                $styles = 'style="position:absolute;right:8px;top:22px;line-height:17px;width:75px;display:inline-block;font-size:16px;font-family:pkobp;color:' . $color . '"';
                $file = str_replace('.webp', '_raty.webp', $file);
            } elseif ($size_style == 'medium') {
                $styles = 'style="position: absolute;right: -10px;top: 12px;line-height: 14px;width: 70px;display: inline-block;font-size: 12px;font-family: pkobp;color:' . $color . '"';
                $file = str_replace('.svg', '_raty.webp', $file);
            } else {
                $styles = 'style="position: absolute;right: -12px;top: 11px;line-height: 12px;width: 70px;display: inline-block;font-size: 11px;font-family: pkobp;color: #000;color:' . $color . '"';
                $file = str_replace('.svg', '_raty.webp', $file);
            }
            $text .= '<span class="pko_rate" ' . $styles . '>Rata od<br/> ' . $price . ' zł</span>';
        }
        if ($status == true) {
            $html_render = '<div class="pkol_widget" style="display:inline-block;"><div onClick="top.location.href=' . $this->site_url . '/pkoleasing_render?pid=' . implode(",", $prod_ids) . '&type=CART" data-baseurl="' . $this->site_url . '" data-pid="' . implode(",", $prod_ids) . '" id="lease_click"  style="display:inline-block;position:absolute;">' . $text . '<img src="' . $this->site_url . '/wp-content/plugins/pko-leasing-online/public/images/' . $file . '" /></div></div><style>#lease_click:hover {cursor:pointer;}</style>';
        } else {
            $html_render = '<div class="pkol_widget" ><div data-baseurl="' . $this->site_url . '" data-pid="' . $id . '"   style="display:inline-block;position:absolute;">' . $text . '<img style="' . $img_styles . '"class="' . $class . '" src="' . $this->site_url . '/wp-content/plugins/pko-leasing-online/public/images/' . $file . '" /></div></div>';
        }
        echo wp_kses($html_render, $allowed_html);
    }
    public function render_button()
    {
        global $product;
        $shopID = sanitize_text_field(get_option($this->settings_base . 'text_id'));
        $secret = sanitize_text_field(get_option($this->settings_base . 'text_secret'));
        $enabled = sanitize_text_field(get_option($this->settings_base . 'single_checkbox'));
        $color_style = sanitize_text_field(get_option($this->settings_base . 'radio_type'));
        $size_style = sanitize_text_field(get_option($this->settings_base . 'radio_type_size'));
        $show_rates = sanitize_text_field(get_option($this->settings_base . 'radio_type_pricing'));
        if ($enabled !== 'on') {
            return false;
        }
        $admin = new Pkol_Admin('check');
        $verify_conection = $admin->check_connection();
        $allowed_html = $admin->getHtmlTags();
        if ($verify_conection) {
            return '';
        }
        if ($shopID && $secret) {
        }
        $id = $product->get_id();
        $product_price = explode('&nbsp;', strip_tags(wc_price(wc_get_price_excluding_tax($product), ['decimal_separator' => '.'])))[0];
        $product_price = str_replace([' ', '&nbsp;'], '', $product_price);
        $tax = new WC_Tax();
        $taxes = $tax->get_rates($product->get_tax_class());
        $item_rate = null;
        if (is_array($taxes) && !empty($taxes)) {
            $rates = array_shift($taxes);
            $item_rate = round(array_shift($rates));
        }
        $term_list = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
        $cat_id = (int)$term_list[0];
        if ($item_rate) {
            $admin = new Pkol_Admin('check');
            $product_tax = $admin->getRate($item_rate);
        }
        $file = 'pko_' . $size_style . '_' . $color_style . '_leasing.' . ($size_style == 'xxl' ? 'webp' : 'svg');
        $disabled = false;
        if ($disabled) {
            $file = 'pko_disabled_' . $size_style . '_leasing.' . ($size_style == 'xxl' ? 'webp' : 'svg');
        }
        $text = '';
        $price = null; // ???
        if ($show_rates == 'yes' && $price) {
            if ($color_style == 'blue') {
                $color = '#fff';
            } else {
                $color = '#000';
            }
            if ($size_style == 'big') {
                $styles = 'style="position:absolute;right:3px;top:20px;line-height:17px;width:70px;display:inline-block;font-size:16px;font-family:pkobp;color:' . $color . '"';
                $file = str_replace('.svg', '_raty.webp', $file);
            } else if ($size_style == 'xxl') {
                $styles = 'style="position:absolute;right:21px;top:24px;line-height:17px;width:70px;display:inline-block;font-size:18px;font-family:pkobp;color:' . $color . '"';
                $file = str_replace('.webp', '_raty.webp', $file);
            } elseif ($size_style == 'medium') {
                $styles = 'style="position: absolute;right: -2px;top: 12px;line-height: 14px;width: 70px;display: inline-block;font-size: 13px;font-family: pkobp;color:' . $color . '"';
                $file = str_replace('.svg', '_raty.webp', $file);
            } else {
                $styles = 'style="position: absolute;right: -12px;top: 11px;line-height: 12px;width: 70px;display: inline-block;font-size: 11px;font-family: pkobp;color: #000;color:' . $color . '"';
                $file = str_replace('.svg', '_raty.webp', $file);
            }
            $text .= '<span class="pko_rate" ' . $styles . '>Rata od<br/> ' . sprintf('%0.2f', $price) . ' zł</span>';
        }
        $link = home_url('/cart'); // <== Here set button link
        $name = esc_html("Raty PKO", "woocommerce"); // <== Here set button name
        $class = 'button alt';
        $style = 'display: inline-block; margin-top: 12px;';
        $plugin_dir = WP_PLUGIN_DIR . '/pkol';
        // Output
        $form = '<input type="' . esc_attr("hidden") . '" id="pid" name="pid" value="' . $id . '" />';
        $form = '<input type="' . esc_attr("hidden") . '" id="url" name="url" value="' . $this->site_url . '/wp-json/pkol/v1/rate/' . $id . '" />';
        $form .= '<input type="' . esc_attr("hidden") . '" id="check_rates" name="check_rates" value="true" />';
        echo wp_kses($form, $allowed_html);
    }
    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.6
     * @access   private
     */
    private function define_public_hooks()
    {
        $plugin_public = new Pkol_Public($this->get_plugin_name(), $this->get_version());
        if (class_exists('WC_Payment_Gateway')) {
            load_plugin_textdomain('wc-gateway-name', false, dirname(plugin_basename(__FILE__)) . '/languages');
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'woocommerce_add_gateway_name_gateway'));
        }
        $show_button = get_option($this->settings_base . 'single_checkbox');
        add_action('template_redirect', function () {
            $check = sanitize_text_field($_SERVER['REQUEST_URI']);
            if (strpos($check, 'pkoleasing_render') !== false) {
                http_response_code(200);
                $shopID = get_option($this->settings_base . 'text_id');
                $type = '';
                $pid = sanitize_text_field($_GET['pid']);
                $variant_id = isset($_GET['variant_id']) ? sanitize_text_field($_GET['variant_id']) : null;
                $pid = explode(',', $pid);
                if (count($pid) == 1) {
                    $product = wc_get_product($pid[0]);
                    $permalink = $product->get_permalink();
                    $returnUrl = $permalink;
                } else {
                    $returnUrl = get_site_url();
                }
                $n = 1;
                $form = '<form action="' . esc_url($this->leaseurl) . '" id="leasing" method="POST">';
                $form .= '<input type="' . esc_attr("hidden") . '" name="shopId" value="' . esc_attr($shopID) . '" />';
                $form .= '<input type="' . esc_attr("hidden") . '" name="returnLink" value="' . esc_attr($returnUrl) . '" />';
                $subtotal = WC()->cart->cart_contents_total;
                if (sanitize_text_field($_GET['type']) == 'CART') {
                    $pid = WC()->cart->cart_contents;
                    $total = 0;
                    $totalQuant = count(WC()->cart->cart_contents);
                    $form .= '<input type="' . esc_attr("hidden") . '" name="uniqueItemQuantity" value="' . esc_attr($totalQuant) . '" />';
                } else {
                    $product = wc_get_product($pid[0]);
                    $total = wc_get_price_including_tax($product);
                    $subtotal = explode('&nbsp;', strip_tags(wc_price(wc_get_price_excluding_tax($product), ['decimal_separator' => '.'])))[0];
                    $subtotal = str_replace([' ', '&nbsp;'], '', $subtotal);
                    $totalQuant = intval($_GET['q']);
                    $form .= '<input type="' . esc_attr("hidden") . '" name="uniqueItemQuantity" value="1" />';
                }
                $subtotal = 0;
                foreach ($pid as $p) {
                    if (sanitize_text_field($_GET['type']) == 'CART') {
                        /* strzał do koszyka*/
                        $product = $p['data'];
                        $type = 'BASKET';
                        $p_quant = $p['quantity'];

                        // Sprawdź, czy jest wariant, jeśli tak, użyj jego cen
                        $variant_id = isset($p['variation_id']) ? $p['variation_id'] : 0;
                        if (!empty($variant_id)) {
                            $variation = wc_get_product($variant_id);
                            $product_price_net = wc_get_price_excluding_tax($variation);
                            $product_price = wc_get_price_including_tax($variation);
                        } else {
                            $product_price_net = wc_get_price_excluding_tax($product);
                            $product_price = wc_get_price_including_tax($product);
                        }

                        // Kontrola liczby przecinków w cenie netto
                        $validate_dots = explode('.', $product_price_net);
                        if (count($validate_dots) > 2) {
                            $product_price_net = number_format($validate_dots[0] . $validate_dots[1] . '.' . $validate_dots[2], 2, '.', '');
                        }

                        $total = $total + ($product_price * (int)$p_quant);
                        $subtotal = $subtotal + ($product_price_net * (int)$p_quant);
                    } else {
                        $p_quant = 1;
                        $type = 'ITEM';
                        $validate_dots = explode('.', wc_get_price_excluding_tax($product));
                        $variant_id = isset($_GET['variant_id']) ? $_GET['variant_id'] : 0;
                        if (!empty($variant_id)) {
                            $variation = wc_get_product($variant_id);
                            $product_price_net = wc_get_price_excluding_tax($variation);
                            $product_price = wc_get_price_including_tax($variation);
                        } else {
                            $product_price_net = wc_get_price_excluding_tax($product);
                            $product_price = wc_get_price_including_tax($product);
                        }
                        if (count($validate_dots) > 2) {
                            $product_price_net = number_format($validate_dots[0] . $validate_dots[1] . '.' . $validate_dots[2], 2, '.', '');
                        }
                        $subtotal = $product_price_net;
                        $total = $product_price;
                    }

                    $tax = new WC_Tax();
                    $taxes = $tax->get_rates($product->get_tax_class());
                    $item_rate = null;
                    if (is_array($taxes) && !empty($taxes)) {
                        $rates = array_shift($taxes);
                        $item_rate = round(array_shift($rates));
                        if ($item_rate) {
                            $admin = new Pkol_Admin('check');
                            $product_tax = $admin->getRate($item_rate);
                            if (!$product_tax) {
                                $product_tax = 1;
                            }
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
                    if (!empty($variant_id) && $type == 'ITEM') {
                        $variation = wc_get_product($variant_id);
                        if ($variation && $variation->is_type('variation')) {
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
                    }
                    $product_name_with_variant = $product->get_name();
                    if ($variant_name) {
                        $product_name_with_variant .= ' - ' . $variant_name;
                    }
                    $form .= '<input type="' . esc_attr("hidden") . '" name="productName' . esc_attr($n) . '" value="' . esc_attr($product_name_with_variant). '" />';
                    $form .= '<input type="' . esc_attr("hidden") . '" name="productPrice' . esc_attr($n) . '" value="' . esc_attr(number_format($product_price,2,'.','')) . '" />';
                    $form .= '<input type="' . esc_attr("hidden") . '" name="productNetPrice' . esc_attr($n) . '" value="' . esc_attr(number_format($product_price_net,2,'.','')) . '" />';
                    $form .= '<input type="' . esc_attr("hidden") . '" name="productQuantity' . esc_attr($n) . '" value="' . esc_attr($p_quant) . '" />';
                    $form .= '<input type="' . esc_attr("hidden") . '" name="productCategory' . esc_attr($n) . '" value="' . esc_attr($cat_id) . '" />';
                    $form .= '<input type="' . esc_attr("hidden") . '" name="productVatRate' . esc_attr($n) . '" value="' . esc_attr($product_tax) . '" />';
                    $form .= '<input type="' . esc_attr("hidden") . '" name="productAvatarUrl' . esc_attr($n) . '" value="' . esc_url($image) . '" />';
                    $n++;
                }
                $form .= '<input type="' . esc_attr("hidden") . '" name="totalValue" value="' . esc_attr(number_format($total,2,'.','')) . '" />';
                $form .= '<input type="'.esc_attr("hidden").'" name="totalNetValue" value="' . esc_attr(number_format($subtotal, 2, '.', '')) . '" />';
                $form .= '<input type="' . esc_attr("hidden") . '" name="source" value="' . esc_attr($type) . '" />';
                $form .= '</form>';
                $form .= '<script>document.getElementById("leasing").submit();</script>';
                $admin = new Pkol_Admin('check');
                $allowed_html = $admin->getHtmlTags();
                echo wp_kses($form, $allowed_html);
                exit();
            }
        });
        $this->loader->add_action('rest_api_init', $plugin_public, 'addEndpoint', 30);
        if ($show_button == 'on') {
            add_action('woocommerce_after_add_to_cart_button', [$this, 'render_button'], 30);
            add_action('woocommerce_after_cart_table', [$this, 'render_cart_button'], 30);
            // $this->loader->add_action('woocommerce_after_add_to_cart_button', plugin_basename($this->file), 'render_button');
        }
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.6
     */
    public function run()
    {
        $this->loader->run();
    }
    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return    string    The name of the plugin.
     * @since     1.0.6
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }
    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return    Pkol_Loader    Orchestrates the hooks of the plugin.
     * @since     1.0.6
     */
    public function get_loader()
    {
        return $this->loader;
    }
    /**
     * Retrieve the version number of the plugin.
     *
     * @return    string    The version number of the plugin.
     * @since     1.0.6
     */
    public function get_version()
    {
        return $this->version;
    }


}

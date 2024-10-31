<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.pkoleasing.pl
 * @since      1.0.6
 *
 * @package    Pkol
 * @subpackage Pkol/public
 */
/**
 * Class responsible for the public-facing functionality of the plugin.
 *
 * @package    Pkol
 * @subpackage Pkol/public
 * @author     PKO Leasing
 */
class Pkol_Public {
    private $plugin_name;
    private $version;
    private $settings_base = 'wpt_';
    public $site_url;
    private $test_url;
    private $endpoint;
    private $env;
    private $client;
    private $logger;
    /**
     * Constructor
     *
     * @since 1.0.3
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of the plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $settings = new Pkol_Admin('check');
        $this->test_url = $settings->getApiUrl();
        $this->endpoint = $settings->getEndpoint();
        $this->env = $settings->getEnv();
        $this->site_url = esc_url(get_site_url());
        $this->logger = new Pkol_Logger();
    }
    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since 1.0.3
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/pkol-public.css',
            [],
            $this->version,
            'all'
        );
    }
    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since 1.0.3
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/pkol-public.js',
            ['jquery'],
            $this->version,
            false
        );
    }
    /**
     * Handles the rate checking request.
     *
     * @since 1.0.3
     * @param WP_REST_Request $request The request object.
     * @return void
     */
    public function check_rate($request) {
        $product = $this->get_product($request);
        if (!$product) {
            return '';
        }
        $settings = $this->get_plugin_settings();
        if ($settings['verify_connection'] || $settings['enabled'] !== 'on') {
            return false;
        }
        $product_price_net = $this->get_product_price_net($product);
        $product_tax = $this->get_product_tax($product);
        $cat_id = $this->get_product_category_id($product);
        $payload = $this->build_payload($settings['shopID'], $product_price_net, $cat_id, $product_tax);
        $price = $this->fetch_rate($payload, $settings['token'], $settings['env_setting']);
        if(!$price){
            exit;
        }
        $output = $this->generate_output($price, $settings, $product->get_id());
        echo json_encode($output);
        exit();
    }
    /**
     * Retrieves the product object based on the request parameter.
     *
     * @param WP_REST_Request $request The request object.
     * @return WC_Product|false The WooCommerce product object or false if not found.
     */
    private function get_product($request) {
        $id = esc_html($request->get_param('id'));
        return wc_get_product($id);
    }
    /**
     * Retrieves plugin settings and configurations.
     *
     * @return array Associative array of settings.
     */
    private function get_plugin_settings() {
        $shopID = sanitize_text_field(get_option($this->settings_base . 'text_id'));
        $secret = sanitize_text_field(get_option($this->settings_base . 'text_secret'));
        $enabled = sanitize_text_field(get_option($this->settings_base . 'single_checkbox'));
        $color_style = sanitize_text_field(get_option($this->settings_base . 'radio_type'));
        $size_style = sanitize_text_field(get_option($this->settings_base . 'radio_type_size'));
        $show_rates = sanitize_text_field(get_option($this->settings_base . 'radio_type_pricing'));
        $admin = new Pkol_Admin('check');
        $verify_connection = $admin->check_connection();
        $settings_token = $shopID . ':' . $secret;
        $token = base64_encode($settings_token);
        $env_setting = ($this->env == 'dev' ? false : true);
        return [
            'shopID' => $shopID,
            'token' => $token,
            'enabled' => $enabled,
            'color_style' => $color_style,
            'size_style' => $size_style,
            'show_rates' => $show_rates,
            'verify_connection' => $verify_connection,
            'env_setting' => $env_setting
        ];
    }
    /**
     * Retrieves the net price of the product.
     *
     * @param WC_Product $product The WooCommerce product object.
     * @return float The net price of the product.
     */
    private function get_product_price_net($product) {
        $product_price_net = explode('&nbsp;', strip_tags(wc_price(wc_get_price_excluding_tax($product), ['decimal_separator' => '.'])))[0];
        $product_price_net = str_replace([' ', '&nbsp;'], '', $product_price_net);

        $validate_dots = explode('.', $product_price_net);
        if (count($validate_dots) > 2) {
            $product_price_net = number_format($validate_dots[0] . $validate_dots[1] . '.' . $validate_dots[2], 2, '.', '');
        }
        return $product_price_net;
    }
    /**
     * Retrieves the tax rate of the product.
     *
     * @param WC_Product $product The WooCommerce product object.
     * @return int|null The tax rate of the product.
     */
    private function get_product_tax($product) {
        $tax = new WC_Tax();
        $taxes = $tax->get_rates($product->get_tax_class());
        $item_rate = null;
        if (is_array($taxes) && !empty($taxes)) {
            $rates = array_shift($taxes);
            $item_rate = round(array_shift($rates));
        }
        if ($item_rate) {
            $admin = new Pkol_Admin('check');
            return $admin->getRate($item_rate);
        }
        return null;
    }
    /**
     * Retrieves the category ID of the product.
     *
     * @param WC_Product $product The WooCommerce product object.
     * @return int The category ID of the product.
     */
    private function get_product_category_id($product) {
        $term_list = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids']);
        return !empty($term_list) ? (int) $term_list[0] : 0;
    }
    /**
     * Builds the payload for the API request.
     *
     * @param string $shopID The shop ID.
     * @param float $product_price_net The net price of the product.
     * @param int $cat_id The category ID of the product.
     * @param int|null $product_tax The tax rate of the product.
     * @return array The payload for the API request.
     */
    private function build_payload($shopID, $product_price_net, $cat_id, $product_tax) {
        return [
            'shopId' => $shopID,
            'widgetOption' => 1,
            'totalNetValue' => $product_price_net,
            'uniqueItemQuantity' => 1,
            'source' => 'ITEM',
            'products' => [
                [
                    'categoryId' => $cat_id,
                    'quantity' => 1,
                    'netValue' => $product_price_net,
                    'vatRate' => $product_tax
                ]
            ]
        ];
    }
    /**
     * Fetches the rate from the external API.
     *
     * @param array $payload The payload for the API request.
     * @param string $token The authorization token.
     * @param bool $env_setting Environment setting for SSL verification.
     * @return float|null The rate price or null if unsuccessful.
     */
    private function fetch_rate($payload, $token, $env_setting)
    {
        // Tworzenie instancji klienta Guzzle
        $this->client = new \GuzzleHttp\Client([
            'http_errors' => false,
            'verify' => $env_setting,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        try {
            $response = $this->client->request(
                'POST',
                $this->test_url . $this->endpoint,
                [
                    'http_errors' => false,
                    'body' => json_encode($payload),
                    'timeout' => 5, // Response timeout
                    'connect_timeout' => 5, // Connection timeout
                ]
            );

            $data = json_decode($response->getBody());

            if (isset($data->errorCode) || !empty($data->errors)) {
                $this->logger->log(
                    $this->test_url . $this->endpoint,
                    json_encode($payload),
                    json_encode($data),
                    'fetch_rate'
                );
                return null;
            }

            if ($data && !$data->errors && $data->validityResult === 'VALID') {
                return $data->firstInstallment->value ?? null;
            }

        } catch (\Throwable $e) {
            $this->logger->log(
                $this->test_url . $this->endpoint,
                json_encode($payload),
                $e->getMessage(),
                'fetch_rate'
            );
            return null;
        }

        return null;
    }

    /**
     * Generates the HTML output for the rate information.
     *
     * @param float|null $price The rate price.
     * @param array $settings The plugin settings.
     * @param int $product_id The product ID.
     * @return string The HTML output.
     */
    private function generate_output($price, $settings, $product_id) {
        $size_style = $settings['size_style'];
        $color_style = $settings['color_style'];
        $show_rates = $settings['show_rates'];
        $file = 'pko_' . $size_style . '_' . $color_style . '_leasing.' . ($size_style === 'xxl' ? 'webp' : 'svg');
        if ($size_style === 'big' && $color_style !== 'blue' && $show_rates === 'no') {
            $file = 'pko_' . $size_style . '_' . $color_style . '_leasing.webp';
        }
        $img_styles = '';
        if ($price === null) {
            $show_rates = 'no';
            $file = 'pko_disabled_' . $size_style . '_leasing.' . ($size_style === 'xxl' ? 'webp' : 'svg');
            if ($size_style === 'big' || $size_style === 'xxl') {
                $img_styles = 'border: 1px solid #E6E6E6; border-radius: 4px;';
            }
        }
        $text = '';
        $class = $color_style !== 'blue' ? 'white' : '';
        if ($show_rates === 'yes' && $price) {
            $color = $color_style === 'blue' ? '#fff' : '#000';
            $styles = $this->generate_rate_styles($size_style, $color);
            $file = preg_replace('/\.(svg|webp)$/', '_raty.webp', $file);
            $text .= '<span class="pko_rate" ' . $styles . '>Rata od<br/> ' . sprintf('%0.2f', $price) . ' zł</span>';
        }
        return sprintf(
            '<br><br><div class="pkol_widget">
                <div data-baseurl="%s" data-pid="%d" id="lease_click" style="display:inline-block;position:relative;cursor:pointer">
                    %s
                    <img class="%s" src="%s/wp-content/plugins/pko-leasing-online/public/images/%s" style="%s" />
                </div>
            </div>',
            $this->site_url,
            $product_id,
            $text,
            $class,
            $this->site_url,
            $file,
            $img_styles
        );
    }
    /**
     * Generates the inline styles for the rate text based on size and color.
     *
     * @param string $size_style The size style of the rate.
     * @param string $color The color style of the rate.
     * @return string The inline styles.
     */
    private function generate_rate_styles($size_style, $color) {
        switch ($size_style) {
            case 'big':
                return 'style="position:absolute;right:4px;top:17px;line-height:17px;width:70px;display:inline-block;font-size:14px;font-family:pkobp;color:' . $color . '"';
            case 'xxl':
                return 'style="position:absolute;right:5px;top:20px;line-height:17px;width:80px;display:inline-block;font-size:15px;font-family:pkobp;color:' . $color . '"';
            case 'medium':
                return 'style="position: absolute;right: -9px;top: 13px;line-height: 14px;width: 70px;display: inline-block;font-size: 12px;font-family: pkobp;color:' . $color . '"';
            default:
                return 'style="position: absolute;right: -12px;top: 11px;line-height: 12px;width: 60px;display: inline-block;font-size: 11px;font-family: pkobp;color:' . $color . '"';
        }
    }
    /**
     * Register REST endpoint.
     *
     * @since 1.0.3
     */
    public function addEndpoint() {
        register_rest_route('pkol/v1', '/rate/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'check_rate'],
            'permission_callback' => '__return_true',
        ]);
    }
}

<?php
/**
 * Fired during plugin activation
 *
 * @link       https://www.pkoleasing.pl
 * @since      1.0.6
 *
 * @package    Pkol
 * @subpackage Pkol/includes
 */
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.6
 * @package    Pkol
 * @subpackage Pkol/includes
 * @author     PKO Leasing
 */
class Pkol_Activator
{
    /**
     * Fired during plugin activation
     *
     * @since    1.0.6
     */
    public static function activate()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pkol_logs';
        $charset_collate = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                shop_id int(11) DEFAULT NULL,
                url varchar(255) DEFAULT NULL,
                request longtext DEFAULT NULL,
                response longtext DEFAULT NULL,
                message text DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}

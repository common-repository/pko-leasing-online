<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://www.pkoleasing.pl
 * @since      1.0.6
 *
 * @package    Pkol
 * @subpackage Pkol/includes
 */
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.6
 * @package    Pkol
 * @subpackage Pkol/includes
 * @author     PKO Leasing
 */
class Pkol_Deactivator {
	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.3
	 */
	public static function deactivate() {
		$data = [
			'wpt_text_secret',
			'wpt_text_id',
			'wpt_single_checkbox',
			'wpt_radio_env',
			'wpt_radio_type',
			'wpt_radio_type_size',
			'wpt_radio_type_pricing'
		];
		foreach ($data as $o) {
			delete_option($o);
		}

        global $wpdb;

        $table_name = $wpdb->prefix . 'pkol_logs';
        $sql = "DROP TABLE IF EXISTS $table_name;";
        $wpdb->query($sql);
    }
}

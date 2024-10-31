<?php
/**
 * Plugin Name: Profile Update Notification
 * Plugin URI: http://allaboutweb.ch/wp-plugins/
 * Description: This plugin extends the WP-Members plugin from Chad Butler. It allows users to get notifications when a user updates his wp-members fields.
 * Version: 2.0.0
 * Author: AllAboutWeb
 * Author URI: http://allaboutweb.ch
 * License: GPL3
 * Text Domain: pun
 * Domain Path: /languages
 */

function pun_name() {
	return __('Profile Update Notification Settings', 'pun');
}

include_once(ABSPATH.'wp-admin/includes/plugin.php');
if (!is_plugin_active('wp-members/wp-members.php')) {
	add_action('admin_notices', 'pun_wpmem_not_installed');
} else {
	add_action('init', 'pun_load_textdomain');
	add_action('wpmem_pre_update_data', 'pun_get_meta_values');
	add_action('wpmem_post_update_data', 'pun_profile_update');
	add_action('admin_menu', 'pun_menu');
	add_action('admin_init', 'pun_settings');
	if(isset($_GET['settings-updated'])) {
		add_action('admin_notices', 'pun_settings_updated');
	}
}

function pun_wpmem_not_installed() {
	$class = 'error';
	$message = sprintf(__('The Profile Update Notification plugin needs the %s to be installed AND activated!'), '<a href="https://wordpress.org/plugins/wp-members/" target="_blank">WP-Members Plugin</a>');
	printf('<div class="%s"><p>%s</p></div>', $class, $message);
}

function pun_settings_updated() {
	$class = 'updated';
	$message = __('Settings saved!', 'pun');
	printf('<div class="%s"><p>%s</p></div>', $class, $message);
}

function pun_get_meta_values() {
	global $pun_user_meta;
	$pun_user_meta = array();
	$pun_fields = get_option('pun_fields', array());
	foreach ($pun_fields as $field) {
		$pun_user_meta[$field] = get_user_meta(get_current_user_id(), $field, true);
	}
}

function pun_get_new_meta_values() {
	$pun_new_user_meta = array();
	$pun_fields = get_option('pun_fields', array());
	foreach ($pun_fields as $field) {
		$pun_new_user_meta[$field] = get_user_meta(get_current_user_id(), $field, true);
	}
	return $pun_new_user_meta;
}

function pun_compare_values($old_values, $new_values) {
	$changed_values = array();
	foreach ($old_values as $attribute => $value) {
		if ($old_values[$attribute] != $new_values[$attribute]) {
			$changed_values[$attribute]['old_value'] = $old_values[$attribute];
			$changed_values[$attribute]['new_value'] = $new_values[$attribute];
		}
	}
	return $changed_values;
}

function pun_get_recipients() {
	// create recipients array
	$pun_roles = get_option('pun_roles', array());
	$users = array();
	foreach ($pun_roles as $role) {
		$users = array_merge($users, get_users('role='.$role));
	}
	$recipients = array();
	foreach ($users as $user) {
		$recipients[] = $user->user_email;
	}
	return $recipients;
}

function pun_profile_update($user) {
	global $pun_user_meta;

	// only do it when pun is activated and the user updates his own profile
	if (get_option('pun_active') && $user['ID'] == get_current_user_id()) {
		$changed_user = get_userdata($user['ID']);
		$new_user_meta = array_map(function($a) { return $a[0]; }, get_user_meta($user['ID']));

		$changed_values = pun_compare_values($pun_user_meta, pun_get_new_meta_values());
		if (!empty($changed_values)) {
			// create table for changed values
			$table = sprintf('<style>
								p {
									color: #555555;
								}
	
								td, th {
									border: 1px #555555 solid;
									color: #555555;
								}
							</style>
							<table>
								<tbody>
								<tr>
									<th width="200">%s</th>
									<th width="200">%s</th>
									<th width="200">%s</th>
								</tr>', __('Attribute'), __('Old value'), __('New value'));
			
			foreach ($changed_values as $attribute => $value) {
				$table .= sprintf('<tr>
									<td>%s</td>
									<td>%s</td>
									<td>%s</td>
								</tr>', $attribute, $value['old_value'], $value['new_value']);
			}
			$table .= '</tbody>
					</table>';
	
			// Set mail content type to html
			add_filter('wp_mail_content_type', 'pun_set_html_content_type');
			wp_mail(pun_get_recipients(),
					__('User profile update!'),
					sprintf('<p>%s %s</p>
							<p>%s</p>
							%s',
							$changed_user->display_name,
							__(' has changed his profile!'),
							__('The following attributes has been changed:'),
							$table));
			// Reset content-type to avoid conflicts
			remove_filter('wp_mail_content_type', 'pun_set_html_content_type');
		}
	}
}

function pun_menu() {
	add_menu_page(pun_name(), 'Profile Update Notification', 'administrator', 'pun-settings', 'pun_settings_page', 'dashicons-admin-generic');
}

function pun_settings() {
	global $wp_roles;
	register_setting('pun-settings-group', 'pun_active');
	register_setting('pun-settings-group', 'pun_roles');
	$pun_wpmem_fields = get_option('wpmembers_fields');
	register_setting('pun-settings-group', 'pun_fields');
}

function pun_settings_page() {
	if(!current_user_can('manage_options'))
		wp_die(__('Cheatin\' uh?', 'pun'));
	global $wp_roles;
?>
	<div class="wrap">
	<h2><?php echo pun_name(); ?></h2>
	
	<form method="post" action="options.php">
	    <?php settings_fields('pun-settings-group'); ?>
	    <?php do_settings_sections('pun-settings-group'); ?>
	    <table class="form-table">
	        <tr>
	        	<th scope="row"><?php echo __('Notifications active?', 'pun'); ?></th>
	        	<td><input type="checkbox" name="pun_active" value="1" <?php echo (esc_attr(get_option('pun_active')) ? 'checked' : ''); ?> /></td>
	        </tr>
	        <tr>
	        	<th scope="row"><?php echo __('Send notifications to users with the following roles' ,'pun'); ?></th>
	        	<td>
	        	<table>
	        	<?php
	        	$selected_roles = get_option('pun_roles', array());
	        	foreach ($wp_roles->roles as $rolename => $role) {
	        		printf('<tr>
	        					<td><input id="%s" type="checkbox" name="pun_roles[]" value="%s" %s /></td>
	        					<td><label for="%s">%s</label></td>
	        				</tr>', $rolename, $rolename, (in_array($rolename, (empty($selected_roles) ? array() : $selected_roles)) ? 'checked' : ''), $rolename, __($role['name']));
	        	}
	        	?>
	        	</table>
	        	</td>
	        </tr>
	        <tr>
	        	<th scope="row"><?php echo __('Send notifications when the following field has been changed', 'pun'); ?></th>
	        	<td>
	        	<table>
	        	<?php
	        	$pun_wpmem_fields = get_option('wpmembers_fields');
	        	$selected_fields = get_option('pun_fields');
	        	foreach ($pun_wpmem_fields as $field) {
        			printf('<tr>
	        					<td><input id="%s" type="checkbox" name="pun_fields[]" value="%s" %s /></td>
	        					<td><label for="%s">%s</label></td>
	        				</tr>', $field[2], $field[2], (in_array($field[2], (empty($selected_fields) ? array() : $selected_fields)) ? 'checked' : ''), $field[2], __($field[1]));
	        	}
	        	?>
	        	</table>
	        	</td>
	        </tr>
	    </table>
	    <?php
	    submit_button();
	    ?>
	</form>
	</div>
<?php
}

function pun_load_textdomain() {
	$domain = 'pun';
	$locale = apply_filters('plugin_locale', get_locale(), $domain);
	load_textdomain($domain, trailingslashit(WP_LANG_DIR).$domain.'/'.$domain.'-'.$locale.'.mo');
	load_plugin_textdomain($domain, false, basename(dirname(__FILE__)).'/languages/');
}

function pun_set_html_content_type() {
	return 'text/html';
}
?>
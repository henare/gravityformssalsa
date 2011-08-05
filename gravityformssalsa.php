<?php
/*
Plugin Name: Gravity Forms Salsa Add-On
Plugin URI: https://github.com/henare/gravityformssalsa
Description: Integrates Gravity Forms with Salsa allowing form submissions to be automatically sent to your Salsa account
Version: 0.0.1
Author: Henare Degan
Author URI: http://www.henaredegan.com/
License: GPL v2

------------------------------------------------------------------------

Copyright (C) 2011 Henare Degan

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

*/

include_once "salsa/salsa-core.php";

add_action('init', 'gf_salsa_init');
add_action('gform_post_submission', 'gf_salsa_submit', 10, 2);

function gf_salsa_init() {
  // Creates a Settings page on Gravity Forms' settings screen
  RGForms::add_settings_page("Salsa", "gf_salsa_settings_page", plugins_url( 'images/salsa_logo.png', __FILE__ ));
}

function gf_salsa_settings_page() {
  // Access control
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

  // Process submitted options
  if (isset($_POST['gf_salsa_hidden']) && $_POST['gf_salsa_hidden'] == 'Y'){
    // CSRF check
    check_admin_referer('gf_salsa_settings');

    $gf_salsa_options = array(
        'salsa_username'      => $_POST['salsa_username'],
        'salsa_password'      => $_POST['salsa_password'], // Should probably encrypt this
        'salsa_url'           => $_POST['salsa_url'],
        'salsa_enabled_forms' => $_POST['salsa_enabled_forms']
    );
    update_option('gf_salsa_options', $gf_salsa_options);

    echo '<div class="updated"><p><strong>Options saved.</strong></p></div>';
  }

  // Get current options
  $gf_salsa_options = get_option('gf_salsa_options');

  // Render the settings page
  ?>
  <div class="wrap">
    <h3>Salsa integration settings</h3>
    <form method="post" action="">
      <input type="hidden" name="gf_salsa_hidden" value="Y" />
      <?php wp_nonce_field('gf_salsa_settings'); ?>
      <h4>Salsa enabled forms</h4>
      <p>
        <input type="text" name="salsa_enabled_forms" value="<?php if (isset($gf_salsa_options['salsa_enabled_forms'])) { echo $gf_salsa_options['salsa_enabled_forms']; } ?>" size="26" />
        <span class="description">Enter a comma-separated list of form IDs that should submit data to Salsa</span>
      </p>
      <h4>Salsa account settings</h4>
      <p>
        Salsa Username<br />
        <input type="text" name="salsa_username" value="<?php if (isset($gf_salsa_options['salsa_username'])) { echo $gf_salsa_options['salsa_username']; } ?>" size="26" />
        <span class="description">Enter the Salsa username to access the API, this nornally your email address</span>
      </p>

      <p>
        Salsa Password<br />
        <input type="text" name="salsa_password" value="<?php if (isset($gf_salsa_options['salsa_password'])) { echo $gf_salsa_options['salsa_password']; } ?>" size="26" />
        <span class="description">Enter your Salsa password that has access to the API</span><br /><br />
        <span class="description"><strong>IMPORTANT:</strong> This password is stored and displayed in the settings as plain text, be sure not to use a valuable password</span>
      </p>

      <p>
        Salsa URL<br />
        <input type="text" name="salsa_url" value="<?php if(isset($gf_salsa_options['salsa_url'])) { echo $gf_salsa_options['salsa_url']; } ?>" size="26" />
        <span class="description">Enter the base URL of your Salsa instance. It should looks something like <code>http://salsa.wiredforchange.com/</code></span>
      </p>

      <p class="submit">
        <input type="submit" name="Submit" class="button-primary" value="Save Changes" />
      </p>
    </form>
  </div>
  <?php
}

// Authenticate and instantiate the Salsa connector
function gf_salsa_logon() {
  $gf_salsa_options = get_option('gf_salsa_options');

  return GFSalsaConnector::initialize(
    $gf_salsa_options['salsa_url'],
    $gf_salsa_options['salsa_username'],
    $gf_salsa_options['salsa_password']
  );
}

function gf_salsa_submit($entry, $form) {
  $gf_salsa_options = get_option('gf_salsa_options');
  $salsa = gf_salsa_logon();

  // Check if the form submitted is "Salsa enabled"
  if(!in_array($form['id'], explode(',', $gf_salsa_options['salsa_enabled_forms']))) {
    return;
  }

  /* Iterate through form items to see if they have an admin value
   * if they do, submit them to salsa
   */
  foreach($form['fields'] as $field) {
    if($field['adminLabel']) {
      $p[$field['adminLabel']] = $entry[$field['id']];
    }
  }

  // The Salsa object we want to save
  $p['object'] = "supporter";
  // Make the API return XML so we can parse it
  $p['xml'] = true;

  // Submit the supporter to Salsa
  $result = $salsa->post("/save", $p);

  if ($result->error) {
    echo "Sorry, your details couldn't been saved. Please contact the site owner to report this problem.";
  }else{
    // TODO: Add supporter to groups
  }
}

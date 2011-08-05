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

// Authenticate and instantiate the Salsa connector
function gf_salsa_logon() {
  // Testing with hard coded values
  //$gf_salsa_options = get_option('gf_salsa_options');
  $gf_salsa_options = array(
        'salsa_username'    => '',
        'salsa_password'    => '',
        'salsa_url'         => 'http://salsa.wiredforchange.com/'
  );

  return GFSalsaConnector::initialize(
    $gf_salsa_options['salsa_url'],
    $gf_salsa_options['salsa_username'],
    $gf_salsa_options['salsa_password']
  );
}

add_action("gform_post_submission", "gf_salsa_submit", 10, 2);

function gf_salsa_submit($entry, $form) {
  $salsa = gf_salsa_logon();
  // TODO: Check if the form submitted is "Salsa enabled" and get the group

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

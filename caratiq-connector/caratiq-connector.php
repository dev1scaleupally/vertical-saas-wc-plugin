<?php
/*
Plugin Name: CaratIQ Connector
Description: A plugin to connect to CaratIQ API and handle authorization and key management.
Version: 1.0
Author: Scaleupally
*/

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
include_once ABSPATH . 'wp-admin/includes/plugin.php';

if (!is_plugin_active('woocommerce/woocommerce.php')) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p><strong>CaratIQ</strong></p></div>';
        exit();
    });
    // Deactivate the plugin
    deactivate_plugins(plugin_basename(__FILE__));
    return;
}


// Hook to add "Connect to CaratIQ" button in the admin menu
add_action('admin_menu', 'caratiq_connector_menu');

function caratiq_connector_menu()
{
    add_menu_page(
        'CaratIQ Connector', // Page title
        'CaratIQ Connector', // Menu title
        'manage_options',    // Capability
        'caratiq-connector', // Menu slug
        'caratiq_connector_page' // Callback function
    );
}

function caratiq_connector_page()
{
?>
    <div class="wrap">
        <h1>Connect to CaratIQ</h1>
        <a href="<?php echo caratiq_get_authorization_url(); ?>" class="button-primary">Connect to CaratIQ</a>
    </div>
    <?php
}

function caratiq_get_authorization_url()
{
    $redirect_uri = admin_url('admin.php?page=caratiq-connector'); // Use admin URL as redirect
    $auth_url = "https://caratiq-customer-website.scaleupdevops.in/woocommerce-auth?redirect_uri=$redirect_uri";
    return $auth_url;
}

// Hook to handle the authorization code and exchange for token
add_action('admin_init', 'caratiq_handle_auth_code');

function caratiq_handle_auth_code()
{
    if (isset($_GET['page']) && $_GET['page'] == 'caratiq-connector' && isset($_GET['auth_code'])) {
        $authorization_code = sanitize_text_field($_GET['auth_code']);
        caratiq_verify_authorization_code($authorization_code);
    }
}

function caratiq_verify_authorization_code($authorization_code)
{
    // Dummy API URL, replace with the actual API endpoint
    $api_url = 'https://caratiq-cms.scaleupdevops.in/api/verify-auth-code';


    $response = wp_remote_post($api_url, array(
        'method'    => 'POST',
        'body'      => array(
            'code'          => $authorization_code,
        )
    ));

    if (is_wp_error($response)) {
        wp_die('Error in verifying authorization code.');
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['status']) && $body['status'] === 200) {
        // Store the access token and proceed to create keys
        update_option('caratiq_access_token', $body['data']["token"]);
        caratiq_create_customer_key($body['data']["token"]);
    } else {
        wp_die('Invalid authorization code response.');
    }
}

function caratiq_create_customer_key($token)
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $user_id = 1;
    $description = "Connection with CaratIQ";
    $permissions = "read_write";
    $response = generate_woocommerce_api_key($user_id, $description, $permissions);

    $api_url = 'https://caratiq-cms.scaleupdevops.in/api/verify-woo-token';

    $response = wp_remote_post($api_url, array(
        'method'    => 'POST',
        'body'      => array(
            'token'          => $token,
            'consumer_key'    => $response['consumer_key'],
            'consumer_secret' => $response['consumer_secret'],
        )
    ));

    $body = json_decode(wp_remote_retrieve_body($response), true);


    if (isset($body["status"]) && $body["status"] == 200) {
    ?>

        <div class="wrap">
            <h1>You have connected to CaratIQ successfully.</h1>
            <p>You can close this page...</p>
        </div>

    <?php
        exit;
    } else { ?>
        <div class="wrap">
            <h1>Something went wrong, please try again.</h1>
        </div>
<?php

        exit;
    }
}

// Function to generate WooCommerce API key
function generate_woocommerce_api_key($user_id, $description, $permissions)
{

    global $wpdb;
    $consumer_key    = 'ck_' . wc_rand_hash();
    $consumer_secret = 'cs_' . wc_rand_hash();

    $data = array(
        'user_id'         => $user_id,
        'description'     => $description,
        'permissions'     => $permissions,
        'consumer_key'    => wc_api_hash($consumer_key),
        'consumer_secret' => $consumer_secret,
        'truncated_key'   => substr($consumer_key, -7),
    );

    $wpdb->insert(
        $wpdb->prefix . 'woocommerce_api_keys',
        $data,
        array(
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        )
    );

    $key_id = $wpdb->insert_id;
    $response['consumer_key']    = $consumer_key;
    $response['consumer_secret'] = $consumer_secret;
    $response['key_id']          = $key_id;
    return $response;
}


// Add a menu item to the WordPress admin
function add_admin_menu_item()
{
    add_menu_page('CaratIQ', 'CaratIQ', 'manage_options', 'woocommerce-caratIq-api-key-manager', 'display_api_key_form', '', 19);
}


?>

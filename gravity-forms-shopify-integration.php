<?php

/**
 * Plugin Name: Gravity Forms Shopify Integration
 * Description: Sends Gravity Forms submissions to Shopify customer database
 * Version: 1.0.0
 * Author: Your Name
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

class GF_Shopify_Integration
{

  private $shopify_domain;
  private $admin_api_token;
  private $form_id;
  private $waitlist_count;

  public function __construct()
  {
    add_action('init', array($this, 'init'));
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_init', array($this, 'settings_init'));

    // Hook into Gravity Forms submission
    add_action('gform_after_submission', array($this, 'handle_form_submission'), 10, 2);
  }

  public function init()
  {
    // Load settings
    $this->shopify_domain = get_option('gf_shopify_domain');
    $this->admin_api_token = get_option('gf_shopify_token');
    $this->form_id = get_option('gf_shopify_form_id', 1);
    $this->waitlist_count = get_option('gf_shopify_waitlist_count', 1);
  }

  public function add_admin_menu()
  {
    add_options_page(
      'Gravity Forms Shopify Settings',
      'GF Shopify',
      'manage_options',
      'gf-shopify-settings',
      array($this, 'settings_page')
    );
  }

  public function settings_init()
  {
    register_setting('gf_shopify_settings', 'gf_shopify_domain');
    register_setting('gf_shopify_settings', 'gf_shopify_token');
    register_setting('gf_shopify_settings', 'gf_shopify_form_id');
    register_setting('gf_shopify_settings', 'gf_shopify_waitlist_count');
    register_setting('gf_shopify_settings', 'gf_shopify_customer_tags');
  }

  public function settings_page()
  {
?>
    <div class="wrap">
      <h1>Gravity Forms Shopify Integration Settings</h1>
      <form method="post" action="options.php">
        <?php
        settings_fields('gf_shopify_settings');
        do_settings_sections('gf_shopify_settings');
        ?>
        <table class="form-table">
          <tr>
            <th scope="row">Shopify Domain</th>
            <td>
              <input type="text" name="gf_shopify_domain" value="<?php echo esc_attr($this->shopify_domain); ?>" class="regular-text" placeholder="your-store.myshopify.com" />
              <p class="description">Your Shopify store domain (without https://)</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Admin API Access Token</th>
            <td>
              <input type="password" name="gf_shopify_token" value="<?php echo esc_attr($this->admin_api_token); ?>" class="regular-text" />
              <p class="description">Create a private app in Shopify Admin with customer read/write permissions</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Gravity Form ID</th>
            <td>
              <input type="number" name="gf_shopify_form_id" value="<?php echo esc_attr($this->form_id); ?>" class="small-text" />
              <p class="description">The ID of the Gravity Form to integrate</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Waitlist Count</th>
            <td>
              <input type="number" name="gf_shopify_waitlist_count" value="<?php echo esc_attr($this->waitlist_count); ?>" class="small-text" />
              <p class="description">Number of items customer can purchase (sets waitlist_count metafield)</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Customer Tags</th>
            <td>
              <input type="text" name="gf_shopify_customer_tags" value="<?php echo esc_attr(get_option('gf_shopify_customer_tags', 'waitlist')); ?>" class="regular-text" />
              <p class="description">Comma-separated tags to add to customers (e.g., waitlist,newsletter)</p>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>

      <hr>
      <h2>Setup Instructions</h2>
      <ol>
        <li><strong>Create Shopify Private App:</strong>
          <ul>
            <li>Go to your Shopify Admin → Apps → App and sales channel settings</li>
            <li>Click "Develop apps" → "Create an app"</li>
            <li>Configure Admin API scopes: <code>read_customers, write_customers</code></li>
            <li>Install the app and copy the Admin API access token</li>
          </ul>
        </li>
        <li><strong>Form Requirements:</strong>
          <ul>
            <li>Your Gravity Form must have an email field</li>
            <li>Optional: Add first name and last name fields</li>
          </ul>
        </li>
        <li><strong>Test the integration:</strong>
          <ul>
            <li>Submit your form and check Shopify Admin → Customers</li>
            <li>Verify the waitlist_count metafield is set correctly</li>
          </ul>
        </li>
      </ol>
    </div>
<?php
  }

  public function handle_form_submission($entry, $form)
  {
    // Only process the specified form
    if ($form['id'] != $this->form_id) {
      return;
    }

    // Get email from form submission
    $email = $this->get_field_value($entry, $form, 'email');
    if (empty($email)) {
      error_log('GF Shopify: No email found in form submission');
      return;
    }

    // Get optional name fields
    $first_name = $this->get_field_value($entry, $form, 'name.3') ?: $this->get_field_value($entry, $form, 'text');
    $last_name = $this->get_field_value($entry, $form, 'name.6') ?: '';

    // Send to Shopify
    $result = $this->create_shopify_customer($email, $first_name, $last_name);

    if ($result) {
      error_log("GF Shopify: Successfully created/updated customer: {$email}");
    } else {
      error_log("GF Shopify: Failed to create/update customer: {$email}");
    }
  }

  private function get_field_value($entry, $form, $field_type)
  {
    foreach ($form['fields'] as $field) {
      if (
        $field->type === $field_type ||
        ($field_type === 'email' && $field->type === 'email') ||
        ($field_type === 'name.3' && $field->type === 'name' && isset($entry[$field->id . '.3'])) ||
        ($field_type === 'name.6' && $field->type === 'name' && isset($entry[$field->id . '.6'])) ||
        ($field_type === 'text' && $field->type === 'text')
      ) {

        if ($field_type === 'name.3') {
          return $entry[$field->id . '.3'] ?? '';
        } elseif ($field_type === 'name.6') {
          return $entry[$field->id . '.6'] ?? '';
        } else {
          return $entry[$field->id] ?? '';
        }
      }
    }
    return '';
  }

  private function create_shopify_customer($email, $first_name = '', $last_name = '')
  {
    if (empty($this->shopify_domain) || empty($this->admin_api_token)) {
      error_log('GF Shopify: Missing Shopify configuration');
      return false;
    }

    $url = "https://{$this->shopify_domain}/admin/api/2023-10/customers.json";

    // Prepare customer tags
    $tags = get_option('gf_shopify_customer_tags', 'waitlist');
    $tags_array = array_map('trim', explode(',', $tags));

    $customer_data = array(
      'customer' => array(
        'email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'tags' => implode(', ', $tags_array),
        'metafields' => array(
          array(
            'namespace' => 'custom',
            'key' => 'waitlist_count',
            'value' => $this->waitlist_count,
            'type' => 'number_integer'
          )
        )
      )
    );

    $headers = array(
      'Content-Type: application/json',
      'X-Shopify-Access-Token: ' . $this->admin_api_token
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customer_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 201) {
      return true;
    } elseif ($http_code === 422) {
      // Customer might already exist, try to update
      return $this->update_existing_customer($email);
    } else {
      error_log("GF Shopify API Error: HTTP {$http_code} - {$response}");
      return false;
    }
  }

  private function update_existing_customer($email)
  {
    // First, find the customer by email
    $search_url = "https://{$this->shopify_domain}/admin/api/2023-10/customers/search.json?query=email:{$email}";

    $headers = array(
      'X-Shopify-Access-Token: ' . $this->admin_api_token
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $search_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
      error_log("GF Shopify: Failed to search for customer: {$email}");
      return false;
    }

    $data = json_decode($response, true);
    if (empty($data['customers'])) {
      error_log("GF Shopify: Customer not found: {$email}");
      return false;
    }

    $customer_id = $data['customers'][0]['id'];

    // Update the customer's metafield
    $metafield_url = "https://{$this->shopify_domain}/admin/api/2023-10/customers/{$customer_id}/metafields.json";

    $metafield_data = array(
      'metafield' => array(
        'namespace' => 'custom',
        'key' => 'waitlist_count',
        'value' => $this->waitlist_count,
        'type' => 'number_integer'
      )
    );

    $headers = array(
      'Content-Type: application/json',
      'X-Shopify-Access-Token: ' . $this->admin_api_token
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $metafield_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metafield_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code === 201;
  }
}

// Initialize the plugin
new GF_Shopify_Integration();

// Activation hook to check dependencies
register_activation_hook(__FILE__, function () {
  if (!class_exists('GFForms')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die('This plugin requires Gravity Forms to be installed and activated.');
  }
});

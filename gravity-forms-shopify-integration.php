<?php

/**
 * Plugin Name: Gravity Forms Shopify Integration
 * Description: Sends Gravity Forms submissions to Shopify customer database
 * Version: 1.0.0
 * Author: Gabriel Kanev
 * Author URI: https://gkanev.com
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

  public function __construct()
  {
    add_action('init', array($this, 'init'));
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_init', array($this, 'settings_init'));

    // Hook into Gravity Forms submission
    add_action('gform_after_submission', array($this, 'handle_form_submission'), 10, 2);

    // Add Settings link to plugins page
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
  }

  public function init()
  {
    // Load settings
    $this->shopify_domain = get_option('gf_shopify_domain');
    $this->admin_api_token = get_option('gf_shopify_token');
    $this->form_id = get_option('gf_shopify_form_id', 1);
  }

  public function add_settings_link($links)
  {
    $settings_link = '<a href="' . admin_url('options-general.php?page=gf-shopify-settings') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
  }

  /**
   * Log messages for debugging
   */
  private function log($message, $level = 'info')
  {
    // Check if logging is enabled
    if (!get_option('gf_shopify_enable_logging', true)) {
      return;
    }

    $timestamp = current_time('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] GF Shopify ({$level}): {$message}";

    // WordPress debug log
    error_log($log_entry);

    // Store in custom option for admin display
    $logs = get_option('gf_shopify_debug_logs', array());
    $logs[] = array(
      'timestamp' => $timestamp,
      'level' => $level,
      'message' => $message,
      'date' => current_time('Y-m-d')
    );

    // Keep only last 100 log entries
    if (count($logs) > 100) {
      $logs = array_slice($logs, -100);
    }

    update_option('gf_shopify_debug_logs', $logs);

    // Clean old logs based on retention period
    $this->clean_old_logs();
  }

  /**
   * Clean logs older than retention period
   */
  private function clean_old_logs()
  {
    $retention_days = get_option('gf_shopify_log_retention_days', 7);
    $logs = get_option('gf_shopify_debug_logs', array());
    $cutoff_date = date('Y-m-d', strtotime("-{$retention_days} days"));

    $logs = array_filter($logs, function ($log) use ($cutoff_date) {
      return $log['date'] >= $cutoff_date;
    });

    update_option('gf_shopify_debug_logs', array_values($logs));
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
    register_setting('gf_shopify_settings', 'gf_shopify_customer_tags');
    register_setting('gf_shopify_settings', 'gf_shopify_enable_logging');
    register_setting('gf_shopify_settings', 'gf_shopify_log_retention_days');
  }

  public function settings_page()
  {
    // Handle clear logs action
    if (isset($_GET['clear_logs']) && $_GET['clear_logs'] == '1') {
      delete_option('gf_shopify_debug_logs');
      echo '<div class="notice notice-success"><p>Debug logs cleared successfully.</p></div>';
    }

?>
    <div class="wrap">
      <h1>Gravity Forms Shopify Integration Settings</h1>

      <style>
        .gf-shopify-container {
          display: flex;
          gap: 20px;
        }

        .gf-shopify-main {
          flex: 2;
        }

        .gf-shopify-sidebar {
          flex: 1;
          min-width: 400px;
        }

        .gf-shopify-card {
          background: #fff;
          border: 1px solid #ccd0d4;
          box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
          padding: 20px;
          margin-bottom: 20px;
        }

        .gf-shopify-logs {
          max-height: 300px;
          overflow-y: auto;
          background: #f9f9f9;
          padding: 10px;
          font-family: monospace;
          font-size: 12px;
          border: 1px solid #ddd;
        }

        @media (max-width: 1200px) {
          .gf-shopify-container {
            flex-direction: column;
          }
        }
      </style>

      <div class="gf-shopify-container">
        <div class="gf-shopify-main">
          <div class="gf-shopify-card">
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
                  <th scope="row">Customer Tags</th>
                  <td>
                    <input type="text" name="gf_shopify_customer_tags" value="<?php echo esc_attr(get_option('gf_shopify_customer_tags', 'newsletter')); ?>" class="regular-text" />
                    <p class="description">Comma-separated tags to add to customers (e.g., newsletter,subscriber)</p>
                  </td>
                </tr>
                <tr>
                  <th scope="row">Enable Debug Logging</th>
                  <td>
                    <label>
                      <input type="checkbox" name="gf_shopify_enable_logging" value="1" <?php checked(get_option('gf_shopify_enable_logging', true)); ?> />
                      Enable detailed logging for troubleshooting
                    </label>
                  </td>
                </tr>
                <tr>
                  <th scope="row">Log Retention (Days)</th>
                  <td>
                    <input type="number" name="gf_shopify_log_retention_days" value="<?php echo esc_attr(get_option('gf_shopify_log_retention_days', 7)); ?>" class="small-text" min="1" max="30" />
                    <p class="description">Automatically delete logs older than this many days (1-30)</p>
                  </td>
                </tr>
              </table>
              <?php submit_button(); ?>
            </form>
          </div>

          <div class="gf-shopify-card">
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
                  <li>Verify the customer is created with the specified tags</li>
                </ul>
              </li>
            </ol>
          </div>
        </div>

        <div class="gf-shopify-sidebar">
          <div class="gf-shopify-card">
            <h3>Configuration Status</h3>
            <ul>
              <li><strong>Shopify Domain:</strong> <?php echo !empty($this->shopify_domain) ? '✅ Set (' . esc_html($this->shopify_domain) . ')' : '❌ Missing'; ?></li>
              <li><strong>API Token:</strong> <?php echo !empty($this->admin_api_token) ? '✅ Set' : '❌ Missing'; ?></li>
              <li><strong>Form ID:</strong> <?php echo esc_html($this->form_id); ?></li>
              <li><strong>Gravity Forms:</strong> <?php echo class_exists('GFForms') ? '✅ Active' : '❌ Not Found'; ?></li>
              <li><strong>Debug Logging:</strong> <?php echo get_option('gf_shopify_enable_logging', true) ? '✅ Enabled' : '❌ Disabled'; ?></li>
            </ul>
          </div>

          <div class="gf-shopify-card">
            <h3>Recent Activity Logs</h3>
            <div class="gf-shopify-logs">
              <?php
              if (!get_option('gf_shopify_enable_logging', true)) {
                echo '<p style="color: #d63638;">⚠️ Logging is currently disabled. Enable it above to see debug information.</p>';
              } else {
                $logs = get_option('gf_shopify_debug_logs', array());
                if (empty($logs)) {
                  echo '<p>No activity logs yet. Submit a form to see debug information.</p>';
                } else {
                  $logs = array_reverse(array_slice($logs, -20)); // Show last 20, newest first
                  foreach ($logs as $log) {
                    $level_color = array(
                      'error' => '#d63638',
                      'success' => '#00a32a',
                      'info' => '#0073aa'
                    );
                    $color = $level_color[$log['level']] ?? '#333';
                    echo '<div style="margin-bottom: 5px; color: ' . $color . ';">';
                    echo '<strong>' . esc_html($log['timestamp']) . '</strong> [' . strtoupper(esc_html($log['level'])) . '] ';
                    echo esc_html($log['message']);
                    echo '</div>';
                  }
                }
              }
              ?>
            </div>
            <p style="margin-top: 10px;">
              <a href="<?php echo admin_url('options-general.php?page=gf-shopify-settings&clear_logs=1'); ?>"
                class="button"
                onclick="return confirm('Are you sure you want to clear all logs?');">Clear Logs</a>
            </p>
          </div>
        </div>
      </div>
    </div>
<?php
  }

  public function handle_form_submission($entry, $form)
  {
    $this->log("Form submission received - Form ID: {$form['id']} (Target: {$this->form_id})");

    // Only process the specified form
    if ($form['id'] != $this->form_id) {
      $this->log("Skipping form - ID mismatch");
      return;
    }

    $this->log("Processing form submission - Entry ID: {$entry['id']}");

    // Get email from form submission
    $email = $this->get_field_value($entry, $form, 'email');
    if (empty($email)) {
      $this->log('No email found in form submission', 'error');
      $this->log('Available entry data: ' . print_r(array_keys($entry), true));
      return;
    }

    $this->log("Email found: {$email}");

    // Get optional name fields
    $first_name = $this->get_field_value($entry, $form, 'name.3') ?: $this->get_field_value($entry, $form, 'text');
    $last_name = $this->get_field_value($entry, $form, 'name.6') ?: '';

    $this->log("Name fields - First: '{$first_name}', Last: '{$last_name}'");

    // Send to Shopify
    $result = $this->create_shopify_customer($email, $first_name, $last_name);

    if ($result) {
      $this->log("Successfully created/updated customer: {$email}", 'success');
    } else {
      $this->log("Failed to create/update customer: {$email}", 'error');
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

  /**
   * Clean and validate Shopify domain format
   */
  private function clean_shopify_domain($domain)
  {
    if (empty($domain)) {
      return false;
    }

    // Remove protocol if present
    $domain = preg_replace('#^https?://#', '', $domain);

    // Remove trailing slash
    $domain = rtrim($domain, '/');

    // Validate format: should end with .myshopify.com
    if (!preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $domain)) {
      return false;
    }

    return $domain;
  }

  private function create_shopify_customer($email, $first_name = '', $last_name = '')
  {
    if (empty($this->shopify_domain) || empty($this->admin_api_token)) {
      $this->log('Missing Shopify configuration - Domain: ' . (!empty($this->shopify_domain) ? 'OK' : 'MISSING') .
        ', Token: ' . (!empty($this->admin_api_token) ? 'OK' : 'MISSING'), 'error');
      return false;
    }

    $this->log("Creating Shopify customer for: {$email}");

    // Clean and validate domain
    $clean_domain = $this->clean_shopify_domain($this->shopify_domain);
    if (!$clean_domain) {
      $this->log("Invalid Shopify domain format: {$this->shopify_domain}", 'error');
      return false;
    }

    $url = "https://{$clean_domain}/admin/api/2023-10/customers.json";

    // Prepare customer tags
    $tags = get_option('gf_shopify_customer_tags', 'newsletter');
    $tags_array = array_map('trim', explode(',', $tags));

    $this->log("Tags to apply: " . implode(', ', $tags_array));

    $customer_data = array(
      'customer' => array(
        'email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'tags' => implode(', ', $tags_array),
        'accepts_marketing' => true,
        'accepts_marketing_updated_at' => date('c'),
        'email_marketing_consent' => array(
          'state' => 'subscribed',
          'opt_in_level' => 'confirmed_opt_in',
          'consent_updated_at' => date('c')
        )
      )
    );

    $this->log("Customer data prepared: " . json_encode($customer_data));

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
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3); // Max 3 redirects

    $this->log("Sending API request to: {$url}");

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $this->log("API Response - HTTP Code: {$http_code}");

    if ($effective_url !== $url) {
      $this->log("URL Redirect detected - Original: {$url}, Final: {$effective_url}", 'error');
    }

    if (!empty($curl_error)) {
      $this->log("CURL Error: {$curl_error}", 'error');
      return false;
    }

    if ($response) {
      $this->log("API Response Body: " . substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''));
    }

    if ($http_code === 201) {
      $this->log("Customer created successfully", 'success');
      if ($response) {
        $response_data = json_decode($response, true);
        if (isset($response_data['customer']['accepts_marketing'])) {
          $marketing_status = $response_data['customer']['accepts_marketing'] ? 'subscribed' : 'not subscribed';
          $this->log("Customer marketing status: {$marketing_status}");
        }
      }
      return true;
    } elseif ($http_code === 422) {
      $this->log("Customer might already exist (422), trying to update");
      return $this->update_existing_customer($email);
    } else {
      $this->log("API Error - HTTP {$http_code}: {$response}", 'error');
      return false;
    }
  }

  private function update_existing_customer($email)
  {
    $this->log("Searching for existing customer: {$email}");

    // Clean and validate domain
    $clean_domain = $this->clean_shopify_domain($this->shopify_domain);
    if (!$clean_domain) {
      $this->log("Invalid Shopify domain format during customer search: {$this->shopify_domain}", 'error');
      return false;
    }

    // First, find the customer by email
    $search_url = "https://{$clean_domain}/admin/api/2023-10/customers/search.json?query=email:{$email}";

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
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (!empty($curl_error)) {
      $this->log("CURL Error during customer search: {$curl_error}", 'error');
      return false;
    }

    $this->log("Customer search response - HTTP Code: {$http_code}");

    if ($http_code !== 200) {
      $this->log("Failed to search for customer - HTTP {$http_code}: {$response}", 'error');
      return false;
    }

    $data = json_decode($response, true);
    if (empty($data['customers'])) {
      $this->log("Customer not found in search results", 'error');
      return false;
    }

    $customer_id = $data['customers'][0]['id'];
    $this->log("Found existing customer with ID: {$customer_id}");

    // Update existing customer's marketing preferences
    $update_url = "https://{$clean_domain}/admin/api/2023-10/customers/{$customer_id}.json";

    $update_data = array(
      'customer' => array(
        'id' => $customer_id,
        'accepts_marketing' => true,
        'marketing_opt_in_level' => 'confirmed_opt_in'
      )
    );

    $headers = array(
      'Content-Type: application/json',
      'X-Shopify-Access-Token: ' . $this->admin_api_token
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $update_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($update_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
      $this->log("Updated existing customer marketing preferences", 'success');
      return true;
    } else {
      $this->log("Failed to update customer marketing preferences - HTTP {$http_code}: {$response}", 'error');
      return false;
    }
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

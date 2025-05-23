# Gravity Forms Shopify Integration

A WordPress plugin that automatically sends Gravity Forms submissions to your Shopify customer database with marketing subscription opt-in.

## Features

- **Seamless Integration**: Connects Gravity Forms with Shopify Customer API
- **Marketing Opt-in**: Automatically subscribes customers to marketing emails with proper consent tracking
- **Custom Tags**: Add configurable tags to customers (default: newsletter)
- **Name Field Support**: Captures first and last names when available
- **Smart Updates**: Creates new customers or updates existing ones with marketing preferences
- **Debug Logging**: Comprehensive logging with retention controls
- **Modern Admin Interface**: Clean two-column design with real-time status monitoring

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Gravity Forms plugin
- Shopify store with Admin API access

## Installation

1. Upload the plugin files to `/wp-content/plugins/gravity-forms-shopify-integration/`
2. Activate the plugin through WordPress Admin → Plugins
3. Configure the plugin at Settings → GF Shopify

## Shopify Setup

### Create Private App

1. Go to Shopify Admin → Apps → App and sales channel settings
2. Click "Develop apps" → "Create an app"
3. Set app name (e.g., "Gravity Forms Integration")
4. Configure Admin API scopes:
   - `read_customers`
   - `write_customers`
   - `write_customers_marketing_consent`
5. Install the app and copy the Admin API access token

## Plugin Configuration

Navigate to **Settings → GF Shopify** and configure:

- **Shopify Domain**: `your-store.myshopify.com` (without https://)
- **Admin API Access Token**: From your private app
- **Gravity Form ID**: ID of the form to integrate
- **Customer Tags**: Comma-separated tags (e.g., newsletter,subscriber)
- **Enable Debug Logging**: Toggle logging on/off for troubleshooting
- **Log Retention**: Automatically delete logs after specified days (1-30)

## Form Requirements

Your Gravity Form must include:

- **Email field** (required)
- **Name field** (optional - first/last name support)

## How It Works

1. User submits your Gravity Form
2. Plugin extracts email and name fields
3. Creates/updates customer in Shopify with:
   - Email address
   - First/last name (if provided)
   - Marketing subscription with confirmed opt-in consent
   - Custom tags
   - Proper consent timestamps

## Debugging

The plugin includes comprehensive logging with retention controls:

- **Two-column admin interface** with real-time status monitoring
- **Configurable logging** - enable/disable as needed  
- **Automatic log cleanup** based on retention period
- **Form submission tracking** with detailed field mapping
- **API request/response monitoring** with error details

Check **Settings → GF Shopify** for configuration status and recent activity logs.

## Common Issues

**HTTP 301 Error**: Check domain format - should be `store-name.myshopify.com`

**No email found**: Ensure your form has an email field type

**API permissions**: Verify your Shopify app has customer read/write permissions

## Support

For issues and questions:

- Check the debug logs first
- Ensure all requirements are met
- Verify Shopify API credentials

## License

Licensed under the Apache License 2.0. See LICENSE file for details.

## Author

**Gabriel Kanev**  
Website: [gkanev.com](https://gkanev.com)

---

*Transform your Gravity Forms into a powerful customer acquisition tool for your Shopify store.*

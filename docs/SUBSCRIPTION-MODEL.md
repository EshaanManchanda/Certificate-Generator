# Subscription & License Model - Certificate Generator

## Overview

The Certificate Generator plugin uses a built-in **License Manager** (`CG_License_Manager` class) to handle subscription plans, license key validation, and feature gating. It's located in `includes/Core/license-manager.php`.

---

## Subscription Plans

| Plan      | Monthly Certificates | Key Prefix | Features |
|-----------|---------------------|------------|----------|
| **Free**  | 100                 | (none)     | Basic PDF, Manual Issue, Search Shortcode |
| **Pro**   | 1,000               | `PRO-`     | + CSV Import, Bulk ZIP, Email Templates, API Access, SMTP Tools |
| **Business** | Unlimited (0)    | `BIZ-`     | + 19k+ Fonts, Unlimited API, Multisite, Remove Branding, Commercial License |

### Plan Detection

```php
// Get current plan
$plan = CG_License_Manager::get_plan(); // returns 'free', 'pro', or 'business'

// Quick checks
CG_License_Manager::is_free();      // bool
CG_License_Manager::is_pro();       // bool (true for Pro or Business)
CG_License_Manager::is_business();  // bool

// Get plan label
$label = CG_License_Manager::get_plan_label(); // "Free", "Pro", or "Business"
```

---

## License Key Management

### Stored Options

| Option Key | Description |
|------------|-------------|
| `cg_plan` | Current plan (`free`, `pro`, `business`) |
| `cg_license_key` | The activated license key |
| `cg_license_expiry` | Expiry date (`YYYY-MM-DD`) |
| `cg_monthly_usage` | Certificates generated this month |
| `cg_usage_month` | Current month (`YYYY-MM`) |

### Activate License

```php
$result = CG_License_Manager::activate_license( 'PRO-XXXX-XXXX-XXXX' );

// Returns:
// [
//   'success' => true,
//   'message' => 'License activated! Your plan is now Pro.',
//   'plan'    => 'pro',
//   'expiry'  => '2026-04-20'
// ]
```

**Key Format:**
- `PRO-*` → Pro plan (1 year expiry)
- `BIZ-*` → Business plan (1 year expiry)
- Keys ending in `-LIFETIME` or `-LOCAL` → Expiry set to `2099-12-31`

### Deactivate License

```php
CG_License_Manager::deactivate_license();

// Resets to Free plan and clears license key
```

### Check License Key

```php
$key = CG_License_Manager::get_license_key();   // "PRO-XXXX-XXXX-XXXX" or ""
$expiry = CG_License_Manager::get_expiry();     // "2026-04-20" or ""

// Check if expired
$is_expired = !empty($expiry) && strtotime($expiry) < time();
```

---

## User Authentication Check

### WordPress Users

```php
// Check if user is logged in
if ( is_user_logged_in() ) {
    // User is authenticated
}

// Get current user
$user = wp_get_current_user();
$user_id = get_current_user_id();

// Check capability (role-based access)
if ( current_user_can( 'edit_posts' ) ) {
    // Can access certificate features
}

if ( current_user_can( 'manage_options' ) ) {
    // Is admin - full access
}
```

### Common Capabilities

| Capability | Description |
|------------|-------------|
| `manage_options` | Administrator - full plugin access |
| `edit_posts` | Editor/Author - can manage certificates |
| `read` | Subscriber - minimal access |

---

## Integration: External Authentication

If you're checking authentication from an **external system** (MERN backend, mobile app, etc.):

### Option 1: WordPress REST API with Nonce

```javascript
// Frontend (JavaScript)
fetch('/wp-json/cg/v1/check-auth', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce  // WordPress nonce
    }
})
.then(res => res.json())
.then(data => {
    if (data.authenticated) {
        // User is logged in
        console.log('User:', data.user);
    }
});
```

**Register the endpoint in your plugin:**

```php
add_action('rest_api_init', function() {
    register_rest_route('cg/v1', '/check-auth', [
        'methods'  => 'POST',
        'callback' => 'cg_check_authentication',
        'permission_callback' => '__return_true' // Or custom permission
    ]);
});

function cg_check_authentication(WP_REST_Request $request) {
    $user = wp_get_current_user();
    
    if ( $user->exists() ) {
        return [
            'authenticated' => true,
            'user' => [
                'id'    => $user->ID,
                'login' => $user->user_login,
                'email' => $user->user_email,
                'roles' => $user->roles
            ]
        ];
    }
    
    return ['authenticated' => false];
}
```

### Option 2: External Token Validation (for MERN backend)

Create an endpoint that validates an external token:

```php
add_action('rest_api_init', function() {
    register_rest_route('cg/v1', '/validate-token', [
        'methods'  => 'POST',
        'callback' => 'cg_validate_external_token',
        'permission_callback' => '__return_true'
    ]);
});

function cg_validate_external_token(WP_REST_Request $request) {
    $token = $request->get_param('token');
    
    // Validate your external JWT/session token
    // This depends on your MERN backend's token structure
    
    $is_valid = /* your validation logic */;
    
    if ( $is_valid ) {
        // Check license
        $plan = CG_License_Manager::get_plan();
        $is_pro = CG_License_Manager::is_pro();
        
        return [
            'valid'    => true,
            'plan'     => $plan,
            'features' => [
                'api_access'   => $is_pro,
                'bulk_zip'     => CG_License_Manager::is_pro(),
                'unlimited'    => CG_License_Manager::is_business()
            ]
        ];
    }
    
    return ['valid' => false, 'message' => 'Invalid token'];
}
```

---

## Feature Gating

### Check if Feature is Available

```php
$features = CG_License_Manager::get_plan_features();

// Check specific feature
$has_api = $features['api_access']['pro'] && CG_License_Manager::is_pro();
$has_bulk_zip = $features['bulk_zip']['pro'] && CG_License_Manager::is_pro();

// Simple gate
if ( ! CG_License_Manager::is_pro() ) {
    // Show upgrade message
    wp_redirect( admin_url('options-general.php?page=certificate_generator_settings&tab=license') );
    exit;
}
```

### Usage Limits

```php
$usage = CG_License_Manager::get_usage();       // Current month count
$limit = CG_License_Manager::get_limit();       // Plan limit (0 = unlimited)
$reached = CG_License_Manager::is_limit_reached();

if ( $reached ) {
    // Show upgrade prompt
}
```

---

## Complete Integration Example

```php
<?php
/**
 * External authentication & license check
 * Call from your MERN backend or external system
 */

function check_user_license_access() {
    // 1. Check if user is authenticated (WordPress)
    if ( ! is_user_logged_in() ) {
        return [
            'authenticated' => false,
            'message'      => 'User not logged in'
        ];
    }
    
    // 2. Get license status
    $license_key = CG_License_Manager::get_license_key();
    $plan        = CG_License_Manager::get_plan();
    $expiry      = CG_License_Manager::get_expiry();
    $is_expired  = !empty($expiry) && strtotime($expiry) < time();
    
    // 3. Get user capabilities
    $user = wp_get_current_user();
    $can_manage = current_user_can( 'manage_options' );
    $can_edit   = current_user_can( 'edit_posts' );
    
    return [
        'authenticated' => true,
        'user' => [
            'id'       => $user->ID,
            'email'    => $user->user_email,
            'roles'    => $user->roles
        ],
        'license' => [
            'key'          => $license_key,
            'plan'         => $plan,
            'expiry'       => $expiry,
            'is_expired'   => $is_expired,
            'is_pro'       => CG_License_Manager::is_pro(),
            'is_business'  => CG_License_Manager::is_business()
        ],
        'capabilities' => [
            'manage_options' => $can_manage,
            'edit_posts'    => $can_edit
        ],
        'usage' => [
            'current' => CG_License_Manager::get_usage(),
            'limit'   => CG_License_Manager::get_limit()
        ]
    ];
}
```

---

## AJAX Example (Frontend to Backend)

```javascript
// Check auth status from frontend
jQuery.ajax({
    url: '/wp-admin/admin-ajax.php',
    method: 'POST',
    data: {
        action: 'cg_check_user_auth',
        nonce: '<?php echo wp_create_nonce("cg_auth_nonce"); ?>'
    },
    success: function(response) {
        if (response.authenticated) {
            console.log('Plan:', response.plan);
            console.log('Can use API:', response.can_use_api);
        }
    }
});
```

```php
// Server-side handler
add_action('wp_ajax_cg_check_user_auth', 'cg_ajax_check_user_auth');

function cg_ajax_check_user_auth() {
    check_ajax_referer('cg_auth_nonce', 'nonce');
    
    if ( ! is_user_logged_in() ) {
        wp_send_json_error(['message' => 'Not authenticated']);
    }
    
    wp_send_json_success([
        'authenticated' => true,
        'plan'          => CG_License_Manager::get_plan(),
        'can_use_api'   => CG_License_Manager::is_pro()
    ]);
}
```

---

## Summary

| Task | Method |
|------|--------|
| Check if logged in | `is_user_logged_in()` |
| Get current user | `wp_get_current_user()` |
| Check capability | `current_user_can('edit_posts')` |
| Get plan | `CG_License_Manager::get_plan()` |
| Get license key | `CG_License_Manager::get_license_key()` |
| Check if Pro/Business | `CG_License_Manager::is_pro()` |
| Activate license | `CG_License_Manager::activate_license($key)` |
| Deactivate license | `CG_License_Manager::deactivate_license()` |

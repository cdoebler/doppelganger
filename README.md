# Doppelganger

A WordPress integration for the [php-generic-user-switcher](https://github.com/cdoebler/php-generic-user-switcher) package.
This plugin allows authorized administrators to instantly switch their session to any other user on the site, which is useful for debugging issues reported by specific users or testing role capabilities.

## Features

- **Seamless Integration**: Works out-of-the-box with WordPress authentication system.
- **Configurable**: Customize permissions, UI position, and behavior through WordPress filters.
- **Environment Control**: Restrict user switching to specific environments (e.g., `local`, `development`) to prevent accidents in production.
- **Cookie-based Impersonation**: Securely impersonates users by maintaining the original user's ID in a signed cookie.
- **Persistent Authorization**: Switcher remains visible when impersonating less-privileged users, allowing easy switching back.

## Requirements

- PHP 8.2 or higher
- WordPress 6.8 or higher
- Composer

## Installation

This is a Composer-based WordPress plugin. You can install it using Composer:

```bash
composer require cdoebler/doppelganger
```

Ensure your site is configured to load the `vendor/autoload.php` file, or activate the plugin if it's placed in `wp-content/plugins` and installed with dependencies.

## Usage

1. **Log in as an Administrator.**
   By default, only users with the `edit_users` capability (typically Administrators) can see and use the switcher.

2. **Switch User**
   - Look for the "Switch User" toggle in the bottom-right corner of the screen.
   - Click it to open the user list.
   - Click on any user to switch to their account.

3. **Impersonation Mode**
   - While impersonating, a "Stop Impersonating" banner/button will be visible.
   - You are effectively logged in as that user, with their permissions.

4. **Stop Impersonating**
   - Click "Stop Impersonating" to return to your original administrative account.

## Configuration

The plugin is designed to work out-of-the-box, with three layers of configuration (in order of precedence):

1. **Default Behavior** - Works immediately after installation
2. **wp-config.php Constants** - Simple toggles for non-technical users
3. **WordPress Filters** - Advanced customization for developers

### Default Behavior

Out of the box, the plugin:
- **Enabled**: Yes (active in all environments)
- **Environments**: All environments (`*` wildcard)
- **Authorization**: Users with `edit_users` capability (typically Administrators)

### Simple Configuration (wp-config.php Constants)

For basic on/off toggles and environment control, add constants to your `wp-config.php` file:

```php
// Disable the plugin completely
define('DOPPELGANGER_ENABLED', false);

// Restrict to development environments (comma-separated)
define('DOPPELGANGER_ALLOWED_ENVIRONMENTS', 'local,development');

// Or allow specific environments including staging
define('DOPPELGANGER_ALLOWED_ENVIRONMENTS', 'local,development,staging');

// Allow all environments (default)
define('DOPPELGANGER_ALLOWED_ENVIRONMENTS', '*');
```

**Important:** Constants take precedence over filters. If a constant is defined, filters for that setting will be ignored with a warning in debug mode and the WordPress admin dashboard.

#### ⚠️ Development Mode (EXTREMELY DANGEROUS)

```php
// Enable dev mode - BYPASSES ALL SECURITY CHECKS
define('DOPPELGANGER_DEV_MODE', true);
```

**🚨 CRITICAL SECURITY WARNING:**

When `DOPPELGANGER_DEV_MODE` is enabled:
- ✅ **ALL users can switch to ANY account** (including administrators)
- ✅ **Even guests (not logged in) can use the switcher**
- ✅ Bypasses environment restrictions
- ✅ Bypasses authorization checks
- ✅ Bypasses ALL security measures

**This constant is ONLY for:**
- Local development and debugging
- Testing user permission flows
- Reproducing user-reported issues

**NEVER EVER:**
- ❌ Use this in production
- ❌ Use this in staging (unless absolutely necessary and temporarily)
- ❌ Commit this to version control
- ❌ Leave it enabled after debugging

A persistent **red warning banner** will appear in the WordPress admin dashboard when dev mode is active. If you see this warning in production, **disable dev mode immediately** by removing the constant from `wp-config.php`.

**Note:** The main `DOPPELGANGER_ENABLED=false` constant still works as an emergency kill switch even when dev mode is active.

### Advanced Configuration (WordPress Filters)

For complex logic and dynamic behavior, use WordPress filters in your theme's `functions.php` or a custom plugin:

#### Environment Control

```php
add_filter('doppelganger_allowed_environments', function(array $environments): array {
    // Only allow in local and development
    return ['local', 'development'];
});

// Or allow multiple environments
add_filter('doppelganger_allowed_environments', function(array $environments): array {
    return ['local', 'development', 'staging'];
});
```

**Note:** This filter is ignored if `DOPPELGANGER_ALLOWED_ENVIRONMENTS` constant is defined.

#### Permissions (Authorization)

By default, `edit_users` capability is required. You can change this logic:

```php
add_filter('doppelganger_can_switch', function(bool $canSwitch, int $currentUserId): bool {
    // Example: Allow users with manage_options capability instead
    return user_can($currentUserId, 'manage_options');

    // Or allow specific user IDs
    // return in_array($currentUserId, [1, 5, 10], true);

    // Or allow all users and even guests (helpful for debugging)
    // return true;
}, 10, 2);
```

#### How Authorization Works During Impersonation

**Important:** When you're impersonating another user, authorization checks are based on the **original user** (the one who started impersonation), not the currently impersonated user.

This means:
- If an admin switches to a regular user, the switcher widget remains visible
- The admin can continue switching to other users
- Authorization is always checked against the original admin, not the impersonated user

This prevents the common issue where the switcher disappears after switching to an unauthorized user.

**Example Scenario:**
1. Admin (authorized) logs in → Widget appears ✅
2. Admin switches to Regular User (unauthorized) → Widget **still appears** ✅
3. Admin can switch back or to other users ✅
4. Regular User logs in directly → Widget does **not** appear ✅

#### UI Configuration

Customize the renderer configuration:

```php
add_filter('doppelganger_config', function(array $config): array {
    // You can modify the default config or return a completely new one
    $config['position'] = 'bottom-left'; // 'bottom-right', 'bottom-left', 'top-right', 'top-left'
    $config['param_name'] = '_my_custom_switch_param'; // Custom URL parameter name

    return $config;
});
```

Available configuration options:
- `position`: Widget position on screen (`bottom-right`, `bottom-left`, `top-right`, `top-left`)
- `param_name`: URL parameter name for switching (default: `doppelganger_switch`)
- `current_user_id`: Current user ID (automatically set, usually don't override)

### Database Options (Alternative to Constants)

The plugin also checks a database option for the enabled state. This is useful if you need to toggle the plugin programmatically:

```php
// Disable the plugin via database
update_option('doppelganger_enabled', false);

// Re-enable it
update_option('doppelganger_enabled', true);
```

**Note:** The `DOPPELGANGER_ENABLED` constant takes precedence over this database option.

## Security

- **Return Ticket**: When switching, a secure cookie is set containing the original admin's ID. This cookie is signed using `wp_hash()` with a nonce salt to prevent tampering.
- **Validation**: Authentication cookies are re-issued securely using WordPress core functions.

## Development

To run static analysis and code quality checks:

```bash
# Run all checks (PHPStan, Rector dry-run, and tests)
composer test

# Or run individual checks:
composer phpstan      # Static analysis
composer rector-dry   # Code style check
composer rector       # Apply code style fixes
composer pest         # Run tests
```


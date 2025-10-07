# AVLP Walkthrough Tour Plugin

A custom WordPress plugin for creating interactive site tours and walkthroughs for the AVLP (Virtual Leadership Programs) platform.

## Features

### 🎯 **Core Functionality**
- **Responsive Design**: Mobile-friendly modals that work across all devices
- **Element Targeting**: Target page elements using CSS selectors
- **Modal Positioning**: Support for centered modals and element-relative positioning
- **Progress Tracking**: Optional step counter (1/3, 2/3, etc.) per tour
- **User Management**: Track completion, skipping, and permanent disabling

### 🎨 **User Experience**
- **Auto-trigger**: Tours start automatically on first page visit
- **Manual Trigger**: Trigger tours via shortcode or URL parameters
- **Skip Options**: Users can skip for current visit or permanently disable
- **Dynamic Content**: Support for WordPress shortcodes in tour content
- **Welcome Messages**: Special modal positioning for welcome/intro content

### ⚙️ **Admin Interface**
- **Tour Management**: Create, edit, and manage multiple tours
- **Step Builder**: Add, reorder, and configure tour steps
- **Settings Control**: Enable/disable progress tracking per tour
- **User Analytics**: Track user interactions and completion rates

## Installation

1. **Upload** the plugin files to `/wp-content/plugins/avlp-walkthrough-tour/`
2. **Activate** the plugin through the WordPress admin
3. **Configure** tours through the admin interface

## Database Tables

The plugin creates three custom tables:

- `pus_avlp_tours` - Tour definitions and settings
- `pus_avlp_tour_steps` - Individual tour steps
- `pus_avlp_tour_user_tracking` - User interaction tracking

## Usage

### Creating Tours

1. Go to **AVLP Admin > Walkthrough Tours**
2. Click **Add New Tour**
3. Configure tour settings:
   - **Name & Description**: Basic tour information
   - **Trigger Type**: Automatic, Manual, or URL Parameter
   - **Progress Tracker**: Enable/disable step counter
   - **Status**: Active/Inactive

### Adding Steps

1. **Edit** your tour
2. Click **Add Step**
3. Configure step settings:
   - **Title & Content**: Step information
   - **Target Selector**: CSS selector for element targeting
   - **Position**: Auto, Top, Bottom, Left, Right, or Modal
   - **Page URL Pattern**: Optional page-specific targeting

### Targeting Elements

Use CSS selectors to target elements:

```css
/* ID selector */
#vlp-chat-left

/* Class selector */
.elementor-widget-video

/* Complex selector */
.elementor-column[data-id="4d7ffbcb"] .elementor-widget-video

/* Data attribute */
[data-element_type="widget"][data-widget_type="video.default"]
```

### Shortcodes

Use WordPress shortcodes in tour content:

```php
[vlp_user_field field="coach_image"]
[vlp_user_field field="user_name"]
```

## Technical Details

### File Structure

```
avlp-walkthrough-tour/
├── default-walkthrough.php          # Main plugin file
├── includes/
│   ├── walkthrough-database.php     # Database operations
│   ├── walkthrough-admin.php        # Admin interface
│   ├── walkthrough-frontend.php     # Frontend functionality
│   └── walkthrough-shortcodes.php   # Shortcode handlers
├── css/
│   ├── walkthrough-admin.css        # Admin styling
│   └── walkthrough-frontend.css     # Frontend styling
├── js/
│   ├── walkthrough-admin.js         # Admin JavaScript
│   └── walkthrough-frontend.js      # Frontend tour engine
└── deploy_to_staging.sh             # Deployment script
```

### Key Functions

- `vlp_walkthrough_create_tour()` - Create new tours
- `vlp_walkthrough_get_active_tours_for_page()` - Get tours for current page
- `vlp_walkthrough_track_user_interaction()` - Track user actions
- `vlp_walkthrough_process_step_content()` - Process shortcodes

## Deployment

Use the included deployment script:

```bash
./deploy_to_staging.sh
```

This script deploys all plugin files to the staging server via SCP.

## Version History

### v1.0.0
- Initial release
- Complete tour functionality
- Progress tracking
- User management
- Admin interface
- Responsive design

## Support

For issues or questions, contact the VLP development team.

## License

GPL v2 or later
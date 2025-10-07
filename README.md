# AVLP Walkthrough Tour Plugin

A custom WordPress plugin for creating interactive site tours and walkthroughs for the Virtual Leadership Programs platform.

## Features

- **Responsive Design**: Mobile-friendly tours that work on all devices
- **Modal Implementation**: Popup-blocker safe modals using DOM overlays
- **Element Adaptability**: Flexible element targeting with automatic fallbacks
- **User Tracking**: Page-by-page completion tracking with user preferences
- **Admin Interface**: Visual tour builder with drag-and-drop step management
- **Multiple Triggers**: Automatic, manual, and URL parameter triggers
- **Dynamic Content**: Support for shortcodes and user-specific content
- **VLP Integration**: Follows VLP design standards and integrates with existing plugins

## Installation

1. Upload the plugin files to `/wp-content/plugins/avlp-walkthrough-tour/`
2. Activate the plugin through the WordPress admin
3. Navigate to **AVLP Admin > Walkthrough Tours** to create your first tour

## Quick Start

### Creating a Tour

1. Go to **AVLP Admin > Walkthrough Tours**
2. Click **Add New Tour**
3. Fill in the tour details:
   - **Tour Name**: Display name for the tour
   - **Description**: Optional description
   - **Trigger Type**: How the tour should be triggered
   - **Status**: Active/Inactive
4. Click **Create Tour**

### Adding Steps

1. After creating a tour, click **Manage Steps**
2. Click **Add Step**
3. Configure each step:
   - **Step Title**: Title shown in the tooltip
   - **Step Content**: Content with HTML support and shortcodes
   - **Target Selector**: CSS selector for the element to highlight
   - **Position**: Tooltip position (auto, top, bottom, left, right)
   - **Page URL Pattern**: Optional page restriction
   - **Step Order**: Order in the tour sequence

### Tour Triggers

#### Automatic Trigger
Tours start automatically when users visit matching pages for the first time.

#### Manual Trigger
Use the shortcode to add tour trigger buttons:
```
[vlp_walkthrough_tour tour_id="1" text="Start Tour"]
```

#### URL Parameter Trigger
Add `?show_tour=1` to any URL to trigger tours on that page.

## Shortcodes

### Tour Trigger
```
[vlp_walkthrough_tour tour_id="1" text="Start Tour" class="custom-class"]
```

### Element Trigger
```
[vlp_walkthrough_trigger tour_id="1" element=".my-element" text="Learn More"]
```

### Statistics
```
[vlp_walkthrough_stats tour_id="1" show="completion_rate"]
[vlp_walkthrough_stats tour_id="1" show="all"]
```

## Dynamic Content

### User Fields
Include user-specific data in tour content:
```
Hello [vlp_user_field field="first_name"]! Welcome to the platform.
```

### Coach Images
Display user's assigned coach image:
```
[vlp_coach_image size="small"]
```

### Other Shortcodes
Any existing VLP shortcodes can be used in tour content.

## Styling

The plugin follows VLP design standards:
- **Primary Color**: #0066ff (Blue)
- **CTA Color**: #ff6600 (Orange)
- **Responsive Design**: Works on all screen sizes
- **Accessibility**: Keyboard navigation and screen reader support

## User Experience

### Tour Controls
- **Next/Previous**: Navigate through steps
- **Skip Tour**: Skip for current session
- **Don't show tours again**: Permanently disable tours
- **Close**: Close tour at any time

### Keyboard Navigation
- **Arrow Keys**: Navigate between steps
- **Enter**: Next step
- **Escape**: Close tour

### Mobile Support
- Touch-friendly controls
- Responsive positioning
- Optimized for small screens

## Admin Interface

### Tour Management
- Create, edit, and delete tours
- Set trigger types and values
- Manage tour status

### Step Management
- Drag-and-drop step ordering
- Visual element selector
- Rich text content editor
- Step-specific settings

### Analytics
- Completion rates
- User interaction tracking
- Tour performance metrics

## Database Schema

### Tables
- `wp_avlp_tours`: Tour definitions
- `wp_avlp_tour_steps`: Individual tour steps
- `wp_avlp_tour_user_tracking`: User interaction tracking

### User Preferences
- Session-based skip tracking
- Permanent disable preferences
- Progress tracking per user/page

## Development

### File Structure
```
avlp-walkthrough-tour/
├── default-walkthrough.php          # Main plugin file
├── includes/
│   ├── walkthrough-admin.php        # Admin interface
│   ├── walkthrough-database.php     # Database operations
│   ├── walkthrough-frontend.php     # Frontend functionality
│   └── walkthrough-shortcodes.php   # Shortcode handlers
├── css/
│   ├── walkthrough-admin.css        # Admin styling
│   └── walkthrough-frontend.css     # Frontend styling
├── js/
│   ├── walkthrough-admin.js         # Admin JavaScript
│   └── walkthrough-frontend.js      # Frontend tour engine
├── tests/                           # Testing framework
└── monitoring/                      # Production monitoring
```

### Testing
```bash
# Run unit tests
npm run test:unit

# Run E2E tests
npm run test:e2e

# Run all tests
npm test
```

### Deployment
```bash
# Deploy to staging
npm run deploy:staging

# Or use the script directly
./deploy_to_staging.sh
```

## Configuration

### Plugin Options
- `vlp_walkthrough_enabled`: Enable/disable tours globally
- `vlp_walkthrough_auto_trigger`: Enable automatic triggers
- `vlp_walkthrough_url_trigger`: URL parameter name for manual triggers
- `vlp_walkthrough_animation_speed`: Animation speed in milliseconds
- `vlp_walkthrough_show_progress`: Show progress indicators
- `vlp_walkthrough_allow_skip`: Allow users to skip tours
- `vlp_walkthrough_allow_disable`: Allow users to disable tours permanently

## Troubleshooting

### Common Issues

1. **Tours not appearing**
   - Check if tours are enabled in settings
   - Verify tour is active and has steps
   - Check user hasn't disabled tours permanently

2. **Elements not found**
   - Verify CSS selectors are correct
   - Check if elements exist on the page
   - Use browser developer tools to test selectors

3. **Styling issues**
   - Check for CSS conflicts with theme
   - Verify plugin CSS is loading
   - Test responsive design on different devices

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For support and feature requests, please contact the Virtual Leadership Programs development team.

## Changelog

### Version 1.0.0
- Initial release
- Core tour functionality
- Admin interface
- User tracking
- Responsive design
- VLP integration

## License

This plugin is licensed under the GPL v2 or later.

---

**Virtual Leadership Programs**  
https://virtualleadershipprograms.com

# Changelog

### Version 1.3.0 (Current)

#### **New Features**
- **Dashboard Page**
  - Added new dashboard as default landing page with statistics overview
  - Statistics cards showing total emails, manual emails, and automatic emails
  - Quick actions section for common tasks (Compose, History, Settings)
  - Recent activity feed displaying last 5 emails sent
  - Tips & best practices section for user guidance
  - Fully responsive design with mobile optimization

- **Enhanced Email History**
  - Added "Recipient Names" column in Manual Emails tab
  - Display up to 5 recipient names inline with tooltip for overflow
  - Hover tooltip shows all remaining recipients
  - Improved timezone handling for accurate timestamp display
  - Fixed timezone sync with WordPress settings (UTC to local conversion)

#### **Code Organization & Refactoring**
- **Service Container Integration**
  - Enforced required dependencies across all classes
  - Removed optional parameters and null coalescing operators
  - Integrated service container with automatic dependency injection
  - Added `penalis_get_service()` helper function

- **Intelligent Autoloader**
  - Replaced 26 manual `require_once` statements with pattern-based autoloader
  - Automatic class detection for Admin, Interface, Exception, Repository, Validator
  - On-demand class loading for improved performance
  - Special handling for base exception and admin interface classes

- **Email Sending Logic**
  - Consolidated duplicate email sending code into reusable `send_email()` method
  - Eliminated redundant `compose_email()` and `apply_email_filters()` methods
  - Reduced code duplication across automatic, manual, and test email workflows

- **Exception Handling**
  - Replaced generic exceptions with typed custom exceptions
  - Added `Penalis_Container_Exception` for service container errors
  - Implemented exception handling in all email sending methods
  - Added structured error context for better debugging
  - Integrated action hooks: `penalis_email_sent_success`, `penalis_email_send_failed`

- **Repository Pattern**
  - Created `Penalis_Post_Meta_Repository_Interface` for abstraction
  - Updated Email Logger to depend on interface instead of concrete class
  - Consistent interface-based pattern across all repositories

- **Configuration Management**
  - Centralized automatic email configuration in Config class
  - Added `DEFAULT_AUTO_EMAIL_FROM` constant
  - Created `get_auto_email_subject()` and `get_auto_email_from()` methods with filter support
  - Replaced hardcoded strings throughout codebase

- **Admin Interface**
  - Consolidated history page rendering logic
  - Removed deprecated `render_main_page()` method
  - Improved separation of concerns with dedicated page classes
  - Extracted inline templates into reusable view files

- **View Layer Organization**
  - Created dedicated view files: `email-details-card.php`, `preview-modal.php`
  - Standardized all view includes to use `require` (not `require_once`)
  - Added comprehensive view files documentation (README.md)
  - Consistent security checks and escaping across all views

#### **Design System Overhaul**
- **WordPress Admin Consistency**
  - Removed all emoji icons, replaced with Dashicons
  - Removed colored border-left styling from all components
  - Aligned color palette with WordPress design system (#c3c4c7, #1d2327, #646970)
  - Standardized border radius (3-4px), shadows, and spacing
  - Updated typography: removed uppercase transforms, adjusted font weights (500-600)

- **Component Refinements**
  - Form cards: cleaner headers, better spacing, standard borders
  - Buttons: consistent sizing (8px 16px), lighter font weights
  - Modal dialogs: smaller, cleaner design with subtle shadows
  - Tips/Warning/Info boxes: neutral backgrounds, standard borders
  - User selection: white backgrounds, clean borders
  - Pagination: added borders, lighter font weights
  - Formatting guide: improved structure with code block styling

- **Responsive Design**
  - Mobile-optimized dashboard with stacked layouts
  - Adjusted icon and font sizes for smaller screens
  - Tooltip positioning adjustments for mobile viewports

#### **Bug Fixes**
- Fixed autoloader pattern to recognize Dashboard classes
- Fixed timezone display in email history (UTC to WordPress timezone)
- Fixed `human_time_diff()` comparison using GMT parameter
- Added fallback timezone conversion for WordPress < 5.3

#### **Documentation**
- Added view files README with conventions and usage examples
- Updated PHPDoc comments with proper exception types
- Improved inline code documentation
- Added context explanations for complex logic

#### **Performance**
- Lazy loading of classes through autoloader
- Reduced bootstrap overhead by deferring class loading
- Optimized dashboard queries (only fetch 5 recent emails)
- Efficient recipient name fetching with on-demand user data loading

---

### Version 1.2.0
- **Email History Enhancements**
  - Separated Email History into dedicated page with own menu item
  - Added tab-based navigation (Manual/Automatic emails)
  - Automatic emails now included in history tracking
  - Added visual distinction between manual and automatic emails
  - Optimized automatic email columns (removed redundant Subject column)
  
- **Delete History Feature**
  - Implemented bulk delete functionality for email logs
  - Added "Clear All History" option per tab (Manual/Automatic)
  - WordPress-style bulk actions interface
  - Legacy email entry support with pseudo-ID generation
  - Proper nonce verification and capability checks
  
- **UI/UX Improvements**
  - Improved checkbox alignment in history tables
  - Better table styling and responsive design
  - Removed filter/search functionality for cleaner interface
  - Enhanced bulk actions bar layout
  
- **Code Quality**
  - Removed single delete functionality (keeping only bulk operations)
  - Cleaned up debugging console logs
  - Improved CSS organization and WordPress standards compliance
  - Better separation of concerns in admin pages

### Version 1.1.0
- Complete architecture refactoring
- Added service container with dependency injection
- Implemented repository pattern
- Added comprehensive validation system
- Created 6 interfaces for loose coupling
- Added 6 custom exception classes
- Separated CSS/JS from PHP
- Added markdown parser
- 110+ unit tests with 100% pass rate
- Comprehensive documentation

### Version 1.0.0
- Initial release with core functionality
- Automatic post publication notifications
- Manual email interface
- HTML email templates

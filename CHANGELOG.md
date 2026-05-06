# Changelog

### Version 1.3.3 (Current)

#### **New Features**
- **Email History Enhancements**
  - Added dedicated "Email" column in Manual Emails tab showing recipient email addresses
  - Display recipient email alongside name in history table for better identification
  - Added clickable author profile links to recipient names for quick access to user profiles
  - Improved recipient information display with both name and email visible

- **Email Template Improvements**
  - Added preheader text support for email previews
  - Preheader text appears in email client preview panes before opening the email
  - Enhances email open rates by providing context in inbox preview

#### **Code Quality & Refactoring**
- **Template System Cleanup**
  - Removed deprecated `render_with_template()` method from Email Template class
  - Removed deprecated `render_without_template()` method from Email Template class
  - Removed deprecated `use_template` parameter from email sending workflow
  - Simplified email rendering logic by consolidating to single `render()` method
  - Cleaner codebase with reduced technical debt

#### **Improvements**
- **Better Accessibility**
  - Restructured checkbox markup in history table for improved screen reader support
  - Improved checkbox accessibility with proper ARIA labels
  - Removed redundant CSS for cleaner stylesheet
  - Standardized checkbox column alignment and spacing across all admin tables

- **Enhanced User Experience**
  - Improved tooltip positioning for recipient overflow display
  - Fixed selection counting accuracy in bulk operations
  - Better visual hierarchy in history table with email information
  - Consistent spacing and alignment across admin interface

#### **Bug Fixes**
- Fixed tooltip positioning issues when hovering over recipient names
- Fixed selection counting logic to accurately reflect selected items
- Improved checkbox alignment consistency across different table views

---

### Version 1.3.2

#### **New Features**
- **Select All Users Across Pages**
  - Added "Select All [Total] Users" button to select all eligible users at once
  - Separate "Select All (on this page)" button for current page only
  - AJAX-powered selection without page reload
  - Visual loading state during selection process
  - Gmail-style bulk selection UX pattern

- **Smart Role-Based Selection**
  - "Authors Only" and "Contributors Only" now select ALL users with that role across all pages
  - Server-side role filtering via AJAX for accurate selection
  - Automatic creation of hidden checkboxes for users not on current page
  - Loading state feedback during role selection

#### **Improvements**
- **Enhanced User Selection Counter**
  - Shows total available users (e.g., "52 of 52 users selected")
  - Accurately counts both visible and hidden selected users
  - Separate tracking for visible table checkboxes and hidden checkboxes

- **Better Button Labeling**
  - Clear distinction between "Select All (on this page)" and "Select All X Users"
  - Primary button styling for "Select All Users" for better visual hierarchy
  - Consistent loading states across all selection buttons

- **Improved Pagination Handling**
  - Hidden checkboxes persist across page navigation
  - Selection state maintained when switching between pages
  - Deselect All properly clears both visible and hidden selections

#### **Bug Fixes**
- **Fixed Active Menu State**
  - Fixed Compose Email menu not showing as active when on compose page
  - Changed Compose Email from tab-based routing to dedicated page slug (`penalis-email-compose`)
  - Updated all internal links to use new slug format
  - Fixed pagination links in Recipients table to maintain correct page context

- **Fixed User Selection Counting**
  - Fixed "72 of 52 users selected" bug caused by string/integer type mismatch
  - Convert AJAX-returned user IDs from strings to integers for consistent comparison
  - Properly scope checkbox selectors to avoid counting unintended DOM elements
  - Fixed role selection showing incorrect counts (e.g., "19 of 52" for Contributors)

- **Fixed Pagination Issues**
  - Reduced recipients per page from 50 to 20 for better usability
  - Fixed pagination navigation to properly maintain page state
  - Updated pagination URLs to use correct page slug

#### **Code Quality**
- **Improved JavaScript Architecture**
  - Event delegation for better DOM handling with dynamic checkboxes
  - Separate class names for hidden checkboxes (`hidden-user-checkbox`) to avoid conflicts
  - Clear separation between visible table checkboxes and hidden checkboxes
  - Consistent integer conversion for all user ID comparisons

- **Enhanced AJAX Endpoints**
  - Added `penalis_get_all_user_ids` endpoint for fetching all eligible users
  - Added `penalis_get_users_by_role` endpoint for role-based filtering
  - Proper nonce verification and capability checks for all endpoints
  - Efficient queries using `fields => 'ID'` for performance

- **Better Security**
  - Added nonces for new AJAX endpoints (`getAllUserIds`, `getUsersByRole`)
  - Role validation against eligible roles before processing
  - Proper sanitization of role parameter

#### **User Experience**
- **Consistent Behavior**
  - All selection buttons now work consistently across pagination
  - Role filters select ALL users with that role, not just current page
  - Clear visual feedback with loading states and disabled buttons

- **Intuitive Interface**
  - Button labels clearly indicate scope of selection
  - Loading messages provide feedback during AJAX operations
  - Error messages guide users when operations fail

#### **Performance**
- Efficient AJAX queries fetch only user IDs, not full user objects
- Single AJAX call per selection operation
- No page reloads required for any selection operation
- Lazy loading of hidden checkboxes only when needed

---

### Version 1.3.1

#### **Bug Fixes**
- **Fixed Active Menu State**
  - Fixed Compose Email menu not showing as active when on compose page
  - Changed Compose Email from tab-based routing to dedicated page slug (`penalis-email-compose`)
  - Updated all internal links to use new slug format
  - Fixed pagination links in Recipients table to maintain correct page context

- **Improved Pagination**
  - Reduced recipients per page from 50 to 20 for better usability
  - Fixed pagination navigation to properly maintain page state
  - Updated pagination URLs to use correct page slug

- **Code Cleanup**
  - Removed legacy tab-based routing for compose and settings pages
  - Audited and updated all references to old URL format (`&tab=compose`, `&tab=settings`)
  - Updated Quick Actions links in Dashboard to use proper page slugs
  - Improved consistency across admin navigation

---

### Version 1.3.0

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

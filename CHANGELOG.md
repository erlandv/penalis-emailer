# Changelog

### Version 1.2.0 (Current)
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

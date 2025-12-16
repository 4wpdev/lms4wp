# Changelog

All notable changes to LMS4WP will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-XX

### Added - MVP Release

#### Core Functionality
- Plugin initialization with PSR-4 autoloading
- Database schema with custom tables:
  - `lms4wp_enrollments` - Course enrollment tracking
  - `lms4wp_progress` - Learning progress tracking (separate table)
  - `lms4wp_access` - Course access management for WooCommerce integration
- Activation and deactivation hooks
- Requirements checking (PHP 8.0+, WordPress 6.0+)

#### Post Types
- Course post type registration
- Lesson post type registration
- Quiz post type registration

#### Services Layer
- CourseService - Course management
- LessonService - Lesson management
- QuizService - Quiz management
- EnrollmentService - User enrollment handling
- ProgressService - Learning progress tracking
- AccessService - Access control management

#### Database Layer
- Repository pattern implementation:
  - CourseRepository
  - EnrollmentRepository
  - ProgressRepository
  - AccessRepository
- Database migrations support

#### WooCommerce Integration
- WooCommerce dependency checking
- Product-Course mapping
- Order-based access granting
- User authentication bridge

#### MCP Integration
- WordPress MCP protocol support
- MCP contexts (Course, Lesson, User, Access)
- MCP resources (Course, Lesson, Progress, Product)
- MCP tools (EnrollUser, GrantAccess, RevokeAccess, CompleteLesson)
- MCP schemas (JSON schemas for resources)

#### AI Integration
- AI Manager for multiple providers
- Support for OpenAI, Anthropic, and Local LLM providers
- AI prompts (Tutor, Quiz Generator, Lesson Summary)
- AI actions (RecommendNextLesson, MarkLessonComplete, GrantAccessByAI)

#### Admin Interface
- Admin menu structure
- Settings page
- Course-Product UI integration

#### Frontend
- Template system for courses and lessons
- Shortcodes for course display
- Access control for protected content

#### REST API
- MCP Bridge Controller for external integrations

#### Developer Experience
- Composer autoloading (PSR-4)
- TypeScript support for Gutenberg blocks
- Code structure following WordPress best practices
- Namespace: `ForWP\LMS`

### Technical Details
- **PHP Version**: 8.0+
- **WordPress Version**: 6.0+
- **License**: MIT
- **Namespace**: `ForWP\LMS`
- **Autoloading**: PSR-4 via Composer

---

## [Unreleased]

### Planned Features
- Gutenberg blocks (ai-tutor, course-list, course-progress)
- Advanced quiz functionality
- Certificate generation
- Email notifications
- Analytics and reporting
- Multi-language support (i18n)
- Unit tests and code coverage
- CI/CD pipeline
- Documentation

---

[1.0.0]: https://github.com/4wpdev/lms4wp/releases/tag/1.0.0


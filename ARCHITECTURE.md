# LMS4WP Architecture Documentation

## Services Layer - Final Architecture

### CourseService

**Methods:**
- `createCourse(array $data): int` - Create course (for external services/API)
- `updateCourse(int $course_id, array $data): bool` - Update course
- `getCourse(int $course_id): ?WP_Post` - Get single course
- `getCourses(array $args): array` - Get courses list with filters
- `getCourseLessons(int $course_id, array $args = []): array` - Get lessons for course (sorted by order)
- `getCourseProgress(int $course_id, int $user_id): array` - Get user progress with lesson weights
- `canUserAccess(int $course_id, int $user_id): bool` - Check access (via enrollment)
- `checkPrerequisites(int $course_id, int $user_id): array` - Check prerequisites (returns array of unmet course IDs)

**Notes:**
- Course progress calculated with individual lesson weights
- Each lesson can have different weight (stored in meta `_lms4wp_weight`)

### LessonService

**Methods:**
- `createLesson(array $data): int` - Create lesson (for external services/API)
- `getLesson(int $lesson_id): ?WP_Post` - Get single lesson
- `getLessonsByCourse(int $course_id): array` - Get lessons for course
- `getNextLesson(int $lesson_id, int $user_id): ?WP_Post` - Get next lesson in course
- `getPreviousLesson(int $lesson_id): ?WP_Post` - Get previous lesson
- `canUserAccess(int $lesson_id, int $user_id): bool` - Check access (free lesson or course enrollment)
- `markAsStarted(int $lesson_id, int $user_id): void` - Mark lesson as started (fires `lms4wp_lesson_started` hook)
- `markAsCompleted(int $lesson_id, int $user_id): void` - Mark lesson as completed (user confirms manually, fires `lms4wp_lesson_completed` hook)

**Notes:**
- Lesson completion is manual (user confirms)
- Hooks: `lms4wp_lesson_started`, `lms4wp_lesson_completed`

### QuizService

**Methods:**
- `getQuiz(int $quiz_id): ?WP_Post` - Get single quiz
- `getQuizQuestions(int $quiz_id): array` - Get quiz questions
- `submitQuiz(int $quiz_id, int $user_id, array $answers): array` - Submit quiz answers (saves to `lms4wp_quiz_results` table)
- `getQuizAttempts(int $quiz_id, int $user_id): array` - Get user attempts history
- `getQuizResults(int $quiz_id, int $user_id): array` - Get quiz results
- `canUserTakeQuiz(int $quiz_id, int $user_id): bool` - Check if user can take quiz (access + attempts limit)

**Database:**
- Table: `lms4wp_quiz_results`
- Fields: id, user_id, quiz_id, course_id, lesson_id, score, percentage, passed, answers (JSON), started_at, completed_at, time_spent
- Full logging for future comparison/analytics

### EnrollmentService

**Methods:**
- `enrollUser(int $course_id, int $user_id, string $method = 'manual'): bool` - Enroll user (checks for duplicates)
- `unenrollUser(int $course_id, int $user_id): bool` - Unenroll user
- `isEnrolled(int $course_id, int $user_id): bool` - Check enrollment
- `getUserCourses(int $user_id, array $args = []): array` - Get user's enrolled courses
- `getCourseEnrollments(int $course_id, array $args = []): array` - Get course enrollments list

**Notes:**
- Duplicate prevention: Check before insert (UNIQUE KEY user_course)
- Enrollment method stored in `enrollment_method` field (manual, purchase, admin, ai, api)

### ProgressService

**Methods:**
- `updateProgress(int $lesson_id, int $user_id, array $data): void` - Update progress (real-time during lesson)
- `getLessonProgress(int $lesson_id, int $user_id): ?array` - Get lesson progress
- `getCourseProgress(int $course_id, int $user_id): array` - Get course progress (with lesson weights)
- `calculateCourseCompletion(int $course_id, int $user_id): int` - Calculate completion percentage (weighted)
- `getTimeSpent(int $course_id, int $user_id): int` - Get total time spent (seconds)
- `trackTime(int $lesson_id, int $user_id, int $seconds): void` - Track time spent

**Hooks:**
- `lms4wp_lesson_started` - When user starts lesson
- `lms4wp_lesson_completed` - When user completes lesson
- `lms4wp_course_completed` - When user completes course

**Notes:**
- Real-time updates during lesson (no over-engineering)
- Time tracking via JavaScript timer → AJAX requests

### AccessService

**Methods:**
- `grantAccess(int $course_id, int $user_id, array $data): bool` - Grant access
- `revokeAccess(int $course_id, int $user_id): bool` - Revoke access
- `hasAccess(int $course_id, int $user_id): bool` - Check access (via enrollment check)
- `getUserAccess(int $user_id, array $args = []): array` - Get user access list

**Notes:**
- In MVP: Access = Enrollment (no time limits, no separate access table needed)
- User can use course if enrolled
- Future: May need separate `lms4wp_access` table for time-limited access

---

## Database Schema Updates

### New Table: lms4wp_quiz_results

```sql
CREATE TABLE lms4wp_quiz_results (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    quiz_id bigint(20) UNSIGNED NOT NULL,
    course_id bigint(20) UNSIGNED NULL,
    lesson_id bigint(20) UNSIGNED NULL,
    score int(11) DEFAULT 0,
    percentage int(3) DEFAULT 0,
    passed tinyint(1) DEFAULT 0,
    answers longtext COMMENT 'JSON array of answers',
    started_at datetime DEFAULT CURRENT_TIMESTAMP,
    completed_at datetime NULL,
    time_spent int(11) DEFAULT 0 COMMENT 'Time in seconds',
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY quiz_id (quiz_id),
    KEY course_id (course_id),
    KEY lesson_id (lesson_id),
    KEY completed_at (completed_at)
);
```

### Update: lms4wp_enrollments

Add field:
- `enrollment_method` varchar(20) DEFAULT 'manual' - Method: manual, purchase, admin, ai, api

### Update: lms4wp_progress

Add field (if needed):
- `lesson_weight` int(3) DEFAULT 1 - Weight of lesson for progress calculation

---

## Meta Fields

### Lesson
- `_lms4wp_weight` int - Weight for progress calculation (default: 1)

---

## Hooks Reference

### Action Hooks
- `lms4wp_lesson_started` - Fired when user starts a lesson
  - Params: `$lesson_id`, `$user_id`, `$course_id`
- `lms4wp_lesson_completed` - Fired when user completes a lesson
  - Params: `$lesson_id`, `$user_id`, `$course_id`
- `lms4wp_course_completed` - Fired when user completes a course
  - Params: `$course_id`, `$user_id`

### Filter Hooks
- `lms4wp_can_access_course` - Filter course access check
- `lms4wp_can_access_lesson` - Filter lesson access check
- `lms4wp_course_progress_calculation` - Filter course progress calculation

---

---

## Repositories Layer - WordPress Query Classes Pattern

### Architecture Decision
**Repositories use WordPress Query Classes Pattern (like WP_Query, WP_User_Query)**

### Approach
- Custom Query classes following WordPress core patterns
- Uses `$wpdb` under the hood but with clean WordPress-like API
- No external dependencies
- Consistent with WordPress ecosystem

### Query Classes to Create

#### LMS_Enrollment_Query
- Similar to `WP_Query` but for enrollments table
- Parameters: `user_id`, `course_id`, `status`, `enrollment_method`, `orderby`, `order`, `limit`, `offset`
- Methods: `get_results()`, `get_count()`, `get_sql()`

#### LMS_Progress_Query
- For progress table queries
- Parameters: `user_id`, `course_id`, `lesson_id`, `status`, `orderby`, `order`, `limit`, `offset`
- Methods: `get_results()`, `get_course_completion()`, `get_time_spent()`

#### LMS_Quiz_Result_Query
- For quiz results table queries
- Parameters: `user_id`, `quiz_id`, `course_id`, `lesson_id`, `passed`, `orderby`, `order`, `limit`, `offset`
- Methods: `get_results()`, `get_best_attempt()`, `get_attempts_count()`, `get_statistics()`

### Repository Pattern with Query Classes

#### CourseRepository
- Uses `WP_Query` for courses (standard post type)
- Methods return arrays/objects
- Example:
```php
$courses = new WP_Query([
    'post_type' => 'lms_course',
    'meta_query' => [...],
    'tax_query' => [...]
]);
```

#### EnrollmentRepository
- Uses `LMS_Enrollment_Query` for database operations
- Methods:
  - `find(int $id): ?array`
  - `findByUserAndCourse(int $user_id, int $course_id): ?array`
  - `findByUser(int $user_id, array $args = []): array`
  - `create(int $user_id, int $course_id, array $data = []): int`
  - `updateStatus(int $id, string $status): bool`
  - `delete(int $id): bool`
- **Note:** No batch update methods needed for MVP
- **Enrollment Statuses:** 
  - `active` (default) - Active enrollment
  - `completed` - Course completed
  - `cancelled` - Enrollment cancelled

#### ProgressRepository
- Uses `LMS_Progress_Query` for database operations
- Methods:
  - `createOrUpdate(int $user_id, int $course_id, int $lesson_id, array $data): int`
  - `findByUserAndLesson(int $user_id, int $lesson_id): ?array`
  - `findByUserAndCourse(int $user_id, int $course_id): array`
  - `getCourseCompletion(int $user_id, int $course_id): array`
- **Note:** No batch update methods needed for MVP

#### QuizResultsRepository
- Uses `LMS_Quiz_Result_Query` for database operations
- Methods:
  - `create(int $user_id, int $quiz_id, array $data): int`
  - `findByUserAndQuiz(int $user_id, int $quiz_id): array`
  - `getBestAttempt(int $user_id, int $quiz_id): ?array`
  - `getAttemptsCount(int $user_id, int $quiz_id): int`
- **Note:** No analytics methods needed for MVP

#### AccessRepository
- **MVP Decision:** Access = Enrollment, so use EnrollmentRepository
- Structure left for future (time-limited access, subscriptions)
- Methods not implemented in MVP

### Implementation Structure

```
includes/
├── Database/
│   ├── Queries/
│   │   ├── LMS_Enrollment_Query.php
│   │   ├── LMS_Progress_Query.php
│   │   └── LMS_Quiz_Result_Query.php
│   └── Repositories/
│       ├── CourseRepository.php
│       ├── EnrollmentRepository.php
│       ├── ProgressRepository.php
│       └── QuizResultsRepository.php
```

### Benefits
- Consistent with WordPress core patterns
- No external dependencies
- Clean, familiar API for WordPress developers
- Easy to extend and maintain
- Supports caching and optimization
- Easy to test and mock

---

## WooCommerce Integration - Required Dependency

### Architecture Decision
**WooCommerce is REQUIRED, not optional**

### Why WooCommerce is Required
- User authentication and registration
- Personal account/cabinet (My Account)
- Enrollment tied to products
- Order management
- User management

### MVP Scope
- No complex checkout flows
- No pricing logic (price in product)
- Simple enrollment via product purchase
- Basic order-based access granting

### Components

#### WooBootstrap
- Check WooCommerce dependency (required)
- Show admin notice if WooCommerce is missing
- Initialize all WooCommerce components
- Fail gracefully if WooCommerce not found

#### OrderListener
- Hook: `woocommerce_order_status_completed` - Grant access immediately after order completion
- **Note:** No refund/cancellation logic - access remains until account deletion
- Account deletion: Remove all enrollments and progress when user account deleted

#### AccessSynchronizer
- `grantAccessFromOrder(int $order_id): void` - Grant access from order (immediately on completion)
- `syncProductCourse(int $product_id, int $course_id): void` - Sync product-course link
- **Note:** No `revokeAccessFromOrder` method needed (no refund/cancellation)

#### CourseProductMap
- Methods:
  - `getCourseByProduct(int $product_id): ?int` - Get course ID by product ID
  - `getProductByCourse(int $course_id): ?int` - Get product ID by course ID
  - `linkCourseProduct(int $course_id, int $product_id): void` - Link course to product
- **Note:** No price synchronization needed (all prices are 0 in MVP)

#### LoginBridge (Auth)
- Use WooCommerce authentication system
- User registration via WooCommerce
- Personal account via WooCommerce My Account
- Custom "My Courses" tab in My Account (highly customizable)
- **Enrollment Methods:** Only classic purchase method (`purchase`)

---

---

## WooCommerce Integration - Required Dependency

### Architecture Decision
**WooCommerce is REQUIRED, not optional**

### Why WooCommerce is Required
- User authentication and registration
- Personal account/cabinet
- Enrollment tied to products
- Order management
- User management

### MVP Scope
- No complex checkout flows
- No pricing logic (price in product)
- Simple enrollment via product purchase
- Basic order-based access granting

### WooBootstrap
- Check for WooCommerce availability
- Show admin notice if WooCommerce is missing
- Initialize integration
- Fail gracefully if WooCommerce not found

### Components

#### WooBootstrap
- Check WooCommerce dependency
- Initialize all WooCommerce components
- Show admin notices

#### OrderListener
- Hook: `woocommerce_order_status_completed` - Grant access after order completion
- Hook: `woocommerce_order_status_refunded` - Revoke access on refund
- Hook: `woocommerce_order_status_cancelled` - Revoke access on cancellation

#### AccessSynchronizer
- `grantAccessFromOrder(int $order_id): void` - Grant access from order
- `revokeAccessFromOrder(int $order_id): void` - Revoke access from order
- `syncProductCourse(int $product_id, int $course_id): void` - Sync product-course link

#### CourseProductMap
- `getCourseByProduct(int $product_id): ?int` - Get course by product ID
- `getProductByCourse(int $course_id): ?int` - Get product by course ID
- `linkCourseProduct(int $course_id, int $product_id): void` - Link course to product
- **Note:** No price synchronization needed (all prices are 0 in MVP)

#### LoginBridge (Auth)
- Use WooCommerce authentication system
- User registration via WooCommerce
- Personal account via WooCommerce My Account
- Custom "My Courses" tab in My Account (highly customizable)
- **Enrollment Methods:** Only classic purchase method (`purchase`)

#### LoginBridge (Auth)
- Use WooCommerce authentication system
- User registration via WooCommerce
- Personal account via WooCommerce My Account

### Implementation Notes
- WooCommerce must be installed and activated
- Check on plugin activation
- Show error if WooCommerce not found
- All user management goes through WooCommerce
- Access granted immediately on order completion
- No refund/cancellation access revocation
- Account deletion removes all enrollments and progress
- Custom My Account tab for "My Courses"
- Enrollment method: only `purchase` (classic)

---

## MCP Integration - TODO (Continue Tomorrow)

### Architecture Decision
**WordPress MCP (Model Context Protocol) for maximum AI integration**

### To Discuss:
1. MCP endpoints needed (Contexts, Resources, Tools)
2. MCP Server vs Client
3. Contexts to pass to AI
4. AI actions via MCP Tools

---

## Summary of Today's Architecture Decisions

### ✅ Completed Sections:
1. **Services Layer** - Finalized with all methods and hooks
2. **Repositories Layer** - WordPress Query Classes Pattern
3. **WooCommerce Integration** - Required dependency, all logic defined

### 📋 Pending Sections:
4. **MCP Integration** - Continue tomorrow
5. **AI Integration** - After MCP
6. **Access Control** - After AI
7. **Admin & Frontend** - Final sections

---

## Next Steps (Tomorrow)

1. Continue with MCP Integration architecture
2. Then AI Integration
3. Then Access Control
4. Then Admin & Frontend
5. Final architecture review
6. Start implementation


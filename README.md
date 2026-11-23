# AC.TRACK — Academics & Incomplete-Grade (INC) Resolution Tracker

**AC.TRACK** is a student-centered academic management web platform built using the **Symfony PHP Framework (7.4 LTS)**. Its distinguishing focus is **INCOMPLETE-GRADE (INC) RESOLUTION**: providing a unified, clearly organized record that links students, missing requirements, teacher remarks, instructions, deadlines, student submission deliverables, and approval tracking.

---

## 1. Core Architecture & Tech Stack

- **Backend**: Symfony 7.4 LTS (PHP 8.3+), Composer
- **Database & ORM**: Doctrine ORM & Doctrine Migrations. Built DB-agnostic (runs on SQLite for local development; fully compatible with MySQL, MariaDB, and PostgreSQL).
- **Security & Access Control**: Symfony Security (form login, password hashing, login throttling) + **Record-Level Voters** (`IncompleteRecordVoter`, `GradeVoter`, `SectionSubjectVoter`).
- **Templating & Forms**: Twig templates, Symfony Forms, Validator, and strict CSRF protection.
- **Frontend & UX**: Semantic HTML5, Bootstrap 5.3 + Bootstrap Icons, accessible focus states, high contrast, WCAG 2.2 AA compliant.
- **Reporting**: Dompdf for PDF grade slips and academic progress reports; native streamed CSV export for administration.
- **Testing & Seed Data**: PHPUnit 13 + DoctrineFixturesBundle.

---

## 2. Roles & Record-Level Access Control (Voters)

The platform enforces strict role separation and granular record-level authorization using Symfony Voters:

1. **`ROLE_STUDENT`**:
   - Sees **ONLY** their own enrolled grades, INC records, submissions, and internal notifications.
   - Enforced by `IncompleteRecordVoter::VIEW` and `GradeVoter::VIEW`: accessing another student's record returns `403 Forbidden`.
   - Can transition an INC from `PENDING` to `SUBMITTED` with optional notes and file attachments. Cannot modify instructions, deadlines, or approve/complete an INC.
2. **`ROLE_TEACHER`**:
   - Manages **ONLY** subjects and sections assigned to them.
   - Enforced by `SectionSubjectVoter`, `GradeVoter::EDIT`, and `IncompleteRecordVoter`: attempting to edit grades or issue INCs for unassigned subjects returns `403 Forbidden`.
   - Can enter/update grades per period, create/edit INC requirements, review student submissions (including file downloads), and either mark them `COMPLETED` or return them to `PENDING` with feedback remarks.
3. **`ROLE_ADMIN` (Registrar)**:
   - Full record maintenance: CRUD for users, faculty, students, subjects, sections, class rosters, and grading periods.
   - Academic Progress & Resolution reports exportable as PDF and CSV.
   - Comprehensive audit trail viewer tracking grade updates, INC status changes, and deadline modifications.

---

## 3. Incomplete-Grade (INC) Status Transition Rules

```mermaid
stateDiagram-v2
    [*] --> PENDING: Created by Teacher / Admin
    PENDING --> SUBMITTED: Student marks SUBMITTED (with optional note & file upload)
    SUBMITTED --> PENDING: Teacher returns requirement for revisions (with remarks)
    SUBMITTED --> COMPLETED: Teacher marks COMPLETED / RESOLVED
    PENDING --> COMPLETED: Teacher marks COMPLETED directly
```

Allowed transitions:
- **Student**: `PENDING -> SUBMITTED` (attaches work and notes).
- **Teacher**: `SUBMITTED -> COMPLETED` (approves submission) or `SUBMITTED -> PENDING` (returns for revisions with remarks).
- **Admin**: Full administrative override with complete audit logging.

---

## 4. Entity-Relationship Diagram (ERD)

```mermaid
erDiagram
    User ||--o| StudentProfile : "has"
    User ||--o| TeacherProfile : "has"
    User ||--o{ Notification : "receives"
    User ||--o{ AuditLog : "initiates"

    Section ||--o{ StudentProfile : "enrolls"
    Section ||--o{ SectionSubject : "offers"
    Subject ||--o{ SectionSubject : "linked to"
    TeacherProfile ||--o{ SectionSubject : "taught by"

    StudentProfile ||--o{ Grade : "earns"
    Subject ||--o{ Grade : "for"
    GradingPeriod ||--o{ Grade : "in period"

    StudentProfile ||--o{ IncompleteRecord : "resolves"
    Subject ||--o{ IncompleteRecord : "subject"
    GradingPeriod ||--o{ IncompleteRecord : "period"
    TeacherProfile ||--o{ IncompleteRecord : "issued by"

    User {
        int id PK
        string username UK
        string email UK
        string password
        json roles
        boolean is_active
        datetime created_at
    }

    StudentProfile {
        int id PK
        int user_id FK
        string student_number UK
        string first_name
        string last_name
        string year_level
        int section_id FK
    }

    TeacherProfile {
        int id PK
        int user_id FK
        string employee_number UK
        string first_name
        string last_name
        string department
        string title
    }

    Subject {
        int id PK
        string code UK
        string title
        text description
        int units
    }

    Section {
        int id PK
        string name UK
        string academic_year
        string semester
    }

    SectionSubject {
        int id PK
        int section_id FK
        int subject_id FK
        int teacher_profile_id FK
    }

    GradingPeriod {
        int id PK
        string code UK
        string name
        date start_date
        date end_date
        int sequence_order
        boolean is_active
    }

    Grade {
        int id PK
        int student_profile_id FK
        int subject_id FK
        int section_id FK
        int grading_period_id FK
        decimal numerical_value
        string remarks
        boolean is_inc
        datetime updated_at
        int updated_by_user_id FK
    }

    IncompleteRecord {
        int id PK
        int student_profile_id FK
        int subject_id FK
        int section_id FK
        int grading_period_id FK
        int teacher_profile_id FK
        string missing_category
        string missing_item_title
        text teacher_explanation
        text required_action
        datetime deadline
        string status
        text submission_note
        string attachment_path
        string attachment_name
        datetime submitted_at
        datetime resolved_at
        datetime created_at
        datetime updated_at
        int created_by_user_id FK
        int updated_by_user_id FK
    }

    Notification {
        int id PK
        int user_id FK
        string title
        text message
        string link
        string type
        boolean is_read
        datetime created_at
    }

    AuditLog {
        int id PK
        int user_id FK
        string action
        string entity_type
        int entity_id
        json old_value
        json new_value
        string ip_address
        datetime created_at
    }
```

---

## 5. Seeded Test Accounts & Default Credentials

All seeded accounts use password: `password123`

| Role | Username | Full Name / Detail | Default Password |
|---|---|---|---|
| **Admin** | `admin` | Office of the University Registrar | `password123` |
| **Teacher** | `teacher.santos` | Prof. Maria Santos (Computer Science, BSCS-3A: CS101, CS102) | `password123` |
| **Teacher** | `teacher.reyes` | Engr. Juan Reyes (Information Technology, BSIT-3A: IT201, IT202) | `password123` |
| **Teacher** | `teacher.cruz` | Dr. Elena Cruz (Mathematics & Computing, MATH101, CS301) | `password123` |
| **Student** | `student01` | Carl Alvarez (`2024-00101`, BSIT-3A) — Has pending INC | `password123` |
| **Student** | `student02` | Beatrice Bautista (`2024-00102`, BSIT-3A) — Has completed INC | `password123` |
| **Student** | `student03` | Christian Castillo (`2024-00103`, BSIT-3A) — Has submitted INC | `password123` |
| **Student** | `student04` to `student15` | Diana Dela Cruz, Ethan Estrada, etc. (`2024-00104` to `2024-00115`) | `password123` |

---

## 6. Installation & Setup Instructions

### Prerequisites
- **PHP 8.3 or higher** (with extensions enabled in `php.ini`: `pdo_sqlite` or `pdo_mysql`, `intl`, `mbstring`, `zip`, `fileinfo`)
- **Composer 2.x**

### 1. Clone the Repository
```bash
git clone https://github.com/RickTuix/AC-Track.git
cd AC-Track
```

### 2. Install PHP Dependencies
```bash
composer install
```

### 3. Configure Environment
A default `.env` is pre-configured with SQLite. If you want custom settings or to use MySQL/PostgreSQL, copy `.env` to `.env.local`:
```ini
APP_ENV=dev
APP_SECRET=a85fd193498877e8f57f6b9c9f286b51
# For SQLite (default, zero-configuration local development):
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"

# Or for MySQL / MariaDB (production or local server):
# DATABASE_URL="mysql://root:password@127.0.0.1:3306/actrack?serverVersion=8.0&charset=utf8mb4"
```

### 4. Run Database Migrations
Create the database tables and schema:
```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Load Seed Fixtures
Populate the database with realistic demonstration data (1 admin, 3 faculty members, 15 students, 6 courses, 2 sections, 4 grading periods, grades, and an assortment of pending, submitted, and completed INC records):
```bash
php bin/console doctrine:fixtures:load --no-interaction
```

### 6. Start the Local Development Server
Run Symfony's built-in web server or PHP's built-in development server:
```bash
php -S 127.0.0.1:8000 -t public
```
Or using the Symfony CLI:
```bash
symfony serve
```
Open **[http://127.0.0.1:8000](http://127.0.0.1:8000)** in your browser.

---

## 7. Running the Automated Test Suite

Run PHPUnit with:
```bash
php vendor/phpunit/phpunit/phpunit
```

The test suite covers:
- **Authentication & Role Redirection**: Verifies login form, error handling, and redirection to `/admin/dashboard`, `/teacher/dashboard`, and `/student/dashboard`.
- **Security & Voter Access Rules**: Proves that a student cannot view another student's INC record, a teacher cannot edit unassigned subjects/sections, and students cannot access teacher/admin routes.
- **INC Resolution Workflow**: Proves status transitions (`PENDING -> SUBMITTED -> COMPLETED` and returning to `PENDING`), file upload handling, and timestamp tracking.
- **Internal Notifications**: Proves that automated notifications are dispatched upon INC creation, updates, student submissions, and resolutions.
- **Reporting**: Proves generation of PDF grade slips, academic progress PDFs, and CSV exports.

---

## 8. Automated Deadline Notification Command

AC.TRACK includes a Symfony Console command designed to run periodically via cron or Symfony Scheduler:

```bash
php bin/console app:notify-deadlines --hours=48
```

- Scans all `PENDING` INC records due within the specified threshold (default: 48 hours) or overdue.
- Automatically generates internal notifications (`DEADLINE_APPROACHING` / `DEADLINE_OVERDUE`) for students and teachers.
- Deduplicates notifications to prevent spamming.

---

## 9. Known Limitations & Scope Boundaries

In accordance with product guidelines, the following boundaries are maintained:
1. **Notifications are strictly internal**: System notifications are stored in the database and surfaced via the notification center badge and dashboard alerts. No external SMS, email, or web push transports are configured or stubbed.
2. **Analytics are strictly descriptive**: Grade averages, missing requirements counts, and completion percentages reflect historical and recorded values. No predictive modeling or student risk scores are calculated.
3. **Enrollment & Finance Out of Scope**: Tuition payment workflows, course registration carts, and full online course delivery (video streams, automated quizzes) are not part of this platform.

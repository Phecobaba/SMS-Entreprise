# Current screen catalogue

Captured from the isolated QA application on 15 November 2026. Every image uses synthetic QA data. No password, key or token is present; credential-slip secrets are replaced with `WITHHELD FROM DESIGN PACK`.

## auth — sign in — signed out

- **Route/state:** `/login`
- **Roles:** signed-out
- **Purpose:** Lets every account type enter the school system.
- **Reading order:** School brand, email or username, password, remember checkbox, Sign in, Forgot password, website link.
- **States captured:** Main populated state
- **Files:** `auth-sign-in-signed-out-1280.png`, `auth-sign-in-signed-out-390.png`
- **Known problem noticed:** None noticed during capture.
## auth — forgot password — signed out

- **Route/state:** `/forgot-password`
- **Roles:** signed-out
- **Purpose:** Starts account recovery without revealing whether an account exists.
- **Reading order:** School brand, identifier field, Send reset link, Back to sign in.
- **States captured:** Main populated state
- **Files:** `auth-forgot-password-signed-out-1280.png`, `auth-forgot-password-signed-out-390.png`
- **Known problem noticed:** None noticed during capture.

## auth — forgot password confirmation — signed out

- **Route/state:** `/forgot-password/check-email`
- **Roles:** signed-out
- **Purpose:** Shows the generic recovery confirmation.
- **Reading order:** School brand, generic confirmation, Back to sign in.
- **States captured:** Main populated state
- **Files:** `auth-forgot-password-confirmation-signed-out-1280.png`, `auth-forgot-password-confirmation-signed-out-390.png`
- **Known problem noticed:** None noticed during capture.

## shell — navigation — admin

- **Route/state:** `/dashboard`
- **Roles:** admin
- **Purpose:** Shows the shared navigation shell for the admin role.
- **Reading order:** Pinned brand, role-filtered navigation, notifications bell and badge, page header, Sign out; phone tab bar and More entry at narrow width.
- **States captured:** Desktop, tablet and phone states with unread notifications.
- **Files:** `shell-navigation-admin-1280.png`, `shell-navigation-admin-390.png`, `shell-navigation-admin-768.png`
- **Known problem noticed:** None noticed during capture.

## dashboard — home — admin

- **Route/state:** `/dashboard`
- **Roles:** admin
- **Purpose:** Gives the admin role its landing summary and next actions.
- **Reading order:** Role heading, notification/ticker content where applicable, summary cards, recent work and action links.
- **States captured:** Populated QA state with notification badge and role-specific notices.
- **Files:** `dashboard-home-admin-1280.png`, `dashboard-home-admin-390.png`
- **Known problem noticed:** At 390px the ticker text is clipped at the right edge, and the fixed bottom bar covers a horizontal strip of the long full-page capture.

## results — workspace — admin

- **Route/state:** `/results`
- **Roles:** Admin
- **Purpose:** Lets the Admin review the school results workload.
- **Reading order:** Filters, submitted/rejected work, class and subject context, action links.
- **States captured:** Submitted results present.
- **Files:** `results-workspace-admin-1280.png`, `results-workspace-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## results — configuration — admin

- **Route/state:** `/results/configuration`
- **Roles:** Admin and permitted Head
- **Purpose:** Configures grade scales, bands, components, templates and report-card options.
- **Reading order:** Section context, scales and bands, components, templates, comments and display options.
- **States captured:** Configured and locked/used items.
- **Files:** `results-configuration-admin-1280.png`, `results-configuration-admin-390.png`
- **Known problem noticed:** The phone page is exceptionally long; the fixed bottom bar overlays a configuration section while scrolling. Used/locked rows still present compact icon actions beside lock text.

## results — approval — admin

- **Route/state:** `/results/4`
- **Roles:** Admin
- **Purpose:** Lets the Admin inspect and approve or reject submitted marks.
- **Reading order:** Pupil, class, subject, components, total, grade, audit trail and approval actions.
- **States captured:** Submitted result.
- **Files:** `results-approval-admin-1280.png`, `results-approval-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## results — report card — admin

- **Route/state:** `/results/cards/1`
- **Roles:** Admin, permitted Head, exact form teacher
- **Purpose:** Shows the compiled report card used for staff review and portal/PDF output.
- **Reading order:** School heading, pupil details, subject table, summary figures, comments and PDF action.
- **States captured:** Compiled card with comments.
- **Files:** `results-report-card-admin-1280.png`, `results-report-card-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## cbt — exam list — admin

- **Route/state:** `/cbt`
- **Roles:** Admin, scoped Head/Teacher
- **Purpose:** Lists managed CBT exams and starts exam setup.
- **Reading order:** Exam creation form, filters, exam rows, statuses and management links.
- **States captured:** Draft, published and completed exam examples.
- **Files:** `cbt-exam-list-admin-1280.png`, `cbt-exam-list-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## cbt — exam setup — admin

- **Route/state:** `/cbt/exams/4/edit`
- **Roles:** Admin, scoped Head/Teacher
- **Purpose:** Edits a CBT exam’s setup before publication.
- **Reading order:** Type, subject/class, schedule, duration, closing and result-release controls.
- **States captured:** Draft exam.
- **Files:** `cbt-exam-setup-admin-1280.png`, `cbt-exam-setup-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## cbt — bulk question upload — admin

- **Route/state:** `/cbt/exams/4`
- **Roles:** Admin, scoped Head
- **Purpose:** Uploads questions to a draft exam using CSV.
- **Reading order:** Template link, instructions, file input, Preview upload, manual question form and publish/delete actions.
- **States captured:** Draft exam before upload.
- **Files:** `cbt-bulk-question-upload-admin-1280.png`, `cbt-bulk-question-upload-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## cbt — live progress — admin

- **Route/state:** `/cbt/exams/1/progress`
- **Roles:** Admin, scoped Head/Teacher
- **Purpose:** Monitors pupils during a published exam.
- **Reading order:** Exam status, writing/submitted/not-started counts and pupil progress rows.
- **States captured:** One pupil in progress.
- **Files:** `cbt-live-progress-admin-1280.png`, `cbt-live-progress-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## cbt — results list — admin

- **Route/state:** `/cbt-results`
- **Roles:** Admin, scoped Head/Teacher
- **Purpose:** Lists CBT results available to staff.
- **Reading order:** Exam rows, class/subject labels, status and results links.
- **States captured:** Published exams with attempts.
- **Files:** `cbt-results-list-admin-1280.png`, `cbt-results-list-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## cbt — results detail — admin

- **Route/state:** `/cbt-results/2`
- **Roles:** Admin, scoped Head/Teacher
- **Purpose:** Summarises results for one exam.
- **Reading order:** Exam summary, pupil attempts, marks and drill-down actions.
- **States captured:** Completed pupil attempt.
- **Files:** `cbt-results-detail-admin-1280.png`, `cbt-results-detail-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## parents — directory — admin

- **Route/state:** `/parents`
- **Roles:** Admin
- **Purpose:** Lists parent accounts and linked pupils.
- **Reading order:** Search, parent rows, contact/account state and detail links.
- **States captured:** Populated parent list.
- **Files:** `parents-directory-admin-1280.png`, `parents-directory-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## parents — detail — admin

- **Route/state:** `/parents/1`
- **Roles:** Admin
- **Purpose:** Shows one parent account and linked children.
- **Reading order:** Parent identity, email/account actions, linked pupils and activity context.
- **States captured:** Parent linked to the demo pupil.
- **Files:** `parents-detail-admin-1280.png`, `parents-detail-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## staff — list — admin

- **Route/state:** `/staff`
- **Roles:** Admin
- **Purpose:** Lists staff employment records.
- **Reading order:** Search/filter controls, staff rows, role title, contract/status and detail links.
- **States captured:** Teacher, Head and Bursar records.
- **Files:** `staff-list-admin-1280.png`, `staff-list-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## staff — record — admin

- **Route/state:** `/staff/1`
- **Roles:** Admin
- **Purpose:** Shows one staff member’s HR record.
- **Reading order:** Identity, employment details, salary/discipline/document/exit actions and audit context.
- **States captured:** Active teacher record.
- **Files:** `staff-record-admin-1280.png`, `staff-record-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## admissions — applications all — admin

- **Route/state:** `/admissions/applications?tab=all`
- **Roles:** Admin and scoped Head
- **Purpose:** Lists public applications in the selected workflow tab.
- **Reading order:** Tab counts, search, application rows, payment/session state and bulk session controls.
- **States captured:** all tab with QA examples.
- **Files:** `admissions-applications-all-admin-1280.png`, `admissions-applications-all-admin-390.png`
- **Known problem noticed:** At 390px the tab row and data table extend past the viewport; right-side columns are clipped.

## admissions — applications paid — admin

- **Route/state:** `/admissions/applications?tab=paid`
- **Roles:** Admin and scoped Head
- **Purpose:** Lists public applications in the selected workflow tab.
- **Reading order:** Tab counts, search, application rows, payment/session state and bulk session controls.
- **States captured:** paid tab with QA examples.
- **Files:** `admissions-applications-paid-admin-1280.png`, `admissions-applications-paid-admin-390.png`
- **Known problem noticed:** At 390px the tab row and data table extend past the viewport; right-side columns are clipped.

## admissions — applications unpaid — admin

- **Route/state:** `/admissions/applications?tab=unpaid`
- **Roles:** Admin and scoped Head
- **Purpose:** Lists public applications in the selected workflow tab.
- **Reading order:** Tab counts, search, application rows, payment/session state and bulk session controls.
- **States captured:** unpaid tab with QA examples.
- **Files:** `admissions-applications-unpaid-admin-1280.png`, `admissions-applications-unpaid-admin-390.png`
- **Known problem noticed:** At 390px the tab row and data table extend past the viewport; right-side columns are clipped.

## admissions — applications awaiting date — admin

- **Route/state:** `/admissions/applications?tab=awaiting_date`
- **Roles:** Admin and scoped Head
- **Purpose:** Lists public applications in the selected workflow tab.
- **Reading order:** Tab counts, search, application rows, payment/session state and bulk session controls.
- **States captured:** awaiting date tab with QA examples.
- **Files:** `admissions-applications-awaiting-date-admin-1280.png`, `admissions-applications-awaiting-date-admin-390.png`
- **Known problem noticed:** At 390px the tab row and data table extend past the viewport; right-side columns are clipped.

## admissions — applications expired — admin

- **Route/state:** `/admissions/applications?tab=expired`
- **Roles:** Admin and scoped Head
- **Purpose:** Lists public applications in the selected workflow tab.
- **Reading order:** Tab counts, search, application rows, payment/session state and bulk session controls.
- **States captured:** expired tab with QA examples.
- **Files:** `admissions-applications-expired-admin-1280.png`, `admissions-applications-expired-admin-390.png`
- **Known problem noticed:** At 390px the tab row and data table extend past the viewport; right-side columns are clipped.

## admissions — application detail — admin

- **Route/state:** `/admissions/applications/1`
- **Roles:** Admin and scoped Head
- **Purpose:** Shows a public application and its exam-day workflow.
- **Reading order:** Child and guardian details, payment/session state, assessment outcome and management actions.
- **States captured:** Registered application assigned to a session.
- **Files:** `admissions-application-detail-admin-1280.png`, `admissions-application-detail-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## admissions — exam sessions — admin

- **Route/state:** `/admissions/exam-sessions`
- **Roles:** Admin and permitted Head
- **Purpose:** Lists entrance exam sessions and creates new ones.
- **Reading order:** Create-session form, Section/venue/date fields, session rows and edit links.
- **States captured:** One upcoming session.
- **Files:** `admissions-exam-sessions-admin-1280.png`, `admissions-exam-sessions-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## admissions — exam session create accounts — admin

- **Route/state:** `/admissions/exam-sessions/1/edit`
- **Roles:** Admin
- **Purpose:** Manages a session and previews candidate account creation.
- **Reading order:** Session fields, assigned applications, eligible/skipped account counts and Create accounts action.
- **States captured:** Upcoming session with an eligible application.
- **Files:** `admissions-exam-session-create-accounts-admin-1280.png`, `admissions-exam-session-create-accounts-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## admissions — candidates — admin

- **Route/state:** `/admissions`
- **Roles:** Admin and scoped Head
- **Purpose:** Tracks candidates through assessment, offer and conversion.
- **Reading order:** Candidate rows, status, checklist setup and detail links.
- **States captured:** Offer-extended candidate.
- **Files:** `admissions-candidates-admin-1280.png`, `admissions-candidates-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## admissions — entrance exams — admin

- **Route/state:** `/cbt/entrance-exams`
- **Roles:** Admin, scoped Head
- **Purpose:** Lists and configures entrance CBT exams.
- **Reading order:** Entrance-exam setup, Level scope, pass mark and exam rows.
- **States captured:** Draft entrance exam.
- **Files:** `admissions-entrance-exams-admin-1280.png`, `admissions-entrance-exams-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## admissions — admission settings — admin

- **Route/state:** `/admissions/applications/settings`
- **Roles:** Admin
- **Purpose:** Configures public admissions and in-person assessment Levels.
- **Reading order:** Open/closed state, fee, privacy wording and Level assessment controls.
- **States captured:** Current QA settings.
- **Files:** `admissions-admission-settings-admin-1280.png`, `admissions-admission-settings-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## admissions — website settings — admin

- **Route/state:** `/website-settings`
- **Roles:** Admin
- **Purpose:** Configures admissions information shown on the public website.
- **Reading order:** Contact/opening hours, key dates, reopening and privacy fields.
- **States captured:** Current QA settings.
- **Files:** `admissions-website-settings-admin-1280.png`, `admissions-website-settings-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## admissions — conversion — admin

- **Route/state:** `/admissions/1`
- **Roles:** Admin and scoped Head
- **Purpose:** Reviews a candidate and prepares conversion to a pupil.
- **Reading order:** Status/history, checklist, offer, exam outcome, class/arm destination and override controls.
- **States captured:** Offer-extended candidate with one outstanding checklist item.
- **Files:** `admissions-conversion-admin-1280.png`, `admissions-conversion-admin-390.png`
- **Known problem noticed:** None noticed during capture.

## cbt — bulk question preview — admin

- **Route/state:** `/cbt/exams/4`
- **Roles:** Admin, scoped Head
- **Purpose:** Reviews valid CSV questions before applying them.
- **Reading order:** Question count, total marks, parsed questions/options, Apply upload and Cancel upload.
- **States captured:** Valid staged preview.
- **Files:** `cbt-bulk-question-preview-admin-1280.png`, `cbt-bulk-question-preview-admin-390.png`
- **Known problem noticed:** The response prints a PHP startup notice about a file created in the system temporary directory above the app shell.

## cbt — bulk question errors — admin

- **Route/state:** `/cbt/exams/4`
- **Roles:** Admin, scoped Head
- **Purpose:** Explains row and column errors in a CBT CSV.
- **Reading order:** Error count, row/column messages and Cancel upload.
- **States captured:** Invalid staged upload.
- **Files:** `cbt-bulk-question-errors-admin-1280.png`, `cbt-bulk-question-errors-admin-390.png`
- **Known problem noticed:** The response prints a PHP startup notice about a file created in the system temporary directory above the app shell.

## shell — navigation — head

- **Route/state:** `/dashboard`
- **Roles:** head
- **Purpose:** Shows the shared navigation shell for the head role.
- **Reading order:** Pinned brand, role-filtered navigation, notifications bell and badge, page header, Sign out; phone tab bar and More entry at narrow width.
- **States captured:** Desktop, tablet and phone states with unread notifications.
- **Files:** `shell-navigation-head-1280.png`, `shell-navigation-head-390.png`, `shell-navigation-head-768.png`
- **Known problem noticed:** None noticed during capture.

## dashboard — home — head

- **Route/state:** `/dashboard`
- **Roles:** head
- **Purpose:** Gives the head role its landing summary and next actions.
- **Reading order:** Role heading, notification/ticker content where applicable, summary cards, recent work and action links.
- **States captured:** Populated QA state with notification badge and role-specific notices.
- **Files:** `dashboard-home-head-1280.png`, `dashboard-home-head-390.png`
- **Known problem noticed:** None noticed during capture.

## shell — navigation — teacher

- **Route/state:** `/dashboard`
- **Roles:** teacher
- **Purpose:** Shows the shared navigation shell for the teacher role.
- **Reading order:** Pinned brand, role-filtered navigation, notifications bell and badge, page header, Sign out; phone tab bar and More entry at narrow width.
- **States captured:** Desktop, tablet and phone states with unread notifications.
- **Files:** `shell-navigation-teacher-1280.png`, `shell-navigation-teacher-390.png`, `shell-navigation-teacher-768.png`
- **Known problem noticed:** None noticed during capture.

## dashboard — home — teacher

- **Route/state:** `/dashboard`
- **Roles:** teacher
- **Purpose:** Gives the teacher role its landing summary and next actions.
- **Reading order:** Role heading, notification/ticker content where applicable, summary cards, recent work and action links.
- **States captured:** Populated QA state with notification badge and role-specific notices.
- **Files:** `dashboard-home-teacher-1280.png`, `dashboard-home-teacher-390.png`
- **Known problem noticed:** None noticed during capture.

## results — teaching list — teacher

- **Route/state:** `/results`
- **Roles:** Teacher
- **Purpose:** Shows the teacher every assigned subject and class pair.
- **Reading order:** Assigned-pair cards, status counts, My class link and result attention items.
- **States captured:** Two assigned pairs and a rejected result.
- **Files:** `results-teaching-list-teacher-1280.png`, `results-teaching-list-teacher-390.png`
- **Known problem noticed:** None noticed during capture.

## results — class students — teacher

- **Route/state:** `/results/teaching/2/1`
- **Roles:** Assigned Teacher
- **Purpose:** Shows pupils in one assigned class and subject.
- **Reading order:** Pair heading, term state, pupil rows, score status and entry actions.
- **States captured:** Approved and rejected pupil rows.
- **Files:** `results-class-students-teacher-1280.png`, `results-class-students-teacher-390.png`
- **Known problem noticed:** None noticed during capture.

## results — pupil score entry — teacher

- **Route/state:** `/results/teaching/2/1/students/2`
- **Roles:** Assigned Teacher
- **Purpose:** Lets the assigned teacher enter one pupil’s component scores.
- **Reading order:** Pupil and pair context, rejection reason, component fields, save/submit actions.
- **States captured:** Rejected result with reason.
- **Files:** `results-pupil-score-entry-teacher-1280.png`, `results-pupil-score-entry-teacher-390.png`
- **Known problem noticed:** None noticed during capture.

## results — my class — teacher

- **Route/state:** `/results/my-class/2`
- **Roles:** Exact form teacher
- **Purpose:** Shows class-wide results to the exact form teacher.
- **Reading order:** Class heading, pupil rows, subject/status overview and report-card links.
- **States captured:** Populated form class.
- **Files:** `results-my-class-teacher-1280.png`, `results-my-class-teacher-390.png`
- **Known problem noticed:** None noticed during capture.

## assignments — teacher list — teacher

- **Route/state:** `/assignments`
- **Roles:** Teacher
- **Purpose:** Lets a teacher create and review class assignments.
- **Reading order:** Assigned-pair picker, title, description, due date, assignment list and pupil marking links.
- **States captured:** Existing assignment.
- **Files:** `assignments-teacher-list-teacher-1280.png`, `assignments-teacher-list-teacher-390.png`
- **Known problem noticed:** None noticed during capture.

## shell — navigation — bursar

- **Route/state:** `/dashboard`
- **Roles:** bursar
- **Purpose:** Shows the shared navigation shell for the bursar role.
- **Reading order:** Pinned brand, role-filtered navigation, notifications bell and badge, page header, Sign out; phone tab bar and More entry at narrow width.
- **States captured:** Desktop, tablet and phone states with unread notifications.
- **Files:** `shell-navigation-bursar-1280.png`, `shell-navigation-bursar-390.png`, `shell-navigation-bursar-768.png`
- **Known problem noticed:** None noticed during capture.

## dashboard — home — bursar

- **Route/state:** `/dashboard`
- **Roles:** bursar
- **Purpose:** Gives the bursar role its landing summary and next actions.
- **Reading order:** Role heading, notification/ticker content where applicable, summary cards, recent work and action links.
- **States captured:** Populated QA state with notification badge and role-specific notices.
- **Files:** `dashboard-home-bursar-1280.png`, `dashboard-home-bursar-390.png`
- **Known problem noticed:** None noticed during capture.

## payments — bursar list — bursar

- **Route/state:** `/payments`
- **Roles:** Bursar
- **Purpose:** Shows invoices, settled payments and payment actions to the Bursar.
- **Reading order:** Payment summary, invoice creation, invoices, settled receipts and result-access rule where permitted.
- **States captured:** Unpaid and paid invoices.
- **Files:** `payments-bursar-list-bursar-1280.png`, `payments-bursar-list-bursar-390.png`
- **Known problem noticed:** None noticed during capture.

## payments — invoice — bursar

- **Route/state:** `/payments/invoices/1`
- **Roles:** Bursar and Admin
- **Purpose:** Shows the complete lifecycle of one invoice.
- **Reading order:** Owner and registration number, lines, payment state, manual/online controls, receipts and history.
- **States captured:** Unpaid term invoice with teller submission.
- **Files:** `payments-invoice-bursar-1280.png`, `payments-invoice-bursar-390.png`
- **Known problem noticed:** None noticed during capture.

## shell — navigation — student

- **Route/state:** `/dashboard`
- **Roles:** student
- **Purpose:** Shows the shared navigation shell for the student role.
- **Reading order:** Pinned brand, role-filtered navigation, notifications bell and badge, page header, Sign out; phone tab bar and More entry at narrow width.
- **States captured:** Desktop, tablet and phone states with unread notifications.
- **Files:** `shell-navigation-student-1280.png`, `shell-navigation-student-390.png`, `shell-navigation-student-768.png`
- **Known problem noticed:** None noticed during capture.

## dashboard — home — student

- **Route/state:** `/dashboard`
- **Roles:** student
- **Purpose:** Gives the student role its landing summary and next actions.
- **Reading order:** Role heading, notification/ticker content where applicable, summary cards, recent work and action links.
- **States captured:** Populated QA state with notification badge and role-specific notices.
- **Files:** `dashboard-home-student-1280.png`, `dashboard-home-student-390.png`
- **Known problem noticed:** None noticed during capture.

## attendance — student history — student

- **Route/state:** `/my-attendance`
- **Roles:** Student
- **Purpose:** Shows the pupil’s payment-gated recent attendance.
- **Reading order:** Attendance summary, recent recorded school days, session status and portal navigation.
- **States captured:** Unpaid tuition lock state.
- **Files:** `attendance-student-history-student-1280.png`, `attendance-student-history-student-390.png`
- **Known problem noticed:** None noticed during capture.

## assignments — student list — student

- **Route/state:** `/my-assignments`
- **Roles:** Student
- **Purpose:** Shows a pupil current assignments.
- **Reading order:** Subject/class label, title, due date, description and status.
- **States captured:** One upcoming assignment.
- **Files:** `assignments-student-list-student-1280.png`, `assignments-student-list-student-390.png`
- **Known problem noticed:** None noticed during capture.

## cbt — pupil exam — student

- **Route/state:** `/my-exams/attempts/1`
- **Roles:** Student
- **Purpose:** Lets a pupil answer and submit a timed exam.
- **Reading order:** Timer, question navigation, multiple-answer control, autosave status and submit action.
- **States captured:** In-progress attempt.
- **Files:** `cbt-pupil-exam-student-1280.png`, `cbt-pupil-exam-student-390.png`
- **Known problem noticed:** None noticed during capture.

## cbt — pupil result — student

- **Route/state:** `/my-exams/attempts/2/result`
- **Roles:** Student
- **Purpose:** Shows the released CBT result to the pupil.
- **Reading order:** Exam title, score and release-controlled review details.
- **States captured:** Released immediate result.
- **Files:** `cbt-pupil-result-student-1280.png`, `cbt-pupil-result-student-390.png`
- **Known problem noticed:** None noticed during capture.

## shell — navigation — parent

- **Route/state:** `/dashboard`
- **Roles:** parent
- **Purpose:** Shows the shared navigation shell for the parent role.
- **Reading order:** Pinned brand, role-filtered navigation, notifications bell and badge, page header, Sign out; phone tab bar and More entry at narrow width.
- **States captured:** Desktop, tablet and phone states with unread notifications.
- **Files:** `shell-navigation-parent-1280.png`, `shell-navigation-parent-390.png`, `shell-navigation-parent-768.png`
- **Known problem noticed:** None noticed during capture.

## dashboard — home — parent

- **Route/state:** `/dashboard`
- **Roles:** parent
- **Purpose:** Gives the parent role its landing summary and next actions.
- **Reading order:** Role heading, notification/ticker content where applicable, summary cards, recent work and action links.
- **States captured:** Populated QA state with notification badge and role-specific notices.
- **Files:** `dashboard-home-parent-1280.png`, `dashboard-home-parent-390.png`
- **Known problem noticed:** None noticed during capture.

## attendance — parent history — parent

- **Route/state:** `/my-attendance?student=1`
- **Roles:** Parent
- **Purpose:** Shows a parent the selected child’s payment-gated attendance.
- **Reading order:** Child context, attendance summary, recent days and portal navigation.
- **States captured:** Unpaid tuition lock state.
- **Files:** `attendance-parent-history-parent-1280.png`, `attendance-parent-history-parent-390.png`
- **Known problem noticed:** None noticed during capture.

## assignments — parent list — parent

- **Route/state:** `/parent/assignments?student=1`
- **Roles:** Parent
- **Purpose:** Shows a parent assignments for the selected child.
- **Reading order:** Child context and assignment rows.
- **States captured:** One upcoming assignment.
- **Files:** `assignments-parent-list-parent-1280.png`, `assignments-parent-list-parent-390.png`
- **Known problem noticed:** None noticed during capture.

## payments — parent pay — parent

- **Route/state:** `/my-fees?student=1`
- **Roles:** Parent
- **Purpose:** Shows a parent the selected pupil’s invoices and Pay controls.
- **Reading order:** Outstanding summary, invoice details, payment state, receipts and Pay action or setup note.
- **States captured:** Unpaid term invoice plus paid one-off receipt.
- **Files:** `payments-parent-pay-parent-1280.png`, `payments-parent-pay-parent-390.png`
- **Known problem noticed:** None noticed during capture.

## shell — navigation — candidate

- **Route/state:** `/my-admission`
- **Roles:** candidate
- **Purpose:** Shows the shared navigation shell for the candidate role.
- **Reading order:** Pinned brand, role-filtered navigation, notifications bell and badge, page header, Sign out; phone tab bar and More entry at narrow width.
- **States captured:** Desktop, tablet and phone states with unread notifications.
- **Files:** `shell-navigation-candidate-1280.png`, `shell-navigation-candidate-390.png`, `shell-navigation-candidate-768.png`
- **Known problem noticed:** None noticed during capture.

## dashboard — home — candidate

- **Route/state:** `/my-admission`
- **Roles:** candidate
- **Purpose:** Gives the candidate role its landing summary and next actions.
- **Reading order:** Role heading, notification/ticker content where applicable, summary cards, recent work and action links.
- **States captured:** Populated QA state with notification badge and role-specific notices.
- **Files:** `dashboard-home-candidate-1280.png`, `dashboard-home-candidate-390.png`
- **Known problem noticed:** None noticed during capture.

## results — approval — head

- **Route/state:** `/results/4`
- **Roles:** Head
- **Purpose:** Lets the Admin inspect and approve or reject submitted marks.
- **Reading order:** Pupil, class, subject, components, total, grade, audit trail and approval actions.
- **States captured:** Submitted result.
- **Files:** `results-approval-head-1280.png`, `results-approval-head-390.png`
- **Known problem noticed:** None noticed during capture.

## admissions — credential slips pdf — qa

- **Route/state:** `One-time Student/Parent credentials print output`
- **Roles:** Admin at account creation/import
- **Purpose:** Shows the A4 cut-slip layout used when a pupil and new parent receive one-time sign-in details.
- **Reading order:** School, account type, pupil/parent identity, class and arm, registration number, username, temporary-password field, sign-in address and first-sign-in instruction.
- **States captured:** Page 1 raster; secret field replaced with WITHHELD FROM DESIGN PACK.
- **Files:** `admissions-credential-slips-pdf-qa-page-1.png`
- **Known problem noticed:** In the first-page raster, the lower sign-in/instruction content is clipped in the parent slip; the print density needs review.

## admissions — candidate slip pdf — qa

- **Route/state:** `Exam-session Create accounts PDF response`
- **Roles:** Admin
- **Purpose:** Shows the A4 candidate exam-day credential-slip layout.
- **Reading order:** School, candidate, session, date/time, venue, username, temporary-password field, sign-in address and exam-day instruction.
- **States captured:** Page 1 raster; secret field replaced with WITHHELD FROM DESIGN PACK.
- **Files:** `admissions-candidate-slip-pdf-qa-page-1.png`
- **Known problem noticed:** None noticed during capture.

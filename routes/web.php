<?php

use App\Modules\Academics\Http\Controllers\AcademicAssignmentController;
use App\Modules\Academics\Http\Controllers\AcademicConfigurationController;
use App\Modules\Academics\Http\Controllers\AcademicStructureController;
use App\Modules\Admissions\Http\Controllers\AdmissionController;
use App\Modules\Admissions\Http\Controllers\ApplicationAdminController;
use App\Modules\Admissions\Http\Controllers\ExamSessionController;
use App\Modules\Admissions\Http\Controllers\IncomingAdmissionController;
use App\Modules\Admissions\Http\Controllers\PromotionController;
use App\Modules\Admissions\Http\Controllers\PublicApplicationController;
use App\Modules\Admissions\Http\Controllers\RegistrationNumberController;
use App\Modules\Admissions\Http\Controllers\StudentController;
use App\Modules\Admissions\Http\Controllers\StudentImportController;
use App\Modules\Assignments\Http\Controllers\AssignmentController;
use App\Modules\Assignments\Http\Controllers\ParentDirectoryController;
use App\Modules\Attendance\Http\Controllers\AttendanceController;
use App\Modules\CBT\Http\Controllers\CbtResultsController;
use App\Modules\CBT\Http\Controllers\ExamParticipantController;
use App\Modules\CBT\Http\Controllers\ExamStaffController;
use App\Modules\Core\Http\Controllers\ActivityLogController;
use App\Modules\Core\Http\Controllers\AuthController;
use App\Modules\Core\Http\Controllers\DashboardController;
use App\Modules\Core\Http\Controllers\ParentDetailsController;
use App\Modules\Core\Http\Controllers\PasswordController;
use App\Modules\Core\Http\Controllers\PasswordRecoveryController;
use App\Modules\Core\Http\Controllers\SchoolProfileController;
use App\Modules\Core\Http\Controllers\StaffAccountController;
use App\Modules\ENotes\Http\Controllers\ENoteController;
use App\Modules\LandingPage\Http\Controllers\LandingPageController;
use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Payments\Http\Controllers\FeeInvoicingController;
use App\Modules\Payments\Http\Controllers\PaymentController;
use App\Modules\Payments\Http\Controllers\PaymentGatewayController;
use App\Modules\Payments\Http\Controllers\PaystackWebhookController;
use App\Modules\Portals\Http\Controllers\PortalPageController;
use App\Modules\Results\Http\Controllers\ResultCardController;
use App\Modules\Results\Http\Controllers\ResultConfigurationController;
use App\Modules\Results\Http\Controllers\ResultWorkflowController;
use App\Modules\StaffHR\Http\Controllers\HeadStaffController;
use App\Modules\StaffHR\Http\Controllers\StaffController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('home');
Route::post('/payments/paystack/webhook', PaystackWebhookController::class)->middleware('throttle:60,1')->name('payments.webhook');
Route::get('/payments/paystack/verify', [PaymentController::class, 'verify'])->middleware('throttle:30,1')->name('payments.verify');
Route::get('/privacy', [PublicApplicationController::class, 'privacy'])->name('privacy');
Route::prefix('apply')->name('applications.')->group(function (): void {
    Route::get('/', [PublicApplicationController::class, 'create'])->name('create');
    Route::post('/', [PublicApplicationController::class, 'store'])->middleware('throttle:5,1,application-submit')->name('store');
    Route::get('/status', [PublicApplicationController::class, 'status'])->name('status');
    Route::get('/status/{application}', [PublicApplicationController::class, 'signedStatus'])->middleware(['signed', 'throttle:30,1,application-signed-status'])->name('status.signed');
    Route::post('/continue', [PublicApplicationController::class, 'continue'])->middleware('throttle:5,1,application-continue')->name('continue');
    Route::post('/check', [PublicApplicationController::class, 'check'])->middleware('throttle:2,1,application-check')->name('check');
    Route::get('/return', [PublicApplicationController::class, 'return'])->middleware('throttle:30,1,application-return')->name('return');
    Route::post('/forget', [PublicApplicationController::class, 'forget'])->name('forget');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'send'])->name('password.email');
    Route::get('/forgot-password/check-email', [PasswordRecoveryController::class, 'confirmation'])->name('password.request.confirmation');
    Route::get('/reset-password/success', [PasswordRecoveryController::class, 'success'])->name('password.reset.success');
    Route::get('/reset-password/{token}', [PasswordRecoveryController::class, 'resetForm'])->middleware('throttle:10,1')->name('password.reset');
    Route::post('/reset-password/{token}', [PasswordRecoveryController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
    Route::get('/admissions/register', fn () => redirect()->route('applications.create'))->name('admissions.register');
    Route::post('/admissions/register', fn () => redirect()->route('applications.create'))->name('admissions.register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/set-password', [PasswordController::class, 'show'])->name('password.set.show');
    Route::post('/set-password', [PasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.set.store');
    Route::get('/account', [StaffAccountController::class, 'account'])->name('account.show');
    Route::post('/account/password', [StaffAccountController::class, 'changeOwnPassword'])->middleware('throttle:5,1')->name('account.password');
    Route::post('/account/email', [ParentDetailsController::class, 'update'])->middleware('throttle:5,1')->name('account.email.update');
    Route::get('/account/email/confirm/{user}', [ParentDetailsController::class, 'confirm'])->middleware('signed')->name('account.email.confirm');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/my-invoices/{invoice}/paystack', [PaymentController::class, 'begin'])->name('payments.mine.begin');
    Route::post('/payment-attempts/{attempt}/check', [PaymentController::class, 'check'])->name('payments.attempts.check');
    Route::get('/my-fees', [PortalPageController::class, 'fees'])->name('payments.mine.index');
    Route::get('/my-results', [PortalPageController::class, 'results'])->name('results.mine.index');
    Route::get('/my-attendance', [PortalPageController::class, 'attendance'])->name('attendance.mine');
    Route::get('/my-enotes', [PortalPageController::class, 'enotes'])->name('enotes.mine');
    Route::get('/my-enotes/{note}/download', [PortalPageController::class, 'downloadEnote'])->name('enotes.mine.download');
    Route::get('/my-profile', [PortalPageController::class, 'profile'])->name('profile.mine');
    Route::get('/my-results/{card}', [ResultCardController::class, 'myResult'])->name('results.mine.show');
    Route::get('/my-results/{card}/pdf', [ResultCardController::class, 'myResultPdf'])->name('results.mine.pdf');
    Route::get('/my-assignments', [AssignmentController::class, 'mine'])->name('assignments.mine');
    Route::get('/parent/assignments', [AssignmentController::class, 'parent'])->name('assignments.parent');
    Route::get('/my-admission', [AdmissionController::class, 'mine'])->name('admissions.mine');
    Route::post('/my-admission/response', [AdmissionController::class, 'respondOwn'])->name('admissions.mine.respond');
    Route::get('/my-exams', [ExamParticipantController::class, 'index'])->name('cbt.mine');
    Route::post('/my-exams/{exam}/start', [ExamParticipantController::class, 'start'])->name('cbt.start');
    Route::get('/my-exams/attempts/{submission}', [ExamParticipantController::class, 'sit'])->name('cbt.sit');
    Route::post('/my-exams/attempts/{submission}/answers', [ExamParticipantController::class, 'answer'])->name('cbt.answers.store');
    Route::post('/my-exams/attempts/{submission}', [ExamParticipantController::class, 'submit'])->name('cbt.submit');
    Route::get('/my-exams/attempts/{submission}/result', [ExamParticipantController::class, 'result'])->name('cbt.result');
});

Route::middleware(['auth', 'can:create_staff'])->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/add', [StaffAccountController::class, 'create'])->name('accounts.create');
    Route::post('/add', [StaffAccountController::class, 'store'])->name('accounts.store');
});

Route::middleware(['auth', 'can:manage_staff_access'])->prefix('staff/access')->name('staff.access.')->group(function (): void {
    Route::get('/', [StaffAccountController::class, 'index'])->name('index');
    Route::get('/{user}', [StaffAccountController::class, 'show'])->name('show');
    Route::put('/{user}', [StaffAccountController::class, 'update'])->name('update');
    Route::post('/{user}/reset-password', [StaffAccountController::class, 'reset'])->name('reset');
    Route::post('/{user}/disable', [StaffAccountController::class, 'disable'])->name('disable');
    Route::post('/{user}/enable', [StaffAccountController::class, 'enable'])->name('enable');
});

Route::get('/activity-log', ActivityLogController::class)
    ->middleware(['auth', 'can:view_activity_log'])
    ->name('activity-log.index');

Route::middleware(['auth', 'can:view_section_staff'])->prefix('head/staff')->name('head.staff.')->group(function (): void {
    Route::get('/', [HeadStaffController::class, 'index'])->name('index');
    Route::get('/{staff}', [HeadStaffController::class, 'show'])->name('show');
    Route::post('/{staff}/discipline', [HeadStaffController::class, 'storeDiscipline'])
        ->middleware('can:record_section_discipline')->name('discipline.store');
});

Route::middleware(['auth', 'can:view_parents'])->prefix('parents')->name('parents.')->group(function (): void {
    Route::get('/', [ParentDirectoryController::class, 'index'])->name('index');
    Route::get('/{parent}', [ParentDirectoryController::class, 'show'])->name('show');
});
Route::middleware(['auth', 'can:manage_parent_accounts'])->prefix('parents')->name('parents.')->group(function (): void {
    Route::post('/{parent}/reset-password', [ParentDirectoryController::class, 'reset'])->name('reset');
    Route::put('/{parent}/email', [ParentDirectoryController::class, 'updateEmail'])->name('email.update');
    Route::post('/{parent}/children', [ParentDirectoryController::class, 'link'])->name('children.store');
    Route::delete('/{parent}/children/{student}', [ParentDirectoryController::class, 'unlink'])->name('children.destroy');
});

Route::middleware(['auth', 'can:view_sign_in_rules'])->prefix('sign-in-rules')->name('sign-in-rules.')->group(function (): void {
    Route::get('/', [StaffAccountController::class, 'rules'])->name('show');
    Route::post('/check', [StaffAccountController::class, 'checkUsername'])->middleware('throttle:20,1')->name('check');
});

Route::middleware(['auth', 'can:manage_cbt'])->prefix('cbt')->name('cbt.staff.')->group(function (): void {
    Route::get('/', [ExamStaffController::class, 'index'])->name('index');
    Route::get('/entrance-exams', [ExamStaffController::class, 'index'])->name('entrance');
    Route::get('/types', [ExamStaffController::class, 'types'])->name('types.index');
    Route::post('/types', [ExamStaffController::class, 'type'])->name('types.store');
    Route::put('/types/{type}', [ExamStaffController::class, 'updateType'])->name('types.update');
    Route::delete('/types/{type}', [ExamStaffController::class, 'destroyType'])->name('types.destroy');
    Route::post('/exams', [ExamStaffController::class, 'exam'])->name('exams.store');
    Route::get('/exams/{exam}', [ExamStaffController::class, 'show'])->name('show');
    Route::get('/exams/{exam}/edit', [ExamStaffController::class, 'edit'])->name('edit');
    Route::put('/exams/{exam}', [ExamStaffController::class, 'update'])->name('update');
    Route::post('/exams/{exam}/questions', [ExamStaffController::class, 'question'])->name('questions.store');
    Route::get('/exams/{exam}/questions/template', [ExamStaffController::class, 'questionTemplate'])->name('questions.template');
    Route::post('/exams/{exam}/questions/preview', [ExamStaffController::class, 'questionPreview'])->name('questions.preview');
    Route::post('/exams/{exam}/questions/apply', [ExamStaffController::class, 'questionApply'])->name('questions.apply');
    Route::post('/exams/{exam}/questions/cancel', [ExamStaffController::class, 'questionCancel'])->name('questions.cancel');
    Route::put('/exams/{exam}/questions/{question}', [ExamStaffController::class, 'updateQuestion'])->name('questions.update');
    Route::delete('/exams/{exam}/questions/{question}', [ExamStaffController::class, 'destroyQuestion'])->name('questions.destroy');
    Route::post('/exams/{exam}/publish', [ExamStaffController::class, 'publish'])->name('publish');
    Route::post('/exams/{exam}/release', [ExamStaffController::class, 'release'])->name('release');
    Route::post('/exams/{exam}/close', [ExamStaffController::class, 'close'])->name('close');
    Route::get('/exams/{exam}/conversion', [ExamStaffController::class, 'conversion'])->name('conversion');
    Route::post('/exams/{exam}/conversion', [ExamStaffController::class, 'convert'])->name('convert');
    Route::delete('/exams/{exam}', [ExamStaffController::class, 'destroy'])->name('destroy');
    Route::get('/exams/{exam}/attempts', [ExamStaffController::class, 'attempts'])->name('attempts');
    Route::get('/exams/{exam}/progress', [ExamStaffController::class, 'progress'])->name('progress');
    Route::get('/exams/{exam}/progress/refresh', [ExamStaffController::class, 'progressRefresh'])->name('progress.refresh');
    Route::post('/exams/{exam}/pupils/{student}/resit', [ExamStaffController::class, 'grantResit'])->name('resits.store');
    Route::delete('/exams/{exam}/resits/{grant}', [ExamStaffController::class, 'withdrawResit'])->name('resits.destroy');
});

Route::middleware(['auth', 'can:view_cbt_results'])->prefix('cbt-results')->name('cbt.results.')->group(function (): void {
    Route::get('/', [CbtResultsController::class, 'index'])->name('index');
    Route::get('/{exam}', [CbtResultsController::class, 'show'])->name('show');
    Route::get('/{exam}/pupils/{submission}', [CbtResultsController::class, 'pupil'])->name('pupil');
});

Route::middleware('auth')->prefix('admissions/applications')->name('admissions.applications.')->group(function (): void {
    Route::get('/', [ApplicationAdminController::class, 'index'])->name('index');
    Route::get('/{application}', [ApplicationAdminController::class, 'show'])->whereNumber('application')->name('show');
    Route::put('/{application}/outcome', [ApplicationAdminController::class, 'outcome'])->whereNumber('application')->name('outcome.update');
});

Route::middleware(['auth', 'can:manage_admissions'])->prefix('admissions')->name('admissions.')->group(function (): void {
    Route::get('/', [AdmissionController::class, 'index'])->name('index');
    Route::get('/applications/settings', [ApplicationAdminController::class, 'settings'])->name('applications.settings');
    Route::put('/applications/settings', [ApplicationAdminController::class, 'updateSettings'])->name('applications.settings.update');
    Route::put('/applications/settings/in-person-levels', [ApplicationAdminController::class, 'updateInPersonLevels'])->name('applications.settings.in-person-levels.update');
    Route::post('/applications/{application}/mark-paid', [ApplicationAdminController::class, 'markPaid'])->name('applications.mark-paid');
    Route::post('/applications/{application}/resend', [ApplicationAdminController::class, 'resend'])->middleware('throttle:5,1')->name('applications.resend');
    Route::delete('/applications/{application}', [ApplicationAdminController::class, 'destroy'])->name('applications.destroy');
    Route::post('/checklist-items', [AdmissionController::class, 'addChecklistItem'])->name('items.store');
    Route::get('/{candidate}', [AdmissionController::class, 'show'])->whereNumber('candidate')->name('show');
    Route::post('/{candidate}/exam-taken', [AdmissionController::class, 'examTaken'])->whereNumber('candidate')->name('exam-taken');
    Route::post('/{candidate}/exam-outcome', [AdmissionController::class, 'examOutcome'])->whereNumber('candidate')->name('exam-outcome');
    Route::post('/{candidate}/checklist/{item}', [AdmissionController::class, 'checklist'])->whereNumber(['candidate', 'item'])->name('checklist');
    Route::post('/{candidate}/offer', [AdmissionController::class, 'offer'])->whereNumber('candidate')->name('offer');
    Route::post('/{candidate}/lapse', [AdmissionController::class, 'lapse'])->whereNumber('candidate')->name('lapse');
    Route::post('/{candidate}/convert', [AdmissionController::class, 'convert'])->whereNumber('candidate')->name('convert');
    Route::post('/exam-sessions/{session}/accounts', [ExamSessionController::class, 'createAccounts'])->name('sessions.accounts.store');
    Route::post('/exam-sessions/{session}/accounts/reset', [ExamSessionController::class, 'resetAccounts'])->name('sessions.accounts.reset');
});

Route::middleware(['auth', 'can:manage_exam_sessions'])->prefix('admissions')->name('admissions.')->group(function (): void {
    Route::get('/exam-sessions', [ExamSessionController::class, 'index'])->name('sessions.index');
    Route::post('/exam-sessions', [ExamSessionController::class, 'store'])->name('sessions.store');
    Route::get('/exam-sessions/{session}/edit', [ExamSessionController::class, 'edit'])->name('sessions.edit');
    Route::put('/exam-sessions/{session}', [ExamSessionController::class, 'update'])->name('sessions.update');
    Route::delete('/exam-sessions/{session}', [ExamSessionController::class, 'destroy'])->name('sessions.destroy');
    Route::post('/applications/assign-session', [ExamSessionController::class, 'assign'])->name('applications.assign-session');
    Route::put('/applications/{application}/exam-session', [ExamSessionController::class, 'move'])->name('applications.session.update');
    Route::delete('/applications/{application}/exam-session', [ExamSessionController::class, 'remove'])->name('applications.session.destroy');
});

Route::middleware(['auth', 'can:view_students'])->prefix('students')->name('students.')->group(function (): void {
    Route::get('/', [StudentController::class, 'index'])->name('index');
});
Route::middleware(['auth', 'can:create_students'])->prefix('students')->name('students.')->group(function (): void {
    Route::get('/add', [StudentController::class, 'create'])->name('create');
    Route::get('/import', [StudentImportController::class, 'create'])->name('imports.create');
    Route::get('/import/template', [StudentImportController::class, 'template'])->name('imports.template');
    Route::post('/import/check', [StudentImportController::class, 'check'])->name('imports.check');
    Route::get('/import/{import}', [StudentImportController::class, 'show'])->name('imports.show');
    Route::post('/import/{import}/confirm', [StudentImportController::class, 'confirm'])->name('imports.confirm');
    Route::post('/', [StudentController::class, 'store'])->name('store');
});
Route::middleware('auth')->prefix('students')->name('students.')->group(function (): void {
    Route::get('/{student}', [StudentController::class, 'show'])->name('show');
    Route::put('/{student}', [StudentController::class, 'update'])->middleware('can:manage_student_accounts')->name('update');
    Route::post('/{student}/registration-number', [StudentController::class, 'changeNumber'])->middleware('can:change_registration_numbers')->name('registration-number.update');
    Route::post('/{student}/reset-password', [StudentController::class, 'resetStudent'])->middleware('can:manage_student_accounts')->name('reset');
    Route::post('/{student}/parents/{parent}/reset-password', [StudentController::class, 'resetParent'])->middleware('can:manage_student_accounts')->name('parents.reset');
});
Route::middleware(['auth', 'can:manage_registration_numbers'])->prefix('registration-numbers')->name('registration-numbers.')->group(function (): void {
    Route::get('/', [RegistrationNumberController::class, 'edit'])->name('edit');
    Route::put('/', [RegistrationNumberController::class, 'update'])->name('update');
});

Route::middleware(['auth', 'can:manage_payments'])->prefix('payments')->name('payments.')->group(function (): void {
    Route::get('/', [PaymentController::class, 'index'])->name('index');
    Route::get('/record', [PaymentController::class, 'record'])->name('record.index');
    Route::get('/approvals', [PaymentController::class, 'approvals'])->name('approvals.index');
    Route::post('/invoices', [PaymentController::class, 'invoice'])->name('invoices.store');
    Route::post('/invoices/{invoice}/manual', [PaymentController::class, 'manual'])->name('manual');
    Route::get('/manual-submissions/{submission}/slip', [PaymentController::class, 'slip'])->name('manual-submissions.slip');
    Route::post('/invoices/{invoice}/paystack', [PaymentController::class, 'begin'])->name('begin');
    Route::post('/expenses', [PaymentController::class, 'expense'])->name('expenses.store');
    Route::post('/result-access-rule', [PaymentController::class, 'rule'])->name('rule');
});

Route::middleware('auth')->prefix('payments')->name('payments.')->group(function (): void {
    Route::get('/gateway', [PaymentGatewayController::class, 'show'])->name('gateway.show');
});

Route::middleware(['auth', 'can:manage_payment_gateway', 'throttle:5,1'])->prefix('payments/gateway')->name('payments.gateway.')->group(function (): void {
    Route::post('/', [PaymentGatewayController::class, 'store'])->name('store');
    Route::post('/test', [PaymentGatewayController::class, 'test'])->name('test');
});

Route::middleware(['auth', 'can:manage_fee_schedule'])->prefix('payments')->name('payments.')->group(function (): void {
    Route::get('/invoices/{invoice}', [PaymentController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/parts', [PaymentController::class, 'part'])->name('invoices.parts.store');
    Route::post('/invoices/{invoice}/adjustments', [PaymentController::class, 'adjustment'])->name('invoices.adjustments.store');
    Route::post('/invoices/{invoice}/void', [PaymentController::class, 'voidInvoice'])->name('invoices.void');
    Route::post('/part-payments/{invoice}/void', [PaymentController::class, 'voidPart'])->name('parts.void');
});

Route::middleware(['auth', 'can:approve_payments'])->prefix('payments')->name('payments.')->group(function (): void {
    Route::post('/manual-submissions/{submission}/approve', [PaymentController::class, 'approve'])->name('manual-submissions.approve');
    Route::post('/manual-submissions/{submission}/reject', [PaymentController::class, 'reject'])->name('manual-submissions.reject');
    Route::put('/approval-setting', [PaymentController::class, 'oneStep'])->name('approval-setting.update');
});

Route::middleware(['auth', 'can:manage_fee_schedule'])->prefix('fees')->name('fees.')->group(function (): void {
    Route::get('/schedule', [FeeInvoicingController::class, 'schedule'])->name('schedule.index');
    Route::post('/items', [FeeInvoicingController::class, 'storeItem'])->name('items.store');
    Route::delete('/items/{item}', [FeeInvoicingController::class, 'destroyItem'])->name('items.destroy');
    Route::post('/schedule-lines', [FeeInvoicingController::class, 'storeLine'])->name('schedule-lines.store');
    Route::put('/schedule-lines/{line}', [FeeInvoicingController::class, 'updateLine'])->name('schedule-lines.update');
    Route::delete('/schedule-lines/{line}', [FeeInvoicingController::class, 'destroyLine'])->name('schedule-lines.destroy');
    Route::post('/schedule/copy/preview', [FeeInvoicingController::class, 'previewScheduleCopy'])->name('schedule.copy.preview');
    Route::post('/schedule/copy/apply', [FeeInvoicingController::class, 'applyScheduleCopy'])->name('schedule.copy.apply');
    Route::get('/generate', [FeeInvoicingController::class, 'generate'])->name('generate.index');
    Route::post('/generate/preview', [FeeInvoicingController::class, 'preview'])->name('generate.preview');
    Route::post('/generate/apply', [FeeInvoicingController::class, 'apply'])->name('generate.apply');
    Route::get('/not-invoiced', [FeeInvoicingController::class, 'notInvoiced'])->name('not-invoiced.index');
    Route::post('/students/{student}/invoice', [FeeInvoicingController::class, 'invoiceStudent'])->name('students.invoice');
});

Route::middleware(['auth', 'can:manage_staff_records'])->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/', [StaffController::class, 'index'])->name('index');
    Route::get('/account-search', [StaffController::class, 'searchAccounts'])->name('accounts.search');
    Route::post('/', [StaffController::class, 'store'])->name('store');
    Route::get('/{staff}', [StaffController::class, 'show'])->name('show');
    Route::get('/{staff}/edit', [StaffController::class, 'edit'])->name('edit');
    Route::put('/{staff}', [StaffController::class, 'update'])->name('update');
    Route::post('/{staff}/salary', [StaffController::class, 'addSalary'])->name('salary.store');
    Route::post('/{staff}/discipline', [StaffController::class, 'addDiscipline'])->name('discipline.store');
    Route::post('/{staff}/documents', [StaffController::class, 'document'])->name('documents.store');
    Route::get('/{staff}/documents/{document}', [StaffController::class, 'download'])->name('documents.download');
    Route::post('/{staff}/exit', [StaffController::class, 'exit'])->name('exit');
});
Route::get('/staff/{staff}/salary', [StaffController::class, 'salary'])->middleware(['auth', 'can:view_staff_salary'])->name('staff.salary');
Route::get('/staff/{staff}/discipline', [StaffController::class, 'discipline'])->middleware(['auth', 'can:view_staff_disciplinary'])->name('staff.discipline');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware('auth')->prefix('academics')->name('academics.')->group(function (): void {
    Route::get('/levels', [AcademicConfigurationController::class, 'levels'])->name('levels.index');
    Route::post('/levels', [AcademicConfigurationController::class, 'storeLevel'])->name('levels.store');
    Route::put('/levels/{level}', [AcademicConfigurationController::class, 'updateLevel'])->name('levels.update');
    Route::delete('/levels/{level}', [AcademicConfigurationController::class, 'destroyLevel'])->name('levels.destroy');
    Route::get('/bulk-subjects', [AcademicAssignmentController::class, 'bulkSubjects'])->name('bulk-subjects.index');
    Route::post('/bulk-subjects/review', [AcademicAssignmentController::class, 'reviewBulkSubjects'])->name('bulk-subjects.review');
    Route::post('/bulk-subjects/apply', [AcademicAssignmentController::class, 'applyBulkSubjects'])->name('bulk-subjects.apply');
    Route::get('/teacher-assignments', [AcademicAssignmentController::class, 'teacherAssignments'])->name('teacher-assignments.index');
    Route::post('/teacher-assignments/review', [AcademicAssignmentController::class, 'reviewTeacherAssignments'])->name('teacher-assignments.review');
    Route::post('/teacher-assignments/apply', [AcademicAssignmentController::class, 'applyTeacherAssignments'])->name('teacher-assignments.apply');
    Route::get('/promotion', [PromotionController::class, 'index'])->name('promotion.index');
    Route::post('/promotion/review', [PromotionController::class, 'review'])->name('promotion.review');
    Route::post('/promotion/apply', [PromotionController::class, 'apply'])->name('promotion.apply');
    Route::post('/promotion/move-one', [PromotionController::class, 'moveOne'])->name('promotion.move-one');
    Route::get('/promotion/incoming', [IncomingAdmissionController::class, 'index'])->name('promotion.incoming.index');
    Route::post('/promotion/incoming/review', [IncomingAdmissionController::class, 'review'])->name('promotion.incoming.review');
    Route::post('/promotion/incoming/accept', [IncomingAdmissionController::class, 'accept'])->name('promotion.incoming.accept');
    Route::post('/promotion/incoming/not-continuing', [IncomingAdmissionController::class, 'notContinuing'])->name('promotion.incoming.not-continuing');
    Route::get('/classes', [AcademicStructureController::class, 'classes'])->name('classes.index');
    Route::post('/classes', [AcademicConfigurationController::class, 'storeClass'])->name('classes.store');
    Route::put('/classes/{class}', [AcademicConfigurationController::class, 'updateClass'])->name('classes.update');
    Route::post('/classes/{class}/move', [AcademicStructureController::class, 'moveClass'])->name('classes.move');
    Route::delete('/classes/{class}', [AcademicStructureController::class, 'destroyClass'])->name('classes.destroy');
    Route::get('/classes-arrange', [AcademicStructureController::class, 'arrange'])->name('classes.arrange');
    Route::post('/classes-arrange', [AcademicStructureController::class, 'saveArrangement'])->name('classes.arrange.store');
    Route::post('/arms', [AcademicConfigurationController::class, 'storeArm'])->name('arms.store');
    Route::put('/arms/{arm}', [AcademicConfigurationController::class, 'updateArm'])->name('arms.update');
    Route::delete('/arms/{arm}', [AcademicConfigurationController::class, 'destroyArm'])->name('arms.destroy');
    Route::get('/setup', [AcademicConfigurationController::class, 'index'])->name('setup');
    Route::post('/subjects', [AcademicConfigurationController::class, 'storeSubject'])->name('subjects.store');
    Route::put('/subjects/{subject}', [AcademicConfigurationController::class, 'updateSubject'])->name('subjects.update');
    Route::delete('/subjects/{subject}', [AcademicConfigurationController::class, 'destroySubject'])->name('subjects.destroy');
    Route::post('/class-subjects', [AcademicConfigurationController::class, 'attachSubject'])->name('class-subjects.store');
});

Route::middleware(['auth', 'can:manage_academics'])->prefix('academics')->name('academics.')->group(function (): void {
    Route::get('/', [AcademicStructureController::class, 'sections'])->name('index');
    Route::get('/sessions', [AcademicConfigurationController::class, 'sessions'])->name('sessions.index');
    Route::post('/sections', [AcademicStructureController::class, 'storeSection'])->name('sections.store');
    Route::put('/sections/{section}', [AcademicStructureController::class, 'updateSection'])->name('sections.update');
    Route::get('/departments', [AcademicStructureController::class, 'departments'])->name('departments.index');
    Route::post('/departments', [AcademicStructureController::class, 'storeDepartment'])->name('departments.store');
    Route::put('/departments/{department}', [AcademicStructureController::class, 'updateDepartment'])->name('departments.update');
    Route::delete('/departments/{department}', [AcademicStructureController::class, 'destroyDepartment'])->name('departments.destroy');
    Route::post('/sessions', [AcademicConfigurationController::class, 'storeSession'])->name('sessions.store');
    Route::put('/sessions/{session}', [AcademicConfigurationController::class, 'updateSession'])->name('sessions.update');
    Route::delete('/sessions/{session}', [AcademicConfigurationController::class, 'destroySession'])->name('sessions.destroy');
    Route::post('/terms', [AcademicConfigurationController::class, 'storeTerm'])->name('terms.store');
    Route::put('/terms/{term}', [AcademicConfigurationController::class, 'updateTerm'])->name('terms.update');
    Route::delete('/terms/{term}', [AcademicConfigurationController::class, 'destroyTerm'])->name('terms.destroy');
});

Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('auth')->name('attendance.index');
Route::middleware(['auth', 'can:mark_attendance'])->prefix('attendance')->name('attendance.')->group(function (): void {
    Route::get('/arms/{arm}', [AttendanceController::class, 'day'])->name('day');
    Route::post('/arms/{arm}/mark', [AttendanceController::class, 'mark'])->name('mark');
    Route::post('/arms/{arm}/lock', [AttendanceController::class, 'lock'])->middleware('can:manage_attendance')->name('lock');
});
Route::get('/attendance/alerts', [AttendanceController::class, 'alerts'])->middleware(['auth', 'can:view_attendance_alerts'])->name('attendance.alerts');
Route::middleware(['auth', 'can:manage_attendance'])->prefix('attendance')->name('attendance.')->group(function (): void {
    Route::post('/form-teachers', [AttendanceController::class, 'assign'])->name('form-teachers.store');
    Route::get('/settings', [AttendanceController::class, 'settings'])->name('settings');
    Route::put('/settings/{section}', [AttendanceController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/{section}/not-held', [AttendanceController::class, 'markNotHeld'])->name('not-held.store');
    Route::delete('/not-held/{entry}', [AttendanceController::class, 'removeNotHeld'])->name('not-held.destroy');
});

Route::middleware(['auth', 'can:mark_assignments'])->prefix('assignments')->name('assignments.')->group(function (): void {
    Route::get('/', [AssignmentController::class, 'index'])->name('index');
    Route::get('/parent-search', [AssignmentController::class, 'searchParents'])->middleware('can:manage_assignments')->name('parents.search');
    Route::post('/', [AssignmentController::class, 'store'])->name('store');
    Route::post('/parent-wards', [AssignmentController::class, 'linkWard'])->middleware('can:manage_assignments')->name('parent-wards.store');
    Route::post('/parents', [AssignmentController::class, 'createParent'])->middleware('can:manage_assignments')->name('parents.store');
    Route::get('/{assignment}', [AssignmentController::class, 'show'])->name('show');
    Route::post('/{assignment}/students/{student}', [AssignmentController::class, 'mark'])->name('mark');
});

Route::middleware(['auth', 'can:upload_enotes'])->prefix('enotes')->name('enotes.')->group(function (): void {
    Route::get('/', [ENoteController::class, 'index'])->name('index');
    Route::post('/', [ENoteController::class, 'store'])->name('store');
    Route::get('/{note}/download', [ENoteController::class, 'download'])->name('download');
});

Route::middleware(['auth', 'can:manage_school_profile'])->group(function (): void {
    Route::get('/school-profile', [SchoolProfileController::class, 'edit'])->name('school-profile.edit');
    Route::put('/school-profile', [SchoolProfileController::class, 'update'])->name('school-profile.update');
    Route::get('/website-settings', [ApplicationAdminController::class, 'websiteSettings'])->name('website-settings.edit');
    Route::put('/website-settings', [ApplicationAdminController::class, 'updateWebsiteSettings'])->name('website-settings.update');
});

Route::middleware(['auth', 'can:view_results_workspace'])->prefix('results')->name('results.')->group(function (): void {
    Route::get('/', [ResultWorkflowController::class, 'index'])->name('index');
    Route::get('/teaching/{class}/{subject}', [ResultWorkflowController::class, 'teacherPair'])->middleware('can:enter_results')->name('teacher.pair');
    Route::get('/teaching/{class}/{subject}/score-template', [ResultWorkflowController::class, 'teacherCsvTemplate'])->middleware('can:enter_results')->name('teacher.csv.template');
    Route::post('/teaching/{class}/{subject}/score-preview', [ResultWorkflowController::class, 'teacherCsvPreview'])->middleware('can:enter_results')->name('teacher.csv.preview');
    Route::post('/teaching/{class}/{subject}/score-apply', [ResultWorkflowController::class, 'teacherCsvApply'])->middleware('can:enter_results')->name('teacher.csv.apply');
    Route::get('/teaching/{class}/{subject}/students/{student}', [ResultWorkflowController::class, 'teacherEntry'])->middleware('can:enter_results')->name('teacher.entry');
    Route::post('/teaching/{class}/{subject}/students/{student}/draft', [ResultWorkflowController::class, 'teacherSave'])->middleware('can:enter_results')->name('teacher.draft');
    Route::post('/teaching/{class}/{subject}/students/{student}/submit', [ResultWorkflowController::class, 'teacherSubmit'])->middleware('can:enter_results')->name('teacher.submit');
    Route::get('/my-class/{arm}', [ResultWorkflowController::class, 'teacherClass'])->middleware('can:enter_results')->name('teacher.class');
    Route::get('/configuration', [ResultConfigurationController::class, 'index'])->middleware('can:manage_result_configuration')->name('configuration');
    Route::post('/components', [ResultConfigurationController::class, 'component'])->middleware('can:manage_result_configuration')->name('components.store');
    Route::put('/components/{component}', [ResultConfigurationController::class, 'updateComponent'])->middleware('can:manage_result_configuration')->name('components.update');
    Route::delete('/components/{component}', [ResultConfigurationController::class, 'destroyComponent'])->middleware('can:manage_result_configuration')->name('components.destroy');
    Route::post('/schemes', [ResultConfigurationController::class, 'scheme'])->middleware('can:manage_result_configuration')->name('schemes.store');
    Route::put('/schemes/{scheme}', [ResultConfigurationController::class, 'updateScheme'])->middleware('can:manage_result_configuration')->name('schemes.update');
    Route::delete('/schemes/{scheme}', [ResultConfigurationController::class, 'destroyScheme'])->middleware('can:manage_result_configuration')->name('schemes.destroy');
    Route::post('/bands', [ResultConfigurationController::class, 'band'])->middleware('can:manage_result_configuration')->name('bands.store');
    Route::put('/bands/{band}', [ResultConfigurationController::class, 'updateBand'])->middleware('can:manage_result_configuration')->name('bands.update');
    Route::delete('/bands/{band}', [ResultConfigurationController::class, 'destroyBand'])->middleware('can:manage_result_configuration')->name('bands.destroy');
    Route::post('/templates', [ResultConfigurationController::class, 'template'])->middleware('can:manage_result_configuration')->name('templates.store');
    Route::put('/templates/{template}', [ResultConfigurationController::class, 'updateTemplate'])->middleware('can:manage_result_configuration')->name('templates.update');
    Route::delete('/templates/{template}', [ResultConfigurationController::class, 'destroyTemplate'])->middleware('can:manage_result_configuration')->name('templates.destroy');
    Route::post('/templates/{template}/retire', [ResultConfigurationController::class, 'retireTemplate'])->middleware('can:manage_result_configuration')->name('templates.retire');
    Route::post('/templates/{template}/subject-remarks', [ResultConfigurationController::class, 'subjectRemarks'])->middleware('can:manage_result_configuration')->name('templates.subject-remarks');
    Route::post('/comment-mode', [ResultConfigurationController::class, 'commentMode'])->middleware('can:manage_result_configuration')->name('comment-mode.store');
    Route::post('/comment-ranges', [ResultConfigurationController::class, 'commentRange'])->middleware('can:manage_result_configuration')->name('comment-ranges.store');
    Route::put('/comment-ranges/{commentRange}', [ResultConfigurationController::class, 'updateCommentRange'])->middleware('can:manage_result_configuration')->name('comment-ranges.update');
    Route::delete('/comment-ranges/{commentRange}', [ResultConfigurationController::class, 'destroyCommentRange'])->middleware('can:manage_result_configuration')->name('comment-ranges.destroy');
    Route::put('/proprietor-comment-title', [ResultConfigurationController::class, 'proprietorTitle'])->middleware('can:manage_result_settings')->name('proprietor-comment-title.update');
    Route::post('/teacher-assignments', [ResultConfigurationController::class, 'assignTeacher'])->middleware('can:manage_result_settings')->name('teacher-assignments.store');
    Route::post('/card-defaults', [ResultCardController::class, 'defaultComment'])->name('card-defaults.store');
    Route::get('/cards/{card}', [ResultCardController::class, 'show'])->name('cards.show');
    Route::get('/cards/{card}/pdf', [ResultCardController::class, 'pdf'])->name('cards.pdf');
    Route::post('/cards/{card}/comments', [ResultCardController::class, 'comments'])->name('cards.comments');
    Route::get('/students/{student}/new', [ResultWorkflowController::class, 'newForStudent'])->middleware('can:enter_results')->name('new');
    Route::post('/drafts', [ResultWorkflowController::class, 'store'])->middleware('can:enter_results')->name('drafts.store');
    Route::post('/imports', [ResultWorkflowController::class, 'importCsv'])->middleware('can:enter_results')->name('imports.store');
    Route::get('/{result}', [ResultWorkflowController::class, 'show'])->name('show');
    Route::post('/{result}/draft', [ResultWorkflowController::class, 'updateDraft'])->middleware('can:enter_results')->name('drafts.update');
    Route::post('/{result}/submit', [ResultWorkflowController::class, 'submit'])->middleware('can:enter_results')->name('submit');
    Route::post('/{result}/approve', [ResultWorkflowController::class, 'approve'])->middleware('can:approve_results')->name('approve');
    Route::post('/{result}/reject', [ResultWorkflowController::class, 'reject'])->middleware('can:approve_results')->name('reject');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/logout', function (Request $request): RedirectResponse {
    return $request->user()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('logout.get');

<?php

use App\Modules\Academics\Http\Controllers\AcademicConfigurationController;
use App\Modules\Admissions\Http\Controllers\AdmissionController;
use App\Modules\Assignments\Http\Controllers\AssignmentController;
use App\Modules\Attendance\Http\Controllers\AttendanceController;
use App\Modules\CBT\Http\Controllers\ExamParticipantController;
use App\Modules\CBT\Http\Controllers\ExamStaffController;
use App\Modules\Core\Http\Controllers\AuthController;
use App\Modules\Core\Http\Controllers\DashboardController;
use App\Modules\Core\Http\Controllers\SchoolProfileController;
use App\Modules\ENotes\Http\Controllers\ENoteController;
use App\Modules\LandingPage\Http\Controllers\LandingPageController;
use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Payments\Http\Controllers\PaymentController;
use App\Modules\Payments\Http\Controllers\PaystackWebhookController;
use App\Modules\Portals\Http\Controllers\PortalPageController;
use App\Modules\Results\Http\Controllers\ResultCardController;
use App\Modules\Results\Http\Controllers\ResultConfigurationController;
use App\Modules\Results\Http\Controllers\ResultWorkflowController;
use App\Modules\StaffHR\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('home');
Route::post('/payments/paystack/webhook', PaystackWebhookController::class)->middleware('throttle:60,1')->name('payments.webhook');
Route::get('/payments/paystack/verify', [PaymentController::class, 'verify'])->middleware('throttle:30,1')->name('payments.verify');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/admissions/register', [AdmissionController::class, 'registration'])->name('admissions.register');
    Route::post('/admissions/register', [AdmissionController::class, 'register'])->middleware('throttle:5,1')->name('admissions.register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/my-invoices/{invoice}/paystack', [PaymentController::class, 'begin'])->name('payments.mine.begin');
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

Route::middleware(['auth', 'can:manage_cbt'])->prefix('cbt')->name('cbt.staff.')->group(function (): void {
    Route::get('/', [ExamStaffController::class, 'index'])->name('index');
    Route::post('/types', [ExamStaffController::class, 'type'])->name('types.store');
    Route::post('/exams', [ExamStaffController::class, 'exam'])->name('exams.store');
    Route::get('/exams/{exam}', [ExamStaffController::class, 'show'])->name('show');
    Route::post('/exams/{exam}/questions', [ExamStaffController::class, 'question'])->name('questions.store');
    Route::post('/exams/{exam}/publish', [ExamStaffController::class, 'publish'])->name('publish');
    Route::post('/exams/{exam}/approve', [ExamStaffController::class, 'approve'])->middleware('can:approve_cbt_results')->name('approve');
    Route::post('/exams/{exam}/release', [ExamStaffController::class, 'release'])->name('release');
});

Route::middleware(['auth', 'can:manage_admissions'])->prefix('admissions')->name('admissions.')->group(function (): void {
    Route::get('/', [AdmissionController::class, 'index'])->name('index');
    Route::post('/checklist-items', [AdmissionController::class, 'addChecklistItem'])->name('items.store');
    Route::get('/{candidate}', [AdmissionController::class, 'show'])->name('show');
    Route::post('/{candidate}/exam-taken', [AdmissionController::class, 'examTaken'])->name('exam-taken');
    Route::post('/{candidate}/exam-outcome', [AdmissionController::class, 'examOutcome'])->name('exam-outcome');
    Route::post('/{candidate}/checklist/{item}', [AdmissionController::class, 'checklist'])->name('checklist');
    Route::post('/{candidate}/offer', [AdmissionController::class, 'offer'])->name('offer');
    Route::post('/{candidate}/lapse', [AdmissionController::class, 'lapse'])->name('lapse');
    Route::post('/{candidate}/convert', [AdmissionController::class, 'convert'])->name('convert');
});

Route::middleware(['auth', 'can:manage_payments'])->prefix('payments')->name('payments.')->group(function (): void {
    Route::get('/', [PaymentController::class, 'index'])->name('index');
    Route::post('/invoices', [PaymentController::class, 'invoice'])->name('invoices.store');
    Route::post('/invoices/{invoice}/manual', [PaymentController::class, 'manual'])->name('manual');
    Route::post('/invoices/{invoice}/paystack', [PaymentController::class, 'begin'])->name('begin');
    Route::post('/expenses', [PaymentController::class, 'expense'])->name('expenses.store');
    Route::post('/result-access-rule', [PaymentController::class, 'rule'])->name('rule');
});

Route::middleware(['auth', 'can:manage_staff_records'])->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/', [StaffController::class, 'index'])->name('index');
    Route::post('/', [StaffController::class, 'store'])->name('store');
    Route::get('/{staff}', [StaffController::class, 'show'])->name('show');
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

Route::middleware(['auth', 'can:manage_academics'])->prefix('academics')->name('academics.')->group(function (): void {
    Route::get('/', [AcademicConfigurationController::class, 'index'])->name('index');
    Route::post('/levels', [AcademicConfigurationController::class, 'storeLevel'])->name('levels.store');
    Route::post('/sessions', [AcademicConfigurationController::class, 'storeSession'])->name('sessions.store');
    Route::post('/terms', [AcademicConfigurationController::class, 'storeTerm'])->name('terms.store');
    Route::post('/classes', [AcademicConfigurationController::class, 'storeClass'])->name('classes.store');
    Route::post('/arms', [AcademicConfigurationController::class, 'storeArm'])->name('arms.store');
    Route::post('/subjects', [AcademicConfigurationController::class, 'storeSubject'])->name('subjects.store');
    Route::post('/class-subjects', [AcademicConfigurationController::class, 'attachSubject'])->name('class-subjects.store');
});

Route::middleware(['auth', 'can:mark_attendance'])->prefix('attendance')->name('attendance.')->group(function (): void {
    Route::get('/', [AttendanceController::class, 'index'])->name('index');
    Route::get('/arms/{arm}', [AttendanceController::class, 'day'])->name('day');
    Route::post('/arms/{arm}/mark', [AttendanceController::class, 'mark'])->name('mark');
    Route::post('/arms/{arm}/lock', [AttendanceController::class, 'lock'])->middleware('can:manage_attendance')->name('lock');
    Route::post('/form-teachers', [AttendanceController::class, 'assign'])->middleware('can:manage_attendance')->name('form-teachers.store');
});
Route::get('/attendance/alerts', [AttendanceController::class, 'alerts'])->middleware(['auth', 'can:view_attendance_alerts'])->name('attendance.alerts');

Route::middleware(['auth', 'can:mark_assignments'])->prefix('assignments')->name('assignments.')->group(function (): void {
    Route::get('/', [AssignmentController::class, 'index'])->name('index');
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
});

Route::middleware(['auth', 'can:view_results_workspace'])->prefix('results')->name('results.')->group(function (): void {
    Route::get('/', [ResultWorkflowController::class, 'index'])->name('index');
    Route::get('/configuration', [ResultConfigurationController::class, 'index'])->middleware('can:manage_result_settings')->name('configuration');
    Route::post('/components', [ResultConfigurationController::class, 'component'])->middleware('can:manage_result_settings')->name('components.store');
    Route::post('/schemes', [ResultConfigurationController::class, 'scheme'])->middleware('can:manage_result_settings')->name('schemes.store');
    Route::post('/bands', [ResultConfigurationController::class, 'band'])->middleware('can:manage_result_settings')->name('bands.store');
    Route::post('/templates', [ResultConfigurationController::class, 'template'])->middleware('can:manage_result_settings')->name('templates.store');
    Route::post('/templates/{template}/subject-remarks', [ResultConfigurationController::class, 'subjectRemarks'])->middleware('can:manage_result_settings')->name('templates.subject-remarks');
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

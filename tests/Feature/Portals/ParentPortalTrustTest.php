<?php

namespace Tests\Feature\Portals;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Results\Models\ResultCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParentPortalTrustTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_cannot_switch_to_unlinked_child_or_guess_card_invoice_or_notification(): void
    {
        [$parent, $own, $other, $card, $invoice, $otherNotice] = $this->fixture();
        $this->actingAs($parent);
        foreach (['dashboard', 'results.mine.index', 'attendance.mine', 'assignments.parent', 'payments.mine.index', 'profile.mine', 'notifications.index'] as $route) {
            $this->get(route($route, ['student' => $own->id]))->assertOk()->assertSee('Jane Obi');
        }
        foreach (['dashboard', 'results.mine.index', 'attendance.mine', 'assignments.parent', 'payments.mine.index', 'profile.mine', 'notifications.index'] as $route) {
            $this->get(route($route, ['student' => $other->id]))->assertForbidden();
        }
        $this->get(route('results.mine.show', ['card' => $card->id, 'student' => $own->id]))->assertForbidden();
        $this->get(route('results.mine.pdf', ['card' => $card->id, 'student' => $own->id]))->assertForbidden();
        $this->post(route('notifications.read', ['notification' => $otherNotice->id, 'student' => $own->id]))->assertNotFound();

        config()->set('services.paystack.secret_key', 'sk_test_local_fake_only');
        $this->post(route('payments.mine.begin', ['invoice' => $invoice->id, 'student' => $own->id]))
            ->assertSessionHasErrors('payment');
        app(TenantContext::class)->set($parent->school_id);
        $this->assertSame(0, PaymentAttempt::count());
    }

    public function test_locked_parent_pages_render_no_attendance_percentage_or_result_data(): void
    {
        [$parent, $own] = $this->fixture();
        $this->actingAs($parent);
        $this->get(route('attendance.mine', ['student' => $own->id]))->assertOk()
            ->assertSee('Attendance is locked')->assertDontSee('73%')->assertDontSee('Present');
        $this->get(route('dashboard', ['student' => $own->id]))->assertOk()
            ->assertSee('Locked')->assertDontSee('73%');
    }

    public function test_paystack_return_without_provider_success_does_not_settle_and_returns_to_parent_fees(): void
    {
        [$parent, $own] = $this->fixture();
        $invoice = Invoice::where('student_id', $own->id)->firstOrFail();
        config()->set('services.paystack.secret_key', 'sk_test_local_fake_only');
        $attempt = PaymentAttempt::create(['invoice_id' => $invoice->id, 'reference' => 'oak-test-redirect',
            'amount_kobo' => $invoice->amount_kobo, 'initiated_by' => $parent->id, 'status' => 'Pending']);
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true,
            'data' => ['status' => 'pending', 'reference' => $attempt->reference]])]);

        $this->actingAs($parent)->get(route('payments.verify', ['reference' => $attempt->reference]))
            ->assertRedirect(route('payments.mine.index', ['student' => $own->id]));
        app(TenantContext::class)->set($parent->school_id);
        $this->assertSame('Unpaid', $invoice->fresh()->status);
        $this->assertSame('Pending', $attempt->fresh()->status);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $parent = User::create(['name' => 'Parent', 'email' => 'parent-trust@example.test', 'password' => 'long-password-here']);
        $ownUser = User::create(['name' => 'Jane Obi', 'email' => 'jane-trust@example.test', 'password' => 'long-password-here']);
        $otherUser = User::create(['name' => 'Other Child', 'email' => 'other-trust@example.test', 'password' => 'long-password-here']);
        $staff = User::create(['name' => 'Staff', 'email' => 'staff-trust@example.test', 'password' => 'long-password-here']);
        $level = AcademicLevel::create(['name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $own = Student::create(['user_id' => $ownUser->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        $other = Student::create(['user_id' => $otherUser->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        $profile = ParentProfile::create(['user_id' => $parent->id]);
        ParentStudent::create(['parent_id' => $profile->id, 'student_id' => $own->id]);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $card = ResultCard::create(['student_id' => $other->id, 'term_id' => $term->id, 'class_arm_id' => $arm->id, 'compiled_at' => now()]);
        Invoice::create(['student_id' => $own->id, 'term_id' => $term->id, 'purpose' => 'Tuition', 'amount_kobo' => 4500000, 'due_date' => today(), 'status' => 'Unpaid', 'created_by' => $staff->id]);
        $invoice = Invoice::create(['student_id' => $other->id, 'term_id' => $term->id, 'purpose' => 'Tuition', 'amount_kobo' => 4500000, 'due_date' => today(), 'status' => 'Unpaid', 'created_by' => $staff->id]);
        AttendanceRecord::create(['student_id' => $own->id, 'class_arm_id' => $arm->id, 'date' => today(), 'status' => 'Present', 'marked_by' => $staff->id, 'locked' => false]);
        $otherNotice = Notification::create(['user_id' => $otherUser->id, 'channel' => 'In App', 'title' => 'Private', 'body' => 'Other child notice']);

        return [$parent, $own, $other, $card, $invoice, $otherNotice];
    }
}

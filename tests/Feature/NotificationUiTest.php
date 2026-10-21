<?php

namespace Tests\Feature;

use App\Auth\SheetUserProvider;
use App\Filament\Portal\Pages\NotifikasiPage;
use App\Livewire\NotificationBell;
use App\Repositories\UserRepository;
use App\Support\NotificationCenter;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class NotificationUiTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-22 09:00:00'));
        $this->setUpSheets();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownSheets();

        parent::tearDown();
    }

    public function test_admin_notification_page_and_bell_render(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAsAdmin();

        $this->get('/admin/notifikasi-page')
            ->assertOk()
            ->assertSee('Notifikasi')
            ->assertSee('Jadwal Hari Ini')
            ->assertSee('Tugas Aktif')
            ->assertSee('Proposal Penelitian Kelompok')
            ->assertSee('Studi Kasus Psikologi Sosial');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('aria-label="Notifikasi"', false)
            ->assertSee('fi-modal-slide-over', false)
            ->assertSee('notification-panel', false)
            ->assertSee('Lihat semua notifikasi');
    }

    public function test_student_notification_page_and_bell_render(): void
    {
        Filament::setCurrentPanel('portal');
        $this->actingAsStudent();

        $this->get('/portal/notifikasi-page')
            ->assertOk()
            ->assertSee('Jadwal Hari Ini')
            ->assertSee('Metodologi Penelitian')
            ->assertSee('Tugas Aktif')
            ->assertSee('Refleksi Psikologi Umum')
            ->assertDontSee('Studi Kasus Psikologi Sosial');

        $this->get('/portal')
            ->assertOk()
            ->assertSee('aria-label="Notifikasi"', false)
            ->assertSee('fi-modal-slide-over', false)
            ->assertSee('notification-panel', false)
            ->assertSee('Lihat semua notifikasi');
    }

    public function test_bell_marks_all_notifications_as_read(): void
    {
        Filament::setCurrentPanel('portal');
        $student = $this->actingAsStudent();

        Livewire::test(NotificationBell::class)
            ->assertSee('fi-modal-slide-over', false)
            ->assertSee('Notifikasi')
            ->assertSee('Refleksi Psikologi Umum')
            ->assertSee('belum dibaca')
            ->call('markAllAsRead')
            ->assertSee('Semua sudah dibaca');

        $this->assertSame(0, app(NotificationCenter::class)->unreadCount($student));
    }

    public function test_bell_marks_a_notification_as_read_before_opening_it(): void
    {
        Filament::setCurrentPanel('portal');
        $student = $this->actingAsStudent();
        $notification = app(NotificationCenter::class)
            ->forUser($student)
            ->firstWhere('type', 'task');

        $this->assertNotNull($notification);

        Livewire::test(NotificationBell::class)
            ->call('open', $notification['key'])
            ->assertRedirect($notification['url']);

        $this->assertTrue(
            app(NotificationCenter::class)
                ->forUser($student)
                ->firstWhere('key', $notification['key'])['read'],
        );
    }

    public function test_unread_filter_becomes_empty_after_marking_all_as_read(): void
    {
        Filament::setCurrentPanel('portal');
        $this->actingAsStudent();

        Livewire::test(NotifikasiPage::class)
            ->call('setFilter', 'unread')
            ->assertSee('Refleksi Psikologi Umum')
            ->call('markAllAsRead')
            ->assertSee('Semua notifikasi sudah dibaca')
            ->assertDontSee('Refleksi Psikologi Umum');
    }

    protected function actingAsStudent(): array
    {
        return $this->actingAsStudentWithEmail('ayu@psikologi.test');
    }

    protected function actingAsAdmin(): void
    {
        $this->actingAsStudentWithEmail('admin@psikologi.test');
    }

    /**
     * @return array<string, mixed>
     */
    protected function actingAsStudentWithEmail(string $email): array
    {
        $data = app(UserRepository::class)->findActiveByLogin($email);

        $this->assertNotNull($data);

        $user = (new SheetUserProvider(app(UserRepository::class)))->retrieveById((string) $data['id']);

        $this->assertNotNull($user);

        $this->actingAs($user);

        return $data;
    }
}

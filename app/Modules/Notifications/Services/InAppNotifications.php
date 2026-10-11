<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Core\Models\User;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\NotificationLog;
use Illuminate\Support\Facades\DB;

class InAppNotifications
{
    public function sendForStudent(int $studentId, int $studentUserId, string $studentName, string $title, string $body): void
    {
        $this->send([$studentUserId], $title, $body);
        $firstName = preg_split('/\s+/u', trim($studentName))[0] ?? 'your child';
        $this->send(app(ParentAccountDirectory::class)->userIdsForStudents([$studentId]),
            $title, 'For '.$firstName.': '.$body);
    }

    public function send(array $userIds, string $title, string $body): void
    {
        foreach (array_values(array_unique(array_map('intval', $userIds))) as $userId) {
            DB::transaction(function () use ($userId, $title, $body): void {
                $notification = Notification::create([
                    'user_id' => $userId,
                    'channel' => 'In App',
                    'title' => $title,
                    'body' => $body,
                ]);
                NotificationLog::create([
                    'notification_id' => $notification->id,
                    'status' => 'Sent',
                    'created_at' => now(),
                ]);
            });
        }
    }

    public function inbox(User $user)
    {
        return Notification::where('user_id', $user->id)->latest()->paginate(20);
    }

    public function unreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)->whereNull('read_at')->count();
    }

    public function latestUnread(User $user, int $limit = 5)
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->latest()
            ->limit(min(max($limit, 1), 5))
            ->get();
    }

    public function markRead(int $notificationId, User $user): void
    {
        Notification::whereKey($notificationId)->where('user_id', $user->id)->firstOrFail()
            ->update(['read_at' => now()]);
    }

    public function markAllRead(User $user): void
    {
        Notification::where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
    }
}

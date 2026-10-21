<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Core\Models\User;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\NotificationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InAppNotifications
{
    public function sendForStudent(int $studentId, int $studentUserId, string $studentName, string $title, string $body, ?string $url = null): void
    {
        $this->send([$studentUserId], $title, $body, $url);
        $firstName = preg_split('/\s+/u', trim($studentName))[0] ?? 'your child';
        $this->send(app(ParentAccountDirectory::class)->userIdsForStudents([$studentId]),
            $title, 'For '.$firstName.': '.$body, $url);
    }

    public function send(array $userIds, string $title, string $body, ?string $url = null): void
    {
        $url = $this->validatedUrl($url);
        foreach (array_values(array_unique(array_map('intval', $userIds))) as $userId) {
            DB::transaction(function () use ($userId, $title, $body, $url): void {
                $notification = Notification::create([
                    'user_id' => $userId,
                    'channel' => 'In App',
                    'title' => $title,
                    'body' => $body,
                    'url' => $url,
                ]);
                NotificationLog::create([
                    'notification_id' => $notification->id,
                    'status' => 'Sent',
                    'created_at' => now(),
                ]);
            });
        }
    }

    private function validatedUrl(?string $url): ?string
    {
        $data = Validator::make(['url' => $url], [
            'url' => [
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)
                        || ! str_starts_with($value, '/')
                        || str_starts_with($value, '//')
                        || str_contains($value, '//')
                        || str_contains($value, '\\')
                        || preg_match('/[\x00-\x1F\x7F]/', $value)
                        || preg_match('/[a-z][a-z0-9+.-]*:/i', $value)) {
                        $fail('The notification link must be a safe path within this site.');
                    }
                },
            ],
        ])->validate();

        return $data['url'] ?? null;
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

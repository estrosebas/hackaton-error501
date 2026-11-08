<?php

namespace App\Services;

use App\Models\MapEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmpathyRewards
{
    public function process(int $attendanceId): bool
    {
        return DB::transaction(function () use ($attendanceId) {
            $eventId = DB::table('map_event_attendances')->where('id', $attendanceId)->value('map_event_id');
            $event = MapEvent::whereKey($eventId)->lockForUpdate()->first();
            $attendance = DB::table('map_event_attendances')->where('id', $attendanceId)->lockForUpdate()->first();
            if (! $attendance || ! $attendance->verified_at || $attendance->processed_at) {
                return false;
            }
            $verified = Carbon::parse($attendance->verified_at);
            if (! $event || $event->user_id === $attendance->user_id || $event->cancelled_at || ! is_numeric($attendance->distance_meters) || $attendance->distance_meters < 0 || $attendance->distance_meters > 100 || $verified->isFuture() || $verified->lt(Carbon::parse($attendance->joined_at)) || ! $verified->betweenIncluded($event->starts_at, $event->ends_at->copy()->addMinute())) {
                return false;
            }
            DB::table('empathy_rewards')->insertOrIgnore([
                'map_event_id' => $event->id, 'user_id' => $attendance->user_id,
                'points' => $event->points, 'awarded_at' => now(),
            ]);
            DB::table('map_event_attendances')->where('id', $attendanceId)->update(['processed_at' => now()]);

            return true;
        });
    }
}

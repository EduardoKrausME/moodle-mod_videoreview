<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_videoreview;

/**
 * Maintains server-authoritative watched intervals for each reviewed video.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_manager {
    /**
     * Saves a progress update and merges watched intervals.
     *
     * @param int $activityid Activity id.
     * @param int $submissionid Submission id, or zero for the common video.
     * @param int $userid User id.
     * @param float $duration Video duration.
     * @param float $position Current position.
     * @param array $segments Watched intervals.
     * @return object Progress record.
     */
    public static function save(
        int $activityid,
        int $submissionid,
        int $userid,
        float $duration,
        float $position,
        array $segments
    ): object {
        global $DB;

        $params = [
            'videoreviewid' => $activityid,
            'submissionid' => $submissionid,
            'userid' => $userid,
        ];
        $record = $DB->get_record('videoreview_progress', $params);
        $existing = $record ? (json_decode((string)$record->watchedsegments, true) ?: []) : [];
        $duration = max(0.0, $duration);
        $position = max(0.0, $position);
        $incoming = self::clean_segments($segments, $duration);
        $merged = self::merge_segments(array_merge($existing, $incoming));
        $unique = self::duration($merged);
        $reported = self::duration($incoming);
        $percent = $duration > 0 ? min(100.0, ($unique / $duration) * 100) : 0.0;
        $now = time();

        if (!$record) {
            $record = (object)($params + [
                    'duration' => $duration,
                    'lastposition' => $position,
                    'uniquewatched' => $unique,
                    'totalwatchtime' => $reported,
                    'percent' => $percent,
                    'watchedsegments' => json_encode($merged),
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            $record->id = $DB->insert_record('videoreview_progress', $record);
        } else {
            $record->duration = max((float)$record->duration, $duration);
            $record->lastposition = $position;
            $record->uniquewatched = $unique;
            $record->totalwatchtime = (float)$record->totalwatchtime + $reported;
            $record->percent = $record->duration > 0 ? min(100.0, ($unique / $record->duration) * 100) : 0.0;
            $record->watchedsegments = json_encode($merged);
            $record->timemodified = $now;
            $DB->update_record('videoreview_progress', $record);
        }
        return $record;
    }

    /**
     * Cleans client-provided intervals.
     *
     * @param array $segments Raw intervals.
     * @param float $duration Video duration.
     * @return array
     */
    private static function clean_segments(array $segments, float $duration): array {
        $result = [];
        foreach ($segments as $segment) {
            if (!is_array($segment) || count($segment) < 2 || !is_numeric($segment[0]) || !is_numeric($segment[1])) {
                continue;
            }
            $start = max(0.0, (float)$segment[0]);
            $end = max(0.0, (float)$segment[1]);
            if ($duration > 0) {
                $start = min($start, $duration);
                $end = min($end, $duration);
            }
            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }
            if ($end - $start <= 0.05 || $end - $start > 30.0) {
                continue;
            }
            $result[] = [round($start, 3), round($end, 3)];
        }
        return $result;
    }

    /**
     * Merges overlapping and adjacent intervals.
     *
     * @param array $segments Intervals.
     * @return array
     */
    private static function merge_segments(array $segments): array {
        if (!$segments) {
            return [];
        }
        usort($segments, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($segments as $segment) {
            if (!$merged) {
                $merged[] = $segment;
                continue;
            }
            $index = count($merged) - 1;
            if ($segment[0] <= $merged[$index][1] + 0.5) {
                $merged[$index][1] = max($merged[$index][1], $segment[1]);
            } else {
                $merged[] = $segment;
            }
        }
        return $merged;
    }

    /**
     * Returns total seconds covered by intervals.
     *
     * @param array $segments Intervals.
     * @return float
     */
    private static function duration(array $segments): float {
        $seconds = 0.0;
        foreach ($segments as $segment) {
            $seconds += max(0.0, (float)$segment[1] - (float)$segment[0]);
        }
        return $seconds;
    }
}

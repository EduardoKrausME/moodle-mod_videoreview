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

use context_module;
use moodle_url;

/**
 * Resolves activity and submission videos into a common player configuration.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class player {
    /**
     * Builds the player configuration for a common activity video.
     *
     * @param object $activity Activity record.
     * @param context_module $context Module context.
     * @return array
     */
    public static function for_activity(object $activity, context_module $context): array {
        if ($activity->commonvideosource === 'upload') {
            $url = self::file_url($context, 'commonvideo', 0);
            return self::from_url($url ?: '');
        }
        return self::from_url((string)$activity->commonvideourl);
    }

    /**
     * Builds the player configuration for a student submission.
     *
     * @param object $submission Submission record.
     * @param context_module $context Module context.
     * @return array
     */
    public static function for_submission(object $submission, context_module $context): array {
        if ($submission->videosource === 'upload') {
            $url = self::file_url($context, 'submissionvideo', (int)$submission->id);
            return self::from_url($url ?: '');
        }
        return self::from_url((string)$submission->videourl);
    }

    /**
     * Creates a client player definition from a URL.
     *
     * @param string $url Video URL.
     * @return array
     */
    public static function from_url(string $url): array {
        $url = trim($url);
        if ($url === '') {
            return ['type' => 'missing', 'url' => '', 'videoid' => ''];
        }

        $youtubeid = self::youtube_id($url);
        if ($youtubeid !== '') {
            return [
                'type' => 'youtube',
                'url' => $url,
                'videoid' => $youtubeid,
                'embedurl' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($youtubeid) . '?enablejsapi=1&rel=0',
            ];
        }

        $vimeoid = self::vimeo_id($url);
        if ($vimeoid !== '') {
            return [
                'type' => 'vimeo',
                'url' => $url,
                'videoid' => $vimeoid,
                'embedurl' => 'https://player.vimeo.com/video/' . rawurlencode($vimeoid) . '?api=1',
            ];
        }

        return ['type' => 'html5', 'url' => $url, 'videoid' => ''];
    }

    /**
     * Returns the first stored file in a plugin file area.
     *
     * @param context_module $context Module context.
     * @param string $filearea File area.
     * @param int $itemid Item id.
     * @return string|null
     */
    private static function file_url(context_module $context, string $filearea, int $itemid): ?string {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_videoreview', $filearea, $itemid, 'id', false);
        if (!$files) {
            return null;
        }
        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $context->id,
            'mod_videoreview',
            $filearea,
            $itemid,
            $file->get_filepath(),
            $file->get_filename(),
            false
        )->out(false);
    }

    /**
     * Extracts a YouTube video identifier.
     *
     * @param string $url URL.
     * @return string
     */
    private static function youtube_id(string $url): string {
        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{6,})~',
            '~youtube(?:-nocookie)?\.com/(?:watch\?.*?v=|embed/|shorts/)([A-Za-z0-9_-]{6,})~',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        return '';
    }

    /**
     * Extracts a Vimeo video identifier.
     *
     * @param string $url URL.
     * @return string
     */
    private static function vimeo_id(string $url): string {
        if (preg_match('~vimeo\.com/(?:video/)?([0-9]+)~', $url, $matches)) {
            return $matches[1];
        }
        return '';
    }
}

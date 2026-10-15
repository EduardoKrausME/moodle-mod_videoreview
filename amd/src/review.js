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

/**
 * review.js
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification', 'core/templates'], function (Ajax, Notification, Templates) {
    const youtubeReady = () => {
        if (window.YT && window.YT.Player) {
            return Promise.resolve();
        }
        if (window.videoreviewYoutubePromise) {
            return window.videoreviewYoutubePromise;
        }
        window.videoreviewYoutubePromise = new Promise((resolve) => {
            const previous = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = () => {
                if (typeof previous === 'function') {
                    previous();
                }
                resolve();
            };
            if (!document.querySelector('script[data-videoreview-youtube-api]')) {
                const script = document.createElement('script');
                script.src = 'https://www.youtube.com/iframe_api';
                script.dataset.videoreviewYoutubeApi = '1';
                document.head.appendChild(script);
            }
        });
        return window.videoreviewYoutubePromise;
    };

    class PlayerAdapter {
        constructor(root, config) {
            this.root = root;
            this.config = config;
            this.type = config.player.type;
            this.current = 0;
            this.duration = 0;
            this.youtube = null;
            this.vimeo = null;
            this.ready = this.initialise();
        }

        initialise() {
            if (this.type === 'html5') {
                const video = this.root.querySelector('[data-region="html5-player"]');
                if (!video) {
                    return Promise.resolve();
                }
                this.video = video;
                const update = () => {
                    this.current = Number(video.currentTime || 0);
                    this.duration = Number(video.duration || 0);
                };
                video.addEventListener('timeupdate', update);
                video.addEventListener('durationchange', update);
                return new Promise((resolve) => {
                    if (video.readyState >= 1) {
                        update();
                        resolve();
                    } else {
                        video.addEventListener('loadedmetadata', () => {
                            update();
                            resolve();
                        }, {once: true});
                    }
                });
            }

            if (this.type === 'youtube') {
                const target = this.root.querySelector('[data-region="youtube-player"]');
                if (!target) {
                    return Promise.resolve();
                }
                return youtubeReady().then(() => new Promise((resolve) => {
                    this.youtube = new window.YT.Player(target, {
                        videoId: target.dataset.videoId,
                        playerVars: {rel: 0},
                        events: {
                            onReady: () => {
                                this.current = Number(this.youtube.getCurrentTime() || 0);
                                this.duration = Number(this.youtube.getDuration() || 0);
                                resolve();
                            }
                        }
                    });
                }));
            }

            if (this.type === 'vimeo') {
                const iframe = this.root.querySelector('[data-region="vimeo-player"]');
                if (!iframe) {
                    return Promise.resolve();
                }
                this.vimeo = iframe;
                window.addEventListener('message', (event) => {
                    if (event.source !== iframe.contentWindow) {
                        return;
                    }
                    let data = event.data;
                    if (typeof data === 'string') {
                        try {
                            data = JSON.parse(data);
                        } catch (error) {
                            return;
                        }
                    }
                    if (!data || typeof data !== 'object') {
                        return;
                    }
                    if (data.event === 'timeupdate' && data.data) {
                        this.current = Number(data.data.seconds || this.current || 0);
                        this.duration = Number(data.data.duration || this.duration || 0);
                    } else if (data.method === 'getCurrentTime') {
                        this.current = Number(data.value || 0);
                    } else if (data.method === 'getDuration') {
                        this.duration = Number(data.value || 0);
                    }
                });
                return new Promise((resolve) => {
                    const initialiseVimeo = () => {
                        this.postVimeo({method: 'addEventListener', value: 'timeupdate'});
                        this.postVimeo({method: 'getDuration'});
                        this.postVimeo({method: 'getCurrentTime'});
                        resolve();
                    };
                    iframe.addEventListener('load', initialiseVimeo, {once: true});
                    window.setTimeout(initialiseVimeo, 1500);
                });
            }
            return Promise.resolve();
        }

        postVimeo(message) {
            if (this.vimeo && this.vimeo.contentWindow) {
                this.vimeo.contentWindow.postMessage(JSON.stringify(message), '*');
            }
        }

        getCurrentTime() {
            if (this.video) {
                return Number(this.video.currentTime || 0);
            }
            if (this.youtube && typeof this.youtube.getCurrentTime === 'function') {
                this.current = Number(this.youtube.getCurrentTime() || 0);
            }
            if (this.vimeo) {
                this.postVimeo({method: 'getCurrentTime'});
            }
            return this.current;
        }

        getDuration() {
            if (this.video) {
                return Number(this.video.duration || 0);
            }
            if (this.youtube && typeof this.youtube.getDuration === 'function') {
                this.duration = Number(this.youtube.getDuration() || 0);
            }
            if (this.vimeo) {
                this.postVimeo({method: 'getDuration'});
            }
            return this.duration;
        }

        seek(seconds) {
            const position = Math.max(0, Number(seconds || 0));
            if (this.video) {
                this.video.currentTime = position;
                return;
            }
            if (this.youtube && typeof this.youtube.seekTo === 'function') {
                this.youtube.seekTo(position, true);
                return;
            }
            if (this.vimeo) {
                this.postVimeo({method: 'setCurrentTime', value: position});
                this.postVimeo({method: 'seekTo', value: position});
            }
        }
    }

    class ReviewWorkspace {
        constructor(root) {
            this.root = root;
            this.config = JSON.parse(root.dataset.config || '{}');
            this.player = new PlayerAdapter(root, this.config);
            this.lastPosition = null;
            this.pendingSegments = [];
            this.flushing = false;
            this.bindComments();
            this.player.ready.then(() => {
                if (Number(this.config.lastposition || 0) > 1) {
                    this.player.seek(Number(this.config.lastposition));
                }
                this.updateInitialProgress();
                this.updateCommentTimeline();
                window.setTimeout(() => this.updateCommentTimeline(), 1000);
                window.setTimeout(() => this.updateCommentTimeline(), 2500);
                if (this.config.track !== false) {
                    this.startTracking();
                }
            }).catch(Notification.exception);
        }

        startTracking() {
            this.tickTimer = window.setInterval(() => this.tick(), 1000);
            this.flushTimer = window.setInterval(() => this.flush(), 10000);
            window.addEventListener('pagehide', () => this.flush());
        }

        tick() {
            const current = Number(this.player.getCurrentTime() || 0);
            const duration = Number(this.player.getDuration() || 0);
            if (this.lastPosition !== null) {
                const delta = current - this.lastPosition;
                if (delta > 0.05 && delta <= 2.5 && current <= duration + 1) {
                    this.pendingSegments.push([this.lastPosition, current]);
                }
            }
            this.lastPosition = current;
        }

        flush() {
            if (this.flushing || !this.pendingSegments.length) {
                return;
            }
            const segments = this.pendingSegments.splice(0, this.pendingSegments.length);
            this.flushing = true;
            Ajax.call([{
                methodname: 'mod_videoreview_save_progress',
                args: {
                    cmid: Number(this.config.cmid),
                    submissionid: Number(this.config.submissionid || 0),
                    duration: Number(this.player.getDuration() || 0),
                    position: Number(this.player.getCurrentTime() || 0),
                    segmentsjson: JSON.stringify(segments)
                }
            }])[0].then((response) => {
                this.updateProgress(Number(response.percent || 0));
                this.flushing = false;
            }).catch((error) => {
                this.pendingSegments = segments.concat(this.pendingSegments);
                this.flushing = false;
                Notification.exception(error);
            });
        }

        updateInitialProgress() {
            const duration = Number(this.player.getDuration() || 0);
            if (duration <= 0 || !Array.isArray(this.config.segments)) {
                return;
            }
            let watched = 0;
            this.config.segments.forEach((segment) => {
                if (Array.isArray(segment) && segment.length >= 2) {
                    watched += Math.max(0, Number(segment[1]) - Number(segment[0]));
                }
            });
            this.updateProgress(Math.min(100, watched / duration * 100));
        }

        updateProgress(percent) {
            const rounded = Math.max(0, Math.min(100, Math.round(percent)));
            const label = this.root.querySelector('[data-region="progress-percent"]');
            const bar = this.root.querySelector('[data-region="progress-bar"]');
            if (label) {
                label.textContent = rounded + '%';
            }
            if (bar) {
                bar.style.width = rounded + '%';
                if (bar.parentElement) {
                    bar.parentElement.setAttribute('aria-valuenow', String(rounded));
                }
            }
        }

        bindComments() {
            const form = this.root.querySelector('[data-region="comment-form"]');
            if (form) {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    this.saveComment();
                });
            }
            this.root.addEventListener('click', (event) => {
                const seek = event.target.closest('[data-action="seek"]');
                if (seek) {
                    this.player.ready.then(() => this.player.seek(Number(seek.dataset.time || 0)));
                    return;
                }
                const remove = event.target.closest('[data-action="delete-comment"]');
                if (remove) {
                    this.deleteComment(remove);
                }
            });
        }

        updateCommentTimeline() {
            const timeline = this.root.querySelector('[data-region="comment-timeline"]');
            const duration = Number(this.player.getDuration() || 0);
            if (!timeline || duration <= 0) {
                return;
            }
            timeline.querySelectorAll('[data-comment-marker-id]').forEach((marker) => {
                const time = Math.max(0, Number(marker.dataset.time || 0));
                marker.style.left = Math.min(100, time / duration * 100) + '%';
            });
        }

        addTimelineMarker(id, timecode, label) {
            const timeline = this.root.querySelector('[data-region="comment-timeline"]');
            if (!timeline) {
                return;
            }
            const marker = document.createElement('button');
            marker.type = 'button';
            marker.className = 'videoreview-comment-marker';
            marker.dataset.action = 'seek';
            marker.dataset.time = String(timecode);
            marker.dataset.commentMarkerId = String(id);
            marker.title = label;
            marker.setAttribute('aria-label', label);
            timeline.appendChild(marker);
            this.updateCommentTimeline();
        }

        saveComment() {
            const input = this.root.querySelector('[data-region="comment-input"]');
            if (!input || !input.value.trim()) {
                return;
            }
            const comment = input.value.trim();
            const timecode = Number(this.player.getCurrentTime() || 0);
            Ajax.call([{
                methodname: 'mod_videoreview_save_comment',
                args: {
                    cmid: Number(this.config.cmid),
                    submissionid: Number(this.config.submissionid || 0),
                    timecode: timecode,
                    comment: comment
                }
            }])[0].then((response) => {
                const seconds = Math.max(0, Math.round(Number(response.timecode || 0)));
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const remainder = seconds % 60;
                const formatted = (hours ? String(hours).padStart(2, '0') + ':' : '') +
                    String(minutes).padStart(2, '0') + ':' + String(remainder).padStart(2, '0');
                return Templates.renderForPromise('mod_videoreview/comment', {
                    id: response.id,
                    timecode: response.timecode,
                    timeformatted: formatted,
                    comment: response.comment
                }).then((rendered) => Object.assign(rendered, {
                    commentId: response.id,
                    timecode: response.timecode,
                    timeformatted: formatted
                }));
            }).then(({html, js, commentId, timecode, timeformatted}) => {
                const list = this.root.querySelector('[data-region="comments"]');
                Templates.appendNodeContents(list, html, js);
                this.addTimelineMarker(commentId, timecode, timeformatted);
                const empty = this.root.querySelector('[data-region="no-comments"]');
                if (empty) {
                    empty.remove();
                }
                input.value = '';
            }).catch(Notification.exception);
        }

        deleteComment(button) {
            const commentId = Number(button.dataset.commentId || 0);
            if (!commentId) {
                return;
            }
            Ajax.call([{
                methodname: 'mod_videoreview_delete_comment',
                args: {cmid: Number(this.config.cmid), commentid: commentId}
            }])[0].then(() => {
                const row = button.closest('[data-comment-id]');
                if (row) {
                    row.remove();
                }
                const marker = this.root.querySelector('[data-comment-marker-id="' + commentId + '"]');
                if (marker) {
                    marker.remove();
                }
            }).catch(Notification.exception);
        }
    }

    const init = () => {
        document.querySelectorAll('[data-region="videoreview-review"]').forEach((root) => {
            try {
                new ReviewWorkspace(root);
            } catch (error) {
                Notification.exception(error);
            }
        });
    };

    return {init: init};
});

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

/**
 * English strings for Video Peer Review.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcomment'] = 'Comment at this moment';
$string['addcriterion'] = 'Add criterion';
$string['allocations'] = 'Peer-review allocations';
$string['allocationscreated'] = '{$a} peer-review allocations were created.';
$string['allocationshelp'] = 'Balanced cyclic allocation. Configured reviews per student:';
$string['allocationstatusassigned'] = 'Assigned';
$string['allocationstatuscompleted'] = 'Completed';
$string['anonymousreviewer'] = 'Anonymous reviewer {$a}';
$string['anonymousreviews'] = 'Anonymous peer reviews';
$string['anonymousreviews_help'] = 'When enabled, students do not see which peer wrote a received review. Teachers with report access can still see reviewer identities.';
$string['assessmentcriteria'] = 'Assessment criteria';
$string['author'] = 'Author';
$string['average'] = 'Average';
$string['averagescore'] = 'Average received score';
$string['averagewatch'] = 'Average viewed percentage';
$string['comment'] = 'Comment';
$string['commentplaceholder'] = 'Write feedback about this moment in the video...';
$string['comments'] = 'Comments';
$string['commenttimeline'] = 'Comment timeline';
$string['commonvideo'] = 'Common video';
$string['commonvideoevaluation'] = 'Evaluate the common video';
$string['commonvideoevaluationhelp'] = 'Watch the video, add timestamped comments, and complete the configured assessment criteria.';
$string['commonvideorequired'] = 'Upload the common video.';
$string['completiondetail:reviews'] = 'Complete all required video reviews';
$string['completiondetail:submission'] = 'Submit a video';
$string['completionrequirereviews'] = 'Student must complete the required reviews';
$string['completionrequiresubmission'] = 'Student must submit a video';
$string['conceptoptions'] = 'Concept options';
$string['conceptoptions_help'] = 'Enter one concept per line as Label|score. Example: Excellent|10';
$string['criteria'] = 'Assessment criteria';
$string['criteriahelp'] = 'Create the criteria reviewers will use. Criteria can use a numeric score or a concept mapped to a numeric score.';
$string['criterion'] = 'Criterion';
$string['criteriondeleted'] = 'Criterion deleted.';
$string['criteriondescription'] = 'Description';
$string['criterionfeedback'] = 'Feedback for this criterion';
$string['criterionname'] = 'Criterion name';
$string['criterionrequired'] = 'A value is required for criterion: {$a}';
$string['criterionrequiredlabel'] = 'Required';
$string['criterionsaved'] = 'Criterion saved.';
$string['criterionstatistics'] = 'Criterion statistics';
$string['criteriontype'] = 'Criterion type';
$string['criteriontypeconcept'] = 'Concept';
$string['criteriontypescore'] = 'Numeric score';
$string['deletecomment'] = 'Delete';
$string['editcriterion'] = 'Edit criterion';
$string['editsubmission'] = 'Edit submission';
$string['finalgrade'] = 'Gradebook grade';
$string['invalidconcept'] = 'Invalid concept selected.';
$string['invalidconceptoptions'] = 'Enter at least two valid concept options using Label|score.';
$string['invalidmaxscore'] = 'Maximum score must be greater than zero.';
$string['invalidpeerreviewcount'] = 'Enter a value from 0 to 50.';
$string['invalidscore'] = 'Invalid criterion score.';
$string['invalidsubmission'] = 'Invalid video submission.';
$string['manageallocations'] = 'Manage allocations';
$string['managecriteria'] = 'Manage criteria';
$string['management'] = 'Teacher management';
$string['maxscore'] = 'Maximum score';
$string['modified'] = 'Last modified';
$string['modulename'] = 'Video Peer Review';
$string['modulenameplural'] = 'Video Peer Reviews';
$string['mysubmission'] = 'My video submission';
$string['noactivities'] = 'There are no Video Peer Review activities in this course.';
$string['noallocations'] = 'No peer-review allocations exist yet. At least two students must have submitted videos.';
$string['nocommentsyet'] = 'No timestamped comments yet.';
$string['nocriteria'] = 'No assessment criteria have been configured yet.';
$string['nodata'] = 'No data is available yet.';
$string['noreviews'] = 'No reviews have been submitted yet.';
$string['noreviewsreceived'] = 'No submitted reviews have been received yet.';
$string['nosubmissions'] = 'No videos have been submitted yet.';
$string['nosubmissionyet'] = 'You have not submitted a video yet.';
$string['notallocated'] = 'This video is not allocated to you for peer review.';
$string['notsubmitted'] = 'Not submitted';
$string['openreview'] = 'Open review';
$string['overallfeedback'] = 'Overall feedback';
$string['participant'] = 'Participant';
$string['participationoverview'] = 'Participation overview';
$string['peerreviewcount'] = 'Peers each student must review';
$string['peerreviewcount_help'] = 'The teacher can regenerate balanced allocations after students have submitted their videos. Use 0 when only teacher reviews are required.';
$string['peerreviewsettings'] = 'Peer review';
$string['pluginadministration'] = 'Video Peer Review administration';
$string['pluginname'] = 'Video Peer Review';
$string['privacy:metadata:allocation'] = 'A peer-review allocation.';
$string['privacy:metadata:allocation:reviewerid'] = 'The user assigned to review a video.';
$string['privacy:metadata:allocation:status'] = 'Whether the allocated review is assigned or completed.';
$string['privacy:metadata:allocation:submissionid'] = 'The video submission assigned for review.';
$string['privacy:metadata:comment'] = 'A timestamped video review comment.';
$string['privacy:metadata:comment:comment'] = 'The timestamped comment text.';
$string['privacy:metadata:comment:timecode'] = 'The video time linked to the comment.';
$string['privacy:metadata:comment:timecreated'] = 'When the timestamped comment was created.';
$string['privacy:metadata:core_files'] = 'Video Peer Review stores uploaded student and common video files in Moodle file storage.';
$string['privacy:metadata:progress'] = 'Video viewing progress recorded while reviewing.';
$string['privacy:metadata:progress:lastposition'] = 'The last known video position.';
$string['privacy:metadata:progress:percent'] = 'The percentage of unique video content watched.';
$string['privacy:metadata:progress:totalwatchtime'] = 'The cumulative playback time reported.';
$string['privacy:metadata:progress:uniquewatched'] = 'The number of unique seconds watched.';
$string['privacy:metadata:progress:userid'] = 'The user whose viewing progress is stored.';
$string['privacy:metadata:progress:watchedsegments'] = 'The watched video intervals.';
$string['privacy:metadata:review'] = 'A video review written by a participant.';
$string['privacy:metadata:review:overallfeedback'] = 'The reviewer overall feedback.';
$string['privacy:metadata:review:reviewerid'] = 'The user who wrote the review.';
$string['privacy:metadata:review:score'] = 'The calculated normalized review score.';
$string['privacy:metadata:review:submissionid'] = 'The video submission being reviewed.';
$string['privacy:metadata:review:timesubmitted'] = 'When the review was submitted.';
$string['privacy:metadata:score'] = 'A criterion score within a video review.';
$string['privacy:metadata:score:concept'] = 'The selected concept for the criterion.';
$string['privacy:metadata:score:feedback'] = 'The reviewer feedback for the criterion.';
$string['privacy:metadata:score:score'] = 'The numeric score for the criterion.';
$string['privacy:metadata:submission'] = 'A student video submission.';
$string['privacy:metadata:submission:timecreated'] = 'When the video submission was created.';
$string['privacy:metadata:submission:timemodified'] = 'When the video submission was last modified.';
$string['privacy:metadata:submission:title'] = 'The submitted video title.';
$string['privacy:metadata:submission:userid'] = 'The user who submitted the video.';
$string['privacy:metadata:submission:videourl'] = 'The submitted external video URL, when URL mode is used.';
$string['rebuildallocations'] = 'Generate / rebuild allocations';
$string['receivedreview'] = 'Received review';
$string['receivedreviews'] = 'Reviews received';
$string['releasedate'] = 'Release received reviews after';
$string['releasedate_help'] = 'When enabled, authors see received peer comments and scores only after this date. Teachers can always see them.';
$string['report'] = 'Video Peer Review report';
$string['required'] = 'Required';
$string['reviewcompleted'] = 'Review completed';
$string['reviewdetails'] = 'Submitted review details';
$string['reviewer'] = 'Reviewer';
$string['reviewpending'] = 'Pending';
$string['reviewsassigned'] = 'Reviews assigned';
$string['reviewsavailableon'] = 'Received reviews will become visible on';
$string['reviewsnotreleased'] = 'Received reviews are not available yet.';
$string['reviewsperformed'] = 'Reviews performed';
$string['reviewsreceived'] = 'Reviews received';
$string['reviewsreceivedcount'] = 'Submitted reviews';
$string['reviewstocomplete'] = 'Reviews to complete';
$string['reviewsubmitted'] = 'Review submitted.';
$string['reviewvideo'] = 'Review video';
$string['savecriterion'] = 'Save criterion';
$string['savesubmission'] = 'Save video submission';
$string['score'] = 'Score';
$string['selectconcept'] = 'Select a concept';
$string['sortorder'] = 'Sort order';
$string['sourceupload'] = 'Upload video';
$string['sourceurl'] = 'Video URL';
$string['studentsubmissions'] = 'Student submissions';
$string['submission'] = 'Submission';
$string['submissionmoderequired'] = 'This feature is available only when students submit their own videos.';
$string['submissionsaved'] = 'Video submission saved.';
$string['submissiontitle'] = 'Video title';
$string['submissionvideo'] = 'Video file';
$string['submissionvideorequired'] = 'Upload a video file.';
$string['submitreview'] = 'Submit review';
$string['submitted'] = 'Submitted';
$string['submitvideo'] = 'Submit video';
$string['timestampedcomments'] = 'Timestamped comments';
$string['timestampedcommentshelp'] = 'Pause at any point or keep watching, write a comment, and it will be linked to the current video time. Click a timecode later to return to that moment.';
$string['trackingerror'] = 'The viewing progress could not be saved.';
$string['video'] = 'Video';
$string['videoconfiguration'] = 'Video configuration';
$string['videomissing'] = 'No playable video was found.';
$string['videomode'] = 'Activity mode';
$string['videomodecommon'] = 'Evaluate one common video';
$string['videomodesubmission'] = 'Each student submits a video';
$string['videonotsupported'] = 'Your browser does not support this video.';
$string['videoplayer'] = 'Video player';
$string['videoreview:addinstance'] = 'Add a new Video Peer Review activity';
$string['videoreview:manageallocations'] = 'Manage peer-review allocations';
$string['videoreview:managecriteria'] = 'Manage assessment criteria';
$string['videoreview:review'] = 'Review videos';
$string['videoreview:submitvideo'] = 'Submit a video';
$string['videoreview:view'] = 'View Video Peer Review';
$string['videoreview:viewall'] = 'View all submissions and reviewer identities';
$string['videoreview:viewreport'] = 'View Video Peer Review reports';
$string['videoreviewname'] = 'Activity name';
$string['videosource'] = 'Video source';
$string['videourl'] = 'Video URL';
$string['videourl_help'] = 'Use a direct video URL, YouTube URL, or Vimeo URL.';
$string['viewingprogress'] = 'Viewed content';
$string['viewreport'] = 'View report';
$string['viewreviewwithvideo'] = 'View review with video';

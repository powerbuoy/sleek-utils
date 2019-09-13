<?php
namespace Sleek\Utils;

#########################################################
# Returns estimated reading time, in minutes, for $postId
# NOTE: 200 words per minute seems normal; http://www.readingsoft.com/
# http://ryanfrankel.com/how-to-find-the-number-of-words-in-a-post-in-wordpress/
function get_reading_time ($postId, $wordsPerMinute = 200) {
	$numWords = str_word_count(strip_tags(get_post_field('post_content', $postId)));
	$min = ceil($numWords / $wordsPerMinute);

	return $min;
}

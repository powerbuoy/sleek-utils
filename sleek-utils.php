<?php
namespace Sleek\Utils;

#########################################
# Like get_template_part but accepts args
# NOTE: Never pass in any of the reserved query vars!
# https://codex.wordpress.org/WordPress_Query_Vars
function get_template_part ($path, $suffix = null, $args = []) {
	# Make all the passed in vars global/accessible in the next get_template_part call
	foreach ($args as $k => $v) {
		if (get_query_var($k)) {
			unset($args[$k]);
			trigger_error("\Sleek\Utils\get_template_part(): variable '$k' already declared", E_USER_WARNING);
		}
		else {
			set_query_var($k, $v);
		}
	}

	# Include the template
	\get_template_part($path, $suffix);

	# Now "unset" the previously set vars (why is there no unset_query_var() ?)
	foreach ($args as $k => $v) {
		set_query_var($k, null);
	}
}

###############################################################
# Like get_template_part but accepts arguments and doesn't echo
function fetch_template_part ($path, $suffix = null, $args = []) {
	ob_start();

	get_template_part($path, $suffix, $args);

	return ob_get_clean();
}

###################################################
# Includes and returns contents instead of echo:ing
function fetch ($path, $args = []) {
	if ($args) {
		extract($args);
	}

	ob_start();

	include $path;

	return ob_get_clean();
}

############################################
# Implodes with different glue for last item
# https://stackoverflow.com/questions/8586141/implode-array-with-and-add-and-before-last-item
function implode_and ($array, $glue = ', ', $lastGlue = ' & ') {
	$last = array_slice($array, -1);
	$first = join($glue, array_slice($array, 0, -1));
	$both  = array_filter(array_merge([$first], $last), 'strlen');

	return join($lastGlue, $both);
}

#########################################################
# Returns estimated reading time, in minutes, for $postId
# NOTE: 200 words per minute seems normal; http://www.readingsoft.com/
# http://ryanfrankel.com/how-to-find-the-number-of-words-in-a-post-in-wordpress/
function get_reading_time ($content, $wordsPerMinute = 200) {
	$content = is_numeric($content) ? get_post_field('post_content', $content) : $content;
	$numWords = str_word_count(strip_tags($content));

	return ceil($numWords / $wordsPerMinute);
}

#####################################
# Gets the currently viewed post type
# Attempts to retrieve the currently viewed post type based on which archive is active
function get_current_post_type () {
	$pt = false;

	# Work out the post type on this archive
	$qo = get_queried_object();

	# Singular
	if (is_singular()) {
		$pt = get_post_type();
	}
	# Post type archive
	elseif ($qo instanceof WP_Post_Type) {
		$pt = $qo->name;
	}
	# Blog archive
	elseif ($qo instanceof WP_Post) {
		$pt = 'post';
	}
	# Taxonomy term
	elseif ($qo instanceof WP_Term) {
		$tax = get_taxonomy($qo->taxonomy);
		$pt = $tax->object_type[0]; # NOTE: Gets the _first_ post-type this tax is connected to
	}
	# Post type set in query var (QUESTION: When does this happen?)
	elseif (get_query_var('post_type')) {
		$pt = get_query_var('post_type');
	}
	# Try to get post type like this (NOTE: this will fetch the _first_ post's post type, if there are posts at all)
	else {
		$pt = get_post_type();
	}

	# NOTE: Sometimes it's an array (in get_query_car('post_type') for example (search??))
	if (is_array($pt)) {
		$pt = '__mixed';
	}

	return $pt;
}

##########################################
# Add extra args to iframe src and element
# https://www.advancedcustomfields.com/resources/oembed/
function add_iframe_args ($iframe, $args = [], $atts = '') {
	# Use preg_match to find iframe src
	preg_match('/src="(.+?)"/', $iframe, $matches);

	$src = $matches[1];
	$newSrc = add_query_arg($args, $src);
	$iframe = str_replace($src, $newSrc, $iframe);

	# Add extra attributes to iframe html
	$iframe = str_replace('></iframe>', ' ' . $atts . '></iframe>', $iframe);

	return $iframe;
}

####################################
# Return YouTube ID from iframe code
# https://stackoverflow.com/questions/1773822/get-youtube-video-id-from-html-code-with-php#answer-7308332
function get_youtube_id ($iframe) {
	preg_match('#(\.be/|/embed/|/v/|/watch\?v=)([A-Za-z0-9_-]{5,11})#', $iframe, $matches);

	return isset($matches[2]) ? $matches[2] : false;
}

##############################################################
# Converts string to camel, pascal, kebab, snake or title case
function convert_case ($str, $to = 'camel') {
	$inflector = \ICanBoogie\Inflector::get('en');

	# camelCase
	if ($to === 'camel') {
		return $inflector->camelize($str, \ICanBoogie\Inflector::DOWNCASE_FIRST_LETTER);
	}
	# PascalCase
	elseif ($to === 'pascal') {
		return $inflector->camelize($str);
	}
	# kebab-case
	elseif ($to === 'kebab') {
		return str_replace('_', '-', $inflector->underscore($str));
	}
	# snake_case
	elseif ($to === 'snake') {
		return $inflector->underscore($str);
	}
	# Title Case
	elseif ($to === 'title') {
		return $inflector->titleize($str);
	}
	# Human readable
	elseif ($to === 'human') {
		return $inflector->humanize($str);
	}
	# Singular
	elseif ($to === 'singular') {
		return $inflector->singularize($str);
	}
	# Plural
	elseif ($to === 'plural') {
		return $inflector->pluralize($str);
	}
	# HTML ID
	elseif ($to === 'html') {
		return trim(preg_replace('/[^a-z0-9-]/', '', str_replace('_', '-', $inflector->underscore($str))), '-');
	}

	return $str;
}

###################################
# Get the optimal number of columns
# for displaying $numItems but never exceeding $maxCols
function optimal_col_count ($numItems, $maxCols = 4) {
	$numCols = $numItems;

	if ($numCols > $maxCols and $maxCols === 2) {
		$numCols = 2;
	}
	elseif ($numCols > $maxCols) {
		$numCols = sqrt($numItems);

		if (!is_int($numCols) or $numCols > $maxCols) {
			$numCols = -1;

			for ($i = $maxCols; $i > 2; $i--) {
				if ($numItems % $i === 0) {
					$numCols = $i;

					break;
				}
			}

			if ($numCols === -1) {
				$rests = [];

				for ($i = $maxCols; $i > 2; $i--) {
					$rests[$i] = $numItems % $i;
				}

				$numCols = array_search(max($rests), $rests);
			}
		}
	}

	return $numCols;
}

#######################
# Log to the JS console
function console_log ($data) {
	add_action('wp_footer', function () use ($data) {
		echo '<script>console.log(';

		if (is_string($data)) {
			echo "'$data'";
		}
		else {
			echo json_encode($data);
		}

		echo ')</script>';
	});

	add_action('admin_footer', function () use ($data) {
		echo '<script>console.log(';

		if (is_string($data)) {
			echo "'$data'";
		}
		else {
			echo json_encode($data);
		}

		echo ')</script>';
	});
}

##############################
# Check if array is sequential
# https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential#comment20074850_265144
function is_sequential_array ($arr) {
	$k = array_keys($arr);

	return $k === array_keys($k);
}

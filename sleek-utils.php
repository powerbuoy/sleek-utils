<?php
namespace Sleek\Utils;

#########################################
# Like get_template_part but accepts args
# NOTE: This is an exact copy of get_template_part() from wp-includes/general-template.php:135 except we call our own load_template()
function get_template_part ($slug, $name = null, $args = []) {
	do_action("get_template_part_{$slug}", $slug, $name);

	$templates = [];
	$name = (string) $name;

	if ($name) {
		$templates[] = "{$slug}-{$name}.php";
	}

	$templates[] = "{$slug}.php";

	do_action('get_template_part', $slug, $name, $templates);

	# NOTE: We don't want locate_template to load the template (first false) and we don't want to require_once (second false)
	$path = locate_template($templates, false, false);

	if ($path) {
		load_template($path, false, $args); # NOTE: Call our own load_template
	}
}

#####################################
# Like load_template but accepts args
# NOTE: THis is an exact copy of load_template() from wp-includes/template.php:702 except we extract $args
function load_template ($_template_file, $require_once = true, $args = []) {
	global $posts, $post, $wp_did_header, $wp_query, $wp_rewrite, $wpdb, $wp_version, $wp, $id, $comment, $user_ID;

	if (is_array($wp_query->query_vars)) {
		extract($wp_query->query_vars, EXTR_SKIP);
	}

	if (isset($s)) {
		$s = esc_attr($s);
	}

	# NOTE: This is our own extract (NOTE: If we do EXTR_SKIP some vars don't get set (like $title I noticed?))
	if (is_array($args)) {
		extract($args);
	}

	if ($require_once) {
		require_once $_template_file;
	}
	else {
		require $_template_file;
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
function get_reading_time ($postIdOrContent, $wordsPerMinute = 200, $acf = true) {
	$content = is_numeric($postIdOrContent) ? get_post_field('post_content', $postIdOrContent) : $postIdOrContent;

	# NOTE: Also take ACF fields into account (not extremely accurate :/)
	if ($acf and is_numeric($postIdOrContent) and function_exists('get_fields')) {
		$content .= json_encode(get_fields($postIdOrContent));
	}

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
	elseif ($qo instanceof \WP_Post_Type) {
		$pt = $qo->name;
	}
	# Blog archive
	elseif ($qo instanceof \WP_Post) {
		$pt = 'post';
	}
	# Taxonomy term
	elseif ($qo instanceof \WP_Term) {
		$tax = get_taxonomy($qo->taxonomy);
		$pt = $tax->object_type[0]; # NOTE: Gets the _first_ post-type this tax is connected to
	}
	# Post type set in query var
	elseif (get_query_var('post_type')) {
		$pt = get_query_var('post_type'); # NOTE: Might be "any"
	}
	# Try to get post type like this (NOTE: this will fetch the _first_ post's post type, if there are posts at all)
	else {
		$pt = get_post_type();
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

	if ($maxCols === 1) {
		return 1;
	}
	elseif ($numCols > $maxCols and $maxCols === 2) {
		$numCols = 2;
	}
	elseif ($numCols > $maxCols) {
		$numCols = sqrt($numItems);

		if (floor($numCols) !== $numCols or $numCols > $maxCols) {
			$numCols = -1;

			for ($i = $maxCols; $i >= 2; $i--) {
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

	return (int) $numCols;
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

############
# Log $where
function log ($data, $where = 'console') {
	if ($where === 'console') {
		console_log($data);
	}
	else {
		echo '<pre>';
		var_dump($data);
		echo '</pre>';
	}
}

##############################
# Check if array is sequential
# https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential#comment20074850_265144
function is_sequential_array ($arr) {
	$k = array_keys($arr);

	return $k === array_keys($k);
}

####################################
# Search/replace every array element
# https://gist.github.com/vdvm/4665450
# https://stackoverflow.com/a/61087353
function str_replace_in_array ($find, $replace, $array) {
	array_walk_recursive($array, function (&$value, $key) use ($find, $replace) {
		$value = str_replace($find, $replace, $value);
	});

	return $array;
}

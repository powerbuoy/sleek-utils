<?php
namespace Sleek\Utils;

#########################################################
# Returns estimated reading time, in minutes, for $postId
# NOTE: 200 words per minute seems normal; http://www.readingsoft.com/
# http://ryanfrankel.com/how-to-find-the-number-of-words-in-a-post-in-wordpress/
function get_reading_time ($postId, $wordsPerMinute = 200) {
	$numWords = str_word_count(strip_tags(get_post_field('post_content', $postId)));

	return ceil($numWords / $wordsPerMinute);
}

#####################################
# Gets the currently viewed post type
# Attempts to retrieve the currently viewed
# post type based on which archive is active
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

		if (is_array($pt)) {
			$pt = '__mixed';
		}
	}
	# Try to get post type like this (NOTE: this will fetch the _first_ post's post type, if there are posts at all)
	else {
		$pt = get_post_type();
	}

	return $pt;
}

###########################################
# Adds support post_type arg in get_terms()
# https://www.dfactory.eu/get_terms-post-type/
if (get_theme_support('sleek-get-terms-post-type-arg')) {
	add_filter('terms_clauses', function ($clauses, $taxonomy, $args) {
		if (!empty($args['post_type']))	{
			global $wpdb;

			$post_types = [];

			if (isset($args['post_type']) and is_array($args['post_type'])) {
				foreach ($args['post_type'] as $cpt)	{
					$post_types[] = "'" . $cpt . "'";
				}
			}

			if (!empty($post_types))	{
				$clauses['fields'] = 'DISTINCT ' . str_replace('tt.*', 'tt.term_taxonomy_id, tt.term_id, tt.taxonomy, tt.description, tt.parent', $clauses['fields']) . ', COUNT(t.term_id) AS count';
				$clauses['join'] .= ' INNER JOIN ' . $wpdb->term_relationships . ' AS r ON r.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN ' . $wpdb->posts . ' AS p ON p.ID = r.object_id';
				$clauses['where'] .= ' AND p.post_type IN (' . implode(',', $post_types) . ')';
				$clauses['orderby'] = 'GROUP BY t.term_id ' . $clauses['orderby'];
			}
		}

		return $clauses;
	}, 10, 3);
}

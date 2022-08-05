<?php
gp_title( __( 'Translation status overview &lt; GlotPress' ) );
gp_enqueue_script( 'tablesorter' );

$breadcrumb   = array();
$breadcrumb[] = gp_link_get( '/', __( 'Locales' ) );
$breadcrumb[] = gp_link_get( gp_url_join( '/locale', $locale_slug ), esc_html( $gp_locale->english_name ) );
$breadcrumb[] = 'Discussions';
gp_breadcrumb( $breadcrumb );
gp_tmpl_header();

?>
<style>
	html { scroll-behavior: smooth; }
</style>

<?php

$comments_by_post_id = array();
$last_comment_by_post_id = array();

foreach ( $comments as $_comment ) {
	$original_id = Helper_Translation_Discussion::get_original_from_post_id( $_comment->comment_post_ID );
	if ( ! isset( $comments_by_post_id[ $_comment->comment_post_ID ] ) ) {
		$comments_by_post_id[ $_comment->comment_post_ID ] = array();
	}

	$comments_by_post_id[ $_comment->comment_post_ID ][] = $_comment;

	if ( ! isset( $last_comment_by_post_id[ $_comment->comment_post_ID ] ) ) {
		$last_comment_by_post_id[ $_comment->comment_post_ID ] = $_comment->comment_date;
	} elseif ( $last_comment_by_post_id[ $_comment->comment_post_ID ] < $_comment->comment_date ) {
		$last_comment_by_post_id[ $_comment->comment_post_ID ] = $_comment->comment_date;
	}
}

uasort(
	$comments_by_post_id,
	function( $a, $b ) use ( $last_comment_by_post_id ) {
		return $last_comment_by_post_id[ $b->comment_post_ID ] <=> $last_comment_by_post_id[ $a->comment_post_ID ];
	}
);

$args = array(
	'style'            => 'ul',
	'type'             => 'comment',
	'callback'         => 'gth_discussion_callback',
	'reverse_children' => false,
);

foreach ( $comments_by_post_id as $_post_id => $post_comments ) {
	$original_id = Helper_Translation_Discussion::get_original_from_post_id( $_post_id );
	if ( ! $original_id ) {
		continue;
	}
	$original = GP::$original->get( $original_id );
	$first_comment = reset( $post_comments );
	?>
	<h2><?php echo esc_html( $original->singular ); ?></h2>
	<a href="<?php echo esc_attr( get_comment_link( $first_comment ) ); ?>">Go to Discussions page</a> | <a href="#after-post-<?php echo esc_attr( $_post_id ); ?>">Next ↓</a>
	<div id="dashboard-comments-<?php echo esc_attr( $_post_id ); ?>" style="border-left: 2px solid #ccc; padding-left: 2em; scroll-behavior: smooth">

	<?php

	wp_list_comments( $args, $post_comments );
	?>
	</div>
	<a name="after-post-<?php echo esc_attr( $_post_id ); ?>"></a>
	<?php
}

?>

<table id="translations" class="translations clear">
	<thead>
	<tr>
		<th>Original string</th>
		<th>Comment</th>
		<th>Author</th>
		<th>Submitted on</th>
	</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $comments as $discussion_comment ) {
			$original_id = Helper_Translation_Discussion::get_original_from_post_id( $discussion_comment->comment_post_ID );
			$original    = GP::$original->get( $original_id );
			echo '<tr>' .
			 '<td>' . esc_html( $original->singular ) . '</td>' .
			 '<td><a href="' . esc_url( get_comment_link( $discussion_comment ) ) . '">' . esc_html( $discussion_comment->comment_content ) . '</a></td>' .
			 '<td>' . esc_html( $discussion_comment->comment_author ) . '</td>' .
			 '<td>' . esc_html( $discussion_comment->comment_date ) . '</td>' .
			 '</tr>';

		}
		?>
		
	</tbody>
	
</table>


	</li>
</ul>
<?php
gp_tmpl_footer();

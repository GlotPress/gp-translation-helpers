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
	table td { overflow-wrap: break-word }
</style>

<?php

$comments_by_post_id            = array();
$bulk_comments                  = array();
$latest_comment_date_by_post_id = array();

foreach ( $comments as $_comment ) {
	$is_linking_comment = preg_match( '!^' . home_url( gp_url() ) . '[a-z0-9_/#-]+$!i', $_comment->comment_content );
	if ( $is_linking_comment ) {
		$linked_comment = $_comment->comment_content;
		$parts          = wp_parse_url( $linked_comment );
		$parts['path']  = rtrim( $parts['path'], '/' );
		$parts['path']  = rtrim( $parts['path'], '/' );
		$path_parts     = explode( '/', $parts['path'] );

		$linking_comment_original_id = array_pop( $path_parts );

		if ( ! isset( $bulk_comments[ $linking_comment_original_id ] ) ) {
			$bulk_comments[ $linking_comment_original_id ] = array();
		}

		$bulk_comments[ $linking_comment_original_id ][] = $_comment;
		continue;
	}

	if ( ! isset( $comments_by_post_id[ $_comment->comment_post_ID ] ) ) {
		$comments_by_post_id[ $_comment->comment_post_ID ] = array();
	}


	$comments_by_post_id[ $_comment->comment_post_ID ][] = $_comment;

	if ( ! isset( $latest_comment_date_by_post_id[ $_comment->comment_post_ID ] ) ) {
		$latest_comment_date_by_post_id[ $_comment->comment_post_ID ] = $_comment->comment_date;
	} elseif ( $latest_comment_date_by_post_id[ $_comment->comment_post_ID ] < $_comment->comment_date ) {
		$latest_comment_date_by_post_id[ $_comment->comment_post_ID ] = $_comment->comment_date;
	}
}

// If the referenced comment is not in the current batch of comments we need to re-add it.
foreach ( $bulk_comments as $original_id => $_post_id ) {
	if ( ! isset( $comments_by_post_id[ $_comment->comment_post_ID ] ) ) {
		$linked_comment = $_comment->comment_content;
		$parts          = wp_parse_url( $linked_comment );
		$comment_id = intval( str_replace( 'comment-', '', $parts['fragment'] ) );
		if ( $comment_id ) {
			$comments_by_post_id[ $_comment->comment_post_ID ] = get_comment( $comment_id );
		}
	}
}

uasort(
	$comments_by_post_id,
	function( $a, $b ) use ( $latest_comment_date_by_post_id ) {
		return $latest_comment_date_by_post_id[ $b->comment_post_ID ] <=> $latest_comment_date_by_post_id[ $a->comment_post_ID ];
	}
);

$args = array(
	'style'            => 'ul',
	'type'             => 'comment',
	'callback'         => 'gth_discussion_callback',
	'reverse_children' => false,
);



?>

<table id="translations" class="translations clear">
	<thead class="discussions-table-head">
	<tr>
		<th>Original string</th>
		<th>Comment</th>
		<th>Author</th>
		<th>Submitted on</th>
	</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $comments_by_post_id as $_post_id => $post_comments ) {
			$original_id = Helper_Translation_Discussion::get_original_from_post_id( $_post_id );
			if ( ! $original_id ) {
				continue;
			}

			$original             = GP::$original->get( $original_id );
			$first_comment        = reset( $post_comments );
			$no_of_other_comments = count( $post_comments ) - 1;
			?>
			<tr>
				<td><?php
				echo esc_html( $original->singular );
				if ( isset( $bulk_comments[ $original_id ] ) ) {
					?> <span class="other-comments" title="<?php echo esc_attr( implode( ', ', array_column( $bulk_comments[ $original_id ], 'comment_content' ) ) ); ?>"><?php
					printf( '+ ' . _n( '%s Other', '%s Others', count( $bulk_comments[ $original_id ] ) ), number_format_i18n( count( $bulk_comments[ $original_id ] ) ) );
					 ?>
					</span>
					<?php
				}
				?></td>
				 <td>
					<a href="<?php echo esc_url( get_comment_link( $first_comment ) ); ?>"><?php echo esc_html( $first_comment->comment_content ); ?></a>
					<?php if ( $no_of_other_comments > 0 ) : ?>
						<br>
						<?php /* translators: number of comments. */ ?>
						<a class="other-comments" href="<?php echo esc_url( get_comment_link( $first_comment ) ); ?>"> + <?php printf( _n( '%s Comment', '%s Comments', $no_of_other_comments ), number_format_i18n( $no_of_other_comments ) ); ?></a>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $first_comment->comment_author ); ?></td>
				<td><?php echo esc_html( $first_comment->comment_date ); ?></td>
			</tr>
			<?php
		}
		?>
		
	</tbody>
	
</table>


	</li>
</ul>
<?php
gp_tmpl_footer();

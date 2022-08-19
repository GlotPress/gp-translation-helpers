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

$comments_by_post_id            = array();
$latest_comment_date_by_post_id = array();

foreach ( $comments as $_comment ) {
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
				<td><?php echo esc_html( $original->singular ); ?></td>
				 <td>
					<a href="<?php echo esc_url( get_comment_link( $first_comment ) ); ?>"><?php echo esc_html( $first_comment->comment_content ); ?></a>
					<?php if ( $no_of_other_comments > 0 ) : ?>
						<br>
						<a class="other-comments" href="<?php echo esc_url( get_comment_link( $first_comment ) ); ?>"> + <?php echo esc_html( $no_of_other_comments ); ?> comments</a>
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

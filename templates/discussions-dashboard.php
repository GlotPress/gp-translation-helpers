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
<table id="translations" class="translations clear">
	<thead>
	<tr>
		<th>Author</th>
		<th>Comment</th>
		<th>Submitted on</th>
	</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $comments as $discussion_comment ) {
			echo '<tr>' .
			 '<td>' . esc_html( $discussion_comment->comment_author ) . '</td>' .
			 '<td><a href="' . esc_url( get_comment_link( $discussion_comment ) ) . '">' . esc_html( $discussion_comment->comment_content ) . '</a></td>' .
			 '<td>' . esc_html( $comment->comment_date ) . '</td>' .
			 '</tr>';

		}
		?>
	</tbody>
	
</table>
<?php
gp_tmpl_footer();

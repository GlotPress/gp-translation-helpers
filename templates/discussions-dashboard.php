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

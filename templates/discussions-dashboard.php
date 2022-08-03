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
		foreach ( $comments as $comment ) {
			echo '<tr>' .
			 '<td>' . $comment->comment_author . '</td>' .
			 '<td>' . $comment->comment_content . '</td>' .
			 '<td>' . $comment->comment_date . '</td>' .
			 '</tr>';

		}
		?>
	</tbody>
	
</table>
<?php
gp_tmpl_footer();

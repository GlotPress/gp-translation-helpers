<?php

class GP_Test_Route_Translation_Helpers extends GP_UnitTestCase_Route {
	public $route_class = 'GP_Route_Translation_Helpers';

	private function create_original( $set, $priority = 0, $singular = 'Original string' ) {
		return $this->factory->original->create(
			array(
				'project_id' => $set->project->id,
				'status'     => '+active',
				'singular'   => $singular,
				'priority'   => $priority,
			)
		);
	}

	private function request_route( $method, ...$args ) {
		try {
			$this->route->$method( ...$args );
		} catch ( Exception $e ) {
			// A halted request (die_with_404()) throws when the loaded GlotPress
			// makes exit_() halt a faked request.
		}
	}

	function test_original_permalink_hides_a_hidden_original_from_anonymous_users() {
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );

		$this->request_route( 'original_permalink', $set->project->path, $hidden->id, $set->locale, $set->slug );

		$this->assert404();
	}

	function test_original_permalink_serves_a_normal_original_to_anonymous_users() {
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$normal = $this->create_original( $set, 0, 'Public string' );

		$this->request_route( 'original_permalink', $set->project->path, $normal->id, $set->locale, $set->slug );

		$this->assertEquals( 'original-permalink', $this->route->loaded_template );
	}

	function test_original_permalink_shows_a_hidden_original_to_a_user_who_can_write() {
		$this->set_admin_user_as_current();

		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );

		$this->request_route( 'original_permalink', $set->project->path, $hidden->id, $set->locale, $set->slug );

		$this->assertEquals( 'original-permalink', $this->route->loaded_template );
	}

	function test_original_permalink_does_not_disclose_a_hidden_original_through_another_project() {
		$set_a  = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set_a, -2, 'Hidden string' );
		$set_b  = $this->factory->translation_set->create_with_project_and_locale();

		$this->request_route( 'original_permalink', $set_b->project->path, $hidden->id, $set_b->locale, $set_b->slug );

		$this->assert404();
	}

	function test_ajax_translation_helpers_hides_a_hidden_original_from_anonymous_users() {
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );

		$this->request_route( 'ajax_translation_helpers', $set->project->path, $hidden->id, null, $set->locale, $set->slug );

		$this->assert404();
	}

	function test_ajax_translation_helpers_locale_hides_a_hidden_original_from_anonymous_users() {
		$set    = $this->factory->translation_set->create_with_project_and_locale();
		$hidden = $this->create_original( $set, -2, 'Hidden string' );

		$this->request_route( 'ajax_translation_helpers_locale', $set->project->path, $set->locale, $set->slug, $hidden->id );

		$this->assert404();
	}
}

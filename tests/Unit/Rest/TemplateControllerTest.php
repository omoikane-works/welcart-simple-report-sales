<?php
/**
 * Template REST controller test.
 *
 * @package SimpleSalesReports
 */

declare(strict_types=1);

namespace OmoikaneWorks\SimpleSalesReports\Tests\Unit\Rest;

use OmoikaneWorks\SimpleSalesReports\Rest\TemplateController;
use OmoikaneWorks\SimpleSalesReports\Templates\TemplateService;
use OmoikaneWorks\SimpleSalesReports\Tests\Unit\Templates\FakeTemplateRepository;
use OmoikaneWorks\SimpleSalesReports\Tests\Support\CreatesTemplateRows;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Tests for TemplateController.
 */
final class TemplateControllerTest extends TestCase {

	use CreatesTemplateRows;

	/**
	 * Test validate ID returns true for positive integer.
	 *
	 * @return void
	 */
	public function test_validate_id_returns_true_for_positive_integer(): void {
		$controller = $this->create_controller();

		$this->assertTrue( $controller->validate_id( 1 ) );
		$this->assertTrue( $controller->validate_id( '10' ) );
	}

	/**
	 * Test validate ID returns false for invalid value.
	 *
	 * @return void
	 */
	public function test_validate_id_returns_false_for_invalid_value(): void {
		$controller = $this->create_controller();

		$this->assertFalse( $controller->validate_id( 0 ) );
		$this->assertFalse( $controller->validate_id( '0' ) );
		$this->assertFalse( $controller->validate_id( '' ) );
	}

	/**
	 * Test get items returns templates.
	 *
	 * @return void
	 */
	public function test_get_items_returns_templates(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0
		);

		$controller = $this->create_controller( $templates );

		$response = $controller->get_items();

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'items', $data );
		$this->assertSame( 10, $data['items'][0]['id'] );
		$this->assertSame( 'Default Sales Report', $data['items'][0]['name'] );
	}

	/**
	 * Test get item returns template.
	 *
	 * @return void
	 */
	public function test_get_item_returns_template(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0
		);

		$controller = $this->create_controller( $templates );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );

		$response = $controller->get_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'item', $data );
		$this->assertSame( 10, $data['item']['id'] );
		$this->assertSame( 'Default Sales Report', $data['item']['name'] );
	}

	/**
	 * Test get item returns error when template is not found.
	 *
	 * @return void
	 */
	public function test_get_item_returns_error_when_template_is_not_found(): void {
		$controller = $this->create_controller();

		$request = new WP_REST_Request();
		$request->set_param( 'id', '999' );

		$response = $controller->get_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_not_found', $response->get_error_code() );

		$data = $response->get_error_data();

		$this->assertIsArray( $data );
		$this->assertSame( 404, $data['status'] );
	}

	/**
	 * Test create item returns created template.
	 *
	 * @return  void
	 */
	public function test_create_item_returns_created_template(): void {
		$repository                = new FakeTemplateRepository();
		$repository->insert_result = 123;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'name', 'Custom Sales Report' );
		$request->set_param( 'content', '<h1>{{ report.title }}</h1>' );

		$response = $controller->create_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'item', $data );
		$this->assertIsArray( $data['item'] );

		$this->assertSame( 123, $data['item']['id'] );
		$this->assertSame( 'Custom Sales Report', $data['item']['name'] );
		$this->assertSame( '<h1>{{ report.title }}</h1>', $data['item']['content'] );
		$this->assertSame( 'sales_report', $data['item']['type'] );
		$this->assertFalse( $data['item']['is_system'] );
		$this->assertFalse( $data['item']['is_default'] );
		$this->assertTrue( $data['item']['is_active'] );
	}

	/**
	 * Test create item returns error when name already exists.
	 *
	 * @return  void
	 */
	public function test_create_item_returns_error_when_name_already_exists(): void {
		$repository                     = new FakeTemplateRepository();
		$repository->name_exists_result = true;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'name', 'Custom Sales Report' );
		$request->set_param( 'content', '<h1>{{ report.title }}</h1>' );

		$response = $controller->create_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_invalid_request', $response->get_error_code() );
		$this->assertSame( 'Template name already exists.', $response->get_error_message() );

		$error_data = $response->get_error_data();

		$this->assertIsArray( $error_data );
		$this->assertSame( 400, $error_data['status'] );
	}

	/**
	 * Test create item returns error when create fails.
	 *
	 * @return  void
	 */
	public function test_create_item_returns_error_when_create_fails(): void {
		$repository                = new FakeTemplateRepository();
		$repository->insert_result = -1;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'name', 'Custom Sales Report' );
		$request->set_param( 'content', '<h1>{{ report.report.title }}</h1>' );

		$response = $controller->create_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_create_failed', $response->get_error_code() );
		$this->assertSame( 'Failed to create template.', $response->get_error_message() );

		$error_data = $response->get_error_data();

		$this->assertIsArray( $error_data );
		$this->assertSame( 500, $error_data['status'] );
	}

	/**
	 * Test duplicate item returns created template item.
	 *
	 * @return  void
	 */
	public function test_duplicate_item_returns_created_template_item(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0
		);

		$repository                = new FakeTemplateRepository( $templates );
		$repository->insert_result = 123;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );

		$response = $controller->duplicate_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$this->assert_response_template_copy( $response, 'Default Sales Report copy' );
	}

	/**
	 * Test duplicate item returns error when template is not found.
	 *
	 * @return  void
	 */
	public function test_duplicate_item_returns_error_when_template_is_not_found(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0
		);

		$repository                = new FakeTemplateRepository( $templates );
		$repository->insert_result = 123;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '999' );

		$response = $controller->duplicate_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_not_found', $response->get_error_code() );

		$error_data = $response->get_error_data();

		$this->assertIsArray( $error_data );
		$this->assertSame( 404, $error_data['status'] );
	}

	/**
	 * Test duplicate item returns error when duplicate fails.
	 *
	 * @return  void
	 */
	public function test_duplicate_item_returns_error_when_duplicate_fails(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0
		);

		$repository                = new FakeTemplateRepository( $templates );
		$repository->insert_result = 0;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );

		$response = $controller->duplicate_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_duplicate_failed', $response->get_error_code() );

		$error_data = $response->get_error_data();

		$this->assertIsArray( $error_data );
		$this->assertSame( 500, $error_data['status'] );
	}

	/**
	 * Test duplicate item generates numbered copy name when copy name exists.
	 *
	 * @return  void
	 */
	public function test_duplicate_item_generates_numbered_copy_name_when_copy_name_exists(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			1
		);

		$repository                = new FakeTemplateRepository( $templates );
		$repository->insert_result = 123;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );

		$response = $controller->duplicate_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$this->assert_response_template_copy( $response, 'Default Sales Report copy(1)' );
	}

	/**
	 * Test duplicate item generates next numbered copy name.
	 *
	 * @return  void
	 */
	public function test_duplicate_item_generates_next_numbered_copy_name(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			2
		);

		$repository                = new FakeTemplateRepository( $templates );
		$repository->insert_result = 123;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );

		$response = $controller->duplicate_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$this->assert_response_template_copy( $response, 'Default Sales Report copy(2)' );
	}

	/**
	 * Test duplicate item returns error when unique copy name cannot be generated.
	 *
	 * @return  void
	 */
	public function test_duplicate_item_returns_error_when_unique_copy_name_cannot_be_generated(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			101
		);

		$repository = new FakeTemplateRepository( $templates );
		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );

		$response = $controller->duplicate_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_duplicate_failed', $response->get_error_code() );

		$error_data = $response->get_error_data();

		$this->assertIsArray( $error_data );
		$this->assertSame( 500, $error_data['status'] );
	}

	/**
	 * Test update item returns updated template.
	 *
	 * @return  void
	 */
	public function test_update_item_returns_updated_template(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0,
			array(
				'is_system'  => false,
				'is_default' => false,
			)
		);

		$repository = new FakeTemplateRepository( $templates );
		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );
		$request->set_param( 'name', 'Updated Template' );
		$request->set_param( 'content', '<h1>{{ report.title }}</h1>' );

		$response = $controller->update_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'item', $data );
		$this->assertSame( 10, $data['item']['id'] );
		$this->assertSame( 'Updated Template', $data['item']['name'] );
		$this->assertSame( '<h1>{{ report.title }}</h1>', $data['item']['content'] );
	}

	/**
	 * Test update item returns error when template is not found.
	 *
	 * @return  void
	 */
	public function test_update_item_returns_error_when_template_is_not_found(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0,
			array(
				'is_system'  => false,
				'is_default' => false,
			)
		);

		$repository = new FakeTemplateRepository( $templates );
		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '999' );
		$request->set_param( 'name', 'Updated Template' );
		$request->set_param( 'content', '<h1>{{ report.title }}</h1>' );

		$response = $controller->update_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_not_found', $response->get_error_code() );

		$data = $response->get_error_data();

		$this->assertIsArray( $data );
		$this->assertSame( 404, $data['status'] );
	}

	/**
	 * Test update item returns error when template is system template.
	 *
	 * @return  void
	 */
	public function test_update_item_returns_error_when_template_is_system_template(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0
		);

		$repository = new FakeTemplateRepository( $templates );
		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );
		$request->set_param( 'name', 'Updated Template' );
		$request->set_param( 'content', '<h1>{{ report.title }}</h1>' );

		$response = $controller->update_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_invalid_request', $response->get_error_code() );
		$this->assertSame( 'System templates cannot be edited.', $response->get_error_message() );

		$data = $response->get_error_data();

		$this->assertIsArray( $data );
		$this->assertSame( 400, $data['status'] );
	}

	/**
	 * Test update item returns error when name already exists.
	 *
	 * @return  void
	 */
	public function test_update_item_returns_error_when_name_already_exists(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0,
			array(
				'is_system'  => false,
				'is_default' => false,
			)
		);

		$repository                     = new FakeTemplateRepository( $templates );
		$repository->name_exists_result = true;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );
		$request->set_param( 'name', 'Existing Template' );
		$request->set_param( 'content', '<h1>{{ report.title }}</h1>' );

		$response = $controller->update_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_invalid_request', $response->get_error_code() );
		$this->assertSame( 'Template name already exists.', $response->get_error_message() );

		$data = $response->get_error_data();

		$this->assertIsArray( $data );
		$this->assertSame( 400, $data['status'] );
	}

	/**
	 * Test update item returns error when update fails.
	 *
	 * @return  void
	 */
	public function test_update_item_returns_error_when_update_fails(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0,
			array(
				'is_system'  => false,
				'is_default' => false,
			)
		);

		$repository                = new FakeTemplateRepository( $templates );
		$repository->update_result = false;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );
		$request->set_param( 'name', 'Custom Sales Report' );
		$request->set_param( 'content', '<h1>{{ report.title }}</h1>' );

		$response = $controller->update_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_update_failed', $response->get_error_code() );
		$this->assertSame( 'Failed to update template.', $response->get_error_message() );

		$data = $response->get_error_data();

		$this->assertIsArray( $data );
		$this->assertSame( 500, $data['status'] );
	}

	/**
	 * Test delete item returns deleted true.
	 *
	 * @return  void
	 */
	public function test_delete_item_returns_deleted_true(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0,
			array(
				'is_system'  => false,
				'is_default' => false,
			)
		);

		$repository = new FakeTemplateRepository( $templates );
		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );

		$response = $controller->delete_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'deleted', $data );
		$this->assertTrue( $data['deleted'] );
	}

	/**
	 * Test delete item returns error when template is not found.
	 *
	 * @return  void
	 */
	public function test_delete_item_returns_error_when_template_is_not_found(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0,
			array(
				'is_system'  => false,
				'is_default' => false,
			)
		);

		$repository = new FakeTemplateRepository( $templates );
		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '999' );

		$response = $controller->delete_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_not_found', $response->get_error_code() );
		$this->assertSame( 'Template not found.', $response->get_error_message() );

		$data = $response->get_error_data();

		$this->assertIsArray( $data );
		$this->assertSame( 404, $data['status'] );
	}

	/**
	 * Test delete item returns error when template is system template.
	 *
	 * @return  void
	 */
	public function test_delete_item_returns_error_when_template_is_system_template(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0
		);

		$repository = new FakeTemplateRepository( $templates );
		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );

		$response = $controller->delete_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_invalid_request', $response->get_error_code() );
		$this->assertSame( 'System template cannot be deleted.', $response->get_error_message() );

		$data = $response->get_error_data();

		$this->assertIsArray( $data );
		$this->assertSame( 400, $data['status'] );
	}

	/**
	 * Test delete item returns error when delete fails.
	 *
	 * @return  void
	 */
	public function test_delete_item_returns_error_when_delete_fails(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0,
			array(
				'is_system'  => false,
				'is_default' => false,
			)
		);

		$repository                    = new FakeTemplateRepository( $templates );
		$repository->deactivate_result = false;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );

		$response = $controller->delete_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_delete_failed', $response->get_error_code() );
		$this->assertSame( 'Failed to delete template.', $response->get_error_message() );

		$data = $response->get_error_data();

		$this->assertIsArray( $data );
		$this->assertSame( 500, $data['status'] );
	}

	/**
	 * Create controller.
	 *
	 * @param array<int, array<string, mixed>> $templates Templates.
	 * @return TemplateController
	 */
	private function create_controller( array $templates = array() ): TemplateController {
		return new TemplateController(
			new TemplateService(
				new FakeTemplateRepository( $templates )
			)
		);
	}

	/**
	 * Create controller with repository.
	 *
	 * @param   FakeTemplateRepository $template_repository    Template repository.
	 * @return  TemplateController
	 */
	private function create_controller_with_repository( FakeTemplateRepository $template_repository ): TemplateController {
		return new TemplateController(
			new TemplateService( $template_repository )
		);
	}

	/**
	 * Assert response template copy.
	 *
	 * @param   WP_REST_Response $response      Response.
	 * @param   string           $expected_name Expected name.
	 * @return  void
	 */
	private function assert_response_template_copy(
		WP_REST_Response $response,
		string $expected_name,
	): void {
		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'item', $data );
		$this->assertIsArray( $data['item'] );

		$this->assertSame( $expected_name, $data['item']['name'] );
		$this->assertSame( '<h1>{{ report.title }}</h1>', $data['item']['content'] );
		$this->assertSame( '1.0.0', $data['item']['version'] );
		$this->assertFalse( $data['item']['is_system'] );
		$this->assertFalse( $data['item']['is_default'] );
		$this->assertTrue( $data['item']['is_active'] );
	}
}

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
		$template    = $this->create_template_row(
			array(
				'id'   => 10,
				'name' => 'Default Sales Report',
			)
		);
		$template_id = (int) $template['id'];

		$controller = $this->create_controller(
			array(
				$template_id => $template,
			)
		);

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
		$template    = $this->create_template_row(
			array(
				'id'   => 10,
				'name' => 'Custom Template',
			)
		);
		$template_id = (int) $template['id'];

		$controller = $this->create_controller(
			array(
				$template_id => $template,
			)
		);

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );

		$response = $controller->get_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'item', $data );
		$this->assertSame( 10, $data['item']['id'] );
		$this->assertSame( 'Custom Template', $data['item']['name'] );
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
	 * Test duplicate item returns created template item.
	 *
	 * @return  void
	 */
	public function test_duplicate_item_returns_created_template_item(): void {
		$template    = $this->create_template_row(
			array(
				'id'           => 10,
				'template_key' => 'default_sales_report',
				'name'         => 'default_sales_report',
				'content'      => 'Hello {{name}}',
				'is_system'    => true,
			)
		);
		$template_id = (int) $template['id'];

		$repository                = new FakeTemplateRepository(
			array(
				$template_id => $template,
			)
		);
		$repository->insert_result = 123;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );
		$request->set_param( 'name', 'Custom Sales Report' );

		$response = $controller->duplicate_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'item', $data );
		$this->assertIsArray( $data['item'] );

		$this->assertSame( 123, $data['item']['id'] );
		$this->assertSame( 'Custom Sales Report', $data['item']['name'] );
		$this->assertSame( 'Hello {{name}}', $data['item']['content'] );
		$this->assertSame( 'sales_report', $data['item']['type'] );
		$this->assertFalse( $data['item']['is_system'] );
		$this->assertFalse( $data['item']['is_default'] );
		$this->assertTrue( $data['item']['is_active'] );
	}

	/**
	 * Test duplicate item returns error when template is not found.
	 *
	 * @return  void
	 */
	public function test_duplicate_item_returns_error_when_template_is_not_found(): void {
		$template    = $this->create_template_row(
			array(
				'id'           => 10,
				'template_key' => 'default_sales_report',
				'name'         => 'default_sales_report',
				'content'      => 'Hello {{name}}',
				'is_system'    => true,
			)
		);
		$template_id = (int) $template['id'];

		$repository                = new FakeTemplateRepository(
			array(
				$template_id => $template,
			)
		);
		$repository->insert_result = 123;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '999' );
		$request->set_param( 'name', 'Custom Sales Report' );

		$response = $controller->duplicate_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_not_found', $response->get_error_code() );

		$error_data = $response->get_error_data();

		$this->assertIsArray( $error_data );
		$this->assertSame( 404, $error_data['status'] );
	}

	/**
	 * Test duplicate item returns error when template name already exists.
	 *
	 * @return  void
	 */
	public function test_duplicate_item_returns_error_when_name_already_exists(): void {
		$template    = $this->create_template_row(
			array(
				'id'           => 10,
				'template_key' => 'default_sales_report',
				'name'         => 'default_sales_report',
				'content'      => 'Hello {{name}}',
				'is_system'    => true,
			)
		);
		$template_id = (int) $template['id'];

		$repository                     = new FakeTemplateRepository(
			array(
				$template_id => $template,
			)
		);
		$repository->name_exists_result = true;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );
		$request->set_param( 'name', 'Custom Sales Report' );

		$response = $controller->duplicate_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'ossr_template_invalid_request', $response->get_error_code() );

		$error_data = $response->get_error_data();

		$this->assertIsArray( $error_data );
		$this->assertSame( 400, $error_data['status'] );
	}

	/**
	 * Test duplicate item returns error when duplicate fails.
	 *
	 * @return  void
	 */
	public function test_duplicate_item_returns_error_when_duplicate_fails(): void {
		$template    = $this->create_template_row(
			array(
				'id'      => 10,
				'content' => 'Hello {{name}}',
			)
		);
		$template_id = (int) $template['id'];

		$repository                = new FakeTemplateRepository(
			array(
				$template_id => $template,
			)
		);
		$repository->insert_result = 0;

		$controller = $this->create_controller_with_repository( $repository );

		$request = new WP_REST_Request();
		$request->set_param( 'id', '10' );
		$request->set_param( 'name', 'Custom Sales Report' );

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
		$template    = $this->create_template_row(
			array(
				'id'           => 10,
				'template_key' => 'custom_sales_report',
				'name'         => 'Custom Sales Report',
				'content'      => 'Hello {{name}}',
				'is_system'    => false,
				'is_default'   => false,
			)
		);
		$template_id = (int) $template['id'];

		$repository = new FakeTemplateRepository(
			array(
				$template_id => $template,
			)
		);

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
		$template    = $this->create_template_row(
			array(
				'id'           => 10,
				'template_key' => 'custom_sales_report',
				'name'         => 'Custom Sales Report',
				'content'      => 'Hello {{name}}',
				'is_system'    => false,
				'is_default'   => false,
			)
		);
		$template_id = (int) $template['id'];

		$repository = new FakeTemplateRepository(
			array(
				$template_id => $template,
			)
		);

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
		$template    = $this->create_template_row(
			array(
				'id'           => 10,
				'template_key' => 'default_sales_report',
				'name'         => 'Default Sales Report',
				'content'      => 'Hello {{name}}',
				'is_system'    => true,
				'is_default'   => true,
			)
		);
		$template_id = (int) $template['id'];

		$repository = new FakeTemplateRepository(
			array(
				$template_id => $template,
			)
		);

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
		$template    = $this->create_template_row(
			array(
				'id'           => 10,
				'template_key' => 'custom_sales_report',
				'name'         => 'Custom Sales Report',
				'content'      => 'Hello {{name}}',
				'is_system'    => false,
				'is_default'   => false,
			)
		);
		$template_id = (int) $template['id'];

		$repository                     = new FakeTemplateRepository(
			array(
				$template_id => $template,
			)
		);
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
		$template    = $this->create_template_row(
			array(
				'id'           => 10,
				'template_key' => 'custom_sales_report',
				'name'         => 'Custom Sales Report',
				'content'      => 'Hello {{name}}',
				'is_system'    => false,
				'is_default'   => false,
			)
		);
		$template_id = (int) $template['id'];

		$repository                = new FakeTemplateRepository(
			array(
				$template_id => $template,
			)
		);
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
}

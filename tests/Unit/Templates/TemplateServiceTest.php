<?php
/**
 * Template Service test.
 *
 * @package SimpleSalesReports
 */

declare(strict_types=1);

namespace OmoikaneWorks\SimpleSalesReports\Tests\Unit\Templates;

use OmoikaneWorks\SimpleSalesReports\Templates\TemplateService;
use OmoikaneWorks\SimpleSalesReports\Tests\Support\CreatesTemplateRows;
use PHPUnit\Framework\TestCase;

/**
 * Test for TemplateService.
 */
final class TemplateServiceTest extends TestCase {

	use CreatesTemplateRows;

	/**
	 * Test list templates returns repository templates.
	 *
	 * @return  void
	 */
	public function test_list_templates_returns_repository_templates(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0
		);

		$repository = new FakeTemplateRepository( $templates );

		$service = new TemplateService( $repository );

		$result = $service->list_templates();

		$this->assertCount( 1, $result );
		$this->assertSame( 10, $result[0]['id'] );
		$this->assertSame( 'Default Sales Report', $result[0]['name'] );
	}

	/**
	 * Test get_template returns repository template.
	 *
	 * @return  void
	 */
	public function test_get_template_returns_repository_template(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0
		);

		$repository = new FakeTemplateRepository( $templates );

		$service = new TemplateService( $repository );

		$result = $service->get_template( 10 );

		$this->assertSame( 10, $result['id'] );
		$this->assertSame( 'Default Sales Report', $result['name'] );
	}

	/**
	 * Test get_template throws exception when template is not found.
	 *
	 * @return  void
	 */
	public function test_get_template_throws_exception_when_template_is_not_found(): void {
		$repository = new FakeTemplateRepository();

		$service = new TemplateService( $repository );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Template not found.' );

		$service->get_template( 999 );
	}

	/**
	 * Test duplicate template inserts copied template.
	 *
	 * @return  void
	 */
	public function test_duplicate_template_inserts_copied_template(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0
		);

		$repository                = new FakeTemplateRepository( $templates );
		$repository->insert_result = 20;

		$service = new TemplateService( $repository );

		$result = $service->duplicate_template( 10 );

		$this->assertSame( 20, $result );

		$this->assert_inserted_template_copy( $repository, 'Default Sales Report copy' );
	}

	/**
	 * Test duplicate template throws exception when insert fails.
	 *
	 * @return  void
	 */
	public function test_duplicate_template_throws_exception_when_insert_fails(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			0
		);

		$repository                = new FakeTemplateRepository( $templates );
		$repository->insert_result = 0;

		$service = new TemplateService( $repository );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to duplicate template.' );

		$service->duplicate_template( 10 );
	}

	/**
	 * Test duplicate template generates numbered copy name when copy name exists.
	 *
	 * @return  void
	 */
	public function test_duplicate_template_generates_numbered_copy_name_when_copy_name_exists(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			1
		);

		$repository                = new FakeTemplateRepository( $templates );
		$repository->insert_result = 20;

		$service = new TemplateService( $repository );

		$result = $service->duplicate_template( 10 );

		$this->assertSame( 20, $result );

		$this->assert_inserted_template_copy( $repository, 'Default Sales Report copy(1)' );
	}

	/**
	 * Test duplicate template generates next numbered copy name.
	 *
	 * @return  void
	 */
	public function test_duplicate_template_generates_next_numbered_copy_name(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			2
		);

		$repository                = new FakeTemplateRepository( $templates );
		$repository->insert_result = 20;

		$service = new TemplateService( $repository );

		$result = $service->duplicate_template( 10 );

		$this->assertSame( 20, $result );

		$this->assert_inserted_template_copy( $repository, 'Default Sales Report copy(2)' );
	}

	/**
	 * Test duplicate template throws exception when unique copy name cannot be generated.
	 *
	 * @return  void
	 */
	public function test_duplicate_template_throws_exception_when_unique_copy_name_cannot_be_generated(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Default Sales Report',
			101
		);

		$repository = new FakeTemplateRepository( $templates );

		$service = new TemplateService( $repository );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Could not generate a unique template name.' );

		$service->duplicate_template( 10 );
	}

	/**
	 * Test update template updates template.
	 *
	 * @return  void
	 */
	public function test_update_template_updates_custom_template(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Custom Sales Report',
			0,
			array( 'is_system' => false )
		);

		$repository = new FakeTemplateRepository( $templates );

		$service = new TemplateService( $repository );

		$service->update_template( 10, 'Updated Template', '<p>Updated</p>' );

		$this->assertIsArray( $repository->updated_data );
		$this->assertSame( 10, $repository->updated_data['id'] );
		$this->assertSame( 'Updated Template', $repository->updated_data['data']['name'] );
		$this->assertSame( '<p>Updated</p>', $repository->updated_data['data']['content'] );
		$this->assertSame( hash( 'sha256', '<p>Updated</p>' ), $repository->updated_data['data']['content_hash'] );
	}

	/**
	 * Test update template throws exception when template is system template.
	 *
	 * @return  void
	 */
	public function test_update_template_throws_exception_when_template_is_system_template(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Custom Sales Report',
			0
		);

		$repository = new FakeTemplateRepository( $templates );

		$service = new TemplateService( $repository );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'System templates cannot be edited.' );

		$service->update_template( 10, 'Updated Template', '<p>Updated</p>' );
	}

	/**
	 * Test update template throws exception when template name already exists.
	 *
	 * @return  void
	 */
	public function test_update_template_throws_exception_when_template_name_already_exists(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Custom Sales Report',
			0,
			array( 'is_system' => false )
		);

		$repository                     = new FakeTemplateRepository( $templates );
		$repository->name_exists_result = true;

		$service = new TemplateService( $repository );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Template name already exists.' );

		$service->update_template( 10, 'Updated Template', '<p>Updated</p>' );
	}

	/**
	 * Test update template throws exception when update fails.
	 *
	 * @return  void
	 */
	public function test_update_template_throws_exception_when_update_fails(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Custom Sales Report',
			0,
			array( 'is_system' => false )
		);

		$repository                = new FakeTemplateRepository( $templates );
		$repository->update_result = false;

		$service = new TemplateService( $repository );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to update template.' );

		$service->update_template( 10, 'Updated Template', '<p>Updated</p>' );
	}

	/**
	 * Test update template throws exception when name is empty.
	 *
	 * @return  void
	 */
	public function test_update_template_throws_exception_when_name_is_empty(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Custom Sales Report',
			0,
			array( 'is_system' => false )
		);

		$repository = new FakeTemplateRepository( $templates );

		$service = new TemplateService( $repository );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Template name is required.' );

		$service->update_template( 10, '   ', '<p>Updated</p>' );
	}

	/**
	 * Test update template throws exception when content is empty.
	 *
	 * @return  void
	 */
	public function test_update_template_throws_exception_when_content_is_empty(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Custom Sales Report',
			0,
			array( 'is_system' => false )
		);

		$repository = new FakeTemplateRepository( $templates );

		$service = new TemplateService( $repository );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Template content is required.' );

		$service->update_template( 10, 'Updated Template', '     ' );
	}

	/**
	 * Test update template throws exception when content syntax is invalid.
	 *
	 * @return  void
	 */
	public function test_update_template_throws_exception_when_content_syntax_is_invalid(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Custom Sales Report',
			0,
			array( 'is_system' => false )
		);

		$repository = new FakeTemplateRepository( $templates );

		$service = new TemplateService( $repository );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Template syntax is invalid.' );

		$service->update_template( 10, 'Updated Template', '{{#items}}{{/orders}}' );
	}

	/**
	 * Test delete template deactivates custom templates.
	 *
	 * @return  void
	 */
	public function test_delete_template_deactivates_custom_template(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Custom Sales Report',
			0,
			array( 'is_system' => false )
		);

		$repository = new FakeTemplateRepository( $templates );

		$service = new TemplateService( $repository );

		$service->delete_template( 10 );

		$this->assertSame( 10, $repository->deactivated_id );
	}

	/**
	 * Test delete template throws exception when template is system template.
	 *
	 * @return  void
	 */
	public function test_delete_template_throws_exception_when_template_is_system_template(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Custom Sales Report',
			0
		);

		$repository = new FakeTemplateRepository( $templates );

		$service = new TemplateService( $repository );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'System template cannot be deleted.' );

		$service->delete_template( 10 );
	}

	/**
	 * Test delete template throws exception when delete fails.
	 *
	 * @return  void
	 */
	public function test_delete_template_throws_exception_when_delete_fails(): void {
		$templates = $this->create_template_rows_with_copy_names(
			10,
			'Custom Sales Report',
			0,
			array( 'is_system' => false )
		);

		$repository                    = new FakeTemplateRepository( $templates );
		$repository->deactivate_result = false;

		$service = new TemplateService( $repository );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to delete template.' );

		$service->delete_template( 10 );
	}

	/**
	 * Assert inserted template copy.
	 *
	 * @param   FakeTemplateRepository $repository     Fake template repository.
	 * @param   string                 $expected_name  Expected name.
	 * @return  void
	 */
	private function assert_inserted_template_copy(
		FakeTemplateRepository $repository,
		string $expected_name,
	): void {
		$data = $repository->inserted_data;

		$this->assertIsArray( $data );
		$this->assertSame( $expected_name, $data['name'] );
		$this->assertSame( '<h1>{{ report.title }}</h1>', $data['content'] );
		$this->assertSame(
			hash( 'sha256', '<h1>{{ report.title }}</h1>' ),
			$data['content_hash']
		);
		$this->assertSame( '1.0.0', $data['version'] );
		$this->assertStringStartswith( 'custom_', $data['template_key'] );
	}
}

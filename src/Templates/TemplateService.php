<?php
/**
 * Template Service.
 *
 * @package SimpleSalesReports
 */

declare(strict_types=1);

namespace OmoikaneWorks\SimpleSalesReports\Templates;

use Mustache_Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Handles template operations.
 */
final class TemplateService {

	/**
	 * Template repository.
	 *
	 * @var TemplateRepositoryInterface
	 */
	private TemplateRepositoryInterface $template_repository;

	/**
	 * Constructor.
	 *
	 * @param TemplateRepositoryInterface $template_repository Template repository.
	 */
	public function __construct( TemplateRepositoryInterface $template_repository ) {
		$this->template_repository = $template_repository;
	}

	/**
	 * List templates.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_templates(): array {
		return $this->template_repository->find_selectable_by_type( TemplateTypes::SALES_REPORT );
	}

	/**
	 * Get template.
	 *
	 * @param   int $template_id    Template ID.
	 * @return  array<string, mixed>
	 * @throws  \InvalidArgumentException   Template not found.
	 */
	public function get_template( int $template_id ): array {
		$template = $this->template_repository->find_by_id( $template_id );

		if ( null === $template ) {
			throw new \InvalidArgumentException( 'Template not found.' );
		}

		return $template;
	}

	/**
	 * Create template.
	 *
	 * @param   string $name       Template name.
	 * @param   string $content    Template content.
	 * @return  int
	 * @throws  \InvalidArgumentException   Template name already exists.
	 * @throws  \RuntimeException           Failed to create template.
	 */
	public function create_template( string $name, string $content ): int {

		// Check name.
		$this->validate_name( $name );
		if ( $this->template_repository->name_exists( $name, null ) ) {
			throw new \InvalidArgumentException( 'Template name already exists.' );
		}

		// Check content.
		$this->validate_content( $content );

		$new_template_id = $this->template_repository->insert(
			array(
				'template_key' => $this->generate_template_key(),
				'name'         => $name,
				'type'         => TemplateTypes::SALES_REPORT,
				'content'      => $content,
				'content_hash' => $this->generate_content_hash( $content ),
				'version'      => '1.0.0',
				'is_system'    => false,
				'is_default'   => false,
				'is_active'    => true,
			)
		);

		if ( 0 >= $new_template_id ) {
			throw new \RuntimeException( 'Failed to create template.' );
		}

		return $new_template_id;
	}

	/**
	 * Duplicate template.
	 *
	 * @param   int $template_id    Template ID.
	 * @return  int
	 * @throws  \InvalidArgumentException   Template name already exists.
	 * @throws  \RuntimeException           Failed to duplicate template.
	 */
	public function duplicate_template( int $template_id ): int {
		$template = $this->get_template( $template_id );

		$name = $this->generate_copy_name( $template['name'] );

		$this->validate_name( $name );

		if ( $this->template_repository->name_exists( $name, null ) ) {
			throw new \InvalidArgumentException( 'Template name already exists.' );
		}

		$content = (string) $template['content'];

		$new_template_id = $this->template_repository->insert(
			array(
				'template_key' => $this->generate_template_key(),
				'name'         => $name,
				'content'      => $content,
				'content_hash' => $this->generate_content_hash( $content ),
				'version'      => '1.0.0',
			)
		);

		if ( 0 >= $new_template_id ) {
			throw new \RuntimeException( 'Failed to duplicate template.' );
		}

		return $new_template_id;
	}

	/**
	 * Update template.
	 *
	 * @param   int    $template_id    Template ID.
	 * @param   string $name           Template name.
	 * @param   string $content        Template content.
	 * @return  void
	 * @throws  \InvalidArgumentException   System templates cannot be edited.
	 * @throws  \InvalidArgumentException   Template name already exists.
	 * @throws  \RuntimeException           Failed to update template.
	 */
	public function update_template( int $template_id, string $name, string $content ): void {
		$template = $this->get_template( $template_id );

		if ( ! empty( $template['is_system'] ) ) {
			throw new \InvalidArgumentException( 'System templates cannot be edited.' );
		}

		$this->validate_name( $name );
		$this->validate_content( $content );

		if ( $this->template_repository->name_exists( $name, $template_id ) ) {
			throw new \InvalidArgumentException( 'Template name already exists.' );
		}

		$updated = $this->template_repository->update(
			$template_id,
			array(
				'name'         => $name,
				'content'      => $content,
				'content_hash' => $this->generate_content_hash( $content ),
			)
		);

		if ( ! $updated ) {
			throw new \RuntimeException( 'Failed to update template.' );
		}
	}

	/**
	 * Delete template.
	 *
	 * @param   int $template_id    Template ID.
	 * @return  void
	 * @throws  \InvalidArgumentException   System template cannot be deleted.
	 * @throws  \RuntimeException           Failed to delete template.
	 */
	public function delete_template( int $template_id ): void {
		$template = $this->get_template( $template_id );

		if ( ! empty( $template['is_system'] ) ) {
			throw new \InvalidArgumentException( 'System template cannot be deleted.' );
		}

		$deleted = $this->template_repository->deactivate( $template_id );

		if ( ! $deleted ) {
			throw new \RuntimeException( 'Failed to delete template.' );
		}
	}

	/**
	 * Validate template name.
	 *
	 * @param   string $name   Template name.
	 * @return  void
	 * @throws  \InvalidArgumentException   Template name is required.
	 * @throws  \InvalidArgumentException   Template name is too long.
	 */
	private function validate_name( string $name ): void {
		if ( '' === trim( $name ) ) {
			throw new \InvalidArgumentException( 'Template name is required.' );
		}

		if ( 191 < mb_strlen( $name ) ) {
			throw new \InvalidArgumentException( 'Template name is too long.' );
		}
	}

	/**
	 * Validate template content.
	 *
	 * @param   string $content    Template content.
	 * @return  void
	 * @throws  \InvalidArgumentException   Template content is required.
	 * @throws  \InvalidArgumentException   Template syntax is invalid.
	 */
	private function validate_content( string $content ): void {
		if ( '' === trim( $content ) ) {
			throw new \InvalidArgumentException( 'Template content is required.' );
		}

		try {
			$engine = new Mustache_Engine();
			$engine->render( $content, array() );
		} catch ( \Throwable $exception ) {
			// The caught exception is passed as the previous exception, not output.
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \InvalidArgumentException( 'Template syntax is invalid.', 0, $exception );
		}
	}

	/**
	 * Generate template key.
	 *
	 * @return  string
	 */
	private function generate_template_key(): string {
		return 'custom_' . str_replace( '-', '', wp_generate_uuid4() );
	}

	/**
	 * Generate content hash.
	 *
	 * @param   string $content    Template content.
	 * @return  string
	 */
	private function generate_content_hash( string $content ): string {
		return hash( 'sha256', $content );
	}

	/**
	 * Generate copy name.
	 *
	 * @param   string $base_name  Base name.
	 * @return  string
	 * @throws  \RuntimeException   Could not generate a unique template name.
	 */
	private function generate_copy_name( string $base_name ): string {
		$copy_name = sprintf(
			// translators: %s: template name.
			__( '%s copy', 'omoikane-simple-sales-reports' ),
			$base_name
		);

		if ( ! $this->template_repository->name_exists( $copy_name, null ) ) {
			return $copy_name;
		}

		for ( $index = 1; $index <= 100; ++$index ) {
			$candidate = sprintf(
				// translators: 1: template name, 2: copy number.
				__( '%1$s copy(%2$d)', 'omoikane-simple-sales-reports' ),
				$base_name,
				$index
			);

			if ( ! $this->template_repository->name_exists( $candidate, null ) ) {
				return $candidate;
			}
		}

		throw new \RuntimeException( 'Could not generate a unique template name.' );
	}
}

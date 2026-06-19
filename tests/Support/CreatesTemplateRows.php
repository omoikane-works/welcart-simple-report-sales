<?php
/**
 * Creates template rows for tests.
 *
 * @package SimpleSalesReports
 */

declare(strict_types=1);

namespace OmoikaneWorks\SimpleSalesReports\Tests\Support;

use OmoikaneWorks\SimpleSalesReports\Templates\TemplateKeys;
use OmoikaneWorks\SimpleSalesReports\Templates\TemplateTypes;

/**
 * Create template rows for tests.
 */
trait CreatesTemplateRows {

	/**
	 * Create Template row.
	 *
	 * @param   array<string, mixed> $overrides  Overrides.
	 * @return  array<string, mixed>
	 */
	private function create_template_row( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'           => 1,
				'template_key' => TemplateKeys::DEFAULT_SALES_REPORT,
				'name'         => 'Default Sales Report',
				'type'         => TemplateTypes::SALES_REPORT,
				'content'      => '<h1>{{ report.title }}</h1>',
				'content_hash' => 'hash-default',
				'version'      => '1.0.0',
				'is_system'    => true,
				'is_default'   => true,
				'is_active'    => true,
				'created_at'   => '2026-05-01 10:00:00',
				'updated_at'   => '2026-05-01 10:00:00',
			),
			$overrides
		);
	}

	/**
	 * Create template rows with copy names.
	 *
	 * @param   int    $base_template_id Base template ID.
	 * @param   string $base_name        Base template name.
	 * @param   int    $copy_count       Copy row count.
	 * @param   array  $overrides        Template row overrides.
	 * @return  array<int, array<string, mixed>>
	 */
	private function create_template_rows_with_copy_names(
		int $base_template_id,
		string $base_name,
		int $copy_count,
		array $overrides = array()
	): array {
		$base_template = $this->create_template_row(
			array_merge(
				$overrides,
				array(
					'id'   => $base_template_id,
					'name' => $base_name,
				)
			)
		);

		$templates = array(
			$base_template_id => $base_template,
		);

		for ( $index = 1; $index <= $copy_count; ++$index ) {
			$template_id   = $base_template_id + $index;
			$template_name = ( 1 === $index )
			? $base_name . ' copy'
			: sprintf( '%s copy(%d)', $base_name, $index - 1 );

			$templates[ $template_id ] = $this->create_template_row(
				array_merge(
					$overrides,
					array(
						'id'        => $template_id,
						'name'      => $template_name,
						'is_system' => false,
					)
				)
			);
		}

		return $templates;
	}

	/**
	 * Create template rows indexed by ID.
	 *
	 * @param   array<int, array<string, mixed>> $templates  Templates.
	 * @return  array<int, array<string, mixed>>
	 */
	private function create_template_map( array $templates ): array {
		$template_map = array();

		foreach ( $templates as $template ) {
			if ( ! isset( $template['id'] ) ) {
				continue;
			}

			$template_map[ (int) $template['id'] ] = $template;
		}

		return $template_map;
	}
}

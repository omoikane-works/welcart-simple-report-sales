<?php
/**
 * Template REST Controller.
 *
 * @package SimpleSalesReports
 */

declare(strict_types=1);

namespace OmoikaneWorks\SimpleSalesReports\Rest;

use OmoikaneWorks\SimpleSalesReports\Templates\TemplateService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Template REST Controller.
 */
final class TemplateController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'ossr/v1';

	/**
	 * Template service.
	 *
	 * @var TemplateService
	 */
	private TemplateService $template_service;

	/**
	 * Constructor.
	 *
	 * @param   TemplateService $template_service   Template service.
	 */
	public function __construct( TemplateService $template_service ) {
		$this->template_service = $template_service;
	}

	/**
	 * Register routes.
	 *
	 * @return  void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/templates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => array( $this, 'validate_id' ),
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'id'      => array(
							'required'          => true,
							'validate_callback' => array( $this, 'validate_id' ),
						),
						'name'    => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => array( $this, 'validate_id' ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/templates/(?P<id>\d+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_item' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'id'   => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_id' ),
					),
					'name' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_name' ),
					),
				),
			)
		);
	}

	/**
	 * Check permissions.
	 *
	 * @return  bool
	 */
	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate ID.
	 *
	 * @param   mixed $value  Value.
	 * @return  bool
	 */
	public function validate_id( mixed $value ): bool {
		return 0 < absint( $value );
	}

	/**
	 * Validate template name.
	 *
	 * @param   mixed $value  Value.
	 * @return  bool
	 */
	public function validate_name( mixed $value ): bool {
		return is_string( $value ) && '' !== trim( $value );
	}

	/**
	 * Get templates.
	 *
	 * @return  WP_REST_Response
	 */
	public function get_items(): WP_REST_Response {
		$templates = $this->template_service->list_templates();

		return new WP_REST_Response(
			array(
				'items' => $templates,
			),
			200
		);
	}

	/**
	 * Get template.
	 *
	 * @param   WP_REST_Request $request    REST request.
	 * @return  WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$template_id = absint( $request['id'] );
			$template    = $this->template_service->get_template( $template_id );

			return new WP_REST_Response(
				array(
					'item' => $template,
				),
				200
			);
		} catch ( \InvalidArgumentException $exception ) {
			unset( $exception );

			return new WP_Error(
				'ossr_template_not_found',
				'Template not found.',
				array(
					'status' => 404,
				)
			);
		}
	}

	/**
	 * Duplicate item.
	 *
	 * @param   WP_REST_Request $request    Request.
	 * @return  WP_REST_Response|WP_Error
	 */
	public function duplicate_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$template_id = absint( $request['id'] );
		$name        = (string) $request['name'];

		try {
			$duplicated_id = $this->template_service->duplicate_template(
				$template_id,
				$name
			);
		} catch ( \InvalidArgumentException $exception ) {
			$message = $exception->getMessage();

			if ( 'Template not found.' === $message ) {
				return new WP_Error(
					'ossr_template_not_found',
					'Template not found.',
					array( 'status' => 404 )
				);
			}

			return new WP_Error(
				'ossr_template_invalid_request',
				$message,
				array( 'status' => 400 )
			);
		} catch ( \RuntimeException $exception ) {
			unset( $exception );

			return new WP_Error(
				'ossr_template_duplicate_failed',
				'Failed to duplicate template.',
				array( 'status' => 500 )
			);
		}

		try {
			$template = $this->template_service->get_template( $duplicated_id );
		} catch ( \InvalidArgumentException $exception ) {
			unset( $exception );

			return new WP_Error(
				'ossr_template_duplicate_failed',
				'Failed to duplicate template.',
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'item' => $template,
			),
			201
		);
	}

	/**
	 * Update template item.
	 *
	 * @param   WP_REST_Request $request    Request.
	 * @return  WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$template_id = absint( $request['id'] );
		$name        = (string) $request->get_param( 'name' );
		$content     = (string) $request->get_param( 'content' );

		try {
			$this->template_service->update_template(
				$template_id,
				$name,
				$content
			);
		} catch ( \InvalidArgumentException $exception ) {
			$message = $exception->getMessage();

			if ( 'Template not found.' === $message ) {
				return new WP_Error(
					'ossr_template_not_found',
					'Template not found.',
					array( 'status' => 404 ),
				);
			}

			return new WP_Error(
				'ossr_template_invalid_request',
				$message,
				array( 'status' => 400 )
			);
		} catch ( \RuntimeException $exception ) {
			unset( $exception );

			return new WP_Error(
				'ossr_template_update_failed',
				'Failed to update template.',
				array( 'status' => 500 )
			);
		}

		try {
			$template = $this->template_service->get_template( $template_id );
		} catch ( \InvalidArgumentException $exception ) {
			unset( $exception );

			return new WP_Error(
				'ossr_template_update_failed',
				'Failed to update template.',
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'item' => $template,
			),
			200
		);
	}

	/**
	 * Delete template item.
	 *
	 * @param   WP_REST_Request $request    Request.
	 * @return  WP_REST_Response|WP_Error
	 */
	public function delete_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$template_id = absint( $request['id'] );

		try {
			$this->template_service->delete_template( $template_id );
		} catch ( \InvalidArgumentException $exception ) {
			$message = $exception->getMessage();

			if ( 'Template not found.' === $message ) {
				return new WP_Error(
					'ossr_template_not_found',
					'Template not found.',
					array( 'status' => 404 )
				);
			}

			return new WP_Error(
				'ossr_template_invalid_request',
				$message,
				array( 'status' => 400 )
			);
		} catch ( \RuntimeException $exception ) {
			unset( $exception );

			return new WP_Error(
				'ossr_template_delete_failed',
				'Failed to delete template.',
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'deleted' => true,
			),
			200
		);
	}
}

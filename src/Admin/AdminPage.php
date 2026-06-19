<?php
/**
 * Admin page handler.
 *
 * @package SimpleSalesReports
 */

declare(strict_types=1);

namespace OmoikaneWorks\SimpleSalesReports\Admin;

use OmoikaneWorks\SimpleSalesReports\Reports\ReportPeriodResolver;
use OmoikaneWorks\SimpleSalesReports\Reports\ReportPeriods;
use OmoikaneWorks\SimpleSalesReports\Reports\SalesReportBuilder;
use OmoikaneWorks\SimpleSalesReports\Reports\SalesReportRenderer;
use OmoikaneWorks\SimpleSalesReports\Templates\TemplateRepository;
use OmoikaneWorks\SimpleSalesReports\Templates\TemplateTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Handles admin menu and pages.
 */
final class AdminPage {

	/**
	 * Menu parent slug.
	 *
	 * @var string
	 */
	private const MENU_PARENT_SLUG = 'welcart-simple-report';

	/**
	 * Menu sales slug.
	 *
	 * @var string
	 */
	private const MENU_SALES_SLUG = 'omoikane-simple-sales-reports';

	/**
	 * Required capability.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'ossr_view_sales_report';

	/**
	 * Nonce name.
	 *
	 * @var string
	 */
	private const NONCE_NAME = 'ossr_nonce';

	/**
	 * Default admin view.
	 *
	 * @var string
	 */
	private const DEFAULT_VIEW = 'report';

	/**
	 * Request parameter name for admin view.
	 *
	 * @var string
	 */
	private const VIEW_PARAM = 'view';

	/**
	 * Sales report renderer.
	 *
	 * @var SalesReportRenderer
	 */
	private SalesReportRenderer $sales_report_renderer;

	/**
	 * Report period resolver.
	 *
	 * @var ReportPeriodResolver
	 */
	private ReportPeriodResolver $period_resolver;

	/**
	 * Sales report builder.
	 *
	 * @var SalesReportBuilder
	 */
	private SalesReportBuilder $sales_report_builder;

	/**
	 * Admin page hooks.
	 *
	 * @var array<int, string>
	 */
	private array $admin_page_hooks = array();

	/**
	 * Request parameter name for template ID.
	 *
	 * @var string
	 */
	private const TEMPLATE_ID_PARAM = 'template_id';

	/**
	 * Template repository.
	 *
	 * @var TemplateRepository
	 */
	private TemplateRepository $template_repository;

	/**
	 * Selected sales report template option name.
	 *
	 * @var string
	 */
	private const SELECTED_TEMPLATE_ID_OPTION = 'ossr_sales_report_template_id';

	/**
	 * Constructor.
	 *
	 * @param   SalesReportRenderer  $sales_report_renderer Sales report renderer.
	 * @param   ReportPeriodResolver $period_resolver       Report period resolver.
	 * @param   SalesReportBuilder   $sales_report_builder  Sales report builder.
	 * @param   TemplateRepository   $template_repository   Template Repository.
	 */
	public function __construct(
		SalesReportRenderer $sales_report_renderer,
		ReportPeriodResolver $period_resolver,
		SalesReportBuilder $sales_report_builder,
		TemplateRepository $template_repository,
	) {
		$this->sales_report_renderer = $sales_report_renderer;
		$this->period_resolver       = $period_resolver;
		$this->sales_report_builder  = $sales_report_builder;
		$this->template_repository   = $template_repository;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return  void
	 */
	public function register_hooks(): void {
		add_action(
			'admin_menu',
			array( $this, 'register_admin_menu' )
		);
		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_admin_assets' )
		);
	}

	/**
	 * Register admin menu.
	 *
	 * @return  void
	 */
	public function register_admin_menu(): void {
		$this->add_admin_page_hook(
			add_menu_page(
				__( 'Simple Reports', 'omoikane-simple-sales-reports' ),
				__( 'Simple Reports', 'omoikane-simple-sales-reports' ),
				self::CAPABILITY,
				self::MENU_PARENT_SLUG,
				array( $this, 'render_sales_report_page' ),
				'dashicons-media-spreadsheet',
				56
			)
		);

		$this->add_admin_page_hook(
			add_submenu_page(
				self::MENU_PARENT_SLUG,
				__( 'Sales Reports', 'omoikane-simple-sales-reports' ),
				__( 'Sales Reports', 'omoikane-simple-sales-reports' ),
				self::CAPABILITY,
				self::MENU_SALES_SLUG,
				array( $this, 'render_sales_report_page' )
			)
		);

		remove_submenu_page( self::MENU_PARENT_SLUG, self::MENU_PARENT_SLUG );
	}

	/**
	 * Add admin page hook suffix.
	 *
	 * @param   bool|string $hook_suffix    Admin page hook suffix.
	 * @return  void
	 */
	private function add_admin_page_hook( bool|string $hook_suffix ): void {
		if ( ! is_string( $hook_suffix ) ) {
			return;
		}

		$this->admin_page_hooks[] = $hook_suffix;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param   string $hook_suffix    Current page hook suffix.
	 * @return  void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->admin_page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'ossr-admin',
			plugins_url( 'assets/css/admin.css', OSSR_PLUGIN_FILE ),
			array(),
			OSSR_VERSION
		);
	}

	/**
	 * Render sales report page.
	 *
	 * @return  void
	 */
	public function render_sales_report_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'omoikane-simple-sales-reports' )
			);
		}

		$current_view = $this->resolve_current_view();

		echo '<div class="wrap">';
		echo '<h1 class="ossr-no-print">' . esc_html__( 'Sales Reports', 'omoikane-simple-sales-reports' ) . '</h1>';
		echo '<div class="ossr-admin-layout">';

		$this->render_inner_navigation( $current_view );

		echo '<div class="ossr-admin-main">';

		if ( self::DEFAULT_VIEW === $current_view ) {
			$this->render_report_generation_view();
		} else {
			/**
			 * Fires when rendering an extended sales report admin view.
			 *
			 * @param   string  $current_view   Current admin view.
			 */
			do_action( 'ossr_render_sales_report_admin_view', $current_view );
		}

		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Get verified request parameters.
	 *
	 * @return array<string, string>
	 */
	private function get_verified_request(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::NONCE_NAME ] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = sanitize_text_field( wp_unslash( $_GET[ self::NONCE_NAME ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return array();
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$request = array(
			'period'      => isset( $_GET['period'] )
				? sanitize_key( wp_unslash( (string) $_GET['period'] ) )
				: '',
			'start_date'  => isset( $_GET['start_date'] )
				? sanitize_text_field( wp_unslash( (string) $_GET['start_date'] ) )
				: '',
			'end_date'    => isset( $_GET['end_date'] )
				? sanitize_text_field( wp_unslash( (string) $_GET['end_date'] ) )
				: '',
			'template_id' => isset( $_GET[ self::TEMPLATE_ID_PARAM ] )
				? sanitize_text_field( wp_unslash( (string) $_GET[ self::TEMPLATE_ID_PARAM ] ) )
				: '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $request;
	}

	/**
	 * Render period form.
	 *
	 * @param   array<string, string> $period               Report period.
	 * @param   int                   $selected_template_id Selected template ID.
	 * @return  void
	 */
	private function render_period_form(
		array $period,
		int $selected_template_id
	): void {

		$current_period = $period['period'] ?? ReportPeriods::PREVIOUS_MONTH;
		$start_date     = $period['start_date'] ?? '';
		$end_date       = $period['end_date'] ?? '';

		echo '<form method="get" class="ossr-period-form ossr-no-print">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::MENU_SALES_SLUG ) . '" />';
		echo '<input type="hidden" name="' . esc_attr( self::VIEW_PARAM ) . '" value="' . esc_attr( self::DEFAULT_VIEW ) . '" />';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<fieldset>';
		echo '<legend class="ossr-period-form__legend">' . esc_html__( 'Period', 'omoikane-simple-sales-reports' ) . '</legend>';
		$this->render_period_radio(
			ReportPeriods::CURRENT_MONTH,
			__( 'This month', 'omoikane-simple-sales-reports' ),
			$current_period
		);
		$this->render_period_radio(
			ReportPeriods::PREVIOUS_MONTH,
			__( 'Previous month', 'omoikane-simple-sales-reports' ),
			$current_period
		);
		echo '<label class="ossr-period-form__radio">';
		echo '<input type="radio" name="period" value="' . esc_attr( ReportPeriods::CUSTOM ) . '" ' . checked( $current_period, ReportPeriods::CUSTOM, false ) . ' />';
		echo ' ' . esc_html__( 'Custom Period', 'omoikane-simple-sales-reports' );
		echo '</label>';
		echo '<input type="date" name="start_date" value="' . esc_attr( $start_date ) . '" />';
		echo ' <span aria-hidden="true">～</span> ';
		echo '<input type="date" name="end_date" value="' . esc_attr( $end_date ) . '" />';
		echo '</fieldset>';

		$this->render_template_select( $selected_template_id );

		echo '<p class="ossr-period-form__actions">';
		echo '<button type="submit" class="button button-secondary">';
		echo esc_html__( 'Show', 'omoikane-simple-sales-reports' );
		echo '</button>';
		echo '</p>';
		echo '</form>';
	}

	/**
	 * Render period radio.
	 *
	 * @param   string $value          Radio value.
	 * @param   string $label          Radio label.
	 * @param   string $current_period Current period.
	 * @return  void
	 */
	private function render_period_radio( string $value, string $label, string $current_period ): void {
		echo '<label class="ossr-period-form__radio">';
		echo '<input type="radio" name="period" value="' . esc_attr( $value ) . '" ' . checked( $current_period, $value, false ) . ' />';
		echo ' ' . esc_html( $label );
		echo '</label>';
	}

	/**
	 * Render template select.
	 *
	 * @param   int $selected_template_id   Selected template ID.
	 * @return  void
	 */
	private function render_template_select( int $selected_template_id ): void {
		$templates = $this->template_repository->find_selectable_by_type(
			TemplateTypes::SALES_REPORT
		);

		if ( array() === $templates ) {
			return;
		}

		echo '<fieldset class="ossr-period-form__template">';
		echo '<legend class="ossr-period-form__legend">' . esc_html__( 'Template', 'omoikane-simple-sales-reports' ) . '</legend>';
		echo '<select name="' . esc_attr( self::TEMPLATE_ID_PARAM ) . '">';

		foreach ( $templates as $template ) {
			$id      = isset( $template['id'] ) ? (int) $template['id'] : 0;
			$name    = isset( $template['name'] ) ? (string) $template['name'] : '';
			$version = isset( $template['version'] ) ? (string) $template['version'] : '';

			if ( 0 === $id ) {
				continue;
			}

			$label = ( '' !== $version )
				? sprintf( '%s v%s', $name, $version )
				: $name;

			echo '<option value="' . esc_attr( (string) $id ) . '" ' . selected( $selected_template_id, $id, false ) . '>';
			echo esc_html( $label );
			echo '</option>';
		}

		echo '</select>';
		echo '</fieldset>';
	}

	/**
	 * Render inner navigation.
	 *
	 * @param   string $current_view   Current view.
	 * @return  void
	 */
	private function render_inner_navigation( string $current_view ): void {
		$menu_items = $this->get_inner_navigation_items();

		echo '<nav class="ossr-admin-nav ossr-no-print">';

		foreach ( $menu_items as $view => $item ) {
			$label = isset( $item['label'] ) ? (string) $item['label'] : '';
			$url   = isset( $item['url'] ) ? (string) $item['url'] : '';

			$class_names = array( 'ossr-admin-nav__item' );

			if ( $current_view === $view ) {
				$class_names[] = 'is-active';
			}

			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( implode( ' ', $class_names ) ) . '">';
			echo esc_html( $label );
			echo '</a>';
		}

		echo '</nav>';
	}

	/**
	 * Get inner navigation items.
	 *
	 * @return  array<string, array<string, string>>
	 */
	private function get_inner_navigation_items(): array {
		$items = array(
			self::DEFAULT_VIEW => array(
				'label' => __( 'Generate report', 'omoikane-simple-sales-reports' ),
				'url'   => add_query_arg(
					array(
						'page'           => self::MENU_SALES_SLUG,
						self::VIEW_PARAM => self::DEFAULT_VIEW,
					),
					admin_url( 'admin.php' )
				),
			),
		);

		/**
		 * Filters sales report admin inner navigation items.
		 *
		 * @param   array<string, array<string, string>>    $items  Inner navigation items.
		 */
		return apply_filters(
			'ossr_sales_report_admin_menu_items',
			$items
		);
	}

	/**
	 * Resolve current admin view.
	 *
	 * @return  string
	 */
	private function resolve_current_view(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$view = isset( $_GET[ self::VIEW_PARAM ] )
			? sanitize_key( wp_unslash( (string) $_GET[ self::VIEW_PARAM ] ) )
			: self::DEFAULT_VIEW;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$allowed_views = array_keys( $this->get_inner_navigation_items() );

		if ( ! in_array( $view, $allowed_views, true ) ) {
			return self::DEFAULT_VIEW;
		}

		return $view;
	}

	/**
	 * Render report generation view.
	 *
	 * @return void
	 */
	private function render_report_generation_view(): void {
		$request              = $this->get_verified_request();
		$selected_template_id = $this->resolve_selected_template_id( $request );
		$period               = $this->period_resolver->resolve( $request );
		$view_data            = $this->sales_report_builder->build( $period );
		$report_html          = $this->sales_report_renderer->render_sales_report(
			$view_data,
			$selected_template_id
		);

		$this->render_period_form( $period, $selected_template_id );

		echo '<div class="ossr-print-actions ossr-no-print">';
		echo '<button type="button" class="button button-primary" onclick="window.print();">';
		echo esc_html__( 'Print', 'omoikane-simple-sales-reports' );
		echo '</button>';
		echo '</div>';

		echo '<p class="description ossr-no-print">';
		echo esc_html__( 'If dates or URLs appear when saving as PDF, turn off "Headers and footers" in the print dialog.', 'omoikane-simple-sales-reports' );
		echo '</p>';

		// The report template is rendered from a trusted system template and escaped by Mustache.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $report_html;
	}

	/**
	 * Resolve selected template ID.
	 *
	 * @param   array<string, string> $request    Request parameters.
	 * @return  int
	 */
	private function resolve_selected_template_id( array $request ): int {
		$request_template_id = isset( $request[ self::TEMPLATE_ID_PARAM ] )
			? absint( $request[ self::TEMPLATE_ID_PARAM ] )
			: 0;

		if ( $request_template_id > 0 && $this->is_valid_sales_report_template_id( $request_template_id ) ) {
			update_option( self::SELECTED_TEMPLATE_ID_OPTION, $request_template_id );

			return $request_template_id;
		}

		$saved_template_id = absint( get_option( self::SELECTED_TEMPLATE_ID_OPTION, 0 ) );

		if ( $saved_template_id > 0 && $this->is_valid_sales_report_template_id( $saved_template_id ) ) {
			return $saved_template_id;
		}

		$default_template = $this->template_repository->find_default_sales_report_template();

		if ( null === $default_template || ! isset( $default_template['id'] ) ) {
			return 0;
		}

		$default_template_id = absint( $default_template['id'] );

		if ( $default_template_id > 0 ) {
			update_option( self::SELECTED_TEMPLATE_ID_OPTION, $default_template_id );
		}

		return $default_template_id;
	}

	/**
	 * Check whether template ID is valid sales report template.
	 *
	 * @param   int $template_id    Template ID.
	 * @return  bool
	 */
	private function is_valid_sales_report_template_id( int $template_id ): bool {
		if ( $template_id <= 0 ) {
			return false;
		}

		$template = $this->template_repository->find_by_id( $template_id );

		return (
			null !== $template
			&& isset( $template['type'] )
			&& TemplateTypes::SALES_REPORT === (string) $template['type']
		);
	}
}

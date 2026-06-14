<?php
/**
 * Fake WP_REST_Request.
 *
 * @package SimpleSalesReports
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Fake WP_REST_Request.
	 */
	class WP_REST_Request implements \ArrayAccess {

		/**
		 * Request params.
		 *
		 * @var   array<string, mixed>
		 */
		private array $params = array();

		/**
		 * Get request parameter.
		 *
		 * @param   string $key    Parameter key.
		 * @return  string|null
		 */
		public function get_param( string $key ): ?string {
			return $this->params[ $key ] ?? null;
		}

		/**
		 * Set request parameter.
		 *
		 * @param   string $key    Parameter key.
		 * @param   mixed  $value  Parameter value.
		 * @return  void
		 */
		public function set_param( string $key, mixed $value ): void {
			$this->params[ $key ] = $value;
		}

		// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

		/**
		 * Whenever an offset exists.
		 *
		 * @param   mixed $offset Offset.
		 * @return  bool
		 */
		public function offsetExists( mixed $offset ): bool {
			return isset( $this->params[ (string) $offset ] );
		}

		/**
		 * Get an offset.
		 *
		 * @param   mixed $offset Offset.
		 * @return  mixed
		 */
		public function offsetGet( mixed $offset ): mixed {
			return $this->params[ (string) $offset ];
		}

		/**
		 * Set an offset.
		 *
		 * @param   mixed $offset Offset.
		 * @param   mixed $value  Value.
		 * @return  void
		 */
		public function offsetSet( mixed $offset, mixed $value ): void {
			$this->params[ (string) $offset ] = $value;
		}

		/**
		 * Unset an offset.
		 *
		 * @param   mixed $offset Offset.
		 * @return  void
		 */
		public function offsetUnset( mixed $offset ): void {
			unset( $this->params[ (string) $offset ] );
		}

		// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	}
}

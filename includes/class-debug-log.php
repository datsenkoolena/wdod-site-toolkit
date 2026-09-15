<?php
/**
 * Read, tail, clear and download the WordPress debug log.
 *
 * @package WDOD\SiteToolkit
 */

namespace WDOD\SiteToolkit;

defined( 'ABSPATH' ) || exit;

/**
 * Class Debug_Log.
 */
class Debug_Log {

	/**
	 * Default number of bytes returned by tail().
	 *
	 * @var int
	 */
	const DEFAULT_TAIL_BYTES = 262144;

	/**
	 * Resolves the log path the same way wp_debug_mode() does.
	 *
	 * WP_DEBUG_LOG can be true/"1" (wp-content/debug.log) or a custom path.
	 *
	 * @return string Absolute path (the file may not exist yet).
	 */
	public function get_path() {
		$path = WP_CONTENT_DIR . '/debug.log';

		if ( defined( 'WP_DEBUG_LOG' ) ) {
			$value = WP_DEBUG_LOG;

			if ( is_string( $value ) && ! in_array( strtolower( $value ), array( '', '0', '1', 'true', 'false' ), true ) ) {
				$path = $value;
			}
		}

		return wp_normalize_path( $path );
	}

	/**
	 * Whether the log file exists.
	 *
	 * @return bool
	 */
	public function exists() {
		return is_file( $this->get_path() );
	}

	/**
	 * Whether the file can be truncated / written by PHP.
	 *
	 * @return bool
	 */
	public function is_writable() {
		$path = $this->get_path();

		if ( is_file( $path ) ) {
			return wp_is_writable( $path );
		}

		return wp_is_writable( dirname( $path ) );
	}

	/**
	 * File size in bytes (0 when missing).
	 *
	 * @return int
	 */
	public function size() {
		if ( ! $this->exists() ) {
			return 0;
		}

		clearstatcache( true, $this->get_path() );

		$size = filesize( $this->get_path() );

		return false === $size ? 0 : (int) $size;
	}

	/**
	 * Returns the last $max_bytes of the file without loading the whole file.
	 *
	 * @param int $max_bytes Maximum number of bytes to read from the end.
	 * @return array{content: string, truncated: bool, size: int, exists: bool}
	 */
	public function tail( $max_bytes = self::DEFAULT_TAIL_BYTES ) {
		$max_bytes = max( 1024, (int) $max_bytes );
		$result    = array(
			'content'   => '',
			'truncated' => false,
			'size'      => 0,
			'exists'    => $this->exists(),
		);

		if ( ! $result['exists'] || ! is_readable( $this->get_path() ) ) {
			return $result;
		}

		$size           = $this->size();
		$result['size'] = $size;

		if ( 0 === $size ) {
			return $result;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading from the end of a large log needs fseek; WP_Filesystem cannot do that.
		$handle = fopen( $this->get_path(), 'rb' );

		if ( false === $handle ) {
			return $result;
		}

		$offset = 0;

		if ( $size > $max_bytes ) {
			$offset              = $size - $max_bytes;
			$result['truncated'] = true;
		}

		if ( 0 !== $offset ) {
			fseek( $handle, $offset );
		}

		$content = '';

		while ( ! feof( $handle ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- see above.
			$chunk = fread( $handle, 8192 );

			if ( false === $chunk ) {
				break;
			}

			$content .= $chunk;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see above.
		fclose( $handle );

		// Drop the first, most likely partial, line when we started mid-file.
		if ( $result['truncated'] ) {
			$newline = strpos( $content, "\n" );

			if ( false !== $newline ) {
				$content = substr( $content, $newline + 1 );
			}
		}

		$result['content'] = $content;

		return $result;
	}

	/**
	 * Returns the last N lines of the log.
	 *
	 * @param int $lines Number of lines.
	 * @return string[]
	 */
	public function tail_lines( $lines = 50 ) {
		$lines = max( 1, (int) $lines );
		$tail  = $this->tail( max( self::DEFAULT_TAIL_BYTES, $lines * 512 ) );

		if ( '' === $tail['content'] ) {
			return array();
		}

		$all = preg_split( '/\r\n|\r|\n/', rtrim( $tail['content'] ) );

		return array_slice( $all, -$lines );
	}

	/**
	 * Truncates the log file. Only works when the file is writable.
	 *
	 * @return bool
	 */
	public function clear() {
		if ( ! $this->exists() ) {
			return true;
		}

		if ( ! $this->is_writable() ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Truncating in place keeps the inode PHP's error_log is writing to.
		$handle = fopen( $this->get_path(), 'r+b' );

		if ( false === $handle ) {
			return false;
		}

		$ok = ftruncate( $handle, 0 );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see above.
		fclose( $handle );

		clearstatcache( true, $this->get_path() );

		return (bool) $ok;
	}

	/**
	 * Streams the log to the browser as a download and terminates the request.
	 *
	 * Capability and nonce checks are the caller's responsibility.
	 *
	 * @return void
	 */
	public function download() {
		$path = $this->get_path();

		if ( ! $this->exists() || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'The debug log does not exist or is not readable.', 'wdod-site-toolkit' ), 404 );
		}

		$filename = sanitize_file_name( wp_parse_url( home_url(), PHP_URL_HOST ) . '-debug-' . gmdate( 'Ymd-His' ) . '.log' );

		nocache_headers();
		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . $this->size() );
		header( 'X-Content-Type-Options: nosniff' );

		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming a potentially large file chunk by chunk.
		$handle = fopen( $path, 'rb' );

		if ( false === $handle ) {
			wp_die( esc_html__( 'The debug log could not be opened.', 'wdod-site-toolkit' ), 500 );
		}

		while ( ! feof( $handle ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- see above.
			$chunk = fread( $handle, 65536 );

			if ( false === $chunk ) {
				break;
			}

			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw file download served as text/plain attachment.
			flush();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see above.
		fclose( $handle );

		exit;
	}
}

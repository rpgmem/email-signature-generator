<?php
/**
 * Self-hosted updater fed by this plugin's GitHub Releases.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator\Update;

/**
 * Teaches WordPress's native plugin-update check to see GitHub Releases.
 *
 * It injects the latest release into the `update_plugins` transient, pointing
 * at the built `email-signature-generator-X.Y.Z.zip` release asset (never the source
 * zipball, whose folder name would not match the installed plugin). It also
 * serves the "View details" modal and verifies the package's SHA-256 before
 * WordPress installs it.
 *
 * Posture: notify, and let the admin opt in natively. It never forces
 * `auto_update_plugin`. Because the plugin is placed in `no_update` when it is
 * current, WordPress shows its own per-plugin auto-update toggle, and that
 * toggle is the opt-in.
 *
 * The repository is public, so no token is needed. A 12h site transient plus
 * ETag conditional requests keep the check well inside the unauthenticated
 * 60/h rate limit.
 *
 * Ported from Total Mail Queue's `GithubUpdater` (rpgmem/total-mail-queue), itself from Free Form Certificate.
 */
final class GithubUpdater {

	/**
	 * `owner/repo` this plugin ships from.
	 */
	public const REPO = 'rpgmem/email-signature-generator';

	/**
	 * Plugin slug, which is also the details-modal slug and the asset prefix.
	 */
	public const SLUG = 'email-signature-generator';

	/**
	 * Site-transient key caching the parsed latest release and its ETag.
	 */
	public const CACHE_KEY = 'email_signature_generator_github_update';

	/**
	 * Cache TTL in seconds (12h).
	 */
	private const CACHE_TTL = 43200;

	/**
	 * Register the update hooks.
	 *
	 * Wired unconditionally, not behind `is_admin()`: the update scan also
	 * runs from cron.
	 */
	public static function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'checkForUpdate' ) );
		add_filter( 'plugins_api', array( self::class, 'pluginInfo' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( self::class, 'verifyDownload' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( self::class, 'flushCache' ), 10, 0 );
	}

	/**
	 * Inject the GitHub release into the plugin-update transient.
	 *
	 * An available update lands in `->response`. When the plugin is current,
	 * it lands in `->no_update`, which WordPress requires before it shows the
	 * auto-update toggle for a self-hosted plugin.
	 *
	 * @param mixed $transient The `update_plugins` transient object.
	 * @return mixed
	 */
	public static function checkForUpdate( $transient ) {
		$obj = ( $transient instanceof \stdClass ) ? $transient : null;
		if ( null === $obj || ! apply_filters( 'email_signature_generator_github_updater_enabled', true ) ) {
			return $transient;
		}

		// WordPress fills ->checked during a real scan. Bail otherwise, so an
		// unrelated write of the transient never gets a fabricated entry.
		$plugin_file = self::pluginFile();
		$checked     = isset( $obj->checked ) && is_array( $obj->checked ) ? $obj->checked : array();
		if ( empty( $checked[ $plugin_file ] ) ) {
			return $transient;
		}

		// Dashboard → Updates → "Check again" drops the 12h cache so the scan
		// asks GitHub instead of serving a stale release.
		if ( self::isForcedCheck() ) {
			delete_site_transient( self::CACHE_KEY );
		}

		$release = self::latestRelease();
		if ( null === $release ) {
			return $transient;
		}

		$installed = (string) $checked[ $plugin_file ];

		if ( ! isset( $obj->response ) || ! is_array( $obj->response ) ) {
			$obj->response = array();
		}
		if ( ! isset( $obj->no_update ) || ! is_array( $obj->no_update ) ) {
			$obj->no_update = array();
		}

		if ( version_compare( $installed, $release['version'], '<' ) ) {
			$obj->response[ $plugin_file ] = self::updateObject( $release, $release['version'] );
			unset( $obj->no_update[ $plugin_file ] );
		} else {
			$obj->no_update[ $plugin_file ] = self::updateObject( $release, $installed );
			unset( $obj->response[ $plugin_file ] );
		}

		return $transient;
	}

	/**
	 * Serve the "View details" modal (`plugins_api`) for this plugin.
	 *
	 * @param mixed  $res    Default result (false) or another plugin's object.
	 * @param string $action The plugins_api action.
	 * @param mixed  $args   Query args (expects ->slug).
	 * @return mixed
	 */
	public static function pluginInfo( $res, $action = '', $args = null ) {
		if ( 'plugin_information' !== $action || ! ( $args instanceof \stdClass ) ) {
			return $res;
		}
		if ( empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $res;
		}

		$release = self::latestRelease();
		if ( null === $release ) {
			return $res;
		}

		$compat              = self::compat( $release );
		$info                = new \stdClass();
		$info->name          = 'Email Signature Generator';
		$info->slug          = self::SLUG;
		$info->version       = $release['version'];
		$info->author        = '<a href="https://github.com/rpgmem">Alex Meusburger</a>';
		$info->homepage      = $release['url'];
		$info->requires      = $compat['requires'];
		$info->tested        = $compat['tested'];
		$info->requires_php  = $compat['requires_php'];
		$info->last_updated  = $release['published_at'];
		$info->download_link = $release['package'];
		$info->icons         = self::icons();
		$info->sections      = array(
			'changelog' => wpautop( esc_html( $release['changelog'] ) ),
		);

		return $info;
	}

	/**
	 * Verify the SHA-256 of our release asset before WordPress installs it.
	 *
	 * Returning a file path short-circuits WordPress's own download with the
	 * verified file. Returning a WP_Error aborts the install on a mismatch.
	 * A package that is not ours, or a release with no published digest,
	 * passes through untouched.
	 *
	 * @param mixed  $reply    Short-circuit value (false = let WordPress download).
	 * @param string $package  The package URL WordPress is about to download.
	 * @param mixed  $upgrader The WP_Upgrader instance (unused).
	 * @return mixed
	 */
	public static function verifyDownload( $reply, $package = '', $upgrader = null ) {
		unset( $upgrader );

		$release = self::latestRelease();
		if ( null === $release || '' === $release['sha256'] || $package !== $release['package'] ) {
			return $reply;
		}

		if ( ! function_exists( 'download_url' ) ) {
			return $reply;
		}

		$tmp = download_url( $package );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$actual = (string) hash_file( 'sha256', (string) $tmp );
		if ( ! hash_equals( strtolower( $release['sha256'] ), strtolower( $actual ) ) ) {
			wp_delete_file( (string) $tmp );
			return new \WP_Error(
				'email_signature_generator_updater_checksum',
				__( 'Update package integrity check (SHA-256) failed. Installation aborted.', 'email-signature-generator' )
			);
		}

		return $tmp;
	}

	/**
	 * Drop the cached release after any plugin update completes.
	 */
	public static function flushCache(): void {
		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * Plugin basename (folder/main-file) as WordPress keys it.
	 *
	 * Derived rather than hard-coded, so an install whose folder was renamed
	 * still matches its own entry in `->checked`.
	 */
	private static function pluginFile(): string {
		return plugin_basename( self::mainFile() );
	}

	/**
	 * Absolute path of the plugin's main file.
	 */
	private static function mainFile(): string {
		return \EmailSignatureGenerator\Plugin::file();
	}

	/**
	 * Build the object WordPress expects in response / no_update.
	 *
	 * @param array<string, string> $release     Parsed release payload.
	 * @param string                $new_version Version to advertise.
	 */
	private static function updateObject( array $release, string $new_version ): \stdClass {
		$compat              = self::compat( $release );
		$obj                 = new \stdClass();
		$obj->id             = 'github.com/' . self::REPO;
		$obj->slug           = self::SLUG;
		$obj->plugin         = self::pluginFile();
		$obj->new_version    = $new_version;
		$obj->url            = $release['url'];
		$obj->package        = $release['package'];
		$obj->requires       = $compat['requires'];
		$obj->tested         = $compat['tested'];
		$obj->requires_php   = $compat['requires_php'];
		$obj->upgrade_notice = $release['upgrade_notice'];
		$obj->icons          = self::icons();
		return $obj;
	}

	/**
	 * The plugin icon WordPress draws on Dashboard → Updates, in the plugin
	 * list and in the "View details" modal; without it, core shows its grey
	 * plug placeholder.
	 *
	 * The URLs point at the INSTALLED copy rather than at GitHub, so the
	 * admin's browser fetches nothing from outside the site. That is safe
	 * because the code building this object is itself the installed copy: an
	 * install old enough to lack the files also lacks this method.
	 *
	 * @return array{'1x': string, '2x': string, svg: string}
	 */
	private static function icons(): array {
		$base = plugin_dir_url( self::mainFile() ) . 'assets/img/brand/';
		return array(
			'1x'  => $base . 'icon-128.png',
			'2x'  => $base . 'icon-256.png',
			'svg' => $base . 'icon.svg',
		);
	}

	/**
	 * Compatibility fields for the release being offered.
	 *
	 * They come from the offered release's own `readme.txt`, because the
	 * installed copy describes the version already running, which is exactly
	 * what an operator deciding on the update does not need. The installed
	 * files are the per-field fallback for a readme that could not be fetched
	 * or did not carry that header.
	 *
	 * Two installed files, not one: `Tested up to` exists only in
	 * `readme.txt`, and the plugin header is what WordPress itself reads.
	 *
	 * @param array<string, string> $release Release payload being offered.
	 * @return array{requires: string, tested: string, requires_php: string}
	 */
	private static function compat( array $release ): array {
		$plugin = get_file_data(
			self::mainFile(),
			array(
				'requires'     => 'Requires at least',
				'requires_php' => 'Requires PHP',
			)
		);
		$readme = get_file_data(
			dirname( self::mainFile() ) . '/readme.txt',
			array( 'tested' => 'Tested up to' )
		);

		$out = array(
			'requires'     => trim( (string) ( $plugin['requires'] ?? '' ) ),
			'tested'       => trim( (string) ( $readme['tested'] ?? '' ) ),
			'requires_php' => trim( (string) ( $plugin['requires_php'] ?? '' ) ),
		);

		foreach ( array_keys( $out ) as $field ) {
			if ( '' !== ( $release[ $field ] ?? '' ) ) {
				$out[ $field ] = $release[ $field ];
			}
		}

		return $out;
	}

	/**
	 * Is this scan an admin-initiated "Check again" (force-check)?
	 *
	 * A read-only presence check. WordPress core puts `force-check` on its own
	 * nonce-protected link, and nothing is mutated here beyond our own cache.
	 */
	private static function isForcedCheck(): bool {
		return isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only; core's "Check again" link carries its own nonce.
	}

	/**
	 * The parsed latest release, from cache or a fresh fetch.
	 *
	 * @return array<string, string>|null
	 */
	private static function latestRelease(): ?array {
		$cache      = get_site_transient( self::CACHE_KEY );
		$cache      = is_array( $cache ) ? $cache : array();
		$fetched_at = isset( $cache['fetched_at'] ) ? (int) $cache['fetched_at'] : 0;
		$cached     = self::toPayload( $cache['payload'] ?? null );

		if ( $fetched_at > 0 && ( time() - $fetched_at ) < self::CACHE_TTL && null !== $cached ) {
			return $cached;
		}

		$etag    = isset( $cache['etag'] ) ? (string) $cache['etag'] : '';
		$fetched = self::fetchRelease( $etag );

		// A failed fetch reuses a stale payload when there is one: the update
		// scan must never break over GitHub being unreachable.
		if ( null === $fetched ) {
			return $cached;
		}

		// 304 Not Modified: refresh the TTL, keep the payload and the ETag.
		if ( 'not_modified' === $fetched['status'] ) {
			$cache['fetched_at'] = time();
			set_site_transient( self::CACHE_KEY, $cache, self::CACHE_TTL );
			return $cached;
		}

		$payload = self::toPayload( $fetched['payload'] ?? null );
		set_site_transient(
			self::CACHE_KEY,
			array(
				'payload'    => $payload,
				'etag'       => $fetched['etag'] ?? '',
				'fetched_at' => time(),
			),
			self::CACHE_TTL
		);

		return $payload;
	}

	/**
	 * Normalise a raw payload into a typed shape, or null without a package URL.
	 *
	 * @param mixed $raw Raw payload (from the cache or a fetch).
	 * @return array<string, string>|null
	 */
	private static function toPayload( $raw ): ?array {
		if ( ! is_array( $raw ) || empty( $raw['package'] ) ) {
			return null;
		}

		$out = array( 'package' => (string) $raw['package'] );
		foreach ( array( 'version', 'sha256', 'url', 'changelog', 'published_at', 'requires', 'tested', 'requires_php', 'upgrade_notice' ) as $key ) {
			$out[ $key ] = (string) ( $raw[ $key ] ?? '' );
		}
		return $out;
	}

	/**
	 * Fetch `releases/latest` with an ETag conditional request.
	 *
	 * @param string $etag Prior ETag, sent as If-None-Match (a 304 costs no rate limit).
	 * @return array{status: string, payload?: array<string, string>, etag?: string}|null
	 */
	private static function fetchRelease( string $etag ): ?array {
		$repo    = (string) apply_filters( 'email_signature_generator_github_updater_repo', self::REPO );
		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'email-signature-generator-updater',
		);
		if ( '' !== $etag ) {
			$headers['If-None-Match'] = $etag;
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/releases/latest', $repo ),
			array(
				'timeout' => 10,
				'headers' => $headers,
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 304 === $code ) {
			return array( 'status' => 'not_modified' );
		}
		if ( 200 !== $code ) {
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return null;
		}

		$payload = self::parseRelease( $data );
		if ( null === $payload ) {
			return null;
		}

		$payload  = array_merge( $payload, self::fetchReleaseReadme( $repo, (string) $data['tag_name'] ) );
		$new_etag = wp_remote_retrieve_header( $response, 'etag' );

		return array(
			'status'  => 'ok',
			'payload' => $payload,
			'etag'    => is_string( $new_etag ) ? $new_etag : '',
		);
	}

	/**
	 * Reduce a `releases/latest` response to what the updater needs.
	 *
	 * Selects the built `email-signature-generator-*.zip` asset and its SHA-256 digest.
	 * Returns null when no such asset is attached.
	 *
	 * @param array<string, mixed> $data Decoded GitHub release.
	 * @return array<string, string>|null
	 */
	public static function parseRelease( array $data ): ?array {
		$package = '';
		$sha256  = '';

		$assets = isset( $data['assets'] ) && is_array( $data['assets'] ) ? $data['assets'] : array();
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$name = (string) ( $asset['name'] ?? '' );
			if ( 1 === preg_match( '/^' . preg_quote( self::SLUG, '/' ) . '-.*\.zip$/', $name ) ) {
				$package = (string) ( $asset['browser_download_url'] ?? '' );
				$digest  = (string) ( $asset['digest'] ?? '' );
				if ( 0 === strpos( $digest, 'sha256:' ) ) {
					$sha256 = substr( $digest, 7 );
				}
				break;
			}
		}

		if ( '' === $package ) {
			return null;
		}

		return array(
			'version'      => ltrim( (string) ( $data['tag_name'] ?? '' ), 'v' ),
			'package'      => $package,
			'sha256'       => $sha256,
			'url'          => (string) ( $data['html_url'] ?? '' ),
			'changelog'    => (string) ( $data['body'] ?? '' ),
			'published_at' => (string) ( $data['published_at'] ?? '' ),
		);
	}

	/**
	 * Read the offered release's compatibility headers and Upgrade Notice from
	 * its own `readme.txt`, at the release tag.
	 *
	 * One extra request per new release: it runs only on a full (200) fetch,
	 * never on a 304 or a cache hit. `raw.githubusercontent.com` is not the
	 * API, so it does not spend the rate limit. Every failure returns empty
	 * fields, which then fall back to the installed copy.
	 *
	 * @param string $repo `owner/repo`.
	 * @param string $tag  Release tag, e.g. `v1.0.0`.
	 * @return array{requires: string, tested: string, requires_php: string, upgrade_notice: string}
	 */
	private static function fetchReleaseReadme( string $repo, string $tag ): array {
		$response = wp_remote_get(
			sprintf( 'https://raw.githubusercontent.com/%s/%s/readme.txt', $repo, rawurlencode( $tag ) ),
			array(
				'timeout' => 10,
				'headers' => array( 'User-Agent' => 'email-signature-generator-updater' ),
			)
		);

		$body = '';
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = (string) wp_remote_retrieve_body( $response );
		}

		return self::readmeMetadata( $body );
	}

	/**
	 * Parse the compatibility headers and the Upgrade Notice from a readme.
	 *
	 * Headers are read the way `get_file_data()` reads them: the first 8 KB,
	 * case-insensitive, rest of the line. This text came over the network and
	 * WordPress feeds these fields to `version_compare()`, so a value that is
	 * not a bare version number is dropped, and the notice is reduced to
	 * plain text.
	 *
	 * @param string $contents Full `readme.txt` text.
	 * @return array{requires: string, tested: string, requires_php: string, upgrade_notice: string}
	 */
	public static function readmeMetadata( string $contents ): array {
		$head   = substr( $contents, 0, 8192 );
		$fields = array(
			'requires'     => 'Requires at least',
			'tested'       => 'Tested up to',
			'requires_php' => 'Requires PHP',
		);

		$out = array();
		foreach ( $fields as $key => $label ) {
			$value = '';
			if ( 1 === preg_match( '/^[ \t\/*#@]*' . preg_quote( $label, '/' ) . ':(.*)$/mi', $head, $m ) ) {
				$value = trim( $m[1] );
			}
			$out[ $key ] = 1 === preg_match( '/^\d+(\.\d+)*$/', $value ) ? $value : '';
		}

		$out['upgrade_notice'] = wp_strip_all_tags( self::noticeFrom( $contents ) );

		return $out;
	}

	/**
	 * Extract the Upgrade Notice entry from a `readme.txt` body.
	 *
	 * Reads the first entry under `== Upgrade Notice ==` and stops at the
	 * first blank line: a notice is one paragraph, and whatever follows it in
	 * the section is prose about the notice rather than part of it.
	 *
	 * @param string $contents Full `readme.txt` text.
	 * @return string Empty when the section is absent or malformed.
	 */
	private static function noticeFrom( string $contents ): string {
		if ( ! preg_match( '/^==\s*Upgrade Notice\s*==\s*$(.*?)(?=^==\s|\z)/ms', $contents, $section ) ) {
			return '';
		}
		if ( ! preg_match( '/^=\s*\S+\s*=\s*$\R+(.+?)(?=\R\s*\R|\z)/ms', $section[1], $entry ) ) {
			return '';
		}
		return trim( $entry[1] );
	}
}

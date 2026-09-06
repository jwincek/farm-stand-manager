<?php
/**
 * Editor panels must not call a hook conditionally.
 *
 * Every sidebar panel bails when the post type is not its own. Nine of them
 * did that *before* calling useEntityProp, which breaks React's rules of
 * hooks: the hook then runs on some renders and not others.
 *
 * It worked, which is why it survived. The post type does not change within a
 * mount, so in practice a panel either always returned early or never did and
 * the hook count stayed consistent. It would have stopped working the moment
 * any condition varied during a mount — "Rendered fewer hooks than expected",
 * and the editor goes down with it.
 *
 * The rule is mechanical and worth asserting rather than remembering.
 */

declare(strict_types=1);

final class EditorHookOrderTest extends WP_UnitTestCase {

	/** Anything that is a React hook by the naming convention React enforces. */
	private const HOOK = '/\buse[A-Z]\w*\(/';

	/**
	 * @return string[]
	 */
	private function scripts(): array {
		return (array) glob( dirname( __DIR__, 2 ) . '/assets/js/editor-*.js' );
	}

	/**
	 * Panel function bodies, keyed by "file / name".
	 *
	 * @return array<string, string>
	 */
	private function panels(): array {
		$out = [];

		foreach ( $this->scripts() as $file ) {
			preg_match_all(
				"/\tfunction (\w+Panel)\(\) \{(.*?)\n\t\}\n/s",
				(string) file_get_contents( $file ),
				$matches,
				PREG_SET_ORDER
			);

			foreach ( $matches as $match ) {
				$out[ basename( $file ) . ' / ' . $match[1] ] = $match[2];
			}
		}

		return $out;
	}

	public function test_there_are_panels_to_check(): void {
		// A regex that quietly matched nothing would make every assertion
		// below pass by vacuum.
		$this->assertGreaterThan( 10, count( $this->panels() ) );
	}

	public function test_no_panel_calls_a_hook_after_its_early_return(): void {
		$bad = [];

		foreach ( $this->panels() as $where => $body ) {
			// The guard proper — an `if` at panel indentation testing the post
			// type. Deliberately not "the first `return null;`": an early
			// version of this test matched one inside a useSelect callback and
			// reported a panel that was already correct.
			if ( ! preg_match( "/\n\t\tif \([^)]*postType !==.*?\n\t\t\}\n/s", $body, $guard, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			$after = substr( $body, $guard[0][1] + strlen( $guard[0][0] ) );

			if ( preg_match_all( self::HOOK, $after, $found ) ) {
				$bad[] = $where . ' → ' . implode( ', ', array_unique( $found[0] ) );
			}
		}

		$this->assertSame(
			[],
			$bad,
			"These panels call a hook only sometimes, which React cannot account for:\n"
				. implode( "\n", $bad )
		);
	}

	public function test_meta_is_read_defensively(): void {
		// It is now read before the post-type check, so it can be undefined
		// for one render. Without the fallback the derived lines below it
		// throw on a property of undefined.
		$bare = [];

		foreach ( $this->scripts() as $file ) {
			if ( str_contains( (string) file_get_contents( $file ), 'const meta = _meta[ 0 ];' ) ) {
				$bare[] = basename( $file );
			}
		}

		$this->assertSame( [], $bare, implode( ', ', $bare ) );
	}
}

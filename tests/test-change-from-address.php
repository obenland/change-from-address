<?php
/**
 * Change From Address tests.
 *
 * @package change-from-address
 */

/**
 * Tests for the cefko_* functions in change-from-address.php.
 */
class Test_Change_From_Address extends WP_UnitTestCase {

	/**
	 * Reset both options between tests so each starts from a clean slate.
	 */
	public function tear_down() {
		delete_option( 'cefko_email_from_address' );
		delete_option( 'cefko_email_from_name' );
		parent::tear_down();
	}

	/**
	 * Tests that `cefko_mail_from_address` returns the saved option when one
	 * is set.
	 */
	public function test_mail_from_address_returns_option_when_set() {
		update_option( 'cefko_email_from_address', 'site@example.com' );

		$this->assertSame(
			'site@example.com',
			cefko_mail_from_address( 'wordpress@example.com' )
		);
	}

	/**
	 * Tests that `cefko_mail_from_address` passes the input through when no
	 * option is set, so other plugins/themes can still filter the value.
	 */
	public function test_mail_from_address_passes_through_when_unset() {
		$this->assertSame(
			'wordpress@example.com',
			cefko_mail_from_address( 'wordpress@example.com' )
		);
	}

	/**
	 * Tests that `cefko_mail_from_address` does not overwrite the input when
	 * the option is set to an empty string (which `update_option` may persist
	 * via the sanitize callback).
	 */
	public function test_mail_from_address_passes_through_when_option_empty() {
		update_option( 'cefko_email_from_address', '' );

		$this->assertSame(
			'wordpress@example.com',
			cefko_mail_from_address( 'wordpress@example.com' )
		);
	}

	/**
	 * Tests that the `wp_mail_from` filter chain returns the saved address.
	 *
	 * Covers integration via the registered filter, not just the callback.
	 */
	public function test_wp_mail_from_filter_returns_saved_address() {
		update_option( 'cefko_email_from_address', 'noreply@example.com' );

		$this->assertSame(
			'noreply@example.com',
			apply_filters( 'wp_mail_from', 'wordpress@example.com' )
		);
	}

	/**
	 * Tests that `cefko_mail_from_name` returns the saved option when set.
	 */
	public function test_mail_from_name_returns_option_when_set() {
		update_option( 'cefko_email_from_name', 'Acme Corp' );

		$this->assertSame(
			'Acme Corp',
			cefko_mail_from_name( 'WordPress' )
		);
	}

	/**
	 * Tests that `cefko_mail_from_name` passes the input through when no
	 * option is set.
	 */
	public function test_mail_from_name_passes_through_when_unset() {
		$this->assertSame(
			'WordPress',
			cefko_mail_from_name( 'WordPress' )
		);
	}

	/**
	 * Tests that `cefko_mail_from_name` does not overwrite the input when
	 * the option is empty.
	 */
	public function test_mail_from_name_passes_through_when_option_empty() {
		update_option( 'cefko_email_from_name', '' );

		$this->assertSame(
			'WordPress',
			cefko_mail_from_name( 'WordPress' )
		);
	}

	/**
	 * Tests that the `wp_mail_from_name` filter chain returns the saved name.
	 */
	public function test_wp_mail_from_name_filter_returns_saved_name() {
		update_option( 'cefko_email_from_name', 'Acme Corp' );

		$this->assertSame(
			'Acme Corp',
			apply_filters( 'wp_mail_from_name', 'WordPress' )
		);
	}

	/**
	 * Tests that `cefko_mail_from_action_links` adds a Settings link to the
	 * existing array without dropping any existing links.
	 */
	public function test_mail_from_action_links_adds_settings_link() {
		$existing = array(
			'<a href="#deactivate">Deactivate</a>',
		);

		$links = cefko_mail_from_action_links( $existing );

		$this->assertCount( 2, $links );
		$this->assertSame( $existing[0], $links[0] );
		$this->assertStringContainsString( 'options-general.php#email-from-address', $links[1] );
		$this->assertStringContainsString( '>Settings<', $links[1] );
	}

	/**
	 * Tests that `cefko_register_settings` registers both options under the
	 * `general` settings group via the Settings API.
	 *
	 * `register_setting` populates `$wp_registered_settings` (since 4.7),
	 * which we read directly to avoid asserting on UI output.
	 */
	public function test_register_settings_registers_both_options() {
		// `cefko_register_settings` is normally fired by admin_init; trigger it
		// directly so this test does not depend on the global hook order.
		cefko_register_settings();

		global $wp_registered_settings;
		$this->assertArrayHasKey( 'cefko_email_from_address', $wp_registered_settings );
		$this->assertArrayHasKey( 'cefko_email_from_name', $wp_registered_settings );
		$this->assertSame( 'general', $wp_registered_settings['cefko_email_from_address']['group'] );
		$this->assertSame( 'general', $wp_registered_settings['cefko_email_from_name']['group'] );
	}

	/**
	 * Tests that `cefko_load_textdomain` is wired to the `init` action so the
	 * plugin's translations actually load.
	 */
	public function test_load_textdomain_is_hooked() {
		$this->assertNotFalse( has_action( 'init', 'cefko_load_textdomain' ) );
	}

	/**
	 * Tests that wp_mail() actually configures PHPMailer with the saved
	 * From address and name end-to-end.
	 *
	 * Captures the From values from the `phpmailer_init` action, which
	 * fires after WordPress has applied the `wp_mail_from` /
	 * `wp_mail_from_name` filter chains and called `setFrom()` on the
	 * mailer. Whether the underlying send succeeds is not relevant — we
	 * only assert on what the mailer was configured with.
	 */
	public function test_wp_mail_uses_configured_from_address_and_name() {
		update_option( 'cefko_email_from_address', 'noreply@example.com' );
		update_option( 'cefko_email_from_name', 'Acme Corp' );

		$captured = array();
		$listener = static function ( $mailer ) use ( &$captured ) {
			// PHPMailer exposes its public state via PascalCase properties.
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$captured = array(
				'from' => $mailer->From,
				'name' => $mailer->FromName,
			);
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		};
		add_action( 'phpmailer_init', $listener );

		try {
			wp_mail( 'recipient@example.com', 'Subject', 'Body' );
		} finally {
			remove_action( 'phpmailer_init', $listener );
		}

		$this->assertSame( 'noreply@example.com', $captured['from'] ?? null );
		$this->assertSame( 'Acme Corp', $captured['name'] ?? null );
	}
}

<?php
	class Tests_GFDigest_Main extends WP_UnitTestCase {
		
		private $digest;

		/** Activate the plugin, mock all the things */
		public function setUp() {
			parent::setUp();

			/* Activate GravityForms */
			require_once WP_PLUGIN_DIR . '/gravityforms/gravityforms.php';
			require_once WP_PLUGIN_DIR . '/gravityforms/export.php';
			GFForms::setup();

			/* Import some ready-made forms */
			$this->assertEquals( GFExport::import_file( dirname( __FILE__ ) . '/forms.xml' ), 2 );

			/* Add a faster turnaround schedule */
			add_filter( 'cron_schedules', function( $s ) {
				$s['minute'] = array( 'interval' => 60, 'display' => 'Minutely' );
				return $s;
			} );

			/* Get an instance of our plugin */
			$this->digest = new GFDigestNotifications;
		}

		/** Stray schedules after an update/install will spoil everything */
		public function test_remove_all_schedules() {
			wp_schedule_event( 1, 'minute', 'gf_digest_send_notifications', array( 'unknown' ) );
			wp_schedule_event( 2, 'hourly', 'gf_digest_send_notifications', array( 1 ) );

			$this->digest->remove_schedules();

			foreach ( _get_cron_array() as $schedule )
				$this->assertArrayNotHasKey( 'gf_digest_send_notifications', $schedule );
		}

		/** Rescheduling should be done all the time */
		public function test_reschedule_existing_simple() {
			wp_set_current_user( 1 );

			/* Activate digetsts for a form */
			$_POST['form_notification_enable_digest'] = true;
			$_POST['save'] = true;
			$_GET['id'] = '1';
			$_POST['form_notification_digest_screen'] = true;
			$_POST['form_notification_enable_digest'] = true;
			$_POST['form_notification_digest_emails'] = 'testing@digests.lo, testing2@digests.lo';
			$_POST['form_notification_digest_interval'] = 'minute';
			$_POST['form_notification_digest_group'] = '';

			$this->digest->init(); // TODO: A better way to add

			$this->digest->reschedule_existing();

			$this->assertEquals( wp_get_schedule( 'gf_digest_send_notifications', array( 1 ) ), 'minute' );
		}

		/** Group rescheduling should also be correct */
		public function test_reschedule_existing_groups() {
			wp_set_current_user( 1 );

			/* Activate digetsts for a form */
			$_POST['form_notification_enable_digest'] = true;
			$_POST['save'] = true;
			$_GET['id'] = '1';
			$_POST['form_notification_digest_screen'] = true;
			$_POST['form_notification_enable_digest'] = true;
			$_POST['form_notification_digest_emails'] = 'testing@digests.lo, testing2@digests.lo';
			$_POST['form_notification_digest_interval'] = 'minute';
			$_POST['form_notification_digest_group'] = 'sales';

			$this->digest->init(); // TODO: A better way to add

			/* Activate digetsts for a form */
			$_POST['form_notification_enable_digest'] = true;
			$_POST['save'] = true;
			$_GET['id'] = '2';
			$_POST['form_notification_digest_screen'] = true;
			$_POST['form_notification_enable_digest'] = true;
			$_POST['form_notification_digest_emails'] = 'testing@digests.lo, testing2@digests.lo';
			$_POST['form_notification_digest_interval'] = 'minute';
			$_POST['form_notification_digest_group'] = 'sales';

			$this->digest->init(); // TODO: A better way to add

			$this->digest->reschedule_existing();

			$schedule_1 = wp_get_schedule( 'gf_digest_send_notifications', array( 1 ) );
			$schedule_2 = wp_get_schedule( 'gf_digest_send_notifications', array( 2 ) );

			$this->assertTrue( $schedule_1 == 'minute' || $schedule_2 == 'minute' );
			$this->assertTrue( $schedule_1 xor $schedule_2 );

			/* Activate digetsts for a form */
			$_POST['form_notification_enable_digest'] = true;
			$_POST['save'] = true;
			$_GET['id'] = '2';
			$_POST['form_notification_digest_screen'] = true;
			$_POST['form_notification_enable_digest'] = true;
			$_POST['form_notification_digest_emails'] = 'testing@digests.lo, testing2@digests.lo';
			$_POST['form_notification_digest_interval'] = 'hourly';
			$_POST['form_notification_digest_group'] = 'sales';

			$this->digest->init(); // TODO: A better way to add

			$this->digest->reschedule_existing();

			$schedule_1 = wp_get_schedule( 'gf_digest_send_notifications', array( 1 ) );
			$schedule_2 = wp_get_schedule( 'gf_digest_send_notifications', array( 2 ) );

			$this->assertEquals( $schedule_1, 'minute' );
			$this->assertEquals( $schedule_2, 'hourly' );
		}

		/** Test a simple schedule addition, see how that works out */
		public function test_add_simple_schedule_settings() {

			wp_set_current_user( 1 );

			/* Activate digetsts for a form */
			$_POST['form_notification_enable_digest'] = true;
			$_POST['save'] = true;
			$_GET['id'] = '1';
			$_POST['form_notification_digest_screen'] = true;
			$_POST['form_notification_enable_digest'] = true;
			$_POST['form_notification_digest_emails'] = 'testing@digests.lo, testing2@digests.lo';
			$_POST['form_notification_digest_interval'] = 'minute';
			$_POST['form_notification_digest_group'] = '';

			$this->digest->init();

			/* Assert that all those match up */
			$form = RGFormsModel::get_form_meta( 1 );

			$this->assertTrue( $form['digests']['enable_digest'] );
			$this->assertEquals( $form['digests']['digest_emails'], array( 'testing@digests.lo','testing2@digests.lo' ) );
			$this->assertEquals( $form['digests']['digest_interval'], 'minute' );
			$this->assertEquals( $form['digests']['digest_group'], '' );

			/* Assert that a cronjob has been scheduled */
			$this->assertEquals( wp_get_schedule( 'gf_digest_send_notifications', array( 1 ) ), 'minute' );
		}

		/** Test e-mail notifications */
		public function test_email_notification_simple() {
			wp_set_current_user( 1 );

			/* Activate digetsts for a form */
			$_POST['form_notification_enable_digest'] = true;
			$_POST['save'] = true;
			$_GET['id'] = '2';
			$_POST['form_notification_digest_screen'] = true;
			$_POST['form_notification_enable_digest'] = true;
			$_POST['form_notification_digest_emails'] = 'testing@digests.lo, testing2@digests.lo';
			$_POST['form_notification_digest_interval'] = 'minute';
			$_POST['form_notification_digest_group'] = '';

			$this->digest->init(); // TODO: A better way to add

			$_POST[] = array(); $_GET[] = array(); $null = null;
			$_POST['input_1'] = 'Gary'; $_POST['input_2'] = 'yesterday';
			RGFormsModel::save_lead( RGFormsModel::get_form_meta( 2 ), $null );

			$this->digest->send_notifications( 2 );

			$form = RGFormsModel::get_form_meta( 2 );
			$this->assertEquals( $form['digests']['digest_last_sent'], 1 );

			global $phpmailer;
			$this->assertNotEmpty( $phpmailer->mock_sent );
			$this->assertEquals( count( $phpmailer->mock_sent ), 2 );
		}

		/** Group notifications */
		public function test_email_notification_groups() {
			wp_set_current_user( 1 );

			/* Activate digetsts for a form */
			$_POST['form_notification_enable_digest'] = true;
			$_POST['save'] = true;
			$_GET['id'] = '1';
			$_POST['form_notification_digest_screen'] = true;
			$_POST['form_notification_enable_digest'] = true;
			$_POST['form_notification_digest_emails'] = 'testing@digests.lo, testing2@digests.lo';
			$_POST['form_notification_digest_interval'] = 'minute';
			$_POST['form_notification_digest_group'] = 'sales';

			$this->digest->init(); // TODO: A better way to add

			/* Activate digetsts for a form */
			$_POST['form_notification_enable_digest'] = true;
			$_POST['save'] = true;
			$_GET['id'] = '2';
			$_POST['form_notification_digest_screen'] = true;
			$_POST['form_notification_enable_digest'] = true;
			$_POST['form_notification_digest_emails'] = 'testing@digests.lo, testing2@digests.lo, out@digests.lo';
			$_POST['form_notification_digest_interval'] = 'minute';
			$_POST['form_notification_digest_group'] = 'sales';

			$this->digest->init(); // TODO: A better way to add

			$_POST[] = array(); $_GET[] = array(); $null = null;
			$_POST['input_1'] = 'One'; $_POST['input_2'] = array( 'two', 'three' );
			RGFormsModel::save_lead( RGFormsModel::get_form_meta( 1 ), $null );

			$_POST[] = array(); $_GET[] = array(); $null = null;
			$_POST['input_1'] = 'Gary'; $_POST['input_2'] = 'yesterday';
			RGFormsModel::save_lead( RGFormsModel::get_form_meta( 2 ), $null );
			$_POST[] = array(); $_GET[] = array(); $null = null;
			$_POST['input_1'] = 'Larry'; $_POST['input_2'] = 'tomorrow';
			RGFormsModel::save_lead( RGFormsModel::get_form_meta( 2 ), $null );

			/* Enable CSV mode to allow us to parse the body */
			define( 'GF_DIGESTS_AS_CSV', true );

			/* Test the correct cron call */
			do_action_ref_array( 'gf_digest_send_notifications', array( 1 ) );

			$form = RGFormsModel::get_form_meta( 1 );
			$this->assertEquals( $form['digests']['digest_last_sent'], 1 );
			$form = RGFormsModel::get_form_meta( 2 );
			$this->assertEquals( $form['digests']['digest_last_sent'], 3 );

			global $phpmailer;
			$this->assertNotEmpty( $phpmailer->mock_sent );
			$this->assertEquals( count( $phpmailer->mock_sent ), 3 );

			/* Let's take a look here... (should be in a test of its own) */
			preg_match( '#filename="(.*)"#', $phpmailer->mock_sent[0]['body'], $matches );
			$filename = sys_get_temp_dir() . '/' . $matches[1];
			$csv = fopen( $filename, 'rb' );
			$this->assertEquals( fgetcsv( $csv ), array( 'Form: Test Form (#1)' ) );
			fgetcsv( $csv ); fgetcsv( $csv ); fgetcsv( $csv );
			$this->assertEquals( fgetcsv( $csv ), array( 'Form: Help (#2)' ) );
			fclose( $csv );
			unlink( $filename );
		}
	}
?>

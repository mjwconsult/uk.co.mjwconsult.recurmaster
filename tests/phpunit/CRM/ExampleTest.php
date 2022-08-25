<?php

use CRM_Recurmaster_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
// FIXME: require trait, should not have to hardcode path like this
require_once(__DIR__ . '/../../../../org.civicrm.smartdebit/Civi/Test/SmartdebitTestTrait.php');

class CRM_ExampleTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;
  use \Civi\Test\TestTrait;

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->install(array('org.civicrm.smartdebit','uk.co.mjwconsult.recurmaster'))
      ->apply();
  }

  public function setUp() {
    parent::setUp();
    $this->_masterProcessorId = $this->smartdebitPaymentProcessorCreate();
    $this->_slaveProcessorId = $this->slavePaymentProcessorCreate();
  }

  public function tearDown() {
    parent::tearDown();
  }

  public function slavePaymentProcessorCreate($params = array()) {
    $paymentProcessorType = $this->callAPISuccess('PaymentProcessorType', 'get', array('name' => "recurmaster_slave"));
    $processorParams = array(
      'domain_id' => '1',
      'name' => 'Slave',
      'payment_processor_type_id' => $paymentProcessorType['id'],
      'is_active' => '1',
      'is_test' => '0',
      'user_name' => 'slave',
      'password' => 'slave',
      'url_site' => 'https://example.org',
      'url_recur' => 'https://example.org',
      'class_name' => 'Payment_RecurmasterSlave',
      'billing_mode' => '1',
      'is_recur' => '1',
      'payment_type' => '1',
      'payment_instrument_id' => 'Debit Card'
    );
    $processorParams = array_merge($processorParams, $params);
    $processor = $this->callAPISuccess('PaymentProcessor', 'create', $processorParams);
    return $processor['id'];
  }

  /**
   * Example: Test that a version is returned.
   */
  public function testWellFormedVersion() {
    $this->assertRegExp('/^([0-9\.]|alpha|beta)*$/', \CRM_Utils_System::version());
  }

  /**
   * Example: Test that we're using a fake CMS.
   */
  public function testWellFormedUF() {
    $this->assertEquals('UnitTests', CIVICRM_UF);
  }

}

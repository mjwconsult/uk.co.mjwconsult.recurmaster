<?php
/*
 * Placeholder clas for offline recurring payments
 */

use CRM_Recurmaster_ExtensionUtil as E;

class CRM_Core_Payment_RecurmasterSlave extends CRM_Core_Payment {

  protected $_mode = NULL;

  protected $_params = array();

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  public function __construct($mode, &$paymentProcessor) {
    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('Master Recurring Slave processor');
  }

  /**
   * Are back office payments supported.
   *
   * @return bool
   */
  public function supportsBackOffice() {
    return TRUE;
  }

  /**
   * Checks if backoffice recurring edit is allowed
   *
   * @return bool
   */
  public function supportsEditRecurringContribution() {
    return TRUE;
  }

  /**
   * Should the first payment date be configurable when setting up back office recurring payments.
   *
   * We set this to false for historical consistency but in fact most new processors use tokens for recurring and can support this
   *
   * @return bool
   */
  protected function supportsFutureRecurStartDate() {
    return TRUE;
  }

  /**
   * Process payment
   *
   * Payment processors should set payment_status_id and trxn_id (if available).
   *
   * @param array $params
   *   Assoc array of input parameters for this transaction.
   *
   * @param string $component
   *
   * @return array
   *   Result array
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function doPayment(&$params, $component = 'contribute') {
    self::setRecurTransactionId($params);
    return $params;
  }



  /**
   * Change the subscription amount
   *
   * @param string $message
   * @param array $params
   *
   * @return bool
   * @throws \Exception
   */
  public function changeSubscriptionAmount(&$message = '', $params = array()) {
    // We need to set contributionRecurID for setRecurTransactionId as that is passed when triggered via doDirectPayment()
    $params['contributionRecurID'] = $params['id'];
    try {
      $recurRecord = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $params['id']]);
    }
    catch (CiviCRM_API3_Exception $e) {
      CRM_Core_Error::statusBounce('No recurring record! ' . $e->getMessage());
      return FALSE;
    }

    $recurRecord['master_recur'] = $recurRecord[CRM_Recurmaster_Utils::getMasterRecurIdCustomField(TRUE)];
    $recurRecord['description'] = $recurRecord[CRM_Recurmaster_Utils::getCustomByName('description')];
    $recurRecord['contributionRecurID'] = $recurRecord['id'];

    $params = array_merge($recurRecord, $params);

    $contributionDetails = civicrm_api3('Contribution', 'get', ['contribution_recur_id' => $recurRecord['id']]);
    if ($contributionDetails['count'] == 1) {
      // Got one contribution, check if we should update the start date.
      $contribution = CRM_Utils_Array::first($contributionDetails['values']);
      if (!CRM_Recurmaster_Utils::dateEquals($contribution['receive_date'], $params['start_date'])) {
        $contributionParams = [
          'id' => $contribution['id'],
          'receive_date' => $params['start_date'],
        ];
        civicrm_api3('Contribution', 'create', $contributionParams);
      }
    }

    return self::setRecurTransactionId($params);
  }

  /**
   * As the recur transaction is created before payment, we need to update it with our params after payment
   *
   * @param $params
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  private static function setRecurTransactionId(&$params) {
    if (!empty($params['contributionRecurID'])) {
      // Recurring transaction, so this is a recurring payment
      $params['id'] = $params['contributionRecurID'];
      $params[CRM_Recurmaster_Utils::getMasterRecurIdCustomField(TRUE)] = $params['master_recur'];
      $params[CRM_Recurmaster_Utils::getCustomByName('description')] = $params['description'];
      // Update the recurring payment
      civicrm_api3('ContributionRecur', 'create', $params);
      civicrm_api3('Job', 'process_recurmaster', array('recur_ids' => array($params['master_recur'])));
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Cancel the Subscription
   *
   * @param string $message
   * @param array $params
   *
   * @return bool
   */
  public function cancelSubscription(&$message = '', $params = []) {
    return TRUE;
  }

  function &error($errorCode = NULL, $errorMessage = NULL) {
    $e = CRM_Core_Error::singleton();
    if ($errorCode) {
      $e->push($errorCode, 0, NULL, $errorMessage);
    }
    else {
      $e->push(9001, 0, NULL, 'Unknown System Error.');
    }
    return $e;
  }

  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig() {
    return NULL;
  }

  /**
   * Override custom Payment Instrument validation
   *  to validate payment details
   *
   * @param array $params
   * @param array $errors
   *
   * @throws \Exception
   */
  public function validatePaymentInstrument($params, &$errors) {
    if (empty($params['master_recur'])) {
      $errors['master_recur'] = E::ts("Select a master payment or use a different processor");
    }
    return;
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return string
   */
  public function getPaymentTypeName() {
    return 'recurmaster_slave';
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return string
   */
  public function getPaymentTypeLabel() {
    return 'Payment';
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return array
   */
  public function getPaymentFormFields() {
    return array('master_recur');
  }


  /**
   * Return an array of all the details about the fields potentially required for payment fields.
   *
   * Only those determined by getPaymentFormFields will actually be assigned to the form
   *
   * @return array
   *   field metadata
   */
  public function getPaymentFormFieldsMetadata() {
    $contactId = CRM_Utils_Request::retrieve('cid', 'Integer');
    if (!isset($contactId)) {
      $contactId = CRM_Core_Session::singleton()->getLoggedInContactID();
    }
    $masterRecurs = array();
    if (!empty($contactId)) {
      $masterRecurs = CRM_Recurmaster_Master::getContactMasterRecurringContributionList($contactId);
    }

    return array(
      'master_recur' => array(
        'htmlType' => 'select',
        'name' => 'master_recur',
        'title' => ts('Add to existing Direct Debit'),
        'cc_field' => TRUE,
        'attributes' => $masterRecurs,
        // eg. array('1' => '1st', '8' => '8th', '21' => '21st'),
        'is_required' => TRUE
      ),
    );
  }

  /**
   * Get billing fields required for this processor.
   *
   * We apply the existing default of returning fields only for payment processor type 1. Processors can override to
   * alter.
   *
   * @param int $billingLocationID
   *
   * @return array
   */
  public function getBillingAddressFields($billingLocationID = NULL) {
    return array();
  }

  /**
   * Get an array of the fields that can be edited on the recurring contribution.
   *
   * @return array
   */
  public function getEditableRecurringScheduleFields() {
    return array(
      'amount',
      'frequency_interval',
      'frequency_unit',
      'start_date',
    );
  }
}


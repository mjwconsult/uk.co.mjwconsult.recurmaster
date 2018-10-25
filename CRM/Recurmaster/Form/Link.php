<?php

use CRM_Recurmaster_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Recurmaster_Form_Link extends CRM_Core_Form {

  /**
   * @var int Contact ID
   */
  private $_cid;

  /**
   * @var int Recurring contribution ID
   */
  private $_crid;

  /**
   * @var int Master Recurring contribution ID
   */
  private $masterRecurId;

  public function preProcess() {
    $this->_cid = CRM_Utils_Request::retrieve('cid', 'Positive');
    $this->_crid = CRM_Utils_Request::retrieve('crid', 'Positive');
    $this->assign('action', $this->_action);
  }

  public function buildQuickForm() {
    $elementNames = array();

    switch ($this->_action) {
      case CRM_Core_Action::ADD:
        CRM_Utils_System::setTitle('Link to master Recurring Contribution');
        $availableRecur = CRM_Recurmaster_Master::getContactMasterRecurringContributionList($this->_cid);
        if (!empty($availableRecur)) {
          $this->add('select', 'contribution_recur_id', ts('Recurring Contribution'), CRM_Recurmaster_Master::getContactMasterRecurringContributionList($this->_cid));
          $elementNames[] = 'contribution_recur_id';
        }
        else {
          CRM_Core_Error::statusBounce('This contact does not have any master recurring contributions.');
        }
        break;

      case CRM_Core_Action::DELETE:
        CRM_Utils_System::setTitle('Unlink from master Recurring Contribution');
        $contributionRecurRecords = civicrm_api3('ContributionRecur', 'getsingle', array(
          'id' => $this->_crid,
          'contact_id' => $this->_cid,
          'return' => array(CRM_Recurmaster_Utils::getMasterRecurIdCustomField()),
        ));
        $this->masterRecurId = $contributionRecurRecords[CRM_Recurmaster_Utils::getMasterRecurIdCustomField()];
        $currentRecur = CRM_Recurmaster_Master::getContactMasterRecurringContributionList($this->_cid, $this->masterRecurId);
        $this->assign('currentRecur', $currentRecur);
        break;
    }

    $this->add('hidden', 'cid');
    $this->add('hidden', 'crid');

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
      ),
    ));

    // export form elements
    $this->assign('elementNames', $elementNames);

    parent::buildQuickForm();
  }

  public function setDefaultValues() {
    $defaults['cid'] = $this->_cid;
    $defaults['crid'] = $this->_crid;
    return $defaults;
  }

  public function postProcess() {
    $values = $this->exportValues();

    $contributionRecurParams = array(
      'id' => $values['crid'],
    );
    switch ($this->_action) {
      case CRM_Core_Action::ADD:
        $this->masterRecurId = CRM_Utils_Array::value('contribution_recur_id', $values);
        $contributionRecurParams[CRM_Recurmaster_Utils::getMasterRecurIdCustomField()] = $this->masterRecurId;
        break;

      case CRM_Core_Action::DELETE:
        $contributionRecurParams[CRM_Recurmaster_Utils::getMasterRecurIdCustomField()] = '';
        break;

      default:
        throw new CRM_Core_Exception('Invalid action: ' . $this->_action);
    }

    civicrm_api3('ContributionRecur', 'create', $contributionRecurParams);
    if (!empty($this->masterRecurId)) {
      civicrm_api3('Job', 'process_recurmaster', array('recur_ids' => array($this->masterRecurId)));
    }

    if (empty((CRM_Utils_Request::retrieve('snippet', 'String')))) {
      // if $_REQUEST['snippet'] is set we are probably in popup context so don't redirect
      $url = CRM_Utils_System::url('civicrm/contact/view', 'action=browse&selectedChild=contribute&cid=' . $this->_cid);
      CRM_Utils_System::redirect($url);
    }
  }

  /**
   * Get list of recurring contribution records for contact
   * @param $contactID
   * @return mixed
   */
  public function getContactMasterRecurringContributions() {
    if (empty($this->_cid)) {
      return array();
    }

    $contributionRecurRecords = CRM_Recurmaster_Master::getContactMasterRecurringContributions($this->_cid);

    $cRecur = array();
    foreach ($contributionRecurRecords['values'] as $contributionRecur) {
      // Get payment processor name used for recurring contribution
      try {
        if (empty($contributionRecur['payment_processor_id'])) {
          $paymentProcessorName = 'Offline';
        }
        else {
          $processor = \Civi\Payment\System::singleton()
            ->getById($contributionRecur['payment_processor_id']);
          $processorDetails = $processor->getPaymentProcessor();
          $paymentProcessorName = $processorDetails['name'];
        }
      }
      catch (CiviCRM_API3_Exception $e) {
        // Invalid payment processor, ignore this recur record
        continue;
      }
      $contributionStatus = CRM_Core_PseudoConstant::getLabel('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $contributionRecur['contribution_status_id']);
      // Create display name for recurring contribution
      $cRecur[$contributionRecur['id']] = $paymentProcessorName . '/'
        . $contributionStatus . '/'
        . CRM_Utils_Money::format($contributionRecur['amount'],$contributionRecur['currency'])
        . '/every ' . $contributionRecur['frequency_interval'] . ' ' . $contributionRecur['frequency_unit']
        . '/' . CRM_Utils_Array::value('trxn_id', $contributionRecur);
    }
    return $cRecur;
  }

}

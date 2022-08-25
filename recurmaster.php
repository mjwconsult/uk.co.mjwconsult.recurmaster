<?php

require_once 'recurmaster.civix.php';
use CRM_Recurmaster_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function recurmaster_civicrm_config(&$config) {
  _recurmaster_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function recurmaster_civicrm_install() {
  // Enable MembershipType custom data
  $optionValue = [
    'name' => 'civicrm_membership_type',
    'label' => 'Membership Types',
    'value' => 'MembershipType',
  ];
  $optionValues = civicrm_api3('OptionValue', 'get', [
    'option_group_id' => 'cg_extend_objects',
    'name' => $optionValue['name'],
  ]);
  if (!$optionValues['count']) {
    civicrm_api3('OptionValue', 'create', [
      'option_group_id' => 'cg_extend_objects',
      'name' => $optionValue['name'],
      'label' => $optionValue['label'],
      'value' => $optionValue['value'],
    ]);
  }
  _recurmaster_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function recurmaster_civicrm_postInstall() {
  _recurmaster_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function recurmaster_civicrm_uninstall() {
  _recurmaster_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function recurmaster_civicrm_enable() {
  _recurmaster_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function recurmaster_civicrm_disable() {
  _recurmaster_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function recurmaster_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _recurmaster_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
 */
function recurmaster_civicrm_navigationMenu(&$menu) {
  $item[] =  array (
    'label' => ts('Master Recurring Contributions'), array('domain' => E::LONG_NAME),
    'name'       => E::SHORT_NAME,
    'url'        => 'civicrm/admin/recurmaster',
    'permission' => 'administer CiviCRM',
    'operator'   => NULL,
    'separator'  => NULL,
  );
  _recurmaster_civix_insert_navigation_menu($menu, 'Administer/CiviContribute', $item[0]);
  _recurmaster_civix_navigationMenu($menu);
}

function recurmaster_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
  //create a Send Invoice link with the context of the participant's order ID (a custom participant field)
  switch ($objectName) {
    case 'Contribution':
      switch ($op) {
        case 'contribution.selector.recurring':
          $crid = $values['crid'];
          $cid = $values['cid'];

          if (CRM_Recurmaster_Master::isMasterRecurByRecurId($crid)) {
            // We are a "Master" recurring contribution, so don't allow linking to others!
            return;
          }

          try {
            $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array(
              'id' => $crid,
            ));
          }
          catch (CiviCRM_API3_Exception $e) {
            return;
          }

          if (empty($contributionRecur[CRM_Recurmaster_Utils::getMasterRecurIdCustomField()])) {
            $links[] = array(
              'name' => ts('Link to Master Recurring Contribution'),
              'title' => ts('Link to Master Recurring Contribution'),
              'url' => 'civicrm/recurmaster/link',
              'qs' => "action=add&reset=1&cid={$cid}&selectedChild=contribute&crid={$crid}",
            );
          }
          else {
            $links[] = array(
              'name' => ts('Unlink from Master Recurring Contribution'),
              'title' => ts('Unlink from Master Recurring Contribution'),
              'url' => 'civicrm/recurmaster/link',
              'qs' => "action=delete&reset=1&cid={$cid}&selectedChild=contribute&crid={$crid}",
            );
          }

      }
      break;
  }
}

/**
 * Implementation of hook_civicrm_smartdebit_alterCreateVariableDDIParams
 * Called whenever a subscription at Smartdebit is being updated
 *
 * @param $recurParams
 * @param $smartDebitParams
 */
function recurmaster_civicrm_smartdebit_alterVariableDDIParams(&$recurParams, &$smartDebitParams, $op) {
  switch ($op) {
    case 'create':
    case 'edit':
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ': recurParams: ' . print_r($recurParams, TRUE), TRUE);
      // Calculate the regular payment amount
      $nextAmount = $recurParams['amount'];
      if ($nextAmount === NULL) {
        return;
      }
      // Set the regular payment amount
      CRM_Recurmaster_Payment_Smartdebit::alterDefaultPaymentAmount($smartDebitParams, $nextAmount);
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ': smartDebitParams: ' . print_r($smartDebitParams, TRUE), TRUE);
      break;

    case 'cancel':
      CRM_Recurmaster_Slave::cancelAllForMaster($recurParams['id']);
      break;

  }
}

/**
 * Implementation of hook_civicrm_smartdebit_updateRecurringContribution
 * Called when recurring contributions are updated by Smartdebit
 *
 * @param $recurContributionParams
 *
 * @throws \CiviCRM_API3_Exception
 * @throws \Exception
 */
function recurmaster_civicrm_smartdebit_updateRecurringContribution(&$recurContributionParams) {
  if (!CRM_Recurmaster_Master::isMasterRecurByPaymentProcessorId($recurContributionParams['payment_processor_id'])) {
    return;
  }
  // This is a master recur
  // Update the master recur financial type to the configured type
  $recurContributionParams['financial_type_id'] = CRM_Recurmaster_Settings::getValue('master_financial_type');
  // We don't allow changing frequency of master, so force it here
  $recurContributionParams['frequency_unit'] = 'month';
  $recurContributionParams['frequency_interval'] = '1';

  if (!empty(Civi::$statics['recurmaster']['slave']['recur_id'])) {
    // We have just created a linked slave recur for this master recur.
    // Update some params on the slave.
    $slaveRecurParams = civicrm_api3('ContributionRecur', 'getsingle', ['id' => Civi::$statics['recurmaster']['slave']['recur_id']]);

    if (CRM_Recurmaster_Utils::dateLessThan($slaveRecurParams['start_date'], $recurContributionParams['start_date'])) {
      $slaveRecurParams['start_date'] = $recurContributionParams['start_date'];
      civicrm_api3('ContributionRecur', 'create', $slaveRecurParams);
    }
    if (empty(Civi::$statics['recurmaster']['master']['contribution_id'])) {
      return;
    }
    $masterContributionDetails = civicrm_api3('Contribution', 'getsingle', ['id' => Civi::$statics['recurmaster']['master']['contribution_id']]);
    // This creates/updates the slave contribution
    CRM_Recurmaster_Slave::updateAllByMasterContribution($masterContributionDetails);
  }
  // This checks if any updates need to be made at the payment processor provider
  CRM_Recurmaster_Payment::checkSubscription($recurContributionParams);
}

/**
 * @param $op
 * @param $objectName
 * @param $id
 * @param $params
 */
function recurmaster_civicrm_pre($op, $objectName, $id, &$params) {
  switch ($objectName) {
    case 'ContributionRecur':
      if (($op !== 'create') && ($op !== 'edit')) {
        return;
      }
      if ($op === 'create') {
        if (CRM_Recurmaster_Master::isMasterRecurByPaymentProcessorId($params['payment_processor_id'])) {
          $params = CRM_Recurmaster_Utils::validateHookParams($params);
          $params = CRM_Recurmaster_Master::setMasterFrequency($params);
        }
      }
      elseif ($op === 'edit') {
        if (CRM_Recurmaster_Slave::isSlaveRecur($id)) {
          $params = CRM_Recurmaster_Utils::validateHookParams($params);
          $params = CRM_Recurmaster_Slave::setNextScheduledDate($params);
        }
        elseif (CRM_Recurmaster_Master::isMasterRecurByRecurId($id)) {
          $params = CRM_Recurmaster_Utils::validateHookParams($params);
          $params = CRM_Recurmaster_Master::setMasterFrequency($params);
        }
      }
  }
}

/**
 * If a contribution is created/edited create/edit the slave contributions
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 *
 * @throws \CiviCRM_API3_Exception
 */
function recurmaster_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  switch ($objectName) {
    case 'Membership':
      if ($op !== 'create') {
        return;
      }
      // Change membership record to be linked to slave recur instead of master
      $slaveRecurId = Civi::$statics['recurmaster']['slave']['recur_id'];
      //$masterRecurId = Civi::$statics['recurmaster']['master']['recur_id'];
      $membershipId = $objectId;
      if (empty($slaveRecurId)) {
        return;
      }
      civicrm_api3('Membership', 'create', array(
        'id' => $membershipId,
        'contribution_recur_id' => $slaveRecurId,
      ));
      break;

    case 'Contribution':
      if (empty($objectRef->contribution_recur_id)) {
        return;
      }
      if (!in_array($op, array('create', 'edit'))) {
        return;
      }
      $contributionDetails = json_decode(json_encode($objectRef), TRUE);
      if (!CRM_Recurmaster_Master::isMasterRecurByRecurId($contributionDetails['contribution_recur_id'])) {
        return;
      }

      // Add a callback to update the master contribution once we've finished here
      $callbackParams = [
        'entity' => $objectName,
        'op' => $op,
        'id' => $contributionDetails['id'],
        'details' => $contributionDetails,
      ];
      if (CRM_Core_Transaction::isActive()) {
        CRM_Core_Transaction::addCallback(CRM_Core_Transaction::PHASE_POST_COMMIT, 'recurmaster_callback_civicrm_post', [$callbackParams]);
      }
      else {
        recurmaster_callback_civicrm_post($callbackParams);
      }
      break;

    case 'ContributionRecur':
      if (($op !== 'create') && ($op !== 'edit')) {
        return;
      }
      $contributionRecurDetails = json_decode(json_encode($objectRef), TRUE);
      if (!CRM_Recurmaster_Master::isMasterRecurByRecurId($contributionRecurDetails['id'])) {
        return;
      }

      // Add a callback to update the master recur once we've finished here
      $callbackParams = [
        'entity' => $objectName,
        'op' => $op,
        'id' => $contributionRecurDetails['id'],
        'details' => $contributionRecurDetails,
      ];
      if (CRM_Core_Transaction::isActive()) {
        CRM_Core_Transaction::addCallback(CRM_Core_Transaction::PHASE_POST_COMMIT, 'recurmaster_callback_civicrm_post', [$callbackParams]);
      }
      else {
        recurmaster_callback_civicrm_post($callbackParams);
      }
      break;
  }
}

/**
 * Callback function for hook_civicrm_post
 *
 * @param $params
 *
 * @throws \CiviCRM_API3_Exception
 */
function recurmaster_callback_civicrm_post($params) {
  switch ($params['entity']) {
    case 'Contribution':
      switch ($params['op']) {
        case 'create':
          // Update the master contribution financial type to the configured type
          $masterContributionParams = array(
            'id' => $params['id'],
            'financial_type_id' => CRM_Recurmaster_Settings::getValue('master_financial_type'),
          );
          $contributionResult = civicrm_api3('Contribution', 'create', $masterContributionParams);
          Civi::$statics['recurmaster']['master']['contribution_id'] = $contributionResult['id'];
          Civi::$statics['recurmaster']['master']['contribution_source'] = $contributionResult['values'][$contributionResult['id']]['source'];
          break;

        case 'edit':
          // Update the receive date for the initial slave contribution if we just created it
          if (!empty(Civi::$statics['recurmaster']['slave']['contribution_id'])) {
            $slaveParams = [
              'id' => Civi::$statics['recurmaster']['slave']['contribution_id'],
              'receive_date' => $params['details']['receive_date'],
              'contribution_status_id' => $params['details']['contribution_status_id'],
              'source' => $params['details']['source'],
            ];
            if (!empty(Civi::$statics['recurmaster']['master']['contribution_source'])) {
              $slaveParams['source'] = Civi::$statics['recurmaster']['master']['contribution_source'];
            }
            civicrm_api3('Contribution', 'create', $slaveParams);
          }
          break;

      }
      break;

    case 'ContributionRecur':
      switch ($params['op']) {
        case 'edit':
          if (!empty(Civi::$statics['recurmaster']['slave']['recur_id'])) {
            //$slaveRecurParams =
            // TODO: Do we pull description from contribution and put in description field for master/slave
          }
          // Calculate the master amount.  If it has changed, update the recur
          civicrm_api3('Job', 'process_recurmaster', [
            'recur_ids' => $params['id'],
          ]);
          return;

        case 'create':
          // We need to create a slave recur/contribution
          Civi::$statics['recurmaster']['master']['recur_id'] = $params['id'];
          // Remove fields we don't want to copy from master to slave
          $fieldsToUnset = array('id', 'trxn_id', 'invoice_id');
          foreach ($fieldsToUnset as $field) {
            unset($params['details'][$field]);
          }
          // Get the id of the first slave payment processor (we only support one).
          try {
            $slaveProcessor = civicrm_api3('PaymentProcessor', 'getsingle', array(
              'payment_processor_type_id' => "recurmaster_slave",
              'options' => array('limit' => 1, 'sort' => "id ASC"),
            ));
          } catch (Exception $e) {
            Civi::log()->error('You must create a slave payment processor to use master recurring payments');
            return;
          }

          // Add the master ID and other parameters to the slave
          $params['details'][CRM_Recurmaster_Utils::getMasterRecurIdCustomField()] = $params['id'];
          $params['details']['payment_processor_id'] = $slaveProcessor['id'];
          $params['details']['frequency_unit'] = Civi::$statics['recurmaster']['slave']['frequency_unit'];
          $params['details']['frequency_interval'] = Civi::$statics['recurmaster']['slave']['frequency_interval'];
          if (!empty(Civi::$statics['recurmaster']['master']['contribution_source'])) {
            $params['details'][CRM_Recurmaster_Utils::getCustomByName('description')]
              = Civi::$statics['recurmaster']['master']['contribution_source'];
          }

          CRM_Recurmaster_Slave::create($params['details']);
          break;
      }
      break;
  }
}

/**
 * Intercept form functions
 * @param $formName
 * @param $form
 */
function recurmaster_civicrm_buildForm($formName, &$form) {
  if ($formName = 'CRM_Contribute_Form_UpdateSubscription') {
    $recurId = $form->getVar('contributionRecurID');
    if (empty($recurId)) {
      return;
    }
    if (CRM_Recurmaster_Master::isMasterRecurByRecurId($recurId)) {
      // Ok, this is a master recur.  Freeze some fields that should not be modified
      $form->getElement('amount')->freeze();
      $form->getElement('installments')->freeze();
      $form->getElement('start_date')->freeze();
      $form->getElement('frequency_interval')->freeze();
      $form->getElement('frequency_unit')->freeze();
    }
    elseif(CRM_Recurmaster_Slave::isSlaveRecur($recurId)) {
      // This is a slave recur.
      $form->add('datepicker', 'start_date', ts('Start Date'), array(), FALSE, array('time' => FALSE));
      $recur = new CRM_Contribute_BAO_ContributionRecur();
      $recur->id = $recurId;
      $recur->find(TRUE);
      $startDate = $recur->start_date;
      $defaults['start_date'] = $startDate;
      $form->setDefaults($defaults);
    }

  }
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function recurmaster_civicrm_entityTypes(&$entityTypes) {
  _recurmaster_civix_civicrm_entityTypes($entityTypes);
}

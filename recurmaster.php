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
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function recurmaster_civicrm_xmlMenu(&$files) {
  _recurmaster_civix_civicrm_xmlMenu($files);
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
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function recurmaster_civicrm_managed(&$entities) {
  _recurmaster_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function recurmaster_civicrm_caseTypes(&$caseTypes) {
  _recurmaster_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function recurmaster_civicrm_angularModules(&$angularModules) {
  _recurmaster_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function recurmaster_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _recurmaster_civix_civicrm_alterSettingsFolders($metaDataFolders);
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

          if (CRM_Recurmaster_Master::isMasterRecur($crid)) {
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

          if (empty($contributionRecur[CRM_Recurmaster_Utils::getMasterRecurIdCustomField(TRUE)])) {
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
  CRM_Recurmaster_Payment::checkSubscription($recurContributionParams);
}

/**
 * If a contribution is created/edited create/edit the slave contributions
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 */
function recurmaster_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  switch ($objectName) {
    case 'Contribution':
      if (empty($objectRef->contribution_recur_id)) {
        return;
      }
      if (!in_array($op, array('create', 'edit'))) {
        return;
      }
      $contributionDetails = json_decode(json_encode($objectRef), TRUE);
      if (CRM_Recurmaster_Master::isMasterRecur($contributionDetails['contribution_recur_id'])) {
        if ($op === 'create') {
          // Add a callback to update the master contribution once we've finished here
          CRM_Core_Transaction::addCallback(CRM_Core_Transaction::PHASE_POST_COMMIT,
            'recurmaster_callback_civicrm_post', array(array('entity' => $objectName, 'op' => $op, 'id' => $contributionDetails['id'])));
        }
        CRM_Recurmaster_Slave::updateAllByMasterContribution($contributionDetails);
      }
      break;

    case 'ContributionRecur':
      if (($op !== 'create') && ($op !== 'edit')) {
        return;
      }
      $contributionRecurDetails = json_decode(json_encode($objectRef), TRUE);
      if (!CRM_Recurmaster_Master::isMasterRecur($contributionRecurDetails['id'])) {
        return;
      }

      // Create a new slave recurring contribution to match the newly created master recur

      // Add the master ID to the slave processor
      $contributionRecurDetails[CRM_Recurmaster_Utils::getMasterRecurIdCustomField()] = $contributionRecurDetails['id'];
      // Add a callback to update the master recur once we've finished here
      $callbackParams = [
        'entity' => $objectName,
        'op' => $op,
        'id' => $contributionRecurDetails['id'],
      ];
      if (CRM_Core_Transaction::isActive()) {
        CRM_Core_Transaction::addCallback(CRM_Core_Transaction::PHASE_POST_COMMIT, 'recurmaster_callback_civicrm_post', [$callbackParams]);
      }
      else {
        recurmaster_callback_civicrm_post($callbackParams);
      }

      if ($op == 'edit') {
        return;
      }

      // Remove fields we don't want to copy from master to slave
      $fieldsToUnset = array('id', 'trxn_id', 'invoice_id');
      foreach ($fieldsToUnset as $field) {
        unset($contributionRecurDetails[$field]);
      }
      // Get the id of the first slave payment processor (we only support one).
      try {
        $slaveProcessor = civicrm_api3('PaymentProcessor', 'getsingle', array(
          'payment_processor_type_id' => "recurmaster_slave",
          'options' => array('limit' => 1, 'sort' => "id ASC"),
        ));
      }
      catch (Exception $e) {
        Civi::log()->error('You must create a slave payment processor to use master recurring payments');
        return;
      }
      $contributionRecurDetails['payment_processor_id'] = $slaveProcessor['id'];
      civicrm_api3('ContributionRecur', 'create', $contributionRecurDetails);

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
    case 'ContributionRecur':
      if ($params['op'] == 'create') {
        // Update the master recur financial type to the configured type
        $masterContributionRecurParams = array(
          'id' => $params['id'],
          'financial_type_id' => CRM_Recurmaster_Settings::getValue('master_financial_type'),
        );
        civicrm_api3('ContributionRecur', 'create', $masterContributionRecurParams);
      }
      elseif ($params['op'] == 'edit') {
        // Calculate the master amount.  If it has changed, update the recur
        civicrm_api3('Job', 'process_recurmaster', [
          'recur_ids' => $params['id'],
        ]);
      }
      break;

    case 'Contribution':
      // Update the master contribution financial type to the configured type
      $masterContributionParams = array(
        'id' => $params['id'],
        'financial_type_id' => CRM_Recurmaster_Settings::getValue('master_financial_type'),
      );
      civicrm_api3('Contribution', 'create', $masterContributionParams);
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
    if (CRM_Recurmaster_Master::isMasterRecur($recurId)) {
      // Ok, this is a master recur.  Freeze some fields that should not be modified
      $form->getElement('amount')->freeze();
      $form->getElement('installments')->freeze();
      $form->removeElement('start_date');
    }
    elseif(CRM_Recurmaster_Slave::isSlaveRecur($recurId)) {
      // This is a slave recur.
      $form->removeElement('start_date');
    }

  }
}



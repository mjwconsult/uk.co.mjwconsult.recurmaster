<?php
/*--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
+--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
+--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +-------------------------------------------------------------------*/

use CRM_Recurmaster_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Recurmaster_Form_SettingsCustom extends CRM_Recurmaster_Form_Settings {

  public static function addSelect2Element(&$form, $name, $setting) {
    switch ($name) {
      case 'paymentprocessortypes':
        $paymentProcessorTypes = civicrm_api3('PaymentProcessorType', 'get', array(
          'return' => array('title'),
        ));
        foreach ($paymentProcessorTypes['values'] as $key => $value) {
          $paymentProcessorTypeOpts[] = array('id' => $key, 'text' => $value['title']);
        }
        $form->add('select2', $name, ts($setting['description']), $paymentProcessorTypeOpts, FALSE, $setting['html_attributes']);
        break;
    }
  }

}

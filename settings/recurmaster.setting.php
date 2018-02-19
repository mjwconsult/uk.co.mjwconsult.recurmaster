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

return array(
  // Payment Processor Types
  'recurmaster_paymentprocessortypes' => array(
    'admin_group' => 'recurmaster_paymentprocessor',
    'admin_grouptitle' => '"Master" Payment Processor Types',
    'admin_groupdescription' => 'Settings that control which payment processor types can accept "Master" recurring contributions',
    'group_name' => 'Recurmaster Settings',
    'group' => 'recurmaster',
    'name' => 'recurmaster_paymentprocessortypes',
    'type' => 'Array',
    'html_type' => 'select2',
    'default' => '',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Payment processor types that accept "Master" recurring contributions',
    'html_attributes' => array('multiple' => TRUE),
    'html_extra' => array(),
  )
);

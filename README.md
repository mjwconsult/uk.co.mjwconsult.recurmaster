# uk.co.mjwconsult.recurmaster (Master Recurring Contributions)

Allow multiple recurring contributions to be linked to a single "Master" for collection.

## Requirements
Version 0.3: Requires org.civicrm.smartdebit >= 1.24
 
## API
The following API functions are implemented:

### Job.process_recurmaster
This accepts a parameter "recur_ids" which is a single or array of master recurring contribution IDs.

*Note: `is_master` must be set or the recur_id will be ignored.*

Each master recurring contribution will be updated to reflect the combined amount of all linked recurring contributions subject to the following rules:
* Currency must be the same.
* Frequency must be in a valid state.

## UI
On the contribution summary for the contact record an additional link is provided for each non-master recurring contribution which allows you to link/unlink it from a master recurring contribution.

Unlink Menu item:

![Menu Unlink](/docs/images/contact_tab_contribute_menu_unlink.png)

Popup to configure linked recurring contribution:

![Link Popup](/docs/images/contact_tab_contribute_link_popup.png)

## Logic

1. The master recurring contribution is automatically updated whenever:
  1. A slave recurring contribution is created.
  1. A slave recurring contribution is updated.
  1. A slave recurring contribution is linked.
  1. The daily Job.process_recurmaster is run.

  
## TODO

#### Test:
Some of this relies on the newly created test framework in org.civicrm.smartdebit
1. Test amount calculation (based on frequency).
1. Test trigger changes at smartdebit.
1. Test Update slave contributions when master contribution is received.

#### Optional:
1. Optional: UI - Add a new "Recurring contribution" tab - Extension

## Installation
Minimum CiviCRM version is 5.??

Following CORE changes are required
1. OPTIONAL: UI - add processor name to recurring contribution tab - CORE (may need refactoring to make it optional): https://github.com/civicrm/civicrm-core/pull/11765 (included as override in this extension via tpl).

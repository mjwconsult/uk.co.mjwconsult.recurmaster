# uk.co.mjwconsult.recurmaster (Master Recurring Contributions)

Allow multiple recurring contributions to be linked to a single "Master" for collection.
 
## API
The following API functions are implemented:

### Job.process_recurmaster
This accepts a parameter "recur_ids" which is a single or array of master recurring contribution IDs.

*Note: `is_master` must be set or the recur_id will be ignored.*

Each master recurring contribution will be updated to reflect the combined amount of all linked recurring contributions subject to the following rules:
* Currency must be the same.
* Frequency must be in a valid state (TODO: currently this just accepts all frequencies).

## UI
On the contribution summary for the contact record an additional link is provided for each non-master recurring contribution which allows you to link/unlink it from a master recurring contribution.

Unlink Menu item:

![Menu Unlink](/docs/images/contact_tab_contribute_menu_unlink.png)

Popup to configure linked recurring contribution:

![Link Popup](/docs/images/contact_tab_contribute_link_popup.png)

## TODO
#### Required:
1. Test amount calculation (based on frequency).
1. Calculate immediately - automatic or as option? Using hook_civicrm_post when a recur is updated?
1. Trigger change on master upstream (eg. if amount changes update via Smartdebit API) - implement via hooks (see uk.co.mjwconsult.variablerecurpayments for an example).
1. UI - view recurring contribution should show custom fields - CORE: https://github.com/civicrm/civicrm-core/pull/11697
1. UI - add processor name to recurring contribution tab - CORE (may need refactoring to make it optional): https://github.com/civicrm/civicrm-core/pull/11765

#### Optional:
1. Optional: UI - Add a new "Recurring contribution" tab - Extension
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

## TODO:
1. Calculate amount based on frequencies (ie. monthly will be taken every month, annual will only be taken once a year).
1. Currently you have to set `is_master` via the API.  This needs to be done in a better way.
1. Only allow linking to certain types of recurring contribution (based on payment processor type?).
1. Don't allow recurring contributions with payment processors to be linked to masters?
1. Calculate immediately - automatic or as option?
# Move Data to Other Event

Allow priviliged users to move data between different events, including documents.

## Prerequisites
 - REDcap >= 9.3.0

## Easy installation
- Install the _Move Data to Other Event_ module from the Consortium [REDCap Repo] (https://redcap.vanderbilt.edu/consortium/modules/index.php) from the control center.

## Manual Installation
- Clone this repo into to `<redcap-root>/modules/move_data_to_other_event_v0.0.0`.

## Introduction
Once enabled, on the Record Home Page for any given record a series of moving truck icons will appear next to each filled in form and at the top of every event. These icons enable moving data for individual forms between events, or moving data within all forms on an event to another event, respectively.

## Global Configuration

- **Restrict use to designers globally**: Access may be limited globally to users with project design rights. Note that the module is **not** visible to all users by default if this option is not selected, this is in place to allow administrators more control as a project-level option grants non-designers access to this module's functionality.

## Project Configuration

- **Activate for use**: The module may be rendered active via checkbox (this has the same effect as disabling the module).
- **Allow non-designers to see and use this module**: You may allow non-designers to see and use the module on a per-project basis. The global configuration **Restrict use to designers globally** _will override this option if it is selected_; administrators have the final say in who may use this module.

## Use

Moving truck icons will appear at the top of all events and beside any form which contains data (i.e. a non-gray circle icon).  
![sample_icons](img/sample_project_icons.png)

Upon clicking an icon, a menu will appear allowing you to select valid events to migrate data to and the form cell or event column you are attempting to migrate will be highlighted. Migrations are only allowed to events where no data is contained in the forms to be moved.  

Clicking a moving truck next to a form will allow migration to the same form on another event provided the target form does not contain any data.  
![sample_event_migration](img/sample_event_migration.png)

Clicking a moving truck in the event titles row will allow migration to other events, provided all forms in the source event are present in the target event **and** are unfilled.  
![sample_form_migration](img/sample_form_migration.png)

Moving truck icons may be presented on forms or events which have no valid targets, in this case they will still be clickable but the dropdown menu will be empty.

# Change Log
All notable changes to Move Data to Other Event will be documented in this file. This project adheres to [Semantic Versioning](http://semver.org/).


## [1.1.0] - 2020-02-14
### Changed
- allow actions involving first form first event correct error in checking for record_pk (Kyle Chesney)
- change mouse cursor on hover (Kyle Chesney)
- Revise authors in config.json (Philip Chase)

### Added
- add ability to clone data, add logic to clone files if cloning data, declutter dialog UI for new option (Kyle Chesney)
- disable migration button if there are no valid targets, display source event, whether user is moving a form or event (Kyle Chesney)
- Add minimum REDCap version of 9.3.0 (Philip Chase)
- Add DOI badge to README (Philip Chase)


## [1.0.1] - 2019-10-29
### Changed
- Fix module that was not working for custom event labels. (Kyle Chesney)

### Added
- Document that repeated events are not supported in this module. (Kyle Chesney)


## [1.0.0] - 2019-10-15
### Summary
 - This is the first release of Move Data to Other Event. Behold!
 - Allows privileged users to easily move data, uploaded files, and signatures between events. 
 - Can move one subject's data at a time, moving either an entire event or a single form.

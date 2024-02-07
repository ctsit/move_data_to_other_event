let module = ExternalModules['MDOE'].ExternalModule;

let dialogButton = $( '<i class="fas fa-truck" type="image" style="padding: 5px; cursor: pointer;"/>' );
let modalIdentifier = "body>.ui-dialog.ui-corner-all.ui-widget.ui-dialog-buttons[role='dialog']";

// Utility function from RedCapUtil.js that is not available
function openLoader(target) {
    var overlay = $("<div></div>");
    overlay.addClass("redcapLoading");
    // insert the overlay into the target
    target.prepend(overlay);
    
    // make the overlay cover the target
    overlay.height(target.height());
    overlay.width(target.width());
    // create the loading spinner
    var spinner = $('<img src="' + module.tt("appPathImages") + 'loader.gif" />');
    var spinnerWidth = 220; // having trouble getting this dynamically
    spinner.addClass("redcapLoading");
    // insert the spinner into the overlay
    overlay.append(spinner);
    // position the spinner 30% down the overlay and in the center
    spinner.css({
        top: Math.floor(overlay.height() * 0.3),
        left: Math.floor((overlay.width() - spinnerWidth) * 0.5)
    });
    overlay.show();
}

// Will need to be called if `location.reload()` is omitted
function closeLoader(target) {
    target.children(".redcapLoading").first().remove();
}

$( "#dialog-mdoe" ).dialog({
          autoOpen: false,
          draggable: true,
          resizable: true,
          modal: true
          });
const eDialogButton = dialogButton.clone().addClass('mdoe-event');
const fDialogButton = dialogButton.clone().addClass('mdoe-form');

document.addEventListener('DOMContentLoaded', function() {
    var events;
    var links;

    var eventColumns = $('.evGridHdr');
    const dataRows = $('#event_grid_table tbody tr');
    var formLinks = dataRows.find('a');

    let eventTitles = {}; // event_id : title name
    eventColumns.each(function() {
        $evGridHdr = $(this).find('.evGridHdrInstance');
        eventId = $evGridHdr[0].className.split('evGridHdrInstance-')[1].split(' ', 1)[0];
        eventTitle = $(this).find('.evTitle').text();

        eventTitles[eventId] = eventTitle;
    });

    $.each(eventColumns, function(i, eventColumn) { 
            // $element.appendTo(dialogButton); does not work, only appears on last element
            eDialogButton.clone().appendTo(eventColumn);
            });

    $.each(formLinks, function(i, link) {
            try {
            const im = link.firstChild.src;
            // TODO: endsWith(array)
                if ( im.endsWith('circle_green.png') || im.endsWith('circle_yellow.png') || im.endsWith('circle_red.png') ) {
                    // $element.prependTo(link); does not work, only appears on last element
                    fDialogButton.clone().insertBefore(link);
                }
            } catch (TypeError) {
                // end of rows
            }
        });

    // actions for event movement
    $( ".mdoe-event" ).on( "click", function() {
        const colIndex = $(this).parent().index();
        let otherCols = {...eventColumns};
        delete otherCols[colIndex-1]; // remove instrument name column
        const sourceEventId = $(this).prev()[0]
            .className.split('evGridHdrInstance-')[1].split(' ', 1)[0];

        let selectedColValues = dataRows.find(`td:nth-child(${colIndex+1})`);
        let selectedFilledCells = {};
        let formNames = [];
        $.each(selectedColValues, function(cellNum) {
            try {
                const im = $(this).find('a')[0];
                if (im.firstChild.src.length !== 0) {
                    selectedFilledCells[cellNum] = im.firstChild.src;
                    if (!selectedFilledCells[cellNum].endsWith('circle_gray.png')) {
                        const params = new URLSearchParams(im.href);
                        formNames.push( params.get('page') );
                    }
                }
            } catch(e) {
                // ignore empty cells
                // console.log(e);
            }
            });

        let validEventIds = [];
        $.each(otherCols, function(cellNum, col) {
            let thisColValues = dataRows.find(`td:nth-child(${$(this).index()+1})`);

            // only check forms for which the source event has entries
            for (let [rowNum, imSrc] of Object.entries(selectedFilledCells)) {
                try {
                    const targCell = $(thisColValues[rowNum]).find('a');
                    if ( !targCell[0].firstChild.src.endsWith('circle_gray.png') ) {
                            return;
                        }
                    } catch(e) {
                        return;
                    }
                }
            // TODO: store eventIds in eventColumns
            const thisEventId = $(this).find('.evGridHdrInstance')[0].className.split('evGridHdrInstance-')[1].split(' ', 1)[0];
            validEventIds.push(thisEventId);
            });

        let dialogEvent = $( "#dialog-mdoe" ).clone();
        $(dialogEvent).attr('title', 'Moving Entire Event Data');
        $(dialogEvent).dialog({
          buttons: {
            "Migrate Event Data": function() {
                const targetEventId = $(this).find('select').find(':selected').val();
                openLoader($(modalIdentifier));
                ajaxMoveEvent(sourceEventId, targetEventId, formNames, true);
            },
            "Clone Event Data": function() {
                const targetEventId = $(this).find('select').find(':selected').val();
                openLoader($(modalIdentifier));
                ajaxMoveEvent(sourceEventId, targetEventId, formNames, false);
            }
          },
        });

        $(dialogEvent).find('#mdoe-select').empty();
        if (validEventIds.length > 0) {
            $(dialogEvent).prepend(`Moving data from ${eventTitles[sourceEventId]}`);
            dialogDropdown = $(dialogEvent).find('#mdoe-select');
            for ( const eventId of validEventIds ) {
                $(dialogDropdown).append(`<option value="${eventId}">${eventTitles[eventId]}</option>`);
            }
        } else {
            $(dialogEvent).text(`Sorry, there are no viable target events for ${eventTitles[sourceEventId]}`);
            $(dialogEvent).parent().find(".ui-dialog-buttonpane").hide();
        }

        // highlight column of source event
        // append to initial style and mark important to override tr-parity
        // https://stackoverflow.com/a/2655976/7418735
        selectedColValues.attr('style', (i,s) => { return (s || '') + 'background-color: #ff9933 !important;' });

        dialogEvent
            .on('dialogclose', function(event) { selectedColValues.css("background-color", ""); })
            .dialog( "open" );
    });

    // actions for form movement
    $( ".mdoe-form" ).on( "click", function() {
        const params = new URLSearchParams($(this).next().attr('href'));
        const thisCell = $(this).parent();

        // TODO: use this to exclude cells containing anything other than grey icons
        const rowSiblings = $(this).parent().siblings();

        // extract ids of cells containing unfilled forms
        let validEventIds = [];
        $.each(rowSiblings, function(cellNum, col) {
            try {
                const thisCell = col.firstChild;
                img = thisCell.firstChild.src;
                if (img.endsWith('circle_gray.png')) {
                    const thisEventId = thisCell.href
                                        .split('event_id=')
                                        .pop()
                                        .split('&')
                                        .shift();
                    validEventIds.push(thisEventId);
                }
            }
            catch(TypeError) {
            // skip invalid cells
            }
        });

        let dialogForm = $( "#dialog-mdoe" ).clone();
        $(dialogForm).attr('title', 'Moving Single Form Data');
        dialogForm.dialog({
          buttons: {
            "Migrate Form Data": function() {
                const targetEventId = $(this).find('select').find(':selected').val();
                openLoader($(modalIdentifier));
                ajaxMoveEvent(params.get('event_id'), targetEventId, [params.get('page')], true);
                // TODO: check that previous worked before deleting
            },
            "Clone Form Data": function() {
                const targetEventId = $(this).find('select').find(':selected').val();
                openLoader($(modalIdentifier));
                ajaxMoveEvent(params.get('event_id'), targetEventId, [params.get('page')], false);
            }
          },
        });

        // refresh selectable options
        $(dialogForm).find('#mdoe-select').empty();
        if (validEventIds.length > 0) {
            $(dialogForm).prepend(`Moving data from ${eventTitles[params.get('event_id')]}`);
            dialogDropdown = $(dialogForm).find('#mdoe-select');
            for ( const eventId of validEventIds ) {
                $(dialogDropdown).append(`<option value="${eventId}">${eventTitles[eventId]}</option>`);
            }
        } else {
            $(dialogForm).text('Sorry, there are no viable target events for this form');
            $(dialogForm).parent().find(".ui-dialog-buttonpane").hide();
        }

        // highlight cell of source form
        thisCell.attr('style', (i,s) => { return (s || '') + 'background-color: #ff9933 !important;' });

        dialogForm
            .on('dialogclose', function(event) { thisCell.css("background-color", ""); })
            .dialog( "open" );
    });

});

function ajaxMoveEvent(sourceEventId, targetEventId, formNames = null, deleteSourceData = false) {
    const searchParams = new URLSearchParams(window.location.search);

    $.get({
        url: ajaxpage,
        data: {
                migrating: 'event',
                sourceEventId: sourceEventId,
                targetEventId: targetEventId,
                formNames: formNames,
                recordId: searchParams.get('id'),
                projectId: searchParams.get('pid'),
                deleteSourceData: deleteSourceData
              },
        })
    .done(function(data) {
            if (data.errors) {
                // TODO: parse and report errors
                return 0;
            }
            location.reload();

            // TODO: consider re-enabling this if targeting an event and the migration was successfull
            //if (deleteSourceData /* && entireEvent */) {
            //    doDeleteEventInstance(sourceEventId); // reloads page on completion
            //} else {
            //    location.reload();
            //}
        });
}

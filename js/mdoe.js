let dialogButton = $( '<i class="fas fa-truck" type="image" style="padding: 5px;"/>' );
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

    $.each(formLinks.slice(1), function(i, link) {
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

        let dialogEvent = $( "#dialog-mdoe" ).dialog({
          buttons: {
            "Migrate Event Data": function() {
                const targetEventId = $(this).find('select').find(':selected').val();
                ajaxMoveEvent(sourceEventId, targetEventId, formNames, true);
            },
            Cancel: function() {
              $(this).dialog( "close" );
            }
          },
        });

        $(dialogEvent).find('option').remove();
        for ( const eventId of validEventIds ) {
            $('#mdoe-select').append(`<option value="${eventId}">${eventTitles[eventId]}</option>`);
            }

        selectedColValues.css("background-color", "#ff9933");

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

        let dialogForm = $( "#dialog-mdoe" ).dialog({
          buttons: {
            "Migrate Form Data": function() {
                const targetEventId = $(this).find('select').find(':selected').val();
                ajaxMoveEvent(params.get('event_id'), targetEventId, [params.get('page')]);
                // TODO: check that previous worked before deleting
            },
            Cancel: function() {
              $(this).dialog( "close" );
            }
          },
        });

        // refresh selectable options
        $(dialogForm).find('option').remove();
        for ( const eventId of validEventIds ) {
            $('#mdoe-select').append(`<option value="${eventId}">${eventTitles[eventId]}</option>`);
            }

        //highlight cell of source form
        thisCell.css("background-color", "#ff9933");

        dialogForm
            .on('dialogclose', function(event) { thisCell.css("background-color", ""); })
            .dialog( "open" );
    });

});

function ajaxMoveEvent(sourceEventId, targetEventId, formNames = null, deleteEvent = false) {
    const searchParams = new URLSearchParams(window.location.search);

    $.get({
        url: ajaxpage,
        data: {
                migrating: 'event',
                sourceEventId: sourceEventId,
                targetEventId: targetEventId,
                formNames: formNames,
                recordId: searchParams.get('id'),
                projectId: searchParams.get('pid')
              },
        })
    .done(function(data) {
            if (data.errors) {
                // TODO: parse and report errors
                return 0;
            }
            console.log(data);
            if (deleteEvent) {
                doDeleteEventInstance(sourceEventId); // reloads page on completion
            } else {
                location.reload();
            }
        });
}

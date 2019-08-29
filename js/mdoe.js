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
    var formLinks = $('#event_grid_table').children('tbody').find('a');

    let eventTitles = {}; // event_id : title name
    eventColumns.toArray().forEach( element => eventTitles[element.children[1].className.split('evGridHdrInstance-')[1].split(' ', 1)[0] ] = element.children[0].textContent);

    console.log(eventTitles);
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
        const sourceEventId = $(this).prev()[0]
            .className.split('evGridHdrInstance-')[1].split(' ', 1)[0];

        let titles = {...eventTitles}; // deep copy
        delete titles[sourceEventId]; // Exclude current event

        //TODO: prune down titles to only those with the same forms AND they are empty

        let dialogEvent = $( "#dialog-mdoe" ).dialog({
          buttons: {
            "Migrate Event Data": function() {
                const targetEventId = $(this).find('select').find(':selected').val();
                ajaxMoveEvent(sourceEventId, targetEventId);
            },
            Cancel: function() {
              $(this).dialog( "close" );
            }
          },
        });

        $(dialogEvent).find('option').remove();
        for ( let [id,title] of Object.entries(titles) ) {
            $('#mdoe-select').append(`<option value="${id}">${title}</option>`);
            }

        let thisColValues = $( `#event_grid_table tbody tr td:nth-child(${colIndex+1})` );
        thisColValues.css("background-color", "#ff9933");

        dialogEvent
            .on('dialogclose', function(event) { thisColValues.css("background-color", ""); })
            .dialog( "open" );
    });

    // actions for form movement
    $( ".mdoe-form" ).on( "click", function() {

        const params = new URLSearchParams($(this).next().attr('href'));
        console.log(params.get('page'));

        // TODO: use this to exclude cells containing anything other than grey icons
        let thisRow = $(this).parent().parent('tr');
        let movableEvents = thisRow.children();

        let titles = {...eventTitles}; // deep copy
        delete titles[params.get('event_id')];

        let dialogForm = $( "#dialog-mdoe" ).dialog({
          buttons: {
            "Migrate Form Data": function() {
                const targetEventId = $(this).find('select').find(':selected').val();
                console.log(targetEventId);
                ajaxMoveForm(params.get('event_id'), targetEventId, params.get('page'));
                // TODO: check that previous worked before deleting
            },
            Cancel: function() {
              $(this).dialog( "close" );
            }
          },
        });

        $(dialogForm).find('option').remove();
        for ( let [id,title] of Object.entries(titles) ) {
            $('#mdoe-select').append(`<option value="${id}">${title}</option>`);
            }

        dialogForm.dialog( "open" );
    });

});

function ajaxMoveEvent(sourceEventId, targetEventId) {
    const searchParams = new URLSearchParams(window.location.search);

    $.get({
        url: ajaxpage,
        data: {
                migrating: 'event',
                sourceEventId: sourceEventId,
                targetEventId: targetEventId,
                recordId: searchParams.get('id'),
                projectId: searchParams.get('pid')
              },
        })
    .done(function(data) {
            if (data.errors) {
                // TODO: parse and report errors
                return 0;
            }
            doDeleteEventInstance(sourceEventId); // reloads page on completion
            });
}

function ajaxMoveForm(sourceEventId, targetEventId, formName) {
    const searchParams = new URLSearchParams(window.location.search);

    $.get({
        url: ajaxpage,
        data: {
                migrating: 'form',
                formName: formName,
                sourceEventId: sourceEventId,
                targetEventId: targetEventId,
                recordId: searchParams.get('id'),
                projectId: searchParams.get('pid')
              },
        })
    .done(function(data) {
            console.log(data);
            if (data.errors) {
                // TODO: parse and report errors
                return 0;
            }
            location.reload();
            //TODO: delete only the contents of this form
            //doDeleteEventInstance(sourceEventId); // reloads page on completion
            });
}

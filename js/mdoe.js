// TODO: pick font-awesome icon for button
const formButton = $( '<input type="image" value="icon" />' );
document.addEventListener('DOMContentLoaded', function() {
    var events;
    var links;

    eventColumns = $('.evGridHdr');
    links = $('#event_grid_table').children('tbody').find('a');

    console.log(eventColumns);
    console.log(links);

    $.each(eventColumns, function(i, eventColumn) { 
            // $element.appendTo(formButton); does not work, only appears on last element
            formButton.clone().appendTo(eventColumn);
            });

    $.each(links, function(i, link) {
            // $element.prependTo(link); does not work, only appears on last element
            formButton.clone().prependTo(link);
            });


    dialog = $( "dialog-form" ).dialog({
      autoOpen: false,
      height: 400,
      width: 350,
      modal: true,
      buttons: {
        "Create an account": console.log,
        Cancel: function() {
          dialog.dialog( "close" );
        }
      },
      close: function() {
        form[ 0 ].reset();
        allFields.removeClass( "ui-state-error" );
      }
    });

    function deliverPopupMenu() {
        return;
    }

});

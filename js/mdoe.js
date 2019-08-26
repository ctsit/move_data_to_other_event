let formButton = $( '<i class="fas fa-truck" type="image" style="padding: 5px;"/>' );
const eFormButton = formButton.clone().addClass('mdoe-event');
const fFormButton = formButton.clone().addClass('mdoe-form');

document.addEventListener('DOMContentLoaded', function() {
    var events;
    var links;

    eventColumns = $('.evGridHdr');
    formLinks = $('#event_grid_table').children('tbody').find('a');

    $.each(eventColumns, function(i, eventColumn) { 
            // $element.appendTo(formButton); does not work, only appears on last element
            eFormButton.clone().appendTo(eventColumn);
            });

    $.each(formLinks, function(i, link) {
            // $element.prependTo(link); does not work, only appears on last element
            fFormButton.clone().insertBefore(link);
            });

    console.log(formLinks);

    dialogForm = $( "#dialog-mdoe" ).dialog({
      autoOpen: false,
      draggable: true,
      resizable: true,
      modal: true,
      buttons: {
        "Migrate Event Data": function() { console.log($(this).find('option')); sendAjaxForEvent('hello world'); },
        Cancel: function() {
          $(this).dialog( "close" );
        }
      },
    });

    $( ".mdoe-event" ).on( "click", function() {
        let colIndex = $(this).parent().index();

        let otherCols = $( "#event_grid_table thead tr th" ).toArray();
        otherCols.splice(colIndex, 1); // Remove this event column
        otherCols.shift(); // Remove form label column

        let titles = {}; // title name : event_id
        otherCols.forEach( element => titles[element.children[0].textContent] = element.children[1].className.split('evGridHdrInstance-')[1].split(' ', 1)[0] );

        $(dialogForm).find('option').remove();
        for ( let [key,value] of Object.entries(titles) ) {
            $('#mdoe-select').append(`<option value="${value}">${key}</option>`);
            }

        console.log(dialogForm);


        let thisColValues = $( `#event_grid_table tbody tr td:nth-child(${colIndex+1})` );
        thisColValues.css("background-color", "#ff9933");

        //$("#dialog-mdoe")
        dialogForm
            .on('dialogclose', function(event) { thisColValues.css("background-color", ""); })
            .dialog( "open" );
    });

    $( ".mdoe-form" ).on( "click", function() {
        console.log('this');
        console.log(this);
        console.log( $("#dialog-mdoe") );
        let page = $(this).next().attr('href')
                    .split('page=')[1].split('&')[0];
        let thisRow = $(this).parent().parent('tr');
        console.log(page);
        console.log(thisRow);
        let movableEvents = thisRow.children();
        console.log(movableEvents);
        $("#dialog-mdoe").dialog( "open" );
    });

});

function sendAjaxForEvent(str) {
    //console.log(str);
    var ajax = new XMLHttpRequest();
    ajax.onreadystatechange = function() { console.log(this.responseText)};
    ajax.open("GET", ajaxpage + "?q=" + str, true);
    //ajax.open("GET", "migratedata.php?q=" + str, true);
    ajax.send();
}


// Make the tabs work on click
$('#tabs li').on('click',function(){

    // Remove any that might not be active
    $('#tabs li').not(this).each(function(i, e) {
        $(e).removeClass('active');
        var panel_id = $(e).data('div');
        var panel = $('#' + panel_id);
        //console.log("active panel", panel);
        panel.hide();
    });

    //Show the active one
    $(this).addClass('active');
    var panel_id = $(this).data('div');
    var panel = $('#' + panel_id);
    //console.log("new active panel", panel);
    panel.show();
});

// Attach handlers to buttons/events
$('#saveBtn').on('click',saveShazam);
$('#active_field_name').on('change',loadShazam);

// Make the first tab active by default
$('#tabs li:first').trigger('click');

// Load the first field from the dropdown
$('#active_field_name').trigger('change');


function loadShazam() {
    var field_name = $( "#active_field_name" ).val();
    if (field_name) {
        var params = {
            "pid": pid,
            "action": "load",
            "field_name": field_name
        };

        var jqxhr = $.ajax({
            type: "POST",
            data: params,
            dataType: "json"
        })
            .done(function(data) {
                if ( console && console.log ) {
                    console.log( "Loading:", data);
                }
                // Make sure the fieldnames still match!
                if ( $( "#active_field_name" ).val() != data.field_name ) {
                    alert ("The selected fieldname doesn't match the loaded one!");
                    return;
                }
                // Load values into editor
                $(editors).each(function(i,e) {
                    var editor = e.instance;
                    var mode = e.mode;
                    var val = editor.getValue();
                    if (data.params[mode]) editor.setValue(data.params[mode]);
                });


            })
            .fail(function() {
                alert( "error" );
            });
    }

}

// Save the current configuration
function saveShazam() {
    var data = {
        "pid": pid,
        "action": "save",
        "field_name": $( "#active_field_name" ).val(),
        "params": {}
    }

    // Get values from editors
    $(editors).each(function(i,e) {
        var editor = e.instance;
        var val = editor.getValue();
        var mode = e.mode;
        console.log(editor, val);
        data.params[mode] = val;
    });
    console.log("Params:",data);

    var jqxhr = $.ajax({
        type: "POST",
        data: data
    })
        .done(function(data) {
            if ( console && console.log ) {
                console.log( "Sample of data:", data.slice( 0, 100 ) );
            }
        })
        .fail(function() {
            alert( "error" );
        })
        .always(function() {
//                    alert( "complete" );
        });
}



// Utility function to dynamically load an external script
function getScript(src, callback) {
    var s = document.createElement('script');
    s.src = src;
    s.async = true;
    s.onreadystatechange = s.onload = function() {
        if (!callback.done && (!s.readyState || /loaded|complete/.test(s.readyState))) {
            callback.done = true;
            callback();
        }
    };
    document.querySelector('head').appendChild(s);
}


// Get the ACE Editor
//getScript('https://cdn.jsdelivr.net/ace/1.2.3/noconflict/ace.js', initAce);


// An array for all of the editors we are working with in the tabs
var editors = [];

function initAce() {
    initEditor('editor_html', 'html');
    initEditor('editor_css', 'css');
    initEditor('editor_js', 'javascript');
}

// Create an ACE editor on the id element in mode mode
function initEditor(id, mode) {
    var editorElement = $('#'+id);
    var editor = ace.edit(id);
    editor.setTheme("ace/theme/clouds");
    editor.getSession().setMode("ace/mode/" + mode);
    editor.$blockScrolling = Infinity;
    editorElement.width('100%');

    editor.commands.addCommand({
        name: 'saveFile',
        bindKey: {
            win: 'Ctrl-S',
            mac: 'Command-S',
            sender: 'editor|cli'
        },
        exec: function(env, args, request) {
            saveShazam();
        }
    });

    editors.push( {id: id, instance: editor, mode: mode});
    resizeAce();
}

function resizeAce() {
    // Bottom of Tabs
    var tabTop = $('#tabs').position().top;


    // Space in window
    var w = window.innerHeight;

    // Top of south footer (if present)
    var footerTop = $('#south').position().top;

    var bottom = footerTop == 0 ? w : footerTop;

    // Space for submit and other stuff
    var fudge = 100;

    // Calc Height
    var h = max(200,bottom - tabTop - fudge);
//            console.log("h", h);

    // Apply height to all editors (even hidden ones)
    $(editors).each(function(i,e){
//                console.log("id", e.id);
        var editorDiv = $('#'+ e.id);
        $(editorDiv).css('height', h.toString() + 'px');
    });
}

$(window).on('resize', function () {
    resizeAce();
});

$(document).ready( function() {
    resizeAce();
});
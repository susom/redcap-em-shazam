// CREATE SHAZAM
if(typeof Shazam === 'undefined') { var Shazam = {}; }

// Table
Shazam.post = function(action,field_name) {
    // console.log("Creating " + field_name);
    var action = $('<input>')
        .attr('name','action')
        .val(action);
    var field_name = $('<input>')
        .attr('name','field_name')
        .val(field_name);
    var form = $('#action-form').append(action).append(field_name).submit();
}

Shazam.initAceEditors = function() {

    // Init read-only editor for the example:
    Shazam.initEditor('shazam-example-code','html');
    //Shazam.example = Shazam.editors.shift();

    // var currentValue = $(editorElement).html();
    // // console.log("EditorElement",editorElement, currentValue);
    // var editor = ace.edit(id);
    //
    // editor.setOptions({
    //     enableBasicAutocompletion: true,
    //     enableSnippets: true,
    //     enableLiveAutocompletion: true
    // });
    //
    // editor.setTheme("ace/theme/clouds");
    // editor.getSession().setMode("ace/mode/" + mode);


    console.log("initAceEditors");
    var langTools = ace.require("ace/ext/language_tools");
    var redcapFieldCompleter = {
        getCompletions: function(editor, session, pos, prefix, callback) {
            var wordList = ["foo", "bar", "baz"];
            // callback(null, wordList.map(function(word) {
            callback(null, Shazam.fields.map(function(word) {
                return {
                    caption: word,
                    value: word,
                    meta: "field"
                };
            }));

        }
    };
    langTools.addCompleter(redcapFieldCompleter);

    var shazamCompleter = {
        getCompletions: function(editor, session, pos, prefix, callback) {
            var wordList = ["shazam", "shazam-mirror-visibility"];
            callback(null, wordList.map(function(word) {
                return {
                    caption: word,
                    value: word,
                    meta: "Shazam Commands"
                };
            }));

        }
    };
    langTools.addCompleter(shazamCompleter);
    var labelCompleter = {
        getCompletions: function(editor, session, pos, prefix, callback) {
            var field_labels = Shazam.fields.map(function(word) { return word + ":label" });
            callback(null, field_labels.map(function(word) {
                return {
                    caption: word,
                    value: word,
                    meta: "Field Label"
                };
            }));

        }
    };
    langTools.addCompleter(labelCompleter);

    Shazam.initEditor('editor_html', 'html');
    Shazam.initEditor('editor_css', 'css');
    Shazam.initEditor('editor_js', 'javascript');
}

Shazam.initEditor = function(id, mode) {
    // console.log("initEditor" + id + " / " + mode);
    // Create an ACE editor on the id element with mode mode
    var editorElement = $('#'+id);
    var currentValue = $(editorElement).html();
    // console.log("EditorElement",editorElement, currentValue);
    var editor = ace.edit(id);

    editor.setOptions({
        enableBasicAutocompletion: true,
        enableSnippets: true,
        enableLiveAutocompletion: true
    });

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
            Shazam.save();
        }
    });

    // Set the value of the editor from memory
    editor.setValue(currentValue,1);

    // Get fancy with the default view of the default js template
    if (mode=='javascript') {
        if(currentValue == "$(document).ready(function(){\n\t//Add javascript here...\n\t\n});") {
            editor.gotoLine(3, 1);
        }
    }

    // For html, remove a few annotations that don't apply
    if (mode=='html') {
        var session = editor.getSession();
        session.on("changeAnnotation", function () {
            var annotations = session.getAnnotations() || [], i = len = annotations.length;
            while (i--) {
                if (/doctype first\. Expected/.test(annotations[i].text)) {
                    annotations.splice(i, 1);
                }
                else if (/Unexpected End of file\. Expected/.test(annotations[i].text)) {
                    annotations.splice(i, 1);
                }
            }
            if (len > annotations.length) {
                session.setAnnotations(annotations);
            }
        });
        editor.focus();
    }

    // Cache the editor objects to Shazam js object (used for resizing and saving)
    Shazam.editors.push( {id: id, instance: editor, mode: mode});
}

// Try to set a reasonable size to the ACE editor based on size of the display/window
Shazam.resizeAceEditors = function () {
    // Top of div
    var tabTop = $('div.shazam-editor').position().top;

    // Space in window
    var w = window.innerHeight;

    // Top of south footer (if present)
    var footerTop = $('#south').position().top;

    // Calculate a suitable bottom
    var bottom = footerTop == 0 ? w : footerTop;

    // Space for submit and other fudge stuff
    var fudge = 90;

    // Calc Height
    var h = max(200,bottom - tabTop - fudge);

    // Apply height to all editors (even hidden ones)
    $(Shazam.editors).each(function(i,e){
        var editorDiv = $('#'+ e.id);
        $(editorDiv).css('height', h.toString() + 'px');
    });
}

Shazam.save = function(callback) {
    // console.log('SAVE!');
    var field_name = $('div.shazam-editor').data('field-name');
    var status = 1; // $('div.shazam-editor').data('status')
    var data = {
        "action": "save",
        "field_name": field_name,
        "params": {
            "status": status
        }
    };

    var saveBtn = $('button[name="save"]');
    var saveBtnHtml = saveBtn.html();
    saveBtn.html('<img src="'+app_path_images+'progress_circle.gif"> Saving...');
    saveBtn.prop('disabled',true);

    // Get the values from the three panes
    $(Shazam.editors).each(function (i, e) {
        var editor = e.instance;
        var val = editor.getValue();
        var mode = e.mode;
        // console.log(editor, val);
        data.params[mode] = val;
    });

    // Post back saved version
    var jqxhr = $.ajax({
        method: "POST",
        data: data,
        dataType: "json"
    })
        .done(function (data) {
            console.log(data);
            saveBtn.html(saveBtnHtml);
            saveBtn.prop('disabled',false);
            if (callback) {
                console.log("callback", field_name, callback, data);
                callback(data);
                return false;
            }
        })
        .fail(function () {
            alert("error");
        });
}

Shazam.closeEditor = function() {
    // go back to the table by making a get to the same url
    var url = window.location.href.replace(/\#$/, "");
    $(location).attr('href', url);
}

// A prepare the editor page by doing all necessary javascript add-ons
Shazam.prepareEditors = function() {

    // Create the ACE objects
    Shazam.editors = [];
    Shazam.initAceEditors();
    Shazam.resizeAceEditors();

    // Add some event handlers
    $(window).on('resize', function () {
        Shazam.resizeAceEditors();
    });

    $('.shazam-edit-buttons button').on('click', function() {
        var action = $(this).attr('name');

        if (action == 'save') {
            Shazam.save();
        }

        if (action == 'save_and_close') {
            Shazam.save(Shazam.closeEditor);
        }


        if (action == 'cancel') {
            Shazam.closeEditor();
        }

    });

}

// Prepare event handlers for the table-view
Shazam.prepareTable = function() {
    // Handle the ADD button
    $('.add-shazam a').on('click', function() {
        // Create a new shazam entry
        var field_name = $(this).data('field-name');
        if (field_name.length) Shazam.post('create', field_name);
    });

    // Handle the in-table action buttons
    $('.shazam-table ul.actions a').on('click', function() {
        var action = $(this).data('action');
        var field_name = $(this).closest('tr').find('td:first').text();

        // Confirm Deletions
        if (action == 'delete') {
            // Give confirmation popup to reset all others
            // function simpleDialog(content,title,id,width,onCloseJs,closeBtnTxt,okBtnJs,okBtnTxt) {
            simpleDialog('Are you sure you want to delete the Shazam configuration for ' + field_name + '?',
                'Confirm Deletion',
                null,
                600,
                function() {
                    // Do nothing
                    return false;
                },
                "Cancel",
                function() {
                    // Delete
                    Shazam.post(action, field_name);
                },
                "Delete"
            );
        } else {
            Shazam.post(action, field_name);
        }
    });
}


$(document).ready(function(){

    // THESE ARE ACTIONS FOR THE 'TABLE VIEW'
    // if ($('div.shazam-table').length) Shazam.prepareTable();

    // PREPARE ACE EDITORS (IF ON EDIT PAGE)
    // if ($('div.shazam-editor').length) Shazam.prepareEditors();

});
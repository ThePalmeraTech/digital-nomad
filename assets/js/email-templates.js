(function($){
    let editors = {};

    function initEditors(){
        $('.palmerita-template').each(function(){
            const id = $(this).attr('id');
            if(editors[id]) return;
            const editorSettings = wp.codeEditor.defaultSettings ? _.clone( wp.codeEditor.defaultSettings ) : {};
            editorSettings.codemirror = editorSettings.codemirror || {};
            editorSettings.codemirror.mode = 'text/html';
            editors[id] = wp.codeEditor.initialize( $(this), editorSettings );
            // Añadir evento change en CodeMirror para actualizar preview en vivo
            if(editors[id] && editors[id].codemirror){
                editors[id].codemirror.on('change', function(){
                    // Sincronizar contenido con el textarea y disparar evento input
                    editors[id].codemirror.save(); // actualiza el textarea subyacente
                    $('#'+id).trigger('input');
                });
            }
        });
    }

    $(document).ready(function(){
        initEditors();
    });
})(jQuery); 
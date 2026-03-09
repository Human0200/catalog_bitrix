
$(function() {

    /**
     * Resize parent iframe when content is changed
     */
    BX24.fitWindow();
    $('.iframe-wrapper').bind("DOMSubtreeModified",function(){
        BX24.fitWindow();
    });

});

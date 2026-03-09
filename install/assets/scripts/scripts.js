
function parse_url(url){
    var parser = document.createElement('a');
    parser.href = url;
    return parser;
}

$(function() {

    /**
     * Resize parent iframe when content is changed
     */
    parent.iframeResize();
    const targetNode = document.getElementById('app');
    const config = {
        attributes: true,
        childList: true,
        subtree: true,
        attributeFilter: ['style'],
    };
    const callback = function(mutationsList, observer) {
        for (let mutation of mutationsList) {
            if (mutation.type === 'childList') {
                parent.iframeResize();
            }
            if (mutation.type === 'attributes') {
                parent.iframeResize();
            }
        }
    };
    const observer = new MutationObserver(callback);
    observer.observe(targetNode, config);

});

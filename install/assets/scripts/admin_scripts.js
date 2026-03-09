
function isInIframe() {
    try {
        return window.self !== window.top;
    } catch (e) {
        return true;
    }
}

if (isInIframe()) {
    var url = window.location.href;
    if (url.search('IFRAME') == -1) {
        var new_url = new URL(url);
        new_url.searchParams.set('IFRAME', 'Y');
        document.location.href = new_url.toString();
    }
}

$(function() {
    if (isInIframe()) {
        if ($('#sale_order_edit_form').attr('action')) {
            var url = $('#sale_order_edit_form').attr('action');
            url = url.replace(/\?/, '?IFRAME=Y&');
            $('#sale_order_edit_form').attr('action', url);
        }
        if ($('#sale_order_create_form').attr('action')) {
            var url = $('#sale_order_create_form').attr('action');
            url = url.replace(/\?/, '?IFRAME=Y&');
            $('#sale_order_create_form').attr('action', url);
        }
    }

});

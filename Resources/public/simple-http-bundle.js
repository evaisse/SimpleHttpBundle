/**
 *
 */
(function ($) {
    function replayRequest(url, request, callback) {
        window.console && console.log('Replay request :', request);

        $.post({
            url: url,
            data: {
                request: JSON.stringify(request)
            }
        }).done(function (data, success, xhr) {
            // Wait 1 second to be sure the profiler has been generated and can be accessed
            setTimeout(() => {
                $.get({
                    url: xhr.getResponseHeader('X-Debug-Token-Link'),
                    data: {
                        panel: 'simplehttpprofiler',
                    }
                }).always(function (html) {
                    callback(null, $(html).find('.http-call'));
                });
            }, 1000);
        }).fail(function (err) {
            callback(err, null);
        });
        return false;
    };


    function filterByHost(value) {
        if (value == 0) {
            $(".http-call").hide();
        } else {
            $(".http-call").each(function () {
                if ($(this).hasClass(value)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    };


    $(document).on('setup', function (event, element) {

        $(element).find('.tabs').each(function () {
            // tabs
            var tabsContents = $(this).find('.tabs-content > div'),
                tabs = $(this).find('.tabs-nav > li > a');

            tabs.each(function (i, e) {
                $(this).click(function () {
                    tabs.removeClass('active');
                    $(this).addClass('active');
                    tabsContents.hide();
                    $(tabsContents[i]).show();
                });
            });
        });

        $(element).find('.http-call__index').click(function () {
            $(this).closest('.http-call').toggleClass('http-call--minimized');
        });

        $(element).find('.js-http-call-hosts-filter').change(function (event) {
            filterByHost(event.target.value);
        });

        $(element).find('[data-simple-http-replay]').click(function (replay, i) {

            var $panel = $(this).closest('.http-call'),
                $butt = $(this);
            $buttImg = $butt.find('img');
            $buttImg.addClass('rotating');

            $butt.prop('disabled', true);

            replayRequest($(this).data('simpleHttpReplayUrl'), $(this).data('simpleHttpReplay'), function (error, block) {
                if (error) {
                    alert('Error on replay request');
                }
                $panel.find('.http-call').addClass('http-call--minimized');
                $butt.prop('disabled', false);
                $(block).find('.http-call__replays').remove();
                $('<a class="http-call__index" href="javascript://"><b>⬇</b><b>⬆</b> Replay #' + ($panel.find('.http-call').length+1) + ' - ' + new Date() + '</a>').prependTo($(block), 'top');
                $(block)
                    .appendTo($panel.find('.http-call__replay-calls:first'));
                $(document).trigger('setup', [block]); // init events
                $buttImg.removeClass('rotating');
            });

        });

        $(element).find("#collector-content pre.hljs").each(function () {
            hljs.highlightBlock(this);
        });

    });

    $(document).trigger('setup', [document]);

})(jQuery);




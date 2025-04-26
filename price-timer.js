(function($) {
    $(function() {
        const $timers = $('.custom-price-timer');
        if (!$timers.length) {
            return;
        }

        const expiredText = wc_price_add_cart_limit_vars.expired_text;
        $timers.each(function() {
            $(this).data('expired-text', expiredText);
        });

        const intervalId = setInterval(() => {
            const now = Math.floor(Date.now() / 1000);
            let shouldReload = false;

            $timers.each(function() {
                const $el         = $(this);
                const expiration  = parseInt($el.data('expiration'), 10);
                const remaining   = expiration - now;

                if (remaining <= 0) {
                    $el.text($el.data('expired-text'));
                    shouldReload = true;
                    return false;
                }

                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                $el.find('.timer').text(
                    `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
                );
            });

            if (shouldReload) {
                clearInterval(intervalId);
                window.location.reload();
            }
        }, 1000);
    });
})(jQuery);

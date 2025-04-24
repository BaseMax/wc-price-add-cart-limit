(function($) {
    function updateTimers() {
        $('.custom-price-timer').each(function() {
            const timerElement = $(this);
            const expiration = parseInt(timerElement.data('expiration'));
            const now = Math.floor(Date.now() / 1000);
            const remaining = expiration - now;

            if (remaining <= 0) {
                timerElement.text(timerElement.data('expired-text'));
                location.reload();
                return;
            }

            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            timerElement.find('.timer').text(
                `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
            );
        });
    }

    $(document).ready(function() {
        $('.custom-price-timer').data(
            'expired-text', 
            wc_price_add_cart_limit_vars.expired_text
        );

        updateTimers();
        setInterval(updateTimers, 1000);
    });
})(jQuery);

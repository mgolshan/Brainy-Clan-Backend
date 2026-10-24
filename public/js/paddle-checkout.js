// Starts Paddle.js on /subscribe/pay (the Paddle "default payment link" page). Paddle.js opens the
// checkout for the transaction in ?_ptxn= by itself. Settings come from data attributes on #paddle-checkout.
(function () {
    var body = document.getElementById('paddle-checkout');

    if (!window.Paddle || !body || !body.dataset.paddleToken) {
        return;
    }

    if (body.dataset.paddleEnvironment === 'sandbox') {
        window.Paddle.Environment.set('sandbox');
    }

    window.Paddle.Initialize({
        token: body.dataset.paddleToken,
        checkout: {
            settings: {
                displayMode: 'overlay',
                locale: body.dataset.locale || 'en',
                successUrl: body.dataset.successUrl
            }
        }
    });
})();

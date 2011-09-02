<?php
/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\Component\Payment;

use Symfony\Component\HttpFoundation\Response;
use Sonata\Component\Payment\PaymentInterface;
use Sonata\Component\Order\OrderInterface;
use Sonata\Component\Basket\BasketInterface;
use Sonata\Component\Product\ProductInterface;
use Sonata\Component\Payment\TransactionInterface;
use Symfony\Component\Routing\RouterInterface;
use Buzz\Browser;

class CheckPayment extends BasePayment
{
    protected $router;

    protected $browser;

    /**
     * @param \Symfony\Component\Routing\RouterInterface $router
     * @param \Buzz\Browser $browser
     */
    public function __construct(RouterInterface $router, Browser $browser)
    {
        $this->router = $router;
        $this->browser = $browser;
    }

    /**
     * encode value for the bank
     *
     * @param string $value
     * @return string the encoded value
     */
    public function encodeString($value)
    {
        return $value;
    }

    /**
     * @return int
     */
    public function getTransactionId()
    {
        return 1;
    }

    /**
     * @param \Sonata\Component\Basket\BasketInterface $basket
     * @param \Sonata\Component\Product\ProductInterface $product
     * @return bool
     */
    public function isAddableProduct(BasketInterface $basket, ProductInterface $product)
    {
        return true;
    }

    /**
     * @param \Sonata\Component\Basket\BasketInterface $basket
     * @return bool
     */
    public function isBasketValid(BasketInterface $basket)
    {
        return true;
    }

    /**
     * @param TransactionInterface $transaction
     * @return bool
     */
    public function isRequestValid(TransactionInterface $transaction)
    {
        return true;
    }

    /**
     * Method called when an error occurs
     *
     * @param \Sonata\Component\Payment\TransactionInterface $transaction
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function handleError(TransactionInterface $transaction)
    {
        return new Response();
    }

    /**
     * Send post back confirmation to the bank when the bank callback the site
     *
     * @param \Sonata\Component\Payment\TransactionInterface $transaction
     * @return \Symfony\Component\HttpFoundation\Response, false otherwise
     */
    function sendConfirmationReceipt(TransactionInterface $transaction)
    {
        $order = $transaction->getOrder();
        if (!$order) {
            $transaction->setStatusCode(TransactionInterface::STATE_KO);

            return new Response('ko');
        }

        $transaction->setStatusCode(TransactionInterface::STATE_OK);
        $order->setStatus(OrderInterface::STATUS_PENDING);
        $order->setPaymentStatus(TransactionInterface::STATUS_PENDING);
        $order->setValidatedAt($transaction->getCreatedAt());

        return new Response('ok');
    }

    /**
     * @param TransactionInterface $transaction
     * @return bool
     */
    public function isCallbackValid(TransactionInterface $transaction)
    {
        return true;
    }

    /**
     * @param \Sonata\Component\Order\OrderInterface $order
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function callbank(OrderInterface $order)
    {
        // call the callback handler ...
        $response = $this->browser->get($this->router->generate($this->getOption('url_callback'), array(), true));

        $url = $response->getContent() == 'ok' ? 'url_return_ok' : 'url_return_ko';

        $params = array(
            'bank'           => $this->getCode(),
            'reference'      => $order->getReference(),
            'transaction_id' => $this->getTransactionId()
        );

        // redirect the user to the correct page
        $response = new Response('', 302, array(
            'Location' => $this->router->generate($url, $params, true)
        ));
        $response->setPrivate();

        return $response;
    }

    /**
     * @param TransactionInterface $transaction
     * @return string
     */
    public function getOrderReference(TransactionInterface $transaction)
    {
        return $transaction->get('reference');
    }

    /**
     * @param TransactionInterface $transaction
     * @return void
     */
    public function applyTransactionId(TransactionInterface $transaction)
    {
        $transaction->setTransactionId($transaction->get('transaction_id'));
    }
}
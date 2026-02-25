<?php

namespace EvrenOnur\SanalPos\Gateways\Providers;

use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Gateways\AbstractGateway;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\IyzicoHashGenerator;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\IyzicoHttpClient;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\IyzicoOptions;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Model\IyzicoAddress;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Model\IyzicoBasketItem;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Model\IyzicoBuyer;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Model\IyzicoPaymentCard;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\PKIRequestStringBuilder;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Request\CreateAmountBasedRefundRequest;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Request\CreateCancelRequest;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Request\CreatePaymentRequest;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Request\CreateThreedsPaymentRequest;
use EvrenOnur\SanalPos\Infrastructure\Iyzico\Request\RetrieveInstallmentInfoRequest;

/**
 * Iyzico sanal POS gateway implementasyonu.
 */
class IyzicoGateway extends AbstractGateway
{
    private string $urlAPITest = 'https://sandbox-api.iyzipay.com';

    private string $urlAPILive = 'https://api.iyzipay.com';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);

        if (empty($request->invoice_info?->tax_number)) {
            if ($request->invoice_info !== null) {
                $request->invoice_info->tax_number = '11111111111';
            }
        }

        if (empty($request->customer_ip_address)) {
            $request->customer_ip_address = '1.1.1.1';
        }

        $amount = $this->formatIyzicoPrice($request->sale_info->amount);

        $req = new CreatePaymentRequest;
        $req->locale = 'tr';
        $req->conversationId = $request->order_number;
        $req->price = $amount;
        $req->paidPrice = $amount;
        $req->currency = $request->sale_info->currency?->name ?? 'TRY';
        $req->installment = $request->sale_info->installment;
        $req->basketId = $request->order_number;

        // Kart bilgileri
        $paymentCard = new IyzicoPaymentCard;
        $paymentCard->cardHolderName = $request->sale_info->card_name_surname;
        $paymentCard->card_number = $request->sale_info->card_number;
        $paymentCard->expireMonth = str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT);
        $paymentCard->expireYear = (string) $request->sale_info->card_expiry_year;
        $paymentCard->cvc = $request->sale_info->card_cvv;
        $req->paymentCard = $paymentCard;

        // Alıcı bilgileri
        $buyer = new IyzicoBuyer;
        $buyer->id = $request->invoice_info?->email_address ?? 'buyer@test.com';
        $buyer->name = $request->invoice_info?->name ?? 'Müşteri';
        $buyer->surname = $request->invoice_info?->surname ?? $request->invoice_info?->name ?? 'Müşteri';
        $buyer->gsmNumber = $request->invoice_info?->phone_number ?? '';
        $buyer->email = $request->invoice_info?->email_address ?? '';
        $buyer->identityNumber = $request->invoice_info?->tax_number ?? '11111111111';
        $buyer->registrationAddress = $request->invoice_info?->address_description ?? '';
        $buyer->ip = $request->customer_ip_address;
        $buyer->city = $request->invoice_info?->city ?? '';
        $buyer->country = $request->invoice_info?->country?->name ?? 'Turkey';
        $buyer->zipCode = $request->invoice_info?->post_code ?? '';
        $req->buyer = $buyer;

        // Kargo adresi
        $shippingAddress = new IyzicoAddress;
        $shippingAddress->contactName = $request->shipping_info?->name ?? $request->sale_info->card_name_surname;
        $shippingAddress->city = $request->shipping_info?->city ?? '';
        $shippingAddress->country = $request->shipping_info?->country?->name ?? 'Turkey';
        $shippingAddress->address = $request->shipping_info?->address_description ?? '';
        $shippingAddress->zipCode = $request->shipping_info?->post_code ?? '';
        $req->shippingAddress = $shippingAddress;

        // Fatura adresi
        $billingAddress = new IyzicoAddress;
        $billingAddress->contactName = $request->invoice_info?->name ?? $request->sale_info->card_name_surname;
        $billingAddress->city = $request->invoice_info?->city ?? '';
        $billingAddress->country = $request->invoice_info?->country?->name ?? 'Turkey';
        $billingAddress->address = $request->invoice_info?->address_description ?? '';
        $billingAddress->zipCode = $request->invoice_info?->post_code ?? '';
        $req->billingAddress = $billingAddress;

        // Sepet
        $basketItem = new IyzicoBasketItem;
        $basketItem->id = 'TAHSILAT';
        $basketItem->name = 'Cari Tahsilat';
        $basketItem->category1 = 'Tahsilat';
        $basketItem->itemType = 'VIRTUAL';
        $basketItem->price = $amount;
        $req->basketItems = [$basketItem];

        $options = $this->getOptions($auth);
        $headers = IyzicoHashGenerator::getHttpHeaders($req, $options);

        if ($request->payment_3d?->confirm === true) {
            // 3D Secure
            $req->callbackUrl = $request->payment_3d->return_url;

            $result = IyzicoHttpClient::post(
                $options->baseUrl . '/payment/3dsecure/initialize',
                $headers,
                $req->toArray()
            );

            $response->private_response = $result;

            if (($result['status'] ?? '') === 'success') {
                $htmlContent = $result['threeDSHtmlContent'] ?? '';
                if (! empty($htmlContent)) {
                    $decodedHtml = base64_decode($htmlContent);
                    $response->status = SaleResponseStatus::RedirectHTML;
                    $response->message = $decodedHtml;
                } else {
                    $response->status = SaleResponseStatus::Error;
                    $response->message = '3D HTML içeriği alınamadı';
                }
            } else {
                $response->status = SaleResponseStatus::Error;
                $response->message = $result['errorMessage'] ?? 'İşlem sırasında bir hata oluştu';
            }
        } else {
            // Normal ödeme
            $result = IyzicoHttpClient::post(
                $options->baseUrl . '/payment/auth',
                $headers,
                $req->toArray()
            );

            $response->private_response = $result;

            if (($result['status'] ?? '') === 'success') {
                $response->status = SaleResponseStatus::Success;
                $response->message = 'İşlem başarıyla tamamlandı';
                $response->transaction_id = $result['paymentId'] ?? '';
            } else {
                $response->status = SaleResponseStatus::Error;
                $response->message = $result['errorMessage'] ?? 'İşlem sırasında bir hata oluştu';
            }
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->status = SaleResponseStatus::Error;
        $response->private_response = $request->responseArray;

        $responseArray = $request->responseArray ?? [];

        $response->order_number = (string) ($responseArray['conversationId'] ?? '');
        $response->transaction_id = (string) ($responseArray['paymentId'] ?? '');

        $status = $responseArray['status'] ?? '';
        $mdStatus = (int) ($responseArray['mdStatus'] ?? 0);

        if ($status === 'success' && $mdStatus === 1) {
            $paymentRequest = new CreateThreedsPaymentRequest;
            $paymentRequest->locale = 'tr';
            $paymentRequest->conversationId = $responseArray['conversationId'] ?? '';
            $paymentRequest->paymentId = $responseArray['paymentId'] ?? '';
            $paymentRequest->conversationData = $responseArray['conversationData'] ?? '';

            $options = $this->getOptions($auth);
            $headers = IyzicoHashGenerator::getHttpHeaders($paymentRequest, $options);

            $result = IyzicoHttpClient::post(
                $options->baseUrl . '/payment/3dsecure/auth',
                $headers,
                $paymentRequest->toArray()
            );

            $response->private_response = array_merge($response->private_response, ['threedsPayment' => $result]);

            if (strtolower($result['status'] ?? '') === 'success') {
                $response->status = SaleResponseStatus::Success;
                $response->message = 'Ödeme başarılı';
            } else {
                $response->status = SaleResponseStatus::Error;
                $response->message = $result['errorMessage'] ?? 'İşlem tamamlanamadı';
            }
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = match ($mdStatus) {
                0 => '3D Secure doğrulaması geçersiz',
                2 => 'Kart sahibi veya bankası sisteme kayıtlı değil',
                3 => 'Kartın bankası sisteme kayıtlı değil',
                4 => 'Doğrulama denemesi, kart sahibi sisteme daha sonra kayıt olmayı seçmiş',
                5 => 'Doğrulama yapılamıyor',
                6 => '3D Secure hatası',
                7 => 'Sistem hatası',
                8 => 'Bilinmeyen kart numarası',
                default => '3D doğrulaması başarısız',
            };
        }

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error);

        $ip = ! empty($request->customer_ip_address) ? $request->customer_ip_address : '1.1.1.1';

        $req = new CreateCancelRequest;
        $req->conversationId = $request->order_number;
        $req->locale = 'tr';
        $req->paymentId = $request->transaction_id;
        $req->ip = $ip;

        $options = $this->getOptions($auth);
        $headers = IyzicoHashGenerator::getHttpHeaders($req, $options);

        $result = IyzicoHttpClient::post(
            $options->baseUrl . '/payment/cancel',
            $headers,
            $req->toArray()
        );

        $response->private_response = $result;

        if (($result['status'] ?? '') === 'success') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İade işlemi başarılı';
            $response->refund_amount = isset($result['price']) ? (float) $result['price'] : 0;
        } else {
            $response->message = $result['errorMessage'] ?? 'İptal işlemi başarısız';
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);

        $ip = ! empty($request->customer_ip_address) ? $request->customer_ip_address : '1.1.1.1';
        $amount = $this->formatIyzicoPrice($request->refund_amount);

        $req = new CreateAmountBasedRefundRequest;
        $req->locale = 'tr';
        $req->conversationId = $request->order_number;
        $req->ip = $ip;
        $req->price = $amount;
        $req->paymentId = $request->transaction_id;

        $options = $this->getOptions($auth);
        $headers = IyzicoHashGenerator::getHttpHeaders($req, $options);

        $result = IyzicoHttpClient::post(
            $options->baseUrl . '/v2/payment/refund',
            $headers,
            $req->toArray()
        );

        $response->private_response = $result;

        if (($result['status'] ?? '') === 'success') {
            $response->status = ResponseStatus::Success;
            $response->message = 'İade işlemi başarılı';
            $response->refund_amount = isset($result['price']) ? (float) $result['price'] : 0;
        } else {
            $response->message = $result['errorMessage'] ?? 'İade işlemi başarısız';
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, MerchantAuth $auth): BINInstallmentQueryResponse
    {
        $response = new BINInstallmentQueryResponse(confirm: false);

        $amount = $this->formatIyzicoPrice($request->amount);

        $req = new RetrieveInstallmentInfoRequest;
        $req->locale = 'tr';
        $req->conversationId = uniqid();
        $req->binNumber = $request->BIN;
        $req->price = $amount;

        $options = $this->getOptions($auth);
        $headers = IyzicoHashGenerator::getHttpHeaders($req, $options);

        $result = IyzicoHttpClient::post(
            $options->baseUrl . '/payment/iyzipos/installment',
            $headers,
            $req->toArray()
        );

        $response->private_response = $result;

        $installmentDetails = $result['installmentDetails'] ?? [];

        if (($result['status'] ?? '') === 'success' && ! empty($installmentDetails)) {
            $detail = $installmentDetails[0];
            $installmentPrices = $detail['installmentPrices'] ?? [];

            if (! empty($installmentPrices)) {
                $response->confirm = true;

                foreach ($installmentPrices as $item) {
                    $installmentNumber = (int) ($item['installmentNumber'] ?? 0);
                    if ($installmentNumber > 1) {
                        $totalPrice = (float) ($item['totalPrice'] ?? $request->amount);
                        $commissionRate = (($totalPrice - $request->amount) / $request->amount) * 100;

                        $response->installment_list[] = [
                            'installment' => $installmentNumber,
                            'rate' => round($commissionRate, 2),
                            'totalAmount' => $totalPrice,
                        ];
                    }
                }
            }
        }

        return $response;
    }

    // --- Private helpers ---

    private function getOptions(MerchantAuth $auth): IyzicoOptions
    {
        return new IyzicoOptions(
            apiKey: $auth->merchant_user,
            secretKey: $auth->merchant_password,
            baseUrl: $auth->test_platform ? $this->urlAPITest : $this->urlAPILive,
        );
    }

    /**
     * Iyzico fiyat formatı: 100.50 â†’ "100.5", 100.00 â†’ "100.0"
     */
    private function formatIyzicoPrice(float $amount): string
    {
        $formatted = number_format($amount, 2, '.', '');

        return PKIRequestStringBuilder::formatPrice($formatted);
    }
}

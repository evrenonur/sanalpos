<?php

namespace EvrenOnur\SanalPos\Gateways\PaymentInstitutions;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Enums\ResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatu;
use EvrenOnur\SanalPos\Enums\SaleResponseStatu;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\IyzicoHashGenerator;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\IyzicoHttpClient;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\IyzicoOptions;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\Model\IyzicoAddress;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\Model\IyzicoBasketItem;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\Model\IyzicoBuyer;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\Model\IyzicoPaymentCard;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\PKIRequestStringBuilder;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\Request\CreateAmountBasedRefundRequest;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\Request\CreateCancelRequest;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\Request\CreatePaymentRequest;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\Request\CreateThreedsPaymentRequest;
use EvrenOnur\SanalPos\Infrastructures\Iyzico\Request\RetrieveInstallmentInfoRequest;
use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\AllInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\CancelRequest;
use EvrenOnur\SanalPos\Models\CancelResponse;
use EvrenOnur\SanalPos\Models\RefundRequest;
use EvrenOnur\SanalPos\Models\RefundResponse;
use EvrenOnur\SanalPos\Models\Sale3DResponseRequest;
use EvrenOnur\SanalPos\Models\SaleQueryRequest;
use EvrenOnur\SanalPos\Models\SaleQueryResponse;
use EvrenOnur\SanalPos\Models\SaleRequest;
use EvrenOnur\SanalPos\Models\SaleResponse;
use EvrenOnur\SanalPos\Models\VirtualPOSAuth;

/**
 * Iyzico sanal POS gateway implementasyonu.
 */
class IyzicoGateway implements VirtualPOSServiceInterface
{
    private string $urlAPITest = 'https://sandbox-api.iyzipay.com';

    private string $urlAPILive = 'https://api.iyzipay.com';

    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse(orderNumber: $request->orderNumber);

        if (empty($request->invoiceInfo?->taxNumber)) {
            if ($request->invoiceInfo !== null) {
                $request->invoiceInfo->taxNumber = '11111111111';
            }
        }

        if (empty($request->customerIPAddress)) {
            $request->customerIPAddress = '1.1.1.1';
        }

        $amount = $this->formatIyzicoPrice($request->saleInfo->amount);

        $req = new CreatePaymentRequest;
        $req->locale = 'tr';
        $req->conversationId = $request->orderNumber;
        $req->price = $amount;
        $req->paidPrice = $amount;
        $req->currency = $request->saleInfo->currency?->name ?? 'TRY';
        $req->installment = $request->saleInfo->installment;
        $req->basketId = $request->orderNumber;

        // Kart bilgileri
        $paymentCard = new IyzicoPaymentCard;
        $paymentCard->cardHolderName = $request->saleInfo->cardNameSurname;
        $paymentCard->cardNumber = $request->saleInfo->cardNumber;
        $paymentCard->expireMonth = str_pad($request->saleInfo->cardExpiryDateMonth, 2, '0', STR_PAD_LEFT);
        $paymentCard->expireYear = (string) $request->saleInfo->cardExpiryDateYear;
        $paymentCard->cvc = $request->saleInfo->cardCVV;
        $req->paymentCard = $paymentCard;

        // Alıcı bilgileri
        $buyer = new IyzicoBuyer;
        $buyer->id = $request->invoiceInfo?->emailAddress ?? 'buyer@test.com';
        $buyer->name = $request->invoiceInfo?->name ?? 'Müşteri';
        $buyer->surname = $request->invoiceInfo?->surname ?? $request->invoiceInfo?->name ?? 'Müşteri';
        $buyer->gsmNumber = $request->invoiceInfo?->phoneNumber ?? '';
        $buyer->email = $request->invoiceInfo?->emailAddress ?? '';
        $buyer->identityNumber = $request->invoiceInfo?->taxNumber ?? '11111111111';
        $buyer->registrationAddress = $request->invoiceInfo?->addressDesc ?? '';
        $buyer->ip = $request->customerIPAddress;
        $buyer->city = $request->invoiceInfo?->city ?? '';
        $buyer->country = $request->invoiceInfo?->country?->name ?? 'Turkey';
        $buyer->zipCode = $request->invoiceInfo?->postCode ?? '';
        $req->buyer = $buyer;

        // Kargo adresi
        $shippingAddress = new IyzicoAddress;
        $shippingAddress->contactName = $request->shippingInfo?->name ?? $request->saleInfo->cardNameSurname;
        $shippingAddress->city = $request->shippingInfo?->city ?? '';
        $shippingAddress->country = $request->shippingInfo?->country?->name ?? 'Turkey';
        $shippingAddress->address = $request->shippingInfo?->addressDesc ?? '';
        $shippingAddress->zipCode = $request->shippingInfo?->postCode ?? '';
        $req->shippingAddress = $shippingAddress;

        // Fatura adresi
        $billingAddress = new IyzicoAddress;
        $billingAddress->contactName = $request->invoiceInfo?->name ?? $request->saleInfo->cardNameSurname;
        $billingAddress->city = $request->invoiceInfo?->city ?? '';
        $billingAddress->country = $request->invoiceInfo?->country?->name ?? 'Turkey';
        $billingAddress->address = $request->invoiceInfo?->addressDesc ?? '';
        $billingAddress->zipCode = $request->invoiceInfo?->postCode ?? '';
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

        if ($request->payment3D?->confirm === true) {
            // 3D Secure
            $req->callbackUrl = $request->payment3D->returnURL;

            $result = IyzicoHttpClient::post(
                $options->baseUrl . '/payment/3dsecure/initialize',
                $headers,
                $req->toArray()
            );

            $response->privateResponse = $result;

            if (($result['status'] ?? '') === 'success') {
                $htmlContent = $result['threeDSHtmlContent'] ?? '';
                if (! empty($htmlContent)) {
                    $decodedHtml = base64_decode($htmlContent);
                    $response->statu = SaleResponseStatu::RedirectHTML;
                    $response->message = $decodedHtml;
                } else {
                    $response->statu = SaleResponseStatu::Error;
                    $response->message = '3D HTML içeriği alınamadı';
                }
            } else {
                $response->statu = SaleResponseStatu::Error;
                $response->message = $result['errorMessage'] ?? 'İşlem sırasında bir hata oluştu';
            }
        } else {
            // Normal ödeme
            $result = IyzicoHttpClient::post(
                $options->baseUrl . '/payment/auth',
                $headers,
                $req->toArray()
            );

            $response->privateResponse = $result;

            if (($result['status'] ?? '') === 'success') {
                $response->statu = SaleResponseStatu::Success;
                $response->message = 'İşlem başarıyla tamamlandı';
                $response->transactionId = $result['paymentId'] ?? '';
            } else {
                $response->statu = SaleResponseStatu::Error;
                $response->message = $result['errorMessage'] ?? 'İşlem sırasında bir hata oluştu';
            }
        }

        return $response;
    }

    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->statu = SaleResponseStatu::Error;
        $response->privateResponse = $request->responseArray;

        $responseArray = $request->responseArray ?? [];

        $response->orderNumber = (string) ($responseArray['conversationId'] ?? '');
        $response->transactionId = (string) ($responseArray['paymentId'] ?? '');

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

            $response->privateResponse = array_merge($response->privateResponse, ['threedsPayment' => $result]);

            if (strtolower($result['status'] ?? '') === 'success') {
                $response->statu = SaleResponseStatu::Success;
                $response->message = 'Ödeme başarılı';
            } else {
                $response->statu = SaleResponseStatu::Error;
                $response->message = $result['errorMessage'] ?? 'İşlem tamamlanamadı';
            }
        } else {
            $response->statu = SaleResponseStatu::Error;
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

    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        $response = new CancelResponse(statu: ResponseStatu::Error);

        $ip = ! empty($request->customerIPAddress) ? $request->customerIPAddress : '1.1.1.1';

        $req = new CreateCancelRequest;
        $req->conversationId = $request->orderNumber;
        $req->locale = 'tr';
        $req->paymentId = $request->transactionId;
        $req->ip = $ip;

        $options = $this->getOptions($auth);
        $headers = IyzicoHashGenerator::getHttpHeaders($req, $options);

        $result = IyzicoHttpClient::post(
            $options->baseUrl . '/payment/cancel',
            $headers,
            $req->toArray()
        );

        $response->privateResponse = $result;

        if (($result['status'] ?? '') === 'success') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İade işlemi başarılı';
            $response->refundAmount = isset($result['price']) ? (float) $result['price'] : 0;
        } else {
            $response->message = $result['errorMessage'] ?? 'İptal işlemi başarısız';
        }

        return $response;
    }

    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        $response = new RefundResponse(statu: ResponseStatu::Error);

        $ip = ! empty($request->customerIPAddress) ? $request->customerIPAddress : '1.1.1.1';
        $amount = $this->formatIyzicoPrice($request->refundAmount);

        $req = new CreateAmountBasedRefundRequest;
        $req->locale = 'tr';
        $req->conversationId = $request->orderNumber;
        $req->ip = $ip;
        $req->price = $amount;
        $req->paymentId = $request->transactionId;

        $options = $this->getOptions($auth);
        $headers = IyzicoHashGenerator::getHttpHeaders($req, $options);

        $result = IyzicoHttpClient::post(
            $options->baseUrl . '/v2/payment/refund',
            $headers,
            $req->toArray()
        );

        $response->privateResponse = $result;

        if (($result['status'] ?? '') === 'success') {
            $response->statu = ResponseStatu::Success;
            $response->message = 'İade işlemi başarılı';
            $response->refundAmount = isset($result['price']) ? (float) $result['price'] : 0;
        } else {
            $response->message = $result['errorMessage'] ?? 'İade işlemi başarısız';
        }

        return $response;
    }

    public function binInstallmentQuery(BINInstallmentQueryRequest $request, VirtualPOSAuth $auth): BINInstallmentQueryResponse
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

        $response->privateResponse = $result;

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

                        $response->installmentList[] = [
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

    public function allInstallmentQuery(AllInstallmentQueryRequest $request, VirtualPOSAuth $auth): AllInstallmentQueryResponse
    {
        return new AllInstallmentQueryResponse(confirm: false);
    }

    public function additionalInstallmentQuery(AdditionalInstallmentQueryRequest $request, VirtualPOSAuth $auth): AdditionalInstallmentQueryResponse
    {
        return new AdditionalInstallmentQueryResponse(confirm: false);
    }

    public function saleQuery(SaleQueryRequest $request, VirtualPOSAuth $auth): SaleQueryResponse
    {
        return new SaleQueryResponse(
            statu: SaleQueryResponseStatu::Error,
            message: 'Bu sanal pos için satış sorgulama işlemi şuan desteklenmiyor'
        );
    }

    // --- Private helpers ---

    private function getOptions(VirtualPOSAuth $auth): IyzicoOptions
    {
        return new IyzicoOptions(
            apiKey: $auth->merchantUser,
            secretKey: $auth->merchantPassword,
            baseUrl: $auth->testPlatform ? $this->urlAPITest : $this->urlAPILive,
        );
    }

    /**
     * Iyzico fiyat formatı: 100.50 → "100.5", 100.00 → "100.0"
     */
    private function formatIyzicoPrice(float $amount): string
    {
        $formatted = number_format($amount, 2, '.', '');

        return PKIRequestStringBuilder::formatPrice($formatted);
    }
}

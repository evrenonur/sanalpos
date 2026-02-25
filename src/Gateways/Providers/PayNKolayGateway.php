<?php

namespace EvrenOnur\SanalPos\Gateways\Providers;

use EvrenOnur\SanalPos\DTOs\AllInstallment;
use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\AllInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Enums\CreditCardProgram;
use EvrenOnur\SanalPos\Enums\Currency;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleResponseStatus;
use EvrenOnur\SanalPos\Gateways\AbstractGateway;
use EvrenOnur\SanalPos\Support\StringHelper;

class PayNKolayGateway extends AbstractGateway
{

    private string $urlTest = 'https://paynkolaytest.nkolayislem.com.tr';

    private string $urlLive = 'https://paynkolay.nkolayislem.com.tr';

    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse(order_number: $request->order_number);
        $baseUrl = $this->getBaseUrl($auth);
        $is3D = $request->payment_3d?->confirm === true;

        $amount = StringHelper::formatAmount($request->sale_info->amount);
        $installment = $request->sale_info->installment > 1 ? $request->sale_info->installment : 1;
        $rnd = date('d.m.Y H:i:s');
        $customerKey = $auth->merchant_storekey;

        $hashData = implode('|', [
            $auth->merchant_id,
            $request->order_number,
            $amount,
            $request->payment_3d?->return_url ?? '',
            $request->payment_3d?->return_url ?? '',
            $rnd,
            $customerKey,
            $auth->merchant_password,
        ]);
        $hash = base64_encode(hash('sha512', $hashData, true));

        $params = [
            'sx' => $auth->merchant_id,
            'clientRefCode' => $request->order_number,
            'cardHolderName' => $request->sale_info->card_name_surname,
            'cardNo' => $request->sale_info->card_number,
            'month' => str_pad($request->sale_info->card_expiry_month, 2, '0', STR_PAD_LEFT),
            'year' => (string) $request->sale_info->card_expiry_year,
            'cvc' => $request->sale_info->card_cvv,
            'amount' => $amount,
            'currency' => StringHelper::getCurrencyCode($request->sale_info->currency ?? Currency::TRY),
            'installmentCount' => (string) $installment,
            'transactionType' => 'SALES',
            'environment' => 'API',
            'customerKey' => $customerKey,
            'rnd' => $rnd,
            'hash' => $hash,
            'use3D' => $is3D ? 'true' : 'false',
        ];

        if ($is3D) {
            $params['successUrl'] = $request->payment_3d->return_url;
            $params['failUrl'] = $request->payment_3d->return_url;
        }

        $resp = $this->formRequest($params, $baseUrl . '/Vpos/v1/Payment');
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        $responseCode = (int) ($dic['RESPONSE_CODE'] ?? 0);

        if ($responseCode === 2) {
            if ($is3D) {
                $use3D = $dic['USE_3D'] ?? '';
                if ($use3D === 'true') {
                    $html = $dic['BANK_REQUEST_MESSAGE'] ?? '';
                    $response->status = SaleResponseStatus::RedirectHTML;
                    $response->message = $this->cleanHtml($html);

                    return $response;
                }
            }

            $authCode = $dic['AUTH_CODE'] ?? '0';
            if (! empty($authCode) && $authCode !== '0') {
                $response->status = SaleResponseStatus::Success;
                $response->message = 'İşlem başarılı';
                $response->transaction_id = (string) ($dic['REFERENCE_CODE'] ?? '');

                return $response;
            }
        }

        $response->status = SaleResponseStatus::Error;
        $response->message = $dic['RESPONSE_MSG'] ?? 'İşlem sırasında bir hata oluştu';

        return $response;
    }

    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        $response = new SaleResponse;
        $response->private_response = ['response_1' => $request->responseArray];

        $response->order_number = (string) ($request->responseArray['CLIENT_REFERENCE_CODE'] ?? '');
        $responseCode = (int) ($request->responseArray['RESPONSE_CODE'] ?? 0);
        $referenceCode = $request->responseArray['REFERENCE_CODE'] ?? '';

        if ($responseCode !== 2 || empty($referenceCode)) {
            $response->status = SaleResponseStatus::Error;
            $response->message = $request->responseArray['RESPONSE_MSG'] ?? '3D doğrulaması başarısız';

            return $response;
        }

        // CompletePayment çağrısı
        $baseUrl = $this->getBaseUrl($auth);

        $params = [
            'sx' => $auth->merchant_password, // Cancel/Refund'da merchant_password kullanılıyor
            'referenceCode' => $referenceCode,
        ];

        $resp = $this->formRequest($params, $baseUrl . '/Vpos/v1/CompletePayment');
        $dic = json_decode($resp, true) ?? [];

        $response->private_response['response_2'] = $dic;

        $completeCode = (int) ($dic['RESPONSE_CODE'] ?? 0);
        $authCode = $dic['AUTH_CODE'] ?? '0';

        if ($completeCode === 2 && ! empty($authCode) && $authCode !== '0') {
            $response->status = SaleResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->transaction_id = (string) ($dic['REFERENCE_CODE'] ?? $referenceCode);
        } else {
            $response->status = SaleResponseStatus::Error;
            $response->message = $dic['RESPONSE_MSG'] ?? 'İşlem tamamlanamadı';
        }

        return $response;
    }

    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        $response = new CancelResponse(status: ResponseStatus::Error);
        $baseUrl = $this->getBaseUrl($auth);

        $rnd = date('d.m.Y H:i:s');
        $hashData = implode('|', [
            $auth->merchant_password,
            $request->transaction_id,
            'cancel',
            '0',
            '',
            $auth->merchant_storekey,
        ]);
        $hash = base64_encode(hash('sha512', $hashData, true));

        $params = [
            'sx' => $auth->merchant_password,
            'referenceCode' => $request->transaction_id,
            'type' => 'cancel',
            'amount' => '0',
            'trxDate' => '',
            'hash' => $hash,
            'rnd' => $rnd,
        ];

        $resp = $this->formRequest($params, $baseUrl . '/Vpos/v1/CancelRefundPayment');
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if ((int) ($dic['RESPONSE_CODE'] ?? 0) === 2) {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
        } else {
            $response->message = $dic['RESPONSE_MSG'] ?? 'İşlem iptal edilemedi';
        }

        return $response;
    }

    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        $response = new RefundResponse(status: ResponseStatus::Error);
        $baseUrl = $this->getBaseUrl($auth);

        $amount = StringHelper::formatAmount($request->refund_amount);
        $rnd = date('d.m.Y H:i:s');
        $hashData = implode('|', [
            $auth->merchant_password,
            $request->transaction_id,
            'refund',
            $amount,
            '',
            $auth->merchant_storekey,
        ]);
        $hash = base64_encode(hash('sha512', $hashData, true));

        $params = [
            'sx' => $auth->merchant_password,
            'referenceCode' => $request->transaction_id,
            'type' => 'refund',
            'amount' => $amount,
            'trxDate' => '',
            'hash' => $hash,
            'rnd' => $rnd,
        ];

        $resp = $this->formRequest($params, $baseUrl . '/Vpos/v1/CancelRefundPayment');
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        if ((int) ($dic['RESPONSE_CODE'] ?? 0) === 2) {
            $response->status = ResponseStatus::Success;
            $response->message = 'İşlem başarılı';
            $response->refund_amount = $request->refund_amount;
        } else {
            $response->message = $dic['RESPONSE_MSG'] ?? 'İşlem iade edilemedi';
        }

        return $response;
    }

    public function allInstallmentQuery(AllInstallmentQueryRequest $request, MerchantAuth $auth): AllInstallmentQueryResponse
    {
        $response = new AllInstallmentQueryResponse(confirm: false);
        $baseUrl = $this->getBaseUrl($auth);

        $params = [
            'sx' => $auth->merchant_id,
        ];

        $resp = $this->formRequest($params, $baseUrl . '/Vpos/Payment/GetMerchandInformation');
        $dic = json_decode($resp, true) ?? [];

        $response->private_response = $dic;

        $commissions = $dic['COMMISSIONS'] ?? [];
        if (is_array($commissions)) {
            $installment_list = [];
            foreach ($commissions as $comm) {
                $programName = $comm['CARD_PROGRAM'] ?? 'Other';
                $program = CreditCardProgram::tryFromName($programName) ?? CreditCardProgram::Other;
                $installment = (int) ($comm['INSTALLMENT'] ?? 0);
                $rate = (float) ($comm['COMMISSION_RATE'] ?? 0);

                if (! isset($installment_list[$programName])) {
                    $installment_list[$programName] = new AllInstallment(
                        cardProgram: $program,
                        installment_list: [],
                    );
                }
                $installment_list[$programName]->installment_list[] = [
                    'installment' => $installment,
                    'rate' => $rate,
                ];
            }
            $response->installment_list = array_values($installment_list);
            if (! empty($response->installment_list)) {
                $response->confirm = true;
            }
        }

        return $response;
    }

    // --- Private helpers ---

    private function getBaseUrl(MerchantAuth $auth): string
    {
        return $auth->test_platform ? $this->urlTest : $this->urlLive;
    }

    private function cleanHtml(string $html): string
    {
        // URL-encoded HTML temizleme
        $html = urldecode($html);

        return $html;
    }

    private function formRequest(array $params, string $url): string
    {
        return $this->httpPostForm($url, $params);
    }
}

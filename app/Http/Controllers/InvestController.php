<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Request as FundRequest;
use App\User;
use Illuminate\Support\Facades\Auth;

class InvestController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Invest Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling investment requests
    | , redirecting to payment page and handling returns from payment page
    | It collects amount to be invested and amount requested and compares them
    | then proceeds appropriately
    */

    /**
     * Handles comparison of Amounts*
     * @param Request $request
     */

    public function index(Request $request)
    {
     
      $request_id = $request->input('request_id');
        $amount_invested = $request->input('amount_invested');
        
        $funder_email = User::find($request->input('user_id'))->email;


        
            $txref = uniqid(rand(0, 1000));
            $curl = curl_init();

            $customer_email = $funder_email;
            $amount = $amount_invested;
            $currency = "NGN";
            $PBFPubKey = "FLWPUBK_TEST-babd6d1a417bdd33d5af0cd1729b36c6-X";
            $redirect_url = url('api/v1/invest/redirect/' . $request_id);
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.ravepay.co/flwv3-pug/getpaidx/api/v2/hosted/pay",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'amount' => $amount,
                    'customer_email' => $customer_email,
                    'currency' => $currency,
                    'txref' => $txref,
                    'PBFPubKey' => $PBFPubKey,
                    'redirect_url' => $redirect_url
                ]),
                CURLOPT_HTTPHEADER => [
                    "content-type: application/json",
                    "cache-control: no-cache"
                ],
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            if ($err) {
                // there was an error contacting the rave API
                return response()->json(['message' => $err], 404);
            }

            $transaction = json_decode($response);

            if (!$transaction->data && !$transaction->data->link) {
                // there was an error from the API
                return response()->json(['message' => 'There is an error from the API'], 400);
            }

            // redirect to page so User can pay
            return redirect($transaction->data->link);
        
    }


    /**
     * Handle successfull or failed transactions
     *
     * @param $request_id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function redirect($request_id, Request $request)
    {

        if (isset($request['cancelled'])) {

            return response()->json(['message' => "Transaction was cancelled"], 400);
        }


        if ($request->query('txref')) {
            $ref = $request->query('txref');
            $amount = FundRequest::find($request_id)['amount'];
            $currency = "NGN";

            $query = array(
                "SECKEY" => "FLWSECK_TEST-64a553127109b16a7164cc9ba03859ec-X",
                "txref" => $ref
            );

            $data_string = json_encode($query);

            $ch = curl_init('https://api.ravepay.co/flwv3-pug/getpaidx/api/v2/verify ');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

            $response = curl_exec($ch);


            curl_close($ch);

            $resp = json_decode($response, true);

            $paymentStatus = $resp['data']['status'];
            $chargeResponsecode = $resp['data']['chargecode'];
            $chargeAmount = $resp['data']['amount'];
            $chargeCurrency = $resp['data']['currency'];

            $query = array(
                "request_id" => $request_id,
                "transaction_ref" => $ref,
                "amount" => $chargeAmount,
                "status" => $paymentStatus,
                "response_code" => intval($chargeResponsecode)

            );

            $data_string = json_encode($query);
            if (($chargeResponsecode == "00" || $chargeResponsecode == "0") && ($chargeAmount == $amount) && ($chargeCurrency == $currency)) {
                $store = curl_init(url('api/v1/transaction/store'));
                curl_setopt($store, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($store, CURLOPT_POSTFIELDS, $data_string);
                curl_setopt($store, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($store, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($store, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                $response = curl_exec($store);
                curl_close($store);
                return $response;
            } else {
                return response()->json(['message' => "Transaction failed"], 400);
            }
        }

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}

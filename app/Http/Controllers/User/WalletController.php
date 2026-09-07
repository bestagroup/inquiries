<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\Billing\WalletService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function __invoke(Request $request, WalletService $wallets): View
    {
        $wallet = $wallets->walletFor($request->user());
        $transactions = $wallet->transactions()
            ->with(['serviceRequest.service:id,name'])
            ->paginate(25)
            ->withQueryString();

        return view('user.wallet.show', compact('wallet', 'transactions'));
    }
}

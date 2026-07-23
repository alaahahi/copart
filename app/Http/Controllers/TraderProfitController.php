<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteTraderProfitEntryRequest;
use App\Http\Requests\PostTraderProfitRequest;
use App\Http\Requests\WithdrawTraderProfitRequest;
use App\Services\TraderProfitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TraderProfitController extends Controller
{
    protected function authorizeProfits(): void
    {
        if (!Auth::check() || !in_array((int) Auth::user()->type_id, [1, 6], true)) {
            abort(403, 'غير مسموح');
        }
    }

    /**
     * Current Trader Profits reserve balance + recent post/withdraw history.
     */
    public function summary(Request $request, TraderProfitService $profits)
    {
        $this->authorizeProfits();

        $ownerId = (int) Auth::user()->owner_id;
        $currency = $request->get('currency', '$');

        return Response::json([
            'balance' => $profits->balance($ownerId, $currency),
            'currency' => $currency,
            'entries' => $profits->recent($ownerId)->map(fn ($entry) => $this->formatEntry($entry)),
        ], 200);
    }

    public function post(PostTraderProfitRequest $request, TraderProfitService $profits)
    {
        $this->authorizeProfits();

        $ownerId = (int) Auth::user()->owner_id;
        $validated = $request->validated();

        try {
            $entry = $profits->postProfit(
                $ownerId,
                (int) $validated['client_id'],
                (float) $validated['amount'],
                $validated['currency'],
                $validated['period_from'] ?? null,
                $validated['period_to'] ?? null,
                $validated['notes'] ?? null,
                $validated['entry_date'] ?? null
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Response::json(['message' => 'تعذر ترحيل أرباح التاجر'], 500);
        }

        return Response::json([
            'message' => 'تم ترحيل أرباح التاجر إلى حساب الأرباح بنجاح',
            'entry' => $this->formatEntry($entry),
        ], 201);
    }

    public function withdraw(WithdrawTraderProfitRequest $request, TraderProfitService $profits)
    {
        $this->authorizeProfits();

        $ownerId = (int) Auth::user()->owner_id;
        $validated = $request->validated();

        try {
            $entry = $profits->withdraw(
                $ownerId,
                (float) $validated['amount'],
                $validated['currency'],
                $validated['notes'] ?? null,
                $validated['entry_date'] ?? null
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Response::json(['message' => 'تعذر تنفيذ السحب من حساب الأرباح'], 500);
        }

        return Response::json([
            'message' => 'تم السحب من حساب الأرباح بنجاح',
            'entry' => $this->formatEntry($entry),
        ], 201);
    }

    public function destroy(DeleteTraderProfitEntryRequest $request, TraderProfitService $profits)
    {
        $this->authorizeProfits();

        $ownerId = (int) Auth::user()->owner_id;
        $validated = $request->validated();

        try {
            $profits->voidEntry($ownerId, (int) $validated['id'], $validated['reason'] ?? null);
        } catch (Throwable $e) {
            return Response::json(['message' => 'تعذر حذف الحركة'], 500);
        }

        return Response::json(['message' => 'تم حذف الحركة بنجاح'], 200);
    }

    protected function formatEntry($entry): array
    {
        return [
            'id' => $entry->id,
            'type' => $entry->type,
            'client_id' => $entry->client_id,
            'trader' => $entry->client?->name,
            'period_from' => optional($entry->period_from)->format('Y-m-d'),
            'period_to' => optional($entry->period_to)->format('Y-m-d'),
            'amount' => (float) $entry->amount,
            'currency' => $entry->currency,
            'memo' => $entry->memo,
            'journal_entry_id' => $entry->journal_entry_id,
            'created_at' => optional($entry->created_at)->format('Y-m-d H:i'),
        ];
    }
}

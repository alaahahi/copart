# نظام المحاسبة الحالي — Shipping ERP (Copart)

> وثيقة تدقيق للمحاسب/الـ AI المراجع. تصف **الوضع الفعلي في الكود** بعد فصل القاصات (Vaults) عن دليل الحسابات (COA) وإزالة جدول `wallets`.  
> اللغة الأساسية عربية؛ أسماء رموز الحسابات والمصطلحات التقنية بالإنجليزية للدقة.

**تاريخ الوصف:** بناءً على الكود الحالي في المستودع (يوليو 2026).  
**لا تختلق قواعد غير موجودة في الكود** — أي سلوك إرثي (legacy) مذكور صراحة أدناه.

---

## 1. نظرة عامة

النظام يعتمد **القيد المزدوج (Double-Entry Accounting)**.

### مصدر الحقيقة المحاسبية (Source of Truth)

| الطبقة | الجداول / النماذج | الدور |
|--------|-------------------|--------|
| دليل الحسابات | `ledger_accounts` (`LedgerAccount`) | شجرة الحسابات (COA) لكل `owner_id` |
| القيود | `journal_entries` (`JournalEntry`) + `journal_lines` (`JournalLine`) | كل حركة مالية متوازنة مدين/دائن |
| الأرصدة | **محسوبة** من مجموع أسطر القيود غير المحذوفة | لا يوجد عمود رصيد يُحدَّث يدوياً على الحساب |

**قاعدة ذهبية في الكود:** الأرصدة تُحسب من `journal_lines` المرتبطة بـ `journal_entries` غير المحذوفة (`SoftDeletes` على القيد). انظر `LedgerAccount::balance()`.

### القاصات (Vaults) مقابل المحفظة (Wallet)

- **القاصات** (`vaults`): صناديق نقدية تشغيلية فقط (`cash` | `bank` | `safe` + استثناء `mainBox`).
- كل قاصة مربوطة بحساب أصول في الدليل عبر `vaults.ledger_account_id`.
- **جدول `wallets` أُزيل** (migration `2026_07_29_031000_drop_wallets_table`). لا يُفترض وجوده.
- `LedgerService::syncWalletFromLedger()` أصبح **no-op** عمداً (لا يكتب أرصدة محفظة).

### مرآة تشغيلية (ليست مصدر الحقيقة)

جدول `transactions` يبقى مرآة تشغيلية/واجهة:

- يعرض حركة القاصة عبر `vault_id` (و`wallet_id` اختياري إن وُجد عمود قديم).
- يربط القيد عبر `journal_entry_id` عند الترحيل.
- أرجل فرعية (`parent_id > 0`) غالباً **لا تنشئ قيداً** — القيد على الرجل الجذر فقط.

---

## 2. فصل المسؤوليات

### 2.1 القاصات — `vaults` (Cash boxes فقط)

**الأنواع النقدية المعتمدة للإنشاء والفلترة:**

```text
Vault::CASH_TYPES = ['cash', 'bank', 'safe']
```

- الإنشاء من الواجهة مقصور على `VaultService::CREATABLE_TYPES` = نفس الثلاثة.
- أنواع قديمة (`expense`, `commission`, `supplier`, `system`, …) **مهمّشة / deprecated** — لا تُعرض عند الإنشاء؛ قد تبقى صفوف تاريخية.
- الاستثناء: الصندوق الرئيسي `code = mainBox` (أو مستخدم تقني `mainBox@account.com`) يُعامل كصندوق نقد حتى لو `type = system` تاريخياً → `Vault::isCashBox()` / `scopeCashBoxes()`.

**ربط الحساب:**

| القاصة | حساب COA |
|--------|----------|
| قاصة جديدة (نقد/بنك/خزنة) | حساب أصل `11V-{vaultId}` تحت أب الصندوق `1100` (parent_id) |
| الصندوق الرئيسي `mainBox` | يستخدم حسابات النظام النقدية حسب العملة: `1100` دولار / `1110` دينار — وليس `11V-*` بالضرورة للترحيل |

كل قاصة تحتفظ أيضاً بـ **مستخدم تقني** (`legacy_user_id`) لتوافق واجهات الدفع القديمة التي تمرّر `user_id` للصندوق.

**قاصة استلام دفعات الزبائن:**

- الإعداد: `system_config.default_receipts_vault_id`
- الحل: `VaultService::resolveReceiptsVault()` → إن لم تُضبط أو غير صالحة → `mainBox`
- المعرّف المستخدم في الترحيل: `VaultService::receiptsCashUserId()` = `legacy_user_id` لهذه القاصة

**قاصة صرف المشتريات:**

- الإعداد: `system_config.default_purchases_vault_id`
- الحل: `VaultService::resolvePurchasesVault()` → إن لم تُضبط أو غير صالحة → `mainBox`
- المعرّف المستخدم في الترحيل: `VaultService::purchasesCashUserId()` = `legacy_user_id` لهذه القاصة
- تُستخدم عند خصم تكلفة شراء السيارات / تعديل التكلفة / مرتجع حذف السيارة — **وليست** لاستلام دفعات الزبائن

### 2.2 دليل الحسابات — Chart of Accounts (COA)

أنواع الحساب: `asset` | `liability` | `equity` | `income` | `expense`.

#### حسابات النظام الافتراضية (`LedgerService::systemAccountDefaults`)

| Code | Name (EN) | الاسم العربي | Type |
|------|-----------|--------------|------|
| `1100` | Cash USD | الصندوق (دولار) | asset |
| `1110` | Cash IQD | الصندوق (دينار) | asset |
| `1120` | Company Treasury USD | قاصة الشركة دولار | asset |
| `1130` | Company Treasury IQD | قاصة الشركة دينار | asset |
| `4100` | Shipping Revenue | إيرادات الشحن | income |
| `5100` | General Expenses | مصاريف عامة | expense |
| `3900` | Opening Capital | رأس المال الافتتاحي | equity |
| `3200` | Trader Profits Reserve | حساب أرباح التجار | equity |

#### حسابات العملاء / الأطراف

| النمط | المعنى |
|-------|--------|
| `1200-{clientId}` | ذمم الزبون / دفعات السيارات (Accounts Receivable — AR) |
| `1210-{clientId}` | عهدة محاسبة دولار للتاجر (عند `show_in_dashboard`) — **ليست** قاصة نظام |
| `1220-{clientId}` | عهدة محاسبة دينار — نفس الملاحظة |

إنشاء حساب مصروف مخصص بلا أب → يُربط تلقائياً تحت `5100`.  
اقتراح الرموز: مصاريف `51xx`، عمولات `52xx` (`suggestExpenseAccountCode`).

### 2.3 العملات

- دولار: `'$'`
- دينار عراقي: `'IQD'`
- التوازن يُتحقق **لكل عملة داخل القيد** على حدة (`LedgerService::post`).

### 2.4 صيغة الرصيد

من `LedgerAccount::balance()`:

- `asset` / `expense`: مدين − دائن
- `liability` / `equity` / `income`: دائن − مدين
- الأسطر على قيود محذوفة (`deleted_at`) لا تدخل (`whereHas('entry')`)

---

## 3. القيود القياسية (Debit / Credit)

كل قيد يمر عبر `LedgerService::post()`:

- سطران على الأقل
- كل سطر إما مدين أو دائن (ليس الاثنين)
- مجموع المدين = مجموع الدائن **لكل عملة**
- `voucher_no` مثل: `JV-{ownerId}-{year}-{序号}`

### 3.1 قبض من عميل / دفعة سيارة — `postClientPayment`

**متى:**  
`AccountingController::increaseWallet` عندما تكون الحركة على قاصة استلام/`cash_box` و`morphed` = عميل (تاجر)، أو استدعاء مباشر لـ `postClientPayment`.

**مثال مسارات تشغيلية:**

- `addPaymentCar` / دفعات العميل: الأب على `receiptsCashUserId` + morph العميل → قيد واحد
- أرجل الأبناء (`parent_id`) مرآة واجهة فقط — **بدون قيد ثانٍ**

| جانب | حساب | مبلغ |
|------|------|------|
| **مدين (Dr)** | نقد القاصة (mainBox → `1100`/`1110` أو `11V-*`) | `amount` (النقد المستلم) |
| **مدين (Dr)** إن وُجد خصم | مصروف `5100` | `discount` |
| **دائن (Cr)** | ذمم العميل `1200-{clientId}` | `amount` أو `amount + discount` |

`source` في القيد: غالباً `'wallet'` (اسم إرثي).

### 3.2 زيادة مديونية عميل (دين بيع/شحن) — `postClientDebtIncrease`

يُستدعى عبر `postWalletIncrease` عندما `walletPostingKind = client` (مسارات عامة غير تسعير بيع السيارة):

| مدين | دائن |
|------|------|
| AR `1200-{clientId}` | إيراد `4100` |

### 3.2.1 تسعير بيع سيارة (updateCarsS) — `postCarSaleClientDebt`

عند تعديل `total_s` يجب **ألا** يُرحَّل كامل المبلغ كإيراد. الربح فقط → `4100`؛ التكلفة تُسترد على مصروف مشتريات السيارات:

| مدين | دائن |
|------|------|
| AR `1200-{clientId}` = Δ`total_s` | مشتريات سيارات `5110` = استرداد التكلفة (Δمبيعات − Δربح) |
| | إيراد `4100` = Δ`profit` فقط (`total_s − total` عبر `CarService::computeProfit`) |

- مرآة العمليات: `AccountingController::adjustCarSaleClientDebt`
- لا يمس نقد القاصة ولا دفعات العميل (AR/Cash منفصلة)
- إصلاح تاريخي اختياري: `php artisan ledger:repair-car-sale-revenue` (افتراضي dry-run؛ `--execute` للترحيل)

### 3.3 وصل قبض مباشر على الصندوق — `postCashReceipt`

عندما الزيادة على `cash_box` **بدون** اعتبارها دفعة عميل (morph ليس عميلاً):

| مدين | دائن |
|------|------|
| نقد القاصة | إيراد `4100` |

مسار واجهة: `receiptArrivedUser` → `increaseWallet` على قاصة الاستلام.

### 3.4 صرف مصروف / عمولة — `postCashDisbursement`

| مدين | دائن |
|------|------|
| حساب مصروف COA (`expenseAccountId` أو الافتراضي `5100`) | نقد القاصة |

مسارات:

- `LedgerController` صرف مصروف من حساب مصروف محدد + قاصة نقد
- `salesDebtUser` / `debtWallet` على صندوق نقد → `postWalletDecrease` → `postCashDisbursement` (افتراضي `5100` إن لم يُمرَّر حساب)

**مهم:** المصروف يُسجَّل ضد **حساب مصروف في الدليل**، وليس كنوع قاصة `expense`.

### 3.5 تحويل نقدي بين القاصات — `postAccountTransfer` / `AccountTransferService`

| مدين | دائن |
|------|------|
| حساب القاصة المستقبلة | حساب القاصة المرسلة |

- مسموح **فقط** بين قاصات نقدية (`findCashVaultByLegacyUser` + `isCashBox`)
- يتحقق من الرصيد المتاح من **ledger** قبل التحويل
- ينشئ سجلّين في `transactions`: `transfer_out` / `transfer_in` مربوطين بنفس `journal_entry_id`
- **لا يمسّ** إيراد ولا مصروف (لا تأثير على P&L)
- **ليس** طريقة لتسجيل مصروف

### 3.6 شراء سيارة / تأثير على الصندوق

مسارات شراء/تسعير السيارة في `DashboardController` ما زالت تستدعي أسماء إرثية `increaseWallet` / `decreaseWallet` على:

- العميل (ذمم / إيراد حسب الاتجاه)
- الصندوق `mainBox` / حسابات تقنية أخرى

الترحيل الفعلي يتم عبر `LedgerService::postWalletIncrease` / `postWalletDecrease` حسب `walletPostingKind`:

| الطرف | زيادة (`increase`) | نقصان (`decrease`) |
|-------|---------------------|---------------------|
| عميل (`client`) | Dr AR / Cr Revenue | يعامل كدفعة عميل عبر `postClientPayment` **إذا** نُودي من المسار المناسب؛ عبر `postWalletDecrease` مباشرة على العميل أيضاً يستدعي `postClientPayment` بدون `cashUserId` → يدين نقد النظام الافتراضي `1100`/`1110` |
| صندوق نقد (`cash_box`) | Dr Cash / Cr Revenue | Dr Expense / Cr Cash |
| نظام آخر (`system`) | Dr party `1200-{id}` / Cr Opening `3900` | العكس |

> نقطة تدقيق: بعض تدفقات السيارة قد تُنشئ أكثر من رجل `transactions` (أب + أبناء). القيد المحاسبي يُنشأ عادةً على الرجل الجذر فقط (`parent_id = 0`).

### 3.7 خزينة الشركة وأرباح التجار (مكمّل)

| العملية | القيد |
|---------|--------|
| إيداع خزينة `postTreasuryDeposit` | Dr `1120`/`1130` / Cr `3900` |
| سحب خزينة `postTreasuryWithdraw` | Dr `5100` / Cr Treasury |
| ترحيل ربح تاجر `postTraderProfitAppropriation` | Dr `4100` / Cr `3200` |
| سحب من الأرباح `postProfitWithdraw` | Dr `3200` / Cr Cash `1100`/`1110` |

---

## 4. ما يُمنع (Invariants في الكود)

| ممنوع | السبب / أين يُفرض |
|-------|-------------------|
| تسجيل **مصروف كتحويل** بين قاصات | التحويل = `postAccountTransfer` أصول↔أصول فقط؛ المصروف = `postCashDisbursement` |
| إنشاء قاصة من نوع `expense` / `commission` من الواجهة | `CREATABLE_TYPES` = cash/bank/safe فقط |
| تحديث أرصدة مباشرة على `wallets.balance` | الجدول أُزيل؛ `syncWalletFromLedger` فارغ |
| الاعتماد على جدول Wallet كمصدر حقيقة | الأرصدة من journal فقط |
| قيد غير متوازن | `LedgerService::post` يرمي RuntimeException |
| مبالغ سالبة في أسطر القيد | مرفوضة في `post` |
| تحويل من/إلى نفس الحساب، أو مبلغ ≤ 0 | `AccountTransferService` / `postAccountTransfer` |
| حذف `mainBox` | `VaultService::softDelete` و`SystemReset` يحفظانه |
| حذف قاصة عليها حركات | `softDelete` يرفض إن وُجدت حركات على المستخدم التقني |
| حذف حساب دليل مرتبط بأسطر قيد | FK `restrictOnDelete` على `journal_lines.ledger_account_id` |

---

## 5. التقارير

### من دفتر القيود (محاسبي)

| تقرير | مصدر | ملاحظات |
|-------|------|---------|
| ميزان المراجعة (Trial Balance) | `LedgerController::trialBalance` + أسطر القيود | يعملات `$` / `IQD` |
| دفتر الأستاذ (General Ledger) | أسطر `journal_lines` لحساب معيّن | رصيد افتتاحي + حركات |
| اليومية / القيود | `journal_entries` + lines | `voucher_no`, `source`, `created_by` |
| سلامة القيود | `AccountingIntegrityService::check` | قيود غير متوازنة، فارغة، يتيمة، حسابات مفقودة |

### حركة القاصة (تشغيلي / عرض)

- قائمة حركات القاصة تعتمد على `transactions` المفلترة بـ `vault_id` (ومع مرآة اختيارية `journal_entry_id`).
- رصيد القاصة المعروض: `VaultService::cashBalance()` ← من حساب الـ COA المرتبط (أو `1100`/`1110` لـ mainBox).
- **لا تستخدم** مجموع `transactions.amount` كمصدر حقيقة للمحاسبة الرسمية؛ هو عرض تشغيلي قد يختلف تاريخياً عن القيود إن وُجدت بيانات قديمة غير مرحّلة.

---

## 6. تصفير النظام — `SystemResetService::wipe`

**مسموح للمدير فقط** (`type_id === 1`).

### ما يُحذف / يُعطَّل (غالباً Soft Delete حيث يتوفر)

- مصاريف السيارات، السيارات
- حركات `transactions` المرتبطة بالمالك
- `journal_entries` (soft) ثم **حذف صريح** لـ `journal_lines` (لا SoftDeletes على الأسطر — لفك قيود FK قبل مسح الحسابات)
- أرباح التجار، خزينة الشركة، التحويلات
- التجار/العملاء (مع استثناءات الأدمن وصناديق النظام الأساسية)
- قاصات غير أساسية (عمولة/مصروف تاريخية …) — يُبقى **mainBox فقط**
- مسح كل `ledger_accounts` للمالك (hard delete بعد فك الروابط)
- إن وُجد جدول wallets متبقٍ: تصفير أرصدة (مسار دفاع قديم)

### ما يُعاد بعد المسح

1. `SystemWalletService::ensureForOwner` — ضمان المستخدم التقني mainBox  
2. `LedgerService::ensureSystemAccounts` — حسابات النظام الأساسية أعلاه  
3. `VaultService::ensureMainBoxVault` — ربط قاصة mainBox بحساب النقد  

**لا يُعاد إنشاء** قاصات اختيارية/محذوفة soft — السياسة في `SystemWalletService`: soft-deleted لا تُعاد تلقائياً.

---

## 7. ملفات محورية للمراجع

| ملف | المسؤولية |
|-----|-----------|
| `app/Services/LedgerService.php` | ترحيل القيود، رموز الحسابات، `post*`, التوازن، إبطال القيد |
| `app/Services/VaultService.php` | إنشاء/تحديث القاصات، `11V-*`, mainBox، قاصة الاستلام، أرصدة نقد |
| `app/Services/AccountTransferService.php` | تحويل نقدي قاصة↔قاصة + سجلات transactions |
| `app/Services/SystemResetService.php` | تصفير تشغيلي |
| `app/Services/SystemWalletService.php` | مستخدمون تقنيون للصناديق (إرث)، mainBox |
| `app/Services/AccountingIntegrityService.php` | فحص توازن القيود |
| `app/Http/Controllers/AccountingController.php` | مسارات الدفع/القبض/السحب؛ `increaseWallet` / `decreaseWallet` / `debtWallet` (أسماء إرثية) |
| `app/Http/Controllers/LedgerController.php` | COA، صرف مصروف من حساب مصروف، ميزان/أستاذ |
| `app/Models/Vault.php` | `CASH_TYPES`, `isCashBox` |
| `app/Models/LedgerAccount.php` | حساب الرصيد من الأسطر |
| `app/Models/JournalEntry.php` | SoftDeletes |
| Migrations | `2026_07_22_190000_create_double_entry_ledger_tables`, `2026_07_28_200000_create_vaults_table`, `2026_07_29_*` (receipts vault، vault_id على transactions، drop wallets) |

---

## 8. إرث ومتبقيات يجب أن يعرفها المدقق (Honest legacy)

1. **أسماء الدوال `increaseWallet` / `decreaseWallet` / `debtWallet` / `postWallet*`**  
   لا تعني وجود Wallet نشط — الترحيل يذهب إلى الـ ledger؛ التسمية تاريخية.

2. **`source = 'wallet'` على بعض القيود**  
   تسمية مصدر قديمة لقيود دفعات العملاء؛ لا تدل على جدول wallets.

3. **`transactions` مرآة تشغيلية**  
   مع `vault_id` + `journal_entry_id`. أرصدة العرض من COA. قد توجد حركات قديمة بلا قيد.

4. **تعليق في `SystemWalletService` ما زال يذكر Wallet**  
   التعليق أعلى الملف قد يكون قديماً جزئياً؛ السلوك الفعلي: ledger + legacy User لـ mainBox.

5. **أرجل مرآة اختيارية** (مثل `main@account.com` في `addPaymentCar`)  
   إن وُجد الحساب تُنشأ حركة فرعية بلا قيد؛ غيابه لا يفشل الدفعة.

6. **أمانات `inUserAmanah` / `outUserAmanah`**  
   مسارات واجهة خاصة؛ راجع مساراتها في `AccountingController` عند تدقيق تلك الحركات بشكل منفصل.

7. **فحص wallets في IntegrityService**  
   معطّل افتراضياً (`$checkWallets = false`) لأن الجدول deprecated.

---

## 9. نقاط تدقيق للمراجع AI — Checklist

### أ) سلامة القيود

- [ ] لكل `journal_entry` نشط: Σ debit = Σ credit **لكل عملة** على حدة
- [ ] لا قيود بلا أسطر؛ لا `journal_lines` يتيمة
- [ ] لا أسطر تشير إلى `ledger_account_id` محذوف
- [ ] القيود المُبطَلة (`SoftDeletes`) لا تدخل في `balance()`
- [ ] تشغيل `AccountingIntegrityService::check($ownerId)` أو أمر `AccountingIntegrityCommand`

### ب) فصل AR عن النقد

- [ ] دفعات العملاء تقيد: **مدين نقد قاصة الاستلام** + **دائن `1200-{clientId}`** (وربما مدين `5100` للخصم)
- [ ] لا تُخلط ذمم العميل مع رصيد قاصة نقدية في نفس حساب COA
- [ ] `default_receipts_vault_id` يشير دائماً إلى قاصة `isCashBox()` نشطة
- [ ] `default_purchases_vault_id` يشير دائماً إلى قاصة `isCashBox()` نشطة (منفصلة عن قاصة الاستلام)
- [ ] mainBox يرحّل عبر `1100`/`1110` حسب العملة؛ القاصات الأخرى عبر `11V-{id}` أو `ledger_account_id`

### ج) قواعد التحويل والمصروف

- [ ] التحويلات فقط بين cash/bank/safe (+ mainBox)، وإلا رفض
- [ ] لا مصروف عبر `AccountTransferService`
- [ ] صرف المصروف دائماً: مدين حساب `type=expense` / دائن نقد
- [ ] لا قاصات جديدة من نوع expense

### د) العملات والحذف

- [ ] دعم `$` و `IQD` متسق على أسطر القيد ومع رصيد الحساب
- [ ] حذف حركة مالية يُبطل القيد المرتبط (`voidJournalEntry`) عند مسارات الحذف الرسمية — راجع `delTransactions`
- [ ] Soft delete للقيود والقاصات والحركات حيث طُبّق؛ hard delete لأسطر القيد فقط عند التصفير لفك القيود

### هـ) بعد التصفير

- [ ] توجد حسابات النظام الثمانية الافتراضية لكل owner
- [ ] توجد قاصة/مستخدم mainBox فقط كصندوق أساسي
- [ ] أرصدة كل حسابات COA = 0 (لا أسطر نشطة)

### و) عدم اختراع أرصدة

- [ ] أي شاشة تعرض «رصيد» للقاصة أو للعميل يجب أن تقرأ من `LedgerAccount::balance` / SQL الـ journal (مثل `clientBalanceSqlSubquery`) وليس من حقل wallet

---

## 10. خريطة سريعة: من الواجهة إلى القيد

```text
دفعة سيارة / زبون
  → AccountingController::increaseWallet(receiptsVaultUser, morph=client)
  → LedgerService::postClientPayment
  → Dr Cash (1100|1110|11V-*) [/ Dr 5100 إن خصم] / Cr 1200-{client}

صرف مصروف من دليل الحسابات
  → LedgerController (expense disbursement)
  → LedgerService::postCashDisbursement
  → Dr expense COA / Cr Cash vault

وصل سحب مباشر (واجهة صندوق)
  → debtWallet على receipts/mainBox
  → postCashDisbursement (افتراضي 5100)

تحويل بين قاصات
  → AccountTransferService::transfer
  → postAccountTransfer
  → Dr vault_to / Cr vault_from
  + transactions transfer_out/in

رصيد معروض للقاصة
  → VaultService::cashBalance → ledger only
```

---

## 11. ملخص جملة واحدة للمراجع

**مصدر الحقيقة = قيود مزدوجة على `ledger_accounts`؛ القاصات صناديق نقد مربوطة بـ COA (`1100`/`1110` أو `11V-*`)؛ ذمم العملاء `1200-*`؛ المصروف من حساب مصروف وليس من التحويل؛ جدول Wallet ملغى والباقي أسماء/مرآة تشغيلية في `transactions`.**

---

*نهاية الوثيقة. عند اختلاف السلوك بين هذه الوثيقة والكود، الكود هو المرجع — حدّث الوثيقة.*

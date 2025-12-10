-- SQL Statement لإضافة عمود commission_s في جدول car
-- يُستخدم هذا العمود لحفظ قيمة عمولة المبيعات (مختلفة عن commission المستخدمة في المشتريات)

ALTER TABLE `car` 
ADD COLUMN `commission_s` INT(11) DEFAULT 0 NULL AFTER `expenses_s`;

-- إذا كنت تريد نسخ القيم الموجودة من commission إلى commission_s للبيانات الحالية (اختياري):
-- UPDATE `car` SET `commission_s` = `commission` WHERE `commission_s` = 0 AND `commission` IS NOT NULL;


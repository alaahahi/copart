ALTER TABLE `car`
  ADD `erbil_clearance` int NOT NULL DEFAULT 0 AFTER `expenses`,
  ADD `erbil_transfer` int NOT NULL DEFAULT 0 AFTER `erbil_clearance`,
  ADD `erbil_border_repair` int NOT NULL DEFAULT 0 AFTER `erbil_transfer`,
  ADD `erbil_customs` int NOT NULL DEFAULT 0 AFTER `erbil_border_repair`,
  ADD `erbil_clearance_s` int NOT NULL DEFAULT 0 AFTER `expenses_s`,
  ADD `erbil_transfer_s` int NOT NULL DEFAULT 0 AFTER `erbil_clearance_s`,
  ADD `erbil_border_repair_s` int NOT NULL DEFAULT 0 AFTER `erbil_transfer_s`,
  ADD `erbil_customs_s` int NOT NULL DEFAULT 0 AFTER `erbil_border_repair_s`;
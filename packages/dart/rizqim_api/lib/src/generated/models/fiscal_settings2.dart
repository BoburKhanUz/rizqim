// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'charges2.dart';
import 'sandbox2.dart';
import 'combo_fiscal_mode.dart';

part 'fiscal_settings2.g.dart';

@JsonSerializable()
class FiscalSettings2 {
  const FiscalSettings2({
    required this.enabled,
    required this.driver,
    required this.activeDriver,
    required this.smartKassaDriver,
    required this.charges,
    required this.sandbox,
    required this.depositRefundReceipt,
    required this.retainedDepositReceipt,
    required this.comboFiscalMode,
  });

  factory FiscalSettings2.fromJson(Map<String, Object?> json) =>
      _$FiscalSettings2FromJson(json);

  final bool enabled;
  final String driver;
  @JsonKey(name: 'active_driver')
  final String activeDriver;
  @JsonKey(name: 'smart_kassa_driver')
  final String smartKassaDriver;
  final Charges2 charges;
  final Sandbox2 sandbox;
  @JsonKey(name: 'deposit_refund_receipt')
  final bool? depositRefundReceipt;
  @JsonKey(name: 'retained_deposit_receipt')
  final bool? retainedDepositReceipt;
  @JsonKey(name: 'combo_fiscal_mode')
  final ComboFiscalMode comboFiscalMode;

  Map<String, Object?> toJson() => _$FiscalSettings2ToJson(this);
}

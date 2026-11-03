// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'charges3.dart';
import 'sandbox3.dart';
import 'combo_fiscal_mode.dart';

part 'fiscal_settings3.g.dart';

@JsonSerializable()
class FiscalSettings3 {
  const FiscalSettings3({
    this.enabled,
    this.driver,
    this.smartKassaDriver,
    this.charges,
    this.sandbox,
    this.depositRefundReceipt,
    this.retainedDepositReceipt,
    this.comboFiscalMode,
  });

  factory FiscalSettings3.fromJson(Map<String, Object?> json) =>
      _$FiscalSettings3FromJson(json);

  final bool? enabled;
  final String? driver;
  @JsonKey(name: 'smart_kassa_driver')
  final String? smartKassaDriver;
  final Charges3? charges;
  final Sandbox3? sandbox;
  @JsonKey(name: 'deposit_refund_receipt')
  final bool? depositRefundReceipt;
  @JsonKey(name: 'retained_deposit_receipt')
  final bool? retainedDepositReceipt;
  @JsonKey(name: 'combo_fiscal_mode')
  final ComboFiscalMode? comboFiscalMode;

  Map<String, Object?> toJson() => _$FiscalSettings3ToJson(this);
}

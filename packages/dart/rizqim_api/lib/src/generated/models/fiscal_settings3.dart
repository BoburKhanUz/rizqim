// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'charges3.dart';
import 'sandbox3.dart';

part 'fiscal_settings3.g.dart';

@JsonSerializable()
class FiscalSettings3 {
  const FiscalSettings3({
    this.enabled,
    this.driver,
    this.charges,
    this.sandbox,
    this.depositRefundReceipt,
    this.retainedDepositReceipt,
  });

  factory FiscalSettings3.fromJson(Map<String, Object?> json) =>
      _$FiscalSettings3FromJson(json);

  final bool? enabled;
  final String? driver;
  final Charges3? charges;
  final Sandbox3? sandbox;
  @JsonKey(name: 'deposit_refund_receipt')
  final bool? depositRefundReceipt;
  @JsonKey(name: 'retained_deposit_receipt')
  final bool? retainedDepositReceipt;

  Map<String, Object?> toJson() => _$FiscalSettings3ToJson(this);
}

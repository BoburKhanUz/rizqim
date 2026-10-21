// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal_settings4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

FiscalSettings4 _$FiscalSettings4FromJson(Map<String, dynamic> json) =>
    FiscalSettings4(
      enabled: json['enabled'] as bool,
      driver: json['driver'] as String,
      activeDriver: json['active_driver'] as String,
      charges: (json['charges'] as Map<String, dynamic>).map(
        (k, e) => MapEntry(k, Charges4.fromJson(e as Map<String, dynamic>)),
      ),
      sandbox: Sandbox4.fromJson(json['sandbox'] as Map<String, dynamic>),
      depositRefundReceipt: json['deposit_refund_receipt'] as bool?,
      retainedDepositReceipt: json['retained_deposit_receipt'] as bool?,
    );

Map<String, dynamic> _$FiscalSettings4ToJson(FiscalSettings4 instance) =>
    <String, dynamic>{
      'enabled': instance.enabled,
      'driver': instance.driver,
      'active_driver': instance.activeDriver,
      'charges': instance.charges,
      'sandbox': instance.sandbox,
      'deposit_refund_receipt': instance.depositRefundReceipt,
      'retained_deposit_receipt': instance.retainedDepositReceipt,
    };

// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal_settings3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

FiscalSettings3 _$FiscalSettings3FromJson(Map<String, dynamic> json) =>
    FiscalSettings3(
      enabled: json['enabled'] as bool?,
      driver: json['driver'] as String?,
      smartKassaDriver: json['smart_kassa_driver'] as String?,
      charges: json['charges'] == null
          ? null
          : Charges3.fromJson(json['charges'] as Map<String, dynamic>),
      sandbox: json['sandbox'] == null
          ? null
          : Sandbox3.fromJson(json['sandbox'] as Map<String, dynamic>),
      depositRefundReceipt: json['deposit_refund_receipt'] as bool?,
      retainedDepositReceipt: json['retained_deposit_receipt'] as bool?,
    );

Map<String, dynamic> _$FiscalSettings3ToJson(FiscalSettings3 instance) =>
    <String, dynamic>{
      'enabled': instance.enabled,
      'driver': instance.driver,
      'smart_kassa_driver': instance.smartKassaDriver,
      'charges': instance.charges,
      'sandbox': instance.sandbox,
      'deposit_refund_receipt': instance.depositRefundReceipt,
      'retained_deposit_receipt': instance.retainedDepositReceipt,
    };

// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal_settings5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

FiscalSettings5 _$FiscalSettings5FromJson(Map<String, dynamic> json) =>
    FiscalSettings5(
      enabled: json['enabled'] as bool?,
      driver: json['driver'] as String?,
      charges: (json['charges'] as Map<String, dynamic>?)?.map(
        (k, e) => MapEntry(k, Charges5.fromJson(e as Map<String, dynamic>)),
      ),
      sandbox: json['sandbox'] == null
          ? null
          : Sandbox5.fromJson(json['sandbox'] as Map<String, dynamic>),
      depositRefundReceipt: json['deposit_refund_receipt'] as bool?,
      retainedDepositReceipt: json['retained_deposit_receipt'] as bool?,
    );

Map<String, dynamic> _$FiscalSettings5ToJson(FiscalSettings5 instance) =>
    <String, dynamic>{
      'enabled': instance.enabled,
      'driver': instance.driver,
      'charges': instance.charges,
      'sandbox': instance.sandbox,
      'deposit_refund_receipt': instance.depositRefundReceipt,
      'retained_deposit_receipt': instance.retainedDepositReceipt,
    };

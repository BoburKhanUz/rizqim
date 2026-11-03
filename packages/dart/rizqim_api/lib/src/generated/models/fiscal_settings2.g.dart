// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal_settings2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

FiscalSettings2 _$FiscalSettings2FromJson(Map<String, dynamic> json) =>
    FiscalSettings2(
      enabled: json['enabled'] as bool,
      driver: json['driver'] as String,
      activeDriver: json['active_driver'] as String,
      smartKassaDriver: json['smart_kassa_driver'] as String,
      charges: Charges2.fromJson(json['charges'] as Map<String, dynamic>),
      sandbox: Sandbox2.fromJson(json['sandbox'] as Map<String, dynamic>),
      depositRefundReceipt: json['deposit_refund_receipt'] as bool?,
      retainedDepositReceipt: json['retained_deposit_receipt'] as bool?,
      comboFiscalMode: ComboFiscalMode.fromJson(
        json['combo_fiscal_mode'] as String,
      ),
    );

Map<String, dynamic> _$FiscalSettings2ToJson(FiscalSettings2 instance) =>
    <String, dynamic>{
      'enabled': instance.enabled,
      'driver': instance.driver,
      'active_driver': instance.activeDriver,
      'smart_kassa_driver': instance.smartKassaDriver,
      'charges': instance.charges,
      'sandbox': instance.sandbox,
      'deposit_refund_receipt': instance.depositRefundReceipt,
      'retained_deposit_receipt': instance.retainedDepositReceipt,
      'combo_fiscal_mode': instance.comboFiscalMode,
    };

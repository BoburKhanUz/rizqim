// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payment_settings.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PaymentSettings _$PaymentSettingsFromJson(Map<String, dynamic> json) =>
    PaymentSettings(
      merchantId: json['merchant_id'] as String?,
      serviceId: json['service_id'] as String?,
      secretKey: json['secret_key'] as String?,
    );

Map<String, dynamic> _$PaymentSettingsToJson(PaymentSettings instance) =>
    <String, dynamic>{
      'merchant_id': instance.merchantId,
      'service_id': instance.serviceId,
      'secret_key': instance.secretKey,
    };

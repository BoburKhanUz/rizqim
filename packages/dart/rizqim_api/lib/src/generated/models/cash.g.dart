// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cash.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Cash _$CashFromJson(Map<String, dynamic> json) => Cash(
  enabled: json['enabled'] as bool?,
  merchantId: json['merchant_id'] as String?,
  serviceId: json['service_id'] as String?,
  secretKey: json['secret_key'] as String?,
);

Map<String, dynamic> _$CashToJson(Cash instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'merchant_id': instance.merchantId,
  'service_id': instance.serviceId,
  'secret_key': instance.secretKey,
};

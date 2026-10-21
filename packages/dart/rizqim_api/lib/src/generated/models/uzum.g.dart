// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'uzum.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Uzum _$UzumFromJson(Map<String, dynamic> json) => Uzum(
  enabled: json['enabled'] as bool?,
  merchantId: json['merchant_id'] as String?,
  serviceId: json['service_id'] as String?,
  secretKey: json['secret_key'] as String?,
);

Map<String, dynamic> _$UzumToJson(Uzum instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'merchant_id': instance.merchantId,
  'service_id': instance.serviceId,
  'secret_key': instance.secretKey,
};

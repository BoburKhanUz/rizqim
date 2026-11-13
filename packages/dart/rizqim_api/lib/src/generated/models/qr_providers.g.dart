// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'qr_providers.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

QrProviders _$QrProvidersFromJson(Map<String, dynamic> json) => QrProviders(
  provider: json['provider'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$QrProvidersToJson(QrProviders instance) =>
    <String, dynamic>{
      'provider': instance.provider,
      'count': instance.count,
      'amount': instance.amount,
    };

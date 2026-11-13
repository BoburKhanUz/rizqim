// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'qr_providers3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

QrProviders3 _$QrProviders3FromJson(Map<String, dynamic> json) => QrProviders3(
  provider: json['provider'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$QrProviders3ToJson(QrProviders3 instance) =>
    <String, dynamic>{
      'provider': instance.provider,
      'count': instance.count,
      'amount': instance.amount,
    };

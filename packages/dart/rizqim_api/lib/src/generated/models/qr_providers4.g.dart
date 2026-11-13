// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'qr_providers4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

QrProviders4 _$QrProviders4FromJson(Map<String, dynamic> json) => QrProviders4(
  provider: json['provider'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$QrProviders4ToJson(QrProviders4 instance) =>
    <String, dynamic>{
      'provider': instance.provider,
      'count': instance.count,
      'amount': instance.amount,
    };

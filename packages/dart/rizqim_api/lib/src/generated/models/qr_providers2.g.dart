// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'qr_providers2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

QrProviders2 _$QrProviders2FromJson(Map<String, dynamic> json) => QrProviders2(
  provider: json['provider'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$QrProviders2ToJson(QrProviders2 instance) =>
    <String, dynamic>{
      'provider': instance.provider,
      'count': instance.count,
      'amount': instance.amount,
    };

// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'qr_providers5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

QrProviders5 _$QrProviders5FromJson(Map<String, dynamic> json) => QrProviders5(
  provider: json['provider'] as String,
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$QrProviders5ToJson(QrProviders5 instance) =>
    <String, dynamic>{
      'provider': instance.provider,
      'count': instance.count,
      'amount': instance.amount,
    };

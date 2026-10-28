// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data61.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data61 _$Data61FromJson(Map<String, dynamic> json) => Data61(
  topupUuid: json['topup_uuid'] as String,
  paymentUrl: json['payment_url'] as String,
);

Map<String, dynamic> _$Data61ToJson(Data61 instance) => <String, dynamic>{
  'topup_uuid': instance.topupUuid,
  'payment_url': instance.paymentUrl,
};

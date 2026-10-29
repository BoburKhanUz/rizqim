// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payment5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payment5 _$Payment5FromJson(Map<String, dynamic> json) => Payment5(
  method: json['method'] as String?,
  tendered: json['tendered'] as String?,
  change: json['change'] as String?,
  qr: json['qr'] == null
      ? null
      : Qr2.fromJson(json['qr'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Payment5ToJson(Payment5 instance) => <String, dynamic>{
  'method': instance.method,
  'tendered': instance.tendered,
  'change': instance.change,
  'qr': instance.qr,
};

// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payment4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payment4 _$Payment4FromJson(Map<String, dynamic> json) => Payment4(
  method: json['method'] as String?,
  tendered: json['tendered'] as String?,
  change: json['change'] as String?,
  qr: json['qr'] == null
      ? null
      : Qr.fromJson(json['qr'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Payment4ToJson(Payment4 instance) => <String, dynamic>{
  'method': instance.method,
  'tendered': instance.tendered,
  'change': instance.change,
  'qr': instance.qr,
};

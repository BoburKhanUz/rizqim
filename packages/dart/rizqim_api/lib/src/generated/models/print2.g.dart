// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'print2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Print2 _$Print2FromJson(Map<String, dynamic> json) => Print2(
  store: json['store'] as String?,
  code: json['code'] as String,
  qr: json['qr'] as String,
  value: json['value'] as String,
  validFrom: json['valid_from'] as String,
  validUntil: json['valid_until'] as String?,
);

Map<String, dynamic> _$Print2ToJson(Print2 instance) => <String, dynamic>{
  'store': instance.store,
  'code': instance.code,
  'qr': instance.qr,
  'value': instance.value,
  'valid_from': instance.validFrom,
  'valid_until': instance.validUntil,
};

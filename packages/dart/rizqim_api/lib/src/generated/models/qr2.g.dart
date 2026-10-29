// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'qr2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Qr2 _$Qr2FromJson(Map<String, dynamic> json) => Qr2(
  payload: json['payload'] as String,
  label: json['label'] as String?,
  provider: json['provider'] as String?,
);

Map<String, dynamic> _$Qr2ToJson(Qr2 instance) => <String, dynamic>{
  'payload': instance.payload,
  'label': instance.label,
  'provider': instance.provider,
};

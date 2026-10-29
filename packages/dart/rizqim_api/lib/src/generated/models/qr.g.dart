// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'qr.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Qr _$QrFromJson(Map<String, dynamic> json) => Qr(
  payload: json['payload'] as String?,
  label: json['label'] as String?,
  provider: json['provider'] as String?,
);

Map<String, dynamic> _$QrToJson(Qr instance) => <String, dynamic>{
  'payload': instance.payload,
  'label': instance.label,
  'provider': instance.provider,
};

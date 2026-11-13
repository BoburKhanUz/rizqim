// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'merchants2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Merchants2 _$Merchants2FromJson(Map<String, dynamic> json) => Merchants2(
  provider: json['provider'] as String,
  label: json['label'] as String,
  configured: json['configured'] as bool,
  driver: json['driver'] as String,
);

Map<String, dynamic> _$Merchants2ToJson(Merchants2 instance) =>
    <String, dynamic>{
      'provider': instance.provider,
      'label': instance.label,
      'configured': instance.configured,
      'driver': instance.driver,
    };

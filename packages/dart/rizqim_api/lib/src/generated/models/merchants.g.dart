// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'merchants.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Merchants _$MerchantsFromJson(Map<String, dynamic> json) => Merchants(
  provider: json['provider'] as String,
  label: json['label'] as String,
  configured: json['configured'] as bool,
  driver: json['driver'] as String,
);

Map<String, dynamic> _$MerchantsToJson(Merchants instance) => <String, dynamic>{
  'provider': instance.provider,
  'label': instance.label,
  'configured': instance.configured,
  'driver': instance.driver,
};

// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'options5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Options5 _$Options5FromJson(Map<String, dynamic> json) => Options5(
  providers: (json['providers'] as List<dynamic>)
      .map((e) => Providers2.fromJson(e as Map<String, dynamic>))
      .toList(),
  expiryMin: (json['expiry_min'] as num).toInt(),
  expiryMax: (json['expiry_max'] as num).toInt(),
);

Map<String, dynamic> _$Options5ToJson(Options5 instance) => <String, dynamic>{
  'providers': instance.providers,
  'expiry_min': instance.expiryMin,
  'expiry_max': instance.expiryMax,
};

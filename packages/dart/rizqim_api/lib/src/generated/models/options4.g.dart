// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'options4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Options4 _$Options4FromJson(Map<String, dynamic> json) => Options4(
  providers: (json['providers'] as List<dynamic>)
      .map((e) => Providers.fromJson(e as Map<String, dynamic>))
      .toList(),
  expiryMin: (json['expiry_min'] as num).toInt(),
  expiryMax: (json['expiry_max'] as num).toInt(),
);

Map<String, dynamic> _$Options4ToJson(Options4 instance) => <String, dynamic>{
  'providers': instance.providers,
  'expiry_min': instance.expiryMin,
  'expiry_max': instance.expiryMax,
};

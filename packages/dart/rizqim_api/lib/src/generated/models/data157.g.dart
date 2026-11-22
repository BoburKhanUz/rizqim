// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data157.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data157 _$Data157FromJson(Map<String, dynamic> json) => Data157(
  settings: Settings.fromJson(json['settings'] as Map<String, dynamic>),
  merchants: (json['merchants'] as List<dynamic>)
      .map((e) => Merchants.fromJson(e as Map<String, dynamic>))
      .toList(),
  options: Options4.fromJson(json['options'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data157ToJson(Data157 instance) => <String, dynamic>{
  'settings': instance.settings,
  'merchants': instance.merchants,
  'options': instance.options,
};

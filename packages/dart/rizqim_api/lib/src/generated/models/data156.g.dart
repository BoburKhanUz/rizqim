// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data156.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data156 _$Data156FromJson(Map<String, dynamic> json) => Data156(
  settings: Settings2.fromJson(json['settings'] as Map<String, dynamic>),
  merchants: (json['merchants'] as List<dynamic>)
      .map((e) => Merchants2.fromJson(e as Map<String, dynamic>))
      .toList(),
  options: Options5.fromJson(json['options'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data156ToJson(Data156 instance) => <String, dynamic>{
  'settings': instance.settings,
  'merchants': instance.merchants,
  'options': instance.options,
};

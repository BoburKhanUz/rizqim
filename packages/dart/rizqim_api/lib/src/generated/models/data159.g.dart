// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data159.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data159 _$Data159FromJson(Map<String, dynamic> json) => Data159(
  settings: Settings2.fromJson(json['settings'] as Map<String, dynamic>),
  merchants: (json['merchants'] as List<dynamic>)
      .map((e) => Merchants2.fromJson(e as Map<String, dynamic>))
      .toList(),
  options: Options5.fromJson(json['options'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data159ToJson(Data159 instance) => <String, dynamic>{
  'settings': instance.settings,
  'merchants': instance.merchants,
  'options': instance.options,
};

// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data158.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data158 _$Data158FromJson(Map<String, dynamic> json) => Data158(
  settings: Settings.fromJson(json['settings'] as Map<String, dynamic>),
  merchants: (json['merchants'] as List<dynamic>)
      .map((e) => Merchants.fromJson(e as Map<String, dynamic>))
      .toList(),
  options: Options4.fromJson(json['options'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data158ToJson(Data158 instance) => <String, dynamic>{
  'settings': instance.settings,
  'merchants': instance.merchants,
  'options': instance.options,
};

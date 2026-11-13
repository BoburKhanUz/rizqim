// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data132.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data132 _$Data132FromJson(Map<String, dynamic> json) => Data132(
  settings: Settings.fromJson(json['settings'] as Map<String, dynamic>),
  merchants: (json['merchants'] as List<dynamic>)
      .map((e) => Merchants.fromJson(e as Map<String, dynamic>))
      .toList(),
  options: Options4.fromJson(json['options'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data132ToJson(Data132 instance) => <String, dynamic>{
  'settings': instance.settings,
  'merchants': instance.merchants,
  'options': instance.options,
};

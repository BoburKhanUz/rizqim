// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data59.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data59 _$Data59FromJson(Map<String, dynamic> json) => Data59(
  brands: (json['brands'] as List<dynamic>).map((e) => e as String).toList(),
  channelsAvailable: json['channels_available'] as List<dynamic>,
);

Map<String, dynamic> _$Data59ToJson(Data59 instance) => <String, dynamic>{
  'brands': instance.brands,
  'channels_available': instance.channelsAvailable,
};

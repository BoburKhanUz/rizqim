// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data60.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data60 _$Data60FromJson(Map<String, dynamic> json) => Data60(
  brands: (json['brands'] as List<dynamic>).map((e) => e as String).toList(),
  channelsAvailable: json['channels_available'] as List<dynamic>,
);

Map<String, dynamic> _$Data60ToJson(Data60 instance) => <String, dynamic>{
  'brands': instance.brands,
  'channels_available': instance.channelsAvailable,
};

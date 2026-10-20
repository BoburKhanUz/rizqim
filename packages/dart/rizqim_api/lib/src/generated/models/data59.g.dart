// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data59.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data59 _$Data59FromJson(Map<String, dynamic> json) => Data59(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Region.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => District.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data59ToJson(Data59 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

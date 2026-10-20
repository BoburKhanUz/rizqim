// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data60.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data60 _$Data60FromJson(Map<String, dynamic> json) => Data60(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Region.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => District.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data60ToJson(Data60 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

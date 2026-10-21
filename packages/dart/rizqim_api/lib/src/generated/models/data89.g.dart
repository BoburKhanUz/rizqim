// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data89.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data89 _$Data89FromJson(Map<String, dynamic> json) => Data89(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Region.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => District.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data89ToJson(Data89 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

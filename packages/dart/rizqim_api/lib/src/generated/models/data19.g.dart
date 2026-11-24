// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data19.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data19 _$Data19FromJson(Map<String, dynamic> json) => Data19(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Regions.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => Districts.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data19ToJson(Data19 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

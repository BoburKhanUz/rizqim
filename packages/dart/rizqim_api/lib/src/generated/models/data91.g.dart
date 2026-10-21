// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data91.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data91 _$Data91FromJson(Map<String, dynamic> json) => Data91(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Regions2.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => Districts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data91ToJson(Data91 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

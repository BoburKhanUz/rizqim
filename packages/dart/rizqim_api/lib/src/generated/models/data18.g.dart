// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data18.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data18 _$Data18FromJson(Map<String, dynamic> json) => Data18(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Regions.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => Districts.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data18ToJson(Data18 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data39.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data39 _$Data39FromJson(Map<String, dynamic> json) => Data39(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Regions.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => Districts.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data39ToJson(Data39 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

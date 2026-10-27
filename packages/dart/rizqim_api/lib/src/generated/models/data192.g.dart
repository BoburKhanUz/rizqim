// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data192.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data192 _$Data192FromJson(Map<String, dynamic> json) => Data192(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Regions2.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => Districts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data192ToJson(Data192 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

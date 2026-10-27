// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data213.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data213 _$Data213FromJson(Map<String, dynamic> json) => Data213(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Regions2.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => Districts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data213ToJson(Data213 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

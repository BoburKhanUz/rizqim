// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data185.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data185 _$Data185FromJson(Map<String, dynamic> json) => Data185(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Regions2.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => Districts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data185ToJson(Data185 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

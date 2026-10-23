// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data189.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data189 _$Data189FromJson(Map<String, dynamic> json) => Data189(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Regions2.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => Districts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data189ToJson(Data189 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

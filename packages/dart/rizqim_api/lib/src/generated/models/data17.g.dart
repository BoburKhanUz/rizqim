// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data17.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data17 _$Data17FromJson(Map<String, dynamic> json) => Data17(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Regions.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => Districts.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data17ToJson(Data17 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

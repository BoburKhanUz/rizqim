// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data177.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data177 _$Data177FromJson(Map<String, dynamic> json) => Data177(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Regions2.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => Districts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data177ToJson(Data177 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

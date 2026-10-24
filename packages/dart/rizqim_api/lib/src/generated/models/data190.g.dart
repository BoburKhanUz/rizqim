// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data190.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data190 _$Data190FromJson(Map<String, dynamic> json) => Data190(
  regions: (json['regions'] as List<dynamic>)
      .map((e) => Regions2.fromJson(e as Map<String, dynamic>))
      .toList(),
  districts: (json['districts'] as List<dynamic>)
      .map((e) => Districts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data190ToJson(Data190 instance) => <String, dynamic>{
  'regions': instance.regions,
  'districts': instance.districts,
};

// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data247.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data247 _$Data247FromJson(Map<String, dynamic> json) => Data247(
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings21.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data247ToJson(Data247 instance) => <String, dynamic>{
  'warnings': instance.warnings,
};

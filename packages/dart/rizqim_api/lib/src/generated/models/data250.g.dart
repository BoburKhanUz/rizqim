// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data250.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data250 _$Data250FromJson(Map<String, dynamic> json) => Data250(
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings22.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data250ToJson(Data250 instance) => <String, dynamic>{
  'warnings': instance.warnings,
};

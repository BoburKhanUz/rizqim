// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data248.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data248 _$Data248FromJson(Map<String, dynamic> json) => Data248(
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings22.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data248ToJson(Data248 instance) => <String, dynamic>{
  'warnings': instance.warnings,
};

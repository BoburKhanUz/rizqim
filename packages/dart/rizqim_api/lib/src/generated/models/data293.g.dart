// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data293.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data293 _$Data293FromJson(Map<String, dynamic> json) => Data293(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta3.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data293ToJson(Data293 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

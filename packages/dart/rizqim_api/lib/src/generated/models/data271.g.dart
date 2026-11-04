// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data271.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data271 _$Data271FromJson(Map<String, dynamic> json) => Data271(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data271ToJson(Data271 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data273.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data273 _$Data273FromJson(Map<String, dynamic> json) => Data273(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data273ToJson(Data273 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

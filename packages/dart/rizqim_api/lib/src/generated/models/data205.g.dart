// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data205.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data205 _$Data205FromJson(Map<String, dynamic> json) => Data205(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data205ToJson(Data205 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

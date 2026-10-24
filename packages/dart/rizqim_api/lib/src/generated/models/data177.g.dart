// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data177.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data177 _$Data177FromJson(Map<String, dynamic> json) => Data177(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data177ToJson(Data177 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

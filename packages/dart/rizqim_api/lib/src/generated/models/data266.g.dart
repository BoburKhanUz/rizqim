// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data266.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data266 _$Data266FromJson(Map<String, dynamic> json) => Data266(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data266ToJson(Data266 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

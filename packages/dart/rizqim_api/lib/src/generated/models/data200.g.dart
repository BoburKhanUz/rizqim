// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data200.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data200 _$Data200FromJson(Map<String, dynamic> json) => Data200(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data200ToJson(Data200 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

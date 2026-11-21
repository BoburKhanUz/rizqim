// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data349.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data349 _$Data349FromJson(Map<String, dynamic> json) => Data349(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta3.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data349ToJson(Data349 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

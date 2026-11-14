// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data345.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data345 _$Data345FromJson(Map<String, dynamic> json) => Data345(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta3.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data345ToJson(Data345 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

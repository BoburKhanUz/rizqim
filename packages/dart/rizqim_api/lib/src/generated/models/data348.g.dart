// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data348.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data348 _$Data348FromJson(Map<String, dynamic> json) => Data348(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta3.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data348ToJson(Data348 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

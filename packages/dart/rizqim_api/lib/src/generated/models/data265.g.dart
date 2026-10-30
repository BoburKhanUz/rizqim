// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data265.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data265 _$Data265FromJson(Map<String, dynamic> json) => Data265(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data265ToJson(Data265 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

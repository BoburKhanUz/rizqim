// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data245.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data245 _$Data245FromJson(Map<String, dynamic> json) => Data245(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data245ToJson(Data245 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

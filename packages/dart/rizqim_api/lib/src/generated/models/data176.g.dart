// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data176.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data176 _$Data176FromJson(Map<String, dynamic> json) => Data176(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data176ToJson(Data176 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

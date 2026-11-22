// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data352.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data352 _$Data352FromJson(Map<String, dynamic> json) => Data352(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta3.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data352ToJson(Data352 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

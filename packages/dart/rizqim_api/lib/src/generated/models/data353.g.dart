// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data353.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data353 _$Data353FromJson(Map<String, dynamic> json) => Data353(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta3.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data353ToJson(Data353 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

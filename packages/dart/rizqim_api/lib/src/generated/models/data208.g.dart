// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data208.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data208 _$Data208FromJson(Map<String, dynamic> json) => Data208(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data208ToJson(Data208 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data347.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data347 _$Data347FromJson(Map<String, dynamic> json) => Data347(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta3.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data347ToJson(Data347 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

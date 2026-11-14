// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data344.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data344 _$Data344FromJson(Map<String, dynamic> json) => Data344(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta3.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data344ToJson(Data344 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

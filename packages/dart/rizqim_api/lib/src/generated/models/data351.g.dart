// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data351.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data351 _$Data351FromJson(Map<String, dynamic> json) => Data351(
  errors: (json['errors'] as List<dynamic>)
      .map((e) => Errors.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta3.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data351ToJson(Data351 instance) => <String, dynamic>{
  'errors': instance.errors,
  'meta': instance.meta,
};

// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'meta3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Meta3 _$Meta3FromJson(Map<String, dynamic> json) => Meta3(
  nextCursor: json['next_cursor'] as String?,
  perPage: (json['per_page'] as num).toInt(),
);

Map<String, dynamic> _$Meta3ToJson(Meta3 instance) => <String, dynamic>{
  'next_cursor': instance.nextCursor,
  'per_page': instance.perPage,
};

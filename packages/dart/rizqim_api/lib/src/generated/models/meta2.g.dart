// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'meta2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Meta2 _$Meta2FromJson(Map<String, dynamic> json) => Meta2(
  nextCursor: json['next_cursor'] as String?,
  perPage: (json['per_page'] as num).toInt(),
);

Map<String, dynamic> _$Meta2ToJson(Meta2 instance) => <String, dynamic>{
  'next_cursor': instance.nextCursor,
  'per_page': instance.perPage,
};

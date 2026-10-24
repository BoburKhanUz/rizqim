// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'meta4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Meta4 _$Meta4FromJson(Map<String, dynamic> json) => Meta4(
  nextCursor: json['next_cursor'] as String?,
  perPage: (json['per_page'] as num).toInt(),
);

Map<String, dynamic> _$Meta4ToJson(Meta4 instance) => <String, dynamic>{
  'next_cursor': instance.nextCursor,
  'per_page': instance.perPage,
};

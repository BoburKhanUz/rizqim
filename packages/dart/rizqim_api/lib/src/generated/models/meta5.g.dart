// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'meta5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Meta5 _$Meta5FromJson(Map<String, dynamic> json) => Meta5(
  nextCursor: json['next_cursor'] as String?,
  perPage: (json['per_page'] as num).toInt(),
);

Map<String, dynamic> _$Meta5ToJson(Meta5 instance) => <String, dynamic>{
  'next_cursor': instance.nextCursor,
  'per_page': instance.perPage,
};

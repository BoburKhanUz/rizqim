// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data2 _$Data2FromJson(Map<String, dynamic> json) => Data2(
  data: (json['data'] as List<dynamic>)
      .map((e) => Data.fromJson(e as Map<String, dynamic>))
      .toList(),
  nextCursor: json['next_cursor'] as String?,
  prevCursor: json['prev_cursor'] as String?,
  perPage: (json['per_page'] as num).toInt(),
);

Map<String, dynamic> _$Data2ToJson(Data2 instance) => <String, dynamic>{
  'data': instance.data,
  'next_cursor': instance.nextCursor,
  'prev_cursor': instance.prevCursor,
  'per_page': instance.perPage,
};
